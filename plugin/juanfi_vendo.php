<?php
/**
 * BILLSYS plugin: BILLSYFI > Vendo List (JuanFi)
 *
 * Install:
 *   system/plugin/juanfi_vendo.php
 *   system/plugin/ui/juanfi_vendo.tpl
 *
 * Menu: BILLSYFI > Vendo List
 *
 * Vendo List page: vendos from the portal's config.js (multiVendoAddresses), pinged from the router.
 *
 * Actions (SuperAdmin / Admin, POST + CSRF), both save into config.js on the router's hotspot files:
 *   plugin/juanfi_vendo_edit      change vendoName, vendoIp and interfaceName of one vendo
 *   plugin/juanfi_vendo_add       add a new vendo (name, IP, interface) to the list
 *   plugin/juanfi_vendo_delete    remove one vendo from the list
 *   plugin/juanfi_vendo_rates_get / _rates_save   read / save a vendo's wifi rates through its admin API
 *   plugin/juanfi_vendo_settings  change VendoOption (single / traditional / auto select) and showPauseTime
 *
 * This file is independent of juanfi_user.php (no shared functions).
 */

// "Parent/Child" names make PHPNuxBill group the entries under one BILLSYFI submenu.
register_menu("BILLSYFI/Vendo List", true, "juanfi_vendo", "AFTER_CUSTOMERS", "fa fa-hdd-o");

/** VendoOption values used by the portal's config.js. */
function juanfi_vendo_modes()
{
    return [
        0 => 'Single vendo',
        1 => 'Traditional (client chooses)',
        2 => 'Auto select (by interface name)',
    ];
}

/** Remove block comments and // line comments from the JavaScript text. */
function juanfi_strip_js_comments($js)
{
    $js = preg_replace('~/\*.*?\*/~s', '', (string) $js);
    return preg_replace('~(^|\s)//.*$~m', '$1', $js);
}

/**
 * Parse the portal's config.js: VendoOption and the multiVendoAddresses list.
 * Only those two things are read; the Telegram token etc. are ignored on purpose.
 *
 * @return array [option (int|null), vendos [['name' =>, 'ip' =>, 'interface' =>], ...]]
 */
function juanfi_parse_vendo_config($js)
{
    $js = juanfi_strip_js_comments($js);

    $option = preg_match('~VendoOption\s*=\s*(\d+)~', $js, $m) ? (int) $m[1] : null;

    $vendos = [];
    if (preg_match('~multiVendoAddresses\s*=\s*\[(.*?)\]\s*;~s', $js, $list)
        && preg_match_all('~\{(.*?)\}~s', $list[1], $objs)) {
        foreach ($objs[1] as $body) {
            $item = [];
            if (preg_match_all('~([A-Za-z]+)\s*:\s*(["\'])(.*?)\2~', $body, $props, PREG_SET_ORDER)) {
                foreach ($props as $p) {
                    $item[$p[1]] = trim($p[3]);
                }
            }
            if (!empty($item['vendoIp'])) {
                $vendos[] = [
                    'name'      => $item['vendoName'] ?? '',
                    'ip'        => $item['vendoIp'],
                    'interface' => $item['interfaceName'] ?? '',
                ];
            }
        }
    }

    return [$option, $vendos];
}

/**
 * Read showPauseTime from config.js.
 *
 * @return bool|null true / false, or null when the file has no showPauseTime
 */
function juanfi_parse_pause($js)
{
    $js = juanfi_strip_js_comments($js);
    if (preg_match('~showPauseTime\s*=\s*(true|false)~i', $js, $m)) {
        return strtolower($m[1]) === 'true';
    }
    return null;
}
/**
 * Read a plain text setting (Footer, bannerText) from config.js.
 * Returns null when the line is missing, or when the text has escapes / new lines
 * that cannot be edited safely.
 */
function juanfi_parse_js_string($js, $var)
{
    $js = juanfi_strip_js_comments($js);
    if (preg_match('~\b' . preg_quote($var, '~') . '\s*=\s*(["\'])((?:(?!\1)[^\\\\\r\n])*)\1~', $js, $m)) {
        return $m[2];
    }
    return null;
}

/** Footer / banner text: only characters that cannot break the JavaScript file or add HTML tags. */
function juanfi_valid_portal_text($text, $max)
{
    return (bool) preg_match('/^[\p{L}\p{N} _\-\.,!?:;#()&|\/+@%*•]{1,' . (int) $max . '}$/u', $text);
}
/**
 * Ping an address from the router (the vendo sits on the router's LAN, not on the web server's).
 *
 * @return array ['online' => bool, 'latency' => string]
 */
function juanfi_ping($client, $ip)
{
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        return ['online' => false, 'latency' => ''];
    }
    try {
        $res = $client->sendSync(
            (new PEAR2\Net\RouterOS\Request('/ping'))
                ->setArgument('address', $ip)
                ->setArgument('count', '1')
        );
        foreach ($res as $reply) {
            $time = (string) $reply->getProperty('time');
            if ($time !== '') {
                return ['online' => true, 'latency' => $time];
            }
        }
    } catch (Throwable $e) {
        error_log('juanfi: ping ' . $ip . ' failed: ' . $e->getMessage());
    }
    return ['online' => false, 'latency' => ''];
}


/**
 * Get the text of the portal's config.js from a router.
 * 1) the file in the hotspot folder (prefers .../assets/js/config.js), then
 * 2) a download by the router itself from its hotspot web server (http://<hotspot-address>/assets/js/config.js).
 *
 * @param string $source set to where it was read from, or to a hint when it could not be read
 * @return string the file text, or '' when not readable
 */
function juanfi_read_config_js($client, &$source)
{
    $source = '';
    $seen = [];
    $best = null;

    try {
        $req = new PEAR2\Net\RouterOS\Request('/file/print');
        $req->setArgument('.proplist', 'name,contents');
        foreach ($client->sendSync($req) as $f) {
            $name = (string) $f->getProperty('name');
            if (!preg_match('~(^|/)config\.js$~', $name)) {
                continue;
            }
            $seen[] = $name;
            $js = (string) $f->getProperty('contents');
            if (stripos($js, 'multiVendoAddresses') === false) {
                continue;
            }
            $rank = preg_match('~(^|/)assets/js/config\.js$~', $name) ? 2 : 1;
            if ($best === null || $rank > $best['rank']) {
                $best = ['rank' => $rank, 'name' => $name, 'js' => $js];
            }
        }
    } catch (Throwable $e) {
        error_log('juanfi: file list failed: ' . $e->getMessage());
    }

    if ($best !== null) {
        $source = $best['name'];
        return $best['js'];
    }

    // Fallback: let the router download it from its own hotspot web server.
    try {
        $hosts = [];
        foreach ($client->sendSync(new PEAR2\Net\RouterOS\Request('/ip/hotspot/profile/print')) as $pr) {
            $a = (string) $pr->getProperty('hotspot-address');
            if ($a !== '' && $a !== '0.0.0.0' && filter_var($a, FILTER_VALIDATE_IP) && !in_array($a, $hosts, true)) {
                $hosts[] = $a;
            }
        }
        foreach ($hosts as $h) {
            $url = 'http://' . $h . '/assets/js/config.js';
            try {
                $res = $client->sendSync(
                    (new PEAR2\Net\RouterOS\Request('/tool/fetch'))
                        ->setArgument('url', $url)
                        ->setArgument('mode', 'http')
                        ->setArgument('output', 'user')
                );
                foreach ($res as $x) {
                    $data = (string) $x->getProperty('data');
                    if (stripos($data, 'multiVendoAddresses') !== false) {
                        $source = $url;
                        return $data;
                    }
                }
            } catch (Throwable $e) {
                error_log('juanfi: fetch ' . $url . ' failed: ' . $e->getMessage());
            }
        }
    } catch (Throwable $e) {
        error_log('juanfi: hotspot profile lookup failed: ' . $e->getMessage());
    }

    $source = $seen ? 'found ' . implode(', ', $seen) . ' but could not read its contents' : '';
    return '';
}

/**
 * Read config.js from each enabled router's hotspot files and list its vendos with a live ping.
 *
 * @return array [rows, errors, vendoOption, sources, settings]
 *               settings = routerName => ['mode' => int|null, 'pause' => '1'|'0'|'']
 */
