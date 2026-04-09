<?php
// 1. Database Connection
require_once 'config.php';

// 2. Fetch Unique Filter Options (For Dropdowns)
$bu_options = $pdo->query("SELECT DISTINCT bu FROM inventory_reports WHERE bu IS NOT NULL ORDER BY bu")->fetchAll(PDO::FETCH_COLUMN);
$team_options = $pdo->query("SELECT DISTINCT serviced_by FROM inventory_reports WHERE serviced_by IS NOT NULL ORDER BY serviced_by")->fetchAll(PDO::FETCH_COLUMN);

// 3. Pagination & Filters
$limit = 50;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$bu = isset($_GET['bu']) ? $_GET['bu'] : '';
$area = isset($_GET['area']) ? $_GET['area'] : '';
$topic = isset($_GET['topic']) ? $_GET['topic'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';
$score_filter = isset($_GET['score']) ? $_GET['score'] : '';

// Build Query
$where = ["report_week = (SELECT MAX(report_week) FROM inventory_reports)"];
$params = [];

if ($bu) { $where[] = "bu = ?"; $params[] = $bu; }
if ($area) { 
    if ($area == 'None') {
        $where[] = "(serviced_by IS NULL OR serviced_by = '')";
    } else {
        $where[] = "serviced_by = ?"; 
        $params[] = $area; 
    }
}
if ($search) { 
    $where[] = "(computer_name LIKE ? OR serial_no LIKE ?)"; 
    $params[] = "%$search%"; 
    $params[] = "%$search%"; 
}

// Build Query with Topic/Status Filters (from Dashboard)
if ($topic && $status) {
    $column = '';
    switch($topic) {
        case 'Domain': $column = 'joined_approved_domain'; break;
        case 'OS Status': $column = 'os_eos_status'; break;
        case 'Patching': $column = 'patch_healthy'; break;
        case 'Antivirus': $column = 'av_compliant'; break;
        case 'Firewall': $column = 'firewall_compliant'; break;
        case 'Admin Rights': $column = 'standard_admin_only'; break;
    }
    if ($column) {
        if ($status == 'success') {
            $where[] = "$column IN ('Y', 'Yes', 'Compliant', 'Success', 'Active', 'Healthy')";
        } else {
            $where[] = "($column NOT IN ('Y', 'Yes', 'Compliant', 'Success', 'Active', 'Healthy') OR $column IS NULL)";
        }
    }
}

// Add Score Filter (Dynamic SQL Calculation)
if ($score_filter) {
    $success_in = "('" . implode("','", ['Y', 'Yes', 'Compliant', 'Success', 'Active', 'Allowed', 'Healthy']) . "')";
    $calc_sql = "(
        (CASE WHEN joined_approved_domain IN $success_in THEN 1 ELSE 0 END) +
        (CASE WHEN os_eos_status IN $success_in THEN 1 ELSE 0 END) +
        (CASE WHEN patch_healthy IN $success_in THEN 1 ELSE 0 END) +
        (CASE WHEN av_compliant IN $success_in THEN 1 ELSE 0 END) +
        (CASE WHEN firewall_compliant IN $success_in THEN 1 ELSE 0 END) +
        (CASE WHEN standard_admin_only IN $success_in THEN 1 ELSE 0 END)
    )";
    
    if ($score_filter == '100') {
        $where[] = "$calc_sql = 6";
    } elseif ($score_filter == '80-99') {
        $where[] = "$calc_sql = 5";
    } elseif ($score_filter == '50-79') {
        $where[] = "$calc_sql >= 3 AND $calc_sql <= 4";
    } elseif ($score_filter == '<50') {
        $where[] = "$calc_sql < 3";
    }
}

$where_sql = implode(" AND ", $where);
$stmt = $pdo->prepare("SELECT * FROM inventory_reports WHERE $where_sql ORDER BY computer_name ASC LIMIT $limit OFFSET $offset");
$stmt->execute($params);
$items = $stmt->fetchAll();

// Total for Pagination
$stmt_total = $pdo->prepare("SELECT COUNT(*) as total FROM inventory_reports WHERE $where_sql");
$stmt_total->execute($params);
$total_rows = $stmt_total->fetch()['total'];
$total_pages = ceil($total_rows / $limit);

