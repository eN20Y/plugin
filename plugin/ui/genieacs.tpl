{include file="sections/header.tpl"}

{literal}
<style>
/* GENIESYS - BILLSYS visual style */
.geniesys-page .panel { border:0; border-radius:8px; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,.08); }
.geniesys-page .panel-heading { background:#337ab7; color:#fff; padding:15px 20px; min-height:48px; }
.geniesys-page .panel-heading-title { font-size:16px; font-weight:600; }
.geniesys-page .panel-heading small { display:block; margin-top:4px; color:rgba(255,255,255,.88); }
.geniesys-page .panel-heading .pull-right .btn { margin-left:4px; }
.geniesys-page .panel-body { padding:20px; }
.geniesys-page .table-responsive { border:0; margin-bottom:0; }
.geniesys-page .table { margin-bottom:0; }
.geniesys-page .table th,
.geniesys-page .table td { vertical-align:middle !important; }
.geniesys-page .device-id { display:block; max-width:220px; font-family:monospace; font-size:11px; color:#777; word-break:break-all; }
.geniesys-page .customer-name { font-weight:700; }
.geniesys-page .wifi-clients { display:inline-block; min-width:54px; padding:6px 9px; border-radius:14px; background:#e8f4ff; color:#1769aa; font-weight:700; text-align:center; }
.geniesys-page .muted { color:#888; }
.geniesys-page .rx-reading { display:inline-block; min-width:82px; padding:6px 8px; font-size:12px; font-weight:700; text-align:center; border-radius:4px; }
.geniesys-page .rx-reading.label-success { background:#5cb85c; }
.geniesys-page .rx-reading.label-warning { background:#f0ad4e; color:#222; }
.geniesys-page .rx-reading.label-danger { background:#d9534f; }
.geniesys-page .rx-reading.label-default { background:#777; }
.geniesys-page .row-actions { white-space:nowrap; }
.geniesys-page .row-actions .btn { margin:1px; }
.geniesys-page .empty-state { padding:35px 15px !important; text-align:center; color:#888; }
.geniesys-page .empty-state .fa { display:block; font-size:34px; margin-bottom:10px; opacity:.5; }
.geniesys-page .config-panel { max-width:850px; margin:0 auto; }
.geniesys-page label { font-weight:600; }
.geniesys-page .help-block { margin-bottom:0; }
.geniesys-page .toolbar { display:flex; flex-wrap:wrap; gap:10px; align-items:center; justify-content:space-between; margin-bottom:15px; }
.geniesys-page .toolbar .search-box { position:relative; flex:1 1 260px; max-width:360px; }
.geniesys-page .toolbar .search-box .fa { position:absolute; left:11px; top:11px; color:#999; pointer-events:none; }
.geniesys-page .toolbar .search-box input { padding-left:32px; }
.geniesys-page .toolbar .count { color:#888; font-size:13px; }
.geniesys-page .no-match { display:none; }

/* Modal: lives under <body> (moved by JS), so it is styled by ID only */
#geniesysWifi .modal-dialog { margin-top:60px; }
#geniesysWifi .modal-content { border:0; border-radius:10px; overflow:hidden; box-shadow:0 10px 35px rgba(0,0,0,.25); }
#geniesysWifi .modal-header { background:#337ab7; color:#fff; border:0; padding:18px 25px; }
#geniesysWifi .modal-header .close { color:#fff; opacity:.9; font-size:28px; text-shadow:none; margin-top:-2px; }
#geniesysWifi .modal-title { font-size:20px; font-weight:600; }
#geniesysWifi .modal-body { padding:20px 25px; }
#geniesysWifi .modal-footer { padding:14px 25px; border-top:1px solid #eee; }
/* Override theme horizontal-form rules: stack label above field, full width */
#geniesysWifi .form-group { display:block !important; float:none !important; width:100% !important; margin-left:0 !important; margin-right:0 !important; }
#geniesysWifi .form-group::before,
#geniesysWifi .form-group::after { display:none !important; }
#geniesysWifi label { display:block !important; float:none !important; width:100% !important; max-width:100% !important; text-align:left !important; padding:0 !important; margin:0 0 6px !important; font-weight:600; }
#geniesysWifi .form-control { display:block !important; float:none !important; width:100% !important; max-width:100% !important; }
#geniesysWifi .input-group { display:table !important; width:100% !important; float:none !important; }
#geniesysWifi .input-group .form-control { display:table-cell !important; float:none !important; width:100% !important; }
#geniesysWifi .input-group-btn { width:1%; white-space:nowrap; }
#geniesysWifi .field-meta { clear:both; width:100%; gap:12px; align-items:flex-start; }
#geniesysWifi .field-meta span:last-child { white-space:nowrap; }
#geniesysWifi .muted { color:#888; font-weight:400; }
#geniesysWifi .device-id { display:block; font-family:monospace; font-size:11px; color:#777; word-break:break-all; }
#geniesysWifi .onu-summary { padding:12px 14px; margin-bottom:18px; background:#f5f8fb; border:1px solid #e2eaf2; border-radius:6px; }
#geniesysWifi .onu-summary strong { display:block; margin-bottom:4px; }
#geniesysWifi .form-group { margin-bottom:16px; }
#geniesysWifi .form-control { height:38px; }
#geniesysWifi .modal-error { display:none; margin:0 0 15px; }
#geniesysWifi .help-block { margin-bottom:0; font-size:12px; }
#geniesysWifi .field-meta { display:flex; justify-content:space-between; margin-top:4px; font-size:12px; color:#888; }
#geniesysWifi .field-meta .bad { color:#d9534f; }
#geniesysWifi .input-group .btn { height:38px; }

body.dark-mode .geniesys-page .panel,
body.dark-mode .geniesys-page .panel-body { background:#111827; color:#f3f4f6; }
body.dark-mode .geniesys-page .table { color:#e5e7eb; }
body.dark-mode .geniesys-page .table-bordered,
body.dark-mode .geniesys-page .table-bordered th,
body.dark-mode .geniesys-page .table-bordered td { border-color:#374151; }
body.dark-mode .geniesys-page .table-striped > tbody > tr:nth-of-type(odd) { background:#1f2937; }
body.dark-mode .geniesys-page .muted,
body.dark-mode .geniesys-page .device-id,
body.dark-mode body.dark-mode .geniesys-page .wifi-clients { background:#12304a; color:#bfdbfe; }
body.dark-mode .geniesys-page .form-control { background:#0f172a; color:#f9fafb; border-color:#374151; }
body.dark-mode #geniesysWifi .modal-content,
body.dark-mode #geniesysWifi .modal-body,
body.dark-mode #geniesysWifi .modal-footer { background:#111827; color:#f3f4f6; }
body.dark-mode #geniesysWifi .modal-footer { border-color:#374151; }
body.dark-mode #geniesysWifi .onu-summary { background:#1f2937; border-color:#374151; }
body.dark-mode #geniesysWifi .form-control { background:#0f172a; color:#f9fafb; border-color:#374151; }
body.dark-mode #geniesysWifi .input-group-btn .btn { background:#1f2937; color:#f3f4f6; border-color:#374151; }
body.dark-mode #geniesysWifi .help-block,
body.dark-mode #geniesysWifi .muted,
body.dark-mode #geniesysWifi .field-meta { color:#9ca3af; }

@media (max-width:767px) {
    .geniesys-page .panel-heading { padding:13px 15px; }
    .geniesys-page .panel-heading .pull-left,
    .geniesys-page .panel-heading .pull-right { float:none !important; }
    .geniesys-page .panel-heading .pull-right { margin-top:12px; }
    .geniesys-page .panel-heading .pull-right .btn:first-child { margin-left:0; }
    .geniesys-page .panel-body { padding:12px; }
    .geniesys-page .toolbar .search-box { max-width:none; }
    #geniesysWifi .modal-dialog { margin:10px; }
    #geniesysWifi .modal-header { padding:15px 18px; }
    #geniesysWifi .modal-body { padding:16px 18px; }
    #geniesysWifi .modal-footer { padding:12px 18px; }
}
</style>
{/literal}

<div class="row geniesys-page">
    {if $_routes[1] == 'genieacs_config'}
        <div class="col-sm-12">
            <div class="panel panel-hovered mb20 panel-primary config-panel">
                <div class="panel-heading">
                    <span class="panel-heading-title"><i class="fa fa-cog"></i> GENIESYS Configuration</span>
                </div>
                <div class="panel-body">
                    <form method="post" action="?_route=plugin/genieacs_config" autocomplete="off">
                        <input type="hidden" name="csrf_token" value="{$genieacsCsrf|escape}">
                        <div class="form-group">
                            <label for="genieacsNbiUrl">NBI URL</label>
                            <input id="genieacsNbiUrl" class="form-control" type="url" name="nbi_url" required value="{$genieacsConfig.genieacs_nbi_url|escape}" placeholder="http://127.0.0.1:7557">
                            <p class="help-block">Use the GenieACS NBI endpoint (normally port 7557), not the web UI port 3000.</p>
                        </div>
                        <div class="form-group">
                            <label for="genieacsNbiUsername">HTTP Basic Auth username (optional)</label>
                            <input id="genieacsNbiUsername" class="form-control" name="nbi_username" value="{$genieacsConfig.genieacs_username|escape}" autocomplete="off">
                        </div>
                        <div class="form-group">
                            <label for="genieacsNbiPassword">HTTP Basic Auth password (optional)</label>
                            <input id="genieacsNbiPassword" class="form-control" type="password" name="nbi_password" autocomplete="new-password" placeholder="Leave blank to keep current password">
                        </div>
                        <button class="btn btn-primary" type="submit"><i class="fa fa-save"></i> Save Configuration</button>
                        <a class="btn btn-default" href="?_route=plugin/genieacs"><i class="fa fa-arrow-left"></i> Back</a>
                    </form>
                </div>
            </div>
        </div>
    {else}
        <div class="col-sm-12">
            <div class="panel panel-hovered mb20 panel-primary">
                <div class="panel-heading clearfix">
                    <div class="pull-right">
                        <a class="btn btn-default btn-xs" href="?_route=plugin/genieacs_config"><i class="fa fa-cog"></i> Settings</a>
                        <a class="btn btn-success btn-xs" href="?_route=plugin/genieacs"><i class="fa fa-refresh"></i> Refresh</a>
                    </div>
                    <div class="pull-left">
                        <div class="panel-heading-title"><i class="fa fa-wifi"></i> GENIESYS - Smart ONU Management</div>
                        <small>Customer details, ONU information and connected WiFi clients.</small>
                    </div>
                </div>
                <div class="panel-body">
                    {if !$genieacsConfigured}
                        <div class="alert alert-warning"><i class="fa fa-exclamation-triangle"></i> Configure the GenieACS NBI URL before automatic ONU matching can start.</div>
                        <a class="btn btn-primary" href="?_route=plugin/genieacs_config"><i class="fa fa-cog"></i> Configure GENIESYS</a>
                    {else}
                        {if $genieacsError}
                            <div class="alert alert-danger"><i class="fa fa-times-circle"></i> {$genieacsError|escape}</div>
                        {/if}

                        <div class="toolbar">
                            <div class="search-box">
                                <i class="fa fa-search"></i>
                                <input type="search" id="geniesysSearch" class="form-control" placeholder="Search customer, model, manufacturer, WiFi, RX power..." autocomplete="off">
                            </div>
                            <span class="count" id="geniesysCount"></span>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-bordered table-striped" id="geniesysTable">
                                <thead>
                                    <tr>
                                        <th>Customer Name</th>
                                        <th>Model / Manufacturer</th>
                                        <th>Connected WiFi Clients</th>
                                        <th>WiFi 2.4 GHz</th>
                                        <th>WiFi 5 GHz</th>
                                        <th>Optical RX Power</th>
                                        <th>Last Inform</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                {foreach $genieacsDevices as $d}
                                    <tr class="js-onu-row">
                                        <td>
                                            <span class="customer-name">{$d._customer_name|default:'Unknown customer'|escape}</span>
                                            <span class="device-id">Customer #{$d._customer_id|escape}</span>
                                        </td>
                                        <td>{$d._model|default:'-'|escape}<br><span class="muted">{$d._manufacturer|default:'-'|escape}</span></td>
                                        <td>
                                            <span class="wifi-clients"><i class="fa fa-users"></i> {$d._wifi_clients|default:0|escape}</span>
                                            
                                        </td>
                                        <td>
                                            <div>{$d._ssid1|default:'-'|escape}</div>
                                        </td>
                                        <td>
                                            <div>{$d._ssid2|default:'-'|escape}</div>
                                        </td>
                                        <td>
                                            {if $d._rx_power ne ''}
                                                <span class="label label-{$d._rx_class|escape} rx-reading">{$d._rx_power|escape} <span class="rx-unit">dBm</span></span>
                                            {else}
                                                <span class="label label-default rx-reading">N/A</span>
                                            {/if}
                                        </td>
                                        <td>{$d._lastInform|default:'-'|escape}</td>
                                        <td class="row-actions">
                                            <button type="button" class="btn btn-info btn-xs js-geniesys-wifi" title="Change WiFi name or password"
                                                data-device="{$d._id|escape:'html'}"
                                                data-pppoe="{$d._pppoe|default:'-'|escape:'html'}"
                                                data-customer="{$d._customer_name|default:'-'|escape:'html'}"
                                                data-ssid1="{$d._ssid1|escape:'html'}"
                                                data-ssid2="{$d._ssid2|escape:'html'}">
                                                <i class="fa fa-wifi"></i> WiFi
                                            </button>
                                            <form method="post" action="?_route=plugin/genieacs_reboot" style="display:inline" class="js-reboot-form">
                                                <input type="hidden" name="csrf_token" value="{$genieacsCsrf|escape}">
                                                <input type="hidden" name="device_id" value="{$d._id|escape:'html'}">
                                                <button class="btn btn-warning btn-xs" type="submit" title="Reboot this ONU"><i class="fa fa-power-off"></i> Reboot</button>
                                            </form>
                                        </td>
                                    </tr>
                                {foreachelse}
                                    <tr><td colspan="8" class="empty-state"><i class="fa fa-inbox"></i>No ONUs matched a BILLSYS customer PPPoE username.</td></tr>
                                {/foreach}
                                    <tr class="no-match" id="geniesysNoMatch"><td colspan="8" class="empty-state"><i class="fa fa-search"></i>No ONUs match your search.</td></tr>
                                </tbody>
                            </table>
                        </div>
                    {/if}
                </div>
            </div>
        </div>
    {/if}
</div>

{if $_routes[1] != 'genieacs_config' && $genieacsConfigured}
<!-- WiFi modal. JS moves it to <body> so no parent container can clip it or trap it under the backdrop. -->
<div class="modal fade" id="geniesysWifi" tabindex="-1" role="dialog" aria-labelledby="geniesysWifiTitle" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form id="geniesysWifiForm" method="post" action="?_route=plugin/genieacs_wifi" autocomplete="off" novalidate>
                <input type="hidden" name="csrf_token" value="{$genieacsCsrf|escape}">
                <input type="hidden" name="device_id" id="geniesysWifiDevice" value="">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title" id="geniesysWifiTitle"><i class="fa fa-wifi"></i> WiFi Configuration</h4>
                </div>
                <div class="modal-body">
                    <div class="onu-summary">
                        <strong>Selected ONU</strong>
                        <span id="geniesysWifiDeviceText" class="device-id">No ONU selected</span>
                        <div class="small muted" style="margin-top:6px">PPPoE: <span id="geniesysWifiPppoe">-</span> &nbsp;|&nbsp; Customer: <span id="geniesysWifiCustomer">-</span></div>
                    </div>

                    <div id="geniesysWifiError" class="alert alert-danger modal-error" role="alert"></div>

                    <div class="form-group">
                        <label for="geniesysWifiBand">WiFi band</label>
                        <select class="form-control" id="geniesysWifiBand" name="band" required>
                            <option value="2g">2.4 GHz (WLANConfiguration.1)</option>
                            <option value="5g">5 GHz (WLANConfiguration.5)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="geniesysWifiSsid">New SSID <span class="muted">(optional)</span></label>
                        <input class="form-control" id="geniesysWifiSsid" name="ssid" maxlength="32" placeholder="Enter new WiFi name">
                        <div class="field-meta"><span id="geniesysWifiCurrent">&nbsp;</span><span id="geniesysWifiSsidCount">0/32</span></div>
                    </div>
                    <div class="form-group">
                        <label for="geniesysWifiPassword">New WiFi password <span class="muted">(optional)</span></label>
                        <div class="input-group">
                            <input class="form-control" id="geniesysWifiPassword" name="wifi_password" type="password" maxlength="63" autocomplete="new-password" placeholder="8 to 63 characters">
                            <span class="input-group-btn">
                                <button class="btn btn-default" type="button" id="geniesysWifiToggle" title="Show / hide password" tabindex="-1"><i class="fa fa-eye"></i></button>
                            </span>
                        </div>
                        <div class="field-meta"><span id="geniesysWifiPassHint">Leave blank to keep the current password.</span><span id="geniesysWifiPassCount">0/63</span></div>
                    </div>
                    <p class="help-block">Uses the Huawei TR-069 KeyPassphrase parameter. Actual support depends on the ONU model and firmware.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                    <button type="submit" id="geniesysWifiSubmit" class="btn btn-primary"><i class="fa fa-save"></i> Submit Configuration</button>
                </div>
            </form>
        </div>
    </div>
</div>
{/if}

{if $_routes[1] != 'genieacs_config' && $genieacsConfigured}
{literal}
<script>
/* Wait for 'load' so jQuery/Bootstrap (often loaded by the footer) are guaranteed to exist. */
window.addEventListener('load', function () {
    'use strict';
    var $ = window.jQuery;
    if (!$ || !$.fn.modal) { return; }

    var $modal = $('#geniesysWifi');
    if (!$modal.length) { return; }

    /* FIX: move modal to <body> so parent wrappers (z-index, overflow, transform) cannot break it. */
    $modal.appendTo('body');

    var $form = $('#geniesysWifiForm'),
        $device = $('#geniesysWifiDevice'),
        $band = $('#geniesysWifiBand'),
        $ssid = $('#geniesysWifiSsid'),
        $pass = $('#geniesysWifiPassword'),
        $error = $('#geniesysWifiError'),
        $submit = $('#geniesysWifiSubmit'),
        current = { '2g': '', '5g': '' },
        submitting = false;

    var submitHtml = '<i class="fa fa-save"></i> Submit Configuration';

    function showError(msg) {
        if (msg) { $error.text(msg).show(); } else { $error.hide().text(''); }
    }

    function updateCurrent() {
        var cur = current[$band.val()] || '';
        $('#geniesysWifiCurrent').text(cur && cur !== '-' ? 'Current: ' + cur : '\u00a0');
        $ssid.attr('placeholder', cur && cur !== '-' ? cur : 'Enter new WiFi name');
    }

    function updateCounters() {
        $('#geniesysWifiSsidCount').text($ssid.val().length + '/32');
        var len = $pass.val().length, $c = $('#geniesysWifiPassCount');
        $c.text(len + '/63').toggleClass('bad', len > 0 && len < 8);
        $('#geniesysWifiPassHint').text(len > 0 && len < 8 ? 'Minimum 8 characters.' : 'Leave blank to keep the current password.')
            .toggleClass('bad', len > 0 && len < 8);
    }

    function resetForm() {
        submitting = false;
        $form[0].reset();
        $device.val('');
        $pass.attr('type', 'password');
        $('#geniesysWifiToggle i').attr('class', 'fa fa-eye');
        $submit.prop('disabled', false).html(submitHtml);
        showError('');
        updateCounters();
    }

    /* Open: fill the modal from the clicked row (single handler, no duplicates). */
    $(document).on('click', '.js-geniesys-wifi', function () {
        var b = this;
        resetForm();
        var id = b.getAttribute('data-device') || '';
        $device.val(id);
        $('#geniesysWifiDeviceText').text(id || 'No ONU selected');
        $('#geniesysWifiPppoe').text(b.getAttribute('data-pppoe') || '-');
        $('#geniesysWifiCustomer').text(b.getAttribute('data-customer') || '-');
        current['2g'] = b.getAttribute('data-ssid1') || '';
        current['5g'] = b.getAttribute('data-ssid2') || '';
        updateCurrent();
        $modal.modal('show');
    });

    $modal.on('shown.bs.modal', function () { $ssid.trigger('focus'); });
    $modal.on('hidden.bs.modal', resetForm);

    $band.on('change', updateCurrent);
    $ssid.add($pass).on('input', function () { updateCounters(); showError(''); });

    $('#geniesysWifiToggle').on('click', function () {
        var show = $pass.attr('type') === 'password';
        $pass.attr('type', show ? 'text' : 'password');
        $(this).find('i').attr('class', show ? 'fa fa-eye-slash' : 'fa fa-eye');
    });

    $form.on('submit', function (e) {
        if (submitting) { e.preventDefault(); return false; }

        var ssid = $.trim($ssid.val()),
            pass = $pass.val(),
            msg = '';

        if (!$.trim($device.val())) {
            msg = 'ONU device ID is missing. Close this window and click WiFi on the ONU row again.';
        } else if (!ssid && !pass) {
            msg = 'Enter a new SSID or WiFi password before submitting.';
        } else if (pass && (pass.length < 8 || pass.length > 63)) {
            msg = 'WiFi password must be between 8 and 63 characters.';
        }

        if (msg) {
            e.preventDefault();
            showError(msg);
            (pass && !ssid ? $pass : $ssid).trigger('focus');
            return false;
        }

        submitting = true;
        $submit.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Sending...');
    });

    /* Reboot: confirm once, then block double submits. */
    $(document).on('submit', '.js-reboot-form', function (e) {
        if (!window.confirm('Reboot this ONU? The customer will lose connection for a few minutes.')) {
            e.preventDefault();
            return false;
        }
        $(this).find('button[type=submit]').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Rebooting...');
    });

    /* Live search + counter. */
    var $rows = $('#geniesysTable tbody tr.js-onu-row'),
        $noMatch = $('#geniesysNoMatch'),
        $count = $('#geniesysCount'),
        total = $rows.length;

    function filterRows() {
        var q = $.trim($('#geniesysSearch').val()).toLowerCase(), shown = 0;
        $rows.each(function () {
            var hit = !q || $(this).text().toLowerCase().indexOf(q) !== -1;
            $(this).toggle(hit);
            if (hit) { shown++; }
        });
        $noMatch.toggle(total > 0 && shown === 0);
        $count.text(total ? 'Showing ' + shown + ' of ' + total + ' ONU' + (total === 1 ? '' : 's') : '');
    }

    $('#geniesysSearch').on('input', filterRows);
    filterRows();
});
</script>
{/literal}
{/if}

{include file="sections/footer.tpl"}
