<?php

/**
 * PHPNuxBill Payment Gateway - PayMongo
 * Test mode / Hosted Checkout
 */

function paymongo_validate_config()
{
    global $config;

    if (empty($config['paymongo_secret_key'])) {
        r2(U . 'order/package', 'w', Lang::T('Admin has not yet setup PayMongo payment gateway, please tell admin'));
    }
}

function paymongo_show_config()
{
    global $ui;

    $ui->assign('_title', 'PayMongo - Payment Gateway');
    $ui->display('paymongo.tpl');
}

function paymongo_save_config()
{
    global $admin, $_L;

    $settings = [
        'paymongo_secret_key' => _post('paymongo_secret_key'),
        'paymongo_webhook_secret' => _post('paymongo_webhook_secret'),
        'paymongo_channels' => implode(',', $_POST['paymongo_channels'] ?? [])
    ];

    foreach ($settings as $key => $value) {
        $d = ORM::for_table('tbl_appconfig')
            ->where('setting', $key)
            ->find_one();

        if (!$d) {
            $d = ORM::for_table('tbl_appconfig')->create();
            $d->setting = $key;
        }

        $d->value = $value;
        $d->save();
    }

    _log(
        '[' . $admin['username'] . ']: PayMongo ' .
        $_L['Settings_Saved_Successfully'],
        'Admin',
        $admin['id']
    );

    r2(
        U . 'paymentgateway/paymongo',
        's',
        $_L['Settings_Saved_Successfully']
    );
}

function paymongo_create_transaction($trx, $user)
{
    global $config;

    paymongo_validate_config();

    $purpose = $trx['plan_name'];

    $bills = User::getBills($user['id']);

    if (!empty($bills[0])) {
        $purpose .= '| ' . implode(', ', array_keys($bills[0]));
    }

    $channels = array_filter(
        array_map(
            'trim',
            explode(',', $config['paymongo_channels'] ?? '')
        )
    );

    if (empty($channels)) {
        $channels = [
            'gcash',
            'paymaya',
            'qrph'
        ];
    }

    $amount = (int)round(((float)$trx['price']) * 100);

    $payload = [
        'data' => [
            'attributes' => [
                'line_items' => [
                    [
                        'currency' => 'PHP',
                        'amount' => $amount,
                        'name' => $purpose,
                        'quantity' => 1
                    ]
                ],
                'payment_method_types' => array_values($channels),
                'success_url' =>
                    U . 'order/view/' . $trx['id'] . '/check',
                'cancel_url' =>
                    U . 'order/view/' . $trx['id'] . '/check',
                'description' => $purpose,
                'send_email_receipt' => false,
                'show_description' => true
            ]
        ]
    ];

    $result = paymongo_request(
        'POST',
        'https://api.paymongo.com/v2/checkout_sessions',
        $payload,
        $config['paymongo_secret_key']
    );

    if (
        !$result ||
        empty($result['data']['id']) ||
        empty($result['data']['attributes']['checkout_url'])
    ) {
        Message::sendTelegram(
            'PayMongo create checkout failed: ' .
            json_encode($result, JSON_PRETTY_PRINT)
        );

        r2(
            U . 'order/package',
            'e',
            Lang::T('Failed to create PayMongo transaction.')
        );
    }

    $data = $result['data'];
    $attr = $data['attributes'];

    $d = ORM::for_table('tbl_payment_gateway')
        ->where('username', $user['username'])
        ->where('status', 1)
        ->find_one();

    if (!$d) {
        r2(
            U . 'order/package',
            'e',
            Lang::T('Payment transaction was not found.')
        );
    }

    $d->gateway_trx_id = $data['id'];
    $d->pg_url_payment = $attr['checkout_url'];
    $d->pg_request = json_encode($result);
    $d->expired_date = !empty($attr['expires_at'])
        ? date('Y-m-d H:i:s', $attr['expires_at'])
        : date('Y-m-d H:i:s', strtotime('+24 hours'));
    $d->save();

    header('Location: ' . $attr['checkout_url']);
    exit;
}

