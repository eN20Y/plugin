<?php
/**
 * Facebook Messenger chatbot for BILLSYS / PHPNuxBill.
 * Webhook: https://YOUR-DOMAIN/?_route=plugin/fb_bot
 * Commands: HELP, LINK <username>, STATUS, PAYMENT <GCASH|MAYA|BANK> (then send a photo of your proof of payment), UNLINK
 *
 * SECURITY: Username-only linking does not prove account ownership. Anyone who
 * knows a customer's username may be able to link their own Facebook account.
 * Use a strong/unpredictable username or add a verification step before production.
 */

// ---------------- SETTINGS: loaded from the database (tbl_appconfig) ----------------
// Add these rows in tbl_appconfig (columns: setting, value):
//   fb_verify_token  - any long random text. Type the SAME text in Meta when you add the webhook.
//   fb_page_token    - Meta app > Messenger > generate token for your Page. Keep it secret.
//   fb_app_secret    - Meta app > Settings > Basic. Used to check messages really come from Meta.
//   fb_graph_version - for example v23.0 (optional, defaults to v23.0)
// ------------------------------------------------------------------------------------

/**
 * Read a setting from tbl_appconfig. All fb_* settings are loaded once per request.
 */
function fb_cfg($key, $default = '')
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            $rows = ORM::for_table('tbl_appconfig')
                ->where_in('setting', ['fb_verify_token', 'fb_page_token', 'fb_app_secret', 'fb_graph_version'])
                ->find_many();
            foreach ($rows as $row) {
                $cache[$row['setting']] = trim((string) $row['value']);
            }
        } catch (Exception $e) {
            error_log('fb_bot: could not read tbl_appconfig: ' . $e->getMessage());
        }
    }
    return (isset($cache[$key]) && $cache[$key] !== '') ? $cache[$key] : $default;
}