function juanfi_read_vendos($showDetail = false)
{
    $rows = [];
    $errors = [];
    $sources = [];
    $settings = [];
    $option = null;

    foreach (ORM::for_table('tbl_routers')->order_by_asc('name')->find_many() as $r) {
        if (isset($r['enabled']) && (string) $r['enabled'] === '0') {
            continue;
        }

        try {
            $client = Mikrotik::getClient($r['ip_address'], $r['username'], $r['password']);

            $source = '';
            $js = juanfi_read_config_js($client, $source);

            if ($js === '') {
                $errors[] = 'Router "' . $r['name'] . '": could not read config.js'
                    . ($source !== '' ? ' (' . $source . ')' : ' (not in the hotspot files, and not served at /assets/js/config.js)') . '.';
                continue;
            }

            list($opt, $vendos) = juanfi_parse_vendo_config($js);
            $option = $option ?? $opt;
            $sources[] = $r['name'] . ': ' . $source;

            $pause  = juanfi_parse_pause($js);
            $footer = juanfi_parse_js_string($js, 'Footer');
            $banner = juanfi_parse_js_string($js, 'bannerText');
            $settings[(string) $r['name']] = [
                'mode'      => $opt,
                'pause'     => $pause === null ? '' : ($pause ? '1' : '0'),
                'footer'    => (string) $footer,
                'footer_ok' => $footer === null ? '0' : '1',
                'banner'    => (string) $banner,
                'banner_ok' => $banner === null ? '0' : '1',
            ];

            foreach ($vendos as $v) {
                $ping = juanfi_ping($client, $v['ip']);
                $rows[] = [
                    'name'      => $v['name'],
                    'ip'        => $v['ip'],
                    'interface' => $v['interface'],
                    'router'    => (string) $r['name'],
                    'online'    => $ping['online'],
                    'latency'   => $ping['latency'],
                ];
            }
        } catch (Throwable $e) {
            error_log('juanfi: vendo list, router "' . $r['name'] . '" failed: ' . $e->getMessage());
            $errors[] = $showDetail
                ? 'Router "' . $r['name'] . '" (' . $r['ip_address'] . '): ' . get_class($e) . ': ' . $e->getMessage()
                : 'Could not read router "' . $r['name'] . '". Check that it is online and the API login works.';
        }
    }

    return [$rows, $errors, $option, $sources, $settings];
}

function juanfi_vendo()
{
    global $ui, $routes;

    _admin();

    $admin = Admin::_info();
    $ui->assign('_system_menu', 'juanfi_vendo');
    $ui->assign('_admin', $admin);
    $ui->assign('routes', $routes);
    $ui->assign('_title', 'BILLSYFI Vendo List');

    $showDetail = (is_array($admin) || is_object($admin))
        && in_array((string) ($admin['user_type'] ?? ''), ['SuperAdmin', 'Admin'], true);

    list($rows, $errors, $option, $sources, $settings) = juanfi_read_vendos($showDetail);

    $online = 0;
    foreach ($rows as $row) {
        if ($row['online']) {
            $online++;
        }
    }

    // Pause-time pill: taken from the first router that has the setting.
    $pauseText = '';
    foreach ($settings as $s) {
        if ($s['pause'] !== '') {
            $pauseText = $s['pause'] === '1' ? 'On' : 'Off';
            break;
        }
    }

    $modes = juanfi_vendo_modes();

    $ui->assign('juanfiVendos', $rows);
    $ui->assign('juanfiVendoErrors', $errors);
    $ui->assign('juanfiVendoSources', $sources);
    $ui->assign('juanfiVendoMode', $modes[$option] ?? '');
    $ui->assign('juanfiVendoPause', $pauseText);
    $ui->assign('juanfiVendoSettings', $settings);
    $ui->assign('juanfiVendoCounts', [
        'total'   => count($rows),
        'online'  => $online,
        'offline' => count($rows) - $online,
    ]);
    $ui->display('juanfi_vendo.tpl');
}

/* ------------------------------------------------------------------------------------------
 * Editing config.js on the router
 * ---------------------------------------------------------------------------------------- */

/**
 * Shared checks for the edit routes: POST only, SuperAdmin/Admin only, valid CSRF token.
 */
function juanfi_vendo_guard($back)
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
 * Find the portal's real config.js file in the router's file list (the one with multiVendoAddresses).
 * Only a real file can be edited, not the copy the router would download over HTTP.
 *
 * @return array|null ['id' =>, 'name' =>, 'contents' =>] or null when not found
 */
function juanfi_find_config_file($client)
{
    $best = null;
    $req = new PEAR2\Net\RouterOS\Request('/file/print');
    $req->setArgument('.proplist', '.id,name,contents');
    foreach ($client->sendSync($req) as $f) {
        $name = (string) $f->getProperty('name');
        if (!preg_match('~(^|/)config\.js$~', $name)) {
            continue;
        }
        $js = (string) $f->getProperty('contents');
        if (stripos($js, 'multiVendoAddresses') === false) {
            continue;
        }
        $rank = preg_match('~(^|/)assets/js/config\.js$~', $name) ? 2 : 1;
        if ($best === null || $rank > $best['rank']) {
            $best = ['rank' => $rank, 'id' => (string) $f->getProperty('.id'), 'name' => $name, 'contents' => $js];
        }
    }
    return $best;
}

/**
 * Connect to the posted router and load its editable config.js.
 * Stops with an error message (r2) when it cannot be edited safely. May throw; callers wrap it in try/catch.
 *
 * @return array [client, file]
 */
function juanfi_vendo_open_config($routerName, $back)
{
    $router = ORM::for_table('tbl_routers')->where('name', $routerName)->find_one();
    if (!$router) {
        r2($back, 'e', 'Router not found.');
    }

    $client = Mikrotik::getClient($router['ip_address'], $router['username'], $router['password']);

    $file = juanfi_find_config_file($client);
    if ($file === null || $file['id'] === '') {
        r2($back, 'e', 'config.js was not found in the router files (it can only be edited as a real file).');
    }
    // RouterOS only returns/accepts about 4 KB of file contents; never write a cut-off file.
    if (strlen($file['contents']) >= 4000) {
        r2($back, 'e', 'config.js is too large to edit safely from here. Edit it on the router instead.');
    }

    return [$client, $file];
}

/**
 * Save new text into the router's config.js, after a best-effort backup next to it (config.js.bak).
 */
function juanfi_write_config($client, array $file, $new)
{
    try {
        $bakName = $file['name'] . '.bak';
        $old = $client->sendSync(
            (new PEAR2\Net\RouterOS\Request('/file/print'))
                ->setQuery(PEAR2\Net\RouterOS\Query::where('name', $bakName))
        );
        $oldId = (string) $old->getProperty('.id');
        if ($oldId !== '') {
            $client->sendSync((new PEAR2\Net\RouterOS\Request('/file/remove'))->setArgument('numbers', $oldId));
        }
        $client->sendSync(
            (new PEAR2\Net\RouterOS\Request('/file/add'))
                ->setArgument('name', $bakName)
                ->setArgument('contents', $file['contents'])
        );
    } catch (Throwable $e) {
        error_log('juanfi: config.js backup failed: ' . $e->getMessage());
    }

    $client->sendSync(
        (new PEAR2\Net\RouterOS\Request('/file/set'))
            ->setArgument('numbers', $file['id'])
            ->setArgument('contents', $new)
    );
}

/**
 * Locate the multiVendoAddresses list text inside config.js.
 *
 * @return array|null [list text, offset of the list text inside $js]
 */
function juanfi_config_list($js)
{
    if (!preg_match('~(multiVendoAddresses\s*=\s*\[)(.*?)(\]\s*;)~s', $js, $m, PREG_OFFSET_CAPTURE)) {
        return null;
    }
    return [$m[2][0], $m[2][1]];
}

/**
 * Find the entries of the list whose vendoIp is $ip.
 *
 * @return array list of ['start' => offset in $list, 'len' => length, 'body' => text between the braces]
 */
function juanfi_find_entries($list, $ip)
{
    $hits = [];
    if (preg_match_all('~\{(.*?)\}~s', $list, $all, PREG_OFFSET_CAPTURE)) {
        foreach ($all[0] as $i => $whole) {
            if (preg_match('~vendoIp\s*:\s*(["\'])' . preg_quote($ip, '~') . '\1~', $all[1][$i][0])) {
                $hits[] = ['start' => $whole[1], 'len' => strlen($whole[0]), 'body' => $all[1][$i][0]];
            }
        }
    }
    return $hits;
}

/**
 * Set a string property inside one entry's text. If the property is missing it is added after vendoIp
 * (an empty value for a missing property is simply skipped).
 */
function juanfi_set_js_prop($body, $key, $value)
{
    $count = 0;
    $out = preg_replace_callback(
        '~(\b' . preg_quote($key, '~') . '\s*:\s*)(["\'])(.*?)\2~',
        static function ($n) use ($value) {
            return $n[1] . $n[2] . $value . $n[2];
        },
        $body,
        1,
        $count
    );
    if ($count === 1) {
        return $out;
    }
    if ($value === '') {
        return $body;
    }
    return preg_replace_callback(
        '~(vendoIp\s*:\s*(["\'])[^"\']*\2)~',
        static function ($n) use ($key, $value) {
            return $n[1] . ', ' . $key . ': "' . $value . '"';
        },
        $body,
        1
    );
}

/**
 * Change vendoName, vendoIp and interfaceName of the one entry whose vendoIp is $oldIp.
 * Everything else in the file (Telegram token, comments, other settings) is left exactly as it is.
 *
 * @param string $problem set to a message when null is returned
 * @return string|null the new file text, or null when it could not be changed safely
 */
