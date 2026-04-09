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

$cache_file = 'bu_ranking_cache.json';
$cache_lifetime = 3600; // 60 minutes
$data_from_cache = false;

if (file_exists($cache_file) && (time() - filemtime($cache_file) < $cache_lifetime)) {
    $cache_data = json_decode(file_get_contents($cache_file), true);
    if ($cache_data) {
        extract($cache_data);
        $data_from_cache = true;
    }
}

if (!$data_from_cache) {
    $clean_query = rtrim(trim($eiaquery), ';');
    try {
        $success_keys = "('Y', 'Yes', 'Compliant', 'Success', 'Active', 'Healthy', 'Installed', 'Allowed')";
        $sql = "
            SELECT 
                `BU` as bu, 
                COUNT(*) as total_devices,
                SUM(
                    (CASE WHEN `Joined Approved Domain` IN $success_keys THEN 1 ELSE 0 END) +
                    (CASE WHEN `OS End of Support Status` IN $success_keys THEN 1 ELSE 0 END) +
                    (CASE WHEN `Patch Healthy` IN $success_keys THEN 1 ELSE 0 END) +
                    (CASE WHEN `Antivirus Compliant` IN $success_keys THEN 1 ELSE 0 END) +
                    (CASE WHEN `Firewall Compliant` IN $success_keys THEN 1 ELSE 0 END) +
                    (CASE WHEN `Standard Admin Only` IN $success_keys THEN 1 ELSE 0 END)
                ) as total_success_points
            FROM ($clean_query) as base_data
            WHERE `BU` != '' AND `BU` IS NOT NULL
            GROUP BY `BU` 
            ORDER BY (
                SUM(
                    (CASE WHEN `Joined Approved Domain` IN $success_keys THEN 1 ELSE 0 END) +
                    (CASE WHEN `OS End of Support Status` IN $success_keys THEN 1 ELSE 0 END) +
                    (CASE WHEN `Patch Healthy` IN $success_keys THEN 1 ELSE 0 END) +
                    (CASE WHEN `Antivirus Compliant` IN $success_keys THEN 1 ELSE 0 END) +
                    (CASE WHEN `Firewall Compliant` IN $success_keys THEN 1 ELSE 0 END) +
                    (CASE WHEN `Standard Admin Only` IN $success_keys THEN 1 ELSE 0 END)
                ) / (COUNT(*) * 6)
            ) DESC 
        ";
        
        // Force Clear Cache once after fix
        if (file_exists($cache_file)) { unlink($cache_file); }

        $stmt = $pdo->query($sql);
        $bu_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $week = date("W"); $year = date("Y"); $last_updated = date("d M Y, H:i");
        $cache_payload = compact('bu_data', 'week', 'year', 'last_updated');
        file_put_contents($cache_file, json_encode($cache_payload));
    } catch (\PDOException $e) {
        die("<div style='color:red;'>Database Error: " . htmlspecialchars($e->getMessage()) . "</div>");
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BU Ranking | EIA Dashboard</title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%232563eb' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><path d='M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z'></path></svg>">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@700;800;900&family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Sarabun', sans-serif; background-image: linear-gradient(120deg, #a1c4fd 0%, #c2e9fb 100%); min-height: 100vh; background-attachment: fixed; }
        .glass-card { background: rgba(255, 255, 255, 0.75); backdrop-filter: blur(24px); -webkit-backdrop-filter: blur(24px); border: 1px solid rgba(255, 255, 255, 0.8); }
        .shimmer { position: relative; overflow: hidden; }
        .shimmer::before { content: ''; position: absolute; top: 0; left: -100%; width: 50%; height: 100%; background: linear-gradient(to right, transparent, rgba(255,255,255,0.4), transparent); transform: skewX(-20deg); animation: shimmerEffect 4s infinite; }
        @keyframes shimmerEffect { 0% { left: -100%; } 20% { left: 200%; } 100% { left: 200%; } }
        .custom-scrollbar::-webkit-scrollbar { width: 6px; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(30, 64, 175, 0.2); border-radius: 10px; }
        .bar-hover:hover { border-right: 6px solid currentColor !important; }
    </style>
</head>
<body class="p-4 md:p-6 lg:p-10 flex items-start justify-center">

    <div class="max-w-5xl w-full glass-card rounded-[2.5rem] p-6 md:p-10 relative overflow-hidden shadow-2xl">
        
        <!-- Header Section -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <span class="bg-blue-600 w-2 h-2 rounded-full animate-pulse shadow-lg"></span>
                    <span class="text-[9px] font-black text-blue-600 uppercase tracking-[0.3em]">Performance Matrix</span>
                </div>
                <h1 class="text-3xl font-black text-slate-800 tracking-tighter" style="font-family: 'Montserrat';">BU Compliance Ranking</h1>
            </div>
            <a href="index.php" class="bg-blue-600 px-4 py-2 rounded-xl shadow-lg border border-blue-500 text-white font-bold flex items-center gap-2 hover:bg-blue-700 transition-all group text-[10px] uppercase tracking-widest">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                Back to Dashboard
            </a>
        </div>

        <!-- Compact Legend Bar -->
        <div class="flex flex-wrap items-center gap-2 md:gap-6 mb-8 p-4 bg-white/40 rounded-2xl border border-white/60 shadow-inner">
            <div class="flex items-center gap-2">
                <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Analysis:</span>
                <span class="text-xs font-black text-slate-700"><?= count($bu_data) ?> BUs</span>
            </div>
            <div class="w-px h-4 bg-slate-300 hidden md:block"></div>
            <div class="flex items-center gap-2">
                <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Period:</span>
                <span class="text-xs font-black text-blue-600">W<?= $week ?> / <?= $year ?></span>
            </div>
            <div class="w-px h-4 bg-slate-300 hidden md:block"></div>
            <div class="flex items-center gap-2">
                <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Standard:</span>
                <span class="text-xs font-black text-emerald-600">80%+ PASS</span>
            </div>
        </div>

        <!-- Ranking List (Compact) -->
        <div class="space-y-3 max-h-[600px] overflow-y-auto pr-2 custom-scrollbar">
            <?php 
            $colors = [
                ['bg'=>'from-emerald-500 to-teal-400', 'text'=>'text-emerald-600', 'h_bg'=>'group-hover:bg-emerald-500'],
                ['bg'=>'from-blue-600 to-indigo-500', 'text'=>'text-blue-600', 'h_bg'=>'group-hover:bg-blue-600'],
                ['bg'=>'from-purple-600 to-fuchsia-500', 'text'=>'text-purple-600', 'h_bg'=>'group-hover:bg-purple-600'],
                ['bg'=>'from-amber-500 to-orange-400', 'text'=>'text-amber-600', 'h_bg'=>'group-hover:bg-amber-500'],
                ['bg'=>'from-rose-600 to-red-500', 'text'=>'text-rose-600', 'h_bg'=>'group-hover:bg-rose-600']
            ];

            foreach ($bu_data as $index => $bu): 
                $score = round(($bu['total_success_points'] / ($bu['total_devices'] * 6)) * 100, 1);
                $color = $colors[$index % count($colors)];
                $rank = $index + 1;
                $rank_style = "bg-slate-100 text-slate-500";
                if ($rank == 1) $rank_style = "bg-yellow-400 text-white shadow-sm shadow-yellow-200";
                elseif ($rank == 2) $rank_style = "bg-slate-300 text-white";
                elseif ($rank == 3) $rank_style = "bg-orange-300 text-white";
            ?>
            <a href="inventory_list.php?bu=<?= urlencode($bu['bu']) ?>" class="block p-3.5 rounded-2xl bg-white/40 border border-white/60 hover:bg-white/80 transition-all duration-300 hover:shadow-xl group">
                <div class="flex justify-between items-center mb-2 px-1">
                    <div class="flex items-center gap-3">
                        <span class="w-7 h-7 flex items-center justify-center <?= $rank_style ?> <?= $color['h_bg'] ?> group-hover:text-white group-hover:shadow-md transition-all duration-300 rounded-lg text-[11px] font-black"><?= $rank ?></span>
                        <div>
                            <h3 class="text-[13px] font-black text-slate-800 uppercase tracking-tight group-hover:text-blue-600 transition-colors"><?= htmlspecialchars($bu['bu']) ?></h3>
                            <p class="text-[9px] font-bold text-slate-400 uppercase"><?= number_format($bu['total_devices']) ?> Units</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <span class="text-xl font-black text-slate-800"><?= $score ?><span class="text-[10px] ml-0.5 opacity-40">%</span></span>
                    </div>
                </div>
                <div class="w-full bg-slate-200/40 rounded-full h-2.5 p-0.5 border border-white shadow-inner">
                    <div class="shimmer bg-gradient-to-r <?= $color['bg'] ?> h-full rounded-full transition-all duration-1000 shadow-sm <?= $color['text'] ?> bar-hover" style="width: <?= max($score, 5) ?>%"></div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>

        <!-- Compact Footer Meta -->
        <div class="mt-8 pt-6 border-t border-white/60 text-center">
            <p class="text-[9px] font-black text-slate-400 uppercase tracking-[0.4em] mb-1">EIA Dashboard &copy; 2026 Prepared by Endpoint Management Team</p>
            <p class="text-[8px] font-black text-slate-400 uppercase tracking-widest italic opacity-50 text-shadow-sm">Last Updated: <?= $last_updated ?></p>
        </div>

    </div>

</body>
</html>