function fb_install()
{
    $db = ORM::get_db();
    $db->exec("CREATE TABLE IF NOT EXISTS tbl_fb_links (
        id INT AUTO_INCREMENT PRIMARY KEY,
        psid VARCHAR(64) NOT NULL,
        customer_id INT NOT NULL,
        linked_at DATETIME NOT NULL,
        last_in DATETIME NULL DEFAULT NULL,
        UNIQUE KEY uq_psid (psid),
        KEY idx_customer_id (customer_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->exec("CREATE TABLE IF NOT EXISTS tbl_fb_fails (
        psid VARCHAR(64) NOT NULL PRIMARY KEY,
        fails INT NOT NULL DEFAULT 0,
        locked_until DATETIME NULL DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Upgrade an existing installation without removing data.
    $has = $db->query("SHOW COLUMNS FROM tbl_fb_links LIKE 'last_in'")->fetch();
    if (!$has) {
        $db->exec("ALTER TABLE tbl_fb_links ADD COLUMN last_in DATETIME NULL DEFAULT NULL");
    }

    $db->exec("CREATE TABLE IF NOT EXISTS tbl_fb_reminders (
        recharge_id INT NOT NULL,
        expiration DATE NOT NULL,
        days_before INT NOT NULL,
        sent_at DATETIME NOT NULL,
        PRIMARY KEY (recharge_id, expiration, days_before)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Customer-submitted GCash, Maya, and bank receipt photos are stored for manual verification only.
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

    // Upgrade an older installation that stored transaction IDs only.
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

    // Remembers which payment method a customer chose while we wait for the receipt photo.
    $db->exec("CREATE TABLE IF NOT EXISTS tbl_fb_payment_pending (
        psid VARCHAR(64) NOT NULL PRIMARY KEY,
        payment_method VARCHAR(20) NOT NULL,
        created_at DATETIME NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Maintenance / outage notices sent from the admin page, and who received each one.
    $db->exec("CREATE TABLE IF NOT EXISTS tbl_fb_announcements (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        kind VARCHAR(20) NOT NULL DEFAULT 'notice',
        title VARCHAR(150) NOT NULL,
        message TEXT NOT NULL,
        created_by VARCHAR(100) NULL DEFAULT NULL,
        created_at DATETIME NOT NULL,
        expires_at DATETIME NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'active'
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $db->exec("CREATE TABLE IF NOT EXISTS tbl_fb_announcement_log (
        announcement_id BIGINT UNSIGNED NOT NULL,
        psid VARCHAR(64) NOT NULL,
        customer_id INT NOT NULL DEFAULT 0,
        status VARCHAR(20) NOT NULL DEFAULT 'queued',
        attempted_at DATETIME NULL DEFAULT NULL,
        error VARCHAR(255) NULL DEFAULT NULL,
        PRIMARY KEY (announcement_id, psid),
        KEY idx_fb_ann_psid_status (psid, status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function fb_send($psid, $message, array $quickReplies = [])
{
    $GLOBALS['fb_last_send_error'] = null; // filled in when a send fails (used by the notice broadcaster)
    $pageToken = fb_cfg('fb_page_token');
    if (!function_exists('curl_init') || $pageToken === '') {
        error_log('fb_bot: cURL or fb_page_token is not configured.');
        $GLOBALS['fb_last_send_error'] = ['http' => 0, 'code' => 0, 'subcode' => 0, 'message' => 'cURL or Page Access Token is not configured'];
        return false;
    }

    $version = trim(fb_cfg('fb_graph_version', 'v23.0'));
    $url = 'https://graph.facebook.com/' . ($version !== '' ? $version . '/' : '') . 'me/messages';
    $body = ['text' => mb_substr((string) $message, 0, 1900, 'UTF-8')];
    if ($quickReplies) {
        $body['quick_replies'] = array_slice(array_values($quickReplies), 0, 13);
    }
    $payload = json_encode([
        'recipient' => ['id' => (string) $psid],
        'messaging_type' => 'RESPONSE',
        'message' => $body,
    ], JSON_UNESCAPED_UNICODE);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        // Token in the header keeps it out of URLs and server logs.
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $pageToken,
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
    ]);
    $response = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($response === false || $status < 200 || $status >= 300) {
        error_log('fb_bot: send failed; HTTP ' . $status . ($error ? '; ' . $error : '') . '; response=' . (string) $response);
        $err = json_decode((string) $response, true);
        $GLOBALS['fb_last_send_error'] = [
            'http' => $status,
            'code' => (int) ($err['error']['code'] ?? 0),
            'subcode' => (int) ($err['error']['error_subcode'] ?? 0),
            'message' => (string) ($err['error']['message'] ?? ($error ?: 'Send failed')),
        ];
        return false;
    }
    return true;
}

/**
 * Tappable buttons shown under a reply. Each button sends its payload back to the bot,
 * and the payload is handled exactly like a typed command (e.g. "PAYMENT GCASH").
 */
function fb_quick_replies($incoming, $isLinked)
{
    if (!$isLinked) {
        return [];
    }
    $btn = function ($title, $payload) {
        return ['content_type' => 'text', 'title' => $title, 'payload' => $payload];
    };

    $text = strtoupper(trim(preg_replace('/\s+/u', ' ', (string) $incoming)));
    $parts = $text === '' ? [''] : explode(' ', $text);
    $command = $parts[0];

    if ($command === 'PAYMENT') {
        // Valid method chosen: the bot is now waiting for the photo, so no buttons.
        if (count($parts) === 2 && in_array($parts[1], ['GCASH', 'MAYA', 'BANK'], true)) {
            return [];
        }
        return [
            $btn('GCash', 'PAYMENT GCASH'),
            $btn('Maya', 'PAYMENT MAYA'),
            $btn('Bank', 'PAYMENT BANK'),
        ];
    }
    if ($command === 'UNLINK') {
        return [];
    }
    return [
        $btn('📊 Status', 'STATUS'),
        $btn('💳 Pay', 'PAYMENT'),
        $btn('❓ Help', 'HELP'),
    ];
}

/**
 * One-time setup of the Messenger "Get Started" button and the persistent menu.
 * Returns [bool ok, string message].
 */
function fb_setup_profile()
{
    $pageToken = fb_cfg('fb_page_token');
    if (!function_exists('curl_init') || $pageToken === '') {
        return [false, 'cURL or the Page Access Token is not configured.'];
    }
    $version = trim(fb_cfg('fb_graph_version', 'v23.0'));
    $url = 'https://graph.facebook.com/' . ($version !== '' ? $version . '/' : '') . 'me/messenger_profile';
    $payload = json_encode([
        'get_started' => ['payload' => 'HELP'],
        'greeting' => [[
            'locale' => 'default',
            'text' => 'Welcome! Tap Get Started to link your account, check your status and send proof of payment.',
        ]],
        'persistent_menu' => [[
            'locale' => 'default',
            'composer_input_disabled' => false,
            'call_to_actions' => [
                ['type' => 'postback', 'title' => '📊 Status', 'payload' => 'STATUS'],
                ['type' => 'postback', 'title' => '💳 Pay', 'payload' => 'PAYMENT'],
                ['type' => 'postback', 'title' => '❓ Help', 'payload' => 'HELP'],
            ],
        ]],
    ], JSON_UNESCAPED_UNICODE);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $pageToken],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
    ]);
    $response = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response !== false && $status >= 200 && $status < 300) {
        return [true, 'Messenger menu and Get Started button applied.'];
    }
    $detail = '';
    $json = json_decode((string) $response, true);
    if (is_array($json) && !empty($json['error']['message'])) {
        $detail = ': ' . $json['error']['message'];
    }
    error_log('fb_bot: messenger_profile failed; HTTP ' . $status . '; response=' . (string) $response);
    return [false, 'Meta rejected the request (HTTP ' . $status . ')' . $detail];
}

/** The text customers receive for a maintenance / outage notice. */
function fb_announcement_text($kind, $title, $message)
{
    $heads = [
        'outage' => '⚠️ SERVICE OUTAGE',
        'maintenance' => '🚧 SCHEDULED MAINTENANCE',
        'restored' => '✅ SERVICE RESTORED',
        'notice' => '📢 SERVICE NOTICE',
    ];
    $head = $heads[$kind] ?? $heads['notice'];
    return $head . "\n\n" . trim((string) $title) . "\n\n" . trim((string) $message)
        . "\n\nThank you for your patience and understanding.";
}

/**
 * Notices are only sent while the customer's 24-hour Messenger window is open. Anyone who was
 * outside it is "queued" and receives the notice right after their next message to the bot.
 */
function fb_deliver_announcements($psid)
{
    $db = ORM::get_db();
    $now = date('Y-m-d H:i:s');
    $stmt = $db->prepare(
        "SELECT l.announcement_id, a.kind, a.title, a.message
         FROM tbl_fb_announcement_log l
         JOIN tbl_fb_announcements a ON a.id = l.announcement_id
         WHERE l.psid = ? AND l.status = 'queued' AND a.status = 'active' AND a.expires_at > ?
         ORDER BY l.announcement_id ASC LIMIT 3"
    );
    $stmt->execute([(string) $psid, $now]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $row) {
        // Claim the row first so a repeated webhook delivery cannot send it twice.
        $claim = $db->prepare("UPDATE tbl_fb_announcement_log SET status = 'sent', attempted_at = ? WHERE announcement_id = ? AND psid = ? AND status = 'queued'");
        $claim->execute([$now, (int) $row['announcement_id'], (string) $psid]);
        if ($claim->rowCount() !== 1) {
            continue;
        }
        if (!fb_send($psid, fb_announcement_text($row['kind'], $row['title'], $row['message']))) {
            $err = $GLOBALS['fb_last_send_error']['message'] ?? 'Send failed';
            $fail = $db->prepare("UPDATE tbl_fb_announcement_log SET status = 'failed', error = ? WHERE announcement_id = ? AND psid = ?");
            $fail->execute([substr((string) $err, 0, 250), (int) $row['announcement_id'], (string) $psid]);
        }
    }
}

function fb_help()
{
    return "Hello! Welcome to BILLSYS Customer Assistance.\n\n"
        . "Here are the commands you can use:\n\n"
        . "🔗 LINK <username> - Link your account\n"
        . "📌 STATUS - View your account status\n"
        . "💳 PAYMENT GCASH - Send proof of payment (GCash)\n"
        . "💳 PAYMENT MAYA - Send proof of payment (Maya)\n"
        . "💳 PAYMENT BANK - Send proof of payment (Bank)\n"
        . "🚫 UNLINK - Unlink your account\n"
        . "❓ HELP - Show this list\n\n"
        . "Thank you for using our service!";
}

/**
 * Build the STATUS message in the same layout as the sample screenshot:
 *
 * Hello! Here's your account status:
 *
 * 🪪 Account No: 00012345
 * 👤 Name: Juan Dela Cruz
 * 📶 Plan: 50 Mbps
 * 📌 Status: ✅ Active
 * 💰 Balance: ₱0.00
 * 📅 Due Date: Oct 25, 2026
 *
 * Thank you for using our service!
 */
function fb_account_no($customer)
{
    // Only returns a value if the client actually has an account number; otherwise ''.
    return trim((string) ($customer['account_number'] ?? ''));
}

function fb_name_line($customer)
{
    // Full name only (no username fallback). Skipped if the client has no full name.
    $name = trim((string) ($customer['fullname'] ?? ''));
    return $name !== '' ? "👤 Name: {$name}\n" : '';
}

function fb_account_line($customer)
{
    $no = fb_account_no($customer);
    return $no !== '' ? "🪪 Account No: {$no}\n" : '';
}

function fb_method_label($method)
{
    $labels = ['GCASH' => 'GCash', 'MAYA' => 'Maya', 'BANK' => 'Bank Transfer'];
    return $labels[strtoupper((string) $method)] ?? (string) $method;
}

/**
 * Build the PAYMENT confirmation in the same layout as STATUS:
 *
 * Hello! Here's your payment submission:
 *
 * 🪪 Account No: 00012345
 * 👤 Name: Juan Dela Cruz
 * 💳 Payment Method: GCash
 * 🖼️ Proof of Payment: Photo received
 * 📌 Status: ⏳ Pending Verification
 * 📅 Submitted: Oct 16, 2026 3:45 PM
 *
 * Thank you! Our staff will verify your payment.
 */
function fb_payment_message($customer, $method, $submittedAt)
{
    return "Hello! Here's your payment submission:\n\n"
        . fb_account_line($customer)
        . fb_name_line($customer)
        . '💳 Payment Method: ' . fb_method_label($method) . "\n"
        . "🖼️ Proof of Payment: Photo received\n"
        . "📌 Status: ⏳ Pending Verification\n"
        . '📅 Submitted: ' . date('M d, Y g:i A', $submittedAt) . "\n\n"
        . "Thank you! Our staff will verify your payment. Your balance and service status will be updated once the payment is confirmed.";
}

/** Folder where receipt photos are stored (blocked from direct web access). */
function fb_proof_dir()
{
    $dir = dirname(__DIR__) . '/uploads/fb_payments';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    if (is_dir($dir)) {
        if (!file_exists($dir . '/.htaccess')) {
            @file_put_contents($dir . '/.htaccess', "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n");
        }
        if (!file_exists($dir . '/index.html')) {
            @file_put_contents($dir . '/index.html', '');
        }
    }
    return $dir;
}

/**
 * Download a photo sent in Messenger. Only Facebook CDN hosts are allowed.
 * Returns [binary, extension] or null.
 */
function fb_download_image($url)
{
    $parts = parse_url((string) $url);
    $host = strtolower((string) ($parts['host'] ?? ''));
    if (($parts['scheme'] ?? '') !== 'https' || !preg_match('/(^|\.)(fbcdn\.net|fbsbx\.com|facebook\.com)$/', $host)) {
        error_log('fb_bot: blocked image host: ' . $host);
        return null;
    }

    $maxBytes = 5 * 1024 * 1024;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_MAXFILESIZE => $maxBytes,
    ]);
    $data = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($data === false || $status !== 200 || $data === '' || strlen($data) > $maxBytes) {
        error_log('fb_bot: image download failed; HTTP ' . $status);
        return null;
    }

    $info = @getimagesizefromstring($data);
    $types = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];
    if (!$info || !isset($types[$info['mime']])) {
        return null;
    }
    return [$data, $types[$info['mime']]];
}