function juanfi_edit_in_config($js, $oldIp, $name, $ip, $iface, &$problem)
{
    $problem = '';
    $found = juanfi_config_list($js);
    if ($found === null) {
        $problem = 'multiVendoAddresses was not found in config.js.';
        return null;
    }
    list($list, $offset) = $found;

    $hits = juanfi_find_entries($list, $oldIp);
    if (count($hits) === 0) {
        $problem = 'Vendo ' . $oldIp . ' was not found in config.js.';
        return null;
    }
    if (count($hits) > 1) {
        $problem = 'More than one vendo uses ' . $oldIp . ' in config.js. Edit the file by hand.';
        return null;
    }
    if ($ip !== $oldIp && count(juanfi_find_entries($list, $ip)) > 0) {
        $problem = 'Another vendo already uses ' . $ip . '.';
        return null;
    }

    $body = $hits[0]['body'];
    $body = juanfi_set_js_prop($body, 'vendoName', $name);
    $body = juanfi_set_js_prop($body, 'interfaceName', $iface);
    if ($ip !== $oldIp) {
        $body = preg_replace_callback(
            '~(vendoIp\s*:\s*)(["\'])' . preg_quote($oldIp, '~') . '\2~',
            static function ($n) use ($ip) {
                return $n[1] . $n[2] . $ip . $n[2];
            },
            $body,
            1
        );
    }
    if ($body === null) {
        $problem = 'Could not change the entry safely.';
        return null;
    }

    $newList = substr($list, 0, $hits[0]['start']) . '{' . $body . '}'
        . substr($list, $hits[0]['start'] + $hits[0]['len']);
    return substr($js, 0, $offset) . $newList . substr($js, $offset + strlen($list));
}

/**
 * Remove the one entry whose vendoIp is $ip from multiVendoAddresses (with its comma).
 *
 * @param string $problem set to a message when null is returned
 * @return string|null the new file text, or null when it could not be changed safely
 */
function juanfi_delete_in_config($js, $ip, &$problem)
{
    $problem = '';
    $found = juanfi_config_list($js);
    if ($found === null) {
        $problem = 'multiVendoAddresses was not found in config.js.';
        return null;
    }
    list($list, $offset) = $found;

    $hits = juanfi_find_entries($list, $ip);
    if (count($hits) === 0) {
        $problem = 'Vendo ' . $ip . ' was not found in config.js.';
        return null;
    }
    if (count($hits) > 1) {
        $problem = 'More than one vendo uses ' . $ip . ' in config.js. Edit the file by hand.';
        return null;
    }

    $before = substr($list, 0, $hits[0]['start']);
    $after = substr($list, $hits[0]['start'] + $hits[0]['len']);
    if (preg_match('~^\s*,~', $after, $mm)) {
        $after = substr($after, strlen($mm[0]));      // its own trailing comma
    } else {
        $before = preg_replace('~,\s*$~', '', $before); // it was the last one: drop the comma before it
    }
    $before = rtrim($before, " \t");

    $newList = $before . $after;
    return substr($js, 0, $offset) . $newList . substr($js, $offset + strlen($list));
}

/**
 * Set VendoOption (and showPauseTime when $pause is not null) in the config.js text.
 * Only those values change; the rest of the file stays exactly as it is.
 *
 * @param int       $mode  0, 1 or 2
 * @param bool|null $pause new showPauseTime, or null to leave it alone
 * @return string|null the new file text, or null when it could not be changed safely
 */
function juanfi_set_config_options($js, $mode, $pause, $footer = null, $banner = null)
{
    $count = 0;
    $new = preg_replace('~(\bVendoOption\s*=\s*)\d+~', '${1}' . (int) $mode, $js, 1, $count);
    if ($new === null || $count !== 1) {
        return null;
    }

    if ($pause !== null) {
        $count = 0;
        $new = preg_replace('~(\bshowPauseTime\s*=\s*)(true|false)~i', '${1}' . ($pause ? 'true' : 'false'), $new, 1, $count);
        if ($new === null || $count !== 1) {
            return null;
        }
    }

    // Footer and bannerText: only the text between the quotes changes.
    foreach (['Footer' => $footer, 'bannerText' => $banner] as $var => $text) {
        if ($text === null) {
            continue;
        }
        $count = 0;
        $new = preg_replace_callback(
            '~(\b' . $var . '\s*=\s*)(["\'])((?:(?!\2)[^\\\\\r\n])*)\2~',
            static function ($n) use ($text) {
                return $n[1] . $n[2] . $text . $n[2];
            },
            $new,
            1,
            $count
        );
        if ($new === null || $count !== 1) {
            return null;
        }
    }

    // The values the portal will really read (comments ignored) must be the ones that were asked for.
    list($checkMode) = juanfi_parse_vendo_config($new);
    if ($checkMode !== (int) $mode) {
        return null;
    }
    if ($pause !== null && juanfi_parse_pause($new) !== $pause) {
        return null;
    }
    if ($footer !== null && juanfi_parse_js_string($new, 'Footer') !== $footer) {
        return null;
    }
    if ($banner !== null && juanfi_parse_js_string($new, 'bannerText') !== $banner) {
        return null;
    }

    return $new;
}

/**
 * Route: plugin/juanfi_vendo_edit (POST: router, old_ip, name, ip, interface, csrf_token)
 * Changes vendoName, vendoIp and interfaceName of one vendo in the router's config.js (SuperAdmin / Admin only).
 */
function juanfi_vendo_edit()
{
    $back = '?_route=plugin/juanfi_vendo';
    juanfi_vendo_guard($back);

    $routerName = trim((string) ($_POST['router'] ?? ''));
    $oldIp      = trim((string) ($_POST['old_ip'] ?? ''));
    $name       = trim((string) ($_POST['name'] ?? ''));
    $ip         = trim((string) ($_POST['ip'] ?? ''));
    $iface      = trim((string) ($_POST['interface'] ?? ''));

    if ($routerName === '' || !filter_var($oldIp, FILTER_VALIDATE_IP)) {
        r2($back, 'e', 'Invalid request.');
    }
    // Only characters that cannot break the JavaScript file.
    if (!preg_match('/^[\p{L}\p{N} _\-\.#()]{1,40}$/u', $name)) {
        r2($back, 'e', 'Vendo name must be 1-40 characters: letters, numbers, spaces and - _ . # ( ) only.');
    }
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        r2($back, 'e', 'Vendo IP is not a valid IP address.');
    }
    if ($iface !== '' && !preg_match('/^[A-Za-z0-9 _\-\.:@\/]{1,40}$/', $iface)) {
        r2($back, 'e', 'Interface name must be up to 40 characters: letters, numbers and - _ . : @ / only.');
    }

    try {
        list($client, $file) = juanfi_vendo_open_config($routerName, $back);

        $problem = '';
        $new = juanfi_edit_in_config($file['contents'], $oldIp, $name, $ip, $iface, $problem);
        if ($new === null) {
            r2($back, 'e', $problem);
        }
        if ($new === $file['contents']) {
            r2($back, 'e', 'Nothing changed.');
        }

        // The portal must read exactly the values that were asked for, and no vendo may get lost.
        list($o1, $before) = juanfi_parse_vendo_config($file['contents']);
        list($o2, $after) = juanfi_parse_vendo_config($new);
        $okNew = false;
        foreach ($after as $v) {
            if ($v['ip'] === $ip && $v['name'] === $name && $v['interface'] === $iface) {
                $okNew = true;
            }
        }
        if (!$okNew || count($after) !== count($before)) {
            r2($back, 'e', 'Could not change that entry safely. Edit config.js by hand.');
        }

        juanfi_write_config($client, $file, $new);

        $check = juanfi_find_config_file($client);
        $ok = false;
        if ($check !== null) {
            list($o3, $vendos) = juanfi_parse_vendo_config($check['contents']);
            foreach ($vendos as $v) {
                if ($v['ip'] === $ip && $v['name'] === $name && $v['interface'] === $iface) {
                    $ok = true;
                }
            }
        }
        if (!$ok) {
            r2($back, 'e', 'The file was written but the change could not be confirmed. Check config.js on the router (backup: config.js.bak).');
        }
    } catch (Throwable $e) {
        error_log('juanfi: vendo edit failed: ' . $e->getMessage());
        r2($back, 'e', 'Router error: ' . $e->getMessage());
    }

    r2($back, 's', 'Vendo saved in config.js: "' . $name . '", ' . $ip
        . ($iface !== '' ? ', ' . $iface : '') . '. Portal pages may need a refresh to show it.');
}

/**
 * Route: plugin/juanfi_vendo_delete (POST: router, ip, csrf_token)
 * Removes one vendo from multiVendoAddresses in the router's config.js (SuperAdmin / Admin only).
 * The last remaining vendo cannot be deleted.
 */
function juanfi_vendo_delete()
{
    $back = '?_route=plugin/juanfi_vendo';
    juanfi_vendo_guard($back);

    $routerName = trim((string) ($_POST['router'] ?? ''));
    $ip         = trim((string) ($_POST['ip'] ?? ''));

    if ($routerName === '' || !filter_var($ip, FILTER_VALIDATE_IP)) {
        r2($back, 'e', 'Invalid request.');
    }

    try {
        list($client, $file) = juanfi_vendo_open_config($routerName, $back);

        list($o1, $before) = juanfi_parse_vendo_config($file['contents']);
        if (count($before) <= 1) {
            r2($back, 'e', 'The last vendo cannot be deleted: config.js needs at least one.');
        }

        $problem = '';
        $new = juanfi_delete_in_config($file['contents'], $ip, $problem);
        if ($new === null) {
            r2($back, 'e', $problem);
        }

        // Exactly that one vendo must be gone, all the others must stay.
        list($o2, $after) = juanfi_parse_vendo_config($new);
        $stillThere = false;
        foreach ($after as $v) {
            if ($v['ip'] === $ip) {
                $stillThere = true;
            }
        }
        if ($stillThere || count($after) !== count($before) - 1) {
            r2($back, 'e', 'Could not remove that entry safely. Edit config.js by hand.');
        }

        juanfi_write_config($client, $file, $new);

        $check = juanfi_find_config_file($client);
        $ok = false;
        if ($check !== null) {
            list($o3, $vendos) = juanfi_parse_vendo_config($check['contents']);
            $ok = count($vendos) === count($before) - 1;
            foreach ($vendos as $v) {
                if ($v['ip'] === $ip) {
                    $ok = false;
                }
            }
        }
        if (!$ok) {
            r2($back, 'e', 'The file was written but the deletion could not be confirmed. Check config.js on the router (backup: config.js.bak).');
        }
    } catch (Throwable $e) {
        error_log('juanfi: vendo delete failed: ' . $e->getMessage());
        r2($back, 'e', 'Router error: ' . $e->getMessage());
    }

    r2($back, 's', 'Vendo ' . $ip . ' deleted from config.js (backup: config.js.bak).');
}

