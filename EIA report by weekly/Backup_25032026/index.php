<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// 1. Database Connection
require_once 'config.php';

// 2. Get Latest Week/Year from Database
$stmt_latest = $pdo->query("SELECT MAX(report_week) as max_w, MAX(report_year) as max_y FROM inventory_reports");
$latest = $stmt_latest->fetch();
$week = $latest['max_w'] ?: date("W");
$year = $latest['max_y'] ?: date("Y");

// 3. Fetch Metrics
// Grand Total
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM inventory_reports WHERE report_week = ? AND report_year = ?");
$stmt->execute([$week, $year]);
$total_data = $stmt->fetch();
$current_total = $total_data['total'] ?: 1;

// Topics Configuration
$topics_map = [
    'Domain' => 'joined_approved_domain',
    'OS Status' => 'os_eos_status',
    'Patching' => 'patch_healthy',
    'Antivirus' => 'av_compliant',
    'Firewall' => 'firewall_compliant',
    'Admin Rights' => 'standard_admin_only'
];

$topic_results = [];
$total_success_sum = 0;
$success_keys = ['Y', 'Yes', 'Compliant', 'Success', 'Active', 'Healthy'];

foreach ($topics_map as $label => $col) {
    $stmt = $pdo->prepare("SELECT SUM(CASE WHEN $col IN ('" . implode("','", $success_keys) . "') THEN 1 ELSE 0 END) as success FROM inventory_reports WHERE report_week = ? AND report_year = ?");
    $stmt->execute([$week, $year]);
    $res = $stmt->fetch();
    $s = (int)$res['success'];
    $p = $current_total - $s;
    $rate = round(($s / $current_total) * 100, 1);
    $total_success_sum += $s;
    
    $color_tail = 'rose-500'; $bg_tail = 'bg-rose-50';
    if ($rate >= 81) { $color_tail = 'emerald-500'; $bg_tail = 'bg-emerald-50'; }
    elseif ($rate >= 70) { $color_tail = 'blue-600'; $bg_tail = 'bg-blue-50'; }
    elseif ($rate >= 50) { $color_tail = 'amber-500'; $bg_tail = 'bg-amber-50'; }

    $topic_results[] = [
        'label' => $label, 'success' => $s, 'pending' => $p, 'rate' => $rate, 'color_tail' => $color_tail, 'bg_tail' => $bg_tail
    ];
}

// Overall Health Calculation
$overall_rate = round(($total_success_sum / ($current_total * 6)) * 100, 1);

// Serviced By Data
$stmt = $pdo->prepare("SELECT COALESCE(NULLIF(serviced_by, ''), 'None') as serviced_by, COUNT(*) as count FROM inventory_reports WHERE report_week = ? AND report_year = ? GROUP BY COALESCE(NULLIF(serviced_by, ''), 'None')");
$stmt->execute([$week, $year]);
$serviced_data = $stmt->fetchAll();