/**
 * Called when the customer sends a photo. Saves it as a pending payment submission.
 */
function fb_receive_image($psid, $imageUrl)
{
    $link = ORM::for_table('tbl_fb_links')->where('psid', (string) $psid)->find_one();
    if (!$link) {
        return "To submit a payment, please link your BILLSYS account first by sending:\n\nLINK <username>";
    }

    $db = ORM::get_db();
    $stmt = $db->prepare('SELECT payment_method, created_at FROM tbl_fb_payment_pending WHERE psid = ? LIMIT 1');
    $stmt->execute([(string) $psid]);
    $pending = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$pending || strtotime($pending['created_at']) < time() - 15 * 60) {
        return "Please choose your payment method first by sending one of these:\n\n"
            . "PAYMENT GCASH\nPAYMENT MAYA\nPAYMENT BANK\n\n"
            . "Then send a photo of your proof of payment.";
    }

    $customer = ORM::for_table('tbl_customers')->find_one((int) $link['customer_id']);
    if (!$customer) {
        $link->delete();
        return 'We could not retrieve the billing account associated with this Facebook profile. Please contact customer support for assistance.';
    }

    $image = fb_download_image($imageUrl);
    if ($image === null) {
        return "Sorry, we could not read that photo. Please send a clear JPG or PNG image (up to 5 MB) of your proof of payment.";
    }
    list($binary, $ext) = $image;

    $hash = hash('sha256', $binary);
    $check = $db->prepare('SELECT id FROM tbl_fb_payment_submissions WHERE proof_hash = ? LIMIT 1');
    $check->execute([$hash]);
    if ($check->fetch()) {
        return 'This proof of payment has already been submitted. If you believe this is an error, please contact customer support.';
    }

    $fileName = bin2hex(random_bytes(16)) . '.' . $ext;
    $dir = fb_proof_dir();
    if (!is_dir($dir) || @file_put_contents($dir . '/' . $fileName, $binary) === false) {
        error_log('fb_bot: could not save receipt photo to ' . $dir);
        return 'We are unable to save your proof of payment at this time. Please try again later or contact customer support.';
    }

    $submittedAt = time();
    try {
        $insert = $db->prepare("INSERT INTO tbl_fb_payment_submissions (psid, customer_id, payment_method, proof_image, proof_hash, status, submitted_at) VALUES (?, ?, ?, ?, ?, 'pending', ?)");
        $insert->execute([(string) $psid, (int) $customer['id'], $pending['payment_method'], $fileName, $hash, date('Y-m-d H:i:s', $submittedAt)]);
    } catch (PDOException $e) {
        @unlink($dir . '/' . $fileName);
        if ((string) $e->getCode() === '23000') {
            return 'This proof of payment has already been submitted. If you believe this is an error, please contact customer support.';
        }
        throw $e;
    }

    $del = $db->prepare('DELETE FROM tbl_fb_payment_pending WHERE psid = ?');
    $del->execute([(string) $psid]);

    return fb_payment_message($customer, $pending['payment_method'], $submittedAt);
}

