<?php
/**
 * BILLSYS plugin: Facebook Messenger Payment Verification + Bot Settings
 *
 * Install:
 *   system/plugin/fbpaymentreview.php
 *   system/plugin/ui/fbpaymentreview.tpl   (add the settings block from fbpaymentreview_settings_block.tpl)
 *
 * This plugin approves/declines the submitted proof of payment. Approving it
 * recharges the customer (Package::rechargeUser) using their latest unpaid order,
 * or their current plan if there is no unpaid order.
 *
 * It also lets a SuperAdmin/Admin edit the Messenger bot settings, which are
 * stored in tbl_appconfig:
 *   fb_verify_token, fb_page_token, fb_app_secret, fb_graph_version
 */

// "Parent/Child" names make PHPNuxBill group the entries under one iPAYMES submenu.
register_menu(
    "iPAYMES/Payment Verification",
    true,
    "fbpaymentreview",
    "AFTER_CUSTOMERS",
    "ion ion-card"
);

/**
 * CSRF token that does not depend on anything stored in $_SESSION by this plugin.
 * It is derived from the logged-in admin and their session cookie, so it cannot be guessed
 * by another site, and it keeps working even if the host drops values written to $_SESSION.
 * Valid for 12-24 hours (two 12-hour buckets).
 */
function fbpr_csrf_token($admin, $offset = 0)
{
    $sid = session_id();
    if ($sid === '' && function_exists('session_name')) {
        $sid = (string) ($_COOKIE[session_name()] ?? '');
    }
    $adminId = (is_array($admin) || is_object($admin)) ? (string) ($admin['id'] ?? '') : '';
    $bucket = (int) floor(time() / 43200) - (int) $offset;
    return hash_hmac('sha256', $adminId . '|' . $sid . '|' . $bucket, 'fbpr-csrf-v1');
}

function fbpr_csrf_valid($token, $admin)
{
    $token = (string) $token;
    if ($token === '') {
        return false;
    }
    return hash_equals(fbpr_csrf_token($admin, 0), $token)
        || hash_equals(fbpr_csrf_token($admin, 1), $token);
}

