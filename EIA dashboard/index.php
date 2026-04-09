<?php
session_start();
date_default_timezone_set('Asia/Bangkok');
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// 1. Database Connection & Base Query
require_once 'config.php';
require_once 'query.php';

$cache_file = 'dashboard_cache.json';
$cache_lifetime = 10800; // 180 minutes
$lock_file = 'dashboard.lock';
$data_from_cache = false;
$needs_update = false;

// 1. Check if cache exists and is fresh
if (file_exists($cache_file)) {
    $cache_data = json_decode(file_get_contents($cache_file), true);
    if ($cache_data) {
        extract($cache_data);
        $data_from_cache = true;
    }
}

// 2. Trigger background update if expired (but only if it exists)
if (!file_exists($cache_file)) {
    $needs_update = true;
} elseif (time() - filemtime($cache_file) > $cache_lifetime) {
    // Check for stale lock (older than 5 mins)
    if (file_exists($lock_file) && (time() - filemtime($lock_file) > 300)) {
        @unlink($lock_file);
    }

    if (!file_exists($lock_file)) {
        $php_path = 'C:\xampp\php\php.exe';
        $worker_path = __DIR__ . DIRECTORY_SEPARATOR . 'cache_worker.php';
        $cmd = "start /B $php_path \"$worker_path\" --type=dashboard > NUL 2>&1";
        exec($cmd);
    }
}

if ($needs_update || !$data_from_cache) {
    if (!$data_from_cache) {
        // FIRST RUN SAFETY: Avoid hanging index.php with heavy query
        $current_total = 1; $g_full = 0; $g_minor = 0; $g_at_risk = 0; $g_critical = 0;
        $p_full = 0; $p_minor = 0; $p_at_risk = 0; $p_critical = 0;
        $s1 = 25; $s2 = 50; $s3 = 75; // Dummy values for chart
        $topic_results = []; 
        $total_success_sum = 0; $overall_rate = 0; $serviced_data = []; $bu_data = []; 
        $max_bu_val = 1; $last_updated = "Preparing Data..."; $s_ended = 0; $live_visitors = 0; $total_visitors = 0;

        // Trigger worker if not already running
        if (!file_exists($lock_file)) {
            $php_path = 'C:\xampp\php\php.exe';
            $worker_path = __DIR__ . DIRECTORY_SEPARATOR . 'cache_worker.php';
            $cmd = "start /B $php_path \"$worker_path\" --type=dashboard > NUL 2>&1";
            exec($cmd);
        }
    } else {
        // We have cache data, but it's expired. The worker was already triggered in the elseif block above.
        // So we do nothing here and just use the $cache_data we extracted earlier.
    }
} else {
    // Cache is fresh, $cache_data already extracted.
}

$week = date("W"); $year = date("Y");
require_once 'counter_engine.php';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EIA Compliance Dashboard — Executive Summary</title>
    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%232563eb' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><path d='M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z'></path></svg>">
    <!-- Tailwind CSS (Production Ready) -->
    <link href="dist/output.css" rel="stylesheet">
    <style>
        body { 
            font-family: 'Sarabun', sans-serif; 
            background-image: linear-gradient(120deg, #a1c4fd 0%, #c2e9fb 100%);
            min-height: 100vh;
            background-attachment: fixed;
        }
        .conic-chart { background: conic-gradient(#10b981 0% <?= $s1 ?>%, #3b82f6 <?= $s1 ?>% <?= $s2 ?>%, #f59e0b <?= $s2 ?>% <?= $s3 ?>%, #f43f5e <?= $s3 ?>% 100%); }

        /* Page Loader Styles */
        #page-loader {
            position: fixed;
            inset: 0;
            z-index: 9999;
            background: #f1f5f9;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            transition: opacity 0.6s ease-out, visibility 0.6s;
        }
        #page-loader.hidden-loader {
            opacity: 0;
            visibility: hidden;
        }
        .loader-logo-container {
            width: 120px;
            height: 120px;
            background: white;
            border-radius: 2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            box-shadow: 0 20px 50px rgba(0,0,0,0.1);
            animation: pulse-logo 2s infinite ease-in-out;
        }
        @keyframes pulse-logo {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.1); opacity: 0.8; }
        }
        </style>
