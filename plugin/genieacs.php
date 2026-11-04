<?php
/**
 * BILLSYS GenieACS ONU Management.
 * Install as system/plugin/genieacs.php and system/plugin/ui/genieacs.tpl.
 */
register_menu("GenieACS ONU Management", true, "genieacs", "AFTER_CUSTOMERS", "fa fa-wifi", "", "", ['Admin', 'SuperAdmin']);

function genieacs_guard($back, $postOnly = true)
{
    _admin();
    $admin = Admin::_info();
    if (!in_array((string)($admin['user_type'] ?? ''), ['Admin', 'SuperAdmin'], true)) {
        r2($back, 'e', 'You are not allowed to do this.');
    }
    if ($postOnly && ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') r2($back);
    if ($postOnly && class_exists('Csrf') && !Csrf::check((string)($_POST['csrf_token'] ?? ''))) {
        r2($back, 'e', 'Invalid or expired token.');
    }
}

function genieacs_config_get()
{
    $out = [];
    foreach (['genieacs_nbi_url','genieacs_username','genieacs_password','genieacs_links'] as $key) {
        $row = ORM::for_table('tbl_appconfig')->where('setting', $key)->find_one();
        $out[$key] = $row ? (string)$row->value : '';
    }
    return $out;
}

function genieacs_config_set($key, $value)
{
    $row = ORM::for_table('tbl_appconfig')->where('setting', $key)->find_one();
    if (!$row) { $row = ORM::for_table('tbl_appconfig')->create(); $row->setting = $key; }
    $row->value = $value;
    $row->save();
}

function genieacs_nbi_request($method, $path, $payload = null, $query = [])
{
    $cfg = genieacs_config_get();
    $base = rtrim($cfg['genieacs_nbi_url'], '/');
    if (!filter_var($base, FILTER_VALIDATE_URL) || !in_array(parse_url($base, PHP_URL_SCHEME), ['http','https'], true)) {
        throw new RuntimeException('Configure a valid GenieACS NBI URL first.');
    }
    $url = $base . '/' . ltrim($path, '/');
    if ($query) $url .= '?' . http_build_query($query);
    $headers = ['Accept: application/json'];
    $body = null;
    if ($payload !== null) { $headers[] = 'Content-Type: application/json'; $body = json_encode($payload, JSON_UNESCAPED_SLASHES); }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_HTTPHEADER => $headers, CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => false
    ]);
    if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    if ($cfg['genieacs_username'] !== '') {
        curl_setopt($ch, CURLOPT_USERPWD, $cfg['genieacs_username'] . ':' . $cfg['genieacs_password']);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    }
    $response = curl_exec($ch); $error = curl_error($ch); $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    if ($response === false) throw new RuntimeException('GenieACS connection failed: ' . $error);
    if ($status < 200 || $status >= 300) throw new RuntimeException('GenieACS HTTP ' . $status . ': ' . substr((string)$response, 0, 400));
    if (trim((string)$response) === '') return null;
    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) throw new RuntimeException('GenieACS returned invalid JSON.');
    return $data;
}