function fb_status_message($customer, $record)
{
    $plan = '-';
    $statusIcon = '❌';
    $statusText = 'No active plan';
    $dueDate = '-';

    if ($record) {
        $plan = trim((string) ($record['namebp'] ?? '')) !== '' ? (string) $record['namebp'] : '-';

        $expiryText = trim((string) ($record['expiration'] ?? '') . ' ' . (string) ($record['time'] ?? ''));
        $expiry = $expiryText !== '' ? strtotime($expiryText) : false;

        if ($expiry) {
            $dueDate = date('M d, Y', $expiry);
            $isOff = strtolower((string) ($record['status'] ?? 'on')) === 'off';
            if ($expiry > time() && !$isOff) {
                $statusIcon = '✅';
                $statusText = 'Active';
            } else {
                $statusIcon = '❌';
                $statusText = 'Expired';
            }
        }
    }

    $balance = '₱' . number_format((float) ($customer['balance'] ?? 0), 2);

    return "Hello! Here's your account status:\n\n"
        . fb_account_line($customer)
        . fb_name_line($customer)
        . "📶 Plan: {$plan}\n"
        . "📌 Status: {$statusIcon} {$statusText}\n"
        . "💰 Balance: {$balance}\n"
        . "📅 Due Date: {$dueDate}\n\n"
        . "Thank you for using our service!";
}

