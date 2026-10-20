<?php
/**
 * BILLSYS plugin: Reported Issues (iPAYMES menu, right after Facebook Post).
 *
 * Install:
 *   system/plugin/fbreport.php
 *   system/plugin/ui/fbreport.tpl
 * Requires the latest fb_bot.php (creates tbl_fb_issues) and fbpaymentreview.php in the same plugin folder.
 *
 * The file is named "fbreport" so it loads after fbpost.php, which puts its menu entry right below
 * iPAYMES / Facebook Post.
 *
 * Customers report problems from Messenger (PROBLEM button). This page lists the tickets, lets an
 * admin change the status, add a private note, and optionally notify the customer on Messenger.
 */

register_menu(
    "iPAYMES/Reported Issues",
    true,
    "fbreport",
    "AFTER_CUSTOMERS",
    "ion ion-alert-circled"
);

/** Build a link to this page, with optional filters. */
function fbreport_url(array $params = [])
{
    $base = defined('U') ? U : (defined('APP_URL') ? rtrim(APP_URL, '/') . '/?_route=' : '?_route=');
    $clean = [];
    foreach ($params as $k => $v) {
        if ((string) $v !== '') {
            $clean[$k] = $v;
        }
    }
    return $base . 'plugin/fbreport' . ($clean ? '&' . http_build_query($clean) : '');
}

/** "5 min ago", "3 hr ago", "2 day(s) ago". */
function fbreport_ago($datetime)
{
    $t = strtotime((string) $datetime);
    if (!$t) {
        return '';
    }
    $d = time() - $t;
    if ($d < 60) {
        return 'just now';
    }
    if ($d < 3600) {
        return floor($d / 60) . ' min ago';
    }
    if ($d < 86400) {
        return floor($d / 3600) . ' hr ago';
    }
    return floor($d / 86400) . ' day(s) ago';
}

/** The message a customer receives when the status changes. */
function fbreport_customer_message($id, $status)
{
    if ($status === 'resolved') {
        return "✅ Update on your problem report (Ticket #{$id}):\n\n"
            . "Your reported issue has been marked as resolved. Please restart your router or modem and check your connection.\n\n"
            . "If the problem continues, send PROBLEM to report it again.\n\n"
            . 'Thank you for your patience.';
    }
    return "🛠️ Update on your problem report (Ticket #{$id}):\n\n"
        . "Our technicians are now checking your issue. We will message you once it is fixed.\n\n"
        . 'Thank you for your patience.';
}

