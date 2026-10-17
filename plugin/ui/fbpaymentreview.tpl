{include file="sections/header.tpl"}

<div class="row">
    <div class="col-md-12">
        <div class="panel panel-primary panel-hovered panel-stacked mb30">
            <div class="panel-heading">
                <div class="row">
                    <div class="col-sm-7">
                        <strong>Payment Verification</strong>
                        <div class="small">Review GCash, Maya and bank proof of payment photos submitted through Facebook Messenger.</div>
                    </div>
                    <div class="col-sm-5 text-right">
                        <div class="form-inline text-right" style="display:inline-block;">
                            <select class="form-control" name="status"
                                    onchange="window.location.href='{$_url}plugin/fbpaymentreview&status=' + encodeURIComponent(this.value);">
                                <option value="pending" {if $fbPaymentStatus eq 'pending'}selected{/if}>Pending</option>
                                <option value="approved" {if $fbPaymentStatus eq 'approved'}selected{/if}>Approved</option>
                                <option value="declined" {if $fbPaymentStatus eq 'declined'}selected{/if}>Declined</option>
                                <option value="all" {if $fbPaymentStatus eq 'all'}selected{/if}>All submissions</option>
                            </select>
                        </div>
                        {if $fbCanEditSettings}
                            <button type="button" class="btn btn-default" data-toggle="modal" data-target="#fbSettingsModal"
                                    title="Messenger Bot Settings" aria-label="Messenger Bot Settings">
                                <i class="ion ion-gear-a"></i>
                            </button>
                        {/if}
                    </div>
                </div>
            </div>

            <div class="panel-body">
                {if $fbPaymentNotice}
                    <div class="alert alert-success" role="alert">{$fbPaymentNotice|escape}</div>
                {/if}
                {if $fbPaymentError}
                    <div class="alert alert-danger" role="alert">{$fbPaymentError|escape}</div>
                {/if}

                <div class="alert alert-info">
                    Open the proof of payment and verify the payment in your actual GCash, Maya or bank records before approving. Approval records the review decision only; it does not automatically create a BILLSYS payment or recharge the customer.
                </div>

                {if $fbCanEditSettings}
                    <form method="post" action="{$_url}plugin/fbpaymentreview" class="form-inline" style="margin-bottom:15px;">
                        <input type="hidden" name="csrf_token" value="{$fbPaymentCsrf|escape}">
                        <input type="hidden" name="action" value="setup_menu">
                        <button type="submit" class="btn btn-default btn-sm"
                                onclick="return confirm('Apply the Get Started button and the Status / Pay / Help menu to your Facebook Page?');">
                            <i class="ion ion-navicon-round"></i> Apply Messenger menu
                        </button>
                        <small class="text-muted">Adds Get Started and the Status / Pay / Help menu. Run once, or again after changing the menu.</small>
                    </form>
                {/if}

                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead>
                            <tr>
                                <th>Customer</th>
                                <th>Payment Proof</th>
                                <th>Submitted</th>
                                <th>Status</th>
                                <th>Review Details</th>
                                <th style="min-width:260px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {if $fbPaymentSubmissions}
                                {foreach $fbPaymentSubmissions as $payment}
                                    <tr>
                                        <td>
                                            <strong>{$payment.fullname|default:'Customer'|escape}</strong><br>
                                            <small>Username: {$payment.username|default:'—'|escape}</small><br>
                                            <small>Customer ID: {$payment.customer_id|escape}</small>
                                        </td>
                                        <td>
                                            {if $payment.proof_image}
                                                <a href="#" class="fb-proof-link" data-toggle="modal" data-target="#fbProofModal"
                                                   data-src="{$_url}plugin/fbpaymentreview&proof={$payment.id|escape}" title="Click to enlarge">
                                                    <img src="{$_url}plugin/fbpaymentreview&proof={$payment.id|escape}" alt="Proof of payment"
                                                         style="max-width:90px; max-height:90px; border:1px solid #ddd; border-radius:3px;">
                                                </a><br>
                                            {elseif !$payment.transaction_id}
                                                <span class="text-muted">No photo</span><br>
                                            {/if}
                                            {if $payment.transaction_id}
                                                <code>{$payment.transaction_id|escape}</code><br>
                                            {/if}
                                            <small>{$payment.payment_method|escape} &middot; #{$payment.id|escape}</small>
                                        </td>
                                        <td>{$payment.submitted_at|escape}</td>
                                        <td>
                                            {if $payment.status eq 'pending'}
                                                <span class="label label-warning">Pending</span>
                                            {elseif $payment.status eq 'approved'}
                                                <span class="label label-success">Approved</span>
                                            {else}
                                                <span class="label label-danger">Declined</span>
                                            {/if}
                                            {if $payment.reviewed_at}
                                                <br><small>{$payment.reviewed_at|escape}</small>
                                            {/if}
                                            {if $payment.reviewed_by}
                                                <br><small>By: {$payment.reviewed_by|escape}</small>
                                            {/if}
                                        </td>
                                        <td>{$payment.review_notes|default:'—'|escape}</td>
                                        <td>
                                            {if $payment.status eq 'pending'}
                                                <form method="post" action="{$_url}plugin/fbpaymentreview">
                                                    <input type="hidden" name="csrf_token" value="{$fbPaymentCsrf|escape}">
                                                    <input type="hidden" name="submission_id" value="{$payment.id|escape}">
                                                    <input type="hidden" name="return_status" value="{$fbPaymentStatus|escape}">
                                                    <input type="hidden" name="action" value="">
                                                    <div class="form-group">
                                                        <textarea class="form-control" name="review_notes" rows="2" maxlength="500" placeholder="Review note; required to decline"></textarea>
                                                    </div>
                                                    <button class="btn btn-success btn-sm" type="button"
                                                        onclick="if (!confirm('Have you verified this transaction in the payment record? Approve this reference review?')) { return false; } var f = this.form; f.elements['action'].value = 'approve'; f.submit();">
                                                        <i class="ion ion-checkmark-circled"></i> Approve
                                                    </button>
                                                    <button class="btn btn-danger btn-sm" type="button"
                                                        onclick="var f = this.form; if (!f.elements['review_notes'].value.trim()) { alert('Enter a reason before declining.'); return false; } if (!confirm('Decline this payment submission?')) { return false; } f.elements['action'].value = 'decline'; f.submit();">
                                                        <i class="ion ion-close-circled"></i> Decline
                                                    </button>
                                                </form>
                                            {else}
                                                <span class="text-muted">Already reviewed</span>
                                            {/if}
                                        </td>
                                    </tr>
                                {/foreach}
                            {else}
                                <tr>
                                    <td colspan="6" class="text-center">No submissions found for this status.</td>
                                </tr>
                            {/if}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

