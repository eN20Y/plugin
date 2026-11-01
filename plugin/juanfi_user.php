<?php
/**
 * BILLSYS plugin: BILLSYFI > User (JuanFi)
 *
 * Install:
 *   system/plugin/juanfi_user.php
 *   system/plugin/ui/juanfi_user.tpl
 *
 * Menu: BILLSYFI > User
 *
 * User page: every hotspot user of every router (/ip/hotspot/user).
 * A user found in /ip/hotspot/active is "Connected", everyone else is "Disconnected".
 *
 * Actions (SuperAdmin / Admin, POST + CSRF):
 *   plugin/juanfi_user_addtime    raise limit-uptime, then disconnect the client
 *   plugin/juanfi_user_deducttime lower limit-uptime, then disconnect the client
 *   plugin/juanfi_user_pause      disconnect the user (time stays frozen, like the portal's Pause)
 *   plugin/juanfi_user_resume     log the device back in (like the portal's Resume)
 *   plugin/juanfi_user_delete     remove the hotspot user, its scheduler, session, cookie and expiry file
 *
 * This file is independent of juanfi_vendo.php (no shared functions).
 */

// "Parent/Child" names make PHPNuxBill group the entries under one BILLSYFI submenu.
register_menu("BILLSYFI/User", true, "juanfi_user", "AFTER_CUSTOMERS", "fa fa-users");

/**
 * Convert a RouterOS duration ("1w2d3h4m5s", "1h", "1d02:03:04") to seconds.
 * Returns null when the value is empty or not understood.
 */
function juanfi_duration_to_seconds($value)
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }
    if (preg_match('/^(?:(\d+)d)?(\d+):(\d{2}):(\d{2})$/', $value, $m)) {
        return ((int) $m[1] * 86400) + ((int) $m[2] * 3600) + ((int) $m[3] * 60) + (int) $m[4];
    }
    if (!preg_match_all('/(\d+)(w|d|h|m|s)/', $value, $parts, PREG_SET_ORDER)) {
        return null;
    }
    $units = ['w' => 604800, 'd' => 86400, 'h' => 3600, 'm' => 60, 's' => 1];
    $total = 0;
    foreach ($parts as $p) {
        $total += (int) $p[1] * $units[$p[2]];
    }
    return $total;
}

/**
 * Convert seconds to a RouterOS duration, e.g. 93784 => "1d2h3m4s".
 */
function juanfi_seconds_to_duration($seconds)
{
    $seconds = max(0, (int) $seconds);
    $out = '';
    foreach (['d' => 86400, 'h' => 3600, 'm' => 60, 's' => 1] as $unit => $size) {
        $n = intdiv($seconds, $size);
        $seconds %= $size;
        if ($n) {
            $out .= $n . $unit;
        }
    }
    return $out !== '' ? $out : '0s';
}

/**
 * Show an expiry value as a readable date when it is a unix timestamp or a date PHP understands;
 * otherwise show it exactly as the router file has it.
 */
function juanfi_format_expiry($raw)
{
    $raw = trim((string) $raw);
    $ts = (ctype_digit($raw) && strlen($raw) === 10) ? (int) $raw : strtotime($raw);
    return $ts ? date('M d, Y h:i A', $ts) : $raw;
}

/**
 * Latest expiry per username, taken from BILLSYS recharges (tbl_user_recharges).
 * Users with no recharge record get no expiry.
 *
 * @return array username => formatted expiry date
 */
function juanfi_expiry_map()
{
    $map = [];
    try {
        $rows = ORM::for_table('tbl_user_recharges')
            ->select_many('username', 'expiration', 'time')
            ->order_by_asc('id')   // later rows overwrite earlier ones, so the newest recharge wins
            ->find_array();
        foreach ($rows as $row) {
            $ts = strtotime(trim($row['expiration'] . ' ' . $row['time']));
            $map[$row['username']] = $ts ? date('M d, Y h:i A', $ts) : '';
        }
    } catch (Throwable $e) {
        error_log('juanfi: expiry lookup failed: ' . $e->getMessage());
    }
    return $map;
}

