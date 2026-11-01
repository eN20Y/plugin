{include file="sections/header.tpl"}

{literal}
<style>
.juanfi-page {
    --bg: #fff; --text: #243650; --head: #172b4d; --border: #dce5f1;
    --th: #f0f5fc; --stripe: #f8fbff; --hover: #edf5ff; --muted: #71819a; --host: #1d3d69;
    color: var(--head);
    padding-bottom: 25px;
}
body.dark-mode .juanfi-page {
    --bg: #182235; --text: #e2e8f0; --head: #e2e8f0; --border: #37465e;
    --th: #202d42; --stripe: #202d42; --hover: #202d42; --host: #a9ccff;
}

.juanfi-page .panel {
    border: 0;
    border-radius: 10px;
    overflow: hidden;
    background: var(--bg);
    box-shadow: 0 4px 18px rgba(24, 64, 120, .07);
}
.juanfi-page .panel-heading {
    padding: 22px 24px;
    border: 0;
    color: #fff;
    background: linear-gradient(115deg, #075bd8, #398df5);
}
.juanfi-page .panel-body { padding: 20px; background: var(--bg); }

.juanfi-page .page-heading { display: flex; align-items: center; gap: 14px; }
.juanfi-page .heading-icon {
    width: 52px; height: 52px; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    border-radius: 12px; font-size: 24px;
    background: rgba(255,255,255,.18);
}
.juanfi-page .heading-title { margin: 0 0 5px; font-size: 23px; font-weight: 700; color: #fff; }
.juanfi-page .heading-subtitle { margin: 0; font-size: 13px; color: rgba(255,255,255,.85); }
.juanfi-page .heading-counts { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px; }
.juanfi-page .count-pill {
    display: inline-flex; align-items: center; gap: 7px;
    padding: 7px 13px; border-radius: 8px;
    font-size: 12px; font-weight: 600; color: #fff;
    background: rgba(255,255,255,.22);
}
.juanfi-page .count-pill.online { background: #18a957; }

.juanfi-page .toolbar { display: flex; justify-content: flex-end; gap: 8px; margin-bottom: 14px; }
.juanfi-page .toolbar .btn { padding: 9px 16px; border-radius: 7px; font-weight: 600; }

.juanfi-page .table-wrap {
    overflow-x: auto;
    border: 1px solid var(--border);
    border-radius: 8px;
}
.juanfi-page #juanfiVendos {
    width: 100%;
    min-width: 760px;
    margin: 0;
    border: 0;
    font-size: 12px;
    color: var(--text);
    background: var(--bg);
}
.juanfi-page #juanfiVendos th,
.juanfi-page #juanfiVendos td {
    padding: 12px;
    white-space: nowrap;
    vertical-align: middle;
    border-color: var(--border);
}
.juanfi-page #juanfiVendos thead th {
    padding: 14px 12px;
    border-top: 0;
    background: var(--th);
    color: var(--head);
    font-weight: 700;
}
.juanfi-page #juanfiVendos tbody tr:nth-child(even) { background: var(--stripe); }
.juanfi-page #juanfiVendos tbody tr:hover { background: var(--hover); }
.juanfi-page .vendo-name { font-weight: 600; color: var(--host); }
.juanfi-page .latency { margin-left: 6px; color: var(--muted); font-size: 11px; }

