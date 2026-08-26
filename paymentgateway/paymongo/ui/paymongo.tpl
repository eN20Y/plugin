{include file="sections/header.tpl"}

<form class="form-horizontal"
      method="post"
      role="form"
      action="{$_url}paymentgateway/paymongo">

    <div class="row">
        <div class="col-sm-12 col-md-12">

            <div class="panel panel-primary panel-hovered panel-stacked mb30">

                <div class="panel-heading">
                    PAYMONGO
                </div>

                <div class="panel-body">

                    <div class="alert alert-info">
                        <strong>Test Mode</strong><br>
                        Use your PayMongo <code>sk_test_...</code>
                        secret key while testing.
                    </div>

                    <div class="form-group">

                        <label class="col-md-2 control-label">
                            Secret Key
                        </label>

                        <div class="col-md-6">

                            <input type="text"
                                   class="form-control"
                                   name="paymongo_secret_key"
                                   placeholder="sk_test_..."
                                   value="{$_c['paymongo_secret_key']|default:''}">

                            <a href="https://dashboard.paymongo.com/developers"
                               target="_blank"
                               class="help-block">
                                PayMongo Developer Dashboard
                            </a>

                        </div>

                    </div>


                    <div class="form-group">

                        <label class="col-md-2 control-label">
                            Webhook Secret
                        </label>

                        <div class="col-md-6">

                            <input type="text"
                                   class="form-control"
                                   name="paymongo_webhook_secret"
                                   placeholder="Webhook signing secret"
                                   value="{$_c['paymongo_webhook_secret']|default:''}">

                            <span class="help-block">
                                Optional during initial testing.
                            </span>

                        </div>

                    </div>


                    <div class="form-group">

                        <label class="col-md-2 control-label">
                            Payment Channels
                        </label>

                        <div class="col-md-8">

                            <label class="checkbox-inline">
                                <input type="checkbox"
                                       name="paymongo_channels[]"
                                       value="gcash"
                                       {if strpos($_c['paymongo_channels']|default:'','gcash') !== false}checked{/if}>
                                GCash
                            </label>

                            <label class="checkbox-inline">
                                <input type="checkbox"
                                       name="paymongo_channels[]"
                                       value="paymaya"
                                       {if strpos($_c['paymongo_channels']|default:'','paymaya') !== false}checked{/if}>
                                Maya
                            </label>

                            <label class="checkbox-inline">
                                <input type="checkbox"
                                       name="paymongo_channels[]"
                                       value="qrph"
                                       {if strpos($_c['paymongo_channels']|default:'','qrph') !== false}checked{/if}>
                                QRPh
                            </label>

                            <label class="checkbox-inline">
                                <input type="checkbox"
                                       name="paymongo_channels[]"
                                       value="card"
                                       {if strpos($_c['paymongo_channels']|default:'','card') !== false}checked{/if}>
                                Card
                            </label>

                        </div>

                    </div>


                    <div class="form-group">

                        <label class="col-md-2 control-label">
                            Webhook URL
                        </label>

                        <div class="col-md-6">

                            <input type="text"
                                   readonly
                                   class="form-control"
                                   onclick="this.select()"
                                   value="{$_url}callback/paymongo">

                        </div>

                    </div>


                    <div class="form-group">

                        <div class="col-lg-offset-2 col-lg-10">

                            <button class="btn btn-primary"
                                    type="submit">

                                <i class="glyphicon glyphicon-save"></i>
                                Save Change

                            </button>

                        </div>

                    </div>


                    <pre>/ip hotspot walled-garden
add dst-host=paymongo.com
add dst-host=*.paymongo.com</pre>

                </div>

            </div>

        </div>
    </div>

</form>

{include file="sections/footer.tpl"}
