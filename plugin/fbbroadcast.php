<?php
/**
 * BILLSYS plugin: Messenger maintenance / outage notices (second tab of iPAYMES).
 *
 * Install:
 *   system/plugin/fbbroadcast.php
 *   system/plugin/ui/fbbroadcast.tpl
 * Requires fb_bot.php (sending + tables) from the same plugin folder.
 *
 * Meta only lets a Page message a person within 24 hours of that person's last message to the Page.
 * (The ACCOUNT_UPDATE tag that used to allow notices after that is no longer available.) So:
 *   - customers whose last message was within the window get the notice immediately;
 *   - everyone else is queued and gets it right after their next message to the bot,
 *     until the notice's expiry time.
 */

register_menu(
    "iPAYMES/Maintenance & Outage",
    true,
    "fbbroadcast",
    "AFTER_CUSTOMERS",
    "ion ion-alert-circled"
);

/** Keep a margin under Meta's 24 hours so a notice is not attempted at the very edge of the window. */
const FBBC_WINDOW_SECONDS = 23 * 3600;

function fbbc_kinds()
{
    return [
        'outage' => 'Service outage',
        'maintenance' => 'Scheduled maintenance',
        'restored' => 'Service restored',
        'notice' => 'General notice',
    ];
}

function fbbroadcast()
{
    global $ui, $routes;

    _admin();

    $admin = Admin::_info();
    $ui->assign('_system_menu', 'fbbroadcast');
    $ui->assign('_admin', $admin);
    $ui->assign('routes', $routes);
    $ui->assign('_title', 'Maintenance / Outage Notices');

    // Sending to every customer is restricted to SuperAdmin / Admin.
    $allowed = (is_array($admin) || is_object($admin))
        && in_array((string) ($admin['user_type'] ?? ''), ['SuperAdmin', 'Admin'], true);
    $ui->assign('fbBcAllowed', $allowed);
    $ui->assign('fbBcKinds', fbbc_kinds());

    if (!$allowed) {
        $ui->display('fbbroadcast.tpl');
        return;
    }

    if (!function_exists('fbpr_csrf_token') || !function_exists('fb_install') || !function_exists('fb_send') || !function_exists('fb_announcement_text')) {
        $ui->assign('fbBcAllowed', true);
        $ui->assign('fbBcError', 'The Messenger plugins are not fully loaded. Install the updated fb_bot.php and fbpaymentreview.php first.');
        $ui->assign('fbBcList', []);
        $ui->assign('fbBcStats', ['total' => 0, 'reachable' => 0, 'queued' => 0]);
        $ui->assign('fbBcForm', ['kind' => 'outage', 'title' => '', 'message' => '', 'hours' => 24]);
        $ui->assign('fbBcCsrf', '');
        $ui->assign('fbBcNotice', '');
        $ui->display('fbbroadcast.tpl');
        return;
    }

    $db = ORM::get_db();
    try {
        fb_install(); // creates the notice tables if they do not exist yet
    } catch (Throwable $e) {
        error_log('fbbroadcast: schema setup failed: ' . $e->getMessage());
    }

    if (session_status() !== PHP_SESSION_ACTIVE) {
        @session_start();
    }
    $csrfToken = fbpr_csrf_token($admin); // defined in fbpaymentreview.php

    $notice = '';
    $error = '';
    if (!empty($_SESSION['fbbroadcast_flash'])) {
        $notice = (string) $_SESSION['fbbroadcast_flash'];
        unset($_SESSION['fbbroadcast_flash']);
    }

    $form = ['kind' => 'outage', 'title' => '', 'message' => '', 'hours' => 24];
    $redirect = false;
    $kinds = fbbc_kinds();

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $posted = (string) ($_POST['csrf_token'] ?? '');
        $action = (string) _post('action');

        if (!fbpr_csrf_valid($posted, $admin)) {
            $error = 'Security check failed. Refresh the page and try again.';
        } elseif ($action === 'send') {
            $form['kind'] = (string) _post('kind');
            $form['title'] = trim((string) _post('title'));
            $form['message'] = trim((string) _post('message'));
            $form['hours'] = (int) _post('hours');

            $len = function ($v) {
                return function_exists('mb_strlen') ? mb_strlen($v) : strlen($v);
            };

            if (!isset($kinds[$form['kind']])) {
                $error = 'Choose a notice type.';
            } elseif ($form['title'] === '' || $len($form['title']) > 100) {
                $error = 'Enter a short title (up to 100 characters).';
            } elseif ($form['message'] === '' || $len($form['message']) > 1000) {
                $error = 'Enter the message (up to 1000 characters).';
            } elseif (!in_array($form['hours'], [6, 12, 24, 48, 72], true)) {
                $error = 'Choose how long queued notices stay valid.';
            } else {
                $adminName = (string) ($admin['username'] ?? $admin['fullname'] ?? $admin['id'] ?? 'admin');
                $now = date('Y-m-d H:i:s');
                $expires = date('Y-m-d H:i:s', time() + $form['hours'] * 3600);

                try {
                    $ins = $db->prepare('INSERT INTO tbl_fb_announcements (kind, title, message, created_by, created_at, expires_at, status) VALUES (?, ?, ?, ?, ?, ?, ?)');
                    $ins->execute([$form['kind'], $form['title'], $form['message'], substr($adminName, 0, 100), $now, $expires, 'active']);
                    $annId = (int) $db->lastInsertId();

                    @set_time_limit(300);
                    ignore_user_abort(true);

                    $text = fb_announcement_text($form['kind'], $form['title'], $form['message']);
                    $cutoff = date('Y-m-d H:i:s', time() - FBBC_WINDOW_SECONDS);
                    $links = $db->query('SELECT psid, customer_id, last_in FROM tbl_fb_links')->fetchAll(PDO::FETCH_ASSOC);

                    $log = $db->prepare('INSERT INTO tbl_fb_announcement_log (announcement_id, psid, customer_id, status, attempted_at, error) VALUES (?, ?, ?, ?, ?, ?)');
                    $sent = $queued = $failed = 0;

                    foreach ($links as $l) {
                        $status = 'queued';
                        $attempted = null;
                        $err = null;

                        if (!empty($l['last_in']) && $l['last_in'] >= $cutoff) {
                            $attempted = date('Y-m-d H:i:s');
                            if (fb_send((string) $l['psid'], $text)) {
                                $status = 'sent';
                            } else {
                                $e = $GLOBALS['fb_last_send_error'] ?? [];
                                // Meta says the window is closed after all: deliver on their next message instead.
                                if ((int) ($e['code'] ?? 0) === 10 || (int) ($e['subcode'] ?? 0) === 2018278) {
                                    $status = 'queued';
                                    $attempted = null;
                                } else {
                                    $status = 'failed';
                                    $err = substr((string) ($e['message'] ?? 'Send failed'), 0, 250);
                                }
                            }
                        }

                        $log->execute([$annId, (string) $l['psid'], (int) $l['customer_id'], $status, $attempted, $err]);
                        if ($status === 'sent') {
                            $sent++;
                        } elseif ($status === 'queued') {
                            $queued++;
                        } else {
                            $failed++;
                        }
                    }

                    $_SESSION['fbbroadcast_flash'] = "Notice sent to $sent customer(s) now. $queued will receive it after their next message to the bot (valid until $expires)."
                        . ($failed > 0 ? " $failed could not be reached (see the history below)." : '');
                    $redirect = true;
                } catch (Throwable $e) {
                    error_log('fbbroadcast: send failed: ' . $e->getMessage());
                    $error = 'Could not send the notice. Please try again.';
                }
            }
        } elseif ($action === 'cancel') {
            $annId = filter_var(_post('announcement_id'), FILTER_VALIDATE_INT);
            if (!$annId) {
                $error = 'Invalid request.';
            } else {
                try {
                    $a = $db->prepare("UPDATE tbl_fb_announcements SET status = 'cancelled' WHERE id = ? AND status = 'active'");
                    $a->execute([(int) $annId]);
                    $l = $db->prepare("UPDATE tbl_fb_announcement_log SET status = 'cancelled' WHERE announcement_id = ? AND status = 'queued'");
                    $l->execute([(int) $annId]);
                    $_SESSION['fbbroadcast_flash'] = 'Queued deliveries for this notice were cancelled. Messages already sent cannot be recalled.';
                    $redirect = true;
                } catch (Throwable $e) {
                    error_log('fbbroadcast: cancel failed: ' . $e->getMessage());
                    $error = 'Could not cancel the notice. Please try again.';
                }
            }
        }
    }

    // Post/Redirect/Get so a refresh cannot send the notice twice.
    if ($redirect) {
        $base = defined('U') ? U : (defined('APP_URL') ? rtrim(APP_URL, '/') . '/?_route=' : '?_route=');
        header('Location: ' . $base . 'plugin/fbbroadcast');
        exit;
    }

    // Audience preview: who can receive a notice right now.
    $cutoff = date('Y-m-d H:i:s', time() - FBBC_WINDOW_SECONDS);
    $st = $db->prepare('SELECT COUNT(*) AS total, SUM(last_in IS NOT NULL AND last_in >= ?) AS reachable FROM tbl_fb_links');
    $st->execute([$cutoff]);
    $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];
    $total = (int) ($row['total'] ?? 0);
    $reachable = (int) ($row['reachable'] ?? 0);

    // History with delivery counts.
    $now = date('Y-m-d H:i:s');
    $list = $db->query(
        "SELECT a.id, a.kind, a.title, a.message, a.created_by, a.created_at, a.expires_at, a.status,
                COUNT(l.psid) AS total,
                COALESCE(SUM(l.status = 'sent'), 0) AS sent,
                COALESCE(SUM(l.status = 'queued'), 0) AS queued,
                COALESCE(SUM(l.status = 'failed'), 0) AS failed
         FROM tbl_fb_announcements a
         LEFT JOIN tbl_fb_announcement_log l ON l.announcement_id = a.id
         GROUP BY a.id
         ORDER BY a.id DESC
         LIMIT 30"
    )->fetchAll(PDO::FETCH_ASSOC);
    foreach ($list as &$a) {
        $a['kind_label'] = $kinds[$a['kind']] ?? 'General notice';
        $a['expired'] = $a['expires_at'] < $now;
        $a['can_cancel'] = $a['status'] === 'active' && !$a['expired'] && (int) $a['queued'] > 0;
    }
    unset($a);

    $ui->assign('fbBcStats', ['total' => $total, 'reachable' => $reachable, 'queued' => max(0, $total - $reachable)]);
    $ui->assign('fbBcList', $list);
    $ui->assign('fbBcForm', $form);
    $ui->assign('fbBcCsrf', $csrfToken);
    $ui->assign('fbBcNotice', $notice);
    $ui->assign('fbBcError', $error);
    $ui->display('fbbroadcast.tpl');
}