// Top BUs (Patch Success)
$stmt = $pdo->prepare("SELECT bu, COUNT(*) as success_count FROM inventory_reports WHERE patch_healthy IN ('" . implode("','", $success_keys) . "') AND report_week = ? AND report_year = ? GROUP BY bu ORDER BY success_count DESC LIMIT 5");
$stmt->execute([$week, $year]);
$bu_data = $stmt->fetchAll();
$max_bu_val = !empty($bu_data) ? max(array_column($bu_data, 'success_count')) : 1;

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EIA Compliance Dashboard — Week <?= $week ?></title>
    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%232563eb' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><path d='M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z'></path></svg>">
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font -->
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { 
            font-family: 'Sarabun', sans-serif; 
            background-image: linear-gradient(-225deg, #69EACB 0%, #EACCF8 48%, #6654F1 100%);
            min-height: 100vh;
            background-attachment: fixed;
        }
        .conic-chart { 
            background: conic-gradient(
                #22c55e 0% <?= $overall_rate ?>%, 
                #e5e7eb <?= $overall_rate ?>% 100%
            ); 
        }
    </style>
</head>
<body class="p-4 md:p-8">

    <div class="max-w-7xl mx-auto bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
        
        <!-- Header Section -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center p-5 border-b border-gray-100 gap-4 md:gap-0">
            <div class="flex items-center gap-4">
                <a href="index.php" title="Dashboard" class="w-14 h-14 bg-white rounded-xl flex items-center justify-center shadow-lg hover:shadow-xl transition-all overflow-hidden border border-gray-100 p-1">
                    <img src="Logo RIS.png" alt="RIS Logo" class="w-full h-full object-contain">
                </a>
                <div>
                    <h1 class="text-xl font-black text-gray-800 tracking-tight flex items-center gap-2">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                        <a href="index.php" class="hover:text-blue-600 transition-colors uppercase">EIA Compliance — Infrastructure Matrix</a>
                    </h1>
                    <p class="text-[11px] text-gray-400 font-bold uppercase tracking-widest mt-0.5">Live Security Monitoring & Analysis System</p>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-3 text-sm w-full md:w-auto">
                <span class="text-gray-400 font-bold uppercase text-[10px] tracking-widest hidden lg:block">Status: Verified</span>
                <span class="bg-blue-50 text-blue-600 px-4 py-1.5 rounded-full border border-blue-100 font-bold whitespace-nowrap flex items-center gap-2 text-xs">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                    Week <?= $week ?> / <?= $year ?>
                </span>
                
                <a href="inventory_list.php" class="bg-gray-100 px-4 py-2 rounded-lg border hover:bg-gray-200 transition-colors flex items-center gap-2 font-bold text-xs text-gray-600 uppercase">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
                    Full List
                </a>
                <a href="https://centralgroup.sharepoint.com/sites/ITS/SitePages/ProjectHome.aspx" target="_blank" class="bg-white px-4 py-2 rounded-lg border hover:bg-blue-50 hover:border-blue-200 text-blue-600 transition-colors flex items-center gap-2 font-bold text-xs uppercase">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    SharePoint
                </a>
                <a href="logout.php" class="bg-rose-50 px-4 py-2 rounded-lg border border-rose-100 hover:bg-rose-100 text-rose-600 transition-colors flex items-center gap-2 font-bold text-xs uppercase ml-1" title="Logout">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                    Logout
                </a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="p-5 md:p-8 bg-slate-50">
            
            <!-- KPI Cards Grid (EIA Topics) -->
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-5 mb-8">
                <?php foreach ($topic_results as $topic): ?>
                <a href="inventory_list.php?topic=<?= urlencode($topic['label']) ?>&status=success" class="block <?= $topic['bg_tail'] ?> rounded-xl border shadow-sm relative overflow-hidden p-5 hover:shadow-lg hover:-translate-y-1 transition-all duration-300 group">
                    <div class="absolute top-0 left-0 w-full h-1.5 bg-<?= $topic['color_tail'] ?>"></div>
                    <div class="text-3xl font-black text-<?= $topic['color_tail'] ?> mt-2 group-hover:scale-110 transition-transform"><?= $topic['rate'] ?>%</div>
                    <div class="text-xs font-black text-gray-700 mt-2 uppercase tracking-tighter"><?= $topic['label'] ?></div>
                    <div class="text-[10px] text-gray-400 font-bold mt-1"><?= number_format($topic['success']) ?> Successful Units</div>
                </a>
                <?php endforeach; ?>
            </div>

            <!-- Middle Section: Assets by Team & BU Ranking Link -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8 items-stretch">
                <!-- Asset by Service team -->
                <div class="lg:col-span-2 border rounded-[2rem] p-8 shadow-sm flex flex-col justify-between bg-orange-50 border-orange-100">
                    <div>
                        <div class="flex justify-between items-center mb-8">
                            <h3 class="text-sm font-black text-orange-700 tracking-[0.3em] uppercase flex items-center gap-3">
                                <div class="w-3 h-3 bg-orange-500 rounded-full shadow-md"></div>
                                Asset by Service team
                            </h3>
                            <div class="text-xs font-black text-orange-700 bg-orange-100 backdrop-blur-sm px-4 py-1.5 rounded-full border border-orange-200 shadow-sm">Total: <?= number_format($current_total) ?></div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-x-10 gap-y-6">
                            <?php foreach ($serviced_data as $area): 
                                $pct = ($area['count'] / $current_total) * 100;
                                $bar_color = 'bg-slate-400';
                                if ($pct > 30) { $bar_color = 'bg-indigo-600'; }
                                elseif ($pct >= 15) { $bar_color = 'bg-blue-500'; }
                                elseif ($pct >= 5) { $bar_color = 'bg-cyan-500'; }
                            ?>
                            <a href="inventory_list.php?area=<?= urlencode($area['serviced_by']) ?>" class="group block p-4 -m-4 rounded-2xl hover:bg-white/80 hover:shadow-xl hover:shadow-orange-200/50 transition-all duration-300 hover:-translate-y-1 active:scale-95">
                                <div class="flex justify-between items-end mb-3 px-1">
                                    <div class="text-sm font-black text-slate-800 truncate group-hover:text-orange-600 transition-colors uppercase pr-2"><?= htmlspecialchars($area['serviced_by']) ?></div>
                                    <div class="text-lg font-black text-slate-900"><?= number_format($area['count']) ?></div>
                                </div>
                                <div class="w-full bg-orange-100 rounded-full h-3 overflow-hidden border border-orange-200/50 shadow-inner">
                                    <div class="<?= $bar_color ?> h-full rounded-full transition-all duration-1000 shadow-md" style="width: <?= $pct ?>%"></div>
                                </div>
                                <div class="text-xs font-black text-slate-600 uppercase tracking-widest mt-2 bg-transparent"><?= round($pct, 1) ?>% Density</div>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Full Ranking Link Card -->
                <a href="top_bu_compliance.php" class="lg:col-span-1 border rounded-[2.5rem] p-8 shadow-2xl hover:shadow-blue-200/50 hover:-translate-y-1 transition-all duration-500 group flex flex-col items-center justify-center text-center relative overflow-hidden border-white/20 min-h-[250px]" 
                   style="background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);">
                    
                    <div class="absolute -right-6 -bottom-6 w-24 h-24 bg-white/10 rounded-full blur-2xl group-hover:bg-white/20 transition-all duration-700"></div>
                    
                    <div class="w-16 h-16 bg-white/10 backdrop-blur-md rounded-2xl flex items-center justify-center mb-5 shadow-inner border border-white/20 group-hover:scale-110 transition-all duration-500 relative">
                        <svg class="w-8 h-8 text-yellow-400 drop-shadow-md" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M18 2H6c-1.1 0-2 .9-2 2v1c0 2.55 1.92 4.63 4.39 4.94A5.01 5.01 0 0011 13.9V17H8v2h8v-2h-3v-3.1c1.76-.31 3.11-1.63 3.61-3.06C19.08 9.63 21 7.55 21 5V4c0-1.1-.9-2-2-2zM6 7V4h2v3.18C6.94 6.88 6.23 6.48 6 6zm12-1c-.23.48-.94.88-2 1.18V4h2v3z"/>
                        </svg>
                    </div>

                    <h3 class="text-xl font-black text-white uppercase tracking-tighter mb-2">Full BU Ranking</h3>
                    <p class="text-[9px] font-bold text-blue-100 uppercase tracking-[0.2em] leading-relaxed max-w-[180px] opacity-70">Compare compliance scores across BU</p>
                    
                    <div class="mt-6 flex items-center gap-3 bg-white text-blue-700 px-6 py-2 rounded-xl font-black text-[10px] uppercase tracking-widest group-hover:bg-yellow-400 group-hover:text-slate-900 transition-all shadow-lg">
                        Explore
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                        </svg>
                    </div>
                </a>
            </div>

            <!-- Bottom Section (3 Columns Layout like ex_dashboard) -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                
                <!-- Overall System Health (Donut like WP Status) -->
                <div class="bg-white border rounded-xl p-6 shadow-sm flex flex-col">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-xs font-black text-gray-400 uppercase tracking-widest flex items-center gap-2">
                            <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z"></path></svg>
                            Overall Health
                        </h3>
                        <span class="text-[10px] font-black text-emerald-500 bg-emerald-50 px-2 py-0.5 rounded uppercase border border-emerald-100">Live</span>
                    </div>
                    <div class="flex flex-col items-center justify-center flex-1">
                        <div class="relative w-40 h-40 rounded-full flex items-center justify-center conic-chart shadow-xl border-4 border-white ring-1 ring-gray-100">
                            <div class="w-32 h-32 bg-white rounded-full flex flex-col items-center justify-center shadow-inner">
                                <span class="text-4xl font-black text-gray-800"><?= $overall_rate ?>%</span>
                                <span class="text-[9px] text-gray-400 font-black uppercase tracking-widest">Score</span>
                            </div>
                        </div>
                        <div class="mt-8 space-y-3 w-full">
                            <div class="flex items-center justify-between p-2 hover:bg-slate-50 rounded-lg transition-colors">
                                <div class="flex items-center gap-3"><div class="w-2.5 h-2.5 bg-green-500 rounded-full"></div><span class="text-xs font-bold text-gray-600">Compliance Pass</span></div>
                                <span class="text-xs font-black text-gray-800"><?= number_format($total_success_sum) ?></span>
                            </div>
                            <div class="flex items-center justify-between p-2 hover:bg-slate-50 rounded-lg transition-colors">
                                <div class="flex items-center gap-3"><div class="w-2.5 h-2.5 bg-gray-200 rounded-full"></div><span class="text-xs font-bold text-gray-600">Pending Actions</span></div>
                                <span class="text-xs font-black text-gray-800"><?= number_format(($current_total * 6) - $total_success_sum) ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Top Business Units (Effort by Owner equivalent) -->
                <div class="bg-white border rounded-xl p-6 shadow-sm">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-xs font-black text-gray-400 uppercase tracking-widest flex items-center gap-2">
                            <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                            Top Performers (BU)
                        </h3>
                        <span class="text-[9px] font-bold text-emerald-500 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-100 uppercase tracking-tighter">Patching Success</span>
                    </div>
                    <div class="space-y-5 mt-4">
                        <?php 
                        $bar_colors = [
                            ['from' => 'from-emerald-500', 'to' => 'to-teal-400', 'border' => 'hover:border-[#064e3b]'],
                            ['from' => 'from-blue-600', 'to' => 'to-indigo-400', 'border' => 'hover:border-[#1e3a8a]'],
                            ['from' => 'from-purple-600', 'to' => 'to-pink-400', 'border' => 'hover:border-[#581c87]'],
                            ['from' => 'from-amber-500', 'to' => 'to-orange-400', 'border' => 'hover:border-[#78350f]'],
                            ['from' => 'from-rose-600', 'to' => 'to-red-400', 'border' => 'hover:border-[#7f1d1d]']
                        ];
                        foreach ($bu_data as $index => $bu): 
                            $color = $bar_colors[$index % count($bar_colors)];
                        ?>
                        <a href="inventory_list.php?bu=<?= urlencode($bu['bu']) ?>&topic=Patching&status=success" class="flex flex-col gap-2 group block">
                            <div class="flex justify-between items-end px-1">
                                <span class="text-[11px] font-black text-gray-600 group-hover:text-blue-600 transition-colors uppercase"><?= htmlspecialchars($bu['bu']) ?></span>
                                <span class="text-[10px] font-black text-gray-900"><?= number_format($bu['success_count']) ?> <span class="text-gray-400 font-bold ml-0.5">Units</span></span>
                            </div>
                            <div class="flex-1 bg-slate-50 rounded-lg h-7 flex items-center relative overflow-hidden border border-slate-100 transition-all p-1">
                                <div class="bg-gradient-to-r <?= $color['from'] ?> <?= $color['to'] ?> h-full rounded-md z-10 transition-all duration-1000 shadow-sm relative group-hover:border-r-[6px] <?= $color['border'] ?> flex items-center px-3" 
                                     style="width: <?= max(($bu['success_count'] / $max_bu_val) * 100, 15) ?>%">
                                    <div class="absolute inset-0 bg-white/10 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                                    <span class="text-[9px] text-white font-black z-20 drop-shadow-sm whitespace-nowrap">
                                        <?= round(($bu['success_count'] / $current_total) * 100, 1) ?>%
                                    </span>
                                </div>
                            </div>
                        </a>
                        <?php endforeach; ?>
                    </div>
                    <div class="text-right text-[9px] font-black text-gray-300 mt-8 border-t pt-4 uppercase tracking-[0.2em]">Verified Performance Data</div>
                </div>

                <!-- Critical Alerts (Action Items equivalent) -->
                <div class="bg-white border rounded-xl p-6 shadow-sm">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-xs font-black text-gray-400 uppercase tracking-widest flex items-center gap-2">
                            <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                            Security Alerts
                        </h3>
                        <a href="inventory_list.php" class="text-[10px] font-black text-blue-600 hover:underline uppercase tracking-tighter">See All Matrix &rarr;</a>
                    </div>
                    <div class="space-y-4 text-sm flex flex-col">
                        <a href="inventory_list.php?topic=Patching&status=pending" class="block bg-red-50 border-l-4 border-red-500 p-4 rounded-r-lg flex items-start gap-4 text-red-800 transition-all hover:bg-red-100 hover:shadow-md group">
                            <span class="text-2xl group-hover:scale-125 transition-transform duration-300">🚨</span> 
                            <div>
                                <p class="font-black text-xs uppercase tracking-tighter">Patching Critical</p>
                                <p class="text-[11px] font-bold opacity-70 mt-0.5"><?= number_format($topic_results[2]['pending']) ?> devices need urgent updates.</p>
                            </div>
                        </a>
                        
                        <a href="inventory_list.php?topic=Antivirus&status=pending" class="block bg-orange-50 border-l-4 border-orange-400 p-4 rounded-r-lg flex items-start gap-4 text-orange-800 transition-all hover:bg-orange-100 hover:shadow-md group">
                            <span class="text-2xl group-hover:scale-125 transition-transform duration-300">🛡️</span> 
                            <div>
                                <p class="font-black text-xs uppercase tracking-tighter">Antivirus Alert</p>
                                <p class="text-[11px] font-bold opacity-70 mt-0.5"><?= number_format($topic_results[3]['pending']) ?> devices unprotected.</p>
                            </div>
                        </a>

                        <a href="inventory_list.php?topic=OS Status&status=pending" class="block bg-blue-50 border-l-4 border-blue-500 p-4 rounded-r-lg flex items-start gap-4 text-blue-800 transition-all hover:bg-blue-100 hover:shadow-md group">
                            <span class="text-2xl group-hover:scale-125 transition-transform duration-300">💻</span> 
                            <div>
                                <p class="font-black text-xs uppercase tracking-tighter">OS Lifecycle</p>
                                <p class="text-[11px] font-bold opacity-70 mt-0.5"><?= number_format($topic_results[1]['pending']) ?> devices on legacy OS.</p>
                            </div>
                        </a>
                    </div>
                </div>

            </div>
        </div>

        <!-- Master Footer -->
        <footer class="p-6 text-center bg-white border-t border-gray-100">
            <p class="text-[10px] font-black text-gray-400 uppercase tracking-[0.5em]">&copy; 2026 EIA COMPLIANCE UNIT | Prepared by Endpoint Management Team</p>
        </footer>
    </div>
</body>
</html>