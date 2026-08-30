{include file="admin/header.tpl"}

<div class="row">

    <div class="col-md-12">

        <div class="box box-primary">

            <div class="box-header with-border">

                <h3 class="box-title">

                    <i class="glyphicon glyphicon-bell"></i>

                    Payment Reminder

                </h3>

            </div>


            {if $save_success}

                <div class="alert alert-success"
                     style="margin:15px;">

                    <i class="glyphicon glyphicon-ok"></i>

                    Settings saved successfully.

                </div>

            {/if}


            <form method="post">

                <input
                    type="hidden"
                    name="save"
                    value="1"
                >


                <div class="box-body">


                    <!-- ==================================================
                         GENERAL
                    =================================================== -->

                    <h4>
                        <strong>GENERAL</strong>
                    </h4>

                    <hr>


                    <div class="form-group">

                        <label>

                            <input
                                type="checkbox"
                                name="payment_reminder_enabled"
                                value="yes"

                                {if $settings.payment_reminder_enabled == 'yes'}
                                    checked
                                {/if}
                            >

                            Enable Payment Reminder

                        </label>

                        <p class="help-block">

                            Enable or disable the payment reminder
                            for customers.

                        </p>

                    </div>


                    <!-- ==================================================
                         TEMPLATE
                    =================================================== -->

                    <h4>
                        <strong>TEMPLATE</strong>
                    </h4>

                    <hr>


                    <div class="form-group">

                        <label>
                            Reminder Template
                        </label>


<select
    name="payment_reminder_template"
    class="form-control"
>

    <option
        value="default"

        {if $settings.payment_reminder_template == 'default'}
            selected
        {/if}
    >
        Default
    </option>


    <option
        value="violet"

        {if $settings.payment_reminder_template == 'violet'}
            selected
        {/if}
    >
        Violet + Yellow
    </option>


    <option
        value="violet_gold"

        {if $settings.payment_reminder_template == 'violet_gold'}
            selected
        {/if}
    >
        Violet + Gold
    </option>
    <option
        value="black_yellow"

        {if $settings.payment_reminder_template == 'black_yellow'}
            selected
        {/if}
    >
        Black + Yellow
    </option>
</select>


                        <p class="help-block">

                            Select the design used by the
                            payment reminder.

                        </p>

                    </div>


                    <!-- ==================================================
                         MESSAGE
                    =================================================== -->

                    <h4>
                        <strong>MESSAGE</strong>
                    </h4>

                    <hr>


                    <div class="form-group">

                        <label>
                            Due Message
                        </label>


                        <textarea
                            name="payment_reminder_message"
                            class="form-control"
                            rows="6"
                        >{$settings.payment_reminder_message|escape}</textarea>


                        <p class="help-block">

                            Available variables:

                            <br>

                            <code>{literal}{fullname}{/literal}</code>

                            <code>{literal}{username}{/literal}</code>

                            <code>{literal}{plan}{/literal}</code>

                            <code>{literal}{amount}{/literal}</code>

                            <code>{literal}{due_date}{/literal}</code>

                            <code>{literal}{days_before}{/literal}</code>

                            <code>{literal}{days_overdue}{/literal}</code>

                            <code>{literal}{service_type}{/literal}</code>

                            <code>{literal}{billing_type}{/literal}</code>

                        </p>

                    </div>


                </div>

<!-- ==================================================
     PAYMENT METHODS
=================================================== -->

<h4 style="margin-top:30px;">
    <strong>PAYMENT METHODS</strong>
</h4>

<hr>