/**
 * Read hotspot users and active sessions from every enabled router.
 * One router being offline does not stop the others: it is reported in $errors instead.
 *
 * @param bool $showDetail true = put the real error message in $errors (SuperAdmin / Admin only)
 * @return array [rows, errors, routerNames]
 */
function juanfi_collect_hotspot($showDetail = false)
{
    $rows = [];
    $errors = [];
    $routerNames = [];
    $expiry = juanfi_expiry_map();

    $routers = ORM::for_table('tbl_routers')->order_by_asc('name')->find_many();
    foreach ($routers as $r) {
        if (isset($r['enabled']) && (string) $r['enabled'] === '0') {
            continue;
        }
        $routerNames[] = (string) $r['name'];

        try {
            $client = Mikrotik::getClient($r['ip_address'], $r['username'], $r['password']);

            // Active sessions, keyed by username (first session wins if a user is logged in twice).
            $active = [];
            $resp = $client->sendSync(new PEAR2\Net\RouterOS\Request('/ip/hotspot/active/print'));
            // The closing "!done" reply has no properties, so the empty-name check skips it.
            foreach ($resp as $a) {
                $name = (string) $a->getProperty('user');
                if ($name !== '' && !isset($active[$name])) {
                    $active[$name] = $a;
                }
            }

            // IP of devices that are not logged in (paused / disconnected): hotspot host list, matched by MAC.
            $hostIps = [];
            try {
                foreach ($client->sendSync(new PEAR2\Net\RouterOS\Request('/ip/hotspot/host/print')) as $h) {
                    $hostMac = strtoupper((string) $h->getProperty('mac-address'));
                    $hostIp = (string) $h->getProperty('address');
                    if ($hostMac !== '' && $hostIp !== '' && !isset($hostIps[$hostMac])) {
                        $hostIps[$hostMac] = $hostIp;
                    }
                }
            } catch (Throwable $hostError) {
                error_log('juanfi: hotspot hosts of "' . $r['name'] . '" not read: ' . $hostError->getMessage());
            }

            // Hostnames (and a fallback IP) come from DHCP leases, matched by MAC.
            // A router without DHCP just shows no hostname.
            $hostnames = [];
            $leaseIps = [];
            try {
                $resp = $client->sendSync(new PEAR2\Net\RouterOS\Request('/ip/dhcp-server/lease/print'));
                foreach ($resp as $l) {
                    $leaseMac = strtoupper((string) $l->getProperty('mac-address'));
                    $leaseHost = (string) $l->getProperty('host-name');
                    $leaseIp = (string) $l->getProperty('address');
                    if ($leaseMac !== '' && $leaseHost !== '') {
                        $hostnames[$leaseMac] = $leaseHost;
                    }
                    if ($leaseMac !== '' && $leaseIp !== '') {
                        $leaseIps[$leaseMac] = $leaseIp;
                    }
                }
            } catch (Throwable $leaseError) {
                error_log('juanfi: DHCP leases of "' . $r['name'] . '" not read: ' . $leaseError->getMessage());
            }

            // Expiry that the portal reads from <hotspot folder>/data/<mac>.txt, saved as "user#expiry".
            // Optional: if the router does not return file contents, the BILLSYS recharge expiry is used instead.
            $fileExpiry = [];
            $fileMac = [];
            try {
                $req = new PEAR2\Net\RouterOS\Request('/file/print');
                $req->setArgument('.proplist', 'name,contents');
                foreach ($client->sendSync($req) as $f) {
                    if (!preg_match('~(^|/)data/([A-Za-z0-9]+)\.txt$~', (string) $f->getProperty('name'), $fm)) {
                        continue;
                    }
                    $content = trim((string) $f->getProperty('contents'));
                    if ($content === '' || strlen($content) > 50) {
                        continue;
                    }
                    $bits = explode('#', $content);
                    // The file name is the device MAC without colons.
                    if ($bits[0] !== '' && preg_match('/^[0-9A-Fa-f]{12}$/', $fm[2])) {
                        $fileMac[$bits[0]] = strtoupper(implode(':', str_split($fm[2], 2)));
                    }
                    if ($bits[0] !== '' && isset($bits[1]) && trim($bits[1]) !== '') {
                        $fileExpiry[$bits[0]] = juanfi_format_expiry($bits[1]);
                    }
                }
            } catch (Throwable $fileError) {
                error_log('juanfi: data files of "' . $r['name'] . '" not read: ' . $fileError->getMessage());
            }

            $resp = $client->sendSync(new PEAR2\Net\RouterOS\Request('/ip/hotspot/user/print'));
            foreach ($resp as $u) {
                $name = (string) $u->getProperty('name');
                if ($name === '') {
                    continue;
                }
                $a = $active[$name] ?? null;

                // Skip users that never used any time (uptime 0s), unless they are connected right now.
                $usedTime = (string) $u->getProperty('uptime');
                if ($a === null && ($usedTime === '' || $usedTime === '0s')) {
                    continue;
                }

                $mac = $a ? (string) $a->getProperty('mac-address') : (string) $u->getProperty('mac-address');
                if ($mac === '' || $mac === '00:00:00:00:00:00') {
                    $mac = $fileMac[$name] ?? '';
                }
                $macKey = strtoupper($mac);

                // Time left in seconds (null = no time limit).
                // Connected: the router's own session-time-left. Otherwise: limit-uptime minus uptime.
                if ($a !== null) {
                    $left = juanfi_duration_to_seconds($a->getProperty('session-time-left'));
                } else {
                    $limit = juanfi_duration_to_seconds($u->getProperty('limit-uptime'));
                    $used = juanfi_duration_to_seconds($u->getProperty('uptime')) ?? 0;
                    $left = ($limit !== null && $limit > 0) ? max(0, $limit - $used) : null;
                }
                $rows[] = [
                    'router'    => (string) $r['name'],
                    'hostname'  => $hostnames[$macKey] ?? '',
                    'username'  => $name,
                    'mac'       => $mac,
                    'ip'        => $a ? (string) $a->getProperty('address') : ($hostIps[$macKey] ?? $leaseIps[$macKey] ?? ''),
                    'profile'   => (string) $u->getProperty('profile'),
                    'left'      => $left,
                    'expiry'    => $fileExpiry[$name] ?? ($expiry[$name] ?? ''),
                    'connected' => $a !== null,
                    // Paused = same as the portal's "Time Pause": not connected but time is left (or disabled by an older pause).
                    'paused'    => strtolower((string) $u->getProperty('disabled')) === 'true'
                                   || ($a === null && $left !== null && $left > 0),
                ];
            }
        } catch (Throwable $e) {
            error_log('juanfi: router "' . $r['name'] . '" failed: ' . $e->getMessage());
            $errors[] = $showDetail
                ? 'Router "' . $r['name'] . '" (' . $r['ip_address'] . '): ' . get_class($e) . ': ' . $e->getMessage()
                : 'Could not read router "' . $r['name'] . '". Check that it is online and the API login works.';
        }
    }

    // Connected users first, then A-Z.
    usort($rows, static function ($x, $y) {
        if ($x['connected'] !== $y['connected']) {
            return $x['connected'] ? -1 : 1;
        }
        return strcasecmp($x['username'], $y['username']);
    });

    return [$rows, $errors, $routerNames];
}

