<?php
/**
 * BILLSYS GenieACS ONU Management plugin.
 *
 * Install:
 *   system/plugin/genieacs.php
 *   system/plugin/ui/genieacs.tpl
 *
 * GenieACS NBI calls are performed server-side. Do not expose NBI credentials
 * to the browser. Device/customer links are stored in tbl_appconfig as JSON
 * to avoid requiring a separate migration in the first release.
 */

register_menu("GenieACS ONU Management", true, "genieacs", "AFTER_CUSTOMERS", "fa fa-wifi", "", "", ['Admin', 'SuperAdmin']);

function genieacs_guard($back, $postOnly = true)
{
    _admin();
    $admin = Admin::_info();
    if (!in_array((string)($admin['user_type'] ?? ''), ['Admin', 'SuperAdmin'], true)) {
        r2($back, 'e', 'You are not allowed to do this.');
    }
    if ($postOnly && ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        r2($back);
    }
    if ($postOnly && class_exists('Csrf') && !Csrf::check((string)($_POST['csrf_token'] ?? ''))) {
        r2($back, 'e', 'Invalid or expired token.');
    }
}

function genieacs_config_get()
{
    $keys = ['genieacs_nbi_url', 'genieacs_username', 'genieacs_password', 'genieacs_links'];
    $out = [];
    foreach ($keys as $key) {
        $row = ORM::for_table('tbl_appconfig')->where('setting', $key)->find_one();
        $out[$key] = $row ? (string)$row->value : '';
    }
    return $out;
}

function genieacs_config_set($key, $value)
{
    $row = ORM::for_table('tbl_appconfig')->where('setting', $key)->find_one();
    if (!$row) {
        $row = ORM::for_table('tbl_appconfig')->create();
        $row->setting = $key;
    }
    $row->value = $value;
    $row->save();
}

function genieacs_nbi_request($method, $path, $payload = null, $query = [])
{
    $cfg = genieacs_config_get();
    $base = rtrim($cfg['genieacs_nbi_url'], '/');
    if ($base === '' || !filter_var($base, FILTER_VALIDATE_URL)) {
        throw new RuntimeException('Configure a valid GenieACS NBI URL first.');
    }
    // Only allow HTTP(S) URL schemes; NBI should be private/restricted to this server.
    $scheme = parse_url($base, PHP_URL_SCHEME);
    if (!in_array($scheme, ['http', 'https'], true)) {
        throw new RuntimeException('NBI URL must use HTTP or HTTPS.');
    }

    $url = $base . '/' . ltrim($path, '/');
    if ($query) {
        $url .= '?' . http_build_query($query);
    }
    $headers = ['Accept: application/json'];
    $body = null;
    if ($payload !== null) {
        $headers[] = 'Content-Type: application/json';
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES);
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_FOLLOWLOCATION => false,
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }
    if ($cfg['genieacs_username'] !== '') {
        curl_setopt($ch, CURLOPT_USERPWD, $cfg['genieacs_username'] . ':' . $cfg['genieacs_password']);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    }
    $response = curl_exec($ch);
    $error = curl_error($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($response === false) {
        throw new RuntimeException('GenieACS connection failed: ' . $error);
    }
    if ($status < 200 || $status >= 300) {
        throw new RuntimeException('GenieACS returned HTTP ' . $status . ': ' . substr((string)$response, 0, 500));
    }
    if (trim((string)$response) === '') {
        return null;
    }
    $decoded = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new RuntimeException('GenieACS returned invalid JSON.');
    }
    return $decoded;
}

function genieacs_links_get()
{
    $cfg = genieacs_config_get();
    $links = json_decode($cfg['genieacs_links'], true);
    return is_array($links) ? $links : [];
}

