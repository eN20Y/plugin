{include file="sections/header.tpl"}

{literal}
<style>
.juanfi-page {
    --bg: #fff; --text: #243650; --head: #172b4d; --border: #dce5f1;
    --th: #f0f5fc; --stripe: #f8fbff; --hover: #edf5ff; --soft: #f4f8ff;
    --muted: #71819a; --link: #23416b; --host: #1d3d69;
    color: var(--head);
    padding-bottom: 25px;
}
body.dark-mode .juanfi-page {
    --bg: #182235; --text: #e2e8f0; --head: #e2e8f0; --border: #37465e;
    --th: #202d42; --stripe: #202d42; --hover: #202d42; --soft: #202d42;
    --link: #e2e8f0; --host: #a9ccff;
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

/* Heading */
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

/* Filters */
.juanfi-page .juanfi-filters {
    display: grid;
    grid-template-columns: minmax(220px, 1.7fr) minmax(125px, .7fr) minmax(145px, .8fr) auto auto auto;
    gap: 10px;
    align-items: center;
    margin-bottom: 18px;
}
.juanfi-page .form-control {
    height: 42px;
    border: 1px solid var(--border);
    border-radius: 7px;
    box-shadow: none;
    color: var(--text);
    background: var(--bg);
}
.juanfi-page .form-control:focus {
    border-color: #2780f5;
    box-shadow: 0 0 0 3px rgba(39,128,245,.1);
}
.juanfi-page .btn {
    height: 42px;
    padding: 9px 16px;
    border-radius: 7px;
    font-weight: 600;
    white-space: nowrap;
}
.juanfi-page .btn-primary { background: #0878f9; border-color: #0878f9; }
.juanfi-page .btn-primary:hover { background: #0664d5; border-color: #0664d5; }
.juanfi-page .btn-default {
    background: var(--th);
    border-color: var(--border);
    color: var(--text);
}
.juanfi-page .result-count {
    padding: 12px;
    border: 1px solid var(--border);
    border-radius: 7px;
    background: var(--soft);
    color: var(--text);
    text-align: center;
    font-size: 12px;
    font-weight: 600;
    white-space: nowrap;
}

/* Table */
.juanfi-page .table-wrap {
    overflow-x: auto;
    border: 1px solid var(--border);
    border-radius: 8px;
}
.juanfi-page #juanfiUsers {
    width: 100%;
    min-width: 1000px;
    margin: 0;
    border: 0;
    font-size: 12px;
    color: var(--text);
    background: var(--bg);
}
.juanfi-page #juanfiUsers th,
.juanfi-page #juanfiUsers td {
    padding: 12px;
    white-space: nowrap;
    vertical-align: middle;
    border-color: var(--border);
}
.juanfi-page #juanfiUsers thead th {
    padding: 14px 12px;
    border-top: 0;
    background: var(--th);
    color: var(--head);
    font-weight: 700;
}
.juanfi-page #juanfiUsers tbody tr:nth-child(even) { background: var(--stripe); }
.juanfi-page #juanfiUsers tbody tr:hover { background: var(--hover); }
.juanfi-page .user-hostname { font-weight: 600; color: var(--host); }