/**
 * Page numbers for the paginator: 1 ... 4 5 [6] 7 8 ... 20
 *
 * @return array list of ints and '...' entries
 */
function juanfi_page_list($page, $count)
{
    $pages = [];
    $last = 0;
    for ($i = 1; $i <= $count; $i++) {
        if ($i === 1 || $i === $count || abs($i - $page) <= 2) {
            if ($last && $i - $last > 1) {
                $pages[] = '...';
            }
            $pages[] = $i;
            $last = $i;
        }
    }
    return $pages;
}

function juanfi_user()
{
    global $ui, $routes;

    _admin();

    $admin = Admin::_info();
    $ui->assign('_system_menu', 'juanfi_user');
    $ui->assign('_admin', $admin);
    $ui->assign('routes', $routes);
    $ui->assign('_title', 'BILLSYFI Users');

    // Only SuperAdmin / Admin see the real router error (it can include the router address).
    $showDetail = (is_array($admin) || is_object($admin))
        && in_array((string) ($admin['user_type'] ?? ''), ['SuperAdmin', 'Admin'], true);

    list($rows, $errors, $routerNames) = juanfi_collect_hotspot($showDetail);

    $connected = 0;
    foreach ($rows as $row) {
        if ($row['connected']) {
            $connected++;
        }
    }

    // Filters come from the URL, so they apply to every page, not just the one on screen.
    $status = (string) ($_GET['status'] ?? '');
    if (!in_array($status, ['connected', 'disconnected'], true)) {
        $status = '';
    }
    $routerFilter = (string) ($_GET['router'] ?? '');
    if (!in_array($routerFilter, $routerNames, true)) {
        $routerFilter = '';
    }
    $q = trim((string) ($_GET['q'] ?? ''));

    $filtered = array_values(array_filter($rows, static function ($row) use ($status, $routerFilter, $q) {
        if ($status === 'connected' && !$row['connected']) {
            return false;
        }
        if ($status === 'disconnected' && $row['connected']) {
            return false;
        }
        if ($routerFilter !== '' && $row['router'] !== $routerFilter) {
            return false;
        }
        if ($q !== '') {
            $hay = $row['hostname'] . ' ' . $row['username'] . ' ' . $row['mac'] . ' ' . $row['ip'] . ' ' . $row['profile'];
            if (stripos($hay, $q) === false) {
                return false;
            }
        }
        return true;
    }));

    // Pagination: 25 rows per page, page number in ?p= like the other PHPNuxBill lists.
    $perPage = 25;
    $shown = count($filtered);
    $pageCount = max(1, (int) ceil($shown / $perPage));
    $page = min($pageCount, max(1, (int) ($_GET['p'] ?? 1)));
    $pageRows = array_slice($filtered, ($page - 1) * $perPage, $perPage);

    $paginator = null;
    if ($pageCount > 1) {
        $query = array_filter(
            ['status' => $status, 'router' => $routerFilter, 'q' => $q],
            static fn($v) => $v !== ''
        );
        $base = '?_route=';
        $paginator = [
            'url'   => $base . 'plugin/juanfi_user&' . ($query ? http_build_query($query) . '&' : '') . 'p=',
            'page'  => $page,
            'count' => $pageCount,
            'prev'  => $page > 1 ? $page - 1 : '',
            'next'  => $page + 1,
            'pages' => juanfi_page_list($page, $pageCount),
        ];
    }

    $ui->assign('juanfiUsers', $pageRows);
    $ui->assign('paginator', $paginator);
    $ui->assign('juanfiFilter', ['status' => $status, 'router' => $routerFilter, 'q' => $q]);
    $ui->assign('juanfiErrors', $errors);
    $ui->assign('juanfiRouters', $routerNames);
    $ui->assign('juanfiCounts', [
        'total'        => count($rows),
        'connected'    => $connected,
        'disconnected' => count($rows) - $connected,
        'shown'        => $shown,
    ]);
    $ui->display('juanfi_user.tpl');
}

