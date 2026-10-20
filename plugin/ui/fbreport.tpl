{include file="sections/header.tpl"}

{literal}
<style>
    .fbrp-stat { display:block; text-decoration:none !important; color:inherit; background:#fff; border:1px solid #e3e6ea; border-top:4px solid #ccc; border-radius:6px; padding:14px 12px; margin-bottom:15px; text-align:center; transition:transform .12s, box-shadow .12s, background .12s; }
    .fbrp-stat:hover { transform:translateY(-2px); box-shadow:0 4px 12px rgba(0,0,0,.12); }
    .fbrp-stat .num { font-size:32px; font-weight:700; line-height:1.1; display:block; }
    .fbrp-stat .lbl { font-size:12px; text-transform:uppercase; letter-spacing:.5px; color:#777; }
    .fbrp-stat .ico { font-size:20px; margin-right:4px; vertical-align:middle; }
    .fbrp-stat .tap { display:block; font-size:11px; color:#aaa; margin-top:2px; }

    .fbrp-stat.s-open { border-top-color:#d9534f; }
    .fbrp-stat.s-open .num, .fbrp-stat.s-open .ico { color:#d9534f; }
    .fbrp-stat.s-progress { border-top-color:#f0ad4e; }
    .fbrp-stat.s-progress .num, .fbrp-stat.s-progress .ico { color:#ec971f; }
    .fbrp-stat.s-resolved { border-top-color:#5cb85c; }
    .fbrp-stat.s-resolved .num, .fbrp-stat.s-resolved .ico { color:#449d44; }
    .fbrp-stat.s-all { border-top-color:#337ab7; }
    .fbrp-stat.s-all .num, .fbrp-stat.s-all .ico { color:#337ab7; }

    /* The card of the filter that is currently selected */
    .fbrp-stat.active { box-shadow:0 0 0 2px currentColor, 0 4px 14px rgba(0,0,0,.15); }
    .fbrp-stat.active.s-open { background:#fdf0ef; color:#d9534f; }
    .fbrp-stat.active.s-progress { background:#fef7ea; color:#ec971f; }
    .fbrp-stat.active.s-resolved { background:#eef8ee; color:#449d44; }
    .fbrp-stat.active.s-all { background:#edf4fa; color:#337ab7; }
    .fbrp-stat.active .lbl { color:#444; font-weight:700; }

    /* Ticket rows get a coloured edge so the status is visible at a glance */
    .fbrp-table > tbody > tr > td:first-child { border-left:5px solid #ccc; }
    .fbrp-table > tbody > tr.r-open > td:first-child { border-left-color:#d9534f; }
    .fbrp-table > tbody > tr.r-in_progress > td:first-child { border-left-color:#f0ad4e; }
    .fbrp-table > tbody > tr.r-resolved > td:first-child { border-left-color:#5cb85c; }
    .fbrp-table > tbody > tr.r-open { background:#fffafa; }
    .fbrp-table > tbody > tr.r-in_progress { background:#fffcf5; }
    .fbrp-table > tbody > tr.r-resolved { background:#fafdfa; }
    .fbrp-table > tbody > tr.r-resolved .fbrp-details { opacity:.75; }
    .fbrp-table > tbody > tr:hover { background:#f3f7fb; }
    .fbrp-table .label { font-size:11px; padding:4px 8px; }
    .fbrp-ticket { font-size:15px; }
    .fbrp-details { background:#f7f7f7; border-left:3px solid #ddd; padding:5px 8px; margin-top:6px; border-radius:3px; }
    .fbrp-hint { font-size:11px; color:#888; margin-top:4px; }
    .fbrp-info { border-left:4px solid #5bc0de; }
</style>
{/literal}

<div class="row">
    <div class="col-md-12">
        {if !$fbRpAllowed}
            <div class="alert alert-danger" role="alert">Only a SuperAdmin or Admin can view reported issues.</div>
        {else}

        <div class="row">
            <div class="col-xs-6 col-sm-3">
                <a href="{$_url}plugin/fbreport&amp;status=open" class="fbrp-stat s-open {if $fbRpFilter.status eq 'open'}active{/if}">
                    <span class="num">{$fbRpStats.open}</span>
                    <span class="lbl"><i class="ion ion-alert-circled ico"></i>Open</span>
                    <span class="tap">Needs attention</span>
                </a>
            </div>
            <div class="col-xs-6 col-sm-3">
                <a href="{$_url}plugin/fbreport&amp;status=in_progress" class="fbrp-stat s-progress {if $fbRpFilter.status eq 'in_progress'}active{/if}">
                    <span class="num">{$fbRpStats.in_progress}</span>
                    <span class="lbl"><i class="ion ion-wrench ico"></i>In progress</span>
                    <span class="tap">Being fixed</span>
                </a>
            </div>
            <div class="col-xs-6 col-sm-3">
                <a href="{$_url}plugin/fbreport&amp;status=resolved" class="fbrp-stat s-resolved {if $fbRpFilter.status eq 'resolved'}active{/if}">
                    <span class="num">{$fbRpStats.resolved}</span>
                    <span class="lbl"><i class="ion ion-checkmark-circled ico"></i>Resolved</span>
                    <span class="tap">Completed</span>
                </a>
            </div>
            <div class="col-xs-6 col-sm-3">
                <a href="{$_url}plugin/fbreport" class="fbrp-stat s-all {if $fbRpFilter.status eq ''}active{/if}">
                    <span class="num">{$fbRpStats.total}</span>
                    <span class="lbl"><i class="ion ion-clipboard ico"></i>All reports</span>
                    <span class="tap">Show everything</span>
                </a>
            </div>
        </div>

        <div class="panel panel-primary panel-hovered panel-stacked mb30">
            <div class="panel-heading">
                <strong>Reported Issues</strong>
                <div class="small">Internet problems reported by customers through the Messenger bot.</div>
            </div>
            <div class="panel-body">
                {if $fbRpNotice}
                    <div class="alert alert-success" role="alert">{$fbRpNotice|escape}</div>
                {/if}
                {if $fbRpError}
                    <div class="alert alert-danger" role="alert">{$fbRpError|escape}</div>
                {/if}

                <div class="alert alert-info fbrp-info" role="alert" style="padding:8px 12px;">
                    <i class="ion ion-chatbubble-working"></i>
                    When you set a ticket to <strong>In progress</strong> or <strong>Resolved</strong>, the customer is
                    messaged on Messenger if <strong>Tell the customer on Messenger</strong> is ticked. Your private note is never sent.
                </div>

                <form method="get" action="{$_url}plugin/fbreport" class="form-inline" style="margin-bottom:15px;">
                    <input type="hidden" name="_route" value="plugin/fbreport">
                    <div class="form-group">
                        <select name="status" class="form-control">
                            <option value="">All statuses</option>
                            {foreach $fbRpStatuses as $k => $label}
                                <option value="{$k|escape}" {if $fbRpFilter.status eq $k}selected{/if}>{$label|escape}</option>
                            {/foreach}
                        </select>
                    </div>
                    <div class="form-group">
                        <select name="cat" class="form-control">
                            <option value="">All problem types</option>
                            {foreach $fbRpCats as $k => $label}
                                <option value="{$k|escape}" {if $fbRpFilter.cat eq $k}selected{/if}>{$label|escape}</option>
                            {/foreach}
                        </select>
                    </div>
                    <div class="form-group">
                        <input type="text" name="q" class="form-control" maxlength="60"
                               value="{$fbRpFilter.q|escape}" placeholder="Name, username, account no. or ticket #">
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="ion ion-search"></i> Filter</button>
                    <a href="{$_url}plugin/fbreport" class="btn btn-default">Reset</a>
                </form>

                <div class="table-responsive">
                    <table class="table table-bordered fbrp-table">
                        <thead>
                            <tr>
                                <th style="width:80px;">Ticket</th>
                                <th>Customer</th>
                                <th>Problem</th>
                                <th>Reported</th>
                                <th>Status</th>
                                <th style="width:250px;">Update</th>
                            </tr>
                        </thead>
                        <tbody>
                            {foreach $fbRpList as $r}
                                <tr class="r-{$r.status|escape}">
                                    <td><strong class="fbrp-ticket">#{$r.id|escape}</strong></td>
                                    <td>
                                        <strong>{$r.customer_name|escape}</strong><br>
                                        <small class="text-muted">
                                            {if $r.username}@{$r.username|escape}{/if}
                                            {if $r.account_number} | Acct {$r.account_number|escape}{/if}
                                        </small>
                                        {if $r.phone}<br><small><i class="ion ion-android-call"></i> {$r.phone|escape}</small>{/if}
                                        {if $r.address}<br><small class="text-muted">{$r.address|truncate:70:'...'|escape}</small>{/if}
                                    </td>
                                    <td>
                                        <span class="label label-{$r.category_class|escape}">{$r.category_label|escape}</span>
                                        {if $r.details}
                                            <div class="fbrp-details"><small>{$r.details|escape|nl2br}</small></div>
                                        {else}
                                            <div style="margin-top:6px;"><small class="text-muted">No description</small></div>
                                        {/if}
                                    </td>
                                    <td>
                                        {$r.created_at|escape}<br>
                                        <small class="text-muted">{$r.ago|escape}</small>
                                    </td>
                                    <td>
                                        <span class="label label-{$r.status_class|escape}">{$r.status_label|escape}</span>
                                        {if $r.handled_by}<br><small>By: {$r.handled_by|escape}</small>{/if}
                                        {if $r.updated_at}<br><small class="text-muted">{$r.updated_at|escape}</small>{/if}
                                        {if $r.status eq 'resolved' and $r.resolved_at}<br><small class="text-success">Fixed: {$r.resolved_at|escape}</small>{/if}
                                    </td>
                                    <td>
                                        <form method="post" action="{$_url}plugin/fbreport">
                                            <input type="hidden" name="csrf_token" value="{$fbRpCsrf|escape}">
                                            <input type="hidden" name="action" value="update">
                                            <input type="hidden" name="id" value="{$r.id|escape}">
                                            <input type="hidden" name="f_status" value="{$fbRpFilter.status|escape}">
                                            <input type="hidden" name="f_cat" value="{$fbRpFilter.cat|escape}">
                                            <input type="hidden" name="f_q" value="{$fbRpFilter.q|escape}">
                                            <input type="hidden" name="f_page" value="{$fbRpPage|escape}">
                                            <select name="status" class="form-control input-sm">
                                                {foreach $fbRpStatuses as $k => $label}
                                                    <option value="{$k|escape}" {if $r.status eq $k}selected{/if}>{$label|escape}</option>
                                                {/foreach}
                                            </select>
                                            <input type="text" name="note" class="form-control input-sm" maxlength="500"
                                                   style="margin-top:4px;" placeholder="Private note (not sent to customer)"
                                                   value="{$r.admin_notes|escape}">
                                            <div class="checkbox" style="margin:6px 0 4px;">
                                                <label class="small">
                                                    <input type="checkbox" name="notify" value="1" checked> <i class="ion ion-chatbubble"></i> Tell the customer on Messenger
                                                </label>
                                            </div>
                                            <button type="submit" class="btn btn-primary btn-sm btn-block">
                                                <i class="ion ion-checkmark"></i> Save
                                            </button>
                                            <div class="fbrp-hint">Message is sent only for In progress / Resolved</div>
                                        </form>
                                    </td>
                                </tr>
                            {foreachelse}
                                <tr>
                                    <td colspan="6" class="text-center text-muted" style="padding:30px;">
                                        <i class="ion ion-checkmark-circled" style="font-size:28px;"></i><br>
                                        No reported issues found.
                                    </td>
                                </tr>
                            {/foreach}
                        </tbody>
                    </table>
                </div>

                {if $fbRpPages > 1}
                    <nav>
                        <ul class="pagination" style="margin:0;">
                            {if $fbRpPage > 1}
                                <li><a href="{$_url}plugin/fbreport{$fbRpQs|escape}&amp;page={$fbRpPage-1}">&laquo; Prev</a></li>
                            {/if}
                            {for $p=1 to $fbRpPages}
                                {if $p >= $fbRpPage-3 and $p <= $fbRpPage+3}
                                    <li {if $p == $fbRpPage}class="active"{/if}><a href="{$_url}plugin/fbreport{$fbRpQs|escape}&amp;page={$p}">{$p}</a></li>
                                {/if}
                            {/for}
                            {if $fbRpPage < $fbRpPages}
                                <li><a href="{$_url}plugin/fbreport{$fbRpQs|escape}&amp;page={$fbRpPage+1}">Next &raquo;</a></li>
                            {/if}
                        </ul>
                        <small class="text-muted">{$fbRpTotalRows} report(s) found</small>
                    </nav>
                {/if}
            </div>
        </div>
        {/if}
    </div>
</div>

{include file="sections/footer.tpl"}
