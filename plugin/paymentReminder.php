<?php

register_menu(
    'Payment Reminder',
    true,
    'paymentReminder_config',
    'AFTER_SETTINGS',
    'glyphicon glyphicon-bell',
    '',
    '',
    ['SuperAdmin', 'Admin']
);


/*
|--------------------------------------------------------------------------
| PAYMENT REMINDER CONFIGURATION
|--------------------------------------------------------------------------
*/

function paymentReminder_config()
{
    global $ui, $admin;

    _admin();


    /*
    |--------------------------------------------------------------------------
    | DEFAULT SETTINGS
    |--------------------------------------------------------------------------
    */

    $defaults = [

        'payment_reminder_enabled' => 'yes',

        'payment_reminder_message' =>
            'Hello {fullname}. Your payment is due.',

        'payment_reminder_template' =>
            'default',

        'payment_reminder_payment_methods' =>
            '[]'
    ];


    /*
    |--------------------------------------------------------------------------
    | SAVE SETTINGS
    |--------------------------------------------------------------------------
    */

    $saved = false;


    if (_post('save', '') != '') {


        /*
        |--------------------------------------------------------------------------
        | ENABLED
        |--------------------------------------------------------------------------
        */

        $enabled = _post(
            'payment_reminder_enabled',
            'no'
        );


        /*
        |--------------------------------------------------------------------------
        | MESSAGE
        |--------------------------------------------------------------------------
        */

        $message = _post(
            'payment_reminder_message',
            $defaults['payment_reminder_message']
        );


        /*
        |--------------------------------------------------------------------------
        | TEMPLATE
        |--------------------------------------------------------------------------
        */

        $template = _post(
            'payment_reminder_template',
            $defaults['payment_reminder_template']
        );


        /*
        |--------------------------------------------------------------------------
        | PAYMENT METHODS
        |--------------------------------------------------------------------------
        */

        $paymentMethods = [];


        $methodNames = $_POST['payment_method_name'] ?? [];

        $accountNumbers = $_POST['payment_account_number'] ?? [];

        $accountNames = $_POST['payment_account_name'] ?? [];


        if (
            is_array($methodNames) &&
            is_array($accountNumbers) &&
            is_array($accountNames)
        ) {

            $count = count($methodNames);


            for ($i = 0; $i < $count; $i++) {


                $methodName = trim(
                    (string)($methodNames[$i] ?? '')
                );


                $accountNumber = trim(
                    (string)($accountNumbers[$i] ?? '')
                );


                $accountName = trim(
                    (string)($accountNames[$i] ?? '')
                );


                /*
                 * Skip completely empty rows.
                 */

                if (
                    $methodName === '' &&
                    $accountNumber === '' &&
                    $accountName === ''
                ) {
                    continue;
                }


                $paymentMethods[] = [

                    'name' =>
                        $methodName,

                    'account_number' =>
                        $accountNumber,

                    'account_name' =>
                        $accountName
                ];
            }
        }


        /*
        |--------------------------------------------------------------------------
        | Convert payment methods to JSON
        |--------------------------------------------------------------------------
        */

        $paymentMethodsJson = json_encode(
            $paymentMethods,
            JSON_UNESCAPED_UNICODE
        );


        if ($paymentMethodsJson === false) {

            $paymentMethodsJson = '[]';
        }


        /*
        |--------------------------------------------------------------------------
        | SAVE ENABLED
        |--------------------------------------------------------------------------
        */

        $row = ORM::for_table('tbl_appconfig')
            ->where(
                'setting',
                'payment_reminder_enabled'
            )
            ->find_one();


        if ($row) {

            $row->value = (string)$enabled;

            $row->save();

        } else {

            $row = ORM::for_table('tbl_appconfig')->create();

            $row->setting =
                'payment_reminder_enabled';

            $row->value =
                (string)$enabled;

            $row->save();
        }


        /*
        |--------------------------------------------------------------------------
        | SAVE MESSAGE
        |--------------------------------------------------------------------------
        */

        $row = ORM::for_table('tbl_appconfig')
            ->where(
                'setting',
                'payment_reminder_message'
            )
            ->find_one();


        if ($row) {

            $row->value =
                (string)$message;

            $row->save();

        } else {

            $row = ORM::for_table('tbl_appconfig')->create();

            $row->setting =
                'payment_reminder_message';

            $row->value =
                (string)$message;

            $row->save();
        }


        /*
        |--------------------------------------------------------------------------
        | SAVE TEMPLATE
        |--------------------------------------------------------------------------
        */

        $row = ORM::for_table('tbl_appconfig')
            ->where(
                'setting',
                'payment_reminder_template'
            )
            ->find_one();


        if ($row) {

            $row->value =
                (string)$template;

            $row->save();

        } else {

            $row = ORM::for_table('tbl_appconfig')->create();

            $row->setting =
                'payment_reminder_template';

            $row->value =
                (string)$template;

            $row->save();
        }


        /*
        |--------------------------------------------------------------------------
        | SAVE PAYMENT METHODS
        |--------------------------------------------------------------------------
        */

        $row = ORM::for_table('tbl_appconfig')
            ->where(
                'setting',
                'payment_reminder_payment_methods'
            )
            ->find_one();


        if ($row) {

            $row->value =
                (string)$paymentMethodsJson;

            $row->save();

        } else {

            $row = ORM::for_table('tbl_appconfig')->create();

            $row->setting =
                'payment_reminder_payment_methods';

            $row->value =
                (string)$paymentMethodsJson;

            $row->save();
        }


        $saved = true;
    }


    /*
    |--------------------------------------------------------------------------
    | LOAD SETTINGS
    |--------------------------------------------------------------------------
    */

    $settings = [];


    /*
    |--------------------------------------------------------------------------
    | ENABLED
    |--------------------------------------------------------------------------
    */

    $row = ORM::for_table('tbl_appconfig')
        ->where(
            'setting',
            'payment_reminder_enabled'
        )
        ->find_one();


    $settings['payment_reminder_enabled'] =
        $row
            ? (string)$row->value
            : $defaults['payment_reminder_enabled'];


    /*
    |--------------------------------------------------------------------------
    | MESSAGE
    |--------------------------------------------------------------------------
    */

    $row = ORM::for_table('tbl_appconfig')
        ->where(
            'setting',
            'payment_reminder_message'
        )
        ->find_one();


    $settings['payment_reminder_message'] =
        $row
            ? (string)$row->value
            : $defaults['payment_reminder_message'];


    /*
    |--------------------------------------------------------------------------
    | TEMPLATE
    |--------------------------------------------------------------------------
    */

    $row = ORM::for_table('tbl_appconfig')
        ->where(
            'setting',
            'payment_reminder_template'
        )
        ->find_one();


    $settings['payment_reminder_template'] =
        $row
            ? (string)$row->value
            : $defaults['payment_reminder_template'];


    /*
    |--------------------------------------------------------------------------
    | PAYMENT METHODS
    |--------------------------------------------------------------------------
    */

    $row = ORM::for_table('tbl_appconfig')
        ->where(
            'setting',
            'payment_reminder_payment_methods'
        )
        ->find_one();


    if ($row) {

        $paymentMethods = json_decode(
            (string)$row->value,
            true
        );

    } else {

        $paymentMethods = [];
    }


    /*
    |--------------------------------------------------------------------------
    | Make sure JSON is valid
    |--------------------------------------------------------------------------
    */

    if (!is_array($paymentMethods)) {

        $paymentMethods = [];
    }


    $settings['payment_methods'] =
        $paymentMethods;


    /*
    |--------------------------------------------------------------------------
    | SEND TO SMARTY
    |--------------------------------------------------------------------------
    */

    $ui->assign(
        'settings',
        $settings
    );


    $ui->assign(
        'save_success',
        $saved
    );


    $ui->assign(
        '_title',
        'Payment Reminder'
    );


    $ui->assign(
        '_admin',
        $admin
    );


    /*
    |--------------------------------------------------------------------------
    | DISPLAY
    |--------------------------------------------------------------------------
    */

    $ui->display(
        'paymentReminder_config.tpl'
    );
}
