{include file="sections/header.tpl"}

<style>
{literal}
/* BILLSYS Partner Payout — responsive dashboard skin */
.pp-wrap{--pp-blue:#0878f9;--pp-blue2:#075dcc;--pp-ink:#14213d;--pp-muted:#64748b;--pp-line:#e3eaf4;--pp-bg:#f4f7fc;--pp-card:#fff;color:inherit}
.pp-wrap *{box-sizing:border-box}
.pp-wrap .text-muted,.pp-wrap .help-block{color:var(--pp-muted)!important}
.pp-wrap .panel-heading{font-weight:800;letter-spacing:-.3px}
.panel.panel-primary:has(.pp-wrap){border:0;border-radius:16px;overflow:hidden;box-shadow:0 8px 28px rgba(23,55,95,.08)}
.panel.panel-primary:has(.pp-wrap)>.panel-heading{display:none}
.panel.panel-primary:has(.pp-wrap)>.panel-body{padding:22px;background:var(--pp-bg)}
.pp-wrap .pp-pagehead{display:flex;justify-content:space-between;align-items:center;gap:16px;margin:0 0 20px}
.pp-wrap .pp-titlegroup{display:flex;align-items:center;gap:14px;min-width:0}
.pp-wrap .pp-titleicon{display:grid;place-items:center;width:54px;height:54px;border-radius:16px;background:linear-gradient(140deg,#168bff,#075bd1);color:#fff;box-shadow:0 8px 20px rgba(8,120,249,.22);flex-shrink:0}
.pp-wrap .pp-titleicon svg{width:28px;height:28px}
.pp-wrap .pp-title{font-size:27px;font-weight:850;letter-spacing:-.8px;line-height:1.15;margin:0;color:inherit}
.pp-wrap .pp-subtitle{font-size:13px;color:var(--pp-muted);margin:5px 0 0}
.pp-wrap .pp-breadcrumb{font-size:12px;color:var(--pp-muted);white-space:nowrap}
.pp-wrap .pp-topnav{display:flex;align-items:center;gap:8px;background:var(--pp-card);padding:6px;border:1px solid var(--pp-line);border-radius:15px;margin-bottom:18px;box-shadow:0 4px 14px rgba(22,52,88,.04)}
.pp-wrap .pp-topnav .nav{display:flex;flex:1;gap:5px;border:0;margin:0;padding:0}
.pp-wrap .pp-topnav .nav li{flex:1;float:none;margin:0}
.pp-wrap .pp-topnav .nav li a{display:flex;align-items:center;justify-content:center;gap:8px;padding:13px 9px;border:0!important;border-radius:10px;color:var(--pp-muted);font-weight:750;white-space:nowrap}
.pp-wrap .pp-topnav .nav li a:hover{background:rgba(8,120,249,.07);color:var(--pp-blue)}
.pp-wrap .pp-topnav .nav li.active a{background:#edf5ff;color:var(--pp-blue);box-shadow:inset 0 -3px 0 var(--pp-blue)}
.pp-wrap .pp-topnav .nav li a .glyphicon{font-size:16px}
.pp-wrap .pp-add{white-space:nowrap;padding:12px 17px;border-radius:11px;font-weight:800;background:linear-gradient(120deg,#0878f9,#0561d1);border:0;box-shadow:0 6px 15px rgba(8,120,249,.18)}
.pp-wrap .pp-metrics{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:13px;margin:0 0 17px}
.pp-wrap .pp-metric{display:flex;align-items:center;gap:12px;min-width:0;background:var(--pp-card);border:1px solid var(--pp-line);border-radius:15px;padding:15px;box-shadow:0 5px 18px rgba(25,58,94,.035);animation:ppRise .45s ease both}
.pp-wrap .pp-metric:nth-child(2){animation-delay:.05s}.pp-wrap .pp-metric:nth-child(3){animation-delay:.1s}.pp-wrap .pp-metric:nth-child(4){animation-delay:.15s}
.pp-wrap .pp-metric-icon{width:48px;height:48px;display:grid;place-items:center;border-radius:50%;flex-shrink:0}
.pp-wrap .pp-metric-icon svg{width:24px;height:24px}
.pp-wrap .pp-metric-icon.blue{background:#e0efff;color:#0878f9}.pp-wrap .pp-metric-icon.amber{background:#fff2ce;color:#f59e0b}.pp-wrap .pp-metric-icon.purple{background:#eee5ff;color:#8548f5}.pp-wrap .pp-metric-icon.green{background:#d7f8e9;color:#07965d}
.pp-wrap .pp-metric-label{font-size:12px;color:var(--pp-muted);margin-bottom:4px}
.pp-wrap .pp-metric-value{font-size:22px;line-height:1.2;font-weight:850;letter-spacing:-.5px;color:inherit;overflow-wrap:anywhere}
.pp-wrap .pp-metric-note{font-size:11px;color:var(--pp-muted);margin-top:4px}
.pp-wrap .pp-workspace{background:var(--pp-card);border:1px solid var(--pp-line);border-radius:15px;padding:18px;margin-bottom:16px;box-shadow:0 5px 18px rgba(25,58,94,.035)}
.pp-wrap .pp-filter-title{font-size:13px;font-weight:800;margin:0 0 8px}
.pp-wrap .pp-filterrow{display:flex;gap:10px;align-items:flex-end}
.pp-wrap .pp-filterrow .pp-month{width:245px;max-width:100%}
.pp-wrap .pp-filterrow .pp-search{display:flex;gap:8px;flex:1;min-width:0;margin:0}
.pp-wrap .form-control{border-color:#dce5f0;border-radius:9px;box-shadow:none;min-height:40px;background:var(--pp-card);color:inherit}
.pp-wrap .form-control:focus{border-color:#6caeff;box-shadow:0 0 0 3px rgba(8,120,249,.1)}
.pp-wrap .btn{border-radius:9px;transition:transform .18s,box-shadow .18s,background .18s}
.pp-wrap .btn:hover{transform:translateY(-1px)}
.pp-wrap .pp-filterrow .btn{min-height:40px;padding:9px 17px;font-weight:750}
.pp-wrap .pp-period-summary{display:none}
.pp-wrap .pp-table-wrap{background:var(--pp-card);border:1px solid var(--pp-line);border-radius:15px;padding:12px;box-shadow:0 5px 18px rgba(25,58,94,.035)}
.pp-wrap .nav-tabs{display:flex;gap:0;border-bottom:1px solid var(--pp-line);margin:0 0 13px!important}
.pp-wrap .nav-tabs>li{float:none;margin:0 6px 0 0}
.pp-wrap .nav-tabs>li>a{border:0!important;border-bottom:3px solid transparent!important;border-radius:10px 10px 0 0;padding:12px 19px;font-weight:800;color:var(--pp-muted);background:transparent}
.pp-wrap .nav-tabs>li.active>a,.pp-wrap .nav-tabs>li.active>a:hover,.pp-wrap .nav-tabs>li.active>a:focus{color:#ed3d45;background:#fff;border-bottom-color:#ff4b55!important}
.pp-wrap .nav-tabs .badge{border-radius:20px;background:#e8eef6;color:#46566d;margin-left:6px}
.pp-wrap .nav-tabs>li.active .badge{background:#f0444c;color:#fff}
.pp-wrap table{width:100%;color:inherit;background:transparent;border-collapse:separate;border-spacing:0}
.pp-wrap table th,.pp-wrap table td{border-color:#e8eef6!important;background:transparent;vertical-align:middle!important;padding:10px 9px!important}
.pp-wrap table thead th{background:#f1f5fb!important;color:#334155;font-size:12px;font-weight:850;white-space:nowrap;border-top:0!important}
.pp-wrap table thead th:first-child{border-radius:9px 0 0 9px}
.pp-wrap table thead th:last-child{border-radius:0 9px 9px 0}
.pp-wrap .pp-striped tbody tr:nth-child(even) td{background:rgba(120,145,175,.045)}
.pp-wrap tbody tr{transition:background .18s}
.pp-wrap tbody tr:hover td{background:rgba(8,120,249,.045)}
.pp-wrap .pp-gross{background:#fff5cf!important;color:#68400a;font-weight:800}
.pp-wrap .pp-net{font-weight:850;color:#078b59}
.pp-wrap tfoot th{background:#f4f7fb!important;font-weight:850}
.pp-wrap .pp-card{border:1px solid var(--pp-line);background:var(--pp-card);border-radius:14px;padding:15px 10px;text-align:center;color:inherit;margin-bottom:10px;box-shadow:0 4px 15px rgba(25,58,94,.035)}
.pp-wrap .pp-card small{color:var(--pp-muted);font-size:12px}
.pp-wrap .pp-card h4{margin:6px 0 0;color:inherit;font-size:20px;font-weight:850}
.pp-wrap .pagination>li>a{border-color:var(--pp-line);color:var(--pp-blue);border-radius:7px;margin:0 2px}
.pp-wrap .pagination>.active>a{background:var(--pp-blue);border-color:var(--pp-blue);color:#fff}
.pp-wrap .help-block{font-size:12px}
@keyframes ppRise{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:translateY(0)}}

/* Collect popup + read-only harvest table */
/* hidden harvest columns on the Uncollected tab only */
.pp-wrap #pp-table.pp-hidecols .pp-hide,.pp-wrap #pp-table.pp-hidecols tfoot{display:none!important}
.pp-wrap .pp-actioncell{white-space:nowrap;text-align:right}
.pp-wrap .pp-collect-btn{font-weight:800;border-radius:9px;padding:7px 14px}
.pp-wrap .pp-partner-name{font-weight:800}
.pp-wrap .pp-partner-addr{font-size:12px;color:var(--pp-muted)}
.pp-wrap tr.pp-extra td{background:#fafcff}
#pp-collect-modal .modal-content{border:0;border-radius:16px;overflow:hidden}
#pp-collect-modal .modal-header{background:linear-gradient(120deg,#0878f9,#075dcc);color:#fff;border:0;padding:16px 20px}
#pp-collect-modal .modal-header .close{color:#fff;opacity:.9;text-shadow:none}
#pp-collect-modal .modal-title{font-weight:800}
#pp-collect-modal .pp-m-sub{font-size:12px;opacity:.85;margin-top:3px}
#pp-collect-modal .pp-m-info{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px}
#pp-collect-modal .pp-m-chip{background:#eef4fc;color:#334155;border-radius:20px;padding:4px 11px;font-size:12px;font-weight:700}
#pp-collect-modal .form-group{margin-bottom:14px}
#pp-collect-modal label{font-weight:700;font-size:13px;margin-bottom:5px;display:block}
#pp-collect-modal .form-control{height:46px;font-size:18px;border-radius:10px}
#pp-collect-modal .pp-m-gross{background:#fff5cf;font-weight:800}
#pp-collect-modal .pp-m-net{display:flex;justify-content:space-between;align-items:center;background:#e9fbf3;border-radius:12px;padding:12px 16px;margin-top:6px}
#pp-collect-modal .pp-m-net span{color:#475569;font-weight:700}
#pp-collect-modal .pp-m-net b{font-size:24px;color:#078b59;letter-spacing:-.5px}
#pp-collect-modal .modal-footer{border:0;padding:6px 20px 20px}
#pp-collect-modal .modal-footer .btn{min-height:44px;font-weight:800;border-radius:10px}

@media(max-width:1100px){.pp-wrap .pp-breadcrumb{display:none}.pp-wrap .pp-metrics{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:767px){
 .panel.panel-primary:has(.pp-wrap)>.panel-body{padding:12px}
 .pp-wrap .pp-pagehead{align-items:flex-start;margin-bottom:14px}
 .pp-wrap .pp-title{font-size:22px}.pp-wrap .pp-titleicon{width:45px;height:45px}
 .pp-wrap .pp-topnav{flex-wrap:wrap}.pp-wrap .pp-topnav .nav{flex-basis:100%;flex-wrap:wrap}
 .pp-wrap .pp-topnav .nav li{flex:1 1 42%}.pp-wrap .pp-topnav .nav li a{padding:10px 5px;font-size:12px}
 .pp-wrap .pp-add{width:100%}
 .pp-wrap .pp-metrics{gap:8px}.pp-wrap .pp-metric{padding:11px;gap:8px;align-items:flex-start}
 .pp-wrap .pp-metric-icon{width:37px;height:37px}.pp-wrap .pp-metric-icon svg{width:19px;height:19px}
 .pp-wrap .pp-metric-value{font-size:18px}.pp-wrap .pp-workspace{padding:12px}
 .pp-wrap .pp-filterrow{display:block}.pp-wrap .pp-filterrow .pp-month{width:100%;margin-bottom:9px}
 .pp-wrap .pp-filterrow .pp-search{width:100%}.pp-wrap .pp-filterrow .pp-search input{min-width:0!important;width:100%}
 .pp-wrap .pp-table-wrap{padding:8px}.pp-wrap table th,.pp-wrap table td{padding:8px 7px!important}
}
@media(prefers-reduced-motion:reduce){.pp-wrap *{animation:none!important;transition:none!important}}
{/literal}
</style>
<div class="row">
  <div class="col-md-12">
    <div class="panel panel-primary panel-hovered panel-stacked mb30">
      <div class="panel-heading">Partner Payout</div>
      <div class="panel-body pp-wrap">

        <div class="pp-pagehead">
          <div class="pp-titlegroup">
            <div class="pp-titleicon" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M8 11 5 8a2 2 0 0 1 3-3l4 4"/><path d="m8 11 3 3a2 2 0 0 0 3 0l5-5a2 2 0 0 0-3-3l-2 2"/><path d="m5 12-2 2a2 2 0 0 0 3 3l2-2m4 2 2 2a2 2 0 0 0 3-3l-1-1"/><path d="m16 5 2-2a2 2 0 0 1 3 3l-2 2"/></svg>
            </div>
            <div><h2 class="pp-title">Partner Payout</h2><p class="pp-subtitle">Manage partner collections, harvest, and payouts</p></div>
          </div>
          <div class="pp-breadcrumb"><span class="glyphicon glyphicon-home"></span> &nbsp; Home &nbsp; › &nbsp; Plugins &nbsp; › &nbsp; Partner Payout</div>
        </div>
        <div class="pp-topnav">
          <ul class="nav nav-pills">
            <li {if $view == 'harvest'}class="active"{/if}><a href="{$_url}plugin/partner_payout&view=harvest&period={$period}&from={$from}&to={$to}"><span class="glyphicon glyphicon-cloud-download"></span> Harvest</a></li>
            <li {if $view == 'harvested'}class="active"{/if}><a href="{$_url}plugin/partner_payout&view=harvested&period={$period}&from={$from}&to={$to}"><span class="glyphicon glyphicon-ok-sign"></span> Harvested</a></li>
            <li {if $view == 'list'}class="active"{/if}><a href="{$_url}plugin/partner_payout&view=list&period={$period}&from={$from}&to={$to}"><span class="glyphicon glyphicon-home"></span> List of Vendo</a></li>
            <li {if $view == 'collectors'}class="active"{/if}><a href="{$_url}plugin/partner_payout&view=collectors&period={$period}&from={$from}&to={$to}"><span class="glyphicon glyphicon-user"></span> Collectors</a></li>
          </ul>
          <button type="button" class="btn btn-primary pp-add" data-toggle="modal" data-target="#pp-add-modal"><span class="glyphicon glyphicon-user"></span> <span class="glyphicon glyphicon-plus"></span> Add Partner</button>
        </div>

        {* ================= HARVEST (entry) ================= *}
        {if $view == 'harvest'}
          <div class="pp-metrics">
            <div class="pp-metric"><div class="pp-metric-icon blue"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v5c0 1.7 3.6 3 8 3 1.3 0 2.6-.1 3.7-.4M4 10v5c0 1.7 3.6 3 8 3 1.3 0 2.6-.1 3.7-.4M20 8v5c0 1.2-1.8 2.2-4.3 2.7"/></svg></div><div><div class="pp-metric-label">Total Collected</div><div class="pp-metric-value">&#8369;{$month_total.gross}</div><div class="pp-metric-note">{$month_label}</div></div></div>
            <div class="pp-metric"><div class="pp-metric-icon amber"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m13 2-3 8h7l-6 12 1-9H6l7-11Z"/></svg></div><div><div class="pp-metric-label">Kuryente</div><div class="pp-metric-value">&#8369;{$month_total.kuryente}</div><div class="pp-metric-note">This month</div></div></div>
            <div class="pp-metric"><div class="pp-metric-icon purple"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m14.7 6.3 3-3a5 5 0 0 1-6.4 6.4L5 16l3 3 6.3-6.3a5 5 0 0 0 6.4-6.4l-3 3-3-3Z"/></svg></div><div><div class="pp-metric-label">Tapal</div><div class="pp-metric-value">&#8369;{$month_total.tapal}</div><div class="pp-metric-note">This month</div></div></div>
            <div class="pp-metric"><div class="pp-metric-icon green"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 10h18M5 10V6h14v4M5 10v9h14v-9M9 19v-5h6v5M3 6l2-3h14l2 3"/></svg></div><div><div class="pp-metric-label">Collected Vendo</div><div class="pp-metric-value">{$done_count} / {$done_count + $todo_count}</div><div class="pp-metric-note">Monthly progress</div></div></div>
          </div>
          <div class="pp-workspace">
            <div class="pp-filter-title">Harvest Month</div>
            <div class="pp-filterrow">
              <form method="get" action="{$_url}plugin/partner_payout" class="pp-month">
                <input type="hidden" name="_route" value="plugin/partner_payout"><input type="hidden" name="view" value="harvest">
                <input type="hidden" name="from" value="{$from}"><input type="hidden" name="to" value="{$to}"><input type="hidden" name="tab" value="{$tab}">
                <div style="display:flex;gap:7px"><input type="month" name="month" value="{$month}" placeholder="YYYY-MM" class="form-control"><button class="btn btn-default" type="submit" aria-label="Load month"><span class="glyphicon glyphicon-calendar"></span></button></div>
              </form>
              <form method="get" action="{$_url}plugin/partner_payout" class="pp-search">
                <input type="hidden" name="_route" value="plugin/partner_payout"><input type="hidden" name="view" value="harvest">
                <input type="hidden" name="period" value="{$period}"><input type="hidden" name="from" value="{$from}"><input type="hidden" name="to" value="{$to}"><input type="hidden" name="tab" value="{$tab}">
                <input type="text" name="q" value="{$q|escape}" class="form-control" placeholder="Search partner or address" style="min-width:160px">
                <button class="btn btn-primary" type="submit"><span class="glyphicon glyphicon-search"></span> Search</button>
                {if $q != ''}<a class="btn btn-default" href="{$_url}plugin/partner_payout&view=harvest&period={$period}&from={$from}&to={$to}&tab={$tab}">Clear</a>{/if}
              </form>
            </div>
          </div>
          <div class="pp-table-wrap">
          <ul class="nav nav-tabs" style="margin-bottom:15px">
            <li {if $tab != 'done'}class="active"{/if}><a href="{$_url}plugin/partner_payout&view=harvest&period={$period}&from={$from}&to={$to}&tab=todo">Uncollected <span class="badge">{$todo_count}</span></a></li>
            <li {if $tab == 'done'}class="active"{/if}><a href="{$_url}plugin/partner_payout&view=harvest&period={$period}&from={$from}&to={$to}&tab=done">Collected <span class="badge">{$done_count}</span></a></li>
          </ul>

          {if count($partners) == 0}
            {if $q != ''}
              <p>No vendo found for "{$q|escape}".</p>
            {elseif $hidden_count > 0}
              <p>{if $tab == 'done'}Nothing is collected yet for {$month_label}.{else}Everything is collected for {$month_label}. See the <b>Harvested</b> tab for the summary.{/if}</p>
            {else}
              <p>No partners yet. Go to <a href="{$_url}plugin/partner_payout&view=list&period={$period}">List of Vendo</a> to import your sheet.</p>
            {/if}
          {else}
            <div class="table-responsive">
              <table class="table table-bordered table-condensed{if $tab != 'done'} pp-hidecols{/if}" id="pp-table">
                <thead>
                  <tr>
                    <th>No</th><th>Partner</th><th>Schedule</th><th class="pp-hide">Share %</th>
                    <th class="text-right pp-hide">Gross</th><th class="text-right pp-hide">Kuryente</th>
                    <th class="text-right pp-hide">Tapal</th><th class="text-right pp-hide">Net</th>
                    <th class="pp-hide">Collected by</th><th></th>
                  </tr>
                </thead>
                <tbody>
                {foreach $partners as $i => $p}
                  <tr data-share="{$p.share}" data-gross="{$p.gross}" data-kuryente="{$p.kuryente}" data-tapal="{$p.tapal}">
                    <td>{$offset + $i + 1}</td>
                    <td>
                      <div class="pp-partner-name">{$p.name}</div>
                      <div class="pp-partner-addr"><span class="glyphicon glyphicon-map-marker"></span> {$p.address}</div>
                      {if $p.can_again}
                        <button type="button" class="btn btn-xs btn-default pp-open" style="margin-top:5px"
                          data-id="{$p.id}" data-prefix="x" data-no="{$p.next_no}"
                          data-name="{$p.name|escape}" data-address="{$p.address|escape}" data-share="{$p.share + 0}"
                          data-title="{$p.next_label} collection" data-gross="" data-kuryente="" data-tapal="">
                          <span class="glyphicon glyphicon-repeat"></span> Collect again ({$p.next_label})
                        </button>
                      {/if}
                    </td>
                    <td>{$p.schedule}{if $p.state == 'overdue'}<br><small class="text-danger"><b>Overdue {$p.days}d</b> (was due {$p.due_label})</small>{elseif $p.state == 'today'}<br><small class="text-primary"><b>Due today</b></small>{elseif $p.state == 'upcoming'}<br><small class="text-muted">Upcoming &middot; due {$p.due_label}</small>{/if}</td>
                    <td class="pp-hide">{$p.share + 0}</td>
                    <td class="pp-gross pp-hide text-right">{if $p.gross !== '' && $p.gross !== null}{$p.gross}{else}-{/if}</td>
                    <td class="text-right pp-hide">{if $p.kuryente !== '' && $p.kuryente !== null}{$p.kuryente}{else}-{/if}</td>
                    <td class="text-right pp-hide">{if $p.tapal !== '' && $p.tapal !== null}{$p.tapal}{else}-{/if}</td>
                    <td class="pp-net pp-hide text-right">-</td>
                    <td class="pp-hide">{if $p.collected_by}{$p.collected_by|escape}<br><small class="text-muted">{$p.collected_at}</small>{else}-{/if}</td>
                    <td class="pp-actioncell">
                      <button type="button" class="btn btn-sm pp-open pp-collect-btn {if $tab == 'done'}btn-default{else}btn-success{/if}"
                        data-id="{$p.id}" data-prefix="" data-no=""
                        data-name="{$p.name|escape}" data-address="{$p.address|escape}" data-share="{$p.share + 0}"
                        data-title="{if $tab == 'done'}Edit collection{else}Collect{/if}"
                        data-gross="{$p.gross}" data-kuryente="{$p.kuryente}" data-tapal="{$p.tapal}">
                        {if $tab == 'done'}<span class="glyphicon glyphicon-pencil"></span> Edit{else}<span class="glyphicon glyphicon-ok"></span> Collect{/if}
                      </button>
                    </td>
                  </tr>

                  {foreach $p.extras as $x}
                  <tr class="pp-extra" data-share="{$p.share}" data-gross="{$x.gross}" data-kuryente="{$x.kuryente}" data-tapal="{$x.tapal}">
                    <td></td>
                    <td colspan="{if $tab != 'done'}2{else}3{/if}" class="text-right"><small class="text-muted"><span class="glyphicon glyphicon-repeat"></span> {$x.label} collection &middot; {$p.name}</small></td>
                    <td class="pp-gross pp-hide text-right">{if $x.gross !== '' && $x.gross !== null}{$x.gross}{else}-{/if}</td>
                    <td class="text-right pp-hide">{if $x.kuryente !== '' && $x.kuryente !== null}{$x.kuryente}{else}-{/if}</td>
                    <td class="text-right pp-hide">{if $x.tapal !== '' && $x.tapal !== null}{$x.tapal}{else}-{/if}</td>
                    <td class="pp-net pp-hide text-right">-</td>
                    <td class="pp-hide">{if $x.collected_by}{$x.collected_by|escape}<br><small class="text-muted">{$x.collected_at}</small>{else}-{/if}</td>
                    <td class="pp-actioncell">
                      <button type="button" class="btn btn-default btn-sm pp-open pp-collect-btn"
                        data-id="{$p.id}" data-prefix="x" data-no="{$x.no}"
                        data-name="{$p.name|escape}" data-address="{$p.address|escape}" data-share="{$p.share + 0}"
                        data-title="Edit {$x.label} collection"
                        data-gross="{$x.gross}" data-kuryente="{$x.kuryente}" data-tapal="{$x.tapal}">
                        <span class="glyphicon glyphicon-pencil"></span> Edit
                      </button>
                    </td>
                  </tr>
                  {/foreach}
                {/foreach}
                </tbody>
                <tfoot>
                  <tr>
                    <th colspan="4" class="text-right">Page total</th>
                    <th class="text-right" id="pp-tg">0.00</th><th></th><th></th>
                    <th class="text-right" id="pp-tn">0.00</th><th></th><th></th>
                  </tr>
                </tfoot>
              </table>
            </div>
          {if $paginator}
              <center>
              <nav aria-label="Page navigation pagination-sm">
                  <ul class="pagination">
                      <li {if empty($paginator['prev'])}class="disabled" {/if}>
                          <a href="{$paginator['url']}{$paginator['prev']}" aria-label="Previous">
                              <span aria-hidden="true">{Lang::T('Prev')}</span>
                          </a>
                      </li>
                      {foreach $paginator['pages'] as $page}
                          <li class="{if $paginator['page'] == $page}active{elseif $page == '...'}disabled{/if}"><a
                                  href="{$paginator['url']}{$page}">{$page}</a></li>
                      {/foreach}
                      <li {if $paginator['page']>=$paginator['count']}class="disabled" {/if}>
                          <a href="{$paginator['url']}{$paginator['next']}" aria-label="Next">
                              <span aria-hidden="true">{Lang::T('Next')}</span>
                          </a>
                      </li>
                  </ul>
              </nav>
              </center>
          {/if}
          
          {/if}
          </div>
        {/if}

        {* ================= HARVESTED (summary within period) ================= *}
        {if $view == 'harvested'}
          <form method="get" action="{$_url}plugin/partner_payout" class="form-inline" style="margin-bottom:15px">
            <input type="hidden" name="_route" value="plugin/partner_payout">
            <input type="hidden" name="view" value="harvested">
            <input type="hidden" name="period" value="{$period}">
            <label>From</label>
            <input type="date" name="from" value="{$from}" class="form-control">
            <label>To</label>
            <input type="date" name="to" value="{$to}" class="form-control">
            <button class="btn btn-default" type="submit">Show summary</button>
          </form>

          {if count($by_period) == 0}
            <p class="text-muted">No harvest saved between {$from} and {$to}.</p>
          {else}
            <div class="row" style="margin-bottom:15px">
              <div class="col-sm-2 col-xs-6"><div class="pp-card"><small><span class="glyphicon glyphicon-calendar"></span> Harvests</small><h4>{$sum.harvests}</h4></div></div>
              <div class="col-sm-2 col-xs-6"><div class="pp-card"><small><span class="glyphicon glyphicon-usd"></span> Gross</small><h4>{$sum.gross}</h4></div></div>
              <div class="col-sm-2 col-xs-6"><div class="pp-card"><small><span class="glyphicon glyphicon-stats"></span> Share</small><h4>{$sum.share}</h4></div></div>
              <div class="col-sm-2 col-xs-6"><div class="pp-card"><small><span class="glyphicon glyphicon-flash"></span> Kuryente</small><h4>{$sum.kuryente}</h4></div></div>
              <div class="col-sm-2 col-xs-6"><div class="pp-card"><small><span class="glyphicon glyphicon-wrench"></span> Tapal</small><h4>{$sum.tapal}</h4></div></div>
              <div class="col-sm-2 col-xs-6"><div class="pp-card"><small><span class="glyphicon glyphicon-piggy-bank"></span> Net payout</small><h4>{$sum.net}</h4></div></div>
            </div>

            <h4>By partner</h4>
            <form method="get" action="{$_url}plugin/partner_payout" class="form-inline pp-search" style="margin-bottom:15px">
            <input type="hidden" name="_route" value="plugin/partner_payout">
            <input type="hidden" name="view" value="harvested">
            <input type="hidden" name="period" value="{$period}">
            <input type="hidden" name="from" value="{$from}">
            <input type="hidden" name="to" value="{$to}">
            {if $show_all}<input type="hidden" name="all" value="1">{/if}
            <input type="text" name="q" value="{$q|escape}" class="form-control" placeholder="Search partner or address" style="min-width:240px">
            <button class="btn btn-default" type="submit"><i class="glyphicon glyphicon-search"></i> Search</button>
            {if $q != ''}<a class="btn btn-link" href="{$_url}plugin/partner_payout&view=harvested&period={$period}&from={$from}&to={$to}{if $show_all}&all=1{/if}">Clear</a>{/if}
          </form>
            {if count($by_partner) == 0}
              <p class="text-muted">No partner found for "{$q|escape}".</p>
            {/if}
            <div class="table-responsive">
              <table class="table table-bordered table-condensed pp-striped">
                <thead>
                  <tr><th>No</th><th>Partner</th><th>Address</th><th class="text-right">Harvests</th>
                  <th class="text-right">Gross</th><th class="text-right">Share</th><th class="text-right">Kuryente</th>
                  <th class="text-right">Tapal</th><th class="text-right">Net</th><th>Collected by</th></tr>
                </thead>
                <tbody>
                {foreach $by_partner as $i => $r}
                  <tr>
                    <td>{$offset + $i + 1}</td><td>{$r.name}</td><td>{$r.address}</td>
                    <td class="text-right">{$r.harvests}</td>
                    <td class="text-right">{$r.gross}</td>
                    <td class="text-right">{$r.share}</td>
                    <td class="text-right">{$r.kuryente}</td>
                    <td class="text-right">{$r.tapal}</td>
                    <td class="text-right">{$r.net}</td>
                    <td>{$r.collectors|escape}</td>
                  </tr>
                {/foreach}
                </tbody>
                <tfoot>
                  <tr>
                    <th colspan="3" class="text-right">Total (all partners)</th>
                    <th class="text-right">{$sum.harvests}</th>
                    <th class="text-right">{$sum.gross}</th>
                    <th class="text-right">{$sum.share}</th>
                    <th class="text-right">{$sum.kuryente}</th>
                    <th class="text-right">{$sum.tapal}</th>
                    <th class="text-right">{$sum.net}</th>
                    <th></th>
                  </tr>
                </tfoot>
              </table>
            </div>

{if $paginator}
                <center>
                <nav aria-label="Page navigation pagination-sm">
                    <ul class="pagination">
                        <li {if empty($paginator['prev'])}class="disabled" {/if}>
                            <a href="{$paginator['url']}{$paginator['prev']}" aria-label="Previous">
                                <span aria-hidden="true">{Lang::T('Prev')}</span>
                            </a>
                        </li>
                        {foreach $paginator['pages'] as $page}
                            <li class="{if $paginator['page'] == $page}active{elseif $page == '...'}disabled{/if}"><a
                                    href="{$paginator['url']}{$page}">{$page}</a></li>
                        {/foreach}
                        <li {if $paginator['page']>=$paginator['count']}class="disabled" {/if}>
                            <a href="{$paginator['url']}{$paginator['next']}" aria-label="Next">
                                <span aria-hidden="true">{Lang::T('Next')}</span>
                            </a>
                        </li>
                    </ul>
                </nav>
                </center>
            {/if}
            
                        <h4>By harvest date</h4>
            <div class="table-responsive">
              <table class="table table-bordered table-condensed pp-striped">
                <thead>
                  <tr><th>Date</th><th class="text-right">Vendo harvested</th><th class="text-right">Gross</th>
                  <th class="text-right">Share</th><th class="text-right">Kuryente</th><th class="text-right">Tapal</th>
                  <th class="text-right">Net</th><th>Collected by</th><th></th></tr>
                </thead>
                <tbody>
                {foreach $by_period as $r}
                  <tr>
                    <td>{$r.period}</td>
                    <td class="text-right">{$r.partners}</td>
                    <td class="text-right">{$r.gross}</td>
                    <td class="text-right">{$r.share}</td>
                    <td class="text-right">{$r.kuryente}</td>
                    <td class="text-right">{$r.tapal}</td>
                    <td class="text-right">{$r.net}</td>
                    <td>{$r.collectors|escape}</td>
                    <td><a class="btn btn-default btn-xs" href="{$_url}plugin/partner_payout&view=harvest&period={$r.period}&from={$from}&to={$to}&tab=done">Open</a></td>
                  </tr>
                {/foreach}
                </tbody>
              </table>
            </div>
          {/if}
        {/if}

        {* ================= COLLECTORS ================= *}
        {if $view == 'collectors'}
          <p class="text-muted">Collectors log in at <b>{$_url}plugin/partner_collector</b> with their own username and password. They only see the partners assigned to them for the month (assign in <b>List of Vendo</b>; assignments start blank every month). The Partners column below counts {$month_label}.</p>
          <form method="post" action="{$_url}plugin/partner_payout/collector_add{$qs}" class="form-inline" style="margin-bottom:15px" autocomplete="off">
            {if isset($csrf_token)}<input type="hidden" name="csrf_token" value="{$csrf_token}">{/if}
            <input class="form-control" name="name" placeholder="Collector name" required>
            <input class="form-control" name="username" placeholder="Username" required autocomplete="off">
            <input class="form-control" type="password" name="password" placeholder="Password (min 6)" required autocomplete="new-password">
            <button class="btn btn-primary" type="submit">Add collector</button>
          </form>

          {if count($collectors) == 0}
            <p>No collectors yet.</p>
          {else}
          <div class="table-responsive">
            <table class="table table-bordered table-condensed pp-striped">
              <thead>
                <tr><th>Name</th><th>Username</th><th>Partners</th><th>Access</th><th>New password</th><th></th></tr>
              </thead>
              <tbody>
              {foreach $collectors as $c}
                <tr>
                  <td>{$c.name|escape}</td>
                  <td>{$c.username|escape}</td>
                  <td>{$c.partner_count}</td>
                  <td>
                    <form method="post" action="{$_url}plugin/partner_payout/collector_toggle{$qs}" style="margin:0">
                      {if isset($csrf_token)}<input type="hidden" name="csrf_token" value="{$csrf_token}">{/if}
                      <input type="hidden" name="id" value="{$c.id}">
                      {if $c.active}
                        <button class="btn btn-success btn-xs" type="submit" title="Click to disable">Active</button>
                      {else}
                        <button class="btn btn-default btn-xs" type="submit" title="Click to enable">Disabled</button>
                      {/if}
                    </form>
                  </td>
                  <td>
                    <form method="post" action="{$_url}plugin/partner_payout/collector_pass{$qs}" class="form-inline" style="margin:0" autocomplete="off">
                      {if isset($csrf_token)}<input type="hidden" name="csrf_token" value="{$csrf_token}">{/if}
                      <input type="hidden" name="id" value="{$c.id}">
                      <input class="form-control input-sm" type="password" name="password" placeholder="New password" required autocomplete="new-password">
                      <button class="btn btn-default btn-xs" type="submit">Change</button>
                    </form>
                  </td>
                  <td>
                    <form method="post" action="{$_url}plugin/partner_payout/collector_delete{$qs}" style="margin:0">
                      {if isset($csrf_token)}<input type="hidden" name="csrf_token" value="{$csrf_token}">{/if}
                      <input type="hidden" name="id" value="{$c.id}">
                      <button class="btn btn-danger btn-xs" type="submit" onclick="return confirm('Delete this collector? Their partners become unassigned.')">&times;</button>
                    </form>
                  </td>
                </tr>
              {/foreach}
              </tbody>
            </table>
          </div>
          {/if}
        {/if}

        {* ================= LIST OF VENDO ================= *}
        {if $view == 'list'}
          <form method="get" action="{$_url}plugin/partner_payout" class="form-inline" style="margin-bottom:10px">
            <input type="hidden" name="_route" value="plugin/partner_payout">
            <input type="hidden" name="view" value="list">
            <input type="hidden" name="from" value="{$from}">
            <input type="hidden" name="to" value="{$to}">
            <label>Collector assignment for</label>
            <input type="month" name="month" value="{$month}" placeholder="YYYY-MM" class="form-control">
            <button class="btn btn-default" type="submit">Load</button>
          </form>
          <p class="text-muted">Collectors are assigned per month. Every month starts blank on the 1st, so assign the partners again. You can also open next month and assign ahead.</p>
          {if count($partners) == 0 && $q != ''}
            <form method="get" action="{$_url}plugin/partner_payout" class="form-inline pp-search" style="margin-bottom:15px">
            <input type="hidden" name="_route" value="plugin/partner_payout">
            <input type="hidden" name="view" value="list">
            <input type="hidden" name="period" value="{$period}">
            <input type="hidden" name="from" value="{$from}">
            <input type="hidden" name="to" value="{$to}">
            {if $show_all}<input type="hidden" name="all" value="1">{/if}
            <input type="text" name="q" value="{$q|escape}" class="form-control" placeholder="Search partner or address" style="min-width:240px">
            <button class="btn btn-default" type="submit"><i class="glyphicon glyphicon-search"></i> Search</button>
            {if $q != ''}<a class="btn btn-link" href="{$_url}plugin/partner_payout&view=list&period={$period}&from={$from}&to={$to}{if $show_all}&all=1{/if}">Clear</a>{/if}
          </form>
            <p>No vendo found for "{$q|escape}".</p>
          {elseif count($partners) == 0}
            <p>No partners yet. Add one, or import your Excel/CSV sheet below.</p>
            <form method="post" action="{$_url}plugin/partner_payout/seed{$qs}" style="margin-bottom:15px">
              {if isset($csrf_token)}<input type="hidden" name="csrf_token" value="{$csrf_token}">{/if}
              <button class="btn btn-default" type="submit">Load 20 sample partners</button>
            </form>
          {else}
            <form method="get" action="{$_url}plugin/partner_payout" class="form-inline pp-search" style="margin-bottom:15px">
            <input type="hidden" name="_route" value="plugin/partner_payout">
            <input type="hidden" name="view" value="list">
            <input type="hidden" name="period" value="{$period}">
            <input type="hidden" name="from" value="{$from}">
            <input type="hidden" name="to" value="{$to}">
            {if $show_all}<input type="hidden" name="all" value="1">{/if}
            <input type="text" name="q" value="{$q|escape}" class="form-control" placeholder="Search partner or address" style="min-width:240px">
            <button class="btn btn-default" type="submit"><i class="glyphicon glyphicon-search"></i> Search</button>
            {if $q != ''}<a class="btn btn-link" href="{$_url}plugin/partner_payout&view=list&period={$period}&from={$from}&to={$to}{if $show_all}&all=1{/if}">Clear</a>{/if}
          </form>
            <p><b>{$found}</b> vendo partners{if $q != ''} found for "{$q|escape}"{/if}</p>
            <form method="post" action="{$_url}plugin/partner_payout/delete{$qs}">
              {if isset($csrf_token)}<input type="hidden" name="csrf_token" value="{$csrf_token}">{/if}
              <div class="table-responsive">
                <table class="table table-bordered table-condensed pp-striped">
                  <thead>
                    <tr><th>No</th><th>Partner</th><th>Address</th><th>Schedule</th><th>Share %</th><th>Collector &middot; {$month_label}</th><th></th></tr>
                  </thead>
                  <tbody>
                  {foreach $partners as $i => $p}
                    <tr>
                      <td>{$offset + $i + 1}</td><td>{$p.name}</td><td>{$p.address}</td>
                      <td>{$p.schedule}</td><td>{$p.share + 0}</td>
                      <td>
                        <select class="form-control input-sm" onchange="ppAssign({$p.id}, this.value)">
                          <option value="0">- none -</option>
                          {foreach $collectors as $c}<option value="{$c.id}" {if $p.collector_id == $c.id}selected{/if}>{$c.name|escape}{if !$c.active} (disabled){/if}</option>{/foreach}
                        </select>
                      </td>
                      <td>
                        <button type="submit" class="btn btn-danger btn-xs" name="id" value="{$p.id}"
                          onclick="return confirm('Delete this partner and all of their saved harvests?')">&times;</button>
                      </td>
                    </tr>
                  {/foreach}
                  </tbody>
                </table>
              </div>
            </form>
            {if $paginator}
                <center>
                <nav aria-label="Page navigation pagination-sm">
                    <ul class="pagination">
                        <li {if empty($paginator['prev'])}class="disabled" {/if}>
                            <a href="{$paginator['url']}{$paginator['prev']}" aria-label="Previous">
                                <span aria-hidden="true">{Lang::T('Prev')}</span>
                            </a>
                        </li>
                        {foreach $paginator['pages'] as $page}
                            <li class="{if $paginator['page'] == $page}active{elseif $page == '...'}disabled{/if}"><a
                                    href="{$paginator['url']}{$page}">{$page}</a></li>
                        {/foreach}
                        <li {if $paginator['page']>=$paginator['count']}class="disabled" {/if}>
                            <a href="{$paginator['url']}{$paginator['next']}" aria-label="Next">
                                <span aria-hidden="true">{Lang::T('Next')}</span>
                            </a>
                        </li>
                    </ul>
                </nav>
                </center>
            {/if}
                      {/if}

          <form method="post" id="pp-assign-form" action="{$_url}plugin/partner_payout/assign{$qs}">
            {if isset($csrf_token)}<input type="hidden" name="csrf_token" value="{$csrf_token}">{/if}
            <input type="hidden" name="id" id="pp-assign-id" value="">
            <input type="hidden" name="collector_id" id="pp-assign-cid" value="">
          </form>
          {literal}<script>
            function ppAssign(id, cid) {
              document.getElementById('pp-assign-id').value = id;
              document.getElementById('pp-assign-cid').value = cid;
              document.getElementById('pp-assign-form').submit();
            }
          </script>{/literal}

          <hr>
          <h4>Import from Excel / CSV</h4>
          <form method="post" enctype="multipart/form-data" action="{$_url}plugin/partner_payout/import{$qs}">
            {if isset($csrf_token)}<input type="hidden" name="csrf_token" value="{$csrf_token}">{/if}
            <div class="form-group">
              <input type="file" name="sheet" accept=".xlsx,.csv" required>
              <p class="help-block">
                .xlsx or .csv with a header row: PARTNERS, ADRESS, SCHEDULE, SHARE. Other columns are ignored.
                Partners with the same name and address are updated, new ones are added.
              </p>
            </div>
            <button class="btn btn-warning" type="submit">Import file</button>
          </form>
        {/if}

      </div>
    </div>
  </div>
</div>

<style>
{literal}
  #pp-add-modal .form-group { display: block !important; margin-bottom: 15px; }
  #pp-add-modal .form-group label { display: block !important; width: 100% !important; text-align: left !important;
    float: none !important; margin: 0 0 6px 0; padding: 0; font-weight: 600; }
  #pp-add-modal .form-control { display: block !important; width: 100% !important; float: none !important; }
  #pp-add-modal .modal-body { padding-bottom: 5px; }
{/literal}
</style>
<div class="modal fade" id="pp-add-modal" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <form method="post" action="{$_url}plugin/partner_payout/add{$qs}" class="modal-content">
      {if isset($csrf_token)}<input type="hidden" name="csrf_token" value="{$csrf_token}">{/if}
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        <h4 class="modal-title">Add partner</h4>
      </div>
      <div class="modal-body">
        <div class="form-group">
          <label>Partner name</label>
          <input class="form-control" name="name" placeholder="Partner name" required>
        </div>
        <div class="form-group">
          <label>Address</label>
          <input class="form-control" name="address" value="AMAS">
        </div>
        <div class="row">
          <div class="col-xs-6 form-group">
            <label>Schedule</label>
            <select class="form-control" name="schedule"><option>15</option><option>30</option></select>
          </div>
          <div class="col-xs-6 form-group">
            <label>Share %</label>
            <input class="form-control" type="number" step="0.01" min="0" max="100" name="share" value="30">
          </div>
        </div>
        <div class="form-group">
          <label>Collector (for {$month_label})</label>
          <select class="form-control" name="collector_id">
            <option value="0">- none -</option>
            {foreach $collectors as $c}<option value="{$c.id}">{$c.name|escape}{if !$c.active} (disabled){/if}</option>{/foreach}
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary">Add</button>
      </div>
    </form>
  </div>
</div>

{* ================= COLLECT / EDIT POPUP ================= *}
<div class="modal fade" id="pp-collect-modal" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <form method="post" action="{$_url}plugin/partner_payout/save{$qs}" class="modal-content" id="pp-collect-form" autocomplete="off">
      {if isset($csrf_token)}<input type="hidden" name="csrf_token" value="{$csrf_token}">{/if}
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        <h4 class="modal-title" id="pp-m-title">Collect</h4>
        <div class="pp-m-sub" id="pp-m-sub"></div>
      </div>
      <div class="modal-body">
        <div class="pp-m-info">
          <span class="pp-m-chip"><span class="glyphicon glyphicon-map-marker"></span> <span id="pp-m-addr"></span></span>
          <span class="pp-m-chip">Share <span id="pp-m-share"></span>%</span>
        </div>
        <div class="form-group">
          <label>Gross (&#8369;)</label>
          <input type="number" step="0.01" min="0" inputmode="decimal" class="form-control pp-m-gross" id="pp-m-gross" placeholder="0.00" required>
        </div>
        <div class="row">
          <div class="col-xs-6 form-group">
            <label>Kuryente (&#8369;)</label>
            <input type="number" step="0.01" min="0" inputmode="decimal" class="form-control" id="pp-m-kuryente" placeholder="0.00">
          </div>
          <div class="col-xs-6 form-group">
            <label>Tapal (&#8369;)</label>
            <input type="number" step="0.01" min="0" inputmode="decimal" class="form-control" id="pp-m-tapal" placeholder="0.00">
          </div>
        </div>
        <div class="pp-m-net"><span>Net payout</span><b id="pp-m-net">&#8369;0.00</b></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-success"><span class="glyphicon glyphicon-floppy-disk"></span> Save</button>
      </div>
    </form>
  </div>
</div>

{literal}
<script>
(function () {
  var table = document.getElementById('pp-table');
  if (!table) return;

  function num(v) { return parseFloat(v) || 0; }
  function netOf(g, k, t, share) { var base = g - k - t; return base - base * share / 100; }

  /* Fill Net column + page totals from the row data */
  function calcTable() {
    var tg = 0, tn = 0;
    table.querySelectorAll('tbody tr').forEach(function (tr) {
      var g = num(tr.dataset.gross), k = num(tr.dataset.kuryente), t = num(tr.dataset.tapal);
      var cell = tr.querySelector('.pp-net');
      if (g || k || t) {
        var n = netOf(g, k, t, num(tr.dataset.share));
        cell.textContent = n.toFixed(2); tg += g; tn += n;
      } else { cell.textContent = '-'; }
    });
    document.getElementById('pp-tg').textContent = tg.toFixed(2);
    document.getElementById('pp-tn').textContent = tn.toFixed(2);
  }
  calcTable();

  /* Popup */
  var $ = function (id) { return document.getElementById(id); };
  var fG = $('pp-m-gross'), fK = $('pp-m-kuryente'), fT = $('pp-m-tapal');
  var curShare = 0;

  function liveNet() {
    var g = num(fG.value), k = num(fK.value), t = num(fT.value);
    $('pp-m-net').textContent = '\u20B1' + netOf(g, k, t, curShare).toFixed(2);
  }
  [fG, fK, fT].forEach(function (f) { f.addEventListener('input', liveNet); });

  table.addEventListener('click', function (e) {
    var b = e.target.closest ? e.target.closest('.pp-open') : null;
    if (!b) return;
    var id = b.dataset.id, no = b.dataset.no, p = b.dataset.prefix || '';
    var suffix = p ? '[' + id + '][' + no + ']' : '[' + id + ']';
    fG.name = p + 'gross' + suffix;
    fK.name = p + 'kuryente' + suffix;
    fT.name = p + 'tapal' + suffix;
    fG.value = b.dataset.gross; fK.value = b.dataset.kuryente; fT.value = b.dataset.tapal;
    curShare = num(b.dataset.share);
    $('pp-m-title').textContent = b.dataset.title;
    $('pp-m-sub').textContent = b.dataset.name;
    $('pp-m-addr').textContent = b.dataset.address || '-';
    $('pp-m-share').textContent = curShare;
    liveNet();
    window.jQuery && jQuery('#pp-collect-modal').modal('show');
  });

  if (window.jQuery) {
    jQuery('#pp-collect-modal').on('shown.bs.modal', function () { fG.focus(); fG.select(); });
  }

  /* Prevent saving an empty collection by accident */
  $('pp-collect-form').addEventListener('submit', function (e) {
    if (!num(fG.value) && !num(fK.value) && !num(fT.value)) {
      e.preventDefault(); fG.focus();
    }
  });
})();
</script>
{/literal}

{include file="sections/footer.tpl"}
