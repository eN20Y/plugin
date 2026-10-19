<?php
/**
 * Partner Collector portal for PHPNuxBill (companion of partner_payout.php)
 * URL: ?_route=plugin/partner_collector
 *
 * - Own login (tbl_pp_collectors) and own session key: never an admin session, no admin menu entry.
 * - A collector only sees the partners assigned to them for that month (assignment resets, blank, every month), sees the total collected, and types in the money collected, kuryente and tapal.
 * - Works per month: one record per partner per month (resets on the 1st), shared with the admin Harvest tab.
 * - "Collect again": after the 1st collection a partner can be collected again in the same month (see PP_MAX_COLLECTIONS in partner_payout.php);
 *   each extra collection is its own record with its own gross / kuryente / tapal.
 * - Saving writes gross / kuryente / tapal into that monthly record (replaces the old values, net is recalculated)
 *   and records who collected it and when.
 * Install: copy this file to system/plugin/ and ui/partner_collector.tpl to system/plugin/ui/
 */

function pc_session()
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

function pc_csrf()
{
    if (empty($_SESSION['pc_csrf'])) {
        $_SESSION['pc_csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['pc_csrf'];
}

function pc_csrf_ok()
{
    return !empty($_SESSION['pc_csrf']) && hash_equals($_SESSION['pc_csrf'], (string) ($_POST['csrf'] ?? ''));
}

function pc_go($url, $type = '', $msg = '')
{
    if ($msg !== '') {
        $_SESSION['pc_flash'] = ['type' => $type, 'msg' => $msg];
    }
    header('Location: ' . $url);
    exit;
}

function partner_collector()
{
    global $ui, $routes;
    pc_session();
    if (!function_exists('pp_install')) {
        die('The partner_payout plugin must be installed too.');
    }
    pp_install();

    $action = $routes[2] ?? 'index';
    $base = U . 'plugin/partner_collector';
    $isPost = $_SERVER['REQUEST_METHOD'] === 'POST';

    // The collector works per month (resets on the 1st): one record per partner per month
    $month = pp_month($_REQUEST['month'] ?? '');

    // ---- who is logged in? (re-checked on every request, so a disabled collector is kicked out) ----
    $me = null;
    if (!empty($_SESSION['pc_collector_id'])) {
        $me = ORM::for_table('tbl_pp_collectors')->find_one((int) $_SESSION['pc_collector_id']);
        if (!$me || !$me['active']) {
            unset($_SESSION['pc_collector_id']);
            $me = null;
        }
    }

    // ---- login ----
    if ($action === 'login' && $isPost) {
        if (!pc_csrf_ok()) {
            pc_go($base, 'e', 'Session expired. Please try again.');
        }
        $u = trim($_POST['username'] ?? '');
        $pw = (string) ($_POST['password'] ?? '');
        $c = $u !== '' ? ORM::for_table('tbl_pp_collectors')->where('username', $u)->find_one() : null;
        if ($c && !empty($c['locked_until']) && strtotime($c['locked_until']) > time()) {
            pc_go($base, 'e', 'Too many failed attempts. Try again in a few minutes.');
        }
        if ($c && $c['active'] && password_verify($pw, $c['password_hash'])) {
            $c->fails = 0;
            $c->locked_until = null;
            $c->save();
            session_regenerate_id(true);
            $_SESSION['pc_collector_id'] = (int) $c['id'];
            pc_go($base);
        }
        if ($c) {
            $c->fails = (int) $c['fails'] + 1;
            if ($c->fails >= 5) {
                $c->fails = 0;
                $c->locked_until = date('Y-m-d H:i:s', time() + 15 * 60);
            }
            $c->save();
        }
        pc_go($base, 'e', 'Wrong username or password.');
    }

    // ---- logout ----
    if ($action === 'logout' && $isPost && pc_csrf_ok()) {
        unset($_SESSION['pc_collector_id']);
        pc_go($base);
    }

    // ---- save collected money ----
    if ($action === 'save' && $isPost) {
        if (!$me) {
            pc_go($base, 'e', 'Please log in.');
        }
        $back = $base . '&month=' . $month;
        if (!pc_csrf_ok()) {
            pc_go($back, 'e', 'Session expired. Please try again.');
        }
        $amounts = $_POST['amount'] ?? [];
        $kurs = $_POST['kuryente'] ?? [];
        $taps = $_POST['tapal'] ?? [];
        $kurs = is_array($kurs) ? $kurs : [];
        $taps = is_array($taps) ? $taps : [];
        $amounts = is_array($amounts) ? $amounts : [];
        $saved = 0;
        $bad = 0;
        $locked = 0;
        $needFirst = 0;
        // Which collection of the month this is: 1 = first, 2 = "collect again", ...
        $no = (int) ($_POST['round'] ?? 1);
        if ($no < 1 || $no > PP_MAX_COLLECTIONS) {
            $no = 1;
        }
        // Only partners assigned to this collector are ever processed
        $mine = pp_collector_partners($me['id'], $month); // assigned for this month only
        $recs = pp_month_records($month, $no);
        $firstRecs = $no > 1 ? pp_month_records($month, 1) : [];
        foreach ($mine as $p) {
            $id = $p['id'];
            $row = $recs[$id] ?? null; // this partner's record for the month

            // Left empty = no change. Otherwise the typed value replaces the old one.
            $gross = $row ? (float) $row['gross'] : 0.0;
            $kur = $row ? (float) $row['kuryente'] : 0.0;
            $tap = $row ? (float) $row['tapal'] : 0.0;
            $newGross = $gross;
            $newKur = $kur;
            $newTap = $tap;
            $touched = false;
            if (isset($amounts[$id]) && trim((string) $amounts[$id]) !== '') {
                $newGross = round(pp_num($amounts[$id]), 2);
                $touched = true;
            }
            if (isset($kurs[$id]) && trim((string) $kurs[$id]) !== '') {
                $newKur = round(pp_num($kurs[$id]), 2);
                $touched = true;
            }
            if (isset($taps[$id]) && trim((string) $taps[$id]) !== '') {
                $newTap = round(pp_num($taps[$id]), 2);
                $touched = true;
            }
            if (!$touched) {
                continue;
            }
            if ($no > 1) {
                $f = $firstRecs[$id] ?? null;
                $prev = $no > 2 ? (pp_month_records($month, $no - 1)[$id] ?? null) : $f;
                if (!$f || (float) $f['gross'] <= 0 || !$prev || (float) $prev['gross'] <= 0) {
                    $needFirst++; // the earlier collection must be saved first
                    continue;
                }
            }
            if ($gross > 0) {
                $locked++; // already collected this month: only the admin can change it
                continue;
            }
            if ($newGross < 0 || $newKur < 0 || $newTap < 0) {
                $bad++;
                continue;
            }
            if (abs($newGross - $gross) < 0.005 && abs($newKur - $kur) < 0.005 && abs($newTap - $tap) < 0.005) {
                continue; // nothing changed
            }
            if (!$row) {
                if ($newGross == 0 && $newKur == 0 && $newTap == 0) {
                    continue;
                }
                $row = ORM::for_table('tbl_pp_payouts')->create();
                $row->period = pp_month_date($month);
                $row->partner_id = $id;
                $row->collect_no = $no;
            }
            $row->share = $p['share'];
            $row->gross = $newGross;
            $row->kuryente = $newKur;
            $row->tapal = $newTap;
            $row->net = pp_net($newGross, $newKur, $newTap, $p['share']);
            $row->collected_by = $me['name'];
            $row->collected_by_id = $me['id'];
            $row->collected_at = date('Y-m-d H:i:s');
            $row->save();
            $saved++;
        }
        if ($bad > 0) {
            pc_go($back, 'e', "$bad entry(ies) had a negative number and were not saved.");
        }
        if ($needFirst > 0 && $saved == 0) {
            pc_go($back, 'e', 'Save the earlier collection of this partner first.');
        }
        if ($locked > 0 && $saved == 0) {
            pc_go($back, 'e', 'Already collected partners can no longer be edited. Please ask the admin to change them.');
        }
        $what = $no > 1 ? pp_ordinal($no) . ' collection of ' : '';
        pc_go($back, 's', $saved > 0 ? "Saved {$what}$saved partner(s) for " . pp_month_label($month) . '.' : 'Nothing changed.');
    }

    // ---- page ----
    $flash = $_SESSION['pc_flash'] ?? null;
    unset($_SESSION['pc_flash']);

    $rows = [];
    $mine = [];
    $tot = ['gross' => 0.0, 'kuryente' => 0.0, 'tapal' => 0.0, 'done' => 0];
    if ($me) {
        $recs = pp_month_records($month);
        $extras = pp_month_extra($month); // "collect again" records of the month
        $mine = pp_collector_partners($me['id'], $month); // assigned for this month only
        foreach ($mine as $p) {
            $r = $recs[$p['id']] ?? null;
            $g = $r ? (float) $r['gross'] : 0.0;
            $k = $r ? (float) $r['kuryente'] : 0.0;
            $t = $r ? (float) $r['tapal'] : 0.0;
            $isDone = $g > 0;
            $st = pp_month_status($p['schedule'], $month, $isDone);
            $tot['gross'] += $g;
            $tot['kuryente'] += $k;
            $tot['tapal'] += $t;
            if ($isDone) {
                $tot['done']++;
            }
            $entries = [[
                'id' => $p['id'],
                'name' => $p['name'],
                'address' => $p['address'],
                'schedule' => $p['schedule'],
                'round' => 1,
                'round_label' => '',
                'amount' => $isDone ? number_format($g, 2, '.', '') : '',
                'kuryente' => $k > 0 ? number_format($k, 2, '.', '') : '',
                'tapal' => $t > 0 ? number_format($t, 2, '.', '') : '',
                'done' => $isDone,
                'state' => $st['state'],
                'group' => $st['group'],
                'group_head' => '',
                'due_label' => $st['due_label'],
                'days' => $st['days'],
                'rank' => $st['rank'],
                'by' => $isDone && !empty($r['collected_by']) ? $r['collected_by'] : '',
                'at' => $isDone && !empty($r['collected_at']) ? date('M j, g:i A', strtotime($r['collected_at'])) : '',
                'can_again' => false,
                'next_round' => 2,
                'next_label' => pp_ordinal(2),
            ]];
            // 2nd collection and later (already saved)
            if ($isDone) {
                foreach ($extras[$p['id']] ?? [] as $xno => $xr) {
                    $xg = (float) $xr['gross'];
                    $xk = (float) $xr['kuryente'];
                    $xt = (float) $xr['tapal'];
                    $tot['gross'] += $xg;
                    $tot['kuryente'] += $xk;
                    $tot['tapal'] += $xt;
                    $entries[] = [
                        'id' => $p['id'],
                        'name' => $p['name'],
                        'address' => $p['address'],
                        'schedule' => $p['schedule'],
                        'round' => $xno,
                        'round_label' => pp_ordinal($xno) . ' collection',
                        'amount' => number_format($xg, 2, '.', ''),
                        'kuryente' => $xk > 0 ? number_format($xk, 2, '.', '') : '',
                        'tapal' => $xt > 0 ? number_format($xt, 2, '.', '') : '',
                        'done' => true,
                        'state' => 'done',
                        'group' => '',
                        'group_head' => '',
                        'due_label' => '',
                        'days' => 0,
                        'rank' => 5000,
                        'by' => !empty($xr['collected_by']) ? $xr['collected_by'] : '',
                        'at' => !empty($xr['collected_at']) ? date('M j, g:i A', strtotime($xr['collected_at'])) : '',
                        'can_again' => false,
                        'next_round' => $xno + 1,
                        'next_label' => pp_ordinal($xno + 1),
                    ];
                }
                // Only the newest collection of a partner offers "Collect again"
                $last = count($entries) - 1;
                $entries[$last]['can_again'] = $entries[$last]['next_round'] <= PP_MAX_COLLECTIONS;
            }
            foreach ($entries as $en) {
                $rows[] = $en;
            }
        }
        // Overdue first, then due today, then upcoming (soonest first); collected ones last
        usort($rows, function ($x, $y) {
            return [$x['rank'], $x['name'], $x['round']] <=> [$y['rank'], $y['name'], $y['round']];
        });
        $prev = '';
        foreach ($rows as $i => $row) {
            if (!$row['done'] && $row['group'] !== $prev) {
                $rows[$i]['group_head'] = $row['group'];
                $prev = $row['group'];
            }
        }
    }
    $pc_totals = [
        'gross' => number_format($tot['gross'], 2),
        'kuryente' => number_format($tot['kuryente'], 2),
        'tapal' => number_format($tot['tapal'], 2),
        'done' => $tot['done'],
        'count' => count($mine),
        'todo' => count($mine) - $tot['done'],
    ];

    $ui->assign('pc_me', $me ? $me['name'] : '');
    $ui->assign('pc_rows', $rows);
    $ui->assign('pc_totals', $pc_totals);
    $ui->assign('pc_month', $month);
    $ui->assign('pc_month_label', pp_month_label($month));
    $ui->assign('pc_base', $base);
    $ui->assign('pc_csrf', pc_csrf());
    $ui->assign('pc_flash', $flash);
    $ui->addTemplateDir(__DIR__ . '/ui/');
    $ui->display('partner_collector.tpl');
}
