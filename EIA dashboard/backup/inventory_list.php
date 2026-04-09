<?php
session_start();
date_default_timezone_set('Asia/Bangkok');
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// 1. Database Connection & Query Include
require_once 'config.php';
require_once 'query.php';

// 2. Fetch Unique Filter Options
try {
    $bu_options = $pdo->query("SELECT DISTINCT A.NAME FROM ASSET_ASSOCIATION J JOIN ASSET A ON A.ID = J.ASSOCIATED_ASSET_ID WHERE J.ASSET_FIELD_ID = 10003 AND A.NAME IS NOT NULL AND A.NAME != '' ORDER BY A.NAME")->fetchAll(PDO::FETCH_COLUMN);
    $team_options = $pdo->query("SELECT DISTINCT FIELD_10007 FROM ASSET_DATA_5 WHERE FIELD_10007 IS NOT NULL AND FIELD_10007 != '' AND FIELD_10007 NOT IN ('0', 'Desktop', 'Laptop') ORDER BY FIELD_10007")->fetchAll(PDO::FETCH_COLUMN);
} catch (\PDOException $e) {
    $bu_options = []; $team_options = [];
}

// 3. Pagination & Filters Logic
$limit = 50;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;
$bu = $_GET['bu'] ?? '';
$area = $_GET['area'] ?? '';
$topic = $_GET['topic'] ?? '';
$status = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';
$score_filter = $_GET['score'] ?? '';