/** Read one setting from tbl_appconfig ('' if missing). */
function fbpr_get_setting($db, $key)
{
    $stmt = $db->prepare('SELECT value FROM tbl_appconfig WHERE setting = ? LIMIT 1');
    $stmt->execute([$key]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? (string) $row['value'] : '';
}

/** Insert or update one setting in tbl_appconfig. */
function fbpr_set_setting($db, $key, $value)
{
    $stmt = $db->prepare('SELECT id FROM tbl_appconfig WHERE setting = ? LIMIT 1');
    $stmt->execute([$key]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $up = $db->prepare('UPDATE tbl_appconfig SET value = ? WHERE id = ?');
        $up->execute([$value, (int) $row['id']]);
    } else {
        $in = $db->prepare('INSERT INTO tbl_appconfig (setting, value) VALUES (?, ?)');
        $in->execute([$key, $value]);
    }
}

/** Show only the last 4 characters of a secret. */
function fbpr_mask($value)
{
    $value = (string) $value;
    if ($value === '') {
        return '';
    }
    return strlen($value) > 8 ? '••••••••' . substr($value, -4) : '••••••••';
}

/** Make sure the payment table supports receipt photos (older installs stored transaction IDs only). */
function fbpr_ensure_schema($db)
{
    $db->exec("CREATE TABLE IF NOT EXISTS tbl_fb_payment_submissions (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        psid VARCHAR(64) NOT NULL,
        customer_id INT NOT NULL,
        payment_method VARCHAR(20) NOT NULL DEFAULT 'BANK',
        transaction_id VARCHAR(100) NULL DEFAULT NULL,
        proof_image VARCHAR(255) NULL DEFAULT NULL,
        proof_hash CHAR(64) NULL DEFAULT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'pending',
        submitted_at DATETIME NOT NULL,
        reviewed_at DATETIME NULL DEFAULT NULL,
        reviewed_by VARCHAR(100) NULL DEFAULT NULL,
        review_notes VARCHAR(500) NULL DEFAULT NULL,
        UNIQUE KEY uq_fb_payment_transaction (transaction_id),
        UNIQUE KEY uq_fb_proof_hash (proof_hash),
        KEY idx_fb_submission_customer (customer_id),
        KEY idx_fb_submission_status (status),
        KEY idx_fb_submission_psid (psid)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    if (!$db->query("SHOW COLUMNS FROM tbl_fb_payment_submissions LIKE 'proof_image'")->fetch()) {
        $db->exec("ALTER TABLE tbl_fb_payment_submissions ADD COLUMN proof_image VARCHAR(255) NULL DEFAULT NULL");
    }
    if (!$db->query("SHOW COLUMNS FROM tbl_fb_payment_submissions LIKE 'proof_hash'")->fetch()) {
        $db->exec("ALTER TABLE tbl_fb_payment_submissions ADD COLUMN proof_hash CHAR(64) NULL DEFAULT NULL");
        $db->exec("ALTER TABLE tbl_fb_payment_submissions ADD UNIQUE KEY uq_fb_proof_hash (proof_hash)");
    }
    $col = $db->query("SHOW COLUMNS FROM tbl_fb_payment_submissions LIKE 'transaction_id'")->fetch(PDO::FETCH_ASSOC);
    if ($col && strtoupper((string) $col['Null']) === 'NO') {
        $db->exec("ALTER TABLE tbl_fb_payment_submissions MODIFY transaction_id VARCHAR(100) NULL DEFAULT NULL");
    }
}

/** Folders where proof-of-payment photos may be stored (first existing match wins). */
function fbpr_proof_dirs()
{
    global $UPLOAD_PATH;
    $dirs = [dirname(__DIR__) . '/uploads/fb_payments'];
    if (!empty($UPLOAD_PATH)) {
        $dirs[] = rtrim((string) $UPLOAD_PATH, '/\\') . '/fb_payments';
    }
    if (!empty($_SERVER['DOCUMENT_ROOT'])) {
        $dirs[] = rtrim((string) $_SERVER['DOCUMENT_ROOT'], '/\\') . '/system/uploads/fb_payments';
    }
    return array_values(array_unique($dirs));
}

/**
 * Send a stored proof-of-payment photo to the logged-in admin (the folder itself is not web-accessible).
 * Add &debug=1 to the image URL to see why a photo does not load (admin only).
 */
function fbpr_serve_proof($db, $submissionId, $debug = false)
{
    // Never let notices, warnings or stray output corrupt the image bytes.
    @ini_set('display_errors', '0');
    while (ob_get_level() > 0) {
        @ob_end_clean();
    }

    $stmt = $db->prepare('SELECT proof_image FROM tbl_fb_payment_submissions WHERE id = ? LIMIT 1');
    $stmt->execute([(int) $submissionId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $file = $row ? basename((string) $row['proof_image']) : '';

    $valid = (bool) preg_match('/^[a-f0-9]{32}\.(jpg|png|webp|gif)$/', $file);
    $path = '';
    $tried = [];
    if ($valid) {
        foreach (fbpr_proof_dirs() as $dir) {
            $candidate = $dir . '/' . $file;
            $tried[] = $candidate;
            if (is_file($candidate) && is_readable($candidate)) {
                $path = $candidate;
                break;
            }
        }
    }

    if ($debug) {
        header('Content-Type: text/plain; charset=utf-8');
        echo "submission id : " . (int) $submissionId . "\n";
        echo "proof_image   : " . ($row ? ($row['proof_image'] !== null && $row['proof_image'] !== '' ? $row['proof_image'] : '(empty)') : '(no such submission)') . "\n";
        echo "valid name    : " . ($valid ? 'yes' : 'NO (expected 32 hex chars + jpg/png/webp/gif)') . "\n";
        echo "php user      : " . (function_exists('posix_geteuid') && function_exists('posix_getpwuid') ? (posix_getpwuid(posix_geteuid())['name'] ?? '?') : get_current_user()) . "\n";
        echo "open_basedir  : " . (ini_get('open_basedir') ?: '(none)') . "\n\n";
        foreach (fbpr_proof_dirs() as $dir) {
            $f = $dir . '/' . $file;
            echo $f . "\n";
            echo "  folder exists : " . (is_dir($dir) ? 'yes' : 'NO') . "\n";
            echo "  file exists   : " . (is_file($f) ? 'yes' : 'NO') . "\n";
            echo "  readable      : " . (is_readable($f) ? 'yes' : 'NO') . "\n";
            if (is_file($f)) {
                echo "  size / perms  : " . filesize($f) . " bytes / " . substr(sprintf('%o', fileperms($f)), -4) . "\n";
            }
        }
        exit;
    }

    if ($path === '') {
        error_log('fbpaymentreview: proof not found for submission ' . (int) $submissionId . '; tried: ' . implode(', ', $tried));
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        exit('Proof of payment not found.');
    }

    $mimes = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif'];
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

    header('Content-Type: ' . $mimes[$ext]);
    header('Content-Length: ' . filesize($path));
    header('Content-Disposition: inline; filename="proof-' . (int) $submissionId . '.' . $ext . '"');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, max-age=3600');
    readfile($path);
    exit;
}

/**
 * Decide which plan to recharge for a customer.
 * 1) latest unpaid order (same row PayMongo uses), 2) current/last plan (renewal).
 */
function fbpr_resolve_plan($customerId)
{
    $customer = ORM::for_table('tbl_customers')->find_one($customerId);
    if (!$customer) {
        return null;
    }

    $order = ORM::for_table('tbl_payment_gateway')
        ->where('username', $customer['username'])
        ->where('status', 1)
        ->order_by_desc('id')
        ->find_one();

    if ($order && !empty($order['plan_id'])) {
        return [
            'plan_id' => $order['plan_id'],
            'routers' => $order['routers'],
            'trx_id'  => $order['id'],
        ];
    }

    $last = ORM::for_table('tbl_user_recharges')
        ->where('customer_id', $customerId)
        ->order_by_desc('id')
        ->find_one();

    if ($last && !empty($last['plan_id'])) {
        return [
            'plan_id' => $last['plan_id'],
            'routers' => $last['routers'],
            'trx_id'  => null,
        ];
    }

    return null;
}

/** Default Messenger text sent after a proof of payment is approved and the customer is recharged. */
function fbpr_default_approved_template()
{
    return 'Hi [[name]], we received your payment of [[plan_price]] via [[payment_gateway]] [[payment_channel]]. '
        . 'Your [[plan_name]] subscription is now active and will be valid from [[billing_period]]. '
        . 'Thank you for choosing [[company_name]]!';
}

/**
 * Load a message template from PHPNuxBill's notification files:
 * system/uploads/notifications.json (edited in Settings > Notifications),
 * falling back to system/uploads/notifications.default.json.
 */
function fbpr_get_notif_template($key)
{
    global $UPLOAD_PATH;

    if (class_exists('Lang') && method_exists('Lang', 'getNotifText')) {
        $text = (string) Lang::getNotifText($key);
        if ($text !== '') {
            return $text;
        }
    }

    $base = !empty($UPLOAD_PATH) ? rtrim((string) $UPLOAD_PATH, '/\\') : dirname(__DIR__) . '/uploads';
    foreach (['notifications.json', 'notifications.default.json'] as $file) {
        $path = $base . '/' . $file;
        if (is_file($path) && is_readable($path)) {
            $json = json_decode((string) file_get_contents($path), true);
            if (is_array($json) && !empty($json[$key])) {
                return (string) $json[$key];
            }
        }
    }
    return '';
}

/** Replace [[placeholders]] in a template. Unknown placeholders are left empty. */
function fbpr_render_template($template, array $vars)
{
    $out = (string) $template;
    foreach ($vars as $key => $value) {
        $out = str_replace('[[' . $key . ']]', (string) $value, $out);
    }
    return preg_replace('/\[\[[a-z_]+\]\]/i', '', $out);
}

/** Format an amount like the rest of the system does (falls back to a plain number). */
function fbpr_money($amount)
{
    global $config;
    if (class_exists('Lang') && method_exists('Lang', 'moneyFormat')) {
        return Lang::moneyFormat($amount);
    }
    return trim((string) ($config['currency_code'] ?? '') . ' ' . number_format((float) $amount, 2));
}

/**
 * Build the placeholder values for the "payment approved" message.
 * Call this AFTER Package::rechargeUser() so the new expiry is available.
 */
function fbpr_build_approved_vars($user, $recharge, $orderPrice, $gatewayLabel, $channel)
{
    global $config;

    $plan = ORM::for_table('tbl_plans')->find_one($recharge['plan_id']);

    $price = $orderPrice !== null ? $orderPrice : ($plan ? $plan['price'] : 0);

    $period = '';
    $rec = ORM::for_table('tbl_user_recharges')
        ->where('customer_id', $user['id'])
        ->where('plan_id', $recharge['plan_id'])
        ->order_by_desc('id')
        ->find_one();
    if ($rec) {
        $from = trim($rec['recharged_on'] . ' ' . $rec['recharged_time']);
        $to = trim($rec['expiration'] . ' ' . $rec['time']);
        $fromTs = strtotime($from);
        $toTs = strtotime($to);
        if ($fromTs && $toTs) {
            $period = date('M d, Y h:i A', $fromTs) . ' to ' . date('M d, Y h:i A', $toTs);
        }
    }

    // Latest transaction row = the invoice that rechargeUser() just created.
    $tx = ORM::for_table('tbl_transactions')
        ->where('username', $user['username'])
        ->order_by_desc('id')
        ->find_one();

    $txDate = '';
    if ($tx) {
        $ts = strtotime(trim($tx['recharged_on'] . ' ' . $tx['recharged_time']));
        $txDate = $ts ? date('M d, Y h:i A', $ts) : (string) $tx['recharged_on'];
    }
    $expTs = $tx ? strtotime(trim($tx['expiration'] . ' ' . $tx['time'])) : false;

    return [
        'name'            => $user['fullname'] !== '' ? $user['fullname'] : $user['username'],
        'username'        => $user['username'],
        'user_name'       => $user['username'],
        'invoice'         => $tx ? $tx['invoice'] : '',
        'date'            => $txDate,
        'type'            => $tx ? $tx['type'] : ($plan ? $plan['type'] : ''),
        'expired_date'    => $expTs ? date('M d, Y h:i A', $expTs) : '',
        'address'         => (string) ($config['address'] ?? ''),
        'phone'           => (string) ($config['phone'] ?? ''),
        'footer'          => (string) ($config['note'] ?? ''),
        'company'         => (string) ($config['CompanyName'] ?? ''),
        'plan_price'      => fbpr_money($price),
        'plan_name'       => $plan ? $plan['name_plan'] : '',
        'payment_gateway' => $gatewayLabel,
        'payment_channel' => strtoupper((string) $channel),
        'billing_period'  => $period,
        'company_name'    => (string) ($config['CompanyName'] ?? ''),
    ];
}

function fbpaymentreview()
{
    global $ui, $routes, $config;

    _admin();

    $admin = Admin::_info();
    $ui->assign('_system_menu', 'fbpaymentreview');
    $ui->assign('_admin', $admin);
    $ui->assign('routes', $routes);
    $ui->assign('_title', 'Messenger Payment Verification');

    $db = ORM::get_db();

    // Only SuperAdmin / Admin may view or change the bot settings.
    $canEditSettings = is_array($admin) || is_object($admin)
        ? in_array((string) ($admin['user_type'] ?? ''), ['SuperAdmin', 'Admin'], true)
        : false;

    // Safe fallback in case the Messenger webhook has not created or upgraded the table yet.
    fbpr_ensure_schema($db);

    // Proof of payment viewer: /?_route=plugin/fbpaymentreview&proof=<submission id>
    if (isset($_GET['proof'])) {
        $proofId = filter_var($_GET['proof'], FILTER_VALIDATE_INT);
        if (!$proofId) {
            http_response_code(400);
            exit('Invalid request.');
        }
        fbpr_serve_proof($db, $proofId, isset($_GET['debug']));
    }

    if (session_status() !== PHP_SESSION_ACTIVE) {
        @session_start();
    }
    $csrfToken = fbpr_csrf_token($admin);

    $notice = '';
    $error = '';
    $settingsNotice = '';
    $settingsError = '';

    // Which list is being viewed. A POST form cannot carry ?status=..., so it sends return_status.
    $validStatuses = ['pending', 'approved', 'declined', 'all'];
    $statusFilter = (string) ($_GET['status'] ?? '');
    if ($statusFilter === '' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $statusFilter = (string) _post('return_status');
    }
    if (!in_array($statusFilter, $validStatuses, true)) {
        $statusFilter = 'pending';
    }
    $reviewDone = false;

    // Message left by the redirect that follows a successful approve / decline.
    if (!empty($_SESSION['fbpaymentreview_flash'])) {
        $notice = (string) $_SESSION['fbpaymentreview_flash'];
        unset($_SESSION['fbpaymentreview_flash']);
    }

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $postedToken = (string) ($_POST['csrf_token'] ?? '');
        if (!fbpr_csrf_valid($postedToken, $admin)) {
            $error = 'Security check failed. Refresh the page and try again.';
        } elseif ((string) _post('action') === 'save_settings') {
            // ---------------- SAVE MESSENGER BOT SETTINGS ----------------
            if (!$canEditSettings) {
                $settingsError = 'You do not have permission to change these settings.';
            } else {
                $verifyToken = trim((string) _post('fb_verify_token'));
                $graphVersion = trim((string) _post('fb_graph_version'));
                $pageToken = trim((string) _post('fb_page_token'));
                $appSecret = trim((string) _post('fb_app_secret'));

                if ($graphVersion === '') {
                    $graphVersion = 'v23.0';
                }

                $hasPageToken = fbpr_get_setting($db, 'fb_page_token') !== '';
                $hasAppSecret = fbpr_get_setting($db, 'fb_app_secret') !== '';

                if ($verifyToken === '' || strlen($verifyToken) < 8) {
                    $settingsError = 'Verify token is required (at least 8 characters).';
                } elseif (!preg_match('/^v\d+\.\d+$/', $graphVersion)) {
                    $settingsError = 'Graph API version must look like v23.0.';
                } elseif ($pageToken === '' && !$hasPageToken) {
                    $settingsError = 'Page access token is required.';
                } elseif ($appSecret === '' && !$hasAppSecret) {
                    $settingsError = 'App secret is required.';
                } else {
                    try {
                        $db->beginTransaction();
                        fbpr_set_setting($db, 'fb_verify_token', $verifyToken);
                        fbpr_set_setting($db, 'fb_graph_version', $graphVersion);
                        // Leaving a secret field blank keeps the saved value.
                        if ($pageToken !== '') {
                            fbpr_set_setting($db, 'fb_page_token', $pageToken);
                        }
                        if ($appSecret !== '') {
                            fbpr_set_setting($db, 'fb_app_secret', $appSecret);
                        }
                        $db->commit();
                        $settingsNotice = 'Messenger bot settings saved.';
                    } catch (Throwable $e) {
                        if ($db->inTransaction()) {
                            $db->rollBack();
                        }
                        error_log('fbpaymentreview: saving settings failed: ' . $e->getMessage());
                        $settingsError = 'Could not save the settings. Please try again.';
                    }
                }
            }
        } elseif ((string) _post('action') === 'setup_menu') {
            // ---------------- APPLY MESSENGER GET STARTED + PERSISTENT MENU ----------------
            if (!$canEditSettings) {
                $error = 'You do not have permission to change these settings.';
            } elseif (!function_exists('fb_setup_profile')) {
                $error = 'The Messenger bot plugin (fb_bot.php) is not loaded.';
            } else {
                list($ok, $msg) = fb_setup_profile();
                if ($ok) {
                    $notice = $msg;
                } else {
                    $error = $msg;
                }
            }
        } else {
            // ---------------- APPROVE / DECLINE A PAYMENT SUBMISSION ----------------
            $submissionId = filter_var(_post('submission_id'), FILTER_VALIDATE_INT);
            $action = (string) _post('action');
            $notes = trim((string) _post('review_notes'));

            if (!$submissionId || !in_array($action, ['approve', 'decline'], true)) {
                $error = 'Invalid review request.';
            } elseif (function_exists('mb_strlen') && mb_strlen($notes) > 500) {
                $error = 'Review notes must be 500 characters or fewer.';
            } elseif (strlen($notes) > 500) {
                $error = 'Review notes must be 500 characters or fewer.';
            } elseif ($action === 'decline' && $notes === '') {
                $error = 'Please enter a reason before declining this submission.';
            } else {
                try {
                    $db->beginTransaction();

                    $stmt = $db->prepare(
                        "SELECT id, customer_id, psid, payment_method, transaction_id, status
                         FROM tbl_fb_payment_submissions
                         WHERE id = ?
                         FOR UPDATE"
                    );
                    $stmt->execute([(int) $submissionId]);
                    $submission = $stmt->fetch(PDO::FETCH_ASSOC);

                    if (!$submission) {
                        $db->rollBack();
                        $error = 'Payment submission not found.';
                    } elseif ($submission['status'] !== 'pending') {
                        $db->rollBack();
                        $error = 'This submission has already been reviewed.';
                    } else {
                        $newStatus = $action === 'approve' ? 'approved' : 'declined';

                        // Work out the plan BEFORE touching anything, so a failure leaves it pending.
                        $recharge = null;
                        $approvedVars = null;
                        if ($action === 'approve') {
                            $recharge = fbpr_resolve_plan((int) $submission['customer_id']);
                            if (!$recharge) {
                                $db->rollBack();
                                $error = 'No unpaid order or previous plan found for this customer, so there is nothing to recharge. Create the order first, or recharge manually.';
                            }
                        }

                        if ($error === '') {
                            $adminName = '';
                            if (is_array($admin) || is_object($admin)) {
                                $adminName = (string) ($admin['username'] ?? $admin['fullname'] ?? $admin['id'] ?? 'admin');
                            }
                            $update = $db->prepare(
                                "UPDATE tbl_fb_payment_submissions
                                 SET status = ?, reviewed_at = NOW(), reviewed_by = ?, review_notes = ?
                                 WHERE id = ? AND status = 'pending'"
                            );
                            $update->execute([
                                $newStatus,
                                substr($adminName, 0, 100),
                                $notes !== '' ? $notes : null,
                                (int) $submissionId
                            ]);

                            if ($update->rowCount() !== 1) {
                                throw new RuntimeException('Submission was already updated.');
                            }

                            // ---- RECHARGE (same flow as paymongo_mark_paid) ----
                            if ($action === 'approve') {
                                $user = ORM::for_table('tbl_customers')->find_one((int) $submission['customer_id']);
                                if (!$user) {
                                    throw new RuntimeException('Customer not found.');
                                }

                                $channel = strtolower((string) $submission['payment_method']) ?: 'messenger';

                                if (!Package::rechargeUser(
                                    $user['id'],
                                    $recharge['routers'],
                                    $recharge['plan_id'],
                                    'Messenger',
                                    $channel
                                )) {
                                    throw new RuntimeException('Package::rechargeUser failed for submission ' . (int) $submissionId);
                                }

                                // Close the pending order so PayMongo / another approval can't recharge it twice.
                                if (!empty($recharge['trx_id'])) {
                                    $trx = ORM::for_table('tbl_payment_gateway')->find_one($recharge['trx_id']);
                                    if ($trx && (int) $trx['status'] !== 2) {
                                        $trx->pg_paid_response = json_encode([
                                            'source'        => 'fbpaymentreview',
                                            'submission_id' => (int) $submissionId,
                                            'reference'     => $submission['transaction_id'],
                                            'approved_by'   => $adminName,
                                        ]);
                                        $trx->payment_method  = 'Messenger Proof';
                                        $trx->payment_channel = $channel;
                                        $trx->paid_date       = date('Y-m-d H:i:s');
                                        $trx->status          = 2;
                                        $trx->save();
                                    }
                                }

                                // Values for the customer's "payment received" message.
                                $orderPrice = !empty($recharge['trx_id']) && isset($trx) && $trx ? $trx['price'] : null;
                                $approvedVars = fbpr_build_approved_vars($user, $recharge, $orderPrice, 'Messenger', $channel);
                            }

                            $db->commit();

                            $reviewDone = true;
                            $notice = $action === 'approve'
                                ? 'Submission approved and the customer has been recharged.'
                                : 'Submission declined.';

                            // Notify the customer in Messenger if fb_send() is loaded by the bot.
                            if (function_exists('fb_send')) {
                                $methodLabel = function_exists('fb_method_label')
                                    ? fb_method_label($submission['payment_method'])
                                    : (string) $submission['payment_method'];

                                $ref = '#' . $submission['id'];
                                if ($action === 'approve') {
                                    // 1) custom override in tbl_appconfig, 2) "invoice_paid" from notifications.json
                                    // (or notifications.default.json), 3) built-in short text.
                                    $template = fbpr_get_setting($db, 'fb_approved_template');
                                    if ($template === '') {
                                        $template = fbpr_get_notif_template('invoice_paid');
                                    }
                                    if ($template === '') {
                                        $template = fbpr_default_approved_template();
                                    }
                                    $message = fbpr_render_template($template, $approvedVars ?: []);
                                    // Messenger shows WhatsApp-style *bold* asterisks literally, and wants \n line breaks.
                                    $message = str_replace(["\r\n", "\r", '*'], ["\n", "\n", ''], $message);
                                } else {
                                    $message = "PAYMENT SUBMISSION DECLINED\n\n"
                                        . "We could not approve your " . $methodLabel . " proof of payment (" . $ref . ").\n"
                                        . "Reason: " . $notes . "\n\n"
                                        . "Please check your proof of payment and send it again, or contact customer support if you need assistance.";
                                }
                                try {
                                    fb_send((string) $submission['psid'], $message);
                                } catch (Throwable $notifyError) {
                                    error_log('fbpaymentreview: Messenger notification failed: ' . $notifyError->getMessage());
                                }
                            }
                        }
                    }
                } catch (Throwable $e) {
                    if ($db->inTransaction()) {
                        $db->rollBack();
                    }
                    error_log('fbpaymentreview: review failed: ' . $e->getMessage());
                    $error = 'Could not save the review or recharge the customer. Nothing was changed. Please try again.';
                }
            }
        }
    }

    // Post/Redirect/Get: keeps the chosen filter and stops a refresh from re-submitting the review.
    if ($reviewDone) {
        $_SESSION['fbpaymentreview_flash'] = $notice;
        $base = defined('U') ? U : (defined('APP_URL') ? rtrim(APP_URL, '/') . '/?_route=' : '?_route=');
        header('Location: ' . $base . 'plugin/fbpaymentreview&status=' . urlencode($statusFilter));
        exit;
    }

    $sql = "SELECT s.id, s.customer_id, s.psid, s.payment_method, s.transaction_id, s.proof_image,
                   s.status, s.submitted_at, s.reviewed_at, s.reviewed_by, s.review_notes,
                   c.username, c.fullname
            FROM tbl_fb_payment_submissions s
            LEFT JOIN tbl_customers c ON c.id = s.customer_id";
    if ($statusFilter !== 'all') {
        $stmt = $db->prepare($sql . " WHERE s.status = ? ORDER BY s.submitted_at ASC LIMIT 500");
        $stmt->execute([$statusFilter]);
    } else {
        $stmt = $db->query($sql . " ORDER BY (s.status = 'pending') DESC, s.submitted_at DESC LIMIT 500");
    }

    // Settings shown in the form. Secrets are masked and never sent back in full.
    if ($canEditSettings) {
        $savedPage = fbpr_get_setting($db, 'fb_page_token');
        $savedSecret = fbpr_get_setting($db, 'fb_app_secret');
        $savedVersion = fbpr_get_setting($db, 'fb_graph_version');
        $ui->assign('fbSettings', [
            'verify_token' => fbpr_get_setting($db, 'fb_verify_token'),
            'graph_version' => $savedVersion !== '' ? $savedVersion : 'v23.0',
            'page_token_masked' => fbpr_mask($savedPage),
            'app_secret_masked' => fbpr_mask($savedSecret),
            'page_token_set' => $savedPage !== '',
            'app_secret_set' => $savedSecret !== '',
        ]);
        $webhookBase = defined('APP_URL') ? rtrim(APP_URL, '/') : '';
        $ui->assign('fbWebhookUrl', $webhookBase . '/?_route=plugin/fb_bot');
    }
    $ui->assign('fbCanEditSettings', $canEditSettings);
    $ui->assign('fbSettingsNotice', $settingsNotice);
    $ui->assign('fbSettingsError', $settingsError);

    $ui->assign('fbPaymentSubmissions', $stmt->fetchAll(PDO::FETCH_ASSOC));
    $ui->assign('fbPaymentStatus', $statusFilter);
    $ui->assign('fbPaymentCsrf', $csrfToken);
    $ui->assign('fbPaymentNotice', $notice);
    $ui->assign('fbPaymentError', $error);
    $ui->display('fbpaymentreview.tpl');
}