/**
 * Route: plugin/juanfi_vendo_settings (POST: router, mode 0|1|2, pause 1|0|'' , csrf_token)
 * Changes VendoOption and showPauseTime in the router's config.js (SuperAdmin / Admin only).
 * pause '' = this config.js has no showPauseTime, so it is left alone.
 */
function juanfi_vendo_settings()
{
    $back = '?_route=plugin/juanfi_vendo';
    juanfi_vendo_guard($back);

    $routerName = trim((string) ($_POST['router'] ?? ''));
    $modes      = juanfi_vendo_modes();
    $mode       = (string) ($_POST['mode'] ?? '');
    $pauseIn    = (string) ($_POST['pause'] ?? '');

    if ($routerName === '' || !ctype_digit($mode) || !isset($modes[(int) $mode])) {
        r2($back, 'e', 'Invalid request.');
    }
    $mode  = (int) $mode;
    $pause = $pauseIn === '1' ? true : ($pauseIn === '0' ? false : null);

    // Portal texts. A field is not sent at all when that router's config.js has no such line.
    $footer = isset($_POST['footer']) ? trim((string) $_POST['footer']) : null;
    $banner = isset($_POST['banner']) ? trim((string) $_POST['banner']) : null;
    if ($footer !== null && !juanfi_valid_portal_text($footer, 60)) {
        r2($back, 'e', 'PisoWiFi name must be 1-60 characters: letters, numbers, spaces and - _ . , ! ? : ; # ( ) & | / + @ % * only.');
    }
    if ($banner !== null && !juanfi_valid_portal_text($banner, 120)) {
        r2($back, 'e', 'Banner text must be 1-120 characters: letters, numbers, spaces and - _ . , ! ? : ; # ( ) & | / + @ % * only.');
    }

    try {
        list($client, $file) = juanfi_vendo_open_config($routerName, $back);

        // A setting that is not in this config.js cannot be changed.
        if (juanfi_parse_pause($file['contents']) === null) {
            $pause = null;
        }
        if (juanfi_parse_js_string($file['contents'], 'Footer') === null) {
            $footer = null;
        }
        if (juanfi_parse_js_string($file['contents'], 'bannerText') === null) {
            $banner = null;
        }

        $new = juanfi_set_config_options($file['contents'], $mode, $pause, $footer, $banner);
        if ($new === null) {
            r2($back, 'e', 'Could not change the settings safely (VendoOption, Footer or bannerText was not found in the expected form). Edit config.js by hand.');
        }
        if ($new === $file['contents']) {
            r2($back, 'e', 'Those settings are already set, nothing changed.');
        }
        // RouterOS only takes about 4 KB of file contents; never write a cut-off file.
        if (strlen($new) >= 4000) {
            r2($back, 'e', 'config.js would become too large to edit safely from here. Edit it on the router instead.');
        }

        juanfi_write_config($client, $file, $new);

        // Read it back and make sure the portal will see the new values.
        $check = juanfi_find_config_file($client);
        $ok = false;
        if ($check !== null) {
            list($checkMode) = juanfi_parse_vendo_config($check['contents']);
            $ok = $checkMode === $mode
                && ($pause === null || juanfi_parse_pause($check['contents']) === $pause)
                && ($footer === null || juanfi_parse_js_string($check['contents'], 'Footer') === $footer)
                && ($banner === null || juanfi_parse_js_string($check['contents'], 'bannerText') === $banner);
        }
        if (!$ok) {
            r2($back, 'e', 'The file was written but the new settings could not be confirmed. Check config.js on the router (backup: config.js.bak).');
        }
    } catch (Throwable $e) {
        error_log('juanfi: vendo settings failed: ' . $e->getMessage());
        r2($back, 'e', 'Router error: ' . $e->getMessage());
    }

    $parts = ['mode "' . $modes[$mode] . '"'];
    if ($pause !== null) {
        $parts[] = 'pause time ' . ($pause ? 'on' : 'off');
    }
    if ($footer !== null) {
        $parts[] = 'PisoWiFi name "' . $footer . '"';
    }
    if ($banner !== null) {
        $parts[] = 'banner text';
    }
    r2($back, 's', 'Saved to config.js: ' . implode(', ', $parts) . '. Portal pages may need a refresh to show it.');
}

/**
 * Add a new entry at the end of multiVendoAddresses.
 * Everything else in the file (Telegram token, comments, other settings) is left exactly as it is.
 *
 * @param string $problem set to a message when null is returned
 * @return string|null the new file text, or null when it could not be changed safely
 */
function juanfi_add_to_config($js, $name, $ip, $iface, &$problem)
{
    $problem = '';
    $found = juanfi_config_list($js);
    if ($found === null) {
        $problem = 'multiVendoAddresses was not found in config.js.';
        return null;
    }
    list($list, $offset) = $found;

    if (count(juanfi_find_entries($list, $ip)) > 0) {
        $problem = 'A vendo with IP ' . $ip . ' already exists.';
        return null;
    }

    $entry = "{\n\t\tvendoName: \"" . $name . "\",\n\t\tvendoIp: \"" . $ip . "\",\n\t\tinterfaceName: \"" . $iface . "\",\n\t}";

    // Put it after the last existing entry (keeping that entry's own trailing comma, if it has one).
    $lastEnd = null;
    if (preg_match_all('~\{(.*?)\}~s', $list, $all, PREG_OFFSET_CAPTURE)) {
        $last = end($all[0]);
        $lastEnd = $last[1] + strlen($last[0]);
    }

    if ($lastEnd === null) {
        $newList = "\n\t" . $entry . "\n" . $list;
    } else {
        $head = substr($list, 0, $lastEnd);
        $tail = substr($list, $lastEnd);
        if (preg_match('~^\s*,~', $tail, $mm)) {
            $head .= $mm[0];
            $tail = substr($tail, strlen($mm[0]));
            $newList = $head . "\n\t" . $entry . ',' . $tail;
        } else {
            $newList = $head . ",\n\t" . $entry . $tail;
        }
    }

    return substr($js, 0, $offset) . $newList . substr($js, $offset + strlen($list));
}


/**
 * Set up the router for a new vendo: static DHCP lease, hotspot bypass (by MAC) and address-list entry.
 * Best effort: config.js is already saved, so a problem here is reported but does not undo the add.
 * Each step checks first, so running it twice does not create duplicates.
 *
 */
/** Send one command; returns '' when it worked, or the router's error text. */
function juanfi_vendo_send($client, PEAR2\Net\RouterOS\Request $req)
{
    try {
        $res = $client->sendSync($req);
    } catch (Throwable $e) {
        return $e->getMessage();
    }
    return juanfi_vendo_answer_error($res);
}

