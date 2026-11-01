<?php
/**
 * BILLSYS plugin: BILLSYFI > Server Setup (JuanFi)
 *
 * Install:
 *   system/plugin/juanfi_server.php
 *   system/plugin/ui/juanfi_server.tpl
 *
 * Creates, in one go, on a MikroTik router: VLAN interface, IP address, address-list entry,
 * DHCP pool + network + server, hotspot profile + hotspot server. Can remove them again.
 *
 *   plugin/juanfi_server          the list page
 *   plugin/juanfi_server_add      create a VLAN with DHCP + hotspot   (SuperAdmin / Admin, POST + CSRF)
 *   plugin/juanfi_server_delete   remove one (only those commented BILLSYFI)
 *
 * This file is independent of juanfi_vendo.php (all functions are juanfi_srv_* / juanfi_server_*).
 */

register_menu("BILLSYFI/Server Setup", true, "juanfi_server", "AFTER_CUSTOMERS", "fa fa-server");

const JUANFI_SRV_TAG = 'BILLSYFI';

/** Rows of a RouterOS "print": list of ['.id' =>, prop => string, ...]. May throw on connection errors. */
function juanfi_srv_rows($client, $path, array $props)
{
    $req = new PEAR2\Net\RouterOS\Request($path . '/print');
    $req->setArgument('.proplist', implode(',', array_merge(['.id'], $props)));
    $rows = [];
    foreach ($client->sendSync($req) as $x) {
        $id = (string) $x->getProperty('.id');
        if ($id === '') {
            continue;
        }
        $row = ['.id' => $id];
        foreach ($props as $p) {
            $row[$p] = (string) $x->getProperty($p);
        }
        $rows[] = $row;
    }
    return $rows;
}

/** First row where every key in $match equals the given value, or null. */
function juanfi_srv_find(array $rows, array $match)
{
    foreach ($rows as $r) {
        $ok = true;
        foreach ($match as $k => $v) {
            if (($r[$k] ?? null) !== $v) {
                $ok = false;
                break;
            }
        }
        if ($ok) {
            return $r;
        }
    }
    return null;
}

/** Send one command. Returns '' when it worked, or the router's error text (the API client does not throw on !trap). */
function juanfi_srv_send($client, $path, array $args)
{
    try {
        $req = new PEAR2\Net\RouterOS\Request($path);
        foreach ($args as $k => $v) {
            $req->setArgument($k, (string) $v);
        }
        $res = $client->sendSync($req);
    } catch (Throwable $e) {
        return $e->getMessage();
    }
    foreach ($res as $x) {
        $t = $x->getType();
        if ($t === PEAR2\Net\RouterOS\Response::TYPE_ERROR || $t === PEAR2\Net\RouterOS\Response::TYPE_FATAL) {
            $m = trim((string) $x->getProperty('message'));
            return $m !== '' ? $m : 'the router returned an error';
        }
    }
    return '';
}

/**
 * Parse "a.b.c.d/p" into network numbers.
 * @return array|null ['ip','prefix','net','bc','first','last','network']
 */
function juanfi_srv_net($cidr, $min = 16, $max = 29)
{
    if (!preg_match('~^(\d{1,3}(?:\.\d{1,3}){3})/(\d{1,2})$~', (string) $cidr, $m)) {
        return null;
    }
    $ip = ip2long($m[1]);
    $p  = (int) $m[2];
    if ($ip === false || $p < $min || $p > $max) {
        return null;
    }
    $mask = (-1 << (32 - $p)) & 0xFFFFFFFF;
    $net  = $ip & $mask;
    $bc   = $net | (~$mask & 0xFFFFFFFF);
    return [
        'ip'      => long2ip($ip),
        'prefix'  => $p,
        'net'     => $net,
        'bc'      => $bc,
        'first'   => $net + 1,
        'last'    => $bc - 1,
        'network' => long2ip($net) . '/' . $p,
    ];
}

function juanfi_server_guard($back)
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

