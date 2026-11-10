<?php
/**
 * BILLSYS GenieACS ONU Management.
 * Install as system/plugin/genieacs.php and system/plugin/ui/genieacs.tpl.
 */
register_menu("GENIESYS", true, "genieacs", "AFTER_CUSTOMERS", "fa fa-wifi", "", "", ['Admin', 'SuperAdmin']);

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

/**
 * Create the ONU-to-customer mapping table automatically.
 * Existing assignments stored in tbl_appconfig are migrated once.
 */
function genieacs_ensure_tables()
{
    static $ready = false;
    if ($ready) {
        return;
    }

    $db = ORM::get_db();
    $db->exec("
        CREATE TABLE IF NOT EXISTS `tbl_genieacs_onu` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `device_id` VARCHAR(255) NOT NULL,
            `customer_id` BIGINT UNSIGNED NOT NULL,
            `updated_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_genieacs_device_id` (`device_id`),
            KEY `idx_genieacs_customer_id` (`customer_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // Migrate assignments created by the older JSON/appconfig version.
    $legacy = ORM::for_table('tbl_appconfig')
        ->where('setting', 'genieacs_links')
        ->find_one();

    if ($legacy && trim((string)$legacy->value) !== '') {
        $oldLinks = json_decode((string)$legacy->value, true);
        if (is_array($oldLinks)) {
            $stmt = $db->prepare("
                INSERT INTO `tbl_genieacs_onu`
                    (`device_id`, `customer_id`, `updated_at`)
                VALUES
                    (:device_id, :customer_id, :updated_at)
                ON DUPLICATE KEY UPDATE
                    `customer_id` = VALUES(`customer_id`),
                    `updated_at` = VALUES(`updated_at`)
            ");

            foreach ($oldLinks as $deviceId => $link) {
                $customerId = (string)($link['customer_id'] ?? '');
                if ((string)$deviceId === '' || !ctype_digit($customerId) || (int)$customerId < 1) {
                    continue;
                }
                $stmt->execute([
                    ':device_id' => (string)$deviceId,
                    ':customer_id' => (int)$customerId,
                    ':updated_at' => date('Y-m-d H:i:s')
                ]);
            }
        }

        // Keep the old setting empty after migration to avoid re-importing stale data.
        $legacy->value = '';
        $legacy->save();
    }

    $ready = true;
}

/**
 * Return ONU assignments in the same keyed-array format used by the UI.
 */
function genieacs_config_get()
{
    $out = [];
    foreach (['genieacs_nbi_url','genieacs_username','genieacs_password'] as $key) {
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
    genieacs_ensure_tables();

    $rows = ORM::for_table('tbl_genieacs_onu')->find_many();
    $links = [];

    foreach ($rows as $row) {
        $links[(string)$row->device_id] = [
            'customer_id' => (string)$row->customer_id,
            'updated_at' => (string)$row->updated_at
        ];
    }

    return $links;
}

function genieacs_link_set($deviceId, $customerId)
{
    genieacs_ensure_tables();

    $stmt = ORM::get_db()->prepare("
        INSERT INTO `tbl_genieacs_onu`
            (`device_id`, `customer_id`, `updated_at`)
        VALUES
            (:device_id, :customer_id, :updated_at)
        ON DUPLICATE KEY UPDATE
            `customer_id` = VALUES(`customer_id`),
            `updated_at` = VALUES(`updated_at`)
    ");

    return $stmt->execute([
        ':device_id' => (string)$deviceId,
        ':customer_id' => (int)$customerId,
        ':updated_at' => date('Y-m-d H:i:s')
    ]);
}

function genieacs_link_delete($deviceId)
{
    genieacs_ensure_tables();

    return ORM::for_table('tbl_genieacs_onu')
        ->where('device_id', (string)$deviceId)
        ->delete_many();
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

/**
 * Check whether a parameter exists in the GenieACS device data.
 */
function genieacs_parameter_exists($device, $path)
{
    $node = $device;

    foreach (explode('.', $path) as $part) {
        if (!is_array($node) || !array_key_exists($part, $node)) {
            return false;
        }

        $node = $node[$part];
    }

    return is_array($node);
}

/**
 * Detect the supported WiFi password parameter for an ONU.
 */
function genieacs_wifi_password_path($device, $index)
{
    $base = "InternetGatewayDevice.LANDevice.1.WLANConfiguration.$index.";

    $paths = [
        $base . 'PreSharedKey.1.KeyPassphrase',
        $base . 'KeyPassphrase'
    ];

    foreach ($paths as $path) {
        if (genieacs_parameter_exists($device, $path)) {
            return $path;
        }
    }

    return '';
}

/**
 * PPPoE username: virtual parameter first, then known real paths (FiberHome index 1, ZTE index 2).
 */
function genieacs_pppoe_username($device)
{
    $paths = [
        'VirtualParameters.PPPoEUsername',
        'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.1.Username',
        'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.2.Username',
    ];
    foreach ($paths as $path) {
        $v = trim(genieacs_value($device, $path));
        if ($v !== '') {
            return $v;
        }
    }
    return '';
}
/**
 * Count connected WiFi clients across TR-098 WLANConfiguration radios.
 * Project AssociatedDevice child rows; use device counters as fallbacks.
 */
function genieacs_wifi_clients_count($device)
{
    $hosts = $device['InternetGatewayDevice']['LANDevice']['1']['Hosts']['Host'] ?? [];
    $seen = [];
    if (is_array($hosts)) {
        foreach ($hosts as $i => $h) {
            if (!ctype_digit((string)$i) || !is_array($h)) continue;
            $mac = strtoupper(trim((string)($h['MACAddress']['_value'] ?? '')));
            if ($mac !== '' && $mac !== '00:00:00:00:00:00') $seen[$mac] = true;
        }
    }
    return count($seen);
}

function genieacs_devices_fetch()
{
    $wlan = 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.';
    $fields = [
        '_id', '_lastInform', 'DeviceID',
        'InternetGatewayDevice.DeviceInfo.Manufacturer',
        'InternetGatewayDevice.DeviceInfo.ModelName',
        'VirtualParameters.PPPoEUsername',
        'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.1.Username',
        'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.2.Username',
        $wlan . '1.SSID',
        $wlan . '1.KeyPassphrase',
        $wlan . '1.PreSharedKey.1.KeyPassphrase',
        $wlan . '1.TotalAssociations',

        $wlan . '5.SSID',
        $wlan . '5.KeyPassphrase',
        $wlan . '5.PreSharedKey.1.KeyPassphrase',
        $wlan . '5.TotalAssociations',
        'InternetGatewayDevice.LANDevice.1.Hosts.Host',
        'VirtualParameters.OpticalPower',
        'VirtualParameters.WifiClients',
        'InternetGatewayDevice.WANDevice.1.X_GponInterafceConfig.RXPower',
        'InternetGatewayDevice.WANDevice.1.X_GponInterfaceConfig.RXPower',
        'InternetGatewayDevice.WANDevice.1.X_HW_DEBUG.AdminTR069.RxPower',
        'InternetGatewayDevice.X_HW_DEBUG.AdminTR069.RxPower',
    ];
    return genieacs_nbi_request('GET', '/devices/', null, ['projection' => implode(',', $fields)]) ?: [];
}

/**
 * Read Huawei/other ONU optical receive power from known TR-069 paths.
 * Return a numeric dBm string or an empty string when the device does not expose it.
 */
function genieacs_optical_rx($device)
{
    // Primary source: the GenieACS Virtual Parameter built from the user's
    // six declare() fallbacks. GenieACS exposes it at VirtualParameters.OpticalPower.
    $paths = [
        'VirtualParameters.OpticalPower',
        'InternetGatewayDevice.WANDevice.1.X_GponInterafceConfig.RXPower',
        'InternetGatewayDevice.WANDevice.1.X_GponInterfaceConfig.RXPower',
        'InternetGatewayDevice.Optical.RXPower',
        'InternetGatewayDevice.X_HW_PonQualityMonitor.RXPower',
        'InternetGatewayDevice.X_HW_PonQualityMonitor.ReceiveOpticalPower',
        'InternetGatewayDevice.X_HW_PonQualityMonitor.OLTReceivedPower',
        'InternetGatewayDevice.WANDevice.1.X_HW_DEBUG.AdminTR069.RxPower',
        'InternetGatewayDevice.X_HW_DEBUG.AdminTR069.RxPower'
    ];

    foreach ($paths as $path) {
        $raw = trim(genieacs_value($device, $path));
        if ($raw === '') {
            continue;
        }

        // Some firmwares return a number, while others append the dBm unit.
        if (preg_match('/[-+]?(?:\\d+\\.?\\d*|\\.\\d+)/', $raw, $match)) {
            $value = (float)$match[0];
            if (is_finite($value) && $value <= 10 && $value >= -60) {
                return number_format($value, 2, '.', '');
            }
        }
    }

    return '';
}

/**
 * Assign an optical level and a visual severity for the inventory table.
 * More-negative RX readings indicate weaker receive power.
 */
function genieacs_optical_rx_status($rx)
{
    if ($rx === '' || !is_numeric($rx)) {
        return ['value' => '', 'class' => 'default'];
    }

    $value = (float)$rx;
    if ($value <= -27) {
        return ['value' => number_format($value, 2, '.', ''), 'class' => 'danger'];
    }
    if ($value <= -25) {
        return ['value' => number_format($value, 2, '.', ''), 'class' => 'warning'];
    }

    return ['value' => number_format($value, 2, '.', ''), 'class' => 'success'];
}
/**
 * Build a PPPoE-username-to-customer map.
 * Duplicate usernames are excluded to avoid binding an ONU to the wrong account.
 */
function genieacs_customers_by_pppoe()
{
    $rows = ORM::get_db()->query("
        SELECT `id`, `pppoe_username`, `fullname`
        FROM `tbl_customers`
        WHERE `pppoe_username` IS NOT NULL
          AND TRIM(`pppoe_username`) <> ''
    ")->fetchAll(PDO::FETCH_ASSOC);

    $map = [];
    $duplicates = [];

    foreach ($rows as $row) {
        $username = strtolower(trim((string)($row['pppoe_username'] ?? '')));
        $customerId = (int)($row['id'] ?? 0);

        if ($username === '' || $customerId < 1) {
            continue;
        }

        if (isset($map[$username])) {
            $duplicates[$username] = true;
            unset($map[$username]);
            continue;
        }

        if (!isset($duplicates[$username])) {
            $map[$username] = [
                'id' => $customerId,
                'name' => trim((string)($row['fullname'] ?? ''))
            ];
        }
    }

    return $map;
}
/**
 * Extract compound surname from customer fullname.
 */
function genieacs_extract_surname($fullname)
{
    $parts = preg_split('/\s+/', trim($fullname));

    if (!$parts || count($parts) < 2) {
        return '';
    }

    $last = array_pop($parts);
    $previous = strtolower(end($parts));

    $compoundPrefixes = [
        'dela', 'de', 'del', 'dos', 'das',
        'los', 'las', 'san', 'santa', 'van', 'von'
    ];

    if (in_array($previous, $compoundPrefixes, true)) {
        array_pop($parts);
        $surname = $previous . ' ' . $last;
    } else {
        $surname = $last;
    }

    return trim($surname);
}

/**
 * Generate customer WiFi password.
 */
function genieacs_generate_wifi_password($fullname)
{
    $surname = genieacs_extract_surname($fullname);

    $surname = strtolower($surname);
    $surname = preg_replace('/[^a-z0-9]/', '', $surname);

    if ($surname === '') {
        throw new RuntimeException('Unable to extract customer surname.');
    }

    return $surname . '_' . date('Y');
}

/**
 * Generate customer WiFi SSID.
 */
function genieacs_generate_wifi_ssid($fullname, $band = '2g')
{
    $surname = genieacs_extract_surname($fullname);

    if ($surname === '') {
        throw new RuntimeException('Unable to extract customer surname.');
    }

    $ssid = strtoupper($surname) . ' FIBER';

    if ($band === '5g') {
        $ssid .= '-5G';
    }

    return $ssid;
}

/**
 * Read a BILLSYS customer custom field.
 */
function genieacs_customer_field_get($customerId, $fieldName)
{
    $row = ORM::for_table('tbl_customers_fields')
        ->where('customer_id', (int)$customerId)
        ->where('field_name', $fieldName)
        ->find_one();

    return $row ? (string)$row->field_value : '';
}

/**
 * Save or update a BILLSYS customer custom field.
 */
function genieacs_customer_field_set($customerId, $fieldName, $value)
{
    $row = ORM::for_table('tbl_customers_fields')
        ->where('customer_id', (int)$customerId)
        ->where('field_name', $fieldName)
        ->find_one();

    if (!$row) {
        $row = ORM::for_table('tbl_customers_fields')->create();
        $row->customer_id = (int)$customerId;
        $row->field_name = $fieldName;
    }

    $row->field_value = (string)$value;
    $row->save();
}

/**
 * Generate credentials only when they do not already exist.
 */
function genieacs_customer_wifi_prepare($customerId, $fullname)
{
    $ssid24 = genieacs_customer_field_get($customerId, 'WiFi SSID 2.4GHz');
    $ssid5 = genieacs_customer_field_get($customerId, 'WiFi SSID 5GHz');
    $password = genieacs_customer_field_get($customerId, 'WiFi Password');

    if ($ssid24 === '') {
        $ssid24 = genieacs_generate_wifi_ssid($fullname, '2g');
        genieacs_customer_field_set($customerId, 'WiFi SSID 2.4GHz', $ssid24);
    }

    if ($ssid5 === '') {
        $ssid5 = genieacs_generate_wifi_ssid($fullname, '5g');
        genieacs_customer_field_set($customerId, 'WiFi SSID 5GHz', $ssid5);
    }

    if ($password === '') {
        $password = genieacs_generate_wifi_password($fullname);
        genieacs_customer_field_set($customerId, 'WiFi Password', $password);
    }

    return [
        'ssid24' => $ssid24,
        'ssid5' => $ssid5,
        'password' => $password
    ];
}


/**
 * Automatically save the GenieACS Device ID
 * into the BILLSYS customer custom fields.
 */
function genieacs_customer_device_id_set($customerId, $deviceId)
{
    $db = ORM::get_db();

    $stmt = $db->prepare("
        SELECT id, field_value
        FROM tbl_customers_fields
        WHERE customer_id = :customer_id
          AND field_name = 'Device ID'
        ORDER BY id ASC
        LIMIT 1
    ");

    $stmt->execute([
        ':customer_id' => (int)$customerId
    ]);

    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        if ((string)$existing['field_value'] !== (string)$deviceId) {
            $update = $db->prepare("
                UPDATE tbl_customers_fields
                SET field_value = :device_id
                WHERE id = :id
            ");

            $update->execute([
                ':device_id' => $deviceId,
                ':id' => (int)$existing['id']
            ]);
        }

        return;
    }

    $insert = $db->prepare("
        INSERT INTO tbl_customers_fields
            (customer_id, field_name, field_value)
        VALUES
            (:customer_id, 'Device ID', :device_id)
    ");

    $insert->execute([
        ':customer_id' => (int)$customerId,
        ':device_id' => $deviceId
    ]);
}

function genieacs()
{
    global $ui;
    genieacs_ensure_tables();
    genieacs_guard(getUrl('plugin/genieacs'), false);
    $cfg = genieacs_config_get(); $devices = []; $error = '';
    if ($cfg['genieacs_nbi_url'] !== '') {
        try { $devices = genieacs_devices_fetch(); } catch (Throwable $e) { $error = $e->getMessage(); }
    }
    // Automatically bind only devices whose PPPoE username matches one BILLSYS customer.
    // Unmatched and duplicate usernames are hidden from this inventory.
    $visibleDevices = [];
    if ($devices) {
        try {
            $customerMap = genieacs_customers_by_pppoe();

            foreach ($devices as $d) {
                $id = trim((string)($d['_id'] ?? ''));
                $pppoe = genieacs_pppoe_username($d);
                $lookup = strtolower($pppoe);

                if ($id === '' || $lookup === '' || !isset($customerMap[$lookup])) {
                    continue;
                }

                $customerId = (int)$customerMap[$lookup]['id'];

                // Save actual GenieACS Device ID to BILLSYS customer fields.
                genieacs_customer_device_id_set($customerId, $id);

                // Keep existing ONU mapping for WiFi management and reboot.
                genieacs_link_set($id, $customerId);

                $d['_customer_id'] = (string)$customerId;
                $d['_customer_name'] = trim((string)$customerMap[$lookup]['name']) ?: ('Customer #' . $customerId);
                $wifiCredentials = genieacs_customer_wifi_prepare(
                    $customerId,
                    $d['_customer_name']
                );

                $d['_generated_ssid24'] = $wifiCredentials['ssid24'];
                $d['_generated_ssid5'] = $wifiCredentials['ssid5'];
                $d['_generated_wifi_password'] = $wifiCredentials['password'];
                $d['_pppoe'] = $pppoe;
                $d['_manufacturer'] = genieacs_value($d, 'InternetGatewayDevice.DeviceInfo.Manufacturer');
                $d['_model'] = genieacs_value($d, 'InternetGatewayDevice.DeviceInfo.ModelName');
                // Dual-band mapping: 2.4 GHz is WLANConfiguration.1;
                // the user's 5 GHz radio is WLANConfiguration.5 (not .2).
                $d['_ssid1'] = genieacs_value($d, 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSID');
                $d['_ssid2'] = genieacs_value($d, 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.5.SSID');
                // Fetch the password parameters for device completeness, but never expose plaintext passwords in the inventory UI.
                $d['_wifi24_password_set'] = genieacs_value(
                    $d,
                    'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.KeyPassphrase'
                ) !== '';

                $d['_wifi5_password_set'] = genieacs_value(
                    $d,
                    'InternetGatewayDevice.LANDevice.1.WLANConfiguration.5.KeyPassphrase'
                ) !== '';
                $vpClients = trim(genieacs_value($d, 'VirtualParameters.WifiClients'));
                $d['_wifi_clients'] = ($vpClients !== '' && is_numeric($vpClients))
                    ? (int)$vpClients
                    : genieacs_wifi_clients_count($d); // fallback if the virtual parameter has no value yet

                $rx = genieacs_optical_rx($d);
                $rxStatus = genieacs_optical_rx_status($rx);
                $d['_rx_power'] = $rxStatus['value'];
                $d['_rx_class'] = $rxStatus['class'];

                $visibleDevices[] = $d;
            }
        } catch (Throwable $e) {
            $error = 'Automatic PPPoE binding failed: ' . $e->getMessage();
            $visibleDevices = [];
        }
    }
    $devices = $visibleDevices;
    $ui->assign('_admin', Admin::_info());
    $ui->assign('_system_menu', 'plugin/genieacs');
    $ui->assign('_title', 'GENIESYS - ONU Management');
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
    genieacs_ensure_tables();
    $back=getUrl('plugin/genieacs'); genieacs_guard($back, true);
    // Accept the normal form fields plus legacy aliases, and normalize whitespace.
    $id = trim((string)($_POST['device_id'] ?? $_POST['onu_device_id'] ?? ''));
    $cidRaw = trim((string)($_POST['customer_id'] ?? $_POST['cid'] ?? ''));

    // Distinguish a missing ONU ID from an invalid customer number.
    if ($id === '') {
        r2($back, 'e', 'ONU device ID is missing. Click Assign on the ONU row again and retry.');
    }
    if ($cidRaw === '' || !preg_match('/^[0-9]+$/', $cidRaw) || (int)$cidRaw < 1) {
        r2($back, 'e', 'Invalid BILLSYS Customer ID. Enter the numeric customer ID (for example: 123).');
    }
    $cid = (int)$cidRaw;
    try {
        $items=genieacs_nbi_request('GET','/devices/',null,['query'=>json_encode(['_id'=>$id]),'projection'=>'_id']);
        if (empty($items[0]['_id'])) r2($back,'e','Device not found in GenieACS.');
    } catch (Throwable $e) { r2($back,'e',$e->getMessage()); }
    // One ONU may belong to only one customer; one customer may have many ONUs.
    genieacs_link_set($id, (int)$cid);
    r2($back,'s','ONU assigned to customer #'.$cid.'.');
}
function genieacs_unassign()
{
    genieacs_ensure_tables();
    $back=getUrl('plugin/genieacs'); genieacs_guard($back,true);
    $id=trim((string)($_POST['device_id'] ?? ''));
    if ($id === '') r2($back,'e','Select a device.');
    genieacs_link_delete($id);
    r2($back,'s','ONU assignment removed.');
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


    $index = $band === '2g' ? '1' : '5';
    $params = [];

    // Update SSID if supplied.
    if ($ssid !== '') {
        $params[] = [
            "InternetGatewayDevice.LANDevice.1.WLANConfiguration.$index.SSID",
            $ssid,
            'xsd:string'
        ];
    }

    // Automatically detect the correct password parameter.
    if ($password !== '') {

        if (strlen($password) < 8 || strlen($password) > 63) {
            r2($back, 'e', 'WiFi password must be 8 to 63 characters.');
        }

        // Fetch the exact ONU and inspect its supported parameters.
        $devices = genieacs_nbi_request(
            'GET',
            '/devices/',
            null,
            [
                'query' => json_encode(['_id' => $id]),
                'projection' => implode(',', [
                    '_id',
                    "InternetGatewayDevice.LANDevice.1.WLANConfiguration.$index.PreSharedKey.1.KeyPassphrase",
                    "InternetGatewayDevice.LANDevice.1.WLANConfiguration.$index.KeyPassphrase"
                ])
            ]
        );

        $device = $devices[0] ?? [];

        if (empty($device['_id'])) {
            r2($back, 'e', 'ONU not found in GenieACS.');
        }

        $passwordPath = genieacs_wifi_password_path($device, $index);

        if ($passwordPath === '') {
            r2($back, 'e', 'No supported WiFi password parameter found for this ONU.');
        }

        $params[] = [
            $passwordPath,
            $password,
            'xsd:string'
        ];
    }

    if (!$params) {
        r2($back, 'e', 'Enter an SSID or password.');
    }

    try {
        $result=genieacs_nbi_request('POST','/devices/'.rawurlencode($id).'/tasks',['name'=>'setParameterValues','parameterValues'=>$params],['timeout'=>30000,'connection_request'=>'']);
        r2($back,'s','Task submitted: '.(string)($result['_id'] ?? 'queued').'. Check task execution before reporting success.');
    } catch (Throwable $e) { r2($back,'e',$e->getMessage()); }
}

function genieacs_autowifi()
{
    $back = getUrl('plugin/genieacs');
    genieacs_guard($back, true);

    $id = trim((string)($_POST['device_id'] ?? ''));
    if ($id === '') r2($back, 'e', 'Select a device.');

    $links = genieacs_links_get();
    if (!isset($links[$id])) r2($back, 'e', 'Assign this ONU to a BILLSYS customer before managing it.');

    $customerId = (int)$links[$id]['customer_id'];
    $customer = ORM::for_table('tbl_customers')->find_one($customerId);
    if (!$customer) r2($back, 'e', 'Customer not found.');

    try {
        // Reads saved credentials, or generates and saves them if missing.
        $creds = genieacs_customer_wifi_prepare($customerId, trim((string)$customer->fullname));

        if (strlen($creds['password']) < 8 || strlen($creds['password']) > 63) {
            r2($back, 'e', 'Generated WiFi password must be 8 to 63 characters. Edit the customer\'s "WiFi Password" field first.');
        }

        $wlan = 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.';
        $devices = genieacs_nbi_request('GET', '/devices/', null, [
            'query' => json_encode(['_id' => $id]),
            'projection' => implode(',', [
                '_id',
                $wlan . '1.SSID', $wlan . '1.KeyPassphrase', $wlan . '1.PreSharedKey.1.KeyPassphrase',
                $wlan . '5.SSID', $wlan . '5.KeyPassphrase', $wlan . '5.PreSharedKey.1.KeyPassphrase',
            ])
        ]);
        $device = $devices[0] ?? [];
        if (empty($device['_id'])) r2($back, 'e', 'ONU not found in GenieACS.');

        $bands = [
            '1' => $creds['ssid24'],   // 2.4 GHz
            '5' => $creds['ssid5'],    // 5 GHz
        ];

        $params = [];
        foreach ($bands as $index => $ssid) {
            if (!genieacs_parameter_exists($device, $wlan . $index . '.SSID')) {
                continue; // this radio does not exist on this ONU
            }
            $params[] = [$wlan . $index . '.SSID', $ssid, 'xsd:string'];

            $passPath = genieacs_wifi_password_path($device, $index);
            if ($passPath !== '') {
                $params[] = [$passPath, $creds['password'], 'xsd:string'];
            }
        }

        if (!$params) r2($back, 'e', 'No supported WiFi parameters found on this ONU.');

        $result = genieacs_nbi_request('POST', '/devices/' . rawurlencode($id) . '/tasks',
            ['name' => 'setParameterValues', 'parameterValues' => $params],
            ['timeout' => 30000, 'connection_request' => '']);

        r2($back, 's', 'Auto WiFi task submitted: ' . (string)($result['_id'] ?? 'queued') . '. Check task execution before reporting success.');
    } catch (Throwable $e) {
        r2($back, 'e', $e->getMessage());
    }
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