function genieacs_devices_fetch()
{
    return genieacs_nbi_request('GET', '/devices/', null, [
        'projection' => '_id,_lastInform,_lastBoot,DeviceID,InternetGatewayDevice.DeviceInfo.Manufacturer,InternetGatewayDevice.DeviceInfo.ModelName,InternetGatewayDevice.DeviceInfo.SerialNumber,InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.1.Username,InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSID,InternetGatewayDevice.LANDevice.1.WLANConfiguration.2.SSID'
    ]) ?: [];
}

function genieacs()
{
    global $ui;
    genieacs_guard(getUrl('plugin/genieacs'), false);
    $cfg = genieacs_config_get();
    $devices = [];
    $error = '';
    if ($cfg['genieacs_nbi_url'] !== '') {
        try {
            $devices = genieacs_devices_fetch();
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
    $links = genieacs_links_get();
    foreach ($devices as &$device) {
        $id = (string)($device['_id'] ?? '');
        $device['_customer_id'] = (string)($links[$id]['customer_id'] ?? '');
        $device['_pppoe'] = genieacs_value($device, 'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.1.Username');
        $device['_manufacturer'] = genieacs_value($device, 'InternetGatewayDevice.DeviceInfo.Manufacturer');
        $device['_model'] = genieacs_value($device, 'InternetGatewayDevice.DeviceInfo.ModelName');
        $device['_serial'] = genieacs_value($device, 'InternetGatewayDevice.DeviceInfo.SerialNumber');
        $device['_ssid1'] = genieacs_value($device, 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSID');
        $device['_ssid2'] = genieacs_value($device, 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.2.SSID');
        $device['_last_inform'] = (string)($device['_lastInform'] ?? '');
    }
    unset($device);
    $admin = Admin::_info();
    $ui->assign('_admin', $admin);
    $ui->assign('_system_menu', 'plugin/genieacs');
    $ui->assign('_title', 'GenieACS ONU Management');
    $ui->assign('genieacsDevices', $devices);
    $ui->assign('genieacsError', $error);
    $ui->assign('genieacsConfigured', $cfg['genieacs_nbi_url'] !== '');
    $ui->assign('genieacsCsrf', class_exists('Csrf') ? Csrf::getToken() : '');
    $ui->display('genieacs.tpl');
}

function genieacs_value($device, $path)
{
    $parts = explode('.', $path);
    $node = $device;
    foreach ($parts as $part) {
        if (!is_array($node) || !array_key_exists($part, $node)) {
            return '';
        }
        $node = $node[$part];
    }
    return is_array($node) ? (string)($node['_value'] ?? '') : '';
}

function genieacs_config()
{
    global $ui;
    $back = getUrl('plugin/genieacs_config');
    genieacs_guard($back, false);
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        genieacs_guard($back, true);
        $url = rtrim(trim((string)($_POST['nbi_url'] ?? '')), '/');
        if (!filter_var($url, FILTER_VALIDATE_URL) || !in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)) {
            r2($back, 'e', 'Enter a valid HTTP or HTTPS NBI URL.');
        }
        genieacs_config_set('genieacs_nbi_url', $url);
        genieacs_config_set('genieacs_username', trim((string)($_POST['nbi_username'] ?? '')));
        // Blank password preserves the saved secret.
        if ((string)($_POST['nbi_password'] ?? '') !== '') {
            genieacs_config_set('genieacs_password', (string)$_POST['nbi_password']);
        }
        r2($back, 's', 'GenieACS settings saved.');
    }
    $cfg = genieacs_config_get();
    $admin = Admin::_info();
    $ui->assign('_admin', $admin);
    $ui->assign('_system_menu', 'plugin/genieacs');
    $ui->assign('_title', 'GenieACS Configuration');
    $ui->assign('genieacsConfig', $cfg);
    $ui->assign('genieacsCsrf', class_exists('Csrf') ? Csrf::getToken() : '');
    $ui->display('genieacs.tpl');
}

function genieacs_assign()
{
    $back = getUrl('plugin/genieacs');
    genieacs_guard($back, true);
    $deviceId = trim((string)($_POST['device_id'] ?? ''));
    $customerId = trim((string)($_POST['customer_id'] ?? ''));
    if ($deviceId === '' || !ctype_digit($customerId)) {
        r2($back, 'e', 'Choose a device and enter a valid BILLSYS customer ID.');
    }
    // Validate that the target device exists in GenieACS before storing its link.
    try {
        $items = genieacs_nbi_request('GET', '/devices/', null, [
            'query' => json_encode(['_id' => $deviceId]),
            'projection' => '_id'
        ]);
        if (empty($items[0]['_id'])) {
            r2($back, 'e', 'Device not found in GenieACS.');
        }
    } catch (Throwable $e) {
        r2($back, 'e', $e->getMessage());
    }
    $links = genieacs_links_get();
    $links[$deviceId] = ['customer_id' => $customerId, 'updated_at' => date('c')];
    genieacs_config_set('genieacs_links', json_encode($links));
    r2($back, 's', 'ONU assigned to BILLSYS customer #' . $customerId . '.');
}

function genieacs_unassign()
{
    $back = getUrl('plugin/genieacs');
    genieacs_guard($back, true);
    $deviceId = trim((string)($_POST['device_id'] ?? ''));
    $links = genieacs_links_get();
    unset($links[$deviceId]);
    genieacs_config_set('genieacs_links', json_encode($links));
    r2($back, 's', 'ONU assignment removed.');
}

function genieacs_wifi()
{
    $back = getUrl('plugin/genieacs');
    genieacs_guard($back, true);
    $deviceId = trim((string)($_POST['device_id'] ?? ''));
    $band = (string)($_POST['band'] ?? '2g');
    $ssid = trim((string)($_POST['ssid'] ?? ''));
    $password = (string)($_POST['wifi_password'] ?? '');
    if ($deviceId === '' || !in_array($band, ['2g', '5g'], true)) {
        r2($back, 'e', 'Invalid device or WiFi band.');
    }
    $index = $band === '2g' ? '1' : '5';
    $params = [];
    if ($ssid !== '') {
        $params[] = ["InternetGatewayDevice.LANDevice.1.WLANConfiguration.$index.SSID", $ssid, 'xsd:string'];
    }
    if ($password !== '') {
        if (strlen($password) < 8 || strlen($password) > 63) {
            r2($back, 'e', 'WiFi password must be 8 to 63 characters.');
        }
        $params[] = ["InternetGatewayDevice.LANDevice.1.WLANConfiguration.$index.PreSharedKey.1.KeyPassphrase", $password, 'xsd:string'];
    }
    if (!$params) {
        r2($back, 'e', 'Enter an SSID or a new WiFi password.');
    }
    try {
        $result = genieacs_nbi_request('POST', '/devices/' . rawurlencode($deviceId) . '/tasks', [
            'name' => 'setParameterValues',
            'parameterValues' => $params
        ], ['timeout' => 30000, 'connection_request' => '']);
        $id = $result['_id'] ?? '';
        r2($back, 's', $id ? 'Configuration task submitted: ' . $id : 'Configuration task submitted.');
    } catch (Throwable $e) {
        r2($back, 'e', $e->getMessage());
    }
}

function genieacs_reboot()
{
    $back = getUrl('plugin/genieacs');
    genieacs_guard($back, true);
    $deviceId = trim((string)($_POST['device_id'] ?? ''));
    if ($deviceId === '') {
        r2($back, 'e', 'Select a device.');
    }
    try {
        $result = genieacs_nbi_request('POST', '/devices/' . rawurlencode($deviceId) . '/tasks', [
            'name' => 'reboot'
        ], ['timeout' => 30000, 'connection_request' => '']);
        r2($back, 's', 'Reboot task submitted: ' . (string)($result['_id'] ?? 'queued'));
    } catch (Throwable $e) {
        r2($back, 'e', $e->getMessage());
    }
}