function fbreport()
{
    global $ui, $routes;

    _admin();

    $admin = Admin::_info();
    $ui->assign('_system_menu', 'fbreport');
    $ui->assign('_admin', $admin);
    $ui->assign('routes', $routes);
    $ui->assign('_title', 'Reported Issues');

    $statuses = ['open' => 'Open', 'in_progress' => 'In progress', 'resolved' => 'Resolved'];
    $statusClass = ['open' => 'danger', 'in_progress' => 'warning', 'resolved' => 'success'];
    $catClass = ['NO_INTERNET' => 'danger', 'SLOW' => 'warning', 'INTERMITTENT' => 'info', 'OTHER' => 'default'];

    // Shows the page with an empty list (access denied, or the Messenger plugins are missing).
    $blank = function ($allowed, $error) use ($ui, $statuses) {
        $ui->assign('fbRpAllowed', $allowed);
        $ui->assign('fbRpError', $error);
        $ui->assign('fbRpNotice', '');
        $ui->assign('fbRpStats', ['open' => 0, 'in_progress' => 0, 'resolved' => 0, 'total' => 0]);
        $ui->assign('fbRpList', []);
        $ui->assign('fbRpStatuses', $statuses);
        $ui->assign('fbRpCats', []);
        $ui->assign('fbRpFilter', ['status' => '', 'cat' => '', 'q' => '']);
        $ui->assign('fbRpCsrf', '');
        $ui->assign('fbRpQs', '');
        $ui->assign('fbRpPage', 1);
        $ui->assign('fbRpPages', 1);
        $ui->assign('fbRpTotalRows', 0);
        $ui->display('fbreport.tpl');
    };

    // Reports contain customer details: SuperAdmin / Admin only.
    $allowed = (is_array($admin) || is_object($admin))
        && in_array((string) ($admin['user_type'] ?? ''), ['SuperAdmin', 'Admin'], true);
    if (!$allowed) {
        $blank(false, '');
        return;
    }

    if (!function_exists('fbpr_csrf_token') || !function_exists('fb_install') || !function_exists('fb_issue_categories') || !function_exists('fb_send')) {
        $blank(true, 'Install the latest fb_bot.php and fbpaymentreview.php first (they create the reports table and provide the Messenger functions).');
        return;
    }

    $db = ORM::get_db();
    try {
        fb_install(); // makes sure tbl_fb_issues exists, even before the first Messenger message arrives
    } catch (Throwable $e) {
        error_log('fbreport: schema setup failed: ' . $e->getMessage());
    }

    if (session_status() !== PHP_SESSION_ACTIVE) {
        @session_start();
    }
    $csrfToken = fbpr_csrf_token($admin);
    $cats = fb_issue_categories();

    $notice = '';
    $error = '';
    if (!empty($_SESSION['fbreport_flash'])) {
        $notice = (string) $_SESSION['fbreport_flash'];
        unset($_SESSION['fbreport_flash']);
    }

    // ---------------------------------------------------------------- save a change
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $posted = (string) ($_POST['csrf_token'] ?? '');
        $action = (string) _post('action');

        // Filters to return to after saving.
        $back = [
            'status' => (string) _post('f_status'),
            'cat' => (string) _post('f_cat'),
            'q' => (string) _post('f_q'),
            'page' => (string) _post('f_page'),
        ];

        if (!fbpr_csrf_valid($posted, $admin)) {
            $error = 'Security check failed. Refresh the page and try again.';

        } elseif ($action === 'update') {
            $id = (int) _post('id');
            $status = (string) _post('status');
            $note = trim((string) _post('note'));
            $notify = (string) _post('notify') === '1';
            $note = function_exists('mb_substr') ? mb_substr($note, 0, 500, 'UTF-8') : substr($note, 0, 500);

            $stmt = $db->prepare('SELECT i.id, i.psid, i.status, l.last_in FROM tbl_fb_issues i LEFT JOIN tbl_fb_links l ON l.psid = i.psid WHERE i.id = ? LIMIT 1');
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                $error = 'That report could not be found.';
            } elseif (!isset($statuses[$status])) {
                $error = 'Choose a valid status.';
            } else {
                $now = date('Y-m-d H:i:s');
                $adminName = substr((string) ($admin['username'] ?? $admin['fullname'] ?? $admin['id'] ?? 'admin'), 0, 100);

                $sets = 'status = ?, admin_notes = ?, handled_by = ?, updated_at = ?';
                $params = [$status, $note !== '' ? $note : null, $adminName, $now];
                if ($status === 'resolved' && $row['status'] !== 'resolved') {
                    $sets .= ', resolved_at = ?';
                    $params[] = $now;
                } elseif ($status !== 'resolved') {
                    $sets .= ', resolved_at = NULL';
                }
                $params[] = $id;

                try {
                    $upd = $db->prepare('UPDATE tbl_fb_issues SET ' . $sets . ' WHERE id = ?');
                    $upd->execute($params);
                    $msg = 'Ticket #' . $id . ' saved.';

                    // Optional Messenger update, only when the status really changed.
                    if ($notify && $status !== $row['status'] && in_array($status, ['in_progress', 'resolved'], true)) {
                        $lastIn = !empty($row['last_in']) ? strtotime($row['last_in']) : 0;
                        if (!$lastIn) {
                            $msg .= ' The customer was not notified: their Facebook account is no longer linked.';
                        } elseif ($lastIn < time() - 24 * 3600) {
                            $msg .= ' The customer was not notified: Messenger only allows a reply within 24 hours of their last message.';
                        } elseif (fb_send($row['psid'], fbreport_customer_message($id, $status), fb_quick_replies('', true))) {
                            $msg .= ' The customer was notified on Messenger.';
                        } else {
                            $msg .= ' Messenger did not accept the update, so the customer was not notified.';
                        }
                    }

                    $_SESSION['fbreport_flash'] = $msg;
                    header('Location: ' . fbreport_url($back));
                    exit;
                } catch (Throwable $e) {
                    error_log('fbreport: could not save ticket ' . $id . ': ' . $e->getMessage());
                    $error = 'Could not save the change. Please try again.';
                }
            }
        }
    }

    // ---------------------------------------------------------------- filters
    $fStatus = (string) ($_GET['status'] ?? '');
    if (!isset($statuses[$fStatus])) {
        $fStatus = '';
    }
    $fCat = (string) ($_GET['cat'] ?? '');
    if (!isset($cats[$fCat])) {
        $fCat = '';
    }
    $fQ = trim((string) ($_GET['q'] ?? ''));
    $fQ = function_exists('mb_substr') ? mb_substr($fQ, 0, 60, 'UTF-8') : substr($fQ, 0, 60);
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = 20;

    // Customer columns that exist differ between installations.
    $custCols = [];
    try {
        foreach ($db->query('SHOW COLUMNS FROM tbl_customers')->fetchAll(PDO::FETCH_ASSOC) as $col) {
            $custCols[$col['Field']] = true;
        }
    } catch (Throwable $e) {
        error_log('fbreport: could not read tbl_customers columns: ' . $e->getMessage());
    }
    $hasAcct = isset($custCols['account_number']);
    $hasPhone = isset($custCols['phonenumber']);
    $hasAddr = isset($custCols['address']);

    $where = [];
    $args = [];
    if ($fStatus !== '') {
        $where[] = 'i.status = ?';
        $args[] = $fStatus;
    }
    if ($fCat !== '') {
        $where[] = 'i.category = ?';
        $args[] = $fCat;
    }
    if ($fQ !== '') {
        $like = '%' . addcslashes($fQ, '%_\\') . '%';
        $or = ['c.fullname LIKE ?', 'c.username LIKE ?'];
        $args[] = $like;
        $args[] = $like;
        if ($hasAcct) {
            $or[] = 'c.account_number LIKE ?';
            $args[] = $like;
        }
        if (ctype_digit($fQ)) {
            $or[] = 'i.id = ?';
            $args[] = (int) $fQ;
        }
        $where[] = '(' . implode(' OR ', $or) . ')';
    }
    $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
    $from = ' FROM tbl_fb_issues i LEFT JOIN tbl_customers c ON c.id = i.customer_id';

    $stats = ['open' => 0, 'in_progress' => 0, 'resolved' => 0, 'total' => 0];
    $list = [];
    $totalRows = 0;
    $pages = 1;

    try {
        foreach ($db->query('SELECT status, COUNT(*) AS n FROM tbl_fb_issues GROUP BY status')->fetchAll(PDO::FETCH_ASSOC) as $s) {
            if (isset($stats[$s['status']])) {
                $stats[$s['status']] = (int) $s['n'];
            }
            $stats['total'] += (int) $s['n'];
        }

        $cnt = $db->prepare('SELECT COUNT(*)' . $from . $whereSql);
        $cnt->execute($args);
        $totalRows = (int) $cnt->fetchColumn();
        $pages = max(1, (int) ceil($totalRows / $perPage));
        if ($page > $pages) {
            $page = $pages;
        }
        $offset = ($page - 1) * $perPage;

        $select = 'i.id, i.psid, i.customer_id, i.category, i.details, i.status, i.created_at, i.updated_at, i.resolved_at, i.handled_by, i.admin_notes, c.fullname, c.username'
            . ($hasAcct ? ', c.account_number' : ", '' AS account_number")
            . ($hasPhone ? ', c.phonenumber' : ", '' AS phonenumber")
            . ($hasAddr ? ', c.address' : ", '' AS address");

        $q = $db->prepare(
            'SELECT ' . $select . $from . $whereSql
            . " ORDER BY FIELD(i.status, 'open', 'in_progress', 'resolved'), i.id DESC LIMIT " . (int) $perPage . ' OFFSET ' . (int) $offset
        );
        $q->execute($args);

        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $name = trim((string) ($r['fullname'] ?? ''));
            $r['customer_name'] = $name !== '' ? $name : ($r['username'] ? (string) $r['username'] : '(customer removed)');
            $r['phone'] = (string) ($r['phonenumber'] ?? '');
            $r['category_label'] = $cats[$r['category']] ?? (string) $r['category'];
            $r['category_class'] = $catClass[$r['category']] ?? 'default';
            $r['status_label'] = $statuses[$r['status']] ?? (string) $r['status'];
            $r['status_class'] = $statusClass[$r['status']] ?? 'default';
            $r['ago'] = fbreport_ago($r['created_at']);
            $list[] = $r;
        }
    } catch (Throwable $e) {
        error_log('fbreport: could not load reports: ' . $e->getMessage());
        if ($error === '') {
            $error = 'Could not load the reports.';
        }
    }

    // Query string (without the page) used by the pagination links.
    $qsParts = [];
    if ($fStatus !== '') {
        $qsParts['status'] = $fStatus;
    }
    if ($fCat !== '') {
        $qsParts['cat'] = $fCat;
    }
    if ($fQ !== '') {
        $qsParts['q'] = $fQ;
    }

    $ui->assign('fbRpAllowed', true);
    $ui->assign('fbRpError', $error);
    $ui->assign('fbRpNotice', $notice);
    $ui->assign('fbRpStats', $stats);
    $ui->assign('fbRpList', $list);
    $ui->assign('fbRpStatuses', $statuses);
    $ui->assign('fbRpCats', $cats);
    $ui->assign('fbRpFilter', ['status' => $fStatus, 'cat' => $fCat, 'q' => $fQ]);
    $ui->assign('fbRpCsrf', $csrfToken);
    $ui->assign('fbRpQs', $qsParts ? '&' . http_build_query($qsParts) : '');
    $ui->assign('fbRpPage', $page);
    $ui->assign('fbRpPages', $pages);
    $ui->assign('fbRpTotalRows', $totalRows);
    $ui->display('fbreport.tpl');
}
