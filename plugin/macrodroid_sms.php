<?php

/**
 * MacroDroid SMS Gateway
 * PHPNuxBill Plugin
 */

/*
 * Register menu
 */
register_menu(
    "MacroDroid SMS",
    true,
    "macrodroid_sms_config",
    'SETTINGS',
    '',
    '',
    ""
);

/*
 * Register SMS hook
 */
register_hook('send_sms', 'macrodroid_sms_send');


/**
 * MacroDroid SMS Hook
 *
 * PHPNuxBill calls:
 *
 * run_hook('send_sms', [$phone, $txt]);
 *
 * Hookers.php passes that array as ONE argument.
 */
function macrodroid_sms_send($args)
{
    global $config;

    /*
     * Get phone and message
     */
    $phone = $args[0] ?? '';
    $txt   = $args[1] ?? '';

    $phone = trim((string)$phone);
    $txt   = trim((string)$txt);

    /*
     * Debug log
     */
    file_put_contents(
        '/tmp/macrodroid_sms.log',
        date('Y-m-d H:i:s') .
        " | HOOK FIRED" .
        " | PHONE=" . $phone .
        " | MESSAGE=" . $txt .
        PHP_EOL,
        FILE_APPEND
    );

    /*
     * Get MacroDroid webhook
     */
    $webhook = $config['macrodroid_sms_webhook'] ?? '';

    /*
     * Fallback to database
     */
    if (empty($webhook)) {

        $setting = ORM::for_table('tbl_appconfig')
            ->where('setting', 'macrodroid_sms_webhook')
            ->find_one();

        if ($setting) {
            $webhook = trim($setting['value']);
        }
    }

    /*
     * Webhook missing
     */
    if (empty($webhook)) {

        file_put_contents(
            '/tmp/macrodroid_sms.log',
            date('Y-m-d H:i:s') .
            " | ERROR: WEBHOOK EMPTY" .
            PHP_EOL,
            FILE_APPEND
        );

        return;
    }

    /*
     * Phone missing
     */
    if (empty($phone)) {

        file_put_contents(
            '/tmp/macrodroid_sms.log',
            date('Y-m-d H:i:s') .
            " | ERROR: PHONE EMPTY" .
            PHP_EOL,
            FILE_APPEND
        );

        return;
    }

    /*
     * Build MacroDroid URL
     *
     * phone = customer phone
     * message = SMS text
     */
    $separator = (strpos($webhook, '?') !== false)
        ? '&'
        : '?';

    $url = $webhook .
        $separator .
        'phone=' . urlencode($phone) .
        '&message=' . urlencode($txt);

    /*
     * Log URL
     */
    file_put_contents(
        '/tmp/macrodroid_sms.log',
        date('Y-m-d H:i:s') .
        " | URL=" . $url .
        PHP_EOL,
        FILE_APPEND
    );

    /*
     * CURL
     */
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

    $response = curl_exec($ch);

    $http_code = curl_getinfo(
        $ch,
        CURLINFO_HTTP_CODE
    );

    $curl_error = curl_error($ch);

    curl_close($ch);

    /*
     * Log response
     */
    file_put_contents(
        '/tmp/macrodroid_sms.log',
        date('Y-m-d H:i:s') .
        " | HTTP=" . $http_code .
        " | RESPONSE=" . $response .
        " | CURL_ERROR=" . $curl_error .
        PHP_EOL,
        FILE_APPEND
    );

    /*
     * Do not return an error to PHPNuxBill.
     *
     * MacroDroid can still execute even if its webhook
     * returns an unexpected HTTP response.
     */
}


/**
 * MacroDroid Settings Page
 */