<div id="payment-methods-container">

    {if $settings.payment_methods|@count > 0}

        {foreach $settings.payment_methods as $method}

            <div class="payment-method-row"
                 style="
                    border:1px solid #ddd;
                    border-radius:5px;
                    padding:15px;
                    margin-bottom:15px;
                    background:#fafafa;
                 ">

                <div class="row">

                    <div class="col-md-4">

                        <div class="form-group">

                            <label>Method Name</label>

                            <input
                                type="text"
                                name="payment_method_name[]"
                                class="form-control"
                                value="{$method.name|escape}"
                                placeholder="GCash"
                            >

                        </div>

                    </div>


                    <div class="col-md-3">

                        <div class="form-group">

                            <label>Account Number</label>

                            <input
                                type="text"
                                name="payment_account_number[]"
                                class="form-control"
                                value="{$method.account_number|escape}"
                                placeholder="09XXXXXXXXX"
                            >

                        </div>

                    </div>


                    <div class="col-md-4">

                        <div class="form-group">

                            <label>Account Name</label>

                            <input
                                type="text"
                                name="payment_account_name[]"
                                class="form-control"
                                value="{$method.account_name|escape}"
                                placeholder="Juan Dela Cruz"
                            >

                        </div>

                    </div>


                    <div class="col-md-1">

                        <label>&nbsp;</label>

                        <button
                            type="button"
                            class="btn btn-danger remove-payment-method"
                            style="width:100%;"
                        >

                            <i class="glyphicon glyphicon-trash"></i>

                        </button>

                    </div>

                </div>

            </div>

        {/foreach}

    {/if}

</div>


<button
    type="button"
    id="add-payment-method"
    class="btn btn-default"
>

    <i class="glyphicon glyphicon-plus"></i>

    Add Payment Method

</button>


<p class="help-block">
    Add GCash, Maya, bank accounts, or other payment methods.
</p>
                <!-- ======================================================
                     SAVE
                ======================================================= -->

                <div class="box-footer">

                    <button
                        type="submit"
                        name="save"
                        value="1"
                        class="btn btn-primary"
                    >

                        <i class="glyphicon glyphicon-save"></i>

                        Save Settings

                    </button>

                </div>


            </form>

        </div>

    </div>

</div>

<script>

document.addEventListener('DOMContentLoaded', function () {

    var container = document.getElementById(
        'payment-methods-container'
    );

    var addButton = document.getElementById(
        'add-payment-method'
    );


    /*
    |--------------------------------------------------------------------------
    | ADD PAYMENT METHOD
    |--------------------------------------------------------------------------
    */

    if (addButton) {

        addButton.addEventListener(
            'click',
            function () {

                var row =
                    document.createElement('div');

                row.className =
                    'payment-method-row';

                row.style.border =
                    '1px solid #ddd';

                row.style.borderRadius =
                    '5px';

                row.style.padding =
                    '15px';

                row.style.marginBottom =
                    '15px';

                row.style.background =
                    '#fafafa';


                row.innerHTML =

                    '<div class="row">' +

                        '<div class="col-md-4">' +

                            '<div class="form-group">' +

                                '<label>Method Name</label>' +

                                '<input ' +
                                    'type="text" ' +
                                    'name="payment_method_name[]" ' +
                                    'class="form-control" ' +
                                    'placeholder="GCash">' +

                            '</div>' +

                        '</div>' +


                        '<div class="col-md-3">' +

                            '<div class="form-group">' +

                                '<label>Account Number</label>' +

                                '<input ' +
                                    'type="text" ' +
                                    'name="payment_account_number[]" ' +
                                    'class="form-control" ' +
                                    'placeholder="09XXXXXXXXX">' +

                            '</div>' +

                        '</div>' +


                        '<div class="col-md-4">' +

                            '<div class="form-group">' +

                                '<label>Account Name</label>' +

                                '<input ' +
                                    'type="text" ' +
                                    'name="payment_account_name[]" ' +
                                    'class="form-control" ' +
                                    'placeholder="Juan Dela Cruz">' +

                            '</div>' +

                        '</div>' +


                        '<div class="col-md-1">' +

                            '<label>&nbsp;</label>' +

                            '<button ' +
                                'type="button" ' +
                                'class="btn btn-danger remove-payment-method" ' +
                                'style="width:100%;">' +

                                '<i class="glyphicon glyphicon-trash"></i>' +

                            '</button>' +

                        '</div>' +

                    '</div>';


                container.appendChild(row);

            }
        );

    }


    /*
    |--------------------------------------------------------------------------
    | REMOVE PAYMENT METHOD
    |--------------------------------------------------------------------------
    */

    if (container) {

        container.addEventListener(
            'click',
            function (event) {

                var button =
                    event.target.closest(
                        '.remove-payment-method'
                    );


                if (!button) {
                    return;
                }


                var row =
                    button.closest(
                        '.payment-method-row'
                    );


                if (row) {

                    row.remove();

                }

            }
        );

    }

});

</script>
{include file="admin/footer.tpl"}
