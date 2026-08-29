{include file="sections/header.tpl"}

<div class="row">

    <div class="col-md-8">

        <div class="panel panel-primary">

            <div class="panel-heading">
                <i class="glyphicon glyphicon-phone"></i>
                MacroDroid SMS Gateway
            </div>

            <div class="panel-body">

                <!-- SAVE SETTINGS -->

                <form method="post"
                      action="{Text::url('plugin/macrodroid_sms_config')}">

                    <div class="form-group">

                        <label>MacroDroid Webhook URL</label>

                        <input
                            type="url"
                            class="form-control"
                            name="webhook"
                            value="{$webhook|escape}"
                            placeholder="https://trigger.macrodroid.com/..."
                            required
                        >

                    </div>

                    <button
                        type="submit"
                        name="save"
                        value="1"
                        class="btn btn-primary">

                        <i class="glyphicon glyphicon-save"></i>
                        Save Settings

                    </button>

                </form>

                <hr>

                <!-- TEST SMS -->

                <h4>
                    <i class="glyphicon glyphicon-send"></i>
                    Test MacroDroid
                </h4>

                <p class="text-muted">
                    This sends the phone number to your MacroDroid webhook.
                    Your MacroDroid global variable is:
                    <strong>phone</strong>
                </p>

                <form method="post"
                      action="{Text::url('plugin/macrodroid_sms_config')}">

                    <input
                        type="hidden"
                        name="webhook"
                        value="{$webhook|escape}"
                    >

                    <div class="form-group">

                        <label>Test Phone Number</label>

                        <input
                            type="text"
                            class="form-control"
                            name="test_phone"
                            placeholder="09171234567"
                            value="09171234567"
                            required
                        >

                    </div>

                    <button
                        type="submit"
                        name="test_sms"
                        value="1"
                        class="btn btn-success">

                        <i class="glyphicon glyphicon-send"></i>
                        Test SMS
                    </button>

                </form>

                <hr>

                <div class="alert alert-info">

                    <strong>MacroDroid variable:</strong>
                    <code>phone</code>

                    <br><br>

                    Example request:

                    <br>

                    <code>
                    ?phone=09171234567
                    </code>

                </div>

            </div>

        </div>

    </div>

</div>

{include file="sections/footer.tpl"}