function macrodroid_sms_config()
{
    global $ui, $config;

    _admin();

    $admin = Admin::_info();

    /*
     * Permission
     */
    if (!in_array($admin['user_type'], ['SuperAdmin', 'Admin'])) {

        _alert(
            Lang::T('You do not have permission to access this page'),
            'danger',
            'dashboard'
        );

        exit;
    }


    /*
     * POST
     */
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {


        /*
         * SAVE WEBHOOK
         */
        if (isset($_POST['save'])) {

            $webhook = trim(
                $_POST['webhook'] ?? ''
            );

            if (empty($webhook)) {

                r2(
                    getUrl('plugin/macrodroid_sms_config'),
                    'e',
                    'MacroDroid Webhook URL is required'
                );

                exit;
            }


            /*
             * Save database setting
             */
            $setting = ORM::for_table('tbl_appconfig')
                ->where(
                    'setting',
                    'macrodroid_sms_webhook'
                )
                ->find_one();


            if ($setting) {

                $setting->value = $webhook;
                $setting->save();

            } else {

                $setting = ORM::for_table(
                    'tbl_appconfig'
                )->create();

                $setting->setting =
                    'macrodroid_sms_webhook';

                $setting->value = $webhook;

                $setting->save();
            }


            r2(
                getUrl('plugin/macrodroid_sms_config'),
                's',
                'MacroDroid settings saved successfully'
            );

            exit;
        }


        /*
         * TEST MACRODROID
         */
        if (isset($_POST['test_sms'])) {

            $webhook = trim(
                $_POST['webhook'] ?? ''
            );

            $test_phone = trim(
                $_POST['test_phone'] ?? ''
            );

            if (empty($webhook)) {

                r2(
                    getUrl('plugin/macrodroid_sms_config'),
                    'e',
                    'MacroDroid Webhook URL is required'
                );

                exit;
            }

            if (empty($test_phone)) {

                r2(
                    getUrl('plugin/macrodroid_sms_config'),
                    'e',
                    'Test phone number is required'
                );

                exit;
            }


            /*
             * Test request
             */
            $separator = (
                strpos($webhook, '?') !== false
            )
                ? '&'
                : '?';

            $url = $webhook .
                $separator .
                'phone=' .
                urlencode($test_phone) .
                '&message=' .
                urlencode(
                    'Billsys MacroDroid test SMS'
                );


            $ch = curl_init();

            curl_setopt(
                $ch,
                CURLOPT_URL,
                $url
            );

            curl_setopt(
                $ch,
                CURLOPT_RETURNTRANSFER,
                true
            );

            curl_setopt(
                $ch,
                CURLOPT_TIMEOUT,
                15
            );

            curl_setopt(
                $ch,
                CURLOPT_CONNECTTIMEOUT,
                10
            );

            curl_setopt(
                $ch,
                CURLOPT_SSL_VERIFYPEER,
                true
            );

            curl_setopt(
                $ch,
                CURLOPT_SSL_VERIFYHOST,
                2
            );


            $response = curl_exec($ch);

            $http_code = curl_getinfo(
                $ch,
                CURLINFO_HTTP_CODE
            );

            $curl_error = curl_error($ch);

            curl_close($ch);


            /*
             * Log test
             */
            file_put_contents(
                '/tmp/macrodroid_sms.log',
                date('Y-m-d H:i:s') .
                " | TEST" .
                " | PHONE=" . $test_phone .
                " | HTTP=" . $http_code .
                " | RESPONSE=" . $response .
                " | ERROR=" . $curl_error .
                PHP_EOL,
                FILE_APPEND
            );


            if (!empty($curl_error)) {

                r2(
                    getUrl(
                        'plugin/macrodroid_sms_config'
                    ),
                    'e',
                    'MacroDroid connection failed: ' .
                    $curl_error
                );

                exit;
            }


            /*
             * Treat request as sent if we received
             * any HTTP response.
             */
            if ($http_code > 0) {

                r2(
                    getUrl(
                        'plugin/macrodroid_sms_config'
                    ),
                    's',
                    'Test request sent to MacroDroid for ' .
                    $test_phone .
                    ' (HTTP ' .
                    $http_code .
                    ')'
                );

                exit;
            }


            r2(
                getUrl(
                    'plugin/macrodroid_sms_config'
                ),
                'e',
                'MacroDroid request failed'
            );

            exit;
        }
    }


    /*
     * Get saved webhook
     */
    $webhook =
        $config['macrodroid_sms_webhook'] ?? '';


    if (empty($webhook)) {

        $setting = ORM::for_table(
            'tbl_appconfig'
        )
            ->where(
                'setting',
                'macrodroid_sms_webhook'
            )
            ->find_one();

        if ($setting) {
            $webhook = trim(
                $setting['value']
            );
        }
    }


    /*
     * Smarty
     */
    $ui->assign(
        '_title',
        'MacroDroid SMS Gateway'
    );

    $ui->assign(
        '_system_menu',
        ''
    );

    $ui->assign(
        '_admin',
        $admin
    );

    $ui->assign(
        'webhook',
        $webhook
    );


    $ui->display(
        'macrodroid_sms.tpl'
    );
}