// --- 4. Export Logic (Full 35 Fields) ---
if (isset($_GET['export']) && $_GET['export'] == 'excel') {
    $stmt_export = $pdo->prepare("SELECT * FROM inventory_reports WHERE $where_sql ORDER BY computer_name ASC");
    $stmt_export->execute($params);
    $all_items = $stmt_export->fetchAll();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=EIA_Full_Inventory_'.date('Ymd').'.csv');
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // Thai support
    
    if (!empty($all_items)) {
        // 1. Generate Headers from Table Keys (All 35+ fields)
        $headers = array_keys($all_items[0]);
        fputcsv($output, $headers);
        
        // 2. Put Data
        foreach ($all_items as $row) {
            fputcsv($output, array_values($row));
        }
    }
    fclose($output);
    exit;
}

// Helper function for Status UI
function getStatusIcon($val) {
    $success_vals = ['Y', 'Yes', 'Compliant', 'Success', 'Active', 'Healthy'];
    if (in_array($val, $success_vals)) {
        return '<span class="text-emerald-500 font-black">✓</span>';
    }
    return '<span class="text-rose-400 font-black">✗</span>';
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory List - EIA Report</title>
    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%232563eb' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><path d='M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z'></path></svg>">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        body { 
            font-family: 'Sarabun', sans-serif; 
            background-image: linear-gradient(-225deg, #69EACB 0%, #EACCF8 48%, #6654F1 100%);
            min-height: 100vh;
            background-attachment: fixed;
        }
        .table-container { 
            overflow-x: auto; 
            max-height: 700px; /* Optional: Constrain height for internal scroll */
            overflow-y: auto;
        }
        .table-container::-webkit-scrollbar { height: 8px; width: 8px; }
        .table-container::-webkit-scrollbar-track { background: #f1f1f1; }
        .table-container::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        
        /* Sticky Header Logic */
        thead th { 
            position: sticky; 
            top: 0; 
            z-index: 20; 
            background-color: #f8fafc; /* Match container or specific color */
            box-shadow: 0 1px 0 #e2e8f0; /* Subtle border-bottom replacement */
        }
    </style>
</head>
<body class="p-4 md:p-10">
    <div class="max-w-[1600px] mx-auto">
        
        <!-- Top Navigation -->
        <div class="mb-8 flex flex-col md:flex-row justify-between items-start md:items-end gap-6">
            <div>
                <a href="index.php" class="text-blue-600 font-bold flex items-center gap-2 mb-2 hover:underline group">
                    <svg class="w-4 h-4 group-hover:-translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                    Back to Dashboard
                </a>
                <h1 class="text-4xl font-black text-slate-900 tracking-tight flex items-center gap-3">
                    <svg class="w-10 h-10 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                    EIA Inventory Details
                </h1>
                <p class="text-slate-500 text-sm mt-1 uppercase font-bold tracking-widest">Compliance Status List</p>
            </div>
            
            <!-- Filter Bar -->
            <form method="GET" class="bg-white p-4 rounded-3xl border border-slate-200 shadow-xl flex flex-wrap items-end gap-4 w-fit">
                <!-- Keep Dashboard Context -->
                <?php if ($topic): ?><input type="hidden" name="topic" value="<?= htmlspecialchars($topic) ?>"><?php endif; ?>
                <?php if ($status): ?><input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>"><?php endif; ?>

                <div class="flex flex-col gap-1 w-48">
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Search Device</label>
                    <div class="relative">
                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search Name/Serial..." 
                               class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-10 pr-4 py-2 text-sm font-bold text-slate-700 focus:ring-2 focus:ring-blue-500 outline-none transition-all">
                        <svg class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </div>
                </div>

                <div class="flex flex-col gap-1">
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Business Unit (BU)</label>
                    <select name="bu" class="bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 text-sm font-bold text-slate-700 focus:ring-2 focus:ring-blue-500 outline-none min-w-[150px]">
                        <option value="">All BUs</option>
                        <?php foreach ($bu_options as $opt): ?>
                        <option value="<?= htmlspecialchars($opt) ?>" <?= $bu == $opt ? 'selected' : '' ?>><?= htmlspecialchars($opt) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="flex flex-col gap-1">
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Service Team</label>
                    <select name="area" class="bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 text-sm font-bold text-slate-700 focus:ring-2 focus:ring-blue-500 outline-none min-w-[150px]">
                        <option value="">All Teams</option>
                        <?php foreach ($team_options as $opt): ?>
                        <option value="<?= htmlspecialchars($opt) ?>" <?= $area == $opt ? 'selected' : '' ?>><?= htmlspecialchars($opt) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="flex flex-col gap-1">
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Compliance Score</label>
                    <select name="score" class="bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 text-sm font-bold text-slate-700 focus:ring-2 focus:ring-blue-500 outline-none min-w-[150px]">
                        <option value="">All Scores</option>
                        <option value="100" <?= $score_filter == '100' ? 'selected' : '' ?>>100% (Fully Compliant)</option>
                        <option value="80-99" <?= $score_filter == '80-99' ? 'selected' : '' ?>>83% (Minor Issue)</option>
                        <option value="50-79" <?= $score_filter == '50-79' ? 'selected' : '' ?>>50 - 67% (At Risk)</option>
                        <option value="<50" <?= $score_filter == '<50' ? 'selected' : '' ?>>&lt; 50% (Critical)</option>
                    </select>
                </div>

                <div class="flex gap-2">
                    <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-xl font-bold text-sm hover:bg-blue-700 transition-all shadow-lg shadow-blue-200">Apply Filter</button>
                    <a href="inventory_list.php" class="bg-slate-100 text-slate-500 px-4 py-2 rounded-xl font-bold text-sm hover:bg-slate-200 transition-all flex items-center">Reset</a>
                    <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'excel'])) ?>" class="bg-emerald-600 text-white px-6 py-2 rounded-xl font-bold text-sm hover:bg-emerald-700 transition-all shadow-lg shadow-emerald-200 flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                        Export Excel
                    </a>
                </div>
            </form>
        </div>

        <!-- Total Results Banner -->
        <div class="mb-6 flex items-center gap-3">
            <span class="px-3 py-1 bg-blue-600 text-white rounded-full text-[10px] font-black uppercase tracking-widest">Total Found: <?= number_format($total_rows) ?></span>
            <?php if ($bu || $area || $topic): ?>
            <span class="px-3 py-1 bg-emerald-500 text-white rounded-full text-[10px] font-black uppercase tracking-widest">Active Filters Applied</span>
            <?php endif; ?>
        </div>

        <!-- Table Container -->
        <div class="bg-white rounded-[2rem] shadow-2xl border border-white overflow-hidden backdrop-blur-sm">
            <div class="table-container overflow-x-auto">
                <table class="w-full text-left border-collapse whitespace-nowrap">
                    <thead>
                        <tr class="bg-slate-50/50 border-b border-slate-100">
                            <th class="p-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">Computer Name</th>
                            <th class="p-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">BU</th>
                            <th class="p-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">Service Team</th>
                            <th class="p-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">Type</th>
                            <th class="p-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">OS Name</th>
                            <th class="p-5 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center">Approved Domain</th>
                            <th class="p-5 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center">OS Status</th>
                            <th class="p-5 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center">Patching</th>
                            <th class="p-5 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center">Antivirus</th>
                            <th class="p-5 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center">Firewall</th>
                            <th class="p-5 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center">Admin Rights</th>
                            <th class="p-5 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center">Score</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <?php foreach ($items as $row): 
                            // Calculate Row Compliance Score
                            $success_keys = ['Y', 'Yes', 'Compliant', 'Success', 'Active', 'Allowed', 'Healthy'];
                            $check_fields = [
                                $row['joined_approved_domain'],
                                $row['os_eos_status'],
                                $row['patch_healthy'],
                                $row['av_compliant'],
                                $row['firewall_compliant'],
                                $row['standard_admin_only']
                            ];
                            $passed_count = 0;
                            foreach($check_fields as $f) {
                                if (in_array($f, $success_keys)) $passed_count++;
                            }
                            $score_pct = round(($passed_count / 6) * 100);
                            
                            $score_color = 'text-rose-500';
                            if ($score_pct == 100) $score_color = 'text-emerald-500';
                            elseif ($score_pct >= 70) $score_color = 'text-blue-600';
                            elseif ($score_pct >= 50) $score_color = 'text-amber-500';
                        ?>
                        <tr class="hover:bg-blue-50/30 transition-colors group">
                            <td class="p-5">
                                <a href="inventory_detail.php?id=<?= $row['id'] ?>&back=<?= urlencode($_SERVER['REQUEST_URI']) ?>" class="font-bold text-slate-800 group-hover:text-blue-600 transition-colors flex items-center gap-2">
                                    <?= htmlspecialchars($row['computer_name']) ?>
                                    <svg class="w-3 h-3 opacity-0 group-hover:opacity-100 transition-opacity" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M9 5l7 7-7 7"></path></svg>
                                </a>
                            </td>
                            <td class="p-5 text-sm font-bold text-slate-500 uppercase"><?= htmlspecialchars($row['bu']) ?></td>
                            <td class="p-5"><span class="px-3 py-1 bg-slate-100 text-slate-500 rounded-full text-[10px] font-black uppercase tracking-tight"><?= htmlspecialchars($row['serviced_by']) ?></span></td>
                            <td class="p-5 text-xs text-slate-400 font-bold"><?= htmlspecialchars($row['computer_type']) ?></td>
                            <td class="p-5 text-xs text-slate-500"><?= htmlspecialchars($row['os_name']) ?></td>
                            <td class="p-5 text-center"><?= getStatusIcon($row['joined_approved_domain']) ?></td>
                            <td class="p-5 text-center"><?= getStatusIcon($row['os_eos_status']) ?></td>
                            <td class="p-5 text-center"><?= getStatusIcon($row['patch_healthy']) ?></td>
                            <td class="p-5 text-center"><?= getStatusIcon($row['av_compliant']) ?></td>
                            <td class="p-5 text-center"><?= getStatusIcon($row['firewall_compliant']) ?></td>
                            <td class="p-5 text-center"><?= getStatusIcon($row['standard_admin_only']) ?></td>
                            <td class="p-5 text-center">
                                <span class="font-black <?= $score_color ?>"><?= $score_pct ?>%</span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($items)): ?>
                        <tr><td colspan="11" class="p-20 text-center font-bold text-slate-300 uppercase tracking-[0.5em]">No Data Found matching filters</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="p-8 bg-slate-50/50 border-t border-slate-100 flex flex-col md:flex-row justify-between items-center gap-6">
                <div class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em]">
                    Showing <?= $offset + 1 ?> to <?= min($offset + $limit, $total_rows) ?> of <?= number_format($total_rows) ?> records
                </div>
                <div class="flex gap-2">
                    <?php if ($page > 1): ?>
                    <a href="?page=1&bu=<?= urlencode($bu) ?>&area=<?= urlencode($area) ?>&topic=<?= urlencode($topic) ?>&status=<?= urlencode($status) ?>&score=<?= urlencode($score_filter) ?>" class="px-4 py-2 bg-white border border-slate-200 rounded-xl text-xs font-black hover:bg-slate-50 transition-all uppercase">First</a>
                    <a href="?page=<?= $page-1 ?>&bu=<?= urlencode($bu) ?>&area=<?= urlencode($area) ?>&topic=<?= urlencode($topic) ?>&status=<?= urlencode($status) ?>&score=<?= urlencode($score_filter) ?>" class="px-4 py-2 bg-white border border-slate-200 rounded-xl text-xs font-black hover:bg-slate-50 transition-all uppercase">Prev</a>
                    <?php endif; ?>
                    
                    <div class="flex items-center px-4 text-xs font-black text-blue-600 bg-blue-50 rounded-xl border border-blue-100">
                        PAGE <?= $page ?> / <?= $total_pages ?>
                    </div>

                    <?php if ($page < $total_pages): ?>
                    <a href="?page=<?= $page+1 ?>&bu=<?= urlencode($bu) ?>&area=<?= urlencode($area) ?>&topic=<?= urlencode($topic) ?>&status=<?= urlencode($status) ?>&score=<?= urlencode($score_filter) ?>" class="px-4 py-2 bg-white border border-slate-200 rounded-xl text-xs font-black hover:bg-slate-50 transition-all uppercase">Next</a>
                    <a href="?page=<?= $total_pages ?>&bu=<?= urlencode($bu) ?>&area=<?= urlencode($area) ?>&topic=<?= urlencode($topic) ?>&status=<?= urlencode($status) ?>&score=<?= urlencode($score_filter) ?>" class="px-4 py-2 bg-white border border-slate-200 rounded-xl text-xs font-black hover:bg-slate-50 transition-all uppercase">Last</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <p class="mt-12 text-center text-[10px] font-black text-slate-500 uppercase tracking-[0.5em]">&copy; 2026 EIA COMPLIANCE UNIT | Prepared by Endpoint Management Team</p>
    </div>
</body>
</html>