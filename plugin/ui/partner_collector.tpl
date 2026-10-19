<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Collector</title>
{literal}
<script>
/* apply the saved theme before first paint (no flash); first visit follows the phone's setting */
(function () {
  try {
    var t = localStorage.getItem('pc_theme');
    if (!t) { t = (window.matchMedia && matchMedia('(prefers-color-scheme: dark)').matches) ? 'dark' : 'light'; }
    document.documentElement.setAttribute('data-theme', t);
  } catch (e) {}
})();
</script>
<style>
  :root {
    --bg:#f3f7fc; --card:#fff; --text:#17263d; --muted:#738198; --line:#e2eaf4;
    --input:#f9fbff; --head:#103d70; --head-text:#fff; --brand:#0878e8; --brand-dark:#0564c5;
    --ok:#159b68; --err:#e34f5f; --t-green:#159b68; --t-blue:#0878e8; --t-yellow:#e99a19; --t-red:#e34f5f;
    --shadow:0 10px 28px rgba(25,65,115,.08);
  }
  [data-theme="dark"] {
    --bg:#101923; --card:#182433; --text:#edf4ff; --muted:#9aaac0; --line:#2b3b50;
    --input:#111d2b; --head:#0b1726; --head-text:#f4f8ff; --brand:#3998ff; --brand-dark:#1878df;
    --ok:#32c990; --err:#ff7887; --t-green:#117b59; --t-blue:#145eaa; --t-yellow:#9a6412; --t-red:#a92d3b;
    --shadow:0 10px 28px rgba(0,0,0,.18);
  }
  *{box-sizing:border-box}
  [hidden]{display:none!important}
  body{margin:0;background:var(--bg);color:var(--text);font:15px/1.5 "Inter","Segoe UI",Arial,sans-serif;transition:background .25s,color .25s}
  button,input{font:inherit}
  button{transition:transform .18s,box-shadow .18s,background .18s}
  button:focus-visible,input:focus-visible,.partner-row:focus-visible{outline:3px solid rgba(8,120,232,.25);outline-offset:2px}
  .topbar{background:linear-gradient(115deg,var(--head),#1261a4);color:var(--head-text);position:sticky;top:0;z-index:10;box-shadow:0 5px 22px rgba(13,51,91,.16)}
  .topbar .in{max-width:920px;margin:auto;padding:13px 18px;display:flex;align-items:center;justify-content:space-between;gap:12px}
  .brandline{display:flex;align-items:center;gap:10px;min-width:0}
  .brandmark{width:40px;height:40px;display:grid;place-items:center;background:rgba(255,255,255,.13);border:1px solid rgba(255,255,255,.2);border-radius:13px;flex-shrink:0}
  .brandmark svg{width:24px;height:24px}
  .who{font-size:17px;font-weight:800;line-height:1.2;letter-spacing:-.3px}
  .sub{font-size:12px;opacity:.78;font-weight:400;margin-top:3px}
  .acts{display:flex;gap:8px;align-items:center}
  .topbar button{display:inline-flex;align-items:center;justify-content:center;gap:7px;background:rgba(255,255,255,.12);color:inherit;border:1px solid rgba(255,255,255,.18);border-radius:11px;padding:9px 12px;font-size:13px;font-weight:700;cursor:pointer}
  .topbar button:hover{background:rgba(255,255,255,.22);transform:translateY(-1px)}
  .topbar button svg{width:17px;height:17px}
  .topbar form{margin:0}
  .wrap{max-width:920px;margin:0 auto;padding:22px 16px 32px}
  .page-intro{display:flex;align-items:flex-end;justify-content:space-between;gap:12px;margin:2px 0 18px;animation:riseIn .45s ease both}
  .page-intro h1{font-size:25px;letter-spacing:-.8px;margin:0;font-weight:850}
  .page-intro p{margin:4px 0 0;color:var(--muted)}
  .eyebrow{font-size:11px;text-transform:uppercase;letter-spacing:1.6px;font-weight:800;color:var(--brand);margin-bottom:5px}
  .card{background:var(--card);border:1px solid var(--line);border-radius:18px;padding:18px;margin-bottom:14px;box-shadow:var(--shadow);animation:riseIn .45s ease both}
  .card.plain{border-top-color:var(--line)}
  .login-card{max-width:460px;margin:7vh auto 0;padding:28px}
  .login-emblem{width:58px;height:58px;display:grid;place-items:center;border-radius:18px;color:#fff;background:linear-gradient(135deg,#0b8cff,#0756b8);box-shadow:0 10px 24px rgba(8,120,232,.25);margin-bottom:18px;animation:float 3.5s ease-in-out infinite}
  .login-emblem svg{width:30px;height:30px}
  h1{font-size:22px;margin:0 0 5px;letter-spacing:-.5px}
  .muted{color:var(--muted);font-size:13px}
  label{display:block;font-weight:750;margin:15px 0 6px;font-size:13px}
  .field{position:relative}
  .field-icon{position:absolute;left:13px;top:50%;transform:translateY(-50%);width:18px;height:18px;color:var(--muted);pointer-events:none}
  input[type=text],input[type=password],input[type=date],input[type=month],input[type=number]{width:100%;padding:12px 13px;border:1px solid var(--line);border-radius:12px;background:var(--input);color:var(--text);transition:border .2s,box-shadow .2s}
  .field input{padding-left:42px}
  input:focus{outline:0;border-color:var(--brand);box-shadow:0 0 0 4px rgba(8,120,232,.10)}
  .btn{display:inline-flex;align-items:center;justify-content:center;gap:9px;font-size:14px;padding:12px 16px;border:0;border-radius:12px;background:linear-gradient(110deg,var(--brand),#0568d2);color:#fff;font-weight:800;cursor:pointer;box-shadow:0 7px 16px rgba(8,120,232,.16)}
  .btn svg{width:18px;height:18px}
  .btn:hover{background:var(--brand-dark);transform:translateY(-1px);box-shadow:0 10px 20px rgba(8,120,232,.23)}
  .btn:active{transform:translateY(0)}
  .btn.ghost{background:var(--bg);color:var(--text);box-shadow:none;border:1px solid var(--line)}
  .btn.ghost:hover{background:var(--line)}
  .full{width:100%}
  .flash{padding:12px 14px;border-radius:13px;margin-bottom:14px;font-weight:700;border:1px solid transparent;animation:riseIn .3s ease both}
  .flash.s{background:rgba(21,155,104,.1);color:var(--ok);border-color:rgba(21,155,104,.25)}
  .flash.e{background:rgba(227,79,95,.1);color:var(--err);border-color:rgba(227,79,95,.25)}
  .tiles{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:13px;margin-bottom:16px}
  .tile{position:relative;overflow:hidden;border-radius:17px;padding:17px;color:#fff;box-shadow:0 9px 22px rgba(20,54,90,.10);animation:riseIn .45s ease both;transition:transform .2s}
  .tile:hover{transform:translateY(-3px)}
  .tile:after{content:"";position:absolute;width:100px;height:100px;border:1px solid rgba(255,255,255,.14);border-radius:50%;right:-30px;top:-42px;box-shadow:0 0 0 13px rgba(255,255,255,.04),0 0 0 27px rgba(255,255,255,.025)}
  .tile.wide{grid-column:auto}
  .tile .tile-icon{width:36px;height:36px;border-radius:12px;display:grid;place-items:center;background:rgba(255,255,255,.17);margin-bottom:13px}
  .tile .tile-icon svg{width:21px;height:21px}
  .tile .lbl{font-size:12px;opacity:.9;font-weight:650}
  .tile .val{font-size:clamp(22px,3vw,29px);font-weight:850;line-height:1.2;margin-top:5px;letter-spacing:-.7px}
  .tile .note{font-size:12px;opacity:.85;margin-top:5px}
  .tile.green{background:linear-gradient(135deg,#159b68,#087c5a)}
  .tile.blue{background:linear-gradient(135deg,#1687f8,#0758b9)}
  .tile.yellow{background:linear-gradient(135deg,#f0a52d,#cf7910)}
  .tile.red{background:linear-gradient(135deg,#f06472,#c9364c)}
  .section-title{font-size:16px;font-weight:850;letter-spacing:-.3px;margin:0}
  .month-form{display:flex;gap:10px;align-items:flex-end}
  .month-form>div{flex:1;min-width:0}
  .month-form label{margin-top:0}
  .tabs{display:flex;gap:5px;border-bottom:1px solid var(--line);margin:-2px 0 15px;padding-bottom:0}
  .tab{flex:1;display:flex;justify-content:center;align-items:center;gap:7px;background:none;border:0;border-bottom:3px solid transparent;margin-bottom:-1px;padding:13px 5px;font-size:13px;font-weight:800;color:var(--muted);cursor:pointer}
  .tab.active{color:var(--brand);border-bottom-color:var(--brand)}
  .cnt{display:inline-flex;align-items:center;justify-content:center;min-width:23px;padding:2px 7px;border-radius:99px;background:var(--bg);font-size:11px;color:var(--text)}
  .tab.active .cnt{background:var(--brand);color:#fff}
  .grp{font-size:11px;font-weight:850;text-transform:uppercase;letter-spacing:1px;margin:17px 3px 8px;color:var(--muted)}
  .grp.overdue{color:var(--err)}.grp.today{color:var(--brand)}

  /* client list: details only, tap a row to open the entry modal */
  .partner-card{border:1px solid var(--line);border-radius:15px;padding:15px;margin-bottom:10px;background:var(--card);transition:transform .2s,border-color .2s,box-shadow .2s;animation:riseIn .35s ease both}
  .partner-row{cursor:pointer;-webkit-tap-highlight-color:transparent;user-select:none}
  .partner-row:hover{transform:translateY(-2px);border-color:rgba(8,120,232,.35);box-shadow:0 8px 20px rgba(20,54,90,.07)}
  .partner-row:active{transform:translateY(0)}
  .partner-card.locked{opacity:.92}
  .row-head{display:flex;justify-content:space-between;gap:10px;align-items:flex-start}
  .row-end{display:flex;align-items:center;gap:8px;flex-shrink:0}
  .chev{width:18px;height:18px;color:var(--muted)}
  .pname{font-weight:850;font-size:15px;letter-spacing:-.2px}
  .partner-meta{display:flex;align-items:center;gap:5px;margin-top:4px}
  .partner-meta svg{width:14px;height:14px;flex-shrink:0}
  .sumline{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px;margin-top:12px;padding-top:11px;border-top:1px dashed var(--line)}
  .sumline div{min-width:0}
  .sumline small{display:block;font-size:11px;color:var(--muted);font-weight:700}
  .sumline b{display:block;font-size:14px;font-weight:850;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
  .tap-hint{margin-top:10px;font-size:12px;color:var(--muted)}
  .badge{display:inline-flex;align-items:center;gap:5px;font-size:11px;padding:5px 9px;border-radius:99px;white-space:nowrap;font-weight:800}
  .badge.done{background:rgba(21,155,104,.12);color:var(--ok)}
  .badge.late{background:rgba(227,79,95,.12);color:var(--err)}
  .badge.today{background:rgba(8,120,232,.11);color:var(--brand)}
  .badge.todo{background:var(--bg);color:var(--muted)}
  .grid2{display:grid;grid-template-columns:1fr 1fr;gap:10px}
  .amount-label{display:flex;align-items:center;gap:6px}
  .amount-label svg{width:15px;height:15px;color:var(--brand)}

  /* modal */
  .modal{position:fixed;inset:0;z-index:50;display:flex;align-items:flex-end;justify-content:center;background:rgba(8,18,32,.58);animation:fadeIn .2s ease both}
  .modal-box{width:100%;max-width:440px;max-height:92vh;overflow:auto;background:var(--card);border:1px solid var(--line);border-radius:22px 22px 0 0;padding:20px 20px calc(20px + env(safe-area-inset-bottom,0px));box-shadow:0 -10px 40px rgba(0,0,0,.25);animation:slideUp .25s ease both}
  .modal-head{display:flex;justify-content:space-between;align-items:flex-start;gap:10px;margin-bottom:4px}
  .modal-head h2{margin:0;font-size:18px;letter-spacing:-.4px;font-weight:850}
  .modal-x{width:34px;height:34px;flex-shrink:0;display:grid;place-items:center;border:0;border-radius:10px;background:var(--bg);color:var(--text);cursor:pointer}
  .modal-x svg{width:18px;height:18px}
  .modal-note{margin-top:2px}
  .modal-actions{display:grid;grid-template-columns:1fr 2fr;gap:10px;margin-top:20px}
  @media(min-width:560px){.modal{align-items:center;padding:16px}.modal-box{border-radius:22px}}

  .empty-state{text-align:center;padding:24px 14px}
  @keyframes riseIn{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}
  @keyframes fadeIn{from{opacity:0}to{opacity:1}}
  @keyframes slideUp{from{transform:translateY(40px);opacity:.6}to{transform:translateY(0);opacity:1}}
  @keyframes float{0%,100%{transform:translateY(0)}50%{transform:translateY(-5px)}}
  @media(min-width:720px){.wrap{padding:28px 22px 40px}.login-card{margin-top:9vh}.tile{padding:20px}.card{padding:20px}}
  @media(max-width:560px){
    .topbar .in{padding:11px 12px}.brandmark{width:36px;height:36px}.who{font-size:15px}
    .topbar button{padding:9px}.topbar .logout-text{display:none}
    .wrap{padding:16px 12px 25px}.page-intro h1{font-size:22px}.page-intro{align-items:flex-start}
    .tiles{grid-template-columns:1fr 1fr;gap:9px}.tile{padding:13px;border-radius:15px}.tile:first-child{grid-column:1/-1}
    .tile .val{font-size:24px}.tile:first-child .val{font-size:29px}
    .card{padding:14px;border-radius:15px}.login-card{margin:5vh auto 0;padding:22px}
    .tabs{gap:0}.tab{font-size:12px;flex-direction:column;gap:4px;padding:10px 3px}
    .row-head{gap:7px}.pname{font-size:14px}.badge{font-size:10px;padding:5px 7px}
    .month-form{align-items:flex-end}.month-form .btn{padding:12px}
  }
  @media(prefers-reduced-motion:reduce){*,*::before,*::after{animation-duration:.01ms!important;animation-iteration-count:1!important;scroll-behavior:auto!important;transition-duration:.01ms!important}}
</style>
{/literal}
</head>
<body>

<div class="topbar"><div class="in">
  <div class="brandline">
    <div class="brandmark" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 20v-1.5A3.5 3.5 0 0 1 6.5 15h5A3.5 3.5 0 0 1 15 18.5V20"/><circle cx="9" cy="8" r="3.5"/><path d="M16 5.5a5 5 0 0 1 5 5M17 9a2 2 0 0 1 2 2"/></svg></div>
    <div class="who">{if $pc_me == ''}BILLSYS Collector{else}{$pc_me|escape}{/if}<div class="sub">{if $pc_me == ''}Partner collection portal{else}{$pc_totals.count} partner(s) assigned to you{/if}</div></div>
  </div>
  <div class="acts">
    <button type="button" id="themeBtn" title="Switch theme" aria-label="Switch light or dark mode"><svg id="themeIcon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M4.93 4.93l1.42 1.42m11.3 11.3 1.42 1.42M2 12h2m16 0h2M4.93 19.07l1.42-1.42m11.3-11.3 1.42-1.42"/></svg></button>
    {if $pc_me != ''}
    <form method="post" action="{$pc_base}/logout">
      <input type="hidden" name="csrf" value="{$pc_csrf}">
      <button type="submit" aria-label="Log out"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10 17l5-5-5-5m5 5H3"/><path d="M12 3h6a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-6"/></svg><span class="logout-text">Log out</span></button>
    </form>
    {/if}
  </div>
</div></div>

<div class="wrap">

{if $pc_flash}<div class="flash {if $pc_flash.type == 's'}s{else}e{/if}">{$pc_flash.msg|escape}</div>{/if}

{if $pc_me == ''}
  {* ---------------- LOGIN ---------------- *}
  <div class="card login-card">
    <div class="login-emblem" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 20v-1.5A3.5 3.5 0 0 1 6.5 15h5A3.5 3.5 0 0 1 15 18.5V20"/><circle cx="9" cy="8" r="3.5"/><path d="M16 5.5a5 5 0 0 1 5 5M17 9a2 2 0 0 1 2 2"/></svg></div>
    <div class="eyebrow">Partner portal</div>
    <h1>Welcome back</h1>
    <p class="muted">Sign in to manage your collections and daily reports.</p>
    <form method="post" action="{$pc_base}/login" autocomplete="on">
      <input type="hidden" name="csrf" value="{$pc_csrf}">
      <label for="u">Username</label>
      <div class="field"><svg class="field-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2"/></svg><input type="text" id="u" name="username" required autocapitalize="none" autocomplete="username" placeholder="Enter your username"></div>
      <label for="p">Password</label>
      <div class="field"><svg class="field-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3m-4 5v2"/></svg><input type="password" id="p" name="password" required autocomplete="current-password" placeholder="Enter your password"></div>
      <button class="btn full" style="margin-top:22px" type="submit"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6"/></svg>Sign in securely</button>
    </form>
    <p class="muted" style="text-align:center;margin:18px 0 0">BILLSYS Collector · Secure access</p>
  </div>
{else}
  {* ---------------- DASHBOARD ---------------- *}
  <div class="page-intro">
    <div><div class="eyebrow">Collection workspace</div><h1>Dashboard</h1><p>Monthly collection overview and partner status.</p></div>
  </div>
  <div class="tiles">
    <div class="tile green">
      <div class="tile-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2.5" y="5" width="19" height="14" rx="2"/><circle cx="12" cy="12" r="3"/><path d="M6 9h.01M18 15h.01"/></svg></div>
      <div class="lbl">Total collected · {$pc_month_label}</div>
      <div class="val">&#8369;<span id="tot-gross">{$pc_totals.gross}</span></div>
    </div>
    <div class="tile blue">
      <div class="tile-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg></div>
      <div class="lbl">Collected partners</div>
      <div class="val"><span id="tot-done">{$pc_totals.done}</span><span style="font-size:15px;font-weight:650;opacity:.8"> / {$pc_totals.count}</span></div>
    </div>
    <div class="tile yellow">
      <div class="tile-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m13 2-3 8h7l-6 12 1-9H6l7-11Z"/></svg></div>
      <div class="lbl">Kuryente</div>
      <div class="val">&#8369;<span id="tot-kur">{$pc_totals.kuryente}</span></div>
      <div class="note">Tapal &#8369;<span id="tot-tap">{$pc_totals.tapal}</span></div>
    </div>
  </div>

  <div class="card plain">
    <div class="section-title" style="margin-bottom:12px">Report period</div>
    <form class="month-form" method="get" action="{$_url}plugin/partner_collector">
      <input type="hidden" name="_route" value="plugin/partner_collector">
      <div style="flex:1">
        <label for="d" style="margin-top:0">Month</label>
        <input type="month" id="d" name="month" value="{$pc_month}">
      </div>
      <button class="btn" type="submit"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 7v5h-5M4 17v-5h5"/><path d="M5.5 9A7 7 0 0 1 18 6l2 6M4 12l2 6a7 7 0 0 0 12.5-3"/></svg>Load</button>
    </form>
  </div>

  {if $pc_totals.count == 0}
    <div class="card muted">No partners are assigned to you for {$pc_month_label} yet. Please ask the admin.</div>
  {else}
  <div class="card">
  <div class="section-title" style="margin-bottom:12px">Client list</div>
  <div class="tabs" role="tablist">
    <button type="button" class="tab active" data-tab="todo" role="tab"><svg style="width:16px;height:16px" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>Uncollected <span class="cnt">{$pc_totals.todo}</span></button>
    <button type="button" class="tab" data-tab="done" role="tab"><svg style="width:16px;height:16px" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12 4 4L19 6"/></svg>Collected <span class="cnt">{$pc_totals.done}</span></button>
  </div>

  {if $pc_totals.todo == 0}<div class="muted empty-state" data-empty="todo">All partners are collected for {$pc_month_label}.</div>{/if}
  {if $pc_totals.done == 0}<div class="muted empty-state" data-empty="done" hidden>Nothing collected yet for {$pc_month_label}.</div>{/if}

  {foreach $pc_rows as $r}
    {if $r.group_head != ''}<div class="grp {$r.state}" data-status="todo">{$r.group_head}</div>{/if}
    <div class="partner-card{if !$r.done} partner-row{else} locked{/if}"{if !$r.done} role="button" tabindex="0"{/if} data-status="{if $r.done}done{else}todo{/if}"
      data-id="{$r.id}"
      data-name="{$r.name|escape}"
      data-meta="{$r.address|escape} &middot; harvest day {$r.schedule}"
      data-round="{$r.round}"
      data-note="{if $r.done}{if $r.by != ''}{if $r.round > 1}{$r.round_label} &middot; {/if}Saved by {$r.by|escape} &middot; {$r.at}{else}{if $r.round > 1}{$r.round_label}{else}Collected{/if}{/if}{elseif $r.state == 'overdue'}Overdue {$r.days}d &middot; was due {$r.due_label}{elseif $r.state == 'today'}Due today{else}Due {$r.due_label}{/if}"
      data-amount="{$r.amount}" data-kur="{$r.kuryente}" data-tap="{$r.tapal}">
      <div class="row-head">
        <div style="min-width:0">
          <div class="pname">{$r.name|escape}{if $r.round > 1} <span class="muted" style="font-weight:700;font-size:12px">&middot; {$r.round_label}</span>{/if}</div>
          <div class="muted partner-meta"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/></svg>{$r.address|escape} &middot; harvest day {$r.schedule}</div>
        </div>
        <div class="row-end">
          {if $r.done}<span class="badge done">{if $r.round > 1}{$r.round_label|replace:' collection':''}{else}Collected{/if}</span>
          {elseif $r.state == 'overdue'}<span class="badge late">Overdue {$r.days}d</span>
          {elseif $r.state == 'today'}<span class="badge today">Due today</span>
          {else}<span class="badge todo">Due {$r.due_label}</span>{/if}
          {if $r.done}<svg class="chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-label="Locked"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg>
          {else}<svg class="chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>{/if}
        </div>
      </div>
      {if $r.done || $r.kuryente != '' || $r.tapal != ''}
        <div class="sumline">
          <div><small>Collected</small><b>&#8369;{$r.amount|default:'0.00'}</b></div>
          <div><small>Kuryente</small><b>&#8369;{$r.kuryente|default:'0.00'}</b></div>
          <div><small>Tapal</small><b>&#8369;{$r.tapal|default:'0.00'}</b></div>
        </div>
        {if $r.done}<div class="tap-hint">{if $r.by != ''}Saved by {$r.by|escape} &middot; {$r.at} &middot; {/if}Locked. Ask the admin to change it.</div>{/if}
        {if $r.done && $r.can_again}<button type="button" class="btn ghost full again-btn" data-round="{$r.next_round}" style="margin-top:12px"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17 2l4 4-4 4"/><path d="M3 11V9a3 3 0 0 1 3-3h15M7 22l-4-4 4-4"/><path d="M21 13v2a3 3 0 0 1-3 3H3"/></svg>Collect again ({$r.next_label})</button>{/if}
      {else}
        <div class="tap-hint">Tap to enter amount collected, kuryente and tapal</div>
      {/if}
    </div>
  {/foreach}
  </div>

  {* ---------------- ENTRY MODAL (one modal, filled from the tapped partner) ---------------- *}
  <div class="modal" id="pcModal" role="dialog" aria-modal="true" aria-labelledby="mName" hidden>
    <div class="modal-box">
      <div class="modal-head">
        <h2 id="mName"></h2>
        <button type="button" class="modal-x" data-close aria-label="Close"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg></button>
      </div>
      <div class="muted" id="mMeta"></div>
      <div class="muted modal-note" id="mNote"></div>
      <form method="post" action="{$pc_base}/save" id="pcModalForm">
        <input type="hidden" name="csrf" value="{$pc_csrf}">
        <input type="hidden" name="month" value="{$pc_month}">
        <input type="hidden" name="round" id="mRound" value="1">
        <label class="amount-label" for="mAmount"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2.5" y="5" width="19" height="14" rx="2"/><circle cx="12" cy="12" r="3"/></svg>Amount collected</label>
        <input type="number" id="mAmount" step="0.01" min="0" inputmode="decimal" placeholder="0.00">
        <div class="grid2">
          <div>
            <label class="amount-label" for="mKur"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m13 2-3 8h7l-6 12 1-9H6l7-11Z"/></svg>Kuryente</label>
            <input type="number" id="mKur" step="0.01" min="0" inputmode="decimal" placeholder="0.00">
          </div>
          <div>
            <label class="amount-label" for="mTap"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 2v20M17 6H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>Tapal</label>
            <input type="number" id="mTap" step="0.01" min="0" inputmode="decimal" placeholder="0.00">
          </div>
        </div>
        <div class="modal-actions">
          <button type="button" class="btn ghost" data-close>Cancel</button>
          <button class="btn" type="submit"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2Z"/><path d="M17 21v-8H7v8M7 3v5h8"/></svg>Save</button>
        </div>
      </form>
    </div>
  </div>
  {/if}
{/if}

</div>

{literal}
<script>
(function () {
  /* ---- light / dark toggle ---- */
  var root = document.documentElement;
  var themeBtn = document.getElementById('themeBtn');
  function syncThemeIcon() {
    var icon = document.getElementById('themeIcon');
    if (!icon) return;
    icon.innerHTML = root.getAttribute('data-theme') === 'dark'
      ? '<path d="M20.5 14.5A8.5 8.5 0 0 1 9.5 3.5 8.5 8.5 0 1 0 20.5 14.5Z"/>'
      : '<circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M4.93 4.93l1.42 1.42m11.3 11.3 1.42 1.42M2 12h2m16 0h2M4.93 19.07l1.42-1.42m11.3-11.3 1.42-1.42"/>';
  }
  if (themeBtn) themeBtn.addEventListener('click', function () {
    var next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    root.setAttribute('data-theme', next);
    try { localStorage.setItem('pc_theme', next); } catch (e) {}
    syncThemeIcon();
  });
  syncThemeIcon();

  /* ---- collected / uncollected tabs ---- */
  var tabs = document.querySelectorAll('.tab');
  if (tabs.length) {
    var show = function (name) {
      tabs.forEach(function (t) { t.classList.toggle('active', t.getAttribute('data-tab') === name); });
      document.querySelectorAll('[data-status]').forEach(function (el) { el.hidden = el.getAttribute('data-status') !== name; });
      document.querySelectorAll('[data-empty]').forEach(function (el) { el.hidden = el.getAttribute('data-empty') !== name; });
      try { sessionStorage.setItem('pc_tab', name); } catch (e) {}
    };
    tabs.forEach(function (t) { t.addEventListener('click', function () { show(t.getAttribute('data-tab')); }); });
    var start = 'todo';
    try { start = sessionStorage.getItem('pc_tab') || 'todo'; } catch (e) {}
    show(start);
  }

  /* ---- entry modal: tap a client to enter amount collected, kuryente and tapal ---- */
  var modal = document.getElementById('pcModal');
  if (modal) {
    var fA = document.getElementById('mAmount');
    var fK = document.getElementById('mKur');
    var fT = document.getElementById('mTap');
    var lastRow = null;
    var fR = document.getElementById('mRound');
    var openModal = function (row, again) {
      var id = row.getAttribute('data-id');
      lastRow = row;
      /* again = number of the NEW collection (2, 3 ...) when "Collect again" was tapped; the fields start blank */
      var no = again || 1;
      fR.value = no;
      var nm = row.getAttribute('data-name');
      document.getElementById('mName').textContent = no > 1 ? nm + ' \u00b7 collection ' + no : nm;
      document.getElementById('mMeta').textContent = row.getAttribute('data-meta');
      document.getElementById('mNote').textContent = no > 1 ? 'Collecting again this month. This is saved as its own collection.' : row.getAttribute('data-note');
      /* the field names carry the partner id, so the existing save handler updates only this partner */
      fA.name = 'amount[' + id + ']';
      fK.name = 'kuryente[' + id + ']';
      fT.name = 'tapal[' + id + ']';
      fA.value = no > 1 ? '' : row.getAttribute('data-amount');
      fK.value = no > 1 ? '' : row.getAttribute('data-kur');
      fT.value = no > 1 ? '' : row.getAttribute('data-tap');
      modal.hidden = false;
      document.body.style.overflow = 'hidden';
      setTimeout(function () { fA.focus(); }, 40);
    };
    var closeModal = function () {
      modal.hidden = true;
      document.body.style.overflow = '';
      if (lastRow) { lastRow.focus(); }
    };
    document.addEventListener('click', function (e) {
      if (e.target === modal) { closeModal(); return; }
      if (!e.target.closest) { return; }
      if (e.target.closest('[data-close]')) { closeModal(); return; }
      var again = e.target.closest('.again-btn');
      if (again) { openModal(again.closest('.partner-card'), parseInt(again.getAttribute('data-round'), 10) || 2); return; }
      var row = e.target.closest('.partner-row');
      if (row) { openModal(row); }
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && !modal.hidden) { closeModal(); return; }
      if ((e.key === 'Enter' || e.key === ' ') && e.target.classList && e.target.classList.contains('partner-row')) {
        e.preventDefault();
        openModal(e.target);
      }
    });
  }
})();
</script>
{/literal}
</body>
</html>