function genieacs_links_get()
{
    $links = json_decode(genieacs_config_get()['genieacs_links'], true);
    return is_array($links) ? $links : [];
}
function genieacs_value($device, $path)
{
    $node = $device;
    foreach (explode('.', $path) as $part) {
        if (!is_array($node) || !array_key_exists($part, $node)) return '';
        $node = $node[$part];
    }
    return is_array($node) ? (string)($node['_value'] ?? '') : '';
}
function genieacs_devices_fetch()
{
    return genieacs_nbi_request('GET', '/devices/', null, ['projection' =>
        '_id,_lastInform,DeviceID,InternetGatewayDevice.DeviceInfo.Manufacturer,InternetGatewayDevice.DeviceInfo.ModelName,InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.1.Username,InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSID,InternetGatewayDevice.LANDevice.1.WLANConfiguration.2.SSID'
    ]) ?: [];
}
function genieacs()
{
    global $ui;
    genieacs_guard(getUrl('plugin/genieacs'), false);
    $cfg = genieacs_config_get(); $devices = []; $error = '';
    if ($cfg['genieacs_nbi_url'] !== '') {
        try { $devices = genieacs_devices_fetch(); } catch (Throwable $e) { $error = $e->getMessage(); }
    }
    $links = genieacs_links_get();
    foreach ($devices as &$d) {
        $id = (string)($d['_id'] ?? '');
        $d['_customer_id'] = (string)($links[$id]['customer_id'] ?? '');
        $d['_pppoe'] = genieacs_value($d, 'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.1.Username');
        $d['_manufacturer'] = genieacs_value($d, 'InternetGatewayDevice.DeviceInfo.Manufacturer');
        $d['_model'] = genieacs_value($d, 'InternetGatewayDevice.DeviceInfo.ModelName');
        $d['_ssid1'] = genieacs_value($d, 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSID');
        $d['_ssid2'] = genieacs_value($d, 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.2.SSID');
    }
    unset($d);
    $ui->assign('_admin', Admin::_info());
    $ui->assign('_system_menu', 'plugin/genieacs');
    $ui->assign('_title', 'GenieACS ONU Management');
    $ui->assign('genieacsDevices', $devices);
    $ui->assign('genieacsError', $error);
    $ui->assign('genieacsConfigured', $cfg['genieacs_nbi_url'] !== '');
    $ui->assign('genieacsCsrf', class_exists('Csrf') ? Csrf::generateAndStoreToken() : '');
    $ui->display('genieacs.tpl');
}
function genieacs_config()
{
    global $ui;
    $back = getUrl('plugin/genieacs_config'); genieacs_guard($back, false);
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        genieacs_guard($back, true);
        $url = rtrim(trim((string)($_POST['nbi_url'] ?? '')), '/');
        if (!filter_var($url, FILTER_VALIDATE_URL) || !in_array(parse_url($url, PHP_URL_SCHEME), ['http','https'], true)) r2($back, 'e', 'Enter a valid HTTP(S) NBI URL.');
        genieacs_config_set('genieacs_nbi_url', $url);
        genieacs_config_set('genieacs_username', trim((string)($_POST['nbi_username'] ?? '')));
        if ((string)($_POST['nbi_password'] ?? '') !== '') genieacs_config_set('genieacs_password', (string)$_POST['nbi_password']);
        r2($back, 's', 'GenieACS settings saved.');
    }
    $ui->assign('_admin', Admin::_info()); $ui->assign('_system_menu', 'plugin/genieacs');
    $ui->assign('_title', 'GenieACS Configuration'); $ui->assign('genieacsConfig', genieacs_config_get());
    $ui->assign('genieacsCsrf', class_exists('Csrf') ? Csrf::generateAndStoreToken() : '');
    $ui->display('genieacs.tpl');
}
function genieacs_assign()
{
    $back=getUrl('plugin/genieacs'); genieacs_guard($back, true);
    $id=trim((string)($_POST['device_id'] ?? '')); $cid=trim((string)($_POST['customer_id'] ?? ''));
    if ($id==='' || !ctype_digit($cid) || (int)$cid<1) r2($back,'e','Enter a valid device and customer ID.');
    try {
        $items=genieacs_nbi_request('GET','/devices/',null,['query'=>json_encode(['_id'=>$id]),'projection'=>'_id']);
        if (empty($items[0]['_id'])) r2($back,'e','Device not found in GenieACS.');
    } catch (Throwable $e) { r2($back,'e',$e->getMessage()); }
    $links=genieacs_links_get();
    // One ONU may belong to only one customer; one customer may have many ONUs.
    $links[$id]=['customer_id'=>$cid,'updated_at'=>date('c')];
    genieacs_config_set('genieacs_links',json_encode($links));
    r2($back,'s','ONU assigned to customer #'.$cid.'.');
}
function genieacs_unassign()
{
    $back=getUrl('plugin/genieacs'); genieacs_guard($back,true);
    $id=trim((string)($_POST['device_id'] ?? '')); $links=genieacs_links_get(); unset($links[$id]);
    genieacs_config_set('genieacs_links',json_encode($links)); r2($back,'s','ONU assignment removed.');
}
function genieacs_wifi()
{
    $back=getUrl('plugin/genieacs'); genieacs_guard($back,true);
    $id=trim((string)($_POST['device_id'] ?? '')); $band=(string)($_POST['band'] ?? '2g');
    $ssid=trim((string)($_POST['ssid'] ?? '')); $password=(string)($_POST['wifi_password'] ?? '');
    if ($id==='' || !in_array($band,['2g','5g'],true)) r2($back,'e','Invalid device or band.');

    // Only allow changes to a device that has been explicitly bound to a customer.
    $links=genieacs_links_get();
    if (!isset($links[$id])) r2($back,'e','Assign this ONU to a BILLSYS customer before managing it.');

    $index=$band==='2g'?'1':'5'; $params=[];
    if ($ssid!=='') $params[]=["InternetGatewayDevice.LANDevice.1.WLANConfiguration.$index.SSID",$ssid,'xsd:string'];
    if ($password!=='') {
        if (strlen($password)<8 || strlen($password)>63) r2($back,'e','WiFi password must be 8 to 63 characters.');
        $params[]=["InternetGatewayDevice.LANDevice.1.WLANConfiguration.$index.PreSharedKey.1.KeyPassphrase",$password,'xsd:string'];
    }
    if (!$params) r2($back,'e','Enter an SSID or password.');
    try {
        $result=genieacs_nbi_request('POST','/devices/'.rawurlencode($id).'/tasks',['name'=>'setParameterValues','parameterValues'=>$params],['timeout'=>30000,'connection_request'=>'']);
        r2($back,'s','Task submitted: '.(string)($result['_id'] ?? 'queued').'. Check task execution before reporting success.');
    } catch (Throwable $e) { r2($back,'e',$e->getMessage()); }
}
function genieacs_reboot()
{
    $back=getUrl('plugin/genieacs'); genieacs_guard($back,true);
    $id=trim((string)($_POST['device_id'] ?? ''));
    if ($id==='') r2($back,'e','Select a device.');
    $links=genieacs_links_get();
    if (!isset($links[$id])) r2($back,'e','Assign this ONU to a BILLSYS customer before managing it.');
    try {
        $result=genieacs_nbi_request('POST','/devices/'.rawurlencode($id).'/tasks',['name'=>'reboot'],['timeout'=>30000,'connection_request'=>'']);
        r2($back,'s','Reboot task submitted: '.(string)($result['_id'] ?? 'queued'));
    } catch (Throwable $e) { r2($back,'e',$e->getMessage()); }
}