/**
 * Shared checks for the action routes: POST only, SuperAdmin/Admin only, valid CSRF token.
 */
function juanfi_action_guard($back)
{
    _admin();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        r2($back);
    }

    $admin = Admin::_info();
    if (!in_array((string) ($admin['user_type'] ?? ''), ['SuperAdmin', 'Admin'], true)) {
        r2($back, 'e', 'You are not allowed to do this.');
    }

    if (class_exists('Csrf') && !Csrf::check((string) ($_POST['csrf_token'] ?? ''))) {
        r2($back, 'e', 'Invalid or expired token. Please try again.');
    }
}

/**
 * Connect to the posted router and find the posted hotspot user.
 * May throw; callers wrap it in try/catch.
 *
 * @return array [client, user response, user .id, username]
 */
function juanfi_action_connect($back)
{
    $routerName = trim((string) ($_POST['router'] ?? ''));
    $username   = trim((string) ($_POST['username'] ?? ''));
    if ($routerName === '' || $username === '') {
        r2($back, 'e', 'Invalid request.');
    }

    $router = ORM::for_table('tbl_routers')->where('name', $routerName)->find_one();
    if (!$router) {
        r2($back, 'e', 'Router not found.');
    }

    $client = Mikrotik::getClient($router['ip_address'], $router['username'], $router['password']);

    $user = $client->sendSync(
        (new PEAR2\Net\RouterOS\Request('/ip/hotspot/user/print'))
            ->setQuery(PEAR2\Net\RouterOS\Query::where('name', $username))
    );
    $id = (string) $user->getProperty('.id');
    if ($id === '') {
        r2($back, 'e', 'User "' . $username . '" not found on router.');
    }

    return [$client, $user, $id, $username];
}

