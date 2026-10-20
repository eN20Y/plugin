<?php
/**
 * BILLSYS plugin: Facebook Page Post (third entry of the iPAYMES menu).
 *
 * Install:
 *   system/plugin/fbpost.php
 *   system/plugin/ui/fbpost.tpl
 * Requires fb_bot.php and fbpaymentreview.php from the same plugin folder.
 *
 * It posts to the same Facebook Page as the Messenger bot, using the Page token
 * already saved in tbl_appconfig (fb_page_token, fb_graph_version).
 * That token must also have the pages_manage_posts permission in Meta.
 */

// Same "iPAYMES/" parent as Payment Verification and Maintenance & Outage.
register_menu(
    "iPAYMES/Facebook Post",
    true,
    "fbpost",
    "AFTER_CUSTOMERS",
    "ion ion-social-facebook"
);

/**
 * Ready-made post templates shown in the "Use a template" dropdown.
 * Anything in [SQUARE BRACKETS] must be filled in before the post can be published.
 * Edit the wording here; no change to the .tpl is needed.
 *
 * Keys per template: label, headline/details/footer (banner image text),
 * icon, color1/color2 (banner gradient), message (the post text).
 */
function fbpost_templates()
{
    $thanks = 'Thank you for your understanding.';

    return [
        'scheduled_interruption' => [
            'label' => 'Scheduled internet interruption',
            'headline' => 'SCHEDULED INTERNET INTERRUPTION',
            'details' => '[DATE] | [START TIME] - [END TIME]',
            'footer' => $thanks,
            'icon' => "\u{1F4E2}",
            'color1' => '#f59e0b',
            'color2' => '#b45309',
            'message' => implode("\n", [
                "\u{1F4E2} SCHEDULED INTERNET INTERRUPTION",
                '',
                'Dear subscribers,',
                '',
                'Please be informed that our internet service will be temporarily interrupted in [AREA] on [DATE] from [START TIME] to [END TIME] due to [REASON].',
                '',
                'Service will return to normal as soon as the work is finished. Please plan your online activities ahead.',
                '',
                'We apologize for the inconvenience. Thank you for your understanding.',
            ]),
        ],
        'scheduled_maintenance' => [
            'label' => 'Scheduled maintenance',
            'headline' => 'SCHEDULED MAINTENANCE',
            'details' => '[DATE] | [START TIME] - [END TIME]',
            'footer' => $thanks,
            'icon' => "\u{1F6E0}\u{FE0F}",
            'color1' => '#f97316',
            'color2' => '#c2410c',
            'message' => implode("\n", [
                "\u{1F6E0}\u{FE0F} SCHEDULED MAINTENANCE",
                '',
                'Dear subscribers,',
                '',
                'We will be doing scheduled network maintenance in [AREA] on [DATE] from [START TIME] to [END TIME]. During this time you may experience slow or intermittent internet, or a short loss of connection.',
                '',
                'This work will help us give you a faster and more reliable service.',
                '',
                'We apologize for the inconvenience. Thank you for your understanding.',
            ]),
        ],
        'unscheduled_maintenance' => [
            'label' => 'Unscheduled maintenance / outage',
            'headline' => 'UNSCHEDULED MAINTENANCE',
            'details' => '[AREA] | Back by [ESTIMATED TIME]',
            'footer' => 'Thank you for your patience.',
            'icon' => "\u{26A0}\u{FE0F}",
            'color1' => '#ef4444',
            'color2' => '#991b1b',
            'message' => implode("\n", [
                "\u{26A0}\u{FE0F} UNSCHEDULED MAINTENANCE",
                '',
                'Dear subscribers,',
                '',
                'We are currently doing urgent, unscheduled maintenance in [AREA] to fix [REASON]. Your internet connection may be slow or unavailable in the meantime.',
                '',
                'Our technicians are working on it and we expect service to be back by [ESTIMATED TIME]. We will post an update as soon as it is restored.',
                '',
                'We apologize for the inconvenience. Thank you for your patience.',
            ]),
        ],
        'service_restored' => [
            'label' => 'Service restored',
            'headline' => 'SERVICE RESTORED',
            'details' => 'Internet is back in [AREA]',
            'footer' => 'Thank you for your patience and understanding.',
            'icon' => "\u{2705}",
            'color1' => '#22c55e',
            'color2' => '#166534',
            'message' => implode("\n", [
                "\u{2705} SERVICE RESTORED",
                '',
                'Good news! Internet service in [AREA] has been restored.',
                '',
                'If you still have no connection, please restart your router or modem, then send us a message if the problem continues.',
                '',
                'Thank you for your patience and understanding.',
            ]),
        ],
        'payment_reminder' => [
            'label' => 'Payment reminder',
            'headline' => 'PAYMENT REMINDER',
            'details' => 'Please pay on or before [DUE DATE]',
            'footer' => 'Thank you for being our valued subscriber!',
            'icon' => "\u{1F4B3}",
            'color1' => '#3b82f6',
            'color2' => '#1e3a8a',
            'message' => implode("\n", [
                "\u{1F4B3} PAYMENT REMINDER",
                '',
                'Dear subscribers,',
                '',
                'This is a friendly reminder to settle your internet subscription on or before [DUE DATE] to avoid service interruption.',
                '',
                'You can pay through [PAYMENT METHODS]. If you have already paid, please disregard this message.',
                '',
                'Need help? Send us a message and we will gladly assist you.',
                '',
                'Thank you for being our valued subscriber!',
            ]),
        ],
        'other' => [
            'label' => 'Other (blank)',
            'headline' => 'ANNOUNCEMENT',
            'details' => '',
            'footer' => '',
            'icon' => "\u{1F4E2}",
            'color1' => '#475569',
            'color2' => '#0f172a',
            'message' => '',
        ],
    ];
}