$clean_query = rtrim(trim($eiaquery), ';');
$where = ["1=1"]; $params = [];
if ($bu) { $where[] = "`BU` = ?"; $params[] = $bu; }
if ($area) { 
    if ($area == 'None') { $where[] = "(`Serviced By` IS NULL OR `Serviced By` = '')"; }
    else { $where[] = "`Serviced By` = ?"; $params[] = $area; }
}
if ($search) { $where[] = "(`Computer Name` LIKE ? OR `Serial no.` LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }

if ($topic && $status) {
    $column = '';
    switch($topic) {
        case 'Domain': $column = '`Joined Approved Domain`'; break;
        case 'OS Status': $column = '`OS End of Support Status`'; break;
        case 'Patching': $column = '`Patch Healthy`'; break;
        case 'Antivirus': $column = '`Antivirus Compliant`'; break;
        case 'Firewall': $column = '`Firewall Compliant`'; break;
        case 'Admin Rights': $column = '`Standard Admin Only`'; break;
    }
    if ($column) {
        if ($status == 'success') { $where[] = "$column IN ('Y', 'Yes', 'Compliant', 'Success', 'Active', 'Healthy', 'Installed')"; }
        else { $where[] = "($column NOT IN ('Y', 'Yes', 'Compliant', 'Success', 'Active', 'Healthy', 'Installed') OR $column IS NULL)"; }
    }
}

if ($score_filter) {
    $success_in = "('" . implode("','", ['Y', 'Yes', 'Compliant', 'Success', 'Active', 'Healthy', 'Installed']) . "')";
    $calc_sql = "((CASE WHEN `Joined Approved Domain` IN $success_in THEN 1 ELSE 0 END) + (CASE WHEN `OS End of Support Status` IN $success_in THEN 1 ELSE 0 END) + (CASE WHEN `Patch Healthy` IN $success_in THEN 1 ELSE 0 END) + (CASE WHEN `Antivirus Compliant` IN $success_in THEN 1 ELSE 0 END) + (CASE WHEN `Firewall Compliant` IN $success_in THEN 1 ELSE 0 END) + (CASE WHEN `Standard Admin Only` IN $success_in THEN 1 ELSE 0 END))";
    if ($score_filter == '100') { $where[] = "$calc_sql = 6"; }
    elseif ($score_filter == '80-99') { $where[] = "$calc_sql = 5"; }
    elseif ($score_filter == '50-79') { $where[] = "$calc_sql >= 3 AND $calc_sql <= 4"; }
    elseif ($score_filter == '<50') { $where[] = "$calc_sql < 3"; }
}

$where_sql = implode(" AND ", $where);

try {
    $total_rows_stmt = $pdo->prepare("SELECT COUNT(*) FROM ($clean_query) as base_data WHERE $where_sql");
    $total_rows_stmt->execute($params);
    $total_rows = $total_rows_stmt->fetchColumn();
    $total_pages = ceil($total_rows / $limit) ?: 1;

    $stmt = $pdo->prepare("SELECT * FROM ($clean_query) as base_data WHERE $where_sql LIMIT $limit OFFSET $offset");
    $stmt->execute($params);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (isset($_GET['export']) && $_GET['export'] == 'excel') {
        $stmt_export = $pdo->prepare("SELECT * FROM ($clean_query) as base_data WHERE $where_sql");
        $stmt_export->execute($params);
        $all_items = $stmt_export->fetchAll(PDO::FETCH_ASSOC);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=EIA_Live_Inventory_'.date('Ymd').'.csv');
        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        if (!empty($all_items)) { fputcsv($output, array_keys($all_items[0])); foreach ($all_items as $r) { fputcsv($output, array_values($r)); } }
        fclose($output); exit;
    }
} catch (\PDOException $e) { die("Query Failed: " . $e->getMessage()); }

function getStatusIcon($val, $title = "") {
    if ($val === null) $val = 'No';
    $success_vals = ['Y', 'Yes', 'Compliant', 'Success', 'Active', 'Healthy', 'Installed'];
    $warning_vals = ['Pending', 'Insider', 'Not Support'];
    if (in_array($val, $success_vals)) return '<span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-emerald-100/50 text-emerald-600 border border-emerald-200/50 shadow-sm" title="'.$title.': '.$val.'"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg></span>';
    if (in_array($val, $warning_vals)) return '<span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-amber-100/50 text-amber-600 border border-amber-200/50 shadow-sm" title="'.$title.': '.$val.'"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg></span>';
    return '<span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-rose-100/50 text-rose-500 border border-rose-200/50 shadow-sm" title="'.$title.': '.$val.'"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"></path></svg></span>';
}

function highlight($text, $search) {
    if (!$search) return htmlspecialchars($text);
    return preg_replace('/('.preg_quote($search, '/').')/i', '<span class="bg-yellow-200 text-slate-900 px-0.5 rounded font-black">$1</span>', htmlspecialchars($text));
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EIA Compliance Dashboard — Inventory List</title>
    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%232563eb' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><path d='M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z'></path></svg>">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@700;800;900&family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Sarabun', sans-serif; background-image: linear-gradient(120deg, #a1c4fd 0%, #c2e9fb 100%); min-height: 100vh; background-attachment: fixed; }
        .glass-card { background: rgba(255, 255, 255, 0.75); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.6); }
        .table-container { overflow-x: auto; max-height: 600px; overflow-y: auto; }
        .table-container::-webkit-scrollbar { height: 6px; width: 6px; }
        .table-container::-webkit-scrollbar-thumb { background: rgba(30, 64, 175, 0.2); border-radius: 10px; }
        thead th { 
            position: sticky; top: 0; z-index: 20; 
            background: rgba(226, 232, 240, 0.98); backdrop-filter: blur(12px); 
            box-shadow: 0 2px 10px -2px rgba(0, 0, 0, 0.1), 0 1px 0 rgba(148, 163, 184, 1); 
            color: #0f172a; 
        }
        tr.hover-row:hover { transform: scale(1.002) translateY(-1px); background: rgba(255, 255, 255, 0.95) !important; box-shadow: 0 10px 20px -5px rgba(0, 0, 0, 0.05); z-index: 10; position: relative; }
    </style>
</head>
<body class="p-4 md:p-8">
    <div class="max-w-[1600px] mx-auto">
        
        <!-- Main Integrated Card -->
        <div class="glass-card rounded-xl overflow-hidden shadow-2xl relative border border-white">
            
            <!-- SECTION 1: HEADER -->
            <div class="p-6 md:p-8 flex flex-col md:flex-row justify-between items-center gap-6 border-b border-white/40 bg-white/30 relative overflow-hidden">
                <!-- Faint Background Image -->
                <div class="absolute inset-0 z-0 opacity-[0.04] pointer-events-none grayscale">
                    <img src="Image/bg-computer-8.png" alt="Header Background" class="w-full h-full object-cover">
                </div>

                <div class="flex items-center gap-5 relative z-10">
                    <div class="w-14 h-14 bg-white rounded-2xl flex items-center justify-center shadow-xl border border-slate-100 flex-shrink-0 p-1.5"><img src="Logo RIS.png" alt="RIS Logo" class="w-full h-full object-contain"></div>
                    <div>
                        <h1 class="text-3xl font-black text-slate-800 tracking-tighter" style="font-family: Montserrat;">EIA Dashboard</h1>
                        <div class="flex items-center gap-2 mt-0.5"><span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span><span class="text-[10px] font-black text-slate-500 uppercase tracking-widest">Live Monitoring From KACE System</span></div>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-3 justify-center md:justify-end relative z-10">
                    <div class="flex items-center bg-indigo-50 pl-4 pr-2 py-1.5 rounded-xl border border-indigo-100 shadow-inner group">
                        <span class="text-[10px] font-black text-indigo-600 uppercase tracking-widest mr-3">Showing <?= number_format($total_rows) ?> Results</span>
                        <button onclick="window.location.reload();" class="p-1.5 bg-white rounded-lg border border-indigo-200 text-indigo-500 hover:bg-indigo-500 hover:text-white transition-all shadow-sm" title="Force Refresh Data"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg></button>
                    </div>
                    <a href="index.php" class="bg-blue-600 px-4 py-2 rounded-xl shadow-sm border border-blue-500 text-white font-bold flex items-center gap-2 hover:bg-blue-700 transition-all group text-xs"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>Back to Dashboard</a>
                </div>
            </div>

            <!-- SECTION 2: INTEGRATED FILTER BAR -->
            <form method="GET" class="p-6 bg-slate-50/50 border-b border-slate-200/60">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-6 gap-4 items-end">
                    <div class="flex flex-col gap-1.5">
                        <label class="text-[9px] font-black text-slate-400 uppercase tracking-[0.2em] ml-1">Search Computer</label>
                        <div class="relative"><input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Name/Serial..." class="w-full bg-white border border-slate-200 rounded-xl pl-10 pr-4 py-2 text-sm font-bold text-slate-700 focus:ring-2 focus:ring-indigo-500 outline-none shadow-sm"><svg class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg></div>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="text-[9px] font-black text-slate-400 uppercase tracking-[0.2em] ml-1">BU</label>
                        <select name="bu" class="w-full bg-white border border-slate-200 rounded-xl px-4 py-2 text-sm font-bold text-slate-700 outline-none shadow-sm focus:ring-2 focus:ring-indigo-500"><option value="">All BUs</option><?php foreach ($bu_options as $opt): ?><option value="<?= htmlspecialchars($opt) ?>" <?= $bu == $opt ? 'selected' : '' ?>><?= htmlspecialchars($opt) ?></option><?php endforeach; ?></select>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="text-[9px] font-black text-slate-400 uppercase tracking-[0.2em] ml-1">Service Team</label>
                        <select name="area" class="w-full bg-white border border-slate-200 rounded-xl px-4 py-2 text-sm font-bold text-slate-700 outline-none shadow-sm focus:ring-2 focus:ring-indigo-500"><option value="">All Teams</option><?php foreach ($team_options as $opt): ?><option value="<?= htmlspecialchars($opt) ?>" <?= $area == $opt ? 'selected' : '' ?>><?= htmlspecialchars($opt) ?></option><?php endforeach; ?></select>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="text-[9px] font-black text-slate-400 uppercase tracking-[0.2em] ml-1">Compliance Score</label>
                        <select name="score" class="w-full bg-white border border-slate-200 rounded-xl px-4 py-2 text-sm font-bold text-slate-700 outline-none shadow-sm focus:ring-2 focus:ring-indigo-500"><option value="">All Scores</option><option value="100" <?= $score_filter == '100' ? 'selected' : '' ?>>100% Full</option><option value="80-99" <?= $score_filter == '80-99' ? 'selected' : '' ?>>80-99% Minor</option><option value="50-79" <?= $score_filter == '50-79' ? 'selected' : '' ?>>50-79% Risk</option><option value="<50" <?= $score_filter == '<50' ? 'selected' : '' ?>>< 50% Critical</option></select>
                    </div>
                    <div class="flex gap-2 h-[38px]">
                        <button type="submit" class="flex-1 bg-indigo-600 text-white rounded-xl font-black text-[10px] uppercase tracking-widest hover:bg-indigo-700 shadow-lg shadow-indigo-200 transition-all border border-indigo-400">Filter</button>
                        <a href="inventory_list.php" class="flex-1 px-4 bg-indigo-50 text-indigo-600 flex items-center justify-center rounded-xl font-black text-[10px] uppercase tracking-widest border border-indigo-200 hover:bg-indigo-600 hover:text-white transition-all duration-300 shadow-sm group whitespace-nowrap">
                            <svg class="w-3.5 h-3.5 mr-1.5 group-hover:-rotate-180 transition-transform duration-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                            Clear
                        </a>
                    </div>
                    <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'excel'])) ?>" class="h-[38px] bg-emerald-500 text-white rounded-xl font-black text-[10px] uppercase tracking-widest hover:bg-emerald-600 shadow-lg shadow-emerald-200 transition-all border border-emerald-400 flex items-center justify-center gap-2 px-4 whitespace-nowrap"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>Export</a>
                </div>
                
                <div class="mt-5 flex flex-wrap items-center justify-between gap-4">
                    <!-- Active Filters (Moved to Left and Enhanced) -->
                    <div class="flex flex-wrap items-center gap-2">
                        <?php if ($bu || $area || $search || $score_filter || $topic): ?>
                        <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest mr-1">Active Filters:</span>
                        <div class="flex flex-wrap items-center gap-2">
                            <?php if ($bu): ?><span class="text-[10px] bg-indigo-600 text-white px-3 py-1 rounded-xl shadow-lg shadow-indigo-200 font-black uppercase tracking-tighter border border-indigo-500">BU: <?= htmlspecialchars($bu) ?></span><?php endif; ?>
                            <?php if ($area): ?><span class="text-[10px] bg-indigo-600 text-white px-3 py-1 rounded-xl shadow-lg shadow-indigo-200 font-black uppercase tracking-tighter border border-indigo-500">Team: <?= htmlspecialchars($area) ?></span><?php endif; ?>
                            <?php if ($topic): ?><span class="text-[10px] bg-indigo-600 text-white px-3 py-1 rounded-xl shadow-lg shadow-indigo-200 font-black uppercase tracking-tighter border border-indigo-500">Topic: <?= htmlspecialchars($topic) ?></span><?php endif; ?>
                            <?php if ($score_filter): ?><span class="text-[10px] bg-indigo-600 text-white px-3 py-1 rounded-xl shadow-lg shadow-indigo-200 font-black uppercase tracking-tighter border border-indigo-500">Score: <?= htmlspecialchars($score_filter) ?>%</span><?php endif; ?>
                            <?php if ($search): ?><span class="text-[10px] bg-amber-500 text-white px-3 py-1 rounded-xl shadow-lg shadow-amber-200 font-black uppercase tracking-tighter border border-amber-400 italic">Search: "<?= htmlspecialchars($search) ?>"</span><?php endif; ?>
                        </div>
                        <?php else: ?>
                        <span class="text-[10px] text-slate-400 font-bold italic">No active filters applied</span>
                        <?php endif; ?>
                    </div>

                    <!-- Current Page Summary (Moved to Right) -->
                    <div class="flex flex-wrap items-center gap-2 bg-white/40 p-1.5 rounded-2xl border border-white/60 shadow-sm">
                        <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest mx-2">Page Summary:</span>
                        <?php 
                            $page_full = 0; $page_crit = 0;
                            foreach($items as $row) {
                                $success_keys = ['Y', 'Yes', 'Compliant', 'Success', 'Active', 'Healthy', 'Installed'];
                                $passed = 0;
                                foreach([$row['Joined Approved Domain'], $row['OS End of Support Status'], $row['Patch Healthy'], $row['Antivirus Compliant'], $row['Firewall Compliant'], $row['Standard Admin Only']] as $f) { if (in_array($f, $success_keys)) $passed++; }
                                if ($passed == 6) $page_full++;
                                if ($passed <= 2) $page_crit++;
                            }
                        ?>
                        <span class="text-[10px] bg-emerald-50 text-emerald-700 border border-emerald-100 px-2.5 py-1 rounded-lg font-black uppercase tracking-tighter flex items-center gap-1.5"><div class="w-1.5 h-1.5 bg-emerald-500 rounded-full"></div>Full: <?= $page_full ?></span>
                        <span class="text-[10px] bg-rose-50 text-rose-700 border border-rose-100 px-2.5 py-1 rounded-lg font-black uppercase tracking-tighter flex items-center gap-1.5"><div class="w-1.5 h-1.5 bg-rose-500 rounded-full"></div>Critical: <?= $page_crit ?></span>
                    </div>
                </div>
            </form>

            <!-- SECTION 3: THE TABLE -->
            <div class="table-container min-h-[400px]">
                <table class="w-full text-left whitespace-nowrap border-separate border-spacing-0">
                    <thead>
                        <tr>
                            <th class="px-6 py-5 text-xs font-black uppercase tracking-wider">Computer Name</th>
                            <th class="px-6 py-5 text-xs font-black uppercase tracking-wider">BU</th>
                            <th class="px-6 py-5 text-xs font-black uppercase tracking-wider">Service Team</th>
                            <th class="px-6 py-5 text-xs font-black uppercase tracking-wider">OS Details</th>
                            <th class="px-6 py-5 text-xs font-black uppercase tracking-wider text-center">Domain</th>
                            <th class="px-6 py-5 text-xs font-black uppercase tracking-wider text-center">OS Status</th>
                            <th class="px-6 py-5 text-xs font-black uppercase tracking-wider text-center">Patching</th>
                            <th class="px-6 py-5 text-xs font-black uppercase tracking-wider text-center">ANTIVIRUS</th>
                            <th class="px-6 py-5 text-xs font-black uppercase tracking-wider text-center">Firewall</th>
                            <th class="px-6 py-5 text-xs font-black uppercase tracking-wider text-center">ADMIN RIGHTS</th>
                            <th class="px-6 py-5 text-xs font-black uppercase tracking-wider text-center border-r border-slate-200">SCORE</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white/40">
                        <?php foreach ($items as $row): 
                            $success_keys = ['Y', 'Yes', 'Compliant', 'Success', 'Active', 'Healthy', 'Installed'];
                            $check_fields = [$row['Joined Approved Domain'], $row['OS End of Support Status'], $row['Patch Healthy'], $row['Antivirus Compliant'], $row['Firewall Compliant'], $row['Standard Admin Only']];
                            $passed = 0; foreach($check_fields as $f) { if (in_array($f, $success_keys)) $passed++; }
                            $score_pct = round(($passed / 6) * 100);

                            // Define explicit Tailwind classes based on score
                            if ($score_pct == 100) {
                                $t_color = 'text-emerald-600'; $b_color = 'bg-emerald-500'; $bg_soft = 'bg-emerald-50'; $border_c = 'border-emerald-200';
                            } elseif ($score_pct >= 80) {
                                $t_color = 'text-blue-600'; $b_color = 'bg-blue-500'; $bg_soft = 'bg-blue-50'; $border_c = 'border-blue-200';
                            } elseif ($score_pct >= 50) {
                                $t_color = 'text-amber-600'; $b_color = 'bg-amber-500'; $bg_soft = 'bg-amber-50'; $border_c = 'border-amber-200';
                            } else {
                                $t_color = 'text-rose-600'; $b_color = 'bg-rose-500'; $bg_soft = 'bg-rose-50'; $border_c = 'border-rose-200';
                            }
                        ?>
                        <tr class="hover-row transition-all duration-300 group">
                            <td class="px-6 py-4"><a href="inventory_detail.php?name=<?= urlencode($row['Computer Name']) ?>&back=<?= urlencode($_SERVER['REQUEST_URI']) ?>" class="font-black text-slate-800 text-[13px] hover:text-indigo-600 transition-colors flex items-center gap-2"><?= highlight($row['Computer Name'], $search) ?><svg class="w-3.5 h-3.5 opacity-0 group-hover:opacity-100 transition-opacity" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg></a></td>
                            <td class="px-6 py-4"><span class="px-2.5 py-1 bg-white border border-slate-200 rounded-lg text-[10px] font-black text-slate-500 uppercase shadow-sm"><?= htmlspecialchars($row['BU']) ?></span></td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <?php 
                                        $team = htmlspecialchars($row['Serviced By'] ?: 'None');
                                        $team_icon = '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>'; // Default User
                                        $team_color = 'text-slate-400';

                                        if (stripos($team, 'HO') !== false) {
                                            $team_icon = '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H5a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>';
                                            $team_color = 'text-indigo-600';
                                        } elseif (stripos($team, 'DC') !== false) {
                                            $team_icon = '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path></svg>';
                                            $team_color = 'text-blue-600';
                                        } elseif (stripos($team, 'Branch') !== false) {
                                            $team_icon = '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>';
                                            $team_color = 'text-emerald-600';
                                        } elseif (stripos($team, 'No Service') !== false) {
                                            $team_icon = '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728L5.636 5.636"></path></svg>';
                                            $team_color = 'text-rose-500';
                                        }
                                    ?>
                                    <span class="<?= $team_color ?> opacity-80"><?= $team_icon ?></span>
                                    <span class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase"><?= $team ?></span>
                                </div>
                            </td>
                            <td class="px-6 py-4"><div class="flex flex-col"><div class="flex items-center gap-1.5"><svg class="w-3 h-3 text-blue-500" fill="currentColor" viewBox="0 0 24 24"><path d="M0 0v24h24v-24h-24zm10.642 11.218l-8.214-.112v-7.106l8.214.112v7.106zm0 8.782l-8.214.112v-7.67l8.214-.112v7.67zm10.93-.112l-9.93-.136v-7.544l9.93.136v7.544zm0-8.67l-9.93-.136v-7.106l9.93.136v7.106z"/></svg><span class="text-xs font-black text-slate-700"><?= htmlspecialchars($row['OS Name'] ?? '-') ?></span></div><span class="text-[10px] font-black text-slate-400 mt-0.5 ml-4">Build: <?= htmlspecialchars($row['OS Build'] ?? '') ?></span></div></td>
                            <td class="px-6 py-3 text-center"><?= getStatusIcon($row['Joined Approved Domain'], "Domain") ?></td>
                            <td class="px-6 py-3 text-center"><?= getStatusIcon($row['OS End of Support Status'], "OS Status") ?></td>
                            <td class="px-6 py-3 text-center"><?= getStatusIcon($row['Patch Healthy'], "Patching") ?></td>
                            <td class="px-6 py-3 text-center"><?= getStatusIcon($row['Antivirus Compliant'], "Antivirus") ?></td>
                            <td class="px-6 py-3 text-center"><?= getStatusIcon($row['Firewall Compliant'], "Firewall") ?></td>
                            <td class="px-6 py-3 text-center"><?= getStatusIcon($row['Standard Admin Only'], "Admin Rights") ?></td>
                            <td class="px-6 py-4 text-center">
                                <div class="flex flex-col items-center gap-1.5">
                                    <div class="inline-flex px-2 py-1 rounded-lg border font-black text-[10px] <?= $bg_soft ?> <?= $t_color ?> <?= $border_c ?> shadow-sm min-w-[45px] justify-center"><?= $score_pct ?>%</div>
                                    <div class="w-12 bg-slate-100 rounded-full h-1 overflow-hidden border border-slate-200/50">
                                        <div class="h-full rounded-full transition-all duration-1000 <?= $b_color ?>" style="width: <?= $score_pct ?>%"></div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($items)): ?>
                        <tr>
                            <td colspan="11" class="px-6 py-24 text-center">
                                <div class="flex flex-col items-center justify-center opacity-70">
                                    <svg class="w-16 h-16 text-slate-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2 2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path></svg>
                                    <span class="text-sm font-black text-slate-500 uppercase tracking-[0.2em] mt-2 mb-4">No Data Found</span>
                                    <a href="inventory_list.php" class="px-6 py-2.5 bg-indigo-50 text-indigo-600 rounded-xl font-black text-[10px] uppercase tracking-widest border border-indigo-200 hover:bg-indigo-600 hover:text-white transition-all duration-300 shadow-sm flex items-center gap-2 group">
                                        <svg class="w-4 h-4 group-hover:-rotate-180 transition-transform duration-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                                        Clear All Filters
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- SECTION 4: PAGINATION -->
            <div class="p-6 bg-white/50 border-t border-slate-200/60 flex flex-col md:flex-row justify-between items-center gap-6">
                <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Showing <?= min($offset + 1, $total_rows) ?> to <?= min($offset + $limit, $total_rows) ?> of <?= number_format($total_rows) ?> records</div>
                <div class="flex gap-2">
                    <?php if ($page > 1): ?><a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="px-4 py-2 bg-white border border-slate-200 rounded-xl text-xs font-black text-slate-500 hover:bg-slate-50 transition-all uppercase text-[10px]">Prev</a><?php endif; ?>
                    <div class="flex items-center px-5 text-xs font-black text-white bg-indigo-600 rounded-xl shadow-lg border border-indigo-500 text-[10px]">PAGE <?= $page ?> / <?= $total_pages ?></div>
                    <?php if ($page < $total_pages): ?><a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="px-4 py-2 bg-white border border-slate-200 rounded-xl text-xs font-black text-slate-500 hover:bg-slate-50 transition-all uppercase text-[10px]">Next</a><?php endif; ?>
                </div>
            </div>
        </div>
        <footer class="mt-12 text-center pb-12"><p class="text-[10px] font-black text-slate-400 uppercase tracking-[0.5em]">EIA Dashboard &copy; 2026 Prepared by Endpoint Management Team</p></footer>
        </div>

        <!-- Back to Top Button -->
        <button id="backToTop" class="fixed bottom-8 right-8 z-[150] w-12 h-12 bg-white/80 dark:bg-slate-800/80 backdrop-blur-md border border-white/40 dark:border-slate-700/40 rounded-full shadow-2xl flex items-center justify-center text-blue-600 dark:text-blue-400 transition-all duration-500 translate-y-24 opacity-0 hover:bg-blue-600 hover:text-white dark:hover:bg-blue-500 active:scale-90 group" title="Back to Top">
        <svg class="w-6 h-6 transform group-hover:-translate-y-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 15l7-7 7 7"></path></svg>
        </button>

        <script>
        const backToTopBtn = document.getElementById('backToTop');
        window.addEventListener('scroll', () => {
            if (window.pageYOffset > 300) {
                backToTopBtn.classList.remove('translate-y-24', 'opacity-0');
                backToTopBtn.classList.add('translate-y-0', 'opacity-100');
            } else {
                backToTopBtn.classList.add('translate-y-24', 'opacity-0');
                backToTopBtn.classList.remove('translate-y-0', 'opacity-100');
            }
        });
        backToTopBtn.addEventListener('click', () => {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
        </script>
        </body>
        </html>