{include file="sections/header.tpl"}

{literal}
<style>
.juanfi-srv {
    --bg: #fff; --text: #243650; --head: #172b4d; --border: #dce5f1;
    --th: #f0f5fc; --stripe: #f8fbff; --hover: #edf5ff; --muted: #71819a; --host: #1d3d69;
    color: var(--head); padding-bottom: 25px;
}
body.dark-mode .juanfi-srv {
    --bg: #182235; --text: #e2e8f0; --head: #e2e8f0; --border: #37465e;
    --th: #202d42; --stripe: #202d42; --hover: #202d42; --host: #a9ccff;
}
.juanfi-srv .panel { border: 0; border-radius: 10px; overflow: hidden; background: var(--bg); box-shadow: 0 4px 18px rgba(24,64,120,.07); }
.juanfi-srv .panel-heading { padding: 22px 24px; border: 0; color: #fff; background: linear-gradient(115deg,#075bd8,#398df5); }
.juanfi-srv .panel-body { padding: 20px; background: var(--bg); }
.juanfi-srv .page-heading { display: flex; align-items: center; gap: 14px; }
.juanfi-srv .heading-icon { width: 52px; height: 52px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; border-radius: 12px; font-size: 24px; background: rgba(255,255,255,.18); }
.juanfi-srv .heading-title { margin: 0 0 5px; font-size: 23px; font-weight: 700; color: #fff; }
.juanfi-srv .heading-subtitle { margin: 0; font-size: 13px; color: rgba(255,255,255,.85); }
.juanfi-srv .toolbar { display: flex; justify-content: flex-end; gap: 8px; margin-bottom: 14px; }
.juanfi-srv .toolbar .btn { padding: 9px 16px; border-radius: 7px; font-weight: 600; }
.juanfi-srv .table-wrap { overflow-x: auto; border: 1px solid var(--border); border-radius: 8px; }
.juanfi-srv #juanfiSrvTable { width: 100%; min-width: 820px; margin: 0; border: 0; font-size: 12px; color: var(--text); background: var(--bg); }
.juanfi-srv #juanfiSrvTable th, .juanfi-srv #juanfiSrvTable td { padding: 12px; white-space: nowrap; vertical-align: middle; border-color: var(--border); }
.juanfi-srv #juanfiSrvTable thead th { border-top: 0; background: var(--th); color: var(--head); font-weight: 700; }
.juanfi-srv #juanfiSrvTable tbody tr:nth-child(even) { background: var(--stripe); }
.juanfi-srv #juanfiSrvTable tbody tr:hover { background: var(--hover); }
.juanfi-srv .vlan-name { font-weight: 600; color: var(--host); }
.juanfi-srv .pill { display: inline-block; padding: 4px 10px; border-radius: 14px; font-size: 11px; font-weight: 700; color: #fff; background: #7d8ba0; }
.juanfi-srv .pill.ok { background: #19a64c; }
.juanfi-srv .table-actions { text-align: center; }
.juanfi-srv .empty-state { padding: 35px 15px !important; text-align: center; color: var(--muted); }
.juanfi-srv .alert { margin-bottom: 12px; border-radius: 7px; }

#juanfiSrvAddModal .modal-content, #juanfiSrvDelModal .modal-content { border: 0; border-radius: 10px; overflow: hidden; }
#juanfiSrvAddModal .modal-header { border: 0; color: #fff; background: linear-gradient(115deg,#0f8a43,#2fc873); }
#juanfiSrvDelModal .modal-header { border: 0; color: #fff; background: linear-gradient(115deg,#c62828,#ef5350); }
#juanfiSrvAddModal .modal-header .close, #juanfiSrvDelModal .modal-header .close { color: #fff; opacity: .85; text-shadow: none; }
#juanfiSrvAddModal .modal-body, #juanfiSrvDelModal .modal-body { padding: 20px; }
#juanfiSrvAddModal .modal-footer, #juanfiSrvDelModal .modal-footer { border-top: 1px solid rgba(113,129,154,.25); }
#juanfiSrvAddModal .srv-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0 14px; }
#juanfiSrvAddModal .form-group { display: block; margin: 0 0 14px; padding: 0; }
#juanfiSrvAddModal .form-group::before, #juanfiSrvAddModal .form-group::after { display: none; }
#juanfiSrvAddModal .form-group label { display: block !important; float: none !important; width: 100% !important; padding: 0 !important; margin: 0 0 6px !important; text-align: left !important; font-weight: 600; }
#juanfiSrvAddModal .form-control { display: block !important; float: none !important; width: 100% !important; height: 40px; padding: 6px 12px; border-radius: 7px; box-sizing: border-box; }
#juanfiSrvAddModal .hint, #juanfiSrvDelModal .hint { margin: 8px 0 0; font-size: 11px; color: #71819a; }
body.dark-mode #juanfiSrvAddModal .modal-content, body.dark-mode #juanfiSrvDelModal .modal-content { background: #182235; color: #e2e8f0; }
body.dark-mode #juanfiSrvAddModal .form-control { background: #202d42; border-color: #37465e; color: #e2e8f0; }
@media (max-width: 600px) { #juanfiSrvAddModal .srv-grid { grid-template-columns: 1fr; } }

/* Keep the modals above the theme's header / sidebar and inside the screen */
#juanfiSrvAddModal, #juanfiSrvDelModal { z-index: 20050 !important; }
.modal-backdrop { z-index: 20040 !important; }

#juanfiSrvAddModal .modal-dialog { margin: 30px auto; }
#juanfiSrvAddModal .modal-content { display: flex; flex-direction: column; max-height: calc(100vh - 60px); }
#juanfiSrvAddModal .modal-header, #juanfiSrvAddModal .modal-footer { flex: 0 0 auto; }
#juanfiSrvAddModal .modal-body { flex: 1 1 auto; overflow-y: auto; }

#juanfiSrvAddModal .srv-grid > * { min-width: 0; }
#juanfiSrvAddModal .form-control { max-width: 100%; text-overflow: ellipsis; }
#juanfiSrvAddModal .form-group label { white-space: normal; }
</style>
{/literal}

<div class="row juanfi-srv">
    <div class="col-sm-12">
        <div class="panel panel-primary panel-hovered panel-stacked mb30">
            <div class="panel-heading">
                <div class="page-heading">
                    <div class="heading-icon"><i class="glyphicon glyphicon-transfer"></i></div>
                    <div>
                        <h3 class="heading-title">Server Setup</h3>
                        <p class="heading-subtitle">Create a VLAN with its IP, address list, DHCP server and hotspot server in one step.</p>
                    </div>
                </div>
            </div>

            <div class="panel-body">
                {foreach $juanfiSrvErrors as $e}
                    <div class="alert alert-warning"><i class="glyphicon glyphicon-warning-sign"></i> {$e|escape}</div>
                {/foreach}

                <div class="toolbar">
                    {if $juanfiSrvRouters}
                        <button type="button" class="btn btn-success" data-toggle="modal" data-target="#juanfiSrvAddModal">
                            <i class="glyphicon glyphicon-plus"></i> Add VLAN Server
                        </button>
                    {/if}
                    <a href="?_route=plugin/juanfi_server" class="btn btn-primary"><i class="glyphicon glyphicon-refresh"></i> Refresh</a>
                </div>

                <div class="table-wrap">
                    <table class="table table-bordered table-striped" id="juanfiSrvTable">
                        <thead>
                            <tr>
                                <th>VLAN</th><th>ID</th><th>Parent</th><th>Router</th>
                                <th>Gateway</th><th>DHCP</th><th>Hotspot</th><th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            {foreach $juanfiSrvRows as $v}
                                <tr>
                                    <td class="vlan-name">{$v.name|escape}{if $v.disabled} <span class="pill">disabled</span>{/if}</td>
                                    <td>{$v.vlan_id|escape}</td>
                                    <td>{$v.parent|escape}</td>
                                    <td>{$v.router|escape}</td>
                                    <td>{if $v.address}{$v.address|escape}{else}-{/if}</td>
                                    <td>{if $v.dhcp}<span class="pill ok">{$v.dhcp|escape}</span>{else}<span class="pill">none</span>{/if}</td>
                                    <td>{if $v.hotspot}<span class="pill ok">{$v.hotspot|escape}</span>{else}<span class="pill">none</span>{/if}</td>
                                    <td class="table-actions">
                                        {if $v.managed}
                                            <button type="button" class="btn btn-danger btn-xs juanfi-srv-del"
                                                    data-toggle="modal" data-target="#juanfiSrvDelModal"
                                                    data-router="{$v.router|escape}" data-name="{$v.name|escape}">
                                                <i class="fa fa-trash"></i> Delete
                                            </button>
                                        {else}
                                            <span style="color:var(--muted)">manual</span>
                                        {/if}
                                    </td>
                                </tr>
                            {foreachelse}
                                <tr><td colspan="8" class="empty-state"><i class="glyphicon glyphicon-info-sign"></i> No VLANs found.</td></tr>
                            {/foreach}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

{if $juanfiSrvRouters}
<div class="modal fade" id="juanfiSrvAddModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <form method="post" action="?_route=plugin/juanfi_server_add" class="modal-content" autocomplete="off"
              id="juanfiSrvForm" data-routers="{$juanfiSrvData|escape}">
            <input type="hidden" name="csrf_token" value="{$csrf_token}">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title"><i class="glyphicon glyphicon-plus"></i> Add VLAN Server</h4>
            </div>
            <div class="modal-body">
                <div class="srv-grid">
                    <div class="form-group">
                        <label for="srvRouter">Router</label>
                        <select class="form-control" name="router" id="srvRouter">
                            {foreach $juanfiSrvRouters as $rn}<option value="{$rn|escape}">{$rn|escape}</option>{/foreach}
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="srvParent">Parent interface</label>
                        <select class="form-control" name="parent" id="srvParent"></select>
                    </div>
                    <div class="form-group">
                        <label for="srvVlanId">VLAN ID</label>
                        <input type="number" min="1" max="4094" class="form-control" name="vlan_id" id="srvVlanId" placeholder="55" required>
                    </div>
                    <div class="form-group">
                        <label for="srvName">VLAN / interface name</label>
                        <input type="text" class="form-control" name="name" id="srvName" maxlength="15" placeholder="VLAN55">
                    </div>
                    <div class="form-group">
                        <label for="srvGateway">Gateway IP / prefix</label>
                        <input type="text" class="form-control" name="gateway" id="srvGateway" maxlength="18" placeholder="10.2.1.1/24" required>
                    </div>
                    <div class="form-group">
                        <label for="srvDns">DNS servers (optional)</label>
                        <input type="text" class="form-control" name="dns" id="srvDns" maxlength="60" placeholder="gateway">
                    </div>
                    <div class="form-group">
                        <label for="srvPoolStart">DHCP pool start (optional)</label>
                        <input type="text" class="form-control" name="pool_start" id="srvPoolStart" maxlength="15" placeholder="auto: gateway + 1">
                    </div>
                    <div class="form-group">
                        <label for="srvPoolEnd">DHCP pool end (optional)</label>
                        <input type="text" class="form-control" name="pool_end" id="srvPoolEnd" maxlength="15" placeholder="auto: last address">
                    </div>
                    <div class="form-group">
                        <label for="srvLease">Lease time</label>
                        <input type="text" class="form-control" name="lease" id="srvLease" maxlength="10" value="1d">
                    </div>
                    <div class="form-group">
                        <label for="srvList">Address list name</label>
                        <input type="text" class="form-control" name="list" id="srvList" maxlength="30" value="JuanfiVlan">
                    </div>
                    <div class="form-group" style="grid-column: 1 / -1">
                        <label for="srvProfile">Hotspot profile to copy</label>
                        <select class="form-control" name="profile" id="srvProfile"></select>
                    </div>
                </div>
                <p class="hint">Creates: VLAN, IP address, address-list entry for the network, DHCP pool / network / server, and a hotspot profile (copy of the one above, with this gateway as hotspot-address) plus the hotspot server. Everything is commented BILLSYFI. If a step already exists it is skipped, so you can press Add again after fixing an error.</p>
                <p class="hint">The VLAN name is what the hotspot reports as the interface, so use the same name as the vendo's "Interface Name".</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-success"><i class="fa fa-save"></i> Create</button>
            </div>
        </form>
    </div>
</div>
{/if}

<div class="modal fade" id="juanfiSrvDelModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <form method="post" action="?_route=plugin/juanfi_server_delete" class="modal-content" autocomplete="off">
            <input type="hidden" name="csrf_token" value="{$csrf_token}">
            <input type="hidden" name="router" id="srvDelRouter">
            <input type="hidden" name="name" id="srvDelName">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title"><i class="fa fa-trash"></i> Delete VLAN Server</h4>
            </div>
            <div class="modal-body">
                <p style="margin:0 0 8px">Delete <strong id="srvDelText"></strong> and its hotspot, DHCP server, pool, address-list entry and IP address?</p>
                <p class="hint">Customers on this VLAN lose their connection, and any vendo using this interface name stops working until it is moved.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger"><i class="fa fa-trash"></i> Delete</button>
            </div>
        </form>
    </div>
</div>

{literal}
<script>
document.addEventListener('click', function (e) {
    var b = e.target.closest ? e.target.closest('.juanfi-srv-del') : null;
    if (!b) { return; }
    document.getElementById('srvDelRouter').value = b.getAttribute('data-router');
    document.getElementById('srvDelName').value = b.getAttribute('data-name');
    document.getElementById('srvDelText').textContent = b.getAttribute('data-name') + ' (' + b.getAttribute('data-router') + ')';
});

(function () {
    var form = document.getElementById('juanfiSrvForm');
    if (!form) { return; }
    var data = {};
    try { data = JSON.parse(form.getAttribute('data-routers') || '{}'); } catch (e) {}

    var router = document.getElementById('srvRouter');
    var parent = document.getElementById('srvParent');
    var profile = document.getElementById('srvProfile');
    var vid = document.getElementById('srvVlanId');
    var name = document.getElementById('srvName');
    var gw = document.getElementById('srvGateway');
    var dirty = { name: false, gw: false };

    function fillSelect(sel, items, preferred) {
        sel.innerHTML = '';
        (items || []).forEach(function (v) {
            var o = document.createElement('option');
            o.value = v; o.textContent = v;
            sel.appendChild(o);
        });
        if (preferred && (items || []).indexOf(preferred) >= 0) { sel.value = preferred; }
    }
    function fillRouter() {
        var d = data[router.value] || { parents: [], profiles: [] };
        fillSelect(parent, d.parents);
        fillSelect(profile, d.profiles, 'default');
    }
    router.addEventListener('change', fillRouter);
    fillRouter();

    name.addEventListener('input', function () { dirty.name = true; });
    gw.addEventListener('input', function () { dirty.gw = true; });
    // Suggestions only: edit them if your numbering is different.
    vid.addEventListener('input', function () {
        var n = parseInt(vid.value, 10);
        if (!n) { return; }
        if (!dirty.name) { name.value = 'VLAN' + n; }
        if (!dirty.gw) { gw.value = '10.' + (n % 256) + '.1.1/24'; }
    });
})();
</script>
{/literal}

{include file="sections/footer.tpl"}