function fb_reply($psid, $text)
{
    $text = trim(preg_replace('/\s+/u', ' ', (string) $text));
    if ($text === '') {
        return fb_help();
    }
    $parts = explode(' ', $text);
    $command = strtoupper($parts[0]);
    $link = ORM::for_table('tbl_fb_links')->where('psid', (string) $psid)->find_one();

    if ($command === 'LINK') {
        if ($link) {
            return 'Your Facebook account is already linked to a billing account. To link a different account, please send UNLINK first.';
        }
        if (empty($parts[1]) || count($parts) !== 2) {
            return "To link your billing account, please send the following command using your registered BILLSYS username:\n\nLINK <username>";
        }

        $failure = ORM::for_table('tbl_fb_fails')->where('psid', (string) $psid)->find_one();
        if ($failure && !empty($failure['locked_until']) && strtotime($failure['locked_until']) > time()) {
            return 'For security reasons, account linking is temporarily unavailable due to multiple unsuccessful attempts. Please try again in 15 minutes.';
        }

        $username = trim($parts[1]);
        $customer = ORM::for_table('tbl_customers')->where('username', $username)->find_one();
        if ($customer) {
            try {
                // Re-check this PSID before writing. Update a stale existing row
                // instead of failing on the unique PSID index.
                $db = ORM::get_db();
                $existingStmt = $db->prepare('SELECT id FROM tbl_fb_links WHERE psid = ? LIMIT 1');
                $existingStmt->execute([(string) $psid]);
                $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);
                $now = date('Y-m-d H:i:s');

                if ($existing) {
                    $update = $db->prepare('UPDATE tbl_fb_links SET customer_id = ?, linked_at = ?, last_in = ? WHERE id = ?');
                    $update->execute([(int) $customer['id'], $now, $now, (int) $existing['id']]);
                } else {
                    $insert = $db->prepare('INSERT INTO tbl_fb_links (psid, customer_id, linked_at, last_in) VALUES (?, ?, ?, ?)');
                    $insert->execute([(string) $psid, (int) $customer['id'], $now, $now]);
                }
                if ($failure) {
                    $failure->delete();
                }
                $name = trim((string) ($customer['fullname'] ?? ''));
                return 'Your Facebook account has been successfully linked' . ($name !== '' ? ' to the billing account of ' . $name : ' to your billing account') . ".\n\nYou may now send STATUS to view your account status. Thank you for using our service.";
            } catch (Exception $e) {
                error_log('fb_bot: account link failed: ' . $e->getMessage());
                return 'We are unable to complete your account linking request at this time. Please try again later or contact customer support.';
            }
        }

        if (!$failure) {
            $failure = ORM::for_table('tbl_fb_fails')->create();
            $failure->psid = (string) $psid;
            $failure->fails = 0;
        }
        $attempts = (int) $failure['fails'] + 1;
        if ($attempts >= 5) {
            $failure->fails = 0;
            $failure->locked_until = date('Y-m-d H:i:s', time() + 15 * 60);
        } else {
            $failure->fails = $attempts;
        }
        $failure->save();
        return 'We could not locate a billing account matching the username provided. Please verify your registered username and try again. If the issue persists, contact customer support.';
    }

    if ($command === 'PAYMENT') {
        if (!$link) {
            return "To submit a payment, please link your BILLSYS account first by sending:\n\nLINK <username>";
        }

        $paymentMethod = strtoupper(trim($parts[1] ?? ''));
        if (count($parts) !== 2 || !in_array($paymentMethod, ['GCASH', 'MAYA', 'BANK'], true)) {
            return "To submit a payment, please send one of these:\n\n"
                . "PAYMENT GCASH\n"
                . "PAYMENT MAYA\n"
                . "PAYMENT BANK\n\n"
                . "Then send a photo of your proof of payment.";
        }

        $db = ORM::get_db();
        $stmt = $db->prepare('REPLACE INTO tbl_fb_payment_pending (psid, payment_method, created_at) VALUES (?, ?, ?)');
        $stmt->execute([(string) $psid, $paymentMethod, date('Y-m-d H:i:s')]);

        return "📸 Please send a clear photo or screenshot of your " . fb_method_label($paymentMethod) . " proof of payment.\n\n"
            . "Make sure the amount, date and reference number are visible.\n\n"
            . "This request expires in 15 minutes.";
    }

    if ($command === 'UNLINK') {
        // Delete by PSID directly to ensure the link is removed from the database.
        $db = ORM::get_db();
        $stmt = $db->prepare('DELETE FROM tbl_fb_links WHERE psid = ?');
        $stmt->execute([(string) $psid]);
        $removed = $stmt->rowCount() > 0;

        // Clear any previous failed-link counter or temporary lockout as well.
        $stmt = $db->prepare('DELETE FROM tbl_fb_fails WHERE psid = ?');
        $stmt->execute([(string) $psid]);
        $stmt = $db->prepare('DELETE FROM tbl_fb_payment_pending WHERE psid = ?');
        $stmt->execute([(string) $psid]);

        if ($removed || $link) {
            return 'Your Facebook account has been successfully unlinked from your BILLSYS billing account. You may link an account again at any time by sending LINK <username>.';
        }
        return 'There is currently no billing account linked to this Facebook account. To link an account, send LINK <username>.';
    }

    if ($command === 'STATUS') {
        if (!$link) {
            return "To access your billing information, please link your account first by sending the following command with your registered username:\n\nLINK <username>";
        }
        $customer = ORM::for_table('tbl_customers')->find_one((int) $link['customer_id']);
        if (!$customer) {
            $link->delete();
            return 'We could not retrieve the billing account associated with this Facebook profile. Please contact customer support for assistance.';
        }

        $record = ORM::for_table('tbl_user_recharges')
            ->where('customer_id', (int) $customer['id'])
            ->order_by_desc('id')->find_one();
        return fb_status_message($customer, $record);
    }

    return fb_help();
}

