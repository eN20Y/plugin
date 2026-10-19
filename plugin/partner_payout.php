<?php
/**
 * Partner Payout plugin for PHPNuxBill
 * Base = Gross - Kuryente - Tapal; Share = Base x Share%; Net = Base - Share
 * Install: copy this file to system/plugin/ and ui/partner_payout.tpl to system/plugin/ui/
 */
register_menu("Partner Payout", true, "partner_payout", 'REPORTS');

/** How many times a partner can be collected in one month (1st collection + "collect again"). Change this number if needed. */
if (!defined('PP_MAX_COLLECTIONS')) {
    define('PP_MAX_COLLECTIONS', 2);
}

/** Base = Gross - Kuryente - Tapal; Net = Base - (Base x Share%) */
function pp_net($gross, $kur, $tap, $share)
{
    $base = $gross - $kur - $tap;
    return round($base - ($base * $share / 100), 2);
}

/** "1st", "2nd", "3rd", "4th" ... */
function pp_ordinal($n)
{
    $n = (int) $n;
    $s = ['th', 'st', 'nd', 'rd'];
    $v = $n % 100;
    return $n . ($s[($v - 20) % 10] ?? $s[$v] ?? $s[0]);
}

function pp_install()
{
    $db = ORM::get_db();
    $db->exec("CREATE TABLE IF NOT EXISTS tbl_pp_partners (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(191) NOT NULL,
        address VARCHAR(191) NOT NULL DEFAULT '',
        schedule INT NOT NULL DEFAULT 15,
        share DECIMAL(5,2) NOT NULL DEFAULT 30
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $db->exec("CREATE TABLE IF NOT EXISTS tbl_pp_payouts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        period DATE NOT NULL,
        partner_id INT NOT NULL,
        share DECIMAL(5,2) NOT NULL DEFAULT 30,
        gross DECIMAL(12,2) NOT NULL DEFAULT 0,
        kuryente DECIMAL(12,2) NOT NULL DEFAULT 0,
        tapal DECIMAL(12,2) NOT NULL DEFAULT 0,
        net DECIMAL(12,2) NOT NULL DEFAULT 0,
        UNIQUE KEY uq_period_partner (period, partner_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $db->exec("CREATE TABLE IF NOT EXISTS tbl_pp_collectors (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(191) NOT NULL,
        username VARCHAR(100) NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        active TINYINT(1) NOT NULL DEFAULT 1,
        fails INT NOT NULL DEFAULT 0,
        locked_until DATETIME NULL DEFAULT NULL,
        UNIQUE KEY uq_collector_username (username)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    // Collector assignment is per month: ym = 'Y-m'. A new month simply has no rows, so it starts blank.
    $db->exec("CREATE TABLE IF NOT EXISTS tbl_pp_assign (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ym CHAR(7) NOT NULL,
        partner_id INT NOT NULL,
        collector_id INT NOT NULL,
        UNIQUE KEY uq_ym_partner (ym, partner_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    // Columns added to the existing tables (existing data is kept)
    pp_add_column('tbl_pp_partners', 'collector_id', 'INT NULL DEFAULT NULL');
    pp_add_column('tbl_pp_payouts', 'collected_by', 'VARCHAR(191) NULL DEFAULT NULL');
    pp_add_column('tbl_pp_payouts', 'collected_by_id', 'INT NULL DEFAULT NULL');
    pp_add_column('tbl_pp_payouts', 'collected_at', 'DATETIME NULL DEFAULT NULL');
    // Collection number inside the month: 1 = first collection, 2 = "collect again", ...
    pp_add_column('tbl_pp_payouts', 'collect_no', 'TINYINT NOT NULL DEFAULT 1');
    $oldKey = $db->query("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_pp_payouts' AND INDEX_NAME = 'uq_period_partner'")->fetchColumn();
    if ((int) $oldKey > 0) {
        $db->exec('ALTER TABLE tbl_pp_payouts DROP INDEX uq_period_partner, ADD UNIQUE KEY uq_period_partner_no (period, partner_id, collect_no)');
    }
    // One-time: assignments made before monthly assignment existed become this month's assignments
    // (so collectors are not locked out today); the old column is then cleared so this never runs twice.
    $old = $db->query('SELECT COUNT(*) FROM tbl_pp_partners WHERE collector_id IS NOT NULL')->fetchColumn();
    if ((int) $old > 0) {
        $db->prepare('INSERT IGNORE INTO tbl_pp_assign (ym, partner_id, collector_id) SELECT ?, id, collector_id FROM tbl_pp_partners WHERE collector_id IS NOT NULL')->execute([date('Y-m')]);
        $db->exec('UPDATE tbl_pp_partners SET collector_id = NULL WHERE collector_id IS NOT NULL');
    }
}

/** Add a column to a table only if it does not exist yet. */
function pp_add_column($table, $column, $ddl)
{
    $db = ORM::get_db();
    $st = $db->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    $st->execute([$table, $column]);
    if ((int) $st->fetchColumn() === 0) {
        $db->exec('ALTER TABLE `' . $table . '` ADD COLUMN `' . $column . '` ' . $ddl);
    }
}

function pp_num($v)
{
    $v = str_replace([',', '%'], '', trim((string) $v));
    return is_numeric($v) ? (float) $v : 0.0;
}

/** Collector assignments of month $ym: [partner_id => collector_id]. A month nobody assigned yet is empty. */
function pp_assignments($ym)
{
    $out = [];
    foreach (ORM::for_table('tbl_pp_assign')->where('ym', $ym)->find_many() as $r) {
        $out[(int) $r['partner_id']] = (int) $r['collector_id'];
    }
    return $out;
}

/** Assign a partner to a collector for month $ym only ($collectorId 0 = unassign). */
function pp_assign($ym, $partnerId, $collectorId)
{
    $db = ORM::get_db();
    if ((int) $collectorId > 0) {
        $db->prepare('INSERT INTO tbl_pp_assign (ym, partner_id, collector_id) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE collector_id = VALUES(collector_id)')
            ->execute([$ym, (int) $partnerId, (int) $collectorId]);
    } else {
        $db->prepare('DELETE FROM tbl_pp_assign WHERE ym = ? AND partner_id = ?')->execute([$ym, (int) $partnerId]);
    }
}

/** The partners assigned to a collector in month $ym, by name. */
function pp_collector_partners($collectorId, $ym)
{
    $ids = [];
    foreach (pp_assignments($ym) as $pid => $cid) {
        if ($cid === (int) $collectorId) {
            $ids[] = $pid;
        }
    }
    if (!$ids) {
        return [];
    }
    return ORM::for_table('tbl_pp_partners')->where_in('id', $ids)->order_by_asc('name')->find_many();
}

/** A valid 'Y-m' month, else the month of $fallback (a date), else this month. */
function pp_month($v, $fallback = '')
{
    $v = trim((string) $v);
    if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $v)) {
        return $v;
    }
    $f = substr(trim((string) $fallback), 0, 7);
    return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $f) ? $f : date('Y-m');
}

function pp_month_label($ym)
{
    return date('F Y', strtotime($ym . '-01'));
}

/** Date a NEW record of month $ym is stored under: today if today is in that month, else the nearest day of it. */
function pp_month_date($ym)
{
    $first = $ym . '-01';
    $last = date('Y-m-t', strtotime($first));
    $d = date('Y-m-d');
    return $d < $first ? $first : ($d > $last ? $last : $d);
}

/** The partner's harvest day (15 or 30) inside month $ym; 30 becomes the last day of a short month. */
function pp_due_date($day, $ym)
{
    $dim = (int) date('t', strtotime($ym . '-01'));
    return $ym . '-' . str_pad((string) min((int) $day, $dim), 2, '0', STR_PAD_LEFT);
}

/**
 * One payout record per partner for month $ym: [partner_id => ORM row].
 * The 1st collection of the month; the collector and the admin both read and edit this record (extra collections: pp_month_extra).
 * If a partner has several rows in the month (older date-based entries), the newest collected one is used.
 */
function pp_month_records($ym, $no = 1)
{
    $first = $ym . '-01';
    $last = date('Y-m-t', strtotime($first));
    $out = [];
    $rows = ORM::for_table('tbl_pp_payouts')->where_gte('period', $first)->where_lte('period', $last)
        ->where('collect_no', (int) $no)
        ->order_by_asc('period')->order_by_asc('id')->find_many();
    foreach ($rows as $r) {
        $id = $r['partner_id'];
        if (isset($out[$id]) && (float) $out[$id]['gross'] > 0 && (float) $r['gross'] <= 0) {
            continue; // an empty row never replaces a collected one
        }
        $out[$id] = $r;
    }
    return $out;
}

/**
 * The "collect again" records of month $ym (2nd collection and later): [partner_id => [collect_no => ORM row]].
 * Rows that are still empty (no gross) are ignored.
 */
function pp_month_extra($ym)
{
    $first = $ym . '-01';
    $last = date('Y-m-t', strtotime($first));
    $out = [];
    $rows = ORM::for_table('tbl_pp_payouts')->where_gte('period', $first)->where_lte('period', $last)
        ->where_gt('collect_no', 1)->order_by_asc('collect_no')->order_by_asc('id')->find_many();
    foreach ($rows as $r) {
        if ((float) $r['gross'] <= 0) {
            continue;
        }
        $out[$r['partner_id']][(int) $r['collect_no']] = $r;
    }
    return $out;
}

/**
 * Where a partner stands in month $ym compared with today:
 * done / overdue (harvest day passed) / today / upcoming. 'rank' sorts overdue first, then today, then soonest upcoming.
 */
function pp_month_status($schedule, $ym, $isDone)
{
    if ($isDone) {
        return ['state' => 'done', 'group' => '', 'due_label' => '', 'days' => 0, 'rank' => 5000];
    }
    $due = pp_due_date($schedule, $ym);
    $label = date('M j', strtotime($due));
    $days = (int) round((strtotime(date('Y-m-d')) - strtotime($due)) / 86400); // > 0 = days overdue, < 0 = days left
    if ($days > 0) {
        return ['state' => 'overdue', 'group' => 'Overdue', 'due_label' => $label, 'days' => $days, 'rank' => 1000 - $days];
    }
    if ($days === 0) {
        return ['state' => 'today', 'group' => 'Due today', 'due_label' => $label, 'days' => 0, 'rank' => 2000];
    }
    return ['state' => 'upcoming', 'group' => 'Upcoming', 'due_label' => $label, 'days' => -$days, 'rank' => 2000 - $days];
}

/** Convert an Excel column reference like "AB12" to a 0-based column index. */
function pp_col_index($ref)
{
    preg_match('/^[A-Z]+/i', $ref, $m);
    $n = 0;
    foreach (str_split(strtoupper($m[0] ?? 'A')) as $ch) {
        $n = $n * 26 + (ord($ch) - 64);
    }
    return $n - 1;
}

/** Read the first sheet of an .xlsx file into an array of rows (no external libraries needed). */
function pp_read_xlsx($path)
{
    if (!class_exists('ZipArchive')) {
        return null;
    }
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        return null;
    }
    $ns = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    $shared = [];
    $xml = $zip->getFromName('xl/sharedStrings.xml');
    if ($xml !== false && ($sx = simplexml_load_string($xml))) {
        $sx->registerXPathNamespace('m', $ns);
        foreach ($sx->xpath('//m:si') as $si) {
            $t = '';
            foreach ($si->xpath('.//m:t') as $node) {
                $t .= (string) $node;
            }
            $shared[] = $t;
        }
    }

    // Find the first worksheet (usually xl/worksheets/sheet1.xml)
    $sheetPath = 'xl/worksheets/sheet1.xml';
    $wb = $zip->getFromName('xl/workbook.xml');
    $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');
    if ($wb !== false && $rels !== false) {
        $w = simplexml_load_string($wb);
        $r = simplexml_load_string($rels);
        if ($w && $r) {
            $w->registerXPathNamespace('m', $ns);
            $first = $w->xpath('//m:sheets/m:sheet[1]');
            if ($first) {
                $rid = (string) $first[0]->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
                foreach ($r->Relationship as $rel) {
                    if ((string) $rel['Id'] === $rid) {
                        $target = ltrim((string) $rel['Target'], '/');
                        $sheetPath = strpos($target, 'xl/') === 0 ? $target : 'xl/' . $target;
                        break;
                    }
                }
            }
        }
    }
    $sheet = $zip->getFromName($sheetPath);
    $zip->close();
    if ($sheet === false || !($sx = simplexml_load_string($sheet))) {
        return null;
    }
    $sx->registerXPathNamespace('m', $ns);

    $rows = [];
    foreach ($sx->xpath('//m:sheetData/m:row') as $row) {
        $line = [];
        $max = -1;
        foreach ($row->c as $c) {
            $idx = pp_col_index((string) $c['r']);
            $type = (string) $c['t'];
            if ($type === 'inlineStr') {
                $val = '';
                foreach ($c->xpath('.//m:t') as $node) {
                    $val .= (string) $node;
                }
            } elseif ($type === 's') {
                $val = $shared[(int) $c->v] ?? '';
            } else {
                $val = (string) $c->v;
            }
            $line[$idx] = trim($val);
            $max = max($max, $idx);
        }
        for ($i = 0; $i <= $max; $i++) {
            if (!isset($line[$i])) {
                $line[$i] = '';
            }
        }
        ksort($line);
        $rows[] = array_values($line);
    }
    return $rows;
}

/** Read a .csv file into an array of rows. */
function pp_read_csv($path)
{
    $rows = [];
    if (($h = fopen($path, 'r')) === false) {
        return null;
    }
    while (($r = fgetcsv($h, 0, ',', '"', '\\')) !== false) {
        if (count($rows) === 0 && isset($r[0])) {
            $r[0] = preg_replace('/^\xEF\xBB\xBF/', '', $r[0]); // strip UTF-8 BOM
        }
        $rows[] = array_map('trim', $r);
    }
    fclose($h);
    return $rows;
}

/**
 * Import partners (and optionally Gross/Kuryente/Tapal for a period) from an uploaded .xlsx or .csv.
 * Expected headers (any order): PARTNERS, ADRESS/ADDRESS, SCHEDULE, SHARE, GROSS, KURYENTE, TAPAL
 * Returns [stats array, null] on success or [null, error message].
 */
function pp_import_file($tmp, $ext, $period, $withAmounts)
{
    $rows = $ext === 'xlsx' ? pp_read_xlsx($tmp) : pp_read_csv($tmp);
    if (!$rows) {
        return [null, 'Could not read the file. Make sure it is a valid .xlsx or .csv.'];
    }

    $alias = [
        'name' => ['PARTNERS', 'PARTNER', 'NAME', 'PARTNER NAME'],
        'address' => ['ADRESS', 'ADDRESS'],
        'schedule' => ['SCHEDULE'],
        'share' => ['SHARE', 'SHARE %', 'SHARE%'],
        'gross' => ['GROSS'],
        'kuryente' => ['KURYENTE'],
        'tapal' => ['TAPAL'],
    ];
    // Find the header row (within the first 10 rows) and map columns
    $map = [];
    $start = -1;
    foreach (array_slice($rows, 0, 10, true) as $i => $r) {
        $found = [];
        foreach ($r as $ci => $cell) {
            $key = strtoupper(trim($cell));
            foreach ($alias as $field => $names) {
                if (in_array($key, $names, true) && !isset($found[$field])) {
                    $found[$field] = $ci;
                }
            }
        }
        if (isset($found['name'])) {
            $map = $found;
            $start = $i;
            break;
        }
    }
    if ($start < 0) {
        return [null, 'Header row not found. The sheet needs a PARTNERS column (plus ADRESS, SCHEDULE, SHARE, ...).'];
    }

    $existing = [];
    foreach (ORM::for_table('tbl_pp_partners')->find_many() as $p) {
        $existing[strtolower($p['name']) . '|' . strtolower($p['address'])] = $p;
    }

    $get = function ($r, $f) use ($map) {
        return isset($map[$f]) ? trim((string) ($r[$map[$f]] ?? '')) : '';
    };

    $st = ['added' => 0, 'updated' => 0, 'skipped' => 0, 'amounts' => 0];
    $db = ORM::get_db();
    $db->beginTransaction();
    try {
        foreach (array_slice($rows, $start + 1) as $r) {
            $name = $get($r, 'name');
            if ($name === '') {
                $st['skipped']++;
                continue;
            }
            $address = isset($map['address']) ? $get($r, 'address') : '';
            $key = strtolower($name) . '|' . strtolower($address);

            $p = $existing[$key] ?? null;
            $isNew = !$p;
            if ($isNew) {
                $p = ORM::for_table('tbl_pp_partners')->create();
                $p->name = $name;
                $p->address = $address;
                $p->schedule = 15;
                $p->share = 30;
            }
            if (isset($map['schedule']) && $get($r, 'schedule') !== '') {
                $p->schedule = (int) pp_num($get($r, 'schedule')) === 30 ? 30 : 15;
            }
            if (isset($map['share']) && $get($r, 'share') !== '') {
                $sh = pp_num($get($r, 'share'));
                if ($sh > 0 && $sh <= 1) {
                    $sh *= 100; // Excel percent format stores 30% as 0.3
                }
                $p->share = min(100, max(0, $sh));
            }
            $p->save();
            $existing[$key] = $p;
            $isNew ? $st['added']++ : $st['updated']++;

            if ($withAmounts) {
                $gross = pp_num($get($r, 'gross'));
                $kur = pp_num($get($r, 'kuryente'));
                $tap = pp_num($get($r, 'tapal'));
                if ($gross == 0 && $kur == 0 && $tap == 0) {
                    continue;
                }
                $row = ORM::for_table('tbl_pp_payouts')
                    ->where('period', $period)->where('partner_id', $p['id'])->where('collect_no', 1)->find_one();
                if (!$row) {
                    $row = ORM::for_table('tbl_pp_payouts')->create();
                    $row->period = $period;
                    $row->partner_id = $p['id'];
                }
                $row->share = $p['share'];
                $row->gross = $gross;
                $row->kuryente = $kur;
                $row->tapal = $tap;
                $row->net = pp_net($gross, $kur, $tap, $p['share']);
                $row->save();
                $st['amounts']++;
            }
        }
        $db->commit();
    } catch (Exception $e) {
        $db->rollBack();
        return [null, 'Import failed: ' . $e->getMessage()];
    }
    return [$st, null];
}

function pp_qs($period, $view, $from, $to)
{
    $s = '&period=' . $period . '&view=' . $view . '&from=' . $from . '&to=' . $to;
    $s .= '&tab=' . (($_REQUEST['tab'] ?? '') === 'done' ? 'done' : 'todo');
    $q = trim((string) ($_REQUEST['q'] ?? ''));
    if ($q !== '') {
        $s .= '&q=' . urlencode($q);
    }
    return $s;
}

/** Case-insensitive search on name + address. */
function pp_search($items, $q)
{
    if ($q === '') {
        return $items;
    }
    $out = [];
    foreach ($items as $a) {
        if (stripos(($a['name'] ?? '') . ' ' . ($a['address'] ?? ''), $q) !== false) {
            $out[] = $a;
        }
    }
    return $out;
}

/** Build a paginator array in the same shape PHPNuxBill's templates expect ($paginator.url/page/count/prev/next/pages). */
function pp_paginate($total, $page, $perPage, $baseUrl)
{
    $count = max(1, (int) ceil($total / $perPage));
    $page = min(max(1, (int) $page), $count);
    $pages = [];
    $last = 0;
    for ($i = 1; $i <= $count; $i++) {
        if ($i == 1 || $i == $count || abs($i - $page) <= 2) {
            if ($last && $i - $last > 1) {
                $pages[] = '...';
            }
            $pages[] = $i;
            $last = $i;
        }
    }
    return [
        'url' => $baseUrl,
        'page' => $page,
        'count' => $count,
        'prev' => $page > 1 ? $page - 1 : '',
        'next' => $page < $count ? $page + 1 : $count,
        'pages' => $pages,
    ];
}

function partner_payout()
{
    global $ui, $routes;
    _admin();
    $admin = Admin::_info();
    if (!in_array($admin['user_type'], ['SuperAdmin', 'Admin'])) {
        r2(U . 'dashboard', 'e', Lang::T('You do not have permission to access this page'));
    }
    pp_install();

    $action = $routes[2] ?? 'index';
    $period = $_REQUEST['period'] ?? date('Y-m-d');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $period)) {
        $period = date('Y-m-d');
    }
    // Harvest works per month. A picked month wins; otherwise the month of the date in the link.
    $month = pp_month($_REQUEST['month'] ?? '', $period);
    if (pp_month($_REQUEST['month'] ?? '', '') === ($_REQUEST['month'] ?? null)) {
        $period = $month . '-01';
    }
    $tab = ($_REQUEST['tab'] ?? '') === 'done' ? 'done' : 'todo';
    $view = in_array($_REQUEST['view'] ?? '', ['harvest', 'harvested', 'list', 'collectors']) ? $_REQUEST['view'] : 'harvest';
    $from = $_REQUEST['from'] ?? date('Y-m-01');
    $to = $_REQUEST['to'] ?? date('Y-m-d');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
        $from = date('Y-m-01');
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
        $to = date('Y-m-d');
    }
    if ($from > $to) {
        list($from, $to) = [$to, $from];
    }
    $back = U . 'plugin/partner_payout' . pp_qs($period, $view, $from, $to);
    $backList = U . 'plugin/partner_payout' . pp_qs($period, 'list', $from, $to);

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && class_exists('Csrf')) {
        if (!Csrf::check($_POST['csrf_token'] ?? '')) {
            r2($back, 'e', Lang::T('Invalid or Expired CSRF Token'));
        }
    }

    switch ($action) {
        case 'add':
            $name = trim($_POST['name'] ?? '');
            if ($name === '') {
                r2($back, 'e', 'Name is required');
            }
            $p = ORM::for_table('tbl_pp_partners')->create();
            $p->name = $name;
            $p->address = trim($_POST['address'] ?? '');
            $p->schedule = (int) ($_POST['schedule'] ?? 15) === 30 ? 30 : 15;
            $p->share = pp_num($_POST['share'] ?? 30);
            $cid = (int) ($_POST['collector_id'] ?? 0);
            if ($cid > 0 && !ORM::for_table('tbl_pp_collectors')->find_one($cid)) {
                $cid = 0;
            }
            $p->save();
            pp_assign($month, $p->id(), $cid);
            r2($back, 's', 'Partner added');
            break;

        case 'assign':
            $p = ORM::for_table('tbl_pp_partners')->find_one((int) ($_POST['id'] ?? 0));
            $cid = (int) ($_POST['collector_id'] ?? 0);
            if (!$p) {
                r2($backList, 'e', 'Partner not found');
            }
            if ($cid > 0 && !ORM::for_table('tbl_pp_collectors')->find_one($cid)) {
                r2($backList, 'e', 'Collector not found');
            }
            pp_assign($month, $p['id'], $cid);
            r2($backList, 's', 'Collector updated for ' . pp_month_label($month));
            break;

        case 'collector_add':
            $backC = U . 'plugin/partner_payout' . pp_qs($period, 'collectors', $from, $to);
            $cname = trim($_POST['name'] ?? '');
            $cuser = trim($_POST['username'] ?? '');
            $cpass = (string) ($_POST['password'] ?? '');
            if ($cname === '' || $cuser === '') {
                r2($backC, 'e', 'Name and username are required');
            }
            if (!preg_match('/^[A-Za-z0-9._-]{3,50}$/', $cuser)) {
                r2($backC, 'e', 'Username: 3-50 letters, numbers, dot, dash or underscore');
            }
            if (strlen($cpass) < 6) {
                r2($backC, 'e', 'Password must be at least 6 characters');
            }
            if (ORM::for_table('tbl_pp_collectors')->where('username', $cuser)->find_one()) {
                r2($backC, 'e', 'That username is already used');
            }
            $c = ORM::for_table('tbl_pp_collectors')->create();
            $c->name = $cname;
            $c->username = $cuser;
            $c->password_hash = password_hash($cpass, PASSWORD_DEFAULT);
            $c->active = 1;
            $c->save();
            r2($backC, 's', 'Collector added');
            break;

        case 'collector_pass':
            $backC = U . 'plugin/partner_payout' . pp_qs($period, 'collectors', $from, $to);
            $c = ORM::for_table('tbl_pp_collectors')->find_one((int) ($_POST['id'] ?? 0));
            $cpass = (string) ($_POST['password'] ?? '');
            if (!$c) {
                r2($backC, 'e', 'Collector not found');
            }
            if (strlen($cpass) < 6) {
                r2($backC, 'e', 'Password must be at least 6 characters');
            }
            $c->password_hash = password_hash($cpass, PASSWORD_DEFAULT);
            $c->fails = 0;
            $c->locked_until = null;
            $c->save();
            r2($backC, 's', 'Password changed for ' . $c['name']);
            break;

        case 'collector_toggle':
            $backC = U . 'plugin/partner_payout' . pp_qs($period, 'collectors', $from, $to);
            $c = ORM::for_table('tbl_pp_collectors')->find_one((int) ($_POST['id'] ?? 0));
            if ($c) {
                $c->active = $c['active'] ? 0 : 1;
                $c->save();
            }
            r2($backC, 's', 'Collector access updated');
            break;

        case 'collector_delete':
            $backC = U . 'plugin/partner_payout' . pp_qs($period, 'collectors', $from, $to);
            $c = ORM::for_table('tbl_pp_collectors')->find_one((int) ($_POST['id'] ?? 0));
            if ($c) {
                // Partners become unassigned. Saved payouts keep the collector name that was recorded.
                ORM::get_db()->prepare('DELETE FROM tbl_pp_assign WHERE collector_id = ?')->execute([$c['id']]);
                $c->delete();
            }
            r2($backC, 's', 'Collector deleted');
            break;

        case 'delete':
            $p = ORM::for_table('tbl_pp_partners')->find_one((int) ($_POST['id'] ?? 0));
            if ($p) {
                ORM::for_table('tbl_pp_payouts')->where('partner_id', $p['id'])->delete_many();
                ORM::for_table('tbl_pp_assign')->where('partner_id', $p['id'])->delete_many();
                $p->delete();
            }
            r2($back, 's', 'Partner deleted');
            break;

        case 'seed':
            if (ORM::for_table('tbl_pp_partners')->count() == 0) {
                // Taken from the spreadsheet photo: [name, schedule, share]
                $rows = [
                    ['CIPRIANO-BOSOL', 15, 30], ['YUARATA', 15, 30], ['HERMOGENES', 15, 30],
                    ['LANGGING', 30, 30], ['CARENIO2', 15, 30], ['ENRIQUEZ', 15, 30],
                    ['LOLA BEBE', 15, 30], ['SHIELA ALIVIO', 30, 30], ['CARTALLA VENDO AMASION', 15, 30],
                    ['BANGKAYA', 15, 30], ['CARENIO, NANAY', 15, 30], ['CAMAYMAYAN', 15, 30],
                    ['BON2', 15, 30], ['PUAS INDA- MENDOG', 15, 30], ['ROSIE', 15, 40],
                    ['BINGBONG', 15, 30], ['ANTONIO', 15, 30], ['SALAZAR PRK AVOCADO', 15, 30],
                    ['TABAY', 15, 30], ['FURNITURE ENRIQUEZ', 30, 30],
                ];
                foreach ($rows as $r) {
                    $p = ORM::for_table('tbl_pp_partners')->create();
                    $p->name = $r[0];
                    $p->address = 'AMAS';
                    $p->schedule = $r[1];
                    $p->share = $r[2];
                    $p->save();
                }
            }
            r2($backList, 's', 'Sample partners imported');
            break;

        case 'import':
            $f = $_FILES['sheet'] ?? null;
            if (!$f || $f['error'] !== UPLOAD_ERR_OK) {
                r2($backList, 'e', 'Please choose a file to import');
            }
            $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
            if ($ext === 'xls') {
                r2($backList, 'e', 'Old .xls files are not supported. In Excel use Save As > .xlsx (or .csv).');
            }
            if (!in_array($ext, ['xlsx', 'csv'])) {
                r2($backList, 'e', 'Only .xlsx or .csv files are supported');
            }
            if ($f['size'] > 5 * 1024 * 1024) {
                r2($backList, 'e', 'File is too large (max 5 MB)');
            }
            list($st, $err) = pp_import_file($f['tmp_name'], $ext, $period, false);
            if ($err) {
                r2($backList, 'e', $err);
            }
            $msg = "Import done: {$st['added']} added, {$st['updated']} updated, {$st['skipped']} skipped";
            r2($backList, 's', $msg);
            break;

        case 'save':
            $partners = ORM::for_table('tbl_pp_partners')->find_many();
            $recs = pp_month_records($month);
            foreach ($partners as $p) {
                $id = $p['id'];
                if (!isset($_POST['gross'][$id])) {
                    continue; // partners that were not on the page are not submitted - leave them untouched
                }
                $gross = pp_num($_POST['gross'][$id] ?? 0);
                $kur = pp_num($_POST['kuryente'][$id] ?? 0);
                $tap = pp_num($_POST['tapal'][$id] ?? 0);
                $row = $recs[$id] ?? null; // the partner's record for this month (shared with the collector)
                if (!$row && $gross == 0 && $kur == 0 && $tap == 0) {
                    continue;
                }
                if (!$row) {
                    $row = ORM::for_table('tbl_pp_payouts')->create();
                    $row->period = pp_month_date($month);
                    $row->partner_id = $id;
                } elseif (abs((float) $row['gross'] - $gross) > 0.004) {
                    // Admin changed the collected amount, so it no longer matches what the collector entered
                    $row->collected_by = null;
                    $row->collected_by_id = null;
                    $row->collected_at = null;
                }
                $row->share = $p['share'];
                $row->gross = $gross;
                $row->kuryente = $kur;
                $row->tapal = $tap;
                $row->net = pp_net($gross, $kur, $tap, $p['share']);
                $row->save();
            }
            // "Collect again" rows (2nd collection and later). Only partners whose 1st collection is saved can have them.
            $recs = pp_month_records($month); // re-read, so a 1st collection saved just above counts
            $xg = is_array($_POST['xgross'] ?? null) ? $_POST['xgross'] : [];
            $xk = is_array($_POST['xkuryente'] ?? null) ? $_POST['xkuryente'] : [];
            $xt = is_array($_POST['xtapal'] ?? null) ? $_POST['xtapal'] : [];
            foreach ($partners as $p) {
                $id = $p['id'];
                if (!isset($xg[$id]) || !is_array($xg[$id])) {
                    continue;
                }
                $first = $recs[$id] ?? null;
                if (!$first || (float) $first['gross'] <= 0) {
                    continue;
                }
                foreach ($xg[$id] as $no => $rawGross) {
                    $no = (int) $no;
                    if ($no < 2 || $no > PP_MAX_COLLECTIONS) {
                        continue;
                    }
                    $gross = pp_num($rawGross);
                    $kur = pp_num($xk[$id][$no] ?? 0);
                    $tap = pp_num($xt[$id][$no] ?? 0);
                    $xrecs = pp_month_records($month, $no);
                    $row = $xrecs[$id] ?? null;
                    if ($gross == 0 && $kur == 0 && $tap == 0) {
                        if ($row) {
                            $row->delete(); // cleared by the admin = this extra collection is removed
                        }
                        continue;
                    }
                    if (!$row) {
                        $row = ORM::for_table('tbl_pp_payouts')->create();
                        $row->period = pp_month_date($month);
                        $row->partner_id = $id;
                        $row->collect_no = $no;
                    } elseif (abs((float) $row['gross'] - $gross) > 0.004) {
                        $row->collected_by = null;
                        $row->collected_by_id = null;
                        $row->collected_at = null;
                    }
                    $row->share = $p['share'];
                    $row->gross = $gross;
                    $row->kuryente = $kur;
                    $row->tapal = $tap;
                    $row->net = pp_net($gross, $kur, $tap, $p['share']);
                    $row->save();
                }
            }
            r2($back, 's', 'Payout saved for ' . pp_month_label($month));
            break;
    }

    $partners = ORM::for_table('tbl_pp_partners')->order_by_asc('id')->find_many();
    $collectors = [];
    foreach (ORM::for_table('tbl_pp_collectors')->order_by_asc('name')->find_many() as $c) {
        $ca = $c->as_array();
        unset($ca['password_hash']);
        $ca['partner_count'] = 0;
        $collectors[] = $ca;
    }
    $assign = pp_assignments($month); // this month only; a new month starts blank
    foreach ($assign as $apid => $acid) {
        foreach ($collectors as $ci => $ca) {
            if ((int) $ca['id'] === $acid) {
                $collectors[$ci]['partner_count']++;
            }
        }
    }
    $saved = [];
    foreach (pp_month_records($month) as $spid => $srow) {
        $saved[$spid] = $srow->as_array();
    }
    $mt = ['gross' => 0.0, 'kuryente' => 0.0, 'tapal' => 0.0, 'done' => 0, 'count' => count($partners)];
    $extras = pp_month_extra($month); // "collect again" records of this month
    $list = [];
    foreach ($partners as $p) {
        $a = $p->as_array();
        $a['collector_id'] = $assign[(int) $p['id']] ?? 0;
        $a['gross'] = $saved[$p['id']]['gross'] ?? '';
        $a['kuryente'] = $saved[$p['id']]['kuryente'] ?? '';
        $a['tapal'] = $saved[$p['id']]['tapal'] ?? '';
        $a['collected_by'] = $saved[$p['id']]['collected_by'] ?? '';
        $ts = $saved[$p['id']]['collected_at'] ?? '';
        $a['collected_at'] = $ts ? date('M j, g:i A', strtotime($ts)) : '';
        $g = (float) ($saved[$p['id']]['gross'] ?? 0);
        $mt['gross'] += $g;
        $mt['kuryente'] += (float) ($saved[$p['id']]['kuryente'] ?? 0);
        $mt['tapal'] += (float) ($saved[$p['id']]['tapal'] ?? 0);
        $a['done'] = $g > 0;
        if ($a['done']) {
            $mt['done']++;
        }
        // 2nd collection and later: shown under the partner and added to the month totals
        $a['extras'] = [];
        $maxNo = 1;
        foreach ($extras[$p['id']] ?? [] as $xno => $xr) {
            $mt['gross'] += (float) $xr['gross'];
            $mt['kuryente'] += (float) $xr['kuryente'];
            $mt['tapal'] += (float) $xr['tapal'];
            $xts = $xr['collected_at'] ?? '';
            $a['extras'][] = [
                'no' => $xno,
                'label' => pp_ordinal($xno),
                'gross' => $xr['gross'],
                'kuryente' => $xr['kuryente'],
                'tapal' => $xr['tapal'],
                'collected_by' => $xr['collected_by'] ?? '',
                'collected_at' => $xts ? date('M j, g:i A', strtotime($xts)) : '',
            ];
            $maxNo = max($maxNo, $xno);
        }
        $a['next_no'] = $maxNo + 1;
        $a['can_again'] = $a['done'] && $a['next_no'] <= PP_MAX_COLLECTIONS;
        $a['next_label'] = pp_ordinal($a['next_no']);
        $st = pp_month_status($p['schedule'], $month, $a['done']);
        $a['state'] = $st['state'];
        $a['group'] = $st['group'];
        $a['due_label'] = $st['due_label'];
        $a['days'] = $st['days'];
        $a['rank'] = $st['rank'];
        $list[] = $a;
    }

    // Harvested summary within the selected date range
    $sumTotal = ['harvests' => 0, 'gross' => 0, 'share' => 0, 'kuryente' => 0, 'tapal' => 0, 'net' => 0];
    $byPartner = [];
    $byPeriod = [];
    if ($view === 'harvested') {
        $pmap = [];
        foreach ($partners as $p) {
            $pmap[$p['id']] = $p;
        }
        $rows = ORM::for_table('tbl_pp_payouts')
            ->where_gte('period', $from)->where_lte('period', $to)
            ->order_by_asc('period')->find_many();
        foreach ($rows as $r) {
            if (!isset($pmap[$r['partner_id']])) {
                continue;
            }
            $g = (float) $r['gross'];
            $k = (float) $r['kuryente'];
            $t = (float) $r['tapal'];
            $sh = round(($g - $k - $t) * (float) $r['share'] / 100, 2);
            $n = (float) $r['net'];
            $h = $g > 0 ? 1 : 0;
            $pid = $r['partner_id'];
            if (!isset($byPartner[$pid])) {
                $byPartner[$pid] = ['name' => $pmap[$pid]['name'], 'address' => $pmap[$pid]['address'],
                    'harvests' => 0, 'gross' => 0, 'share' => 0, 'kuryente' => 0, 'tapal' => 0, 'net' => 0, 'collectors' => []];
            }
            $d = $r['period'];
            if (!isset($byPeriod[$d])) {
                $byPeriod[$d] = ['period' => $d, 'partners' => 0, 'gross' => 0, 'share' => 0, 'kuryente' => 0, 'tapal' => 0, 'net' => 0, 'collectors' => []];
            }
            if ($h && !empty($r['collected_by'])) {
                $byPartner[$pid]['collectors'][$r['collected_by']] = true;
                $byPeriod[$d]['collectors'][$r['collected_by']] = true;
            }
            $byPeriod[$d]['partners'] += $h;
            $byPartner[$pid]['harvests'] += $h;
            $sumTotal['harvests'] += $h;
            foreach (['gross' => $g, 'share' => $sh, 'kuryente' => $k, 'tapal' => $t, 'net' => $n] as $f => $v) {
                $byPartner[$pid][$f] += $v;
                $byPeriod[$d][$f] += $v;
                $sumTotal[$f] += $v;
            }
        }
        usort($byPartner, function ($a, $b) {
            return $b['gross'] <=> $a['gross'];
        });
        $byPeriod = array_values($byPeriod);
        foreach ($byPartner as $k => $row) {
            $byPartner[$k]['collectors'] = implode(', ', array_keys($row['collectors']));
        }
        foreach ($byPeriod as $k => $row) {
            $byPeriod[$k]['collectors'] = implode(', ', array_keys($row['collectors']));
        }

        // Format money in PHP so the template stays simple
        $fmt = function ($row) {
            foreach (['gross', 'share', 'kuryente', 'tapal', 'net'] as $f) {
                $row[$f] = number_format($row[$f], 2);
            }
            return $row;
        };
        $byPartner = array_map($fmt, $byPartner);
        $byPeriod = array_map($fmt, $byPeriod);
        $sumTotal = $fmt($sumTotal);
    }

    if (class_exists('Csrf')) {
        $ui->assign('csrf_token', Csrf::generateAndStoreToken());
    }
    $ui->assign('view', $view);
    $ui->assign('from', $from);
    $ui->assign('to', $to);
    $ui->assign('qs', pp_qs($period, $view, $from, $to));
    $ui->assign('sum', $sumTotal);
    $ui->assign('by_partner', $byPartner);
    $ui->assign('by_period', $byPeriod);
    $ui->assign('_title', 'Partner Payout');
    $ui->assign('_system_menu', 'reports');
    $ui->assign('_admin', $admin);
    $ui->assign('period', $period);
    $showAll = !empty($_REQUEST['all']);
    $hiddenCount = 0;
    if ($view === 'harvest') {
        // Two tabs: Uncollected (overdue first, then due today, then upcoming) and Collected
        $visible = [];
        foreach ($list as $a) {
            if (($tab === 'done') === $a['done']) {
                $visible[] = $a;
            } else {
                $hiddenCount++;
            }
        }
        usort($visible, function ($x, $y) {
            return [$x['rank'], $x['id']] <=> [$y['rank'], $y['id']];
        });
        $list = $visible;
    }
    // Search + pagination (works on the harvest list, the vendo list and the by-partner summary)
    $perPage = 25;
    $q = trim((string) ($_REQUEST['q'] ?? ''));
    $items = pp_search($view === 'harvested' ? $byPartner : $list, $q);
    $found = count($items);
    $baseUrl = U . 'plugin/partner_payout' . pp_qs($period, $view, $from, $to) . ($showAll ? '&all=1' : '') . '&page=';
    $pg = pp_paginate($found, $_REQUEST['page'] ?? 1, $perPage, $baseUrl);
    $offset = ($pg['page'] - 1) * $perPage;
    $items = array_slice($items, $offset, $perPage);
    if ($view === 'harvested') {
        $byPartner = $items;
        $ui->assign('by_partner', $byPartner);
    } else {
        $list = $items;
    }
    $ui->assign('paginator', $pg['count'] > 1 ? $pg : null);
    $ui->assign('offset', $offset);
    $ui->assign('found', $found);
    $ui->assign('q', $q);
    $ui->assign('show_all', $showAll);
    $ui->assign('hidden_count', $hiddenCount);
    $ui->assign('tab', $tab);
    $ui->assign('month', $month);
    $ui->assign('month_label', pp_month_label($month));
    $ui->assign('todo_count', $mt['count'] - $mt['done']);
    $ui->assign('done_count', $mt['done']);
    $ui->assign('month_total', ['gross' => number_format($mt['gross'], 2), 'kuryente' => number_format($mt['kuryente'], 2), 'tapal' => number_format($mt['tapal'], 2)]);
    $ui->assign('partners', $list);
    $ui->assign('collectors', $collectors);
    $ui->addTemplateDir(__DIR__ . '/ui/');
    $ui->display('partner_payout.tpl');
}
