{include file="sections/header.tpl"}

<div class="row">
    <div class="col-md-12">
        {if !$fbBcAllowed}
            <div class="alert alert-danger" role="alert">Only a SuperAdmin or Admin can send maintenance / outage notices.</div>
        {else}
        <div class="panel panel-primary panel-hovered panel-stacked mb30">
            <div class="panel-heading">
                <strong>Maintenance / Outage Notice</strong>
                <div class="small">Send a notice to customers who linked their Facebook Messenger account.</div>
            </div>
            <div class="panel-body">
                {if $fbBcNotice}
                    <div class="alert alert-success" role="alert">{$fbBcNotice|escape}</div>
                {/if}
                {if $fbBcError}
                    <div class="alert alert-danger" role="alert">{$fbBcError|escape}</div>
                {/if}

                <div class="alert alert-info">
                    Meta only allows a Page to message someone within 24 hours of their last message to the Page.
                    <strong>{$fbBcStats.reachable}</strong> of <strong>{$fbBcStats.total}</strong> linked customer(s) can be reached right now.
                    The other <strong>{$fbBcStats.queued}</strong> will get the notice right after their next message to the bot, until the notice expires.
                </div>

                <form method="post" action="{$_url}plugin/fbbroadcast" autocomplete="off"
                      onsubmit="var b=this.querySelector('button[type=submit]'); setTimeout(function(){ b.disabled = true; }, 0); return true;">
                    <input type="hidden" name="csrf_token" value="{$fbBcCsrf|escape}">
                    <input type="hidden" name="action" value="send">

                    <div class="row">
                        <div class="col-sm-4">
                            <div class="form-group">
                                <label>Type</label>
                                <select class="form-control" name="kind">
                                    {foreach $fbBcKinds as $k => $label}
                                        <option value="{$k}" {if $fbBcForm.kind eq $k}selected{/if}>{$label|escape}</option>
                                    {/foreach}
                                </select>
                            </div>
                        </div>
                        <div class="col-sm-8">
                            <div class="form-group">
                                <label>Title</label>
                                <input type="text" class="form-control" name="title" maxlength="100" required
                                       value="{$fbBcForm.title|escape}" placeholder="e.g. Internet interruption in Barangay Cogon">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Message</label>
                        <textarea class="form-control" name="message" rows="5" maxlength="1000" required
                                  placeholder="What happened, which areas are affected, and when service is expected back.">{$fbBcForm.message|escape}</textarea>
                        <small class="help-block">A heading matching the type is added above, and a thank-you line below.</small>
                    </div>

                    <div class="form-group" style="max-width:320px;">
                        <label>Keep queued notices valid for</label>
                        <select class="form-control" name="hours">
                            {foreach [6, 12, 24, 48, 72] as $h}
                                <option value="{$h}" {if $fbBcForm.hours eq $h}selected{/if}>{$h} hours</option>
                            {/foreach}
                        </select>
                        <small class="help-block">After this, customers who have not messaged the bot will not receive it.</small>
                    </div>

                    <button type="submit" class="btn btn-primary"
                            onclick="return confirm('Send this notice now to {$fbBcStats.reachable} customer(s) and queue it for {$fbBcStats.queued} more?');">
                        <i class="ion ion-paper-airplane"></i> Send notice
                    </button>
                </form>
            </div>
        </div>

        <div class="panel panel-default panel-hovered panel-stacked mb30">
            <div class="panel-heading"><strong>Recent notices</strong></div>
            <div class="panel-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead>
                            <tr>
                                <th>Notice</th>
                                <th>Sent</th>
                                <th>Delivery</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            {if $fbBcList}
                                {foreach $fbBcList as $a}
                                    <tr>
                                        <td>
                                            <span class="label label-{if $a.kind eq 'outage'}danger{elseif $a.kind eq 'maintenance'}warning{elseif $a.kind eq 'restored'}success{else}info{/if}">{$a.kind_label|escape}</span>
                                            <strong>{$a.title|escape}</strong><br>
                                            <small class="text-muted">{$a.message|truncate:140|escape}</small>
                                        </td>
                                        <td>{$a.created_at|escape}<br><small>By: {$a.created_by|escape}</small></td>
                                        <td>
                                            <span class="text-success">Sent: {$a.sent}</span><br>
                                            <span class="text-warning">Waiting: {$a.queued}</span><br>
                                            <span class="text-danger">Failed: {$a.failed}</span>
                                        </td>
                                        <td>
                                            {if $a.status eq 'cancelled'}
                                                <span class="label label-default">Cancelled</span>
                                            {elseif $a.expired}
                                                <span class="label label-default">Expired</span>
                                            {else}
                                                <span class="label label-primary">Active</span>
                                            {/if}
                                            <br><small>Valid until {$a.expires_at|escape}</small>
                                        </td>
                                        <td>
                                            {if $a.can_cancel}
                                                <form method="post" action="{$_url}plugin/fbbroadcast">
                                                    <input type="hidden" name="csrf_token" value="{$fbBcCsrf|escape}">
                                                    <input type="hidden" name="action" value="cancel">
                                                    <input type="hidden" name="announcement_id" value="{$a.id|escape}">
                                                    <button type="submit" class="btn btn-default btn-sm"
                                                            onclick="return confirm('Stop delivering this notice to the {$a.queued} customer(s) still waiting? Messages already sent cannot be recalled.');">
                                                        Cancel queued
                                                    </button>
                                                </form>
                                            {/if}
                                        </td>
                                    </tr>
                                {/foreach}
                            {else}
                                <tr><td colspan="5" class="text-center">No notices sent yet.</td></tr>
                            {/if}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        {/if}
    </div>
</div>

{include file="sections/footer.tpl"}