.juanfi-page .time-left { font-weight: 700; font-variant-numeric: tabular-nums; }
.juanfi-page .time-left.active { color: #159447; }
.juanfi-page .time-left.expired { color: #dc2626; }
.juanfi-page .time-left.unlimited { color: #0878f9; }

.juanfi-page .status-badge {
    display: inline-flex; align-items: center; justify-content: center;
    min-width: 108px; gap: 6px;
    padding: 7px 11px; border-radius: 18px;
    font-size: 11px; font-weight: 700; color: #fff;
    background: #7d8ba0;
}
.juanfi-page .status-badge.connected { background: #19a64c; }
.juanfi-page .status-badge.paused { background: #f0ad4e; }

.juanfi-page .table-actions { text-align: center; }
.juanfi-page .table-actions .btn { height: auto; margin: 2px; padding: 5px 9px; font-size: 11px; }

.juanfi-page .empty-state {
    padding: 35px 15px !important;
    text-align: center;
    color: var(--muted);
}

/* Footer */
.juanfi-page .table-footer {
    display: flex; flex-wrap: wrap; gap: 12px;
    justify-content: space-between; align-items: center;
    padding-top: 17px;
    color: var(--muted);
    font-size: 12px;
}
.juanfi-page .pagination { margin: 0; }
.juanfi-page .pagination > li > a,
.juanfi-page .pagination > li > span {
    padding: 8px 12px;
    color: var(--link);
    border-color: var(--border);
}
.juanfi-page .pagination > .active > a,
.juanfi-page .pagination > .active > span {
    background: #0878f9;
    border-color: #0878f9;
    color: #fff;
}
.juanfi-page .alert { margin-bottom: 12px; border-radius: 7px; }

/* Responsive */
@media (max-width: 1100px) {
    .juanfi-page .juanfi-filters { grid-template-columns: repeat(2, 1fr); }
    .juanfi-page .result-count { grid-column: span 2; }
}
@media (max-width: 600px) {
    .juanfi-page .panel-heading,
    .juanfi-page .panel-body { padding: 15px; }
    .juanfi-page .heading-title { font-size: 19px; }
    .juanfi-page .heading-icon { width: 43px; height: 43px; font-size: 20px; }
    .juanfi-page .juanfi-filters { grid-template-columns: 1fr; }
    .juanfi-page .result-count { grid-column: auto; }
    .juanfi-page .juanfi-filters .btn { width: 100%; }
    .juanfi-page .table-footer { flex-direction: column; align-items: flex-start; }
    .juanfi-page .pagination { max-width: 100%; overflow-x: auto; }
}
</style>
{/literal}

<div class="row juanfi-page">
    <div class="col-sm-12">
        <div class="panel panel-primary panel-hovered panel-stacked mb30">

            <div class="panel-heading">
                <div class="page-heading">
                    <div class="heading-icon">
                        <i class="glyphicon glyphicon-user"></i>
                    </div>
                    <div>
                        <h3 class="heading-title">Hotspot Users</h3>
                        <p class="heading-subtitle">Monitor connected and disconnected users from your MikroTik routers.</p>
                        <div class="heading-counts">
                            <span class="count-pill online">
                                <i class="glyphicon glyphicon-signal"></i> {$juanfiCounts.connected} Connected
                            </span>
                            <span class="count-pill">
                                <i class="glyphicon glyphicon-ban-circle"></i> {$juanfiCounts.disconnected} Disconnected
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="panel-body">

                {foreach $juanfiErrors as $e}
                    <div class="alert alert-warning">
                        <i class="glyphicon glyphicon-warning-sign"></i> {$e|escape}
                    </div>
                {/foreach}

                <form method="get" action="?_route=plugin/juanfi_user" class="juanfi-filters">
                    <input type="hidden" name="_route" value="plugin/juanfi_user">

                    <input type="text" name="q" class="form-control"
                           value="{$juanfiFilter.q|escape}"
                           placeholder="Search hostname, user, MAC, IP">

                    <select name="status" class="form-control">
                        <option value="">All statuses</option>
                        <option value="connected" {if $juanfiFilter.status == 'connected'}selected{/if}>Connected</option>
                        <option value="disconnected" {if $juanfiFilter.status == 'disconnected'}selected{/if}>Disconnected</option>
                    </select>

                    <select name="router" class="form-control">
                        <option value="">All routers</option>
                        {foreach $juanfiRouters as $rn}
                            <option value="{$rn|escape}" {if $juanfiFilter.router == $rn}selected{/if}>{$rn|escape}</option>
                        {/foreach}
                    </select>

                    <button type="submit" class="btn btn-primary">
                        <i class="glyphicon glyphicon-filter"></i> Filter
                    </button>
                    <a href="?_route=plugin/juanfi_user" class="btn btn-default">
                        <i class="glyphicon glyphicon-refresh"></i> Reset
                    </a>
                    <div class="result-count">{$juanfiCounts.shown} result(s)</div>
                </form>

                <div class="table-wrap">
                    <table class="table table-bordered table-striped" id="juanfiUsers">
                        <thead>
                            <tr>
                                <th>Hostname</th>
                                <th>User</th>
                                <th>MAC Address</th>
                                <th>IP Address</th>
                                <th>Profile</th>
                                <th>Time Left</th>
                                <th>Expiry</th>
                                <th>Status</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            {foreach $juanfiUsers as $u}
                                <tr>
                                    <td class="user-hostname">{$u.hostname|escape}</td>
                                    <td>{$u.username|escape}</td>
                                    <td>{$u.mac|escape}</td>
                                    <td>{$u.ip|escape}</td>
                                    <td>{$u.profile|escape}</td>
                                    <td>
                                        <span class="time-left juanfi-left"
                                              data-left="{$u.left|escape}"
                                              data-live="{if $u.connected}1{else}0{/if}"></span>
                                    </td>
                                    <td>{$u.expiry|escape}</td>
                                    <td>
                                        {if $u.paused}
                                            <span class="status-badge paused">
                                                <i class="fa fa-pause"></i> Paused
                                            </span>
                                        {elseif $u.connected}
                                            <span class="status-badge connected">
                                                <i class="glyphicon glyphicon-signal"></i> Connected
                                            </span>
                                        {else}
                                            <span class="status-badge">
                                                <i class="glyphicon glyphicon-lock"></i> Disconnected
                                            </span>
                                        {/if}
                                    </td>
                                    <td class="table-actions">
                                        <button type="button" class="btn btn-success btn-time"
                                                data-toggle="modal" data-target="#timeModal" data-mode="add"
                                                data-url="?_route=plugin/juanfi_user_addtime"
                                                data-router="{$u.router|escape}" data-user="{$u.username|escape}"
                                                title="Add Time" aria-label="Add Time">
                                            <i class="fa fa-plus"></i> <span class="hidden-xs">Add</span>
                                        </button>
                                        <button type="button" class="btn btn-warning btn-time" style="color:black"
                                                data-toggle="modal" data-target="#timeModal" data-mode="deduct"
                                                data-url="?_route=plugin/juanfi_user_deducttime"
                                                data-router="{$u.router|escape}" data-user="{$u.username|escape}"
                                                title="Deduct Time" aria-label="Deduct Time">
                                            <i class="fa fa-minus"></i> <span class="hidden-xs">Deduct</span>
                                        </button>
                                        {if $u.paused}
                                            <button type="button" class="btn btn-primary btn-act"
                                                    data-url="?_route=plugin/juanfi_user_resume"
                                                    data-router="{$u.router|escape}" data-user="{$u.username|escape}"
                                                    title="Resume" aria-label="Resume">
                                                <i class="fa fa-play"></i> <span class="hidden-xs">Resume</span>
                                            </button>
                                        {elseif $u.connected}
                                            <button type="button" class="btn btn-info btn-act"
                                                    data-url="?_route=plugin/juanfi_user_pause"
                                                    data-confirm-title="Pause this user?"
                                                    data-confirm-text="The user will be disconnected. Remaining time is kept and they can resume from the portal."
                                                    data-confirm-ok="Yes, pause"
                                                    data-router="{$u.router|escape}" data-user="{$u.username|escape}"
                                                    title="Pause" aria-label="Pause">
                                                <i class="fa fa-pause"></i> <span class="hidden-xs">Pause</span>
                                            </button>
                                        {/if}
                                        <button type="button" class="btn btn-danger btn-act"
                                                data-url="?_route=plugin/juanfi_user_delete"
                                                data-confirm-title="Delete this user?"
                                                data-confirm-text="The hotspot user and its expiry will be deleted from the router."
                                                data-confirm-ok="Yes, delete" data-danger="1"
                                                data-router="{$u.router|escape}" data-user="{$u.username|escape}"
                                                title="Delete" aria-label="Delete">
                                            <i class="fa fa-trash"></i> <span class="hidden-xs">Delete</span>
                                        </button>
                                    </td>
                                </tr>
                            {foreachelse}
                                <tr>
                                    <td colspan="9" class="empty-state">
                                        <i class="glyphicon glyphicon-info-sign"></i> No hotspot users found.
                                    </td>
                                </tr>
                            {/foreach}
                        </tbody>
                    </table>
                </div>

                <div class="table-footer">
                    <span>Showing {$juanfiCounts.shown} result(s)</span>
                    <div>{include file="pagination.tpl"}</div>
                </div>

            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="timeModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-sm" role="document">
        <form method="post" id="atForm" class="modal-content">
            <input type="hidden" name="csrf_token" value="{$csrf_token}">
            <input type="hidden" name="router" id="atRouter">
            <input type="hidden" name="username" id="atUser">

            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><span id="atTitle">Add Time</span>: <span id="atUserLabel"></span></h4>
            </div>

            <div class="modal-body">
                <div class="input-group">
                    <input type="number" name="amount" class="form-control" min="1" value="1" required>
                    <span class="input-group-btn" style="width:40%">
                        <select name="unit" class="form-control">
                            <option value="m">Minutes</option>
                            <option value="h" selected>Hours</option>
                            <option value="d">Days</option>
                        </select>
                    </span>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="submit" id="atSubmit" class="btn btn-success">Add Time</button>
            </div>
        </form>
    </div>
</div>

<form method="post" id="actForm" style="display:none">
    <input type="hidden" name="csrf_token" value="{$csrf_token}">
    <input type="hidden" name="router" id="actRouter">
    <input type="hidden" name="username" id="actUser">
</form>

{literal}
<script>
(function () {
    var now = Date.now(), live = [];

    function byId(id) { return document.getElementById(id); }

    function submitAct(b) {
        byId('actForm').action = b.dataset.url;
        byId('actRouter').value = b.dataset.router;
        byId('actUser').value = b.dataset.user;
        byId('actForm').submit();
    }

    document.addEventListener('click', function (e) {
        var t = e.target.closest('.btn-time');
        if (t) {
            var deduct = t.dataset.mode === 'deduct';
            byId('atForm').action = t.dataset.url;
            byId('atRouter').value = t.dataset.router;
            byId('atUser').value = t.dataset.user;
            byId('atUserLabel').textContent = t.dataset.user;
            byId('atTitle').textContent = deduct ? 'Deduct Time' : 'Add Time';
            byId('atSubmit').textContent = deduct ? 'Deduct Time' : 'Add Time';
            byId('atSubmit').className = 'btn ' + (deduct ? 'btn-warning' : 'btn-success');
            return;
        }

        var a = e.target.closest('.btn-act');
        if (!a) { return; }

        var title = a.dataset.confirmTitle;
        if (!title) { submitAct(a); return; }   // no confirmation needed (e.g. Resume)

        var text = a.dataset.user + ': ' + (a.dataset.confirmText || '');
        if (window.Swal) {
            Swal.fire({
                title: title,
                text: text,
                icon: a.dataset.danger ? 'warning' : 'question',
                showCancelButton: true,
                confirmButtonText: a.dataset.confirmOk || 'Yes',
                confirmButtonColor: a.dataset.danger ? '#d33' : undefined
            }).then(function (r) { if (r.isConfirmed) { submitAct(a); } });
        } else if (window.confirm(title + '\n' + text)) {
            submitAct(a);
        }
    });

    window.addEventListener('load', function () {
        if (window.jQuery && jQuery.fn.tooltip) {
            jQuery('.juanfi-page [title]').tooltip({ container: 'body' });
        }
    });

    function pad(n) { return n < 10 ? '0' + n : n; }

    function fmt(s) {
        var d = Math.floor(s / 86400);
        return (d ? d + 'd ' : '') + pad(Math.floor(s % 86400 / 3600)) + ':' +
               pad(Math.floor(s % 3600 / 60)) + ':' + pad(s % 60);
    }

    function render(el, s) {
        el.textContent = s > 0 ? fmt(s) : 'Expired';
        el.className = 'time-left juanfi-left ' + (s > 0 ? 'active' : 'expired');
    }

    document.querySelectorAll('.juanfi-left').forEach(function (el) {
        var raw = el.getAttribute('data-left');

        if (raw === '') {
            el.textContent = 'Unlimited';
            el.classList.add('unlimited');
            return;
        }

        var left = Number(raw);
        if (!isFinite(left)) { el.textContent = '—'; return; }

        left = Math.max(0, Math.floor(left));
        render(el, left);

        if (left > 0 && el.getAttribute('data-live') === '1') {
            live.push({ el: el, end: now + left * 1000 });
        }
    });

    if (live.length) {
        setInterval(function () {
            var t = Date.now();
            live.forEach(function (i) {
                render(i.el, Math.max(0, Math.ceil((i.end - t) / 1000)));
            });
        }, 1000);
    }
})();
</script>
{/literal}

{include file="sections/footer.tpl"}