</head>
<body class="p-4 md:p-8">

    <!-- Page Loader (Style 01) -->
    <div id="page-loader">
        <div class="loader-logo-container">
            <img src="Logo RIS.png" alt="RIS Logo" class="w-full h-full object-contain">
        </div>
        <div class="mt-8 flex flex-col items-center">
            <p class="text-xs font-black text-blue-600 uppercase tracking-[0.3em] animate-pulse">Initializing Dashboard</p>
            <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mt-2">Loading Asset Intelligence...</p>
        </div>
    </div>

    <div class="max-w-7xl mx-auto bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden relative">
        
        <!-- Header Section -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center p-5 border-b border-gray-100 bg-white/50 backdrop-blur-md sticky top-0 z-[100] relative overflow-hidden">
            <!-- Background Image -->
            <div class="img-blur">
                <img src="Image/bg-computer.jpg" alt="BG" class="w-full h-full object-cover">
            </div>

            <div class="flex items-center gap-4 relative z-10">
                <a href="index.php" title="Dashboard" class="w-14 h-14 bg-white rounded-xl flex items-center justify-center shadow-lg hover:shadow-xl transition-all overflow-hidden border border-gray-100 p-1">
                    <img src="Logo RIS.png" alt="RIS Logo" class="w-full h-full object-contain">
                </a>
                <div>
                    <h1 class="text-3xl lg:text-4xl font-black text-slate-800 tracking-tight" style="font-family: 'Montserrat', sans-serif;">
                        <a href="index.php" class="hover:text-indigo-600 transition-colors">EIA Dashboard</a>
                    </h1>
                    <p class="text-[11px] lg:text-xs text-gray-500 font-bold uppercase tracking-widest mt-1">Live Monitoring From KACE System</p>
                </div>
            </div>
            <div class="flex flex-wrap items-center md:items-start gap-3 text-sm w-full md:w-auto relative z-10">
                <div class="hidden lg:flex flex-col items-end mr-2">
                    <span class="text-gray-400 font-bold uppercase text-[9px] tracking-widest leading-none">Last Updated</span>
                    <span class="text-slate-500 font-black text-[10px] mt-1 italic"><?= $last_updated ?></span>
                </div>
                <span class="bg-blue-50 text-blue-600 px-4 py-1.5 rounded-full border border-blue-100 font-bold whitespace-nowrap flex items-center gap-2 text-xs shadow-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                    Week <?= $week ?> / <?= $year ?>
                </span>
                
                <a href="inventory_list.php" class="bg-gray-100 px-4 py-2 rounded-lg border border-slate-200 hover:bg-gray-200 transition-colors flex items-center gap-2 font-bold text-xs text-gray-600 uppercase shadow-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
                    Full List
                </a>
                <a href="https://rscsassetsrv02.central.co.th/cgitasset/login.php" target="_blank" class="bg-white px-4 py-2 rounded-lg border border-blue-100 hover:bg-blue-50 hover:border-blue-200 text-blue-600 transition-colors flex items-center gap-2 font-bold text-xs uppercase shadow-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    CG IT Asset
                </a>
                <div class="flex flex-col items-center">
                    <a href="logout.php" class="bg-rose-50 px-4 py-2 rounded-lg border border-rose-100 hover:bg-rose-100 text-rose-600 transition-colors flex items-center gap-2 font-bold text-xs uppercase shadow-sm" title="Logout">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                        Logout
                    </a>
                    <span class="text-[9px] font-black text-slate-400 uppercase tracking-tighter mt-1 whitespace-nowrap">
                        Hello K. <?= htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'User') ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="p-5 md:p-8 bg-slate-50">
            
            <!-- KPI Cards Grid (EIA Topics) -->
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-5 mb-8">
                <?php foreach ($topic_results as $topic): ?>
                <a href="inventory_list.php?topic=<?= urlencode($topic['label']) ?>&status=success" class="block <?= $topic['bg_tail'] ?> rounded-xl border border-white/40 shadow-sm relative overflow-hidden py-7 px-4 hover:shadow-lg hover:-translate-y-1 transition-all duration-300 group flex flex-col items-center justify-center text-center">
                    <div class="absolute top-0 left-0 w-full h-1.5 bg-<?= $topic['color_tail'] ?>"></div>
                    <div class="text-4xl font-black text-<?= $topic['color_tail'] ?> mt-1 group-hover:scale-110 transition-transform"><?= $topic['rate'] ?>%</div>
                    <div class="text-[13px] font-black text-slate-700 mt-2 uppercase tracking-tighter leading-tight"><?= $topic['label'] ?></div>
                    <div class="text-[10px] text-slate-400 font-bold mt-1.5"><?= number_format($topic['success']) ?> Units</div>
                    <?php if (!empty($topic['note'])): ?>
                        <div class="text-[8px] text-rose-400 font-bold mt-2 leading-none italic uppercase"><?= $topic['note'] ?></div>
                    <?php endif; ?>
                </a>
                <?php endforeach; ?>
            </div>

            <!-- Middle Section: Assets by Team & BU Ranking Link -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8 items-stretch">
                <!-- Asset by Service team -->
                <div class="lg:col-span-2 border border-blue-100 rounded-xl p-6 shadow-sm flex flex-col justify-between bg-blue-50">
                    <div>
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-sm font-black text-blue-700 tracking-widest uppercase flex items-center gap-3">
                                <div class="w-3 h-3 bg-blue-500 rounded-full shadow-md"></div>
                                Asset by Service team
                            </h3>
                            <div class="text-xs font-black text-blue-700 bg-blue-100 backdrop-blur-sm px-4 py-1.5 rounded-full border border-blue-200 shadow-sm">Total: <?= number_format($current_total) ?></div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-x-8 gap-y-4">
                            <?php foreach ($serviced_data as $area): 
                                $pct = ($area['count'] / $current_total) * 100;
                                $bar_color = 'bg-slate-400';
                                if ($pct > 30) { $bar_color = 'bg-indigo-600'; }
                                elseif ($pct >= 15) { $bar_color = 'bg-blue-500'; }
                                elseif ($pct >= 5) { $bar_color = 'bg-cyan-500'; }
                            ?>
                            <a href="inventory_list.php?area=<?= urlencode($area['serviced_by']) ?>" class="group block p-3 -m-3 rounded-2xl hover:bg-white/80 hover:shadow-xl hover:shadow-blue-200/50 transition-all duration-300 hover:-translate-y-1 active:scale-95">
                                <div class="flex justify-between items-end mb-2 px-1">
                                    <div class="text-sm font-black text-slate-800 truncate group-hover:text-blue-600 transition-colors uppercase pr-2"><?= htmlspecialchars($area['serviced_by']) ?></div>
                                    <div class="text-base font-black text-slate-900"><?= number_format($area['count']) ?></div>
                                </div>
                                <div class="w-full bg-blue-100 rounded-full h-2.5 overflow-hidden border border-blue-200/50 shadow-inner">
                                    <div class="<?= $bar_color ?> h-full rounded-full transition-all duration-1000 shadow-md" style="width: <?= $pct ?>%"></div>
                                </div>
                                <div class="text-xs font-black text-slate-600 uppercase tracking-widest mt-1.5 bg-transparent"><?= round($pct, 1) ?>% Density</div>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Full Ranking Link Card -->
                <a href="top_bu_compliance.php" class="lg:col-span-1 border border-white/40 rounded-xl p-6 shadow-2xl hover:shadow-blue-200/50 hover:-translate-y-1 transition-all duration-500 group flex flex-col items-center justify-center text-center relative overflow-hidden border-white/20 min-h-[220px]" 
                   style="background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);">
                    
                    <div class="absolute -right-6 -bottom-6 w-24 h-24 bg-white/10 rounded-full blur-2xl group-hover:bg-white/20 transition-all duration-700"></div>
                    
                    <div class="w-14 h-14 bg-white/10 backdrop-blur-md rounded-2xl flex items-center justify-center mb-3 shadow-inner border border-white/20 group-hover:scale-110 transition-all duration-500 relative">
                        <svg class="w-7 h-7 text-yellow-400 drop-shadow-md" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M18 2H6c-1.1 0-2 .9-2 2v1c0 2.55 1.92 4.63 4.39 4.94A5.01 5.01 0 0011 13.9V17H8v2h8v-2h-3v-3.1c1.76-.31 3.11-1.63 3.61-3.06C19.08 9.63 21 7.55 21 5V4c0-1.1-.9-2-2-2zM6 7V4h2v3.18C6.94 6.88 6.23 6.48 6 6zm12-1c-.23.48-.94.88-2 1.18V4h2v3z"/>
                        </svg>
                    </div>

                    <h3 class="text-lg font-black text-white uppercase tracking-tighter mb-1.5">Full Team & BU Ranking</h3>
                    <p class="text-xs font-bold text-blue-100 uppercase tracking-widest leading-relaxed max-w-[180px] opacity-70">Compare compliance scores across Team & BU</p>
                    
                    <div class="mt-4 flex items-center gap-3 bg-white text-blue-700 px-5 py-2 rounded-xl font-black text-xs uppercase tracking-widest group-hover:bg-yellow-400 group-hover:text-slate-900 transition-all shadow-lg">
                        Explore
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                        </svg>
                    </div>
                </a>
            </div>

            <!-- Bottom Section -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                
                <!-- Overall System Health (4-Level Grading) -->
                <div class="bg-white/60 border border-white/40 rounded-xl p-6 shadow-sm flex flex-col relative overflow-hidden backdrop-blur-md">
                    <!-- Background Image for Card -->
                    <div class="img-blur">
                        <img src="Image/bg-computer-5.jpg" alt="BG" class="w-full h-full object-cover">
                    </div>
                    
                    <div class="flex justify-between items-center mb-6 relative z-10">
                        <h3 class="text-xs font-black text-gray-400 uppercase tracking-widest flex items-center gap-2">
                            <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z"></path></svg>
                            Overall Health Matrix
                        </h3>
                        <span class="text-xs font-black text-emerald-500 bg-emerald-50 px-2 py-0.5 rounded uppercase border border-emerald-100 shadow-sm"><span class="w-1.5 h-1.5 bg-emerald-500 rounded-full inline-block mr-1 animate-pulse"></span>Live</span>
                    </div>
                    <div class="flex flex-col items-center justify-center flex-1 relative z-10">
                        <div class="relative w-44 h-44 rounded-full flex items-center justify-center conic-chart shadow-xl border-4 border-white ring-1 ring-gray-100 transition-transform duration-500 hover:scale-105 group">
                            <div class="w-32 h-32 bg-white rounded-full flex flex-col items-center justify-center shadow-inner relative z-10">
                                <span class="text-3xl font-black text-gray-800 drop-shadow-sm"><?= number_format($current_total) ?></span>
                                <span class="text-xs text-gray-400 font-black uppercase tracking-widest">Assets</span>
                            </div>
                        </div>
                        <div class="mt-8 space-y-2.5 w-full">
                            <!-- Full Compliant -->
                            <a href="inventory_list.php?score=100" class="flex items-center justify-between p-2.5 hover:bg-emerald-50 border border-transparent hover:border-emerald-100 rounded-xl transition-all group">
                                <div class="flex items-center gap-3">
                                    <div class="w-2.5 h-2.5 bg-emerald-500 rounded-full shadow-sm"></div>
                                    <span class="text-xs font-black text-gray-600 group-hover:text-emerald-700 uppercase tracking-tighter">Full Compliant (100%)</span>
                                </div>
                                <div class="text-right">
                                    <span class="text-xs font-black text-gray-800"><?= number_format($g_full) ?></span>
                                    <span class="text-xs font-bold text-gray-400 ml-1"><?= round($p_full, 1) ?>%</span>
                                </div>
                            </a>
                            <!-- Minor Issue -->
                            <a href="inventory_list.php?score=80-99" class="flex items-center justify-between p-2.5 hover:bg-blue-50 border border-transparent hover:border-blue-100 rounded-xl transition-all group">
                                <div class="flex items-center gap-3">
                                    <div class="w-2.5 h-2.5 bg-blue-500 rounded-full shadow-sm"></div>
                                    <span class="text-xs font-black text-gray-600 group-hover:text-blue-700 uppercase tracking-tighter">Minor Issue (80-99%)</span>
                                </div>
                                <div class="text-right">
                                    <span class="text-xs font-black text-gray-800"><?= number_format($g_minor) ?></span>
                                    <span class="text-xs font-bold text-gray-400 ml-1"><?= round($p_minor, 1) ?>%</span>
                                </div>
                            </a>
                            <!-- At Risk -->
                            <a href="inventory_list.php?score=50-79" class="flex items-center justify-between p-2.5 hover:bg-amber-50 border border-transparent hover:border-amber-100 rounded-xl transition-all group">
                                <div class="flex items-center gap-3">
                                    <div class="w-2.5 h-2.5 bg-amber-500 rounded-full shadow-sm"></div>
                                    <span class="text-xs font-black text-gray-600 group-hover:text-amber-700 uppercase tracking-tighter">At Risk (50-79%)</span>
                                </div>
                                <div class="text-right">
                                    <span class="text-xs font-black text-gray-800"><?= number_format($g_at_risk) ?></span>
                                    <span class="text-xs font-bold text-gray-400 ml-1"><?= round($p_at_risk, 1) ?>%</span>
                                </div>
                            </a>
                            <!-- Critical -->
                            <a href="inventory_list.php?score=<50" class="flex items-center justify-between p-2.5 hover:bg-rose-50 border border-transparent hover:border-rose-100 rounded-xl transition-all group">
                                <div class="flex items-center gap-3">
                                    <div class="w-2.5 h-2.5 bg-rose-500 rounded-full shadow-sm"></div>
                                    <span class="text-xs font-black text-gray-600 group-hover:text-rose-700 uppercase tracking-tighter">Critical (<50%)</span>
                                </div>
                                <div class="text-right">
                                    <span class="text-xs font-black text-gray-800"><?= number_format($g_critical) ?></span>
                                    <span class="text-xs font-bold text-gray-400 ml-1"><?= round($p_critical, 1) ?>%</span>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Top Business Units -->
                <div class="bg-white border border-slate-100 rounded-xl p-6 shadow-sm">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-xs font-black text-gray-400 uppercase tracking-widest flex items-center gap-2">
                            <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                            Top Performers (BU)
                        </h3>
                        <span class="text-xs font-bold text-emerald-500 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-100 uppercase tracking-tighter shadow-sm">Patching Success</span>
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
                                <span class="text-xs font-black text-gray-600 group-hover:text-blue-600 transition-colors uppercase"><?= htmlspecialchars($bu['bu']) ?></span>
                                <span class="text-xs font-black text-gray-900"><?= number_format($bu['success_count']) ?> <span class="text-gray-400 font-bold ml-0.5">Units</span></span>
                            </div>
                            <div class="flex-1 bg-slate-50 rounded-lg h-7 flex items-center relative overflow-hidden border border-slate-100 transition-all p-1">
                                <div class="bg-gradient-to-r <?= $color['from'] ?> <?= $color['to'] ?> h-full rounded-md z-10 transition-all duration-1000 shadow-sm relative group-hover:border-r-[6px] <?= $color['border'] ?> flex items-center px-3" 
                                     style="width: <?= max(($bu['success_count'] / $max_bu_val) * 100, 15) ?>%">
                                    <div class="absolute inset-0 bg-white/10 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                                    <span class="text-xs text-white font-black z-20 drop-shadow-sm whitespace-nowrap">
                                        <?= round(($bu['success_count'] / $current_total) * 100, 1) ?>%
                                    </span>
                                </div>
                            </div>
                        </a>
                        <?php endforeach; ?>
                    </div>
                    <div class="text-right text-xs font-black text-gray-300 mt-8 border-t pt-4 uppercase tracking-widest">Live Database</div>
                </div>

                <!-- Critical Alerts -->
                <div class="bg-white border border-slate-100 rounded-xl p-6 shadow-sm">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-xs font-black text-gray-400 uppercase tracking-widest flex items-center gap-2">
                            <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                            Security Alerts
                        </h3>
                        <a href="inventory_list.php" class="text-xs font-black text-blue-600 hover:underline uppercase tracking-tighter">See All Matrix &rarr;</a>
                    </div>
                    <div class="space-y-4 text-sm flex flex-col">
                        <a href="inventory_list.php?topic=Patching&status=pending" class="block bg-red-50 border-l-4 border-red-500 p-4 rounded-r-lg flex items-start gap-4 text-red-800 transition-all hover:bg-red-100 hover:shadow-md group">
                            <span class="text-2xl group-hover:scale-125 transition-transform duration-300">🚨</span> 
                            <div>
                                <p class="font-black text-xs uppercase tracking-tighter">Patching Critical</p>
                                <p class="text-xs font-bold opacity-70 mt-0.5"><?= number_format($topic_results[2]['pending'] ?? 0) ?> devices need urgent updates.</p>
                            </div>
                        </a>
                        
                        <a href="inventory_list.php?topic=Antivirus&status=pending" class="block bg-orange-50 border-l-4 border-orange-400 p-4 rounded-r-lg flex items-start gap-4 text-orange-800 transition-all hover:bg-orange-100 hover:shadow-md group">
                            <span class="text-2xl group-hover:scale-125 transition-transform duration-300">🛡️</span> 
                            <div>
                                <p class="font-black text-xs uppercase tracking-tighter">Antivirus Alert</p>
                                <p class="text-xs font-bold opacity-70 mt-0.5"><?= number_format($topic_results[3]['pending'] ?? 0) ?> devices unprotected.</p>
                            </div>
                        </a>

                        <a href="inventory_list.php?topic=OS Status&status=pending" class="block bg-blue-50 border-l-4 border-blue-500 p-4 rounded-r-lg flex items-start gap-4 text-blue-800 transition-all hover:bg-blue-100 hover:shadow-md group">
                            <span class="text-2xl group-hover:scale-125 transition-transform duration-300">💻</span> 
                            <div>
                                <p class="font-black text-xs uppercase tracking-tighter">OS Lifecycle</p>
                                <p class="text-xs font-bold opacity-70 mt-0.5"><?= number_format($topic_results[1]['pending'] ?? 0) ?> devices on legacy OS.</p>
                            </div>
                        </a>

                        <!-- NEW: Insight Report Links -->
                        <div class="pt-4 mt-2 border-t border-slate-100 space-y-3">
                            <p class="text-xs font-black text-slate-400 uppercase tracking-widest px-1 mb-2">Advanced Insights</p>
                            
                            <a href="admin_audit.php" class="flex items-center justify-between p-3 bg-white border border-slate-100 rounded-xl hover:border-rose-300 hover:bg-rose-50 transition-all group shadow-sm">
                                <div class="flex items-center gap-3">
                                    <span class="text-xl group-hover:rotate-12 transition-transform">🔑</span>
                                    <span class="text-xs font-black text-slate-600 group-hover:text-rose-700 uppercase tracking-tighter">Admin Rights Audit</span>
                                </div>
                                <svg class="w-3 h-3 text-slate-300 group-hover:text-rose-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M9 5l7 7-7 7"></path></svg>
                            </a>

                            <a href="patch_insight.php" class="flex items-center justify-between p-3 bg-white border border-slate-100 rounded-xl hover:border-amber-300 hover:bg-amber-50 transition-all group shadow-sm">
                                <div class="flex items-center gap-3">
                                    <span class="text-xl group-hover:rotate-12 transition-transform">📊</span>
                                    <span class="text-xs font-black text-slate-600 group-hover:text-amber-700 uppercase tracking-tighter">Patch Detail Insight</span>
                                </div>
                                <svg class="w-3 h-3 text-slate-300 group-hover:text-amber-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M9 5l7 7-7 7"></path></svg>
                            </a>

                            <a href="inactive_assets.php" class="flex items-center justify-between p-3 bg-white border border-slate-100 rounded-xl hover:border-slate-400 hover:bg-slate-50 transition-all group shadow-sm">
                                <div class="flex items-center gap-3">
                                    <span class="text-xl group-hover:rotate-12 transition-transform">💤</span>
                                    <span class="text-xs font-black text-slate-600 group-hover:text-slate-800 uppercase tracking-tighter">Inactive Assets Cleanup</span>
                                </div>
                                <svg class="w-3 h-3 text-slate-300 group-hover:text-slate-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M9 5l7 7-7 7"></path></svg>
                            </a>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- Master Footer -->
        <footer class="p-6 text-center bg-white border-t border-gray-100 flex flex-col md:flex-row items-center justify-center gap-4">
            <p class="text-xs font-black text-gray-400 uppercase tracking-widest">EIA Dashboard &copy; 2026 Prepared by Endpoint Management Team</p>
            
            <!-- Style 01: The Pulse Badge (Integrated) -->
            <div class="flex items-center gap-2.5 bg-slate-50 px-3 py-1.5 rounded-full border border-slate-100 shadow-inner">
                <div class="relative flex items-center justify-center">
                    <div class="w-1.5 h-1.5 bg-emerald-500 rounded-full"></div>
                    <div class="absolute w-1.5 h-1.5 bg-emerald-500 rounded-full animate-ping opacity-75"></div>
                </div>
                <div class="flex items-center gap-2 text-xs font-black uppercase tracking-tighter">
                    <span class="text-emerald-700">Live: <span class="counter" data-target="<?= $live_visitors ?>">0</span></span>
                    <div class="w-px h-2.5 bg-slate-200"></div>
                    <span class="text-slate-500">Total: <span class="counter" data-target="<?= $total_visitors ?>">0</span></span>
                </div>
            </div>
        </footer>
    </div>

    <!-- JavaScript for Counter Animation -->
    <script>
        function animateValue(obj, start, end, duration) {
            let startTimestamp = null;
            const step = (timestamp) => {
                if (!startTimestamp) startTimestamp = timestamp;
                const progress = Math.min((timestamp - startTimestamp) / duration, 1);
                obj.innerHTML = Math.floor(progress * (end - start) + start).toLocaleString();
                if (progress < 1) {
                    window.requestAnimationFrame(step);
                }
            };
            window.requestAnimationFrame(step);
        }

        document.addEventListener('DOMContentLoaded', () => {
            const counters = document.querySelectorAll('.counter');
            const loader = document.getElementById('page-loader');

            const hideLoader = () => {
                if (loader && !loader.classList.contains('hidden-loader')) {
                    loader.classList.add('hidden-loader');
                    setTimeout(() => { loader.style.display = 'none'; }, 600);
                }
            };
            
            // Hide Loader once page is fully loaded
            window.addEventListener('load', hideLoader);

            // FAIL-SAFE: Hide loader anyway after 3 seconds (3000ms)
            setTimeout(hideLoader, 3000);

            // Auto-refresh logic when data is preparing
            <?php if ($last_updated === "Preparing Data..."): ?>
            console.log("Data is preparing... setting auto-reload in 10s");
            setTimeout(function() {
                window.location.reload();
            }, 10000);
            <?php endif; ?>

            // Small delay to ensure smooth start after page load
            setTimeout(() => {
                counters.forEach(counter => {
                    const target = parseInt(counter.getAttribute('data-target'));
                    animateValue(counter, 0, target, 2000); // 2 seconds animation
                });
            }, 300);
        });
    </script>
</body>
</html>