{* ---------------- RECEIPT PHOTO PREVIEW MODAL ---------------- *}
<div class="modal fade" id="fbProofModal" tabindex="-1" role="dialog" aria-labelledby="fbProofModalLabel">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="fbProofModalLabel"><i class="ion ion-image"></i> Proof of Payment</h4>
            </div>
            <div class="modal-body text-center">
                <img id="fbProofImage" src="" alt="Proof of payment" style="max-width:100%; max-height:70vh;">
            </div>
            <div class="modal-footer">
                <a id="fbProofOpen" href="#" target="_blank" rel="noopener" class="btn btn-default">Open in new tab</a>
                <button type="button" class="btn btn-primary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
{literal}
<style>
    #fbProofModal.in { display: flex !important; align-items: center; justify-content: center; padding: 0 !important; }
    #fbProofModal .modal-dialog { width: 700px; max-width: 94%; margin: 0 auto; }
</style>
<script>
    window.addEventListener('load', function () {
        if (!window.jQuery) { return; }
        var $p = jQuery('#fbProofModal');
        $p.appendTo('body');
        $p.on('show.bs.modal', function (e) {
            var src = jQuery(e.relatedTarget).data('src') || '';
            jQuery('#fbProofImage').attr('src', src);
            jQuery('#fbProofOpen').attr('href', src);
        });
        $p.on('hidden.bs.modal', function () { jQuery('#fbProofImage').attr('src', ''); });
    });
</script>
{/literal}