/** Create the post history table if it does not exist yet. */
function fbpost_install()
{
    $db = ORM::get_db();
    $db->exec("CREATE TABLE IF NOT EXISTS tbl_fb_page_posts (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        kind VARCHAR(10) NOT NULL DEFAULT 'text',
        message TEXT NULL,
        link VARCHAR(500) NULL,
        fb_post_id VARCHAR(100) NULL,
        scheduled_for DATETIME NULL,
        created_by VARCHAR(100) NULL,
        created_at DATETIME NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/**
 * Call the Graph API with the Messenger bot's Page token.
 * Returns ['ok' => bool, 'data' => array] or ['ok' => false, 'error' => string, 'code' => int]
 */
function fbpost_graph($method, $path, array $fields = [], $multipart = false)
{
    $token = fb_cfg('fb_page_token');
    if ($token === '' || !function_exists('curl_init')) {
        return ['ok' => false, 'error' => 'cURL or the Page Access Token is not configured.', 'code' => 0];
    }

    $version = trim(fb_cfg('fb_graph_version', 'v23.0'));
    $url = 'https://graph.facebook.com/' . ($version !== '' ? $version . '/' : '') . $path;

    $ch = curl_init();
    if ($method === 'GET') {
        $url .= '?' . http_build_query($fields);
    } else {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $multipart ? $fields : http_build_query($fields));
    }

    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        // Token in the header keeps it out of URLs and server logs.
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => $multipart ? 120 : 30,
    ]);

    $body = curl_exec($ch);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($body === false) {
        return ['ok' => false, 'error' => $curlError ?: 'No response from Facebook.', 'code' => 0];
    }

    $data = json_decode($body, true);
    if (!is_array($data)) {
        return ['ok' => false, 'error' => 'Unexpected response from Facebook.', 'code' => 0];
    }

    if (isset($data['error'])) {
        error_log('fbpost: Graph error ' . $body);
        return [
            'ok' => false,
            'error' => (string) ($data['error']['message'] ?? 'Unknown Facebook error'),
            'code' => (int) ($data['error']['code'] ?? 0),
        ];
    }

    return ['ok' => true, 'data' => $data];
}

/** Add a hint when the error looks like a missing permission or an expired token. */
function fbpost_error_text($res)
{
    $text = (string) $res['error'];
    if (in_array((int) ($res['code'] ?? 0), [10, 100, 190, 200], true)) {
        $text .= ' (The Page token may be missing pages_manage_posts or pages_read_engagement, or it has expired. Generate a new Page token in Meta with both permissions and save it in Bot Settings.)';
    }
    return $text;
}