/**
 * Remove every active hotspot session of a user.
 *
 * @return array removed sessions as [['ip' => ..., 'mac' => ...], ...]
 */
function juanfi_kick($client, $username)
{
    $removed = [];
    $active = $client->sendSync(
        (new PEAR2\Net\RouterOS\Request('/ip/hotspot/active/print'))
            ->setQuery(PEAR2\Net\RouterOS\Query::where('user', $username))
    );
    foreach ($active as $session) {
        $sessionId = (string) $session->getProperty('.id');
        if ($sessionId === '') {
            continue;
        }
        $removed[] = [
            'ip'  => (string) $session->getProperty('address'),
            'mac' => (string) $session->getProperty('mac-address'),
        ];
        $client->sendSync(
            (new PEAR2\Net\RouterOS\Request('/ip/hotspot/active/remove'))
                ->setArgument('numbers', $sessionId)
        );
    }
    return $removed;
}

/**
 * Log the user straight back in on the router for each removed session
 * (/ip/hotspot/active/login), so the client reconnects without doing anything.
 *
 * @return int number of sessions logged in again
 */
function juanfi_relogin($client, $username, $password, array $sessions)
{
    $done = 0;
    foreach ($sessions as $s) {
        if ($s['ip'] === '') {
            continue;
        }
        try {
            $req = (new PEAR2\Net\RouterOS\Request('/ip/hotspot/active/login'))
                ->setArgument('user', $username)
                ->setArgument('password', $password)
                ->setArgument('ip', $s['ip']);
            if ($s['mac'] !== '') {
                $req->setArgument('mac-address', $s['mac']);
            }
            $client->sendSync($req);
            $done++;
        } catch (Throwable $e) {
            error_log('juanfi: re-login of "' . $username . '" failed: ' . $e->getMessage());
        }
    }
    return $done;
}

/**
 * Remove every row of a RouterOS menu (e.g. '/system/scheduler') where $key equals $value.
 *
 * @return int number of rows removed
 */
function juanfi_ros_remove($client, $path, $key, $value)
{
    $removed = 0;
    $req = new PEAR2\Net\RouterOS\Request($path . '/print');
    $req->setArgument('.proplist', '.id');
    $req->setQuery(PEAR2\Net\RouterOS\Query::where($key, $value));

    foreach ($client->sendSync($req) as $row) {
        $rowId = (string) $row->getProperty('.id');
        if ($rowId === '') {
            continue;
        }
        $client->sendSync(
            (new PEAR2\Net\RouterOS\Request($path . '/remove'))->setArgument('numbers', $rowId)
        );
        $removed++;
    }
    return $removed;
}

/**
 * Delete the expiry file(s) of a user from the router: the portal keeps them as
 * <hotspot folder>/data/<mac>.txt with "username#expiry" inside.
 *
 * @return int number of files removed
 */
function juanfi_delete_expiry($client, $username)
{
    $removed = 0;
    try {
        $req = new PEAR2\Net\RouterOS\Request('/file/print');
        $req->setArgument('.proplist', '.id,name,contents');
        foreach ($client->sendSync($req) as $f) {
            if (!preg_match('~(^|/)data/[A-Za-z0-9]+\.txt$~', (string) $f->getProperty('name'))) {
                continue;
            }
            $bits = explode('#', trim((string) $f->getProperty('contents')));
            if ($bits[0] !== $username) {
                continue;
            }
            $client->sendSync(
                (new PEAR2\Net\RouterOS\Request('/file/remove'))
                    ->setArgument('numbers', (string) $f->getProperty('.id'))
            );
            $removed++;
        }
    } catch (Throwable $e) {
        error_log('juanfi: expiry file delete failed for "' . $username . '": ' . $e->getMessage());
    }
    return $removed;
}

/**
 * Add ($direction = 1) or deduct ($direction = -1) time on limit-uptime,
 * then disconnect the client so the new limit applies on the next login.
 */