function juanfi_vendo_provision($client, $ip, array &$notes)
{
    $comment = 'BILLSYFI';
    $list    = 'JuanfiVendo';
    $mac     = '';
    $leaseId = '';
    $leaseDynamic = false;

        // Make the router learn the vendo's MAC (creates the ARP entry).
    try {
        $client->sendSync(
            (new PEAR2\Net\RouterOS\Request('/ping'))
                ->setArgument('address', $ip)
                ->setArgument('count', '3')
        );
    } catch (Throwable $e) {
        error_log('juanfi: pre-ping failed: ' . $e->getMessage());
    }
    // 1) MAC: ARP first, then the DHCP lease.
    try {
        foreach ($client->sendSync(
            (new PEAR2\Net\RouterOS\Request('/ip/arp/print'))
                ->setQuery(PEAR2\Net\RouterOS\Query::where('address', $ip))
        ) as $a) {
            $m = strtoupper((string) $a->getProperty('mac-address'));
            if ($m !== '') { $mac = $m; break; }
        }
    } catch (Throwable $e) {
        error_log('juanfi: arp lookup failed: ' . $e->getMessage());
    }

    try {
        foreach ($client->sendSync(
            (new PEAR2\Net\RouterOS\Request('/ip/dhcp-server/lease/print'))
                ->setQuery(PEAR2\Net\RouterOS\Query::where('address', $ip))
        ) as $l) {
            if ((string) $l->getProperty('.id') === '') { continue; }
            $leaseId = (string) $l->getProperty('.id');
            $leaseDynamic = ((string) $l->getProperty('dynamic')) === 'true';
            if ($mac === '') { $mac = strtoupper((string) $l->getProperty('mac-address')); }
            break;
        }
    } catch (Throwable $e) {
        error_log('juanfi: lease lookup failed: ' . $e->getMessage());
    }

    if (!preg_match('/^([0-9A-F]{2}:){5}[0-9A-F]{2}$/', $mac)) {
        $notes[] = 'MAC of ' . $ip . ' not found (is the vendo on and on this router\'s LAN?), so static lease and bypass were skipped';
        $mac = '';
    }

    if ($mac !== '') {
        $notes[] = 'MAC ' . $mac;
        // 2) Static DHCP lease
        if ($leaseId !== '' && $leaseDynamic) {
            $err = juanfi_vendo_send($client, (new PEAR2\Net\RouterOS\Request('/ip/dhcp-server/lease/make-static'))
                ->setArgument('numbers', $leaseId));
            if ($err === '') {
                juanfi_vendo_send($client, (new PEAR2\Net\RouterOS\Request('/ip/dhcp-server/lease/set'))
                    ->setArgument('numbers', $leaseId)->setArgument('comment', $comment));
            }
            $notes[] = $err === '' ? 'lease made static' : 'static lease FAILED: ' . $err;
        } elseif ($leaseId !== '') {
            $notes[] = 'static lease already exists';
        } else {
            $err = juanfi_vendo_send($client, (new PEAR2\Net\RouterOS\Request('/ip/dhcp-server/lease/add'))
                ->setArgument('address', $ip)
                ->setArgument('mac-address', $mac)
                ->setArgument('comment', $comment));
            $notes[] = $err === '' ? 'static lease added' : 'static lease FAILED: ' . $err;
        }

        // 3) Hotspot bypass by MAC
        $exists = false;
        try {
            foreach ($client->sendSync(
                (new PEAR2\Net\RouterOS\Request('/ip/hotspot/ip-binding/print'))
                    ->setQuery(PEAR2\Net\RouterOS\Query::where('mac-address', $mac))
            ) as $b) {
                if ((string) $b->getProperty('.id') !== '') { $exists = true; }
            }
        } catch (Throwable $e) {
            error_log('juanfi: ip-binding lookup failed: ' . $e->getMessage());
        }
        if ($exists) {
            $notes[] = 'bypass binding already exists';
        } else {
            $err = juanfi_vendo_send($client, (new PEAR2\Net\RouterOS\Request('/ip/hotspot/ip-binding/add'))
                ->setArgument('mac-address', $mac)
                ->setArgument('type', 'bypassed')
                ->setArgument('comment', $comment));
            $notes[] = $err === '' ? 'bypass binding added' : 'bypass binding FAILED: ' . $err;
        }
    }

    // 4) Address list JuanfiVendo (does not need the MAC)
    $exists = false;
    try {
        foreach ($client->sendSync(
            (new PEAR2\Net\RouterOS\Request('/ip/firewall/address-list/print'))
                ->setQuery(PEAR2\Net\RouterOS\Query::where('list', $list))
        ) as $x) {
            if ((string) $x->getProperty('address') === $ip) { $exists = true; }
        }
    } catch (Throwable $e) {
        error_log('juanfi: address-list lookup failed: ' . $e->getMessage());
    }
    if ($exists) {
        $notes[] = 'already in address list ' . $list;
    } else {
        $err = juanfi_vendo_send($client, (new PEAR2\Net\RouterOS\Request('/ip/firewall/address-list/add'))
            ->setArgument('list', $list)
            ->setArgument('address', $ip)
            ->setArgument('comment', $comment));
        $notes[] = $err === '' ? 'added to address list ' . $list : 'address list FAILED: ' . $err;
    }
}

/**
 * Route: plugin/juanfi_vendo_add (POST: router, name, ip, interface, csrf_token)
 * Adds a vendo to multiVendoAddresses in the router's config.js (SuperAdmin / Admin only).
 */
function juanfi_vendo_add()
{
    $back = '?_route=plugin/juanfi_vendo';
    juanfi_vendo_guard($back);

    $routerName = trim((string) ($_POST['router'] ?? ''));
    $name       = trim((string) ($_POST['name'] ?? ''));
    $ip         = trim((string) ($_POST['ip'] ?? ''));
    $iface      = trim((string) ($_POST['interface'] ?? ''));

    if ($routerName === '') {
        r2($back, 'e', 'Invalid request.');
    }
    // Only characters that cannot break the JavaScript file.
    if (!preg_match('/^[\p{L}\p{N} _\-\.#()]{1,40}$/u', $name)) {
        r2($back, 'e', 'Vendo name must be 1-40 characters: letters, numbers, spaces and - _ . # ( ) only.');
    }
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        r2($back, 'e', 'Vendo IP is not a valid IP address.');
    }
    if ($iface !== '' && !preg_match('/^[A-Za-z0-9 _\-\.:@\/]{1,40}$/', $iface)) {
        r2($back, 'e', 'Interface name must be up to 40 characters: letters, numbers and - _ . : @ / only.');
    }

    $notes = [];   // <-- new: filled by juanfi_vendo_provision()

    try {
        list($client, $file) = juanfi_vendo_open_config($routerName, $back);

        $problem = '';
        $new = juanfi_add_to_config($file['contents'], $name, $ip, $iface, $problem);
        if ($new === null) {
            r2($back, 'e', $problem);
        }
        // RouterOS only takes about 4 KB of file contents; never write a cut-off file.
        if (strlen($new) >= 4000) {
            r2($back, 'e', 'config.js would become too large to edit safely from here. Edit it on the router instead.');
        }

        // The portal must read the new vendo, and every old vendo must still be there.
        list($o1, $before) = juanfi_parse_vendo_config($file['contents']);
        list($o2, $after) = juanfi_parse_vendo_config($new);
        $okNew = false;
        foreach ($after as $v) {
            if ($v['ip'] === $ip && $v['name'] === $name && $v['interface'] === $iface) {
                $okNew = true;
            }
        }
        if (!$okNew || count($after) !== count($before) + 1) {
            r2($back, 'e', 'Could not add that vendo safely. Edit config.js by hand.');
        }

        juanfi_write_config($client, $file, $new);

        $check = juanfi_find_config_file($client);
        $ok = false;
        if ($check !== null) {
            list($o3, $vendos) = juanfi_parse_vendo_config($check['contents']);
            foreach ($vendos as $v) {
                if ($v['ip'] === $ip && $v['name'] === $name && $v['interface'] === $iface) {
                    $ok = true;
                }
            }
            $ok = $ok && count($vendos) === count($before) + 1;
        }
        if (!$ok) {
            r2($back, 'e', 'The file was written but the new vendo could not be confirmed. Check config.js on the router (backup: config.js.bak).');
        }

        // <-- new: static lease, hotspot bypass and address list (best effort, config.js is already saved)
        juanfi_vendo_provision($client, $ip, $notes);
    } catch (Throwable $e) {
        error_log('juanfi: vendo add failed: ' . $e->getMessage());
        r2($back, 'e', 'Router error: ' . $e->getMessage());
    }

    r2($back, 's', 'Vendo "' . $name . '" (' . $ip . ') added to config.js. '
        . ($notes ? 'Router: ' . implode('; ', $notes) . '. ' : '')
        . 'Portal pages may need a refresh to show it.');
}

/* ------------------------------------------------------------------------------------------
 * Vendo system settings (the vendo's admin API: getSystemConfig / saveSystemConfig)
 * Fields are "|" separated, in the same order as admin.js saveSystemConfig().
 * ---------------------------------------------------------------------------------------- */

/** Pin settings that can be edited: key => [position in the config, label]. */
function juanfi_vendo_cfg_pins()
{
    return [
        'coinSlotPin'       => [11, 'Coin slot pin'],
        'coinSlotSetPin'    => [12, 'Coin slot set pin'],
        'systemReadyLEDPin' => [13, 'System ready LED pin'],
        'insertCoinLEDPin'  => [14, 'Insert coin LED pin'],
        'insertCoinBtnPin'  => [16, 'Insert coin button pin'],
        'nightLightPin'     => [36, 'Night light pin'],
        'lcdSDAPin'         => [41, 'LCD SDA pin'],
        'lcdSCLPin'         => [42, 'LCD SCL pin'],
        'billAcceptorPin'   => [43, 'Bill acceptor pin'],
        'thermalPrinterPin' => [45, 'Thermal printer pin'],
        'buzzerPin'         => [57, 'Buzzer pin'],
    ];
}

/** Pin choices of the vendo's board (same lists as the vendo's admin page). */
function juanfi_vendo_pin_options($hw)
{
    $o = [['v' => '-1', 'l' => 'NONE']];
    if ($hw === 'ESP32') {
        foreach ([0,1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,21,22,23,25,26,27,32,33,34,35,36,39] as $n) {
            $o[] = ['v' => (string) $n, 'l' => 'P' . $n];
        }
    } else {
        foreach (['D0'=>16,'D1'=>5,'D2'=>4,'D3'=>0,'D4'=>2,'D5'=>14,'D6'=>12,'D7'=>13,'D8'=>15,'RX'=>3] as $l => $n) {
            $o[] = ['v' => (string) $n, 'l' => $l];
        }
    }
    return $o;
}