function fbpost()
{
    global $ui, $routes;

    _admin();

    $admin = Admin::_info();
    $ui->assign('_system_menu', 'fbpost');
    $ui->assign('_admin', $admin);
    $ui->assign('routes', $routes);
    $ui->assign('_title', 'Facebook Page Post');

    // Posting to the public Page is restricted to SuperAdmin / Admin.
    $allowed = (is_array($admin) || is_object($admin))
        && in_array((string) ($admin['user_type'] ?? ''), ['SuperAdmin', 'Admin'], true);
    $ui->assign('fbPostAllowed', $allowed);

    $emptyForm = ['message' => '', 'link' => '', 'schedule_at' => ''];

    if (!$allowed) {
        $ui->assign('fbPostError', '');
        $ui->assign('fbPostNotice', '');
        $ui->assign('fbPostForm', $emptyForm);
        $ui->assign('fbPostList', []);
        $ui->assign('fbPostCsrf', '');
        $ui->assign('fbPostHasToken', false);
        $ui->assign('fbPostTemplates', []);
        $ui->assign('fbPostTemplatesJson', '{}');
        $ui->display('fbpost.tpl');
        return;
    }

    if (!function_exists('fbpr_csrf_token') || !function_exists('fb_cfg')) {
        $ui->assign('fbPostError', 'The Messenger plugins are not fully loaded. Install fb_bot.php and fbpaymentreview.php first.');
        $ui->assign('fbPostNotice', '');
        $ui->assign('fbPostForm', $emptyForm);
        $ui->assign('fbPostList', []);
        $ui->assign('fbPostCsrf', '');
        $ui->assign('fbPostHasToken', false);
        $ui->assign('fbPostTemplates', []);
        $ui->assign('fbPostTemplatesJson', '{}');
        $ui->display('fbpost.tpl');
        return;
    }

    $db = ORM::get_db();
    try {
        fbpost_install();
    } catch (Throwable $e) {
        error_log('fbpost: schema setup failed: ' . $e->getMessage());
    }

    if (session_status() !== PHP_SESSION_ACTIVE) {
        @session_start();
    }
    $csrfToken = fbpr_csrf_token($admin); // defined in fbpaymentreview.php

    $notice = '';
    $error = '';
    if (!empty($_SESSION['fbpost_flash'])) {
        $notice = (string) $_SESSION['fbpost_flash'];
        unset($_SESSION['fbpost_flash']);
    }

    $form = $emptyForm;
    $redirect = false;

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $posted = (string) ($_POST['csrf_token'] ?? '');
        $action = (string) _post('action');

        if (!fbpr_csrf_valid($posted, $admin)) {
            $error = 'Security check failed. Refresh the page and try again.';

        } elseif ($action === 'test') {

            $res = fbpost_graph('GET', 'me', ['fields' => 'id,name']);
            if ($res['ok']) {
                $_SESSION['fbpost_flash'] = 'Connected to Facebook Page: ' . ($res['data']['name'] ?? 'unknown') . '. Posting also needs the pages_manage_posts permission.';
                $redirect = true;
            } else {
                // Reading the Page name needs pages_read_engagement. Fall back to a basic token check.
                $basic = fbpost_graph('GET', 'me', ['fields' => 'id']);
                if ($basic['ok']) {
                    $_SESSION['fbpost_flash'] = 'The token is valid (Page ID ' . ($basic['data']['id'] ?? '?') . '), but it is missing pages_read_engagement, which posting also needs. Generate a new Page token with pages_read_engagement and pages_manage_posts.';
                    $redirect = true;
                } else {
                    $error = 'Connection failed: ' . fbpost_error_text($res);
                }
            }

        } elseif ($action === 'post') {

            $form['message'] = trim((string) _post('message'));
            $form['link'] = trim((string) _post('link'));
            $form['schedule_at'] = trim((string) _post('schedule_at'));

            $len = function ($v) {
                return function_exists('mb_strlen') ? mb_strlen($v) : strlen($v);
            };

            $hasPhoto = isset($_FILES['photo']['tmp_name']) && is_uploaded_file($_FILES['photo']['tmp_name']);
            $scheduledTs = 0;
            $photoInfo = null;

            if ($form['message'] === '' && $form['link'] === '' && !$hasPhoto) {
                $error = 'Write a message, add a link, or choose a photo.';
            } elseif ($len($form['message']) > 5000) {
                $error = 'The message is too long (up to 5000 characters).';
            } elseif ($form['link'] !== '' && !preg_match('#^https?://#i', $form['link'])) {
                $error = 'The link must start with http:// or https://';
            } elseif ($form['link'] !== '' && !filter_var($form['link'], FILTER_VALIDATE_URL)) {
                $error = 'The link is not a valid URL.';
            } elseif ($hasPhoto && $form['link'] !== '') {
                $error = 'A photo post cannot include a link. Remove one of them.';
            }

            if ($error === '' && $form['schedule_at'] !== '') {
                $scheduledTs = (int) strtotime($form['schedule_at']);
                if ($scheduledTs < time() + 600 || $scheduledTs > time() + 30 * 86400) {
                    $error = 'Schedule the post between 10 minutes and 30 days from now.';
                }
            }

            if ($error === '' && $hasPhoto) {
                $photoInfo = @getimagesize($_FILES['photo']['tmp_name']);
                if (!$photoInfo || !in_array($photoInfo['mime'], ['image/jpeg', 'image/png', 'image/gif'], true)) {
                    $error = 'The photo must be a JPG, PNG or GIF image.';
                } elseif ((int) $_FILES['photo']['size'] > 4 * 1024 * 1024) {
                    $error = 'The photo must be 4 MB or smaller.';
                }
            }

            if ($error === '') {
                $fields = [];
                if ($scheduledTs > 0) {
                    $fields['published'] = 'false';
                    $fields['scheduled_publish_time'] = $scheduledTs;
                }

                if ($hasPhoto) {
                    $fields['caption'] = $form['message'];
                    $fields['source'] = new CURLFile(
                        $_FILES['photo']['tmp_name'],
                        $photoInfo['mime'],
                        basename((string) $_FILES['photo']['name'])
                    );
                    $res = fbpost_graph('POST', 'me/photos', $fields, true);
                    $kind = 'photo';
                } else {
                    if ($form['message'] !== '') {
                        $fields['message'] = $form['message'];
                    }
                    if ($form['link'] !== '') {
                        $fields['link'] = $form['link'];
                    }
                    $res = fbpost_graph('POST', 'me/feed', $fields);
                    $kind = $form['link'] !== '' ? 'link' : 'text';
                }

                if (!$res['ok']) {
                    $error = 'Facebook did not accept the post: ' . fbpost_error_text($res);
                } else {
                    $fbId = (string) ($res['data']['post_id'] ?? ($res['data']['id'] ?? ''));
                    $adminName = (string) ($admin['username'] ?? $admin['fullname'] ?? $admin['id'] ?? 'admin');

                    try {
                        $ins = $db->prepare('INSERT INTO tbl_fb_page_posts (kind, message, link, fb_post_id, scheduled_for, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)');
                        $ins->execute([
                            $kind,
                            $form['message'] !== '' ? $form['message'] : null,
                            $form['link'] !== '' ? $form['link'] : null,
                            substr($fbId, 0, 100),
                            $scheduledTs > 0 ? date('Y-m-d H:i:s', $scheduledTs) : null,
                            substr($adminName, 0, 100),
                            date('Y-m-d H:i:s'),
                        ]);
                    } catch (Throwable $e) {
                        // The post is already live; only the history row failed.
                        error_log('fbpost: could not save history: ' . $e->getMessage());
                    }

                    $_SESSION['fbpost_flash'] = $scheduledTs > 0
                        ? 'Post scheduled for ' . date('Y-m-d H:i', $scheduledTs) . '. Manage scheduled posts in Meta Business Suite.'
                        : 'Posted to your Facebook Page.';
                    $redirect = true;
                }
            }
        }
    }

    // Post/Redirect/Get so a refresh cannot publish the post twice.
    if ($redirect) {
        $base = defined('U') ? U : (defined('APP_URL') ? rtrim(APP_URL, '/') . '/?_route=' : '?_route=');
        header('Location: ' . $base . 'plugin/fbpost');
        exit;
    }

    $list = [];
    try {
        $list = $db->query(
            'SELECT id, kind, message, link, fb_post_id, scheduled_for, created_by, created_at
             FROM tbl_fb_page_posts ORDER BY id DESC LIMIT 20'
        )->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log('fbpost: could not load history: ' . $e->getMessage());
    }

    // Templates for the "Use a template" dropdown. The JSON is escaped so it is safe inside a <script> tag.
    $templates = fbpost_templates();
    $templatesJson = json_encode($templates, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    if ($templatesJson === false) {
        $templatesJson = '{}';
    }

    $ui->assign('fbPostHasToken', fb_cfg('fb_page_token') !== '');
    $ui->assign('fbPostList', $list);
    $ui->assign('fbPostForm', $form);
    $ui->assign('fbPostCsrf', $csrfToken);
    $ui->assign('fbPostNotice', $notice);
    $ui->assign('fbPostError', $error);
    $ui->assign('fbPostTemplates', $templates);
    $ui->assign('fbPostTemplatesJson', $templatesJson);
    $ui->display('fbpost.tpl');
}