function juanfi_adjust_time($direction)
{
    $back = '?_route=plugin/juanfi_user';
    juanfi_action_guard($back);

    $unitSecs = ['m' => 60, 'h' => 3600, 'd' => 86400][$_POST['unit'] ?? ''] ?? 0;
    $secs     = (int) ($_POST['amount'] ?? 0) * $unitSecs;
    if ($secs <= 0) {
        r2($back, 'e', 'Invalid amount.');
    }

    try {
        list($client, $user, $id, $username) = juanfi_action_connect($back);

        $limit = juanfi_duration_to_seconds($user->getProperty('limit-uptime'));
        if (!$limit) {
            r2($back, 'e', 'User "' . $username . '" has no time limit, nothing to change.');
        }

        $new = $limit + $direction * $secs;
        if ($new <= 0) {
            r2($back, 'e', 'Cannot deduct that much: the total limit is only ' . juanfi_seconds_to_duration($limit) . '.');
        }

        $newLimit = juanfi_seconds_to_duration($new);
        $client->sendSync(
            (new PEAR2\Net\RouterOS\Request('/ip/hotspot/user/set'))
                ->setArgument('numbers', $id)
                ->setArgument('limit-uptime', $newLimit)
        );

        // Disconnect, then log the same device straight back in with the new limit.
        $sessions    = juanfi_kick($client, $username);
        $reconnected = juanfi_relogin($client, $username, (string) $user->getProperty('password'), $sessions);
    } catch (Throwable $e) {
        error_log('juanfi: time change failed: ' . $e->getMessage());
        r2($back, 'e', 'Router error: ' . $e->getMessage());
    }

    r2($back, 's', ($direction > 0 ? 'Added ' : 'Deducted ') . juanfi_seconds_to_duration($secs)
        . ' ' . ($direction > 0 ? 'to ' : 'from ') . $username . '. New limit: ' . $newLimit
        . (count($sessions) === 0
            ? ' (user was not connected).'
            : ($reconnected === count($sessions)
                ? ' Client was disconnected and reconnected.'
                : ' Client was disconnected; it must log in again.')));
}

/** Route: plugin/juanfi_user_addtime (POST: router, username, amount, unit m|h|d) */
function juanfi_user_addtime()
{
    juanfi_adjust_time(1);
}

/** Route: plugin/juanfi_user_deducttime (POST: router, username, amount, unit m|h|d) */
function juanfi_user_deducttime()
{
    juanfi_adjust_time(-1);
}

/**
 * Find the device behind a hotspot user: its MAC (user entry, or the portal's
 * data/<mac>.txt file that contains "username#expiry") and its current IP
 * (hotspot host list, which also has devices that are not logged in).
 *
 * @return array ['mac' => ..., 'ip' => ...] (empty strings when not found)
 */
function juanfi_find_device($client, $username, $user)
{
    $mac = strtoupper((string) $user->getProperty('mac-address'));
    if ($mac === '00:00:00:00:00:00') {
        $mac = '';
    }

    if ($mac === '') {
        try {
            $req = new PEAR2\Net\RouterOS\Request('/file/print');
            $req->setArgument('.proplist', 'name,contents');
            foreach ($client->sendSync($req) as $f) {
                if (!preg_match('~(^|/)data/([0-9A-Fa-f]{12})\.txt$~', (string) $f->getProperty('name'), $m)) {
                    continue;
                }
                $bits = explode('#', trim((string) $f->getProperty('contents')));
                if ($bits[0] === $username) {
                    $mac = strtoupper(implode(':', str_split($m[2], 2)));
                    break;
                }
            }
        } catch (Throwable $e) {
            error_log('juanfi: device lookup failed for "' . $username . '": ' . $e->getMessage());
        }
    }

    $ip = '';
    if ($mac !== '') {
        try {
            $hosts = $client->sendSync(
                (new PEAR2\Net\RouterOS\Request('/ip/hotspot/host/print'))
                    ->setQuery(PEAR2\Net\RouterOS\Query::where('mac-address', $mac))
            );
            $ip = (string) $hosts->getProperty('address');
        } catch (Throwable $e) {
            error_log('juanfi: host lookup failed for "' . $mac . '": ' . $e->getMessage());
        }
    }

    return ['mac' => $mac, 'ip' => $ip];
}