/** 'ESP32' or another board name, from the vendo's dashboard (falls back to ESP32). */
function juanfi_vendo_hardware($client, $ip)
{
    try {
        $raw = juanfi_vendo_fetch($client, $ip, '/admin/api/dashboard?query=' . time());
        $p = explode('|', trim($raw));
        if (isset($p[8]) && trim($p[8]) !== '') {
            return trim($p[8]);
        }
    } catch (Throwable $e) {
        error_log('juanfi: dashboard read failed: ' . $e->getMessage());
    }
    return 'ESP32';
}

/** Read the vendo's system config as a list of fields. */
function juanfi_vendo_cfg_read($client, $ip)
{
    $raw = juanfi_vendo_fetch($client, $ip, '/admin/api/getSystemConfig?query=' . time());
    $raw = trim(str_replace("\0", '', (string) $raw));
    $f = explode('|', $raw);
    if (count($f) < 58) {
        throw new RuntimeException('The vendo returned an unexpected system config (' . count($f) . ' fields). Its firmware may be too old for this editor.');
    }
    return $f;
}

/** Route: plugin/juanfi_vendo_cfg_get (GET: router, ip) - the settings shown in the Edit Vendo tabs. */
function juanfi_vendo_cfg_get()
{
    juanfi_vendo_json_guard(false);

    $routerName = trim((string) ($_GET['router'] ?? ''));
    $ip         = trim((string) ($_GET['ip'] ?? ''));

    try {
        $client = juanfi_vendo_rates_client($routerName, $ip);
        $f  = juanfi_vendo_cfg_read($client, $ip);
        $hw = juanfi_vendo_hardware($client, $ip);
    } catch (Throwable $e) {
        juanfi_vendo_json(['ok' => false, 'error' => $e->getMessage()], 502);
    }

    $pins = [];
    foreach (juanfi_vendo_cfg_pins() as $key => $def) {
        $pins[] = ['key' => $key, 'label' => $def[1], 'value' => trim($f[$def[0]])];
    }
    // Passwords are never sent to the browser.
    juanfi_vendo_json([
        'ok'       => true,
        'hardware' => $hw,
        'options'  => juanfi_vendo_pin_options($hw),
        'pins'     => $pins,
        'values'   => [
            'wait'    => trim($f[6]),
            'abuse'   => trim($f[9]),
            'ban'     => trim($f[10]),
            'restart' => trim($f[55]),
        ],
    ]);
}

/**
 * Route: plugin/juanfi_vendo_cfg_save (POST: router, ip, wait, abuse, ban, restart, pins = JSON,
 *        admin_pw, admin_pw2, op_pw, op_pw2, csrf_token)
 * Changes only those fields in the vendo's system config. The vendo restarts after saving.
 */
function juanfi_vendo_cfg_save()
{
    juanfi_vendo_json_guard(true);

    $routerName = trim((string) ($_POST['router'] ?? ''));
    $ip         = trim((string) ($_POST['ip'] ?? ''));
    $wait       = trim((string) ($_POST['wait'] ?? ''));
    $abuse      = trim((string) ($_POST['abuse'] ?? ''));
    $ban        = trim((string) ($_POST['ban'] ?? ''));
    $restart    = trim((string) ($_POST['restart'] ?? ''));
    $pinsIn     = json_decode((string) ($_POST['pins'] ?? ''), true);
    $adminPw    = (string) ($_POST['admin_pw'] ?? '');
    $adminPw2   = (string) ($_POST['admin_pw2'] ?? '');
    $opPw       = (string) ($_POST['op_pw'] ?? '');
    $opPw2      = (string) ($_POST['op_pw2'] ?? '');

    $fail = static function ($msg) {
        juanfi_vendo_json(['ok' => false, 'error' => $msg], 422);
    };

    if (!preg_match('/^\d{1,4}$/', $wait) || (int) $wait < 1) {
        $fail('Insert coin time must be a whole number of seconds (1-9999).');
    }
    if (!preg_match('/^\d{1,3}$/', $abuse)) {
        $fail('Insert coin ban attempts must be a whole number (0-999).');
    }
    if (!preg_match('/^\d{1,5}$/', $ban)) {
        $fail('Ban time must be a whole number of minutes.');
    }
    if (!preg_match('/^\d{1,4}$/', $restart)) {
        $fail('Auto restart must be a whole number (0 = off).');
    }
    if (!is_array($pinsIn)) {
        $fail('Invalid pin data.');
    }
    foreach ([['Admin', $adminPw, $adminPw2], ['Operator', $opPw, $opPw2]] as $p) {
        if ($p[1] === '' && $p[2] === '') {
            continue;
        }
        if ($p[1] !== $p[2]) {
            $fail($p[0] . ' passwords do not match.');
        }
        if (!preg_match('/^[\x21-\x7E]{4,32}$/', $p[1]) || strpbrk($p[1], '|#') !== false) {
            $fail($p[0] . ' password must be 4-32 characters, no spaces, and no | or #.');
        }
    }

    try {
        $client = juanfi_vendo_rates_client($routerName, $ip);
        $f  = juanfi_vendo_cfg_read($client, $ip);      // always edit the vendo's CURRENT config
        $hw = juanfi_vendo_hardware($client, $ip);
    } catch (Throwable $e) {
        juanfi_vendo_json(['ok' => false, 'error' => $e->getMessage()], 502);
    }

    $defs    = juanfi_vendo_cfg_pins();
    $allowed = array_column(juanfi_vendo_pin_options($hw), 'v');

    foreach ($pinsIn as $key => $val) {
        if (!isset($defs[$key]) || !is_scalar($val) || !preg_match('/^-?\d{1,2}$/', (string) $val)) {
            $fail('Invalid pin setting.');
        }
        $val = (string) $val;
        $idx = $defs[$key][0];
        if (!in_array($val, $allowed, true) && $val !== trim($f[$idx])) {
            $fail($defs[$key][1] . ': that pin does not exist on this board (' . $hw . ').');
        }
        $f[$idx] = $val;
    }

    // The same pin cannot be used twice (NONE is fine).
    $used = [];
    foreach ($defs as $key => $def) {
        $v = trim($f[$def[0]]);
        if ($v === '' || $v === '-1') {
            continue;
        }
        if (isset($used[$v])) {
            $fail($def[1] . ' is already used by ' . $used[$v] . '.');
        }
        $used[$v] = $def[1];
    }

    $f[6]  = $wait;
    $f[9]  = $abuse;
    $f[10] = $ban;
    $f[55] = $restart;
    if ($adminPw !== '') {
        $f[8] = $adminPw;
    }
    if ($opPw !== '') {
        $f[34] = $opPw;
    }

    try {
        juanfi_vendo_fetch($client, $ip, '/admin/api/saveSystemConfig', 'data=' . rawurlencode(implode('|', $f)));
    } catch (Throwable $e) {
        if (strpos($e->getMessage(), 'rejected') !== false) {
            juanfi_vendo_json(['ok' => false, 'error' => $e->getMessage()], 502);
        }
        // The vendo restarts right after saving and may drop the connection before it answers.
        error_log('juanfi: saveSystemConfig no answer: ' . $e->getMessage());
        juanfi_vendo_json([
            'ok' => true, 'warn' => true,
            'message' => 'No confirmation from the vendo (it may be restarting). Wait about 1 minute, then open Edit again to check the values.',
        ]);
    }

    juanfi_vendo_json(['ok' => true, 'message' => 'Saved to the vendo ' . $ip . '. It is restarting now, wait about 1 minute.']);
}
/* ------------------------------------------------------------------------------------------
 * Wifi rates of a vendo (the vendo's own admin API: admin/api/getRates and admin/api/saveRates)
 *
 * The vendo sits on the router's LAN, so the web server cannot reach it. The request is made by the
 * router itself (/tool/fetch), the same way the ping is done.
 * ---------------------------------------------------------------------------------------- */

// API key of the vendos. Define JUANFI_VENDO_API_KEY earlier (e.g. in config.php) to change it.
if (!defined('JUANFI_VENDO_API_KEY')) {
    define('JUANFI_VENDO_API_KEY', 'BILLSYS');
}
// Header that carries the key to the vendo's admin API (admin.js sends its login token as X-TOKEN).
if (!defined('JUANFI_VENDO_AUTH_HEADER')) {
    define('JUANFI_VENDO_AUTH_HEADER', 'X-TOKEN');
}

/** Send a JSON answer and stop (used by the rates routes, which are called with fetch()). */
function juanfi_vendo_json(array $payload, $code = 200)
{
    if (!headers_sent()) {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

/** Checks for the JSON routes: SuperAdmin / Admin only, and POST + CSRF token when $post is true. */
function juanfi_vendo_json_guard($post)
{
    _admin();

    $admin = Admin::_info();
    if (!in_array((string) ($admin['user_type'] ?? ''), ['SuperAdmin', 'Admin'], true)) {
        juanfi_vendo_json(['ok' => false, 'error' => 'You are not allowed to do this.'], 403);
    }
    if ($post) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            juanfi_vendo_json(['ok' => false, 'error' => 'Invalid request.'], 405);
        }
        if (class_exists('Csrf') && !Csrf::check((string) ($_POST['csrf_token'] ?? ''))) {
            juanfi_vendo_json(['ok' => false, 'error' => 'Invalid or expired token. Reload the page and try again.'], 400);
        }
    }
}