function fb_bot()
{
    try {
        fb_install();
    } catch (Exception $e) {
        error_log('fb_bot: database initialization failed: ' . $e->getMessage());
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Webhook initialization failed';
        exit;
    }

    $verifyToken = fb_cfg('fb_verify_token');
    $appSecret = fb_cfg('fb_app_secret');
    if ($verifyToken === '' || fb_cfg('fb_page_token') === '' || $appSecret === '') {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Add fb_verify_token, fb_page_token and fb_app_secret to tbl_appconfig';
        exit;
    }

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
        $mode = (string) ($_GET['hub_mode'] ?? $_GET['hub.mode'] ?? '');
        $token = (string) ($_GET['hub_verify_token'] ?? $_GET['hub.verify_token'] ?? '');
        $challenge = (string) ($_GET['hub_challenge'] ?? $_GET['hub.challenge'] ?? '');
        header('Content-Type: text/plain; charset=utf-8');
        if ($mode === 'subscribe' && hash_equals($verifyToken, $token)) {
            http_response_code(200);
            echo $challenge;
        } else {
            http_response_code(403);
            echo 'Verification failed';
        }
        exit;
    }

    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        header('Allow: GET, POST');
        exit;
    }

    $raw = file_get_contents('php://input');
    $signature = (string) ($_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '');
    $expected = 'sha256=' . hash_hmac('sha256', (string) $raw, $appSecret);
    if ($signature === '' || !hash_equals($expected, $signature)) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Invalid webhook signature';
        exit;
    }

    $data = json_decode((string) $raw, true);
    if (!is_array($data) || ($data['object'] ?? '') !== 'page') {
        http_response_code(404);
        exit;
    }

    foreach (($data['entry'] ?? []) as $entry) {
        foreach (($entry['messaging'] ?? []) as $event) {
            $psid = (string) ($event['sender']['id'] ?? '');
            if ($psid === '' || !empty($event['message']['is_echo'])) {
                continue;
            }
            if (isset($event['message']['quick_reply']['payload'])) {
                // Tapped a quick-reply button: use its payload as the command.
                $message = (string) $event['message']['quick_reply']['payload'];
            } elseif (isset($event['message']['text'])) {
                $message = (string) $event['message']['text'];
            } elseif (isset($event['postback']['payload'])) {
                $message = (string) $event['postback']['payload'];
            } elseif (isset($event['message'])) {
                $message = '';
            } else {
                continue; // Ignore delivery/read receipts.
            }

            try {
                // A photo (proof of payment) is handled separately from text commands.
                $imageUrl = '';
                $attachments = $event['message']['attachments'] ?? [];
                if (is_array($attachments) && $attachments) {
                    foreach ($attachments as $attachment) {
                        if (($attachment['type'] ?? '') === 'image'
                            && empty($attachment['payload']['sticker_id'])
                            && !empty($attachment['payload']['url'])) {
                            $imageUrl = (string) $attachment['payload']['url'];
                            break;
                        }
                    }
                    $reply = $imageUrl !== ''
                        ? fb_receive_image($psid, $imageUrl)
                        : "Sorry, we can only accept a photo as proof of payment. Send PAYMENT GCASH, PAYMENT MAYA or PAYMENT BANK first, then send a photo of your proof of payment.";
                } else {
                    $reply = fb_reply($psid, $message);
                }
                $linked = ORM::for_table('tbl_fb_links')->where('psid', $psid)->find_one();
                if ($linked) {
                    $linked->last_in = date('Y-m-d H:i:s');
                    $linked->save();
                }
                $quick = (empty($attachments) && $message !== '')
                    ? fb_quick_replies($message, (bool) $linked)
                    : [];
                fb_send($psid, $reply, $quick);
                try {
                    fb_deliver_announcements($psid);
                } catch (Throwable $noticeError) {
                    error_log('fb_bot: queued notice delivery failed: ' . $noticeError->getMessage());
                }
            } catch (Exception $e) {
                error_log('fb_bot: message processing failed: ' . $e->getMessage());
            }
        }
    }

    http_response_code(200);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'EVENT_RECEIVED';
    exit;
}