/** Connect to a router by its name in tbl_routers. */
function juanfi_srv_client($routerName)
{
    $router = ORM::for_table('tbl_routers')->where('name', $routerName)->find_one();
    if (!$router) {
        return null;
    }
    return Mikrotik::getClient($router['ip_address'], $router['username'], $router['password']);
}

/**
 * Read VLANs (with their IP / DHCP / hotspot) and the choices for the Add form from every enabled router.
 *
 * @return array [rows, errors, routersData]
 */
function juanfi_server_read($showDetail)
{
    $rows = [];
    $errors = [];
    $data = [];

    foreach (ORM::for_table('tbl_routers')->order_by_asc('name')->find_many() as $r) {
        if (isset($r['enabled']) && (string) $r['enabled'] === '0') {
            continue;
        }
        try {
            $c = Mikrotik::getClient($r['ip_address'], $r['username'], $r['password']);

            $vlans = juanfi_srv_rows($c, '/interface/vlan', ['name', 'vlan-id', 'interface', 'comment', 'disabled']);
            $addrs = juanfi_srv_rows($c, '/ip/address', ['address', 'interface']);
            $dhcp  = juanfi_srv_rows($c, '/ip/dhcp-server', ['name', 'interface']);
            $hs    = juanfi_srv_rows($c, '/ip/hotspot', ['name', 'interface']);
            $ifs   = juanfi_srv_rows($c, '/interface', ['name', 'type']);
            $prof  = juanfi_srv_rows($c, '/ip/hotspot/profile', ['name']);

            $parents = [];
            foreach ($ifs as $i) {
                if ($i['type'] !== 'vlan' && $i['name'] !== '') {
                    $parents[] = $i['name'];
                }
            }
            $profiles = [];
            foreach ($prof as $p) {
                $profiles[] = $p['name'];
            }
            $data[(string) $r['name']] = ['parents' => $parents, 'profiles' => $profiles];

            foreach ($vlans as $v) {
                $a = juanfi_srv_find($addrs, ['interface' => $v['name']]);
                $d = juanfi_srv_find($dhcp, ['interface' => $v['name']]);
                $h = juanfi_srv_find($hs, ['interface' => $v['name']]);
                $rows[] = [
                    'router'   => (string) $r['name'],
                    'name'     => $v['name'],
                    'vlan_id'  => $v['vlan-id'],
                    'parent'   => $v['interface'],
                    'address'  => $a ? $a['address'] : '',
                    'dhcp'     => $d ? $d['name'] : '',
                    'hotspot'  => $h ? $h['name'] : '',
                    'managed'  => $v['comment'] === JUANFI_SRV_TAG,
                    'disabled' => $v['disabled'] === 'true',
                ];
            }
        } catch (Throwable $e) {
            error_log('juanfi: server list, router "' . $r['name'] . '" failed: ' . $e->getMessage());
            $errors[] = $showDetail
                ? 'Router "' . $r['name'] . '" (' . $r['ip_address'] . '): ' . $e->getMessage()
                : 'Could not read router "' . $r['name'] . '". Check that it is online and the API login works.';
        }
    }

    return [$rows, $errors, $data];
}