/** JavaScript encodeURI() (the vendo expects its data encoded like admin.js does it). */
function juanfi_encode_uri($text)
{
    return strtr(rawurlencode((string) $text), [
        '%3B' => ';', '%2C' => ',', '%2F' => '/', '%3F' => '?', '%3A' => ':', '%40' => '@',
        '%26' => '&', '%3D' => '=', '%2B' => '+', '%24' => '$', '%23' => '#',
        '%21' => '!', '%2A' => '*', '%27' => "'", '%28' => '(', '%29' => ')',
    ]);
}

/**
 * Connect to the router and make sure $ip really is one of the vendos in that router's config.js
 * (so this page cannot be used to make the router call any other address).
 *
 * @throws RuntimeException with a message that is safe to show
 */
function juanfi_vendo_rates_client($routerName, $ip)
{
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        throw new RuntimeException('Invalid vendo IP.');
    }
    $router = ORM::for_table('tbl_routers')->where('name', $routerName)->find_one();
    if (!$router) {
        throw new RuntimeException('Router not found.');
    }

    $client = Mikrotik::getClient($router['ip_address'], $router['username'], $router['password']);

    $source = '';
    $js = juanfi_read_config_js($client, $source);
    list($opt, $vendos) = juanfi_parse_vendo_config($js);
    foreach ($vendos as $v) {
        if ($v['ip'] === $ip) {
            return $client;
        }
    }
    throw new RuntimeException('That IP is not a vendo in config.js of this router.');
}

/**
 * Build the /tool/fetch request that calls the vendo's admin API.
 *
 * @param string $mode 'user' (answer returned by the API), 'user-asvalue' (same, with as-value), 'file' (saved to $dst)
 *                     or 'none' (answer thrown away: used for the saveRates POST, the same as the working Winbox command)
 */
function juanfi_vendo_fetch_request($ip, $path, $post, $mode, $dst = '')
{
    // No space after the colon: with "X-TOKEN: KEY" the vendo answers 401, with "X-TOKEN:KEY" it accepts the key
    // (tested with /tool fetch on the router).
    $headers = JUANFI_VENDO_AUTH_HEADER . ':' . JUANFI_VENDO_API_KEY;
    if ($post !== null) {
        $headers .= ',Content-Type: application/x-www-form-urlencoded';
    }

    $req = (new PEAR2\Net\RouterOS\Request('/tool/fetch'))
        ->setArgument('url', 'http://' . $ip . $path)
        ->setArgument('mode', 'http')
        ->setArgument('http-header-field', $headers);

    if ($mode === 'file') {
        $req->setArgument('output', 'file')->setArgument('dst-path', $dst);
    } elseif ($mode === 'none') {
        $req->setArgument('output', 'none');
    } else {
        $req->setArgument('output', 'user');
        if ($mode === 'user-asvalue') {
            $req->setArgument('as-value', '');
        }
    }
    if ($post !== null) {
        $req->setArgument('http-method', 'post')->setArgument('http-data', $post);
    }
    return $req;
}

/**
 * Error text of a router answer, or '' when it has none.
 * The RouterOS API client does NOT throw when the router answers with an error (!trap), so every answer
 * has to be checked here. A failed download can also arrive as status=failed with a "failure" text.
 */
function juanfi_vendo_answer_error($res)
{
    foreach ($res as $x) {
        $type = $x->getType();
        if ($type === PEAR2\Net\RouterOS\Response::TYPE_ERROR || $type === PEAR2\Net\RouterOS\Response::TYPE_FATAL) {
            $m = trim((string) $x->getProperty('message'));
            return $m !== '' ? $m : 'the router returned an error';
        }
        if ((string) $x->getProperty('status') === 'failed') {
            $m = trim((string) $x->getProperty('failure'));
            return $m !== '' ? $m : 'the download failed';
        }
    }
    return '';
}

/** Turn an error text into a RuntimeException with a message that is safe to show. */
function juanfi_vendo_fetch_failure($message, $ip, $path)
{
    error_log('juanfi: vendo ' . $ip . $path . ' failed: ' . $message);
    if (preg_match('/\b40[13]\b|unauthori[sz]ed|forbidden/i', $message)) {
        return new RuntimeException('The vendo rejected the API key (HTTP 401/403).');
    }
    return new RuntimeException('Could not reach the vendo ' . $ip . ' from the router: ' . $message);
}

/** Same, for an exception thrown while talking to the router. */
function juanfi_vendo_fetch_error(Throwable $e, $ip, $path)
{
    return juanfi_vendo_fetch_failure($e->getMessage(), $ip, $path);
}

/**
 * Readable form of a router answer (type and the few properties /tool/fetch uses), for the debug route.
 */
function juanfi_vendo_describe($res)
{
    $items = [];
    foreach ($res as $x) {
        $row = ['type' => $x->getType()];
        foreach (['status', 'failure', 'message', 'downloaded', 'total', 'duration', 'data'] as $k) {
            $v = $x->getProperty($k);
            if ($v !== null) {
                $row[$k] = (string) $v;
            }
        }
        $items[] = $row;
    }
    return $items;
}

/**
 * Last resort for reading an answer: let the router save it into a temporary file, read that file, delete it.
 * An empty file means the vendo really answered with nothing, so this result can be trusted.
 *
 * @throws RuntimeException with a message that is safe to show
 */
function juanfi_vendo_fetch_via_file($client, $ip, $path)
{
    $name = 'juanfi_tmp_' . substr(md5(uniqid('', true)), 0, 8) . '.txt';

    try {
        $res = $client->sendSync(juanfi_vendo_fetch_request($ip, $path, null, 'file', $name));
    } catch (Throwable $e) {
        throw juanfi_vendo_fetch_error($e, $ip, $path);
    }
    $msg = juanfi_vendo_answer_error($res);
    if ($msg !== '') {
        throw juanfi_vendo_fetch_failure($msg, $ip, $path);
    }

    // The router may store the file under a disk prefix (e.g. "flash/"), so match the end of the name.
    $found = false;
    $fileName = '';
    $contents = '';
    $detail = '';
    $count = 0;

    for ($try = 0; $try < 4 && !$found; $try++) {
        if ($try > 0) {
            usleep(300000);
        }
        try {
            $list = new PEAR2\Net\RouterOS\Request('/file/print');
            $list->setArgument('.proplist', '.id,name');
            $count = 0;
            foreach ($client->sendSync($list) as $f) {
                $n = (string) $f->getProperty('name');
                if ($n === '') {
                    continue;
                }
                $count++;
                if ($n === $name || substr($n, -(strlen($name) + 1)) === '/' . $name) {
                    $fileName = $n;
                    $found = true;
                }
            }
        } catch (Throwable $e) {
            $detail = $e->getMessage();
            error_log('juanfi: listing router files failed: ' . $detail);
        }
    }

    if (!$found) {
        throw new RuntimeException('The router fetched the vendo page but no result file appeared (looked for '
            . $name . ' among ' . $count . ' files)' . ($detail !== '' ? ': ' . $detail : '') . '.');
    }

    try {
        $read = new PEAR2\Net\RouterOS\Request('/file/print');
        $read->setArgument('.proplist', '.id,name,contents');
        $read->setQuery(PEAR2\Net\RouterOS\Query::where('name', $fileName));
        $id = '';
        foreach ($client->sendSync($read) as $f) {
            if ((string) $f->getProperty('name') === $fileName) {
                $id = (string) $f->getProperty('.id');
                $contents = (string) $f->getProperty('contents');
            }
        }
        if ($id !== '') {
            $client->sendSync((new PEAR2\Net\RouterOS\Request('/file/remove'))->setArgument('numbers', $id));
        }
    } catch (Throwable $e) {
        error_log('juanfi: reading the fetch result file failed: ' . $e->getMessage());
        throw new RuntimeException('The result file ' . $fileName . ' could not be read: ' . $e->getMessage());
    }

    // RouterOS only returns about 4 KB of file contents; never work with a cut-off answer.
    if (strlen($contents) >= 4000) {
        throw new RuntimeException('The vendo answer is too large to read through the router (4 KB limit).');
    }
    return $contents;
}

/**
 * Make the router call the vendo's admin API and return the answer text.
 * GET: tries output=user, then output=user + as-value, then a temporary file. It only returns '' when the vendo
 * really answered with nothing, so an unreadable list can no longer look like an empty list.
 *
 * @param string|null $post form body for a POST, or null for a GET
 * @throws RuntimeException with a message that is safe to show
 */
function juanfi_vendo_fetch($client, $ip, $path, $post = null)
{
    // A POST only has to be delivered; its answer text is not used.
    if ($post !== null) {
        try {
            $res = $client->sendSync(juanfi_vendo_fetch_request($ip, $path, $post, 'none'));
        } catch (Throwable $e) {
            throw juanfi_vendo_fetch_error($e, $ip, $path);
        }
        $msg = juanfi_vendo_answer_error($res);
        if ($msg !== '') {
            throw juanfi_vendo_fetch_failure($msg, $ip, $path);
        }
        return '';
    }

    foreach (['user', 'user-asvalue'] as $mode) {
        try {
            $res = $client->sendSync(juanfi_vendo_fetch_request($ip, $path, null, $mode));
            $msg = juanfi_vendo_answer_error($res);
            if ($msg !== '') {
                throw juanfi_vendo_fetch_failure($msg, $ip, $path);
            }
        } catch (Throwable $e) {
            $err = $e instanceof RuntimeException ? $e : juanfi_vendo_fetch_error($e, $ip, $path);
            // The first try must work; a router that does not know as-value just moves on to the file way.
            if ($mode === 'user' || strpos($err->getMessage(), 'rejected') !== false) {
                throw $err;
            }
            continue;
        }

        $data = '';
        foreach ($res as $x) {
            $d = (string) $x->getProperty('data');
            if ($d !== '') {
                $data = $d;
            }
        }
        if ($data !== '') {
            return $data;
        }
    }

    return juanfi_vendo_fetch_via_file($client, $ip, $path);
}