{* ---------------- MESSENGER BOT SETTINGS MODAL (SuperAdmin / Admin only) ---------------- *}
{if $fbCanEditSettings}
<div class="modal fade" id="fbSettingsModal" tabindex="-1" role="dialog" aria-labelledby="fbSettingsModalLabel">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="post" action="{$_url}plugin/fbpaymentreview" autocomplete="off">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title" id="fbSettingsModalLabel"><i class="ion ion-gear-a"></i> Messenger Bot Settings</h4>
                    <div class="small text-muted">Saved in tbl_appconfig and used by the Facebook Messenger bot.</div>
                </div>

                <div class="modal-body">
                    {if $fbSettingsNotice}
                        <div class="alert alert-success" role="alert">{$fbSettingsNotice|escape}</div>
                    {/if}
                    {if $fbSettingsError}
                        <div class="alert alert-danger" role="alert">{$fbSettingsError|escape}</div>
                    {/if}

                    <input type="hidden" name="csrf_token" value="{$fbPaymentCsrf|escape}">
                    <input type="hidden" name="action" value="save_settings">

                    <div class="form-group">
                        <label>Webhook Callback URL (paste in Meta)</label>
                        <input type="text" class="form-control" value="{$fbWebhookUrl|escape}" readonly onclick="this.select()">
                    </div>

                    <div class="form-group">
                        <label>Verify Token</label>
                        <input type="text" class="form-control" name="fb_verify_token"
                               value="{$fbSettings.verify_token|escape}" required minlength="8">
                        <small class="help-block">Any long random text. Type the SAME text in Meta when you add the webhook.</small>
                    </div>

                    <div class="form-group">
                        <label>Page Access Token</label>
                        <input type="password" class="form-control" name="fb_page_token" value=""
                               placeholder="{if $fbSettings.page_token_set}Saved: {$fbSettings.page_token_masked|escape} (leave blank to keep){else}Paste token{/if}">
                        <small class="help-block">Meta app &gt; Messenger &gt; generate token for your Page.</small>
                    </div>

                    <div class="form-group">
                        <label>App Secret</label>
                        <input type="password" class="form-control" name="fb_app_secret" value=""
                               placeholder="{if $fbSettings.app_secret_set}Saved: {$fbSettings.app_secret_masked|escape} (leave blank to keep){else}Paste secret{/if}">
                        <small class="help-block">Meta app &gt; Settings &gt; Basic. Used to confirm messages really come from Meta.</small>
                    </div>

                    <div class="form-group">
                        <label>Graph API Version</label>
                        <input type="text" class="form-control" name="fb_graph_version"
                               value="{$fbSettings.graph_version|escape}" placeholder="v23.0" pattern="v[0-9]+\.[0-9]+">
                    </div>

                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Settings</button>
                </div>
            </form>
        </div>
    </div>
</div>

{literal}
<style>
    /* Header: keep filter and gear button on one line, vertically centered */
    .panel-heading .form-inline select.form-control { display: inline-block; vertical-align: middle; width: auto; }
    .panel-heading .btn[data-target="#fbSettingsModal"] { vertical-align: middle; margin-left: 6px; }

    /* Modal: centered on screen, consistent width, tidy header */
    #fbSettingsModal.in { display: flex !important; align-items: center; justify-content: center; padding: 0 !important; }
    #fbSettingsModal .modal-dialog { width: 600px; max-width: 94%; margin: 0 auto; }
    #fbSettingsModal .modal-content { max-height: 92vh; display: flex; flex-direction: column; }
    #fbSettingsModal form { display: flex; flex-direction: column; min-height: 0; }
    #fbSettingsModal .modal-header { padding: 15px 20px; }
    #fbSettingsModal .modal-header .modal-title { margin: 0; line-height: 1.4; }
    #fbSettingsModal .modal-header .close { margin-top: 0; }
    #fbSettingsModal .modal-body { padding: 20px; overflow-y: auto; text-align: left; }
    #fbSettingsModal .modal-body .form-group:last-child { margin-bottom: 0; }
    #fbSettingsModal .modal-footer { padding: 15px 20px; text-align: right; }
</style>
<script>
    window.addEventListener('load', function () {
        if (!window.jQuery) { return; }
        var $m = jQuery('#fbSettingsModal');
        // Move the modal out of the page layout so the theme's wrappers can't offset it.
        $m.appendTo('body');
{/literal}
{if $fbSettingsNotice || $fbSettingsError}
        // Re-open after saving so the success or error message is visible.
        $m.modal('show');
{/if}
{literal}
    });
</script>
{/literal}
{/if}

{include file="sections/footer.tpl"}
