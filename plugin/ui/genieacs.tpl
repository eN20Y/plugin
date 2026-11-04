{include file="sections/header.tpl"}

{if $routes[2] == 'config'}
    <div class="row">
        <div class="col-md-8 col-md-offset-2">
            <div class="panel panel-primary">
                <div class="panel-heading"><strong>GenieACS NBI Configuration</strong></div>
                <div class="panel-body">
                    <form method="post" action="?_route=plugin/genieacs_config" autocomplete="off">
                        <input type="hidden" name="csrf_token" value="{$genieacsCsrf|escape}">
                        <div class="form-group">
                            <label>NBI URL</label>
                            <input class="form-control" type="url" name="nbi_url" required value="{$genieacsConfig.genieacs_nbi_url|escape}" placeholder="http://127.0.0.1:7557">
                            <p class="help-block">Use the GenieACS NBI endpoint (normally port 7557), not the web UI port 3000.</p>
                        </div>
                        <div class="form-group">
                            <label>HTTP Basic Auth username (optional)</label>
                            <input class="form-control" name="nbi_username" value="{$genieacsConfig.genieacs_username|escape}">
                        </div>
                        <div class="form-group">
                            <label>HTTP Basic Auth password (optional)</label>
                            <input class="form-control" type="password" name="nbi_password" autocomplete="new-password" placeholder="Leave blank to keep current password">
                        </div>
                        <button class="btn btn-primary" type="submit">Save Configuration</button>
                        <a class="btn btn-default" href="?_route=plugin/genieacs">Back</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
{else}
    <style>
    .gacs { --gacs-text:#243650; --gacs-muted:#71819a; --gacs-border:#dce5f1; }
    body.dark-mode .gacs { --gacs-text:#e2e8f0; --gacs-muted:#9aa9bd; --gacs-border:#37465e; }
    .gacs .panel { border:0; border-radius:10px; overflow:hidden; }
    .gacs .panel-heading { background:linear-gradient(115deg,#075bd8,#398df5); color:#fff; padding:18px 22px; }
    .gacs .panel-body { color:var(--gacs-text); }
    .gacs .table-wrap { overflow-x:auto; }
    .gacs table { min-width:900px; }
    .gacs .muted { color:var(--gacs-muted); }
    .gacs .device-id { font-family:monospace; font-size:11px; word-break:break-all; }
    .gacs .modal-header { background:#075bd8; color:#fff; }
    .gacs .modal-header .close { color:#fff; opacity:.9; }
    body.dark-mode .gacs .panel-body, body.dark-mode .gacs .modal-content { background:#182235; color:#e2e8f0; }
    body.dark-mode .gacs .form-control { background:#202d42; color:#e2e8f0; border-color:#37465e; }
    </style>
    <div class="row gacs">
        <div class="col-sm-12">
            <div class="panel panel-primary">
                <div class="panel-heading">
                    <div class="clearfix">
                        <div class="pull-left">
                            <h3 style="margin:0;font-weight:700;color:#fff"><i class="fa fa-wifi"></i> GenieACS ONU Management</h3>
                            <small>Discover devices, bind them to BILLSYS customers, and manage WiFi remotely.</small>
                        </div>
                        <div class="pull-right">
                            <a class="btn btn-default btn-sm" href="?_route=plugin/genieacs_config"><i class="fa fa-cog"></i> Settings</a>
                            <a class="btn btn-success btn-sm" href="?_route=plugin/genieacs"><i class="fa fa-refresh"></i> Refresh</a>
                        </div>
                    </div>
                </div>
                <div class="panel-body">
                    {if !$genieacsConfigured}
                        <div class="alert alert-warning">Configure your GenieACS NBI URL before discovering devices.</div>
                        <a class="btn btn-primary" href="?_route=plugin/genieacs_config">Configure GenieACS</a>
                    {else}
                        {if $genieacsError}<div class="alert alert-danger">{$genieacsError|escape}</div>{/if}
                        <div class="table-wrap">
                            <table class="table table-bordered table-striped">
                                <thead><tr><th>Device</th><th>Model / Manufacturer</th><th>PPPoE Username</th><th>WiFi 2.4 GHz</th><th>WiFi 5 GHz</th><th>Last Inform</th><th>Customer ID</th><th>Actions</th></tr></thead>
                                <tbody>
                                {foreach $genieacsDevices as $d}
                                    <tr>
                                        <td><strong>{$d._serial|default:$d._id|escape}</strong><br><span class="device-id">{$d._id|escape}</span></td>
                                        <td>{$d._model|default:'-'|escape}<br><span class="muted">{$d._manufacturer|escape}</span></td>
                                        <td>{$d._pppoe|default:'-'|escape}</td>
                                        <td>{$d._ssid1|default:'-'|escape}</td>
                                        <td>{$d._ssid2|default:'-'|escape}</td>
                                        <td>{$d._last_inform|default:'-'|escape}</td>
                                        <td>{if $d._customer_id}<span class="label label-success">#{$d._customer_id|escape}</span>{else}<span class="label label-warning">Unassigned</span>{/if}</td>
                                        <td>
                                            <button class="btn btn-xs btn-primary" data-toggle="modal" data-target="#gacsAssign" data-device="{$d._id|escape}">Assign</button>
                                            <button class="btn btn-xs btn-info" data-toggle="modal" data-target="#gacsWifi" data-device="{$d._id|escape}">WiFi</button>
                                            <form method="post" action="?_route=plugin/genieacs_reboot" style="display:inline" onsubmit="return confirm('Reboot this ONU?');">
                                                <input type="hidden" name="csrf_token" value="{$genieacsCsrf|escape}">
                                                <input type="hidden" name="device_id" value="{$d._id|escape}">
                                                <button class="btn btn-xs btn-warning" type="submit">Reboot</button>
                                            </form>
                                            {if $d._customer_id}
                                            <form method="post" action="?_route=plugin/genieacs_unassign" style="display:inline" onsubmit="return confirm('Remove customer binding?');">
                                                <input type="hidden" name="csrf_token" value="{$genieacsCsrf|escape}">
                                                <input type="hidden" name="device_id" value="{$d._id|escape}">
                                                <button class="btn btn-xs btn-danger" type="submit">Unassign</button>
                                            </form>
                                            {/if}
                                        </td>
                                    </tr>
                                {foreachelse}
                                    <tr><td colspan="8" class="text-center muted">No GenieACS devices returned.</td></tr>
                                {/foreach}
                                </tbody>
                            </table>
                        </div>
                    {/if}
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="gacsAssign" tabindex="-1"><div class="modal-dialog"><form class="modal-content" method="post" action="?_route=plugin/genieacs_assign">
        <input type="hidden" name="csrf_token" value="{$genieacsCsrf|escape}"><input type="hidden" name="device_id" id="gacsAssignDevice">
        <div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title">Assign ONU to BILLSYS Customer</h4></div>
        <div class="modal-body"><div class="form-group"><label>BILLSYS Customer ID</label><input class="form-control" type="number" min="1" name="customer_id" required><p class="help-block">Enter the numeric customer ID from tbl_customers. The device ID is stored as the stable GenieACS reference.</p></div></div>
        <div class="modal-footer"><button class="btn btn-default" data-dismiss="modal" type="button">Cancel</button><button class="btn btn-primary" type="submit">Save Assignment</button></div>
    </form></div></div>

    <div class="modal fade" id="gacsWifi" tabindex="-1"><div class="modal-dialog"><form class="modal-content" method="post" action="?_route=plugin/genieacs_wifi" autocomplete="off">
        <input type="hidden" name="csrf_token" value="{$genieacsCsrf|escape}"><input type="hidden" name="device_id" id="gacsWifiDevice">
        <div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title">WiFi Configuration</h4></div>
        <div class="modal-body">
            <div class="form-group"><label>WiFi band</label><select class="form-control" name="band"><option value="2g">2.4 GHz (WLANConfiguration.1)</option><option value="5g">5 GHz (WLANConfiguration.5)</option></select></div>
            <div class="form-group"><label>New SSID (optional)</label><input class="form-control" name="ssid" maxlength="32"></div>
            <div class="form-group"><label>New WiFi password (optional)</label><input class="form-control" type="password" name="wifi_password" minlength="8" maxlength="63" autocomplete="new-password"></div>
            <p class="help-block">Uses the Huawei TR-069 KeyPassphrase parameter. Actual support depends on the ONU model and firmware.</p>
        </div>
        <div class="modal-footer"><button class="btn btn-default" data-dismiss="modal" type="button">Cancel</button><button class="btn btn-primary" type="submit">Submit Configuration</button></div>
    </form></div></div>
    <script>
    (function () {
        $('#gacsAssign').on('show.bs.modal', function (e) {
            document.getElementById('gacsAssignDevice').value = e.relatedTarget.getAttribute('data-device');
        });
        $('#gacsWifi').on('show.bs.modal', function (e) {
            document.getElementById('gacsWifiDevice').value = e.relatedTarget.getAttribute('data-device');
        });
    })();
    </script>
{/if}
{include file="sections/footer.tpl"}