/**
 * Parse the vendo's rates text: rows split by "|", columns by "#":
 * name # price # minutes # validity # data limit (MB) # user profile
 *
 * @return array list of ['name', 'price', 'minutes', 'validity', 'data', 'profile']
 */
function juanfi_parse_rates($raw)
{
    $raw = trim(str_replace("\0", '', (string) $raw));
    $rows = [];
    if ($raw === '') {
        return $rows;
    }
    foreach (explode('|', $raw) as $line) {
        if (trim($line) === '') {
            continue;
        }
        $c = explode('#', $line);
        $rows[] = [
            'name'     => trim($c[0] ?? ''),
            'price'    => trim($c[1] ?? ''),
            'minutes'  => trim($c[2] ?? ''),
            'validity' => trim($c[3] ?? ''),
            'data'     => trim($c[4] ?? ''),
            'profile'  => trim($c[5] ?? ''),
        ];
    }
    return $rows;
}

/** Read the vendo's current wifi rates (rateType 1). */
function juanfi_vendo_read_rates($client, $ip)
{
    $raw = juanfi_vendo_fetch($client, $ip, '/admin/api/getRates?rateType=1&query=' . time());
    return juanfi_parse_rates($raw);
}

/**
 * Check the rows the browser sent. Only characters that cannot break the vendo's "name#price#..." format.
 *
 * @param string $error set to a message when null is returned
 * @return array|null cleaned rows, sorted by price (like the vendo's own page does)
 */
function juanfi_validate_rates($rows, &$error)
{
    $error = '';
    if (!is_array($rows) || count($rows) < 1) {
        $error = 'Add at least one rate.';
        return null;
    }
    if (count($rows) > 100) {
        $error = 'A vendo can hold up to 100 rates.';
        return null;
    }

    $clean = [];
    foreach (array_values($rows) as $i => $r) {
        $n = $i + 1;
        $row = [
            'name'     => trim((string) ($r['name'] ?? '')),
            'price'    => trim((string) ($r['price'] ?? '')),
            'minutes'  => trim((string) ($r['minutes'] ?? '')),
            'validity' => trim((string) ($r['validity'] ?? '')),
            'data'     => trim((string) ($r['data'] ?? '')),
            'profile'  => trim((string) ($r['profile'] ?? '')),
        ];
        if (!preg_match('/^[\p{L}\p{N} _\-\.()]{1,30}$/u', $row['name'])) {
            $error = 'Rate ' . $n . ': name must be 1-30 characters (letters, numbers, spaces and - _ . ( ) only).';
            return null;
        }
        if (!preg_match('/^\d{1,6}$/', $row['price'])) {
            $error = 'Rate ' . $n . ': price must be a whole number.';
            return null;
        }
        if (!preg_match('/^\d{1,6}$/', $row['minutes']) || (int) $row['minutes'] < 1) {
            $error = 'Rate ' . $n . ': minutes must be a whole number above 0.';
            return null;
        }
        if (!preg_match('/^\d{0,7}$/', $row['validity'])) {
            $error = 'Rate ' . $n . ': validity must be a whole number of minutes (or empty).';
            return null;
        }
        if (!preg_match('/^\d{0,7}$/', $row['data'])) {
            $error = 'Rate ' . $n . ': data limit must be a whole number of MB (or empty).';
            return null;
        }
        if (!preg_match('/^[A-Za-z0-9 _\-\.]{0,30}$/', $row['profile'])) {
            $error = 'Rate ' . $n . ': user profile may only have letters, numbers, spaces and - _ .';
            return null;
        }
        $clean[] = $row;
    }

    usort($clean, static function ($a, $b) {
        return (int) $a['price'] <=> (int) $b['price'];
    });
    return $clean;
}

/**
 * Route: plugin/juanfi_vendo_rates_get (GET: router, ip) - JSON {ok, rates[]} with the vendo's current wifi rates.
 */
function juanfi_vendo_rates_get()
{
    juanfi_vendo_json_guard(false);

    $routerName = trim((string) ($_GET['router'] ?? ''));
    $ip         = trim((string) ($_GET['ip'] ?? ''));

    try {
        $client = juanfi_vendo_rates_client($routerName, $ip);
        $rates = juanfi_vendo_read_rates($client, $ip);
    } catch (Throwable $e) {
        juanfi_vendo_json(['ok' => false, 'error' => $e->getMessage()], 502);
    }
    juanfi_vendo_json(['ok' => true, 'rates' => $rates]);
}

/**
 * Route: plugin/juanfi_vendo_rates_save (POST: router, ip, rates = JSON list, csrf_token)
 * Saves the whole wifi rate list of a vendo (add / edit / delete rows) and returns the rates as the vendo now has them.
 */
function juanfi_vendo_rates_save()
{
    juanfi_vendo_json_guard(true);

    $routerName = trim((string) ($_POST['router'] ?? ''));
    $ip         = trim((string) ($_POST['ip'] ?? ''));
    $rows       = json_decode((string) ($_POST['rates'] ?? ''), true);

    $error = '';
    $clean = juanfi_validate_rates($rows, $error);
    if ($clean === null) {
        juanfi_vendo_json(['ok' => false, 'error' => $error], 422);
    }

    try {
        $client = juanfi_vendo_rates_client($routerName, $ip);

        // Read first: proves the vendo is reachable and accepts the key before anything is changed.
        juanfi_vendo_read_rates($client, $ip);

        $lines = [];
        foreach ($clean as $r) {
            $lines[] = $r['name'] . '#' . $r['price'] . '#' . $r['minutes'] . '#' . $r['validity'] . '#' . $r['data'] . '#' . $r['profile'];
        }
        juanfi_vendo_fetch(
            $client,
            $ip,
            '/admin/api/saveRates?rateType=1',
            'data=' . juanfi_encode_uri(implode('|', $lines))
        );

        // Read it back and make sure the vendo really has the new list.
        $fresh = juanfi_vendo_read_rates($client, $ip);
        $same = count($fresh) === count($clean);
        if ($same) {
            foreach ($clean as $i => $r) {
                if ($fresh[$i]['name'] !== $r['name']
                    || (int) $fresh[$i]['price'] !== (int) $r['price']
                    || (int) $fresh[$i]['minutes'] !== (int) $r['minutes']) {
                    $same = false;
                    break;
                }
            }
        }
        if (!$same) {
            juanfi_vendo_json([
                'ok'    => false,
                'error' => 'The vendo answered, but its rates are not what was sent. Check the rates on the vendo.',
                'rates' => $fresh,
            ], 502);
        }
    } catch (Throwable $e) {
        juanfi_vendo_json(['ok' => false, 'error' => $e->getMessage()], 502);
    }

    juanfi_vendo_json(['ok' => true, 'rates' => $fresh, 'message' => 'Rates saved on the vendo ' . $ip . '.']);
}

/**
 * TEMPORARY diagnostic route: plugin/juanfi_vendo_rates_debug (GET: router, ip)
 * Shows what each way of reading the vendo's getRates answer returns, and the router's file names.
 * Delete this function once the rates work.
 */
function juanfi_vendo_rates_debug()
{
    juanfi_vendo_json_guard(false);

    $routerName = trim((string) ($_GET['router'] ?? ''));
    $ip         = trim((string) ($_GET['ip'] ?? ''));
    $path       = '/admin/api/getRates?rateType=1&query=' . time();
    $out = [];

    try {
        $client = juanfi_vendo_rates_client($routerName, $ip);

        foreach (['user' => 'user', 'as-value' => 'user-asvalue'] as $label => $mode) {
            try {
                $out[$label] = juanfi_vendo_describe($client->sendSync(juanfi_vendo_fetch_request($ip, $path, null, $mode)));
            } catch (Throwable $e) {
                $out[$label] = ['error' => $e->getMessage()];
            }
        }
        try {
            $out['file'] = ['contents' => juanfi_vendo_fetch_via_file($client, $ip, $path)];
        } catch (Throwable $e) {
            $out['file'] = ['error' => $e->getMessage()];
        }
        try {
            $out['final'] = ['answer' => juanfi_vendo_fetch($client, $ip, $path)];
        } catch (Throwable $e) {
            $out['final'] = ['error' => $e->getMessage()];
        }
        try {
            $names = [];
            $req = new PEAR2\Net\RouterOS\Request('/file/print');
            $req->setArgument('.proplist', 'name');
            foreach ($client->sendSync($req) as $f) {
                $n = (string) $f->getProperty('name');
                if ($n !== '') {
                    $names[] = $n;
                }
            }
            $out['router_files'] = array_slice($names, 0, 80);
        } catch (Throwable $e) {
            $out['router_files'] = ['error' => $e->getMessage()];
        }
    } catch (Throwable $e) {
        juanfi_vendo_json(['ok' => false, 'error' => $e->getMessage()], 502);
    }

    juanfi_vendo_json(['ok' => true, 'debug' => $out]);
}