.juanfi-page .status-badge {
    display: inline-flex; align-items: center; justify-content: center;
    min-width: 96px; gap: 6px;
    padding: 7px 11px; border-radius: 18px;
    font-size: 11px; font-weight: 700; color: #fff;
    background: #7d8ba0;
}
.juanfi-page .status-badge.online { background: #19a64c; }

.juanfi-page .table-actions { text-align: center; }
.juanfi-page .table-actions .btn { margin: 2px; padding: 5px 9px; font-size: 11px; }

.juanfi-page .empty-state { padding: 35px 15px !important; text-align: center; color: var(--muted); }
.juanfi-page .alert { margin-bottom: 12px; border-radius: 7px; }

/* Modals (outside .juanfi-page, so they have their own colours) */
#juanfiRenameModal .modal-content, #juanfiSettingsModal .modal-content { border: 0; border-radius: 10px; overflow: hidden; }
#juanfiRenameModal .modal-header, #juanfiSettingsModal .modal-header {
    border: 0; color: #fff;
    background: linear-gradient(115deg, #075bd8, #398df5);
}
#juanfiRenameModal .modal-header .close, #juanfiSettingsModal .modal-header .close { color: #fff; opacity: .85; text-shadow: none; }
#juanfiRenameModal .modal-title, #juanfiSettingsModal .modal-title { font-weight: 700; }
#juanfiRenameModal .modal-body, #juanfiSettingsModal .modal-body { padding: 20px; }
#juanfiRenameModal .rename-ip { margin: 0 0 14px; font-size: 12px; color: #71819a; }
#juanfiRenameModal .form-group { display: block; margin: 0 0 14px; padding: 0; }
#juanfiRenameModal .form-group::before, #juanfiRenameModal .form-group::after { display: none; }
#juanfiRenameModal .form-group label, #juanfiRenameModal .form-group .control-label, #juanfiSettingsModal label[for="juanfiSetRouter"] { display: block !important; float: none !important; width: 100% !important; max-width: 100% !important; padding: 0 !important; margin: 0 0 6px !important; text-align: left !important; font-weight: 600; }
#juanfiRenameModal .form-control, #juanfiSettingsModal .form-control { display: block !important; float: none !important; width: 100% !important; height: 40px; padding: 6px 12px; border-radius: 7px; box-sizing: border-box; }
#juanfiRenameModal .form-group > div { width: 100% !important; float: none !important; padding: 0 !important; }
#juanfiRenameModal .rename-hint { margin: 8px 0 0; font-size: 11px; color: #71819a; }
#juanfiRenameModal .modal-footer, #juanfiSettingsModal .modal-footer { border-top: 1px solid rgba(113,129,154,.25); }
body.dark-mode #juanfiRenameModal .modal-content, body.dark-mode #juanfiSettingsModal .modal-content { background: #182235; color: #e2e8f0; }
body.dark-mode #juanfiRenameModal .form-control, body.dark-mode #juanfiSettingsModal .form-control { background: #202d42; border-color: #37465e; color: #e2e8f0; }

.juanfi-opt { display: block; margin: 0 0 8px; padding: 10px 12px; border: 1px solid rgba(113,129,154,.35); border-radius: 8px; font-weight: 400; cursor: pointer; }
.juanfi-opt input { margin-right: 8px; }
.juanfi-opt strong { display: block; font-weight: 700; }
.juanfi-opt small { display: block; margin: 2px 0 0 20px; color: #71819a; }
.juanfi-opt:hover { border-color: #398df5; }
#juanfiSettingsModal .settings-label { margin: 14px 0 8px; font-weight: 700; }
#juanfiSettingsModal label.settings-field { display: block; margin: 10px 0 6px; font-weight: 600; }

#juanfiDeleteModal .modal-content { border: 0; border-radius: 10px; overflow: hidden; }
#juanfiDeleteModal .modal-header { border: 0; color: #fff; background: linear-gradient(115deg, #c62828, #ef5350); }
#juanfiDeleteModal .modal-header .close { color: #fff; opacity: .85; text-shadow: none; }
#juanfiDeleteModal .modal-title { font-weight: 700; }
#juanfiDeleteModal .modal-body { padding: 20px; }
#juanfiDeleteModal .rename-hint { margin: 8px 0 0; font-size: 11px; color: #71819a; }
#juanfiDeleteModal .modal-footer { border-top: 1px solid rgba(113,129,154,.25); }
body.dark-mode #juanfiDeleteModal .modal-content { background: #182235; color: #e2e8f0; }
#juanfiRenameModal .form-group { margin-bottom: 12px; }

#juanfiAddModal .modal-content { border: 0; border-radius: 10px; overflow: hidden; }
#juanfiAddModal .modal-header { border: 0; color: #fff; background: linear-gradient(115deg, #0f8a43, #2fc873); }
#juanfiAddModal .modal-header .close { color: #fff; opacity: .85; text-shadow: none; }
#juanfiAddModal .modal-title { font-weight: 700; }
#juanfiAddModal .modal-body { padding: 20px; }
#juanfiAddModal .form-group { display: block; margin: 0 0 14px; padding: 0; }
#juanfiAddModal .form-group::before, #juanfiAddModal .form-group::after { display: none; }
#juanfiAddModal .form-group label, #juanfiAddModal .form-group .control-label { display: block !important; float: none !important; width: 100% !important; max-width: 100% !important; padding: 0 !important; margin: 0 0 6px !important; text-align: left !important; font-weight: 600; }
#juanfiAddModal .form-control { display: block !important; float: none !important; width: 100% !important; height: 40px; padding: 6px 12px; border-radius: 7px; box-sizing: border-box; }
#juanfiAddModal .form-group > div { width: 100% !important; float: none !important; padding: 0 !important; }
#juanfiAddModal .rename-hint { margin: 8px 0 0; font-size: 11px; color: #71819a; }
#juanfiAddModal .modal-footer { border-top: 1px solid rgba(113,129,154,.25); }
body.dark-mode #juanfiAddModal .modal-content { background: #182235; color: #e2e8f0; }
body.dark-mode #juanfiAddModal .form-control { background: #202d42; border-color: #37465e; color: #e2e8f0; }

#juanfiRatesModal .modal-content { border: 0; border-radius: 10px; overflow: hidden; }
#juanfiRatesModal .modal-header { border: 0; color: #fff; background: linear-gradient(115deg, #075bd8, #398df5); }
#juanfiRatesModal .modal-header .close { color: #fff; opacity: .85; text-shadow: none; }
#juanfiRatesModal .modal-title { font-weight: 700; }
#juanfiRatesModal .modal-body { padding: 20px; }
#juanfiRatesModal .modal-footer { border-top: 1px solid rgba(113,129,154,.25); }
#juanfiRatesModal .juanfi-rates-wrap { overflow-x: auto; margin-bottom: 10px; }
#juanfiRatesModal #juanfiRatesTable { min-width: 720px; margin: 0; }
#juanfiRatesModal #juanfiRatesTable th { font-size: 11px; white-space: nowrap; }
#juanfiRatesModal #juanfiRatesTable th small { font-weight: 400; color: #71819a; }
#juanfiRatesModal #juanfiRatesTable td { padding: 6px; vertical-align: middle; }
#juanfiRatesModal #juanfiRatesTable .form-control { height: 34px; padding: 4px 8px; }
#juanfiRatesModal .rename-hint { margin: 10px 0 0; font-size: 11px; color: #71819a; }
body.dark-mode #juanfiRatesModal .modal-content { background: #182235; color: #e2e8f0; }
body.dark-mode #juanfiRatesModal .form-control { background: #202d42; border-color: #37465e; color: #e2e8f0; }
body.dark-mode #juanfiRatesModal #juanfiRatesTable th { background: #202d42; color: #e2e8f0; }

@media (max-width: 600px) {
    .juanfi-page .panel-heading,
    .juanfi-page .panel-body { padding: 15px; }
    .juanfi-page .heading-title { font-size: 19px; }
    .juanfi-page .heading-icon { width: 43px; height: 43px; font-size: 20px; }
}
</style>
{/literal}

<div class="row juanfi-page">
    <div class="col-sm-12">
        <div class="panel panel-primary panel-hovered panel-stacked mb30">

            <div class="panel-heading">
                <div class="page-heading">
                    <div class="heading-icon">
                        <i class="glyphicon glyphicon-hdd"></i>
                    </div>
                    <div>
                        <h3 class="heading-title">Vendo List</h3>
                        <p class="heading-subtitle">Vending machines listed in your portal's config.js, checked live from the router.</p>
                        <div class="heading-counts">
                            <span class="count-pill online">
                                <i class="glyphicon glyphicon-signal"></i> {$juanfiVendoCounts.online} Online
                            </span>
                            <span class="count-pill">
                                <i class="glyphicon glyphicon-ban-circle"></i> {$juanfiVendoCounts.offline} Offline
                            </span>
                            {if $juanfiVendoMode}
                                <span class="count-pill">
                                    <i class="glyphicon glyphicon-cog"></i> {$juanfiVendoMode|escape}
                                </span>
                            {/if}
                            {if $juanfiVendoPause}
                                <span class="count-pill">
                                    <i class="glyphicon glyphicon-pause"></i> Pause time: {$juanfiVendoPause|escape}
                                </span>
                            {/if}
                        </div>
                    </div>
                </div>
            </div>

            <div class="panel-body">

                {foreach $juanfiVendoErrors as $e}
                    <div class="alert alert-warning">
                        <i class="glyphicon glyphicon-warning-sign"></i> {$e|escape}
                    </div>
                {/foreach}

                <div class="toolbar">
                    {if $juanfiVendoSettings}
                        <button type="button" class="btn btn-success" data-toggle="modal" data-target="#juanfiAddModal"
                                title="Add a vendo to config.js">
                            <i class="glyphicon glyphicon-plus"></i> Add Vendo
                        </button>
                        <button type="button" class="btn btn-default" data-toggle="modal" data-target="#juanfiSettingsModal"
                                title="Vendo mode, pause time, PisoWiFi name and banner text" id="juanfiGearBtn">
                            <i class="glyphicon glyphicon-cog"></i>
                        </button>
                    {/if}
                    <a href="?_route=plugin/juanfi_vendo" class="btn btn-primary">
                        <i class="glyphicon glyphicon-refresh"></i> Refresh
                    </a>
                </div>

                <div class="table-wrap">
                    <table class="table table-bordered table-striped" id="juanfiVendos">
                        <thead>
                            <tr>
                                <th>Vendo Name</th>
                                <th>Vendo IP</th>
                                <th>Interface</th>
                                <th>Router</th>
                                <th>Status</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            {foreach $juanfiVendos as $v}
                                <tr>
                                    <td class="vendo-name">{$v.name|escape}</td>
                                    <td>{$v.ip|escape}</td>
                                    <td>{$v.interface|escape}</td>
                                    <td>{$v.router|escape}</td>
                                    <td>
                                        {if $v.online}
                                            <span class="status-badge online">
                                                <i class="glyphicon glyphicon-signal"></i> Online
                                            </span>
                                            <span class="latency">{$v.latency|escape}</span>
                                        {else}
                                            <span class="status-badge">
                                                <i class="glyphicon glyphicon-ban-circle"></i> Offline
                                            </span>
                                        {/if}
                                    </td>
                                    <td class="table-actions">
                                        <button type="button" class="btn btn-primary juanfi-rates-btn"
                                                data-toggle="modal" data-target="#juanfiRatesModal"
                                                data-router="{$v.router|escape}"
                                                data-ip="{$v.ip|escape}"
                                                data-name="{$v.name|escape}"
                                                title="Add or edit this vendo's wifi rates">
                                            <i class="fa fa-tags"></i> <span class="hidden-xs">Rates</span>
                                        </button>
                                        <button type="button" class="btn btn-warning juanfi-rename-btn"
                                                data-toggle="modal" data-target="#juanfiRenameModal"
                                                data-router="{$v.router|escape}"
                                                data-ip="{$v.ip|escape}"
                                                data-name="{$v.name|escape}"
                                                data-interface="{$v.interface|escape}"
                                                title="Edit name, IP and interface in config.js">
                                            <i class="fa fa-pencil"></i> <span class="hidden-xs">Edit</span>
                                        </button>
                                        <button type="button" class="btn btn-danger juanfi-delete-btn"
                                                data-toggle="modal" data-target="#juanfiDeleteModal"
                                                data-router="{$v.router|escape}"
                                                data-ip="{$v.ip|escape}"
                                                data-name="{$v.name|escape}"
                                                title="Delete this vendo from config.js">
                                            <i class="fa fa-trash"></i> <span class="hidden-xs">Delete</span>
                                        </button>
                                    </td>
                                </tr>
                            {foreachelse}
                                <tr>
                                    <td colspan="6" class="empty-state">
                                        <i class="glyphicon glyphicon-info-sign"></i> No vendos found in config.js.
                                    </td>
                                </tr>
                            {/foreach}
                        </tbody>
                    </table>
                </div>

                {if $juanfiVendoSources}
                    <p class="muted" style="margin:12px 0 0;color:var(--muted);font-size:12px;">
                        Read from:
                        {foreach $juanfiVendoSources as $s}{$s|escape}{if !$s@last} &middot; {/if}{/foreach}
                    </p>
                {/if}

            </div>
        </div>
    </div>
</div>

{* Edit vendo: Details saves into config.js; the other tabs save into the vendo itself *}
<div class="modal fade" id="juanfiRenameModal" tabindex="-1" role="dialog" aria-labelledby="juanfiRenameTitle">
    <div class="modal-dialog modal-lg" role="document">
        <form method="post" action="?_route=plugin/juanfi_vendo_edit" class="modal-content" autocomplete="off">
            <input type="hidden" name="csrf_token" value="{$csrf_token}">
            <input type="hidden" name="router" id="juanfiRenameRouter">
            <input type="hidden" name="old_ip" id="juanfiRenameOldIp">

            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="juanfiRenameTitle"><i class="fa fa-pencil"></i> Edit Vendo</h4>
            </div>

            <ul class="nav nav-tabs vs-tabs">
                <li class="active"><a href="#vsDetails" data-toggle="tab">Details</a></li>
                <li><a href="#vsPins" data-toggle="tab">Pins</a></li>
                <li><a href="#vsCoin" data-toggle="tab">Coin &amp; Ban</a></li>
                <li><a href="#vsRestart" data-toggle="tab">Auto Restart</a></li>
                <li><a href="#vsPass" data-toggle="tab">Passwords</a></li>
            </ul>

            <div class="modal-body">
                <div id="vsMsg" class="alert" style="display:none"></div>

                <div class="tab-content">
                    <div class="tab-pane active" id="vsDetails">
                        <p class="rename-ip">Router: <strong id="juanfiRenameRouterText"></strong></p>
                        <div class="form-group">
                            <label for="juanfiRenameName">Vendo Name</label>
                            <input type="text" class="form-control" name="name" id="juanfiRenameName" maxlength="40" required>
                        </div>
                        <div class="form-group">
                            <label for="juanfiRenameIp">Vendo IP</label>
                            <input type="text" class="form-control" name="ip" id="juanfiRenameIp" maxlength="45" required>
                        </div>
                        <div class="form-group">
                            <label for="juanfiRenameIface">Interface Name</label>
                            <input type="text" class="form-control" name="interface" id="juanfiRenameIface" maxlength="40">
                        </div>
                        <p class="rename-hint">Saved to config.js on the router (a backup config.js.bak is kept). Name: letters, numbers, spaces and - _ . # ( ). Interface: letters, numbers and - _ . : @ /.</p>
                    </div>

                    <div class="tab-pane" id="vsPins">
                        <div class="vs-grid" id="vsPinGrid"></div>
                        <p class="rename-hint">Pin choices follow the vendo's board. The same pin cannot be used twice (NONE is fine). Wrong pins can stop the coin slot from working.</p>
                    </div>

                    <div class="tab-pane" id="vsCoin">
                        <div class="form-group">
                            <label for="vsWait">Insert coin time (seconds)</label>
                            <input type="number" min="1" max="9999" class="form-control vs-input" id="vsWait">
                        </div>
                        <div class="form-group">
                            <label for="vsAbuse">Insert coin ban (attempts without a coin before the ban)</label>
                            <input type="number" min="0" max="999" class="form-control vs-input" id="vsAbuse">
                        </div>
                        <div class="form-group">
                            <label for="vsBan">Time to ban (minutes)</label>
                            <input type="number" min="0" max="99999" class="form-control vs-input" id="vsBan">
                        </div>
                    </div>

                    <div class="tab-pane" id="vsRestart">
                        <div class="form-group">
                            <label for="vsRestartVal">Auto restart</label>
                            <input type="number" min="0" max="9999" class="form-control vs-input" id="vsRestartVal">
                        </div>
                        <p class="rename-hint">0 = off. Other numbers are saved exactly as the vendo's own Auto Restart setting stores them.</p>
                    </div>

                    <div class="tab-pane" id="vsPass">
                        <div class="form-group">
                            <label for="vsAdminPw">New admin password</label>
                            <input type="password" class="form-control vs-input" id="vsAdminPw" autocomplete="new-password" maxlength="32">
                        </div>
                        <div class="form-group">
                            <label for="vsAdminPw2">Confirm admin password</label>
                            <input type="password" class="form-control vs-input" id="vsAdminPw2" autocomplete="new-password" maxlength="32">
                        </div>
                        <div class="form-group">
                            <label for="vsOpPw">New operator password</label>
                            <input type="password" class="form-control vs-input" id="vsOpPw" autocomplete="new-password" maxlength="32">
                        </div>
                        <div class="form-group">
                            <label for="vsOpPw2">Confirm operator password</label>
                            <input type="password" class="form-control vs-input" id="vsOpPw2" autocomplete="new-password" maxlength="32">
                        </div>
                        <p class="rename-hint">Leave a pair empty to keep that password. 4-32 characters, no spaces, no | or #. Current passwords are never shown here.</p>
                    </div>
                </div>

                <p class="rename-hint" id="vsRestartNote" style="display:none">Saving the Pins, Coin &amp; Ban, Auto Restart and Passwords tabs writes to the vendo and restarts it (about 1 minute).</p>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary" id="juanfiRenameSaveBtn"><i class="fa fa-save"></i> Save</button>
                <button type="button" class="btn btn-warning" id="vsSaveBtn" style="display:none"><i class="fa fa-save"></i> Save to Vendo</button>
            </div>
        </form>
    </div>
</div>

{* Delete vendo: removes the entry from multiVendoAddresses in config.js *}
<div class="modal fade" id="juanfiDeleteModal" tabindex="-1" role="dialog" aria-labelledby="juanfiDeleteTitle">
    <div class="modal-dialog" role="document">
        <form method="post" action="?_route=plugin/juanfi_vendo_delete" class="modal-content" autocomplete="off">
            <input type="hidden" name="csrf_token" value="{$csrf_token}">
            <input type="hidden" name="router" id="juanfiDeleteRouter">
            <input type="hidden" name="ip" id="juanfiDeleteIp">

            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="juanfiDeleteTitle"><i class="fa fa-trash"></i> Delete Vendo</h4>
            </div>
            <div class="modal-body">
                <p style="margin:0 0 8px">Delete <strong id="juanfiDeleteName"></strong> (<span id="juanfiDeleteIpText"></span>) from config.js on router <strong id="juanfiDeleteRouterText"></strong>?</p>
                <p class="rename-hint">The vendo is removed from the portal's list. A backup config.js.bak is kept. The last remaining vendo cannot be deleted.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger"><i class="fa fa-trash"></i> Delete</button>
            </div>
        </form>
    </div>
</div>

{* Add vendo: appends a new entry to multiVendoAddresses in config.js on the router's hotspot files *}
{if $juanfiVendoSettings}
<div class="modal fade" id="juanfiAddModal" tabindex="-1" role="dialog" aria-labelledby="juanfiAddTitle">
    <div class="modal-dialog" role="document">
        <form method="post" action="?_route=plugin/juanfi_vendo_add" class="modal-content" autocomplete="off">
            <input type="hidden" name="csrf_token" value="{$csrf_token}">

            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="juanfiAddTitle"><i class="glyphicon glyphicon-plus"></i> Add Vendo</h4>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="juanfiAddRouter">Router</label>
                    <select class="form-control" name="router" id="juanfiAddRouter">
                        {foreach $juanfiVendoSettings as $rname => $s}
                            <option value="{$rname|escape}">{$rname|escape}</option>
                        {/foreach}
                    </select>
                </div>
                <div class="form-group">
                    <label for="juanfiAddName">Vendo Name</label>
                    <input type="text" class="form-control" name="name" id="juanfiAddName" maxlength="40" placeholder="Vendo 3" required>
                </div>
                <div class="form-group">
                    <label for="juanfiAddIp">Vendo IP</label>
                    <input type="text" class="form-control" name="ip" id="juanfiAddIp" maxlength="45" placeholder="10.0.0.2" required>
                </div>
                <div class="form-group">
                    <label for="juanfiAddIface">Interface Name</label>
                    <input type="text" class="form-control" name="interface" id="juanfiAddIface" maxlength="40" placeholder="VLAN-12">
                </div>
                <p class="rename-hint">Added to the end of the list in config.js on the router (a backup config.js.bak is kept). Name: letters, numbers, spaces and - _ . # ( ). Interface: letters, numbers and - _ . : @ /.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-success"><i class="fa fa-save"></i> Add</button>
            </div>
        </form>
    </div>
</div>
{/if}

{* Wifi rates of one vendo: read / saved through the vendo's admin API, called by the router *}
<div class="modal fade" id="juanfiRatesModal" tabindex="-1" role="dialog" aria-labelledby="juanfiRatesTitle">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <input type="hidden" id="juanfiRatesCsrf" value="{$csrf_token}">

            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="juanfiRatesTitle"><i class="fa fa-tags"></i> Wifi Rates <small id="juanfiRatesVendo" style="color:rgba(255,255,255,.85)"></small></h4>
            </div>
            <div class="modal-body">
                <div id="juanfiRatesMsg" class="alert" style="display:none"></div>
                <div class="juanfi-rates-wrap">
                    <table class="table table-bordered" id="juanfiRatesTable">
                        <thead>
                            <tr>
                                <th>Rate Name</th>
                                <th>Price</th>
                                <th>Minutes <small>(or 1h, 1d)</small></th>
                                <th>Validity <small>(min, or 1d)</small></th>
                                <th>Data MB (optional)</th>
                                <th>User Profile (optional)</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="juanfiRatesBody"></tbody>
                    </table>
                </div>
                <button type="button" class="btn btn-success btn-sm" id="juanfiRatesAdd"><i class="fa fa-plus"></i> Add Rate</button>
                <p class="rename-hint">Saved straight to the vendo (rates are sorted by price). Name: letters, numbers, spaces and - _ . ( ). Minutes and Validity take minutes, or days / hours / minutes like <strong>90</strong>, <strong>1h</strong>, <strong>1h30m</strong>, <strong>1d</strong> or <strong>1d 12h</strong>; they are converted to minutes when you leave the field and when you save. Validity and data limit can stay empty.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="juanfiRatesSave"><i class="fa fa-save"></i> Save Rates</button>
            </div>
        </div>
    </div>
</div>

{* Gear: mode (VendoOption), pause time (showPauseTime), PisoWiFi name (Footer) and banner text (bannerText), saved into config.js on the router *}
{if $juanfiVendoSettings}
<div class="modal fade" id="juanfiSettingsModal" tabindex="-1" role="dialog" aria-labelledby="juanfiSettingsTitle">
    <div class="modal-dialog" role="document">
        <form method="post" action="?_route=plugin/juanfi_vendo_settings" class="modal-content" autocomplete="off">
            <input type="hidden" name="csrf_token" value="{$csrf_token}">

            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="juanfiSettingsTitle"><i class="glyphicon glyphicon-cog"></i> Vendo Settings</h4>
            </div>
            <div class="modal-body">
                <label for="juanfiSetRouter">Router</label>
                <select class="form-control" name="router" id="juanfiSetRouter">
                    {foreach $juanfiVendoSettings as $rname => $s}
                        <option value="{$rname|escape}" data-mode="{$s.mode}" data-pause="{$s.pause}"
                                data-footer="{$s.footer|escape}" data-footer-ok="{$s.footer_ok}"
                                data-banner="{$s.banner|escape}" data-banner-ok="{$s.banner_ok}">{$rname|escape}</option>
                    {/foreach}
                </select>

                <div class="settings-label">Vendo mode</div>
                <label class="juanfi-opt">
                    <input type="radio" name="mode" value="0">
                    <strong style="display:inline">Single vendo</strong>
                    <small>Uses the first vendo in the list only.</small>
                </label>
                <label class="juanfi-opt">
                    <input type="radio" name="mode" value="1">
                    <strong style="display:inline">Traditional</strong>
                    <small>The client chooses which vendo to use.</small>
                </label>
                <label class="juanfi-opt">
                    <input type="radio" name="mode" value="2">
                    <strong style="display:inline">Auto select</strong>
                    <small>Picks the vendo by hotspot interface name (one hotspot server IP only).</small>
                </label>

                <div class="settings-label">Show pause time</div>
                <div id="juanfiPauseBox">
                    <label class="juanfi-opt" style="display:inline-block;margin-right:8px">
                        <input type="radio" name="pause" value="1"> Show
                    </label>
                    <label class="juanfi-opt" style="display:inline-block">
                        <input type="radio" name="pause" value="0"> Hide
                    </label>
                </div>
                <p class="rename-hint" id="juanfiPauseNone" style="display:none;font-size:11px;color:#71819a">
                    This router's config.js has no showPauseTime line, so it cannot be changed here.
                </p>

                <div class="settings-label">Portal texts</div>
                <label class="settings-field" for="juanfiSetFooter">PisoWiFi name (Footer)</label>
                <input type="text" class="form-control" name="footer" id="juanfiSetFooter" maxlength="60" required>
                <label class="settings-field" for="juanfiSetBanner">Banner text (scrolling)</label>
                <input type="text" class="form-control" name="banner" id="juanfiSetBanner" maxlength="120" required>
                <p class="rename-hint" id="juanfiTextNone" style="display:none;font-size:11px;color:#71819a">
                    This router's config.js has no Footer / bannerText line, so that field cannot be changed here.
                </p>
                <p class="rename-hint" style="font-size:11px;color:#71819a">
                    The name is the big one-line name and the footer of the portal. The banner text scrolls under it. You can use &amp;bull; for a dot.
                </p>

                <p class="rename-hint" style="font-size:11px;color:#71819a;margin-top:10px">
                    Saved to config.js on the router (a backup config.js.bak is kept).
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Save</button>
            </div>
        </form>
    </div>
</div>
{/if}

{literal}
<script>
// Fill the edit / delete modals from the clicked row (plain JS, runs before the modal opens).
document.addEventListener('click', function (e) {
    var t = e.target.closest ? e.target : null;
    if (!t) { return; }

    var btn = t.closest('.juanfi-rename-btn');
    if (btn) {
        document.getElementById('juanfiRenameRouter').value = btn.getAttribute('data-router');
        document.getElementById('juanfiRenameOldIp').value = btn.getAttribute('data-ip');
        document.getElementById('juanfiRenameRouterText').textContent = btn.getAttribute('data-router');
        document.getElementById('juanfiRenameIp').value = btn.getAttribute('data-ip');
        document.getElementById('juanfiRenameIface').value = btn.getAttribute('data-interface');
        var input = document.getElementById('juanfiRenameName');
        input.value = btn.getAttribute('data-name');
        setTimeout(function () { input.focus(); input.select(); }, 300);
        return;
    }

    var del = t.closest('.juanfi-delete-btn');
    if (del) {
        document.getElementById('juanfiDeleteRouter').value = del.getAttribute('data-router');
        document.getElementById('juanfiDeleteIp').value = del.getAttribute('data-ip');
        document.getElementById('juanfiDeleteRouterText').textContent = del.getAttribute('data-router');
        document.getElementById('juanfiDeleteIpText').textContent = del.getAttribute('data-ip');
        document.getElementById('juanfiDeleteName').textContent = del.getAttribute('data-name');
    }
});
// Rates modal: load, edit and save a vendo's wifi rates (the router calls the vendo for us).
(function () {
    var body = document.getElementById('juanfiRatesBody');
    if (!body) { return; }
    var msg = document.getElementById('juanfiRatesMsg');
    var saveBtn = document.getElementById('juanfiRatesSave');
    var addBtn = document.getElementById('juanfiRatesAdd');
    var ctx = { router: '', ip: '' };
    // dur: true = the value is a time; it can be typed as minutes or as days / hours / minutes.
    var fields = [
        { key: 'name', label: 'name', type: 'text', ph: '1 Hour' },
        { key: 'price', label: 'price', type: 'number', ph: '5' },
        { key: 'minutes', label: 'minutes', type: 'text', dur: true, ph: '60 or 1h' },
        { key: 'validity', label: 'validity', type: 'text', dur: true, ph: 'e.g. 1d' },
        { key: 'data', label: 'data limit', type: 'number', ph: '' },
        { key: 'profile', label: 'user profile', type: 'text', ph: '' }
    ];

    function showMsg(kind, text) {
        msg.className = 'alert alert-' + kind;
        msg.textContent = text || '';
        msg.style.display = text ? 'block' : 'none';
    }

    /**
     * "90" -> "90", "1h" -> "60", "1h30m" -> "90", "1d" -> "1440", "1d 12h" -> "2160", "1.5h" -> "90".
     * Returns '' for an empty text and null when the text is not a time.
     */
    function toMinutes(text) {
        var s = String(text == null ? '' : text).trim().toLowerCase();
        if (s === '') { return ''; }
        if (/^\d+$/.test(s)) { return s; }

        var re = /(\d+(?:\.\d+)?)\s*(days?|d|hours?|hrs?|hr|h|minutes?|mins?|min|m)(?![a-z])/g;
        var total = 0, found = false, m;
        while ((m = re.exec(s)) !== null) {
            found = true;
            var unit = m[2].charAt(0);
            total += parseFloat(m[1]) * (unit === 'd' ? 1440 : (unit === 'h' ? 60 : 1));
        }
        if (!found) { return null; }
        // Nothing but spaces, commas and "and" may be left between the parts.
        if (s.replace(re, '').replace(/and/g, '').replace(/[\s,]/g, '') !== '') { return null; }
        return String(Math.round(total));
    }

    function addRow(r) {
        r = r || {};
        var tr = document.createElement('tr');
        fields.forEach(function (f) {
            var td = document.createElement('td');
            var input = document.createElement('input');
            input.className = 'form-control';
            input.type = f.type;
            if (f.type === 'number') { input.min = '0'; input.step = '1'; }
            input.setAttribute('data-col', f.key);
            input.placeholder = f.ph;
            input.value = r[f.key] == null ? '' : r[f.key];
            if (f.dur) {
                input.setAttribute('data-dur', '1');
                input.autocomplete = 'off';
                // Convert days / hours to minutes as soon as the field is left.
                input.addEventListener('change', function () {
                    var v = toMinutes(input.value);
                    if (v === null) {
                        input.style.borderColor = '#d9534f';
                    } else {
                        input.style.borderColor = '';
                        input.value = v;
                    }
                });
            }
            td.appendChild(input);
            tr.appendChild(td);
        });
        var td = document.createElement('td');
        var del = document.createElement('button');
        del.type = 'button';
        del.className = 'btn btn-danger btn-xs';
        del.title = 'Delete this rate';
        del.innerHTML = '<i class="fa fa-trash"></i>';
        del.addEventListener('click', function () { tr.parentNode.removeChild(tr); });
        td.appendChild(del);
        tr.appendChild(td);
        body.appendChild(tr);
    }

    function render(rates) {
        body.innerHTML = '';
        (rates || []).forEach(addRow);
    }

    // Returns { rows: [...], error: '' }. Time fields are converted to minutes here; a bad time stops the save.
    function collect() {
        var rows = [];
        var trs = body.getElementsByTagName('tr');
        for (var i = 0; i < trs.length; i++) {
            var row = {}, any = false;
            var inputs = trs[i].querySelectorAll('input[data-col]');
            for (var j = 0; j < inputs.length; j++) {
                var key = inputs[j].getAttribute('data-col');
                var val = inputs[j].value.trim();
                if (inputs[j].getAttribute('data-dur')) {
                    var conv = toMinutes(val);
                    if (conv === null) {
                        inputs[j].style.borderColor = '#d9534f';
                        return {
                            rows: [],
                            error: 'Rate ' + (rows.length + 1) + ': ' + key + ' must be minutes or days / hours / minutes, like 90, 1h30m or 1d.'
                        };
                    }
                    inputs[j].style.borderColor = '';
                    inputs[j].value = conv;
                    val = conv;
                }
                row[key] = val;
                if (val !== '') { any = true; }
            }
            if (any) { rows.push(row); }   // completely empty rows are ignored
        }
        return { rows: rows, error: '' };
    }

    function busy(on) {
        saveBtn.disabled = on;
        addBtn.disabled = on;
    }

    function load() {
        busy(true);
        body.innerHTML = '';
        showMsg('info', 'Loading rates from the vendo...');
        var url = '?_route=plugin/juanfi_vendo_rates_get&router=' + encodeURIComponent(ctx.router)
            + '&ip=' + encodeURIComponent(ctx.ip);
        fetch(url, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.ok) {
                    render(d.rates);
                    showMsg('', '');
                    busy(false);
                    addBtn.disabled = false;
                } else {
                    showMsg('danger', d.error || 'Could not read the rates.');
                }
            })
            .catch(function () { showMsg('danger', 'Could not read the rates (unexpected answer from the server).'); });
    }

    function save() {
        var c = collect();
        if (c.error) {
            showMsg('danger', c.error);
            return;
        }
        var fd = new FormData();
        fd.append('csrf_token', document.getElementById('juanfiRatesCsrf').value);
        fd.append('router', ctx.router);
        fd.append('ip', ctx.ip);
        fd.append('rates', JSON.stringify(c.rows));
        busy(true);
        showMsg('info', 'Saving to the vendo...');
        fetch('?_route=plugin/juanfi_vendo_rates_save', { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.rates) { render(d.rates); }
                showMsg(d.ok ? 'success' : 'danger', d.ok ? d.message : (d.error || 'Save failed.'));
                busy(false);
            })
            .catch(function () {
                showMsg('danger', 'Save failed (unexpected answer from the server).');
                busy(false);
            });
    }

    document.addEventListener('click', function (e) {
        var t = e.target.closest ? e.target.closest('.juanfi-rates-btn') : null;
        if (!t) { return; }
        ctx.router = t.getAttribute('data-router');
        ctx.ip = t.getAttribute('data-ip');
        document.getElementById('juanfiRatesVendo').textContent = '- ' + t.getAttribute('data-name') + ' (' + ctx.ip + ')';
        load();
    });
    addBtn.addEventListener('click', function () { addRow({}); });
    saveBtn.addEventListener('click', save);
})();
// Gear modal: show the chosen router's current mode, pause time, PisoWiFi name and banner text.
(function () {
    var sel = document.getElementById('juanfiSetRouter');
    if (!sel) { return; }
    function fill() {
        var o = sel.options[sel.selectedIndex];
        var mode = o.getAttribute('data-mode');
        var pause = o.getAttribute('data-pause');
        var modes = document.getElementsByName('mode');
        for (var i = 0; i < modes.length; i++) { modes[i].checked = (modes[i].value === mode); }
        var pauses = document.getElementsByName('pause');
        for (var j = 0; j < pauses.length; j++) {
            pauses[j].checked = (pauses[j].value === pause);
            pauses[j].disabled = (pause === '');
        }
        document.getElementById('juanfiPauseNone').style.display = (pause === '') ? 'block' : 'none';

        // Portal texts (disabled fields are not sent, so the PHP leaves that setting alone)
        var fOk = o.getAttribute('data-footer-ok') === '1';
        var bOk = o.getAttribute('data-banner-ok') === '1';
        var fIn = document.getElementById('juanfiSetFooter');
        var bIn = document.getElementById('juanfiSetBanner');
        fIn.value = fOk ? o.getAttribute('data-footer') : '';
        bIn.value = bOk ? o.getAttribute('data-banner') : '';
        fIn.disabled = !fOk;
        bIn.disabled = !bOk;
        document.getElementById('juanfiTextNone').style.display = (fOk && bOk) ? 'none' : 'block';
    }
    sel.addEventListener('change', fill);
    fill();
})();

// Edit Vendo modal: extra tabs that read / save the vendo's own system settings.
(function () {
    var modal = document.getElementById('juanfiRenameModal');
    if (!modal) { return; }
    var msg = document.getElementById('vsMsg');
    var saveBtn = document.getElementById('vsSaveBtn');
    var detailsBtn = document.getElementById('juanfiRenameSaveBtn');
    var note = document.getElementById('vsRestartNote');
    var pinBox = document.getElementById('vsPinGrid');
    var ctx = { router: '', ip: '' }, loaded = false, loading = false;

    function $id(id) { return document.getElementById(id); }

    function showMsg(kind, text) {
        msg.className = 'alert alert-' + kind;
        msg.textContent = text || '';
        msg.style.display = text ? 'block' : 'none';
    }

    function setTab(isDetails) {
        detailsBtn.style.display = isDetails ? '' : 'none';
        saveBtn.style.display = isDetails ? 'none' : '';
        note.style.display = isDetails ? 'none' : 'block';
        msg.style.display = (isDetails || !msg.textContent) ? 'none' : 'block';
    }

    function showDetailsTab() {
        var lis = modal.querySelectorAll('.vs-tabs li');
        var panes = modal.querySelectorAll('.tab-pane');
        for (var i = 0; i < lis.length; i++) { lis[i].className = i === 0 ? 'active' : ''; }
        for (var j = 0; j < panes.length; j++) { panes[j].className = 'tab-pane' + (j === 0 ? ' active' : ''); }
        setTab(true);
    }

    function clearPasswords() {
        ['vsAdminPw', 'vsAdminPw2', 'vsOpPw', 'vsOpPw2'].forEach(function (id) { $id(id).value = ''; });
    }

    function fill(d) {
        pinBox.innerHTML = '';
        d.pins.forEach(function (p) {
            var g = document.createElement('div');
            g.className = 'form-group';
            var l = document.createElement('label');
            l.textContent = p.label;
            var s = document.createElement('select');
            s.className = 'form-control vs-input';
            s.setAttribute('data-pin', p.key);
            var seen = false;
            d.options.forEach(function (o) {
                var op = document.createElement('option');
                op.value = o.v;
                op.textContent = o.l;
                if (o.v === p.value) { seen = true; }
                s.appendChild(op);
            });
            if (!seen) {   // a value the list does not know: keep it instead of losing it
                var cur = document.createElement('option');
                cur.value = p.value;
                cur.textContent = 'Current (' + p.value + ')';
                s.appendChild(cur);
            }
            s.value = p.value;
            g.appendChild(l);
            g.appendChild(s);
            pinBox.appendChild(g);
        });
        $id('vsWait').value = d.values.wait;
        $id('vsAbuse').value = d.values.abuse;
        $id('vsBan').value = d.values.ban;
        $id('vsRestartVal').value = d.values.restart;
    }

    function load() {
        if (loaded || loading) { return; }
        loading = true;
        saveBtn.disabled = true;
        showMsg('info', 'Reading settings from the vendo...');
        var url = '?_route=plugin/juanfi_vendo_cfg_get&router=' + encodeURIComponent(ctx.router)
            + '&ip=' + encodeURIComponent(ctx.ip);
        fetch(url, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                loading = false;
                if (d.ok) {
                    fill(d);
                    loaded = true;
                    saveBtn.disabled = false;
                    showMsg('', '');
                } else {
                    showMsg('danger', d.error || 'Could not read the vendo settings.');
                }
            })
            .catch(function () {
                loading = false;
                showMsg('danger', 'Could not read the vendo settings (unexpected answer from the server).');
            });
    }

    function save() {
        if (!loaded) { return; }
        if ($id('vsAdminPw').value !== $id('vsAdminPw2').value) { showMsg('danger', 'Admin passwords do not match.'); return; }
        if ($id('vsOpPw').value !== $id('vsOpPw2').value) { showMsg('danger', 'Operator passwords do not match.'); return; }
        if (!confirm('Saving writes these settings to the vendo and restarts it (about 1 minute). Continue?')) { return; }

        var pins = {};
        Array.prototype.forEach.call(pinBox.querySelectorAll('select[data-pin]'), function (s) {
            pins[s.getAttribute('data-pin')] = s.value;
        });

        var fd = new FormData();
        fd.append('csrf_token', modal.querySelector('input[name="csrf_token"]').value);
        fd.append('router', ctx.router);
        fd.append('ip', ctx.ip);
        fd.append('wait', $id('vsWait').value);
        fd.append('abuse', $id('vsAbuse').value);
        fd.append('ban', $id('vsBan').value);
        fd.append('restart', $id('vsRestartVal').value);
        fd.append('pins', JSON.stringify(pins));
        fd.append('admin_pw', $id('vsAdminPw').value);
        fd.append('admin_pw2', $id('vsAdminPw2').value);
        fd.append('op_pw', $id('vsOpPw').value);
        fd.append('op_pw2', $id('vsOpPw2').value);

        saveBtn.disabled = true;
        showMsg('info', 'Saving to the vendo...');
        fetch('?_route=plugin/juanfi_vendo_cfg_save', { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                saveBtn.disabled = false;
                if (d.ok) { clearPasswords(); }
                showMsg(d.ok ? (d.warn ? 'warning' : 'success') : 'danger', d.ok ? d.message : (d.error || 'Save failed.'));
            })
            .catch(function () {
                saveBtn.disabled = false;
                showMsg('danger', 'Save failed (unexpected answer from the server).');
            });
    }

    document.addEventListener('click', function (e) {
        var t = e.target.closest ? e.target : null;
        if (!t) { return; }

        var edit = t.closest('.juanfi-rename-btn');
        if (edit) {                         // modal is opening for another vendo: start clean
            ctx.router = edit.getAttribute('data-router');
            ctx.ip = edit.getAttribute('data-ip');
            loaded = false;
            loading = false;
            pinBox.innerHTML = '';
            clearPasswords();
            showMsg('', '');
            showDetailsTab();
            return;
        }

        var tab = t.closest('#juanfiRenameModal .vs-tabs a');
        if (tab) {
            var isDetails = tab.getAttribute('href') === '#vsDetails';
            setTab(isDetails);
            if (!isDetails) { load(); }
        }
    });

    // Enter in a settings field must not submit the Details form.
    modal.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && e.target.classList && e.target.classList.contains('vs-input')) { e.preventDefault(); }
    });

    saveBtn.addEventListener('click', save);
})();
</script>
{/literal}

{include file="sections/footer.tpl"}