function paymongo_get_status($trx, $user)
{
    global $config;

    paymongo_validate_config();

    $result = paymongo_request(
        'GET',
        'https://api.paymongo.com/v1/checkout_sessions/' .
        rawurlencode($trx['gateway_trx_id']),
        null,
        $config['paymongo_secret_key']
    );

    if (!$result || empty($result['data'])) {

        _log(
            'PAYMONGO STATUS ERROR: ' .
            json_encode($result)
        );

        r2(
            U . 'order/view/' . $trx['id'],
            'e',
            Lang::T('Unable to check PayMongo transaction.')
        );
    }

    $attr = $result['data']['attributes'] ?? [];

    /*
     * PayMongo payment list
     */
    $payments = $attr['payments'] ?? [];

    $paidPayment = null;

    foreach ($payments as $payment) {

        $paymentStatus = strtolower(
            $payment['attributes']['status'] ?? ''
        );

        _log(
            'PAYMONGO PAYMENT STATUS: ' .
            $paymentStatus
        );

        if (
            $paymentStatus === 'paid' ||
            $paymentStatus === 'succeeded'
        ) {
            $paidPayment = $payment;
            break;
        }
    }

    /*
     * Also check Payment Intent
     */
    $intent = $attr['payment_intent']['attributes'] ?? [];

    $intentStatus = strtolower(
        $intent['status'] ?? ''
    );

    _log(
        'PAYMONGO PAYMENT INTENT STATUS: ' .
        $intentStatus
    );

    /*
     * Already paid
     */
    if ((int)$trx['status'] === 2) {

        r2(
            U . 'order/view/' . $trx['id'],
            's',
            Lang::T('Transaction has been paid.')
        );
    }

    /*
     * Payment confirmed
     */
    if (
        $paidPayment !== null ||
        $intentStatus === 'succeeded'
    ) {

        _log(
            'PAYMONGO PAYMENT CONFIRMED: ' .
            $trx['gateway_trx_id']
        );

        if (
            paymongo_mark_paid(
                $trx,
                $user,
                $result,
                $paidPayment
            )
        ) {

            _log(
                'PAYMONGO TRANSACTION MARKED PAID: ' .
                $trx['id']
            );

            r2(
                U . 'order/view/' . $trx['id'],
                's',
                Lang::T('Transaction has been paid.')
            );
        }

        r2(
            U . 'order/view/' . $trx['id'],
            'e',
            Lang::T('Payment was successful but activation failed.')
        );
    }

    _log(
        'PAYMONGO TRANSACTION STILL UNPAID: ' .
        $trx['gateway_trx_id']
    );

    r2(
        U . 'order/view/' . $trx['id'],
        'w',
        Lang::T('Transaction still unpaid.')
    );
}


/**
 * PayMongo webhook.
 */
function paymongo_payment_notification()
{
    global $config;

    header('Content-Type: application/json');

    $payload = file_get_contents('php://input');
    $signature = $_SERVER['HTTP_PAYMONGO_SIGNATURE'] ?? '';

    if ($payload === '') {
        http_response_code(400);
        die(json_encode([
            'status' => 'error',
            'message' => 'No payload received'
        ]));
    }

    if (
        !empty($config['paymongo_webhook_secret']) &&
        !paymongo_verify_webhook(
            $payload,
            $signature,
            $config['paymongo_webhook_secret']
        )
    ) {
        http_response_code(401);
        die(json_encode([
            'status' => 'error',
            'message' => 'Invalid webhook signature'
        ]));
    }

    $event = json_decode($payload, true);

    if (!is_array($event)) {
        http_response_code(400);
        die(json_encode([
            'status' => 'error',
            'message' => 'Invalid JSON'
        ]));
    }

    $type =
        $event['data']['attributes']['type'] ??
        $event['type'] ??
        '';

    if ($type !== 'checkout_session.payment.paid') {
        die(json_encode([
            'status' => 'ignored',
            'type' => $type
        ]));
    }

    $resource =
        $event['data']['attributes']['data'] ??
        $event['data']['attributes']['resource'] ??
        [];

    $checkoutId =
        $resource['id'] ??
        $resource['attributes']['checkout_session_id'] ??
        '';

    if (!$checkoutId) {
        die(json_encode([
            'status' => 'error',
            'message' => 'Checkout session ID not found'
        ]));
    }

    $trx = ORM::for_table('tbl_payment_gateway')
        ->where('gateway_trx_id', $checkoutId)
        ->find_one();

    if (!$trx) {
        http_response_code(404);
        die(json_encode([
            'status' => 'error',
            'message' => 'Transaction not found'
        ]));
    }

    if ($trx['status'] == 2) {
        die(json_encode([
            'status' => 'already_paid'
        ]));
    }

    $user = ORM::for_table('tbl_customers')
        ->where('username', $trx['username'])
        ->find_one();

    if (!$user) {
        http_response_code(404);
        die(json_encode([
            'status' => 'error',
            'message' => 'Customer not found'
        ]));
    }

    $result = paymongo_request(
        'GET',
        'https://api.paymongo.com/v1/checkout_sessions/' .
        rawurlencode($checkoutId),
        null,
        $config['paymongo_secret_key']
    );

    if (!$result) {
        http_response_code(502);
        die(json_encode([
            'status' => 'error',
            'message' => 'Unable to verify checkout session'
        ]));
    }

    $attr = $result['data']['attributes'] ?? [];
    $payments = $attr['payments'] ?? [];

    $paidPayment = null;

    foreach ($payments as $payment) {
        $status = strtolower(
            $payment['attributes']['status'] ?? ''
        );

        if (in_array($status, ['paid', 'succeeded'], true)) {
            $paidPayment = $payment;
            break;
        }
    }

    if (!$paidPayment) {
        die(json_encode([
            'status' => 'not_paid'
        ]));
    }

    if (
        paymongo_mark_paid(
            $trx,
            $user,
            $result,
            $paidPayment
        )
    ) {
        die(json_encode([
            'status' => 'paid'
        ]));
    }

    http_response_code(500);

    die(json_encode([
        'status' => 'error',
        'message' => 'Failed to activate package'
    ]));
}