/**
 * Pause ($pause = true) or resume ($pause = false) a hotspot user, like the portal does:
 * pause = disconnect (time only counts while connected, so the remaining time stays frozen),
 * resume = log the device back in.
 */
function juanfi_set_paused($pause)
{
    $back = '?_route=plugin/juanfi_user';
    juanfi_action_guard($back);

    try {
        list($client, $user, $id, $username) = juanfi_action_connect($back);

        // Pause works like the portal's Pause (disconnect only), so the portal's own
        // Resume / auto-resume login keeps working. Only undo an older "disabled" pause.
        if (!$pause && strtolower((string) $user->getProperty('disabled')) === 'true') {
            $client->sendSync(
                (new PEAR2\Net\RouterOS\Request('/ip/hotspot/user/set'))
                    ->setArgument('numbers', $id)
                    ->setArgument('disabled', 'no')
            );
        }

        $loggedIn = false;
        if ($pause) {
            juanfi_kick($client, $username);
        } else {
            // Same as the portal's Resume button: log the user in again with its username/password.
            $device = juanfi_find_device($client, $username, $user);
            if ($device['ip'] !== '') {
                $loggedIn = juanfi_relogin(
                    $client,
                    $username,
                    (string) $user->getProperty('password'),
                    [$device]
                ) > 0;
            }
        }
    } catch (Throwable $e) {
        error_log('juanfi: pause/resume failed: ' . $e->getMessage());
        r2($back, 'e', 'Router error: ' . $e->getMessage());
    }

    if ($pause) {
        r2($back, 's', $username . ' is paused (disconnected). The remaining time is kept; the user can also resume from the portal.');
    }
    r2($back, 's', $username . ($loggedIn
        ? ' is resumed and connected.'
        : ' is resumed, but the device was not found online. It will connect when it logs in.'));
}

/** Route: plugin/juanfi_user_pause (POST: router, username) */
function juanfi_user_pause()
{
    juanfi_set_paused(true);
}

/** Route: plugin/juanfi_user_resume (POST: router, username) */
function juanfi_user_resume()
{
    juanfi_set_paused(false);
}

/**
 * Route: plugin/juanfi_user_delete (POST: router, username)
 * Removes the hotspot user and everything the portal keeps for it, in this order:
 *   scheduler (first, so its on-event cannot fire) -> active session -> hotspot cookie
 *   -> expiry file data/<mac>.txt -> hotspot user
 * A leftover scheduler is what kept the old expiry date after a new top-up.
 */
function juanfi_user_delete()
{
    $back = '?_route=plugin/juanfi_user';
    juanfi_action_guard($back);

    try {
        list($client, $user, $id, $username) = juanfi_action_connect($back);

        // 1. Scheduler (the user's expiry timer)
        $schedulers = 0;
        try {
            $schedulers = juanfi_ros_remove($client, '/system/scheduler', 'name', $username);
        } catch (Throwable $e) {
            error_log('juanfi: scheduler delete failed for "' . $username . '": ' . $e->getMessage());
        }

        // 2. Active session(s)
        juanfi_kick($client, $username);

        // 3. Hotspot cookie (otherwise the device can log itself back in)
        try {
            juanfi_ros_remove($client, '/ip/hotspot/cookie', 'user', $username);
        } catch (Throwable $e) {
            error_log('juanfi: cookie delete failed for "' . $username . '": ' . $e->getMessage());
        }

        // 4. Expiry file data/<mac>.txt
        $expiryFiles = juanfi_delete_expiry($client, $username);

        // 5. The hotspot user itself
        $client->sendSync(
            (new PEAR2\Net\RouterOS\Request('/ip/hotspot/user/remove'))
                ->setArgument('numbers', $id)
        );
    } catch (Throwable $e) {
        error_log('juanfi: delete failed: ' . $e->getMessage());
        r2($back, 'e', 'Router error: ' . $e->getMessage());
    }

    r2($back, 's', 'Deleted hotspot user ' . $username
        . ($schedulers ? ', its scheduler' : ' (no scheduler found)')
        . ($expiryFiles ? ' and its expiry file.' : ' (no expiry file found).'));
}