function juanfi_server()
{
    global $ui, $routes;

    _admin();

    $admin = Admin::_info();
    if (!in_array((string) ($admin['user_type'] ?? ''), ['SuperAdmin', 'Admin'], true)) {
        r2(U . 'dashboard', 'e', 'You are not allowed to do this.');
    }

    $ui->assign('_system_menu', 'juanfi_server');
    $ui->assign('_admin', $admin);
    $ui->assign('routes', $routes);
    $ui->assign('_title', 'BILLSYFI Server Setup');

    list($rows, $errors, $data) = juanfi_server_read(true);

    $ui->assign('juanfiSrvRows', $rows);
    $ui->assign('juanfiSrvErrors', $errors);
    $ui->assign('juanfiSrvRouters', array_keys($data));
    $ui->assign('juanfiSrvData', json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
    $ui->display('juanfi_server.tpl');
}

/**
 * Route: plugin/juanfi_server_add
 * POST: router, parent, vlan_id, name, gateway (a.b.c.d/p), pool_start, pool_end, dns, lease, list, profile, csrf_token
 */
function juanfi_server_add()
{
    $back = '?_route=plugin/juanfi_server';
    juanfi_server_guard($back);

    $routerName = trim((string) ($_POST['router'] ?? ''));
    $parent     = trim((string) ($_POST['parent'] ?? ''));
    $vlanId     = trim((string) ($_POST['vlan_id'] ?? ''));
    $name       = trim((string) ($_POST['name'] ?? ''));
    $gateway    = trim((string) ($_POST['gateway'] ?? ''));
    $poolStart  = trim((string) ($_POST['pool_start'] ?? ''));
    $poolEnd    = trim((string) ($_POST['pool_end'] ?? ''));
    $dns        = trim((string) ($_POST['dns'] ?? ''));
    $lease      = trim((string) ($_POST['lease'] ?? ''));
    $list       = trim((string) ($_POST['list'] ?? ''));
    $profile    = trim((string) ($_POST['profile'] ?? ''));

    if ($lease === '') {
        $lease = '1d';
    }
    if ($list === '') {
        $list = 'JuanfiVlan';
    }

    // ---- validate everything before touching the router ----
    if ($routerName === '' || !preg_match('/^[A-Za-z0-9 _\-\.:@\/]{1,30}$/', $parent)) {
        r2($back, 'e', 'Choose a router and a parent interface.');
    }
    if (!ctype_digit($vlanId) || (int) $vlanId < 1 || (int) $vlanId > 4094) {
        r2($back, 'e', 'VLAN ID must be a number from 1 to 4094.');
    }
    $vlanId = (int) $vlanId;
    if ($name === '') {
        $name = 'VLAN' . $vlanId;
    }
    if (!preg_match('/^[A-Za-z0-9_\-\.]{1,15}$/', $name)) {
        r2($back, 'e', 'VLAN name must be 1-15 characters: letters, numbers and - _ . only (no spaces).');
    }
    $net = juanfi_srv_net($gateway);
    if ($net === null) {
        r2($back, 'e', 'Gateway must look like 10.2.1.1/24 (prefix 16 to 29).');
    }
    $g = ip2long($net['ip']);
    if ($g < $net['first'] || $g > $net['last']) {
        r2($back, 'e', 'The gateway cannot be the network or broadcast address.');
    }
    $start = $poolStart !== '' ? ip2long($poolStart) : $g + 1;
    $end   = $poolEnd !== '' ? ip2long($poolEnd) : $net['last'];
    if (($poolStart !== '' && !filter_var($poolStart, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4))
        || ($poolEnd !== '' && !filter_var($poolEnd, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4))
        || $start === false || $end === false) {
        r2($back, 'e', 'Pool start / end must be valid IPv4 addresses (or empty for automatic).');
    }
    if ($start < $net['first'] || $end > $net['last'] || $start > $end) {
        r2($back, 'e', 'The DHCP pool must be inside ' . $net['network'] . ' and start before it ends.');
    }
    if ($start <= $g && $g <= $end) {
        r2($back, 'e', 'The DHCP pool must not include the gateway ' . $net['ip'] . '.');
    }
    $dnsList = [];
    foreach (array_filter(array_map('trim', explode(',', $dns))) as $d) {
        if (!filter_var($d, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            r2($back, 'e', 'DNS servers must be IPv4 addresses separated by commas.');
        }
        $dnsList[] = $d;
    }
    if (!$dnsList) {
        $dnsList[] = $net['ip'];
    }
    if (!preg_match('/^(\d{1,4}[smhdw]?|\d{1,2}:\d{2}:\d{2})$/', $lease)) {
        r2($back, 'e', 'Lease time must look like 1d, 12h, 30m or 01:00:00.');
    }
    if (!preg_match('/^[A-Za-z0-9_\-\.]{1,30}$/', $list)) {
        r2($back, 'e', 'Address list name: letters, numbers and - _ . only.');
    }
    if (!preg_match('/^[A-Za-z0-9 _\-\.]{1,30}$/', $profile)) {
        r2($back, 'e', 'Choose a hotspot profile to copy.');
    }

    $poolName = 'pool-' . $name;
    $dhcpName = 'dhcp-' . $name;
    $hsName   = 'hotspot-' . $name;
    $profName = 'hsprof-' . $name;
    $cidr     = $net['ip'] . '/' . $net['prefix'];
    $notes    = [];

    try {
        $c = juanfi_srv_client($routerName);
        if ($c === null) {
            r2($back, 'e', 'Router not found.');
        }

        $ifs   = juanfi_srv_rows($c, '/interface', ['name', 'type']);
        $vlans = juanfi_srv_rows($c, '/interface/vlan', ['name', 'vlan-id', 'interface']);
        $addrs = juanfi_srv_rows($c, '/ip/address', ['address', 'interface']);
        $profs = juanfi_srv_rows($c, '/ip/hotspot/profile', ['name']);

        // ---- pre-flight checks ----
        if (!juanfi_srv_find($ifs, ['name' => $parent])) {
            r2($back, 'e', 'Parent interface "' . $parent . '" was not found on that router.');
        }
        if (!juanfi_srv_find($profs, ['name' => $profile])) {
            r2($back, 'e', 'Hotspot profile "' . $profile . '" was not found on that router.');
        }
        $same = juanfi_srv_find($vlans, ['name' => $name]);
        if ($same && ($same['vlan-id'] !== (string) $vlanId || $same['interface'] !== $parent)) {
            r2($back, 'e', 'An interface named ' . $name . ' already exists with a different VLAN ID or parent.');
        }
        foreach ($vlans as $v) {
            if ($v['name'] !== $name && $v['vlan-id'] === (string) $vlanId && $v['interface'] === $parent) {
                r2($back, 'e', 'VLAN ' . $vlanId . ' already exists on ' . $parent . ' as ' . $v['name'] . '.');
            }
        }
        foreach ($addrs as $a) {
            if ($a['interface'] === $name) {
                continue;
            }
            $o = juanfi_srv_net($a['address'], 1, 32);
            if ($o && $o['net'] <= $net['bc'] && $net['net'] <= $o['bc']) {
                r2($back, 'e', 'The network ' . $net['network'] . ' overlaps ' . $a['address'] . ' on interface ' . $a['interface'] . '.');
            }
        }

        $pools    = juanfi_srv_rows($c, '/ip/pool', ['name']);
        $dhcpNets = juanfi_srv_rows($c, '/ip/dhcp-server/network', ['address']);
        $dhcps    = juanfi_srv_rows($c, '/ip/dhcp-server', ['name']);
        $hotspots = juanfi_srv_rows($c, '/ip/hotspot', ['name', 'interface']);
        $alist    = juanfi_srv_rows($c, '/ip/firewall/address-list', ['list', 'address']);

        // ---- run the steps; stop at the first failure (a rerun skips what already exists) ----
        $failed = false;
        $run = function ($label, $path, array $args) use ($c, &$notes, &$failed) {
            if ($failed) {
                return false;
            }
            $err = juanfi_srv_send($c, $path, $args);
            if ($err !== '') {
                $notes[] = $label . ' FAILED: ' . $err;
                $failed = true;
                return false;
            }
            $notes[] = $label;
            return true;
        };

        if ($same) {
            $notes[] = 'VLAN exists';
        } else {
            $run('VLAN ' . $name . ' created', '/interface/vlan/add', [
                'name' => $name, 'vlan-id' => $vlanId, 'interface' => $parent, 'comment' => JUANFI_SRV_TAG,
            ]);
        }

        if (juanfi_srv_find($addrs, ['address' => $cidr, 'interface' => $name])) {
            $notes[] = 'IP exists';
        } else {
            $run('IP ' . $cidr . ' added', '/ip/address/add', [
                'address' => $cidr, 'interface' => $name, 'comment' => JUANFI_SRV_TAG,
            ]);
        }

        if (juanfi_srv_find($alist, ['list' => $list, 'address' => $net['network']])) {
            $notes[] = 'address list entry exists';
        } else {
            $run('address list ' . $list . ' + ' . $net['network'], '/ip/firewall/address-list/add', [
                'list' => $list, 'address' => $net['network'], 'comment' => JUANFI_SRV_TAG,
            ]);
        }

        if (juanfi_srv_find($pools, ['name' => $poolName])) {
            $notes[] = 'pool exists';
        } else {
            $run('pool ' . long2ip($start) . '-' . long2ip($end), '/ip/pool/add', [
                'name' => $poolName, 'ranges' => long2ip($start) . '-' . long2ip($end), 'comment' => JUANFI_SRV_TAG,
            ]);
        }

        if (juanfi_srv_find($dhcpNets, ['address' => $net['network']])) {
            $notes[] = 'DHCP network exists';
        } else {
            $run('DHCP network ' . $net['network'], '/ip/dhcp-server/network/add', [
                'address' => $net['network'], 'gateway' => $net['ip'],
                'dns-server' => implode(',', $dnsList), 'comment' => JUANFI_SRV_TAG,
            ]);
        }

        if (juanfi_srv_find($dhcps, ['name' => $dhcpName])) {
            $notes[] = 'DHCP server exists';
        } else {
            $run('DHCP server ' . $dhcpName, '/ip/dhcp-server/add', [
                'name' => $dhcpName, 'interface' => $name, 'address-pool' => $poolName,
                'lease-time' => $lease, 'disabled' => 'no', 'comment' => JUANFI_SRV_TAG,
            ]);
        }

        // Hotspot profile: a copy of the chosen one (keeps its html-directory, login options...), own hotspot-address.
        $useProfile = $profName;
        if (!$failed) {
            if (juanfi_srv_find($profs, ['name' => $profName])) {
                $notes[] = 'hotspot profile exists';
            } else {
                $err = juanfi_srv_send($c, '/ip/hotspot/profile/add', [
                    'copy-from' => $profile, 'name' => $profName, 'hotspot-address' => $net['ip'],
                ]);
                if ($err === '') {
                    $notes[] = 'hotspot profile ' . $profName . ' copied from ' . $profile;
                } else {
                    $useProfile = $profile;
                    $notes[] = 'could not copy the profile (' . $err . '), so the hotspot uses "' . $profile
                        . '" directly: set its hotspot-address to ' . $net['ip'] . ' by hand if needed';
                }
            }
        }

        if (juanfi_srv_find($hotspots, ['name' => $hsName]) || juanfi_srv_find($hotspots, ['interface' => $name])) {
            $notes[] = 'hotspot server exists';
        } else {
            $run('hotspot server ' . $hsName, '/ip/hotspot/add', [
                'name' => $hsName, 'interface' => $name, 'address-pool' => $poolName,
                'profile' => $useProfile, 'disabled' => 'no',
            ]);
        }
    } catch (Throwable $e) {
        error_log('juanfi: server add failed: ' . $e->getMessage());
        r2($back, 'e', 'Router error: ' . $e->getMessage());
    }

    if ($failed) {
        r2($back, 'e', 'Stopped on an error. Done so far: ' . implode('; ', $notes)
            . '. Fix the problem and press Add again, finished steps are skipped.');
    }
    r2($back, 's', $name . ' (VLAN ' . $vlanId . ') is ready: ' . implode('; ', $notes)
        . '. Use "' . $name . '" as the vendo\'s interface name.');
}

/**
 * Route: plugin/juanfi_server_delete (POST: router, name, csrf_token)
 * Removes what juanfi_server_add created for one VLAN. Only VLANs commented BILLSYFI.
 */
function juanfi_server_delete()
{
    $back = '?_route=plugin/juanfi_server';
    juanfi_server_guard($back);

    $routerName = trim((string) ($_POST['router'] ?? ''));
    $name       = trim((string) ($_POST['name'] ?? ''));

    if ($routerName === '' || !preg_match('/^[A-Za-z0-9_\-\.]{1,15}$/', $name)) {
        r2($back, 'e', 'Invalid request.');
    }

    $notes = [];
    $failed = false;

    try {
        $c = juanfi_srv_client($routerName);
        if ($c === null) {
            r2($back, 'e', 'Router not found.');
        }

        $vlan = juanfi_srv_find(juanfi_srv_rows($c, '/interface/vlan', ['name', 'comment']), ['name' => $name]);
        if (!$vlan) {
            r2($back, 'e', 'VLAN ' . $name . ' was not found.');
        }
        if ($vlan['comment'] !== JUANFI_SRV_TAG) {
            r2($back, 'e', $name . ' was not created by this page (no ' . JUANFI_SRV_TAG . ' comment), so it is not deleted here.');
        }

        $rm = function ($label, $path, $id) use ($c, &$notes, &$failed) {
            $err = juanfi_srv_send($c, $path . '/remove', ['numbers' => $id]);
            if ($err !== '') {
                $notes[] = $label . ' FAILED: ' . $err;
                $failed = true;
            } else {
                $notes[] = $label . ' removed';
            }
        };

        $addrs = array_filter(
            juanfi_srv_rows($c, '/ip/address', ['address', 'interface']),
            function ($a) use ($name) { return $a['interface'] === $name; }
        );
        $networks = [];
        foreach ($addrs as $a) {
            $n = juanfi_srv_net($a['address'], 1, 32);
            if ($n) {
                $networks[] = $n['network'];
            }
        }

        foreach (juanfi_srv_rows($c, '/ip/hotspot', ['name', 'interface']) as $h) {
            if ($h['interface'] === $name) {
                $rm('hotspot ' . $h['name'], '/ip/hotspot', $h['.id']);
            }
        }
        foreach (juanfi_srv_rows($c, '/ip/hotspot/profile', ['name']) as $p) {
            if ($p['name'] === 'hsprof-' . $name) {
                $rm('hotspot profile', '/ip/hotspot/profile', $p['.id']);
            }
        }
        foreach (juanfi_srv_rows($c, '/ip/dhcp-server', ['name', 'interface']) as $d) {
            if ($d['interface'] === $name) {
                $rm('DHCP server ' . $d['name'], '/ip/dhcp-server', $d['.id']);
            }
        }
        foreach (juanfi_srv_rows($c, '/ip/dhcp-server/network', ['address']) as $dn) {
            if (in_array($dn['address'], $networks, true)) {
                $rm('DHCP network ' . $dn['address'], '/ip/dhcp-server/network', $dn['.id']);
            }
        }
        foreach (juanfi_srv_rows($c, '/ip/pool', ['name']) as $p) {
            if ($p['name'] === 'pool-' . $name) {
                $rm('pool', '/ip/pool', $p['.id']);
            }
        }
        foreach (juanfi_srv_rows($c, '/ip/firewall/address-list', ['list', 'address', 'comment']) as $l) {
            if (in_array($l['address'], $networks, true) && $l['comment'] === JUANFI_SRV_TAG) {
                $rm('address list ' . $l['list'], '/ip/firewall/address-list', $l['.id']);
            }
        }
        foreach ($addrs as $a) {
            $rm('IP ' . $a['address'], '/ip/address', $a['.id']);
        }

        if ($failed) {
            $notes[] = 'VLAN kept because a step failed';
        } else {
            $rm('VLAN ' . $name, '/interface/vlan', $vlan['.id']);
        }
    } catch (Throwable $e) {
        error_log('juanfi: server delete failed: ' . $e->getMessage());
        r2($back, 'e', 'Router error: ' . $e->getMessage());
    }

    r2($back, $failed ? 'e' : 's', implode('; ', $notes) . '.');
}