function paymongo_mark_paid(
    $trx,
    $user,
    $result,
    $payment = null
) {
    if ($trx['status'] == 2) {
        return true;
    }

    $paymentAttr = is_array($payment)
        ? ($payment['attributes'] ?? [])
        : [];

    $paymentMethod = '';
    $paymentChannel = '';

    if (!empty($paymentAttr['source']['type'])) {
        $paymentChannel = $paymentAttr['source']['type'];
    }

    if (!empty($paymentAttr['payment_method']['type'])) {
        $paymentMethod = $paymentAttr['payment_method']['type'];
    }

    if (!$paymentMethod) {
        $paymentMethod = $paymentChannel;
    }

    if (!$paymentChannel) {
        $paymentChannel = $paymentMethod ?: 'paymongo';
    }

    if (!Package::rechargeUser(
        $user['id'],
        $trx['routers'],
        $trx['plan_id'],
        $trx['gateway'],
        $paymentChannel
    )) {
        Message::sendTelegram(
            'PayMongo activation FAILED: ' .
            json_encode($result, JSON_PRETTY_PRINT)
        );

        return false;
    }

    $trx->pg_paid_response = json_encode($result);
    $trx->payment_method = $paymentMethod;
    $trx->payment_channel = $paymentChannel;
    $trx->paid_date = date('Y-m-d H:i:s');
    $trx->status = 2;
    $trx->save();

    return true;
}

function paymongo_verify_webhook(
    $payload,
    $signature,
    $secret
) {
    if (!$signature || !$secret) {
        return false;
    }

    $parts = [];

    foreach (explode(',', $signature) as $part) {
        $pair = explode('=', trim($part), 2);

        if (count($pair) === 2) {
            $parts[$pair[0]] = $pair[1];
        }
    }

    $timestamp = $parts['t'] ?? '';
    $testSignature = $parts['te'] ?? '';
    $liveSignature = $parts['li'] ?? '';

    $provided = $testSignature ?: $liveSignature;

    if (!$timestamp || !$provided) {
        return false;
    }

    $signedPayload =
        $timestamp . '.' . $payload;

    $expected = hash_hmac(
        'sha256',
        $signedPayload,
        $secret
    );

    return hash_equals(
        $expected,
        $provided
    );
}

function paymongo_request($method, $url, $payload, $secret)
{
    if (!function_exists('curl_init')) {
        _log('PayMongo ERROR: cURL is not installed.');
        return false;
    }

    $ch = curl_init($url);

    $headers = [
        'Accept: application/json',
        'Content-Type: application/json',
        'Authorization: Basic ' . base64_encode($secret . ':')
    ];

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

    if ($payload !== null) {
        curl_setopt(
            $ch,
            CURLOPT_POSTFIELDS,
            json_encode($payload)
        );
    }

    $response = curl_exec($ch);
    $errno = curl_errno($ch);
    $error = curl_error($ch);

    $httpCode = curl_getinfo(
        $ch,
        CURLINFO_HTTP_CODE
    );

    curl_close($ch);

    if ($response === false) {
        _log(
            'PayMongo CURL ERROR: ' .
            $errno . ' - ' . $error
        );

        return false;
    }

    _log(
        'PayMongo API [' . $method . '] HTTP ' .
        $httpCode . ': ' . $response
    );

    $data = json_decode($response, true);

    if ($httpCode < 200 || $httpCode >= 300) {
        return false;
    }

    return is_array($data) ? $data : false;
}
