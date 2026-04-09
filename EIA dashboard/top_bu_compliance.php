<?php
session_start();
date_default_timezone_set('Asia/Bangkok');
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

require_once 'config.php';
require_once 'query.php';

// --- BACKGROUND CACHE LOGIC ---
$cache_file = 'bu_ranking_cache.json';
$cache_lifetime = 10800; // 180 minutes
$lock_file = 'bu_ranking.lock';
$needs_update = false;

if (!file_exists($cache_file)) {
    $needs_update = true;
} elseif (time() - filemtime($cache_file) > $cache_lifetime) {
    // Expired but exists: Trigger background worker
    if (file_exists($lock_file) && (time() - filemtime($lock_file) > 300)) {
        @unlink($lock_file);
    }

    if (!file_exists($lock_file)) {
        $php_path = 'C:\xampp\php\php.exe';
        $worker_path = __DIR__ . DIRECTORY_SEPARATOR . 'cache_worker.php';
        $cmd = "start /B $php_path \"$worker_path\" --type=bu_ranking > NUL 2>&1";
        exec($cmd);
    }
}

if ($needs_update) {
    if (!file_exists($cache_file)) {
        // FIRST RUN SAFETY: Show 'Preparing Data' and trigger worker
        $bu_data = []; 
        $team_ranking = [];
        $last_updated = "Preparing Data...";
        
        if (!file_exists($lock_file)) {
            $php_path = 'C:\xampp\php\php.exe';
            $worker_path = __DIR__ . DIRECTORY_SEPARATOR . 'cache_worker.php';
            $cmd = "start /B $php_path \"$worker_path\" --type=bu_ranking > NUL 2>&1";
            exec($cmd);
        }
    } else {
        // Expired cache, load what we have and let the worker run in background
        $cache_data = json_decode(file_get_contents($cache_file), true);
        $bu_data = $cache_data['ranking'] ?? [];
        $team_ranking = $cache_data['team_ranking'] ?? [];
        $last_updated = $cache_data['last_updated'] ?? 'N/A';
    }
} else {
    // NORMAL LOAD: Load from cache
    $cache_data = json_decode(file_get_contents($cache_file), true);
    $bu_data = $cache_data['ranking'] ?? [];
    $team_ranking = $cache_data['team_ranking'] ?? [];
    $last_updated = $cache_data['last_updated'] ?? 'N/A';
}


$week = date("W");
$year = date("Y");
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BU Ranking | EIA Dashboard</title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%232563eb' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><path d='M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z'></path></svg>">
    <!-- Tailwind CSS (Production Ready) -->
    <link href="dist/output.css" rel="stylesheet">
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
    <script>
        // Auto-refresh logic when data is preparing
        <?php if ($last_updated === "Preparing Data..."): ?>
        console.log("Data is preparing... setting auto-reload in 10s");
        setTimeout(function() {
            window.location.reload();
        }, 10000);
        <?php endif; ?>
    </script>
</head>
<body class="p-4 md:p-6 lg:p-10 flex flex-col items-center">

    <div class="max-w-5xl w-full glass-card rounded-3xl p-6 md:p-10 relative overflow-hidden shadow-2xl">
        
        <!-- Header Section -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <span class="bg-blue-600 w-2 h-2 rounded-full animate-pulse shadow-lg"></span>
                    <span class="text-xs font-black text-blue-600 uppercase tracking-widest">Performance Matrix</span>
                </div>
                <h1 class="text-3xl lg:text-4xl font-black text-slate-800 tracking-tight" style="font-family: 'Montserrat', sans-serif;">Team & BU Ranking</h1>
            </div>
            <div class="flex items-center gap-3">
                <div class="text-right hidden md:block">
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest leading-none">Last Updated</p>
                    <p class="text-xs font-black text-slate-500 italic mt-1"><?= $last_updated ?></p>
                </div>
                <a href="index.php" class="bg-blue-600 px-4 py-2.5 rounded-xl shadow-lg border border-blue-500 text-white font-bold flex items-center gap-2 hover:bg-blue-700 transition-all group text-xs uppercase tracking-widest active:scale-95">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                    Back to Dashboard
                </a>
            </div>
        </div>

        <!-- Compact Legend Bar -->
        <div class="flex flex-wrap items-center gap-2 md:gap-6 mb-8 p-4 bg-white/40 rounded-2xl border border-white/60 shadow-inner">
            <div class="flex items-center gap-2">
                <span class="text-xs font-black text-slate-400 uppercase tracking-widest">Analysis:</span>
                <span class="text-xs font-black text-slate-700"><?= count($bu_data) ?> BUs</span>
            </div>
            <div class="w-px h-4 bg-slate-300 hidden md:block"></div>
            <div class="flex items-center gap-2">
                <span class="text-xs font-black text-slate-400 uppercase tracking-widest">Period:</span>
                <span class="text-xs font-black text-blue-600">W<?= $week ?> / <?= $year ?></span>
            </div>
            <div class="w-px h-4 bg-slate-300 hidden md:block"></div>
            <div class="flex items-center gap-2">
                <span class="text-xs font-black text-slate-400 uppercase tracking-widest">Standard:</span>
                <span class="text-xs font-black text-emerald-600">80%+ PASS</span>
            </div>
        </div>

        <!-- Service Team Performance Podium (Enhanced & Vibrant) -->
        <?php if (!empty($team_ranking)): ?>
        <div class="mb-12 animate-in fade-in slide-in-from-top-6 duration-1000">
            <div class="flex items-center justify-between mb-6 px-2">
                <h3 class="text-sm font-black text-slate-500 uppercase tracking-widest flex items-center gap-2">
                    <div class="w-2 h-5 bg-blue-600 rounded-full"></div>
                    Service Excellence Leaderboard
                </h3>
                <span class="text-[10px] font-black text-blue-600/60 uppercase tracking-widest bg-blue-50 px-3 py-1 rounded-full border border-blue-100">Top Performers Only</span>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 items-end">
                <?php 
                // Re-sort to show 2nd, 1st, 3rd on Desktop if possible, but keep it simple for now
                $podium_slice = array_slice($team_ranking, 0, 3);
                
                // Sort for Podium Display: [2, 1, 3]
                $display_order = [];
                if (count($podium_slice) >= 2) $display_order[] = $podium_slice[1]; // 2nd
                if (count($podium_slice) >= 1) $display_order[] = $podium_slice[0]; // 1st
                if (count($podium_slice) >= 3) $display_order[] = $podium_slice[2]; // 3rd
                
                foreach ($display_order as $team): 
                    $score = round(($team['total_success_points'] / ($team['total_devices'] * 6)) * 100, 1);
                    $is_gold = ($team['team'] === $podium_slice[0]['team']);
                    $is_silver = (isset($podium_slice[1]) && $team['team'] === $podium_slice[1]['team']);
                    
                    if ($is_gold) {
                        $s = ['bg' => 'bg-gradient-to-br from-yellow-400/20 to-amber-500/10', 'border' => 'border-yellow-400/50', 'text' => 'text-yellow-700', 'medal' => '🥇', 'label' => 'Gold Champion', 'shadow' => 'shadow-yellow-200', 'height' => 'py-10 md:scale-110 z-10'];
                    } elseif ($is_silver) {
                        $s = ['bg' => 'bg-gradient-to-br from-slate-200 to-slate-300/30', 'border' => 'border-slate-300', 'text' => 'text-slate-600', 'medal' => '🥈', 'label' => 'Silver Runner-up', 'shadow' => 'shadow-slate-200', 'height' => 'py-7'];
                    } else {
                        $s = ['bg' => 'bg-gradient-to-br from-orange-400/20 to-rose-500/10', 'border' => 'border-orange-300/50', 'text' => 'text-orange-700', 'medal' => '🥉', 'label' => 'Bronze Third', 'shadow' => 'shadow-orange-200', 'height' => 'py-7'];
                    }
                ?>
                <div class="glass-card rounded-3xl <?= $s['bg'] ?> border <?= $s['border'] ?> <?= $s['shadow'] ?> shadow-xl flex flex-col items-center text-center relative overflow-hidden group hover:-translate-y-2 transition-all duration-500 <?= $s['height'] ?>">
                    <div class="absolute -right-4 -top-4 opacity-10 text-7xl group-hover:rotate-12 transition-transform duration-700"><?= $s['medal'] ?></div>
                    
                    <div class="w-12 h-12 bg-white/80 rounded-2xl flex items-center justify-center shadow-md mb-4 group-hover:scale-110 transition-transform">
                        <span class="text-2xl"><?= $s['medal'] ?></span>
                    </div>

                    <span class="text-[10px] font-black <?= $s['text'] ?> uppercase tracking-[0.2em] mb-1"><?= $s['label'] ?></span>
                    <h4 class="text-2xl font-black text-slate-800 mb-1 uppercase tracking-tight"><?= htmlspecialchars($team['team']) ?></h4>
                    
                    <div class="mt-2 mb-4">
                        <span class="text-4xl font-black <?= $s['text'] ?> drop-shadow-sm"><?= $score ?>%</span>
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Compliance Rate</p>
                    </div>

                    <div class="w-full px-8">
                        <div class="w-full bg-white/40 rounded-full h-2 overflow-hidden shadow-inner p-0.5 border border-white/50">
                            <div class="h-full rounded-full <?= $is_gold ? 'bg-yellow-500 shimmer' : ($is_silver ? 'bg-slate-400' : 'bg-orange-500') ?> transition-all duration-1000" style="width: <?= $score ?>%"></div>
                        </div>
                        <p class="text-[10px] font-bold text-slate-500 mt-3 uppercase tracking-tighter"><?= number_format($team['total_devices']) ?> Units Under Management</p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- BU Ranking List (Compact) -->
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
                $total_p = isset($bu['total_success_points']) ? $bu['total_success_points'] : 0;
                $total_d = isset($bu['total_devices']) ? $bu['total_devices'] : 1;
                $score = round(($total_p / ($total_d * 6)) * 100, 1);
                $color = $colors[$index % count($colors)];
                $rank = $index + 1;
                $rank_style = "bg-slate-100 text-slate-500";
                if ($rank == 1) $rank_style = "bg-yellow-400 text-white shadow-sm shadow-yellow-200";
                elseif ($rank == 2) $rank_style = "bg-slate-300 text-white";
                elseif ($rank == 3) $rank_style = "bg-orange-300 text-white";
            ?>
            <a href="inventory_list.php?bu=<?= urlencode($bu['bu'] ?? $bu['BU']) ?>" class="block p-3.5 rounded-2xl bg-white/40 border border-white/60 hover:bg-white/80 transition-all duration-300 hover:shadow-xl group">
                <div class="flex justify-between items-center mb-2 px-1">
                    <div class="flex items-center gap-3">
                        <span class="w-7 h-7 flex items-center justify-center <?= $rank_style ?> <?= $color['h_bg'] ?> group-hover:text-white group-hover:shadow-md transition-all duration-300 rounded-lg text-xs font-black"><?= $rank ?></span>
                        <div>
                            <h3 class="text-sm font-black text-slate-800 uppercase tracking-tight group-hover:text-blue-600 transition-colors"><?= htmlspecialchars($bu['bu'] ?? $bu['BU']) ?></h3>
                            <p class="text-[10px] font-bold text-slate-400 uppercase"><?= number_format($total_d) ?> Units</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <span class="text-xl font-black text-slate-800"><?= $score ?><span class="text-xs ml-0.5 opacity-40">%</span></span>
                    </div>
                </div>
                <div class="w-full bg-slate-200/40 rounded-full h-2.5 p-0.5 border border-white shadow-inner">
                    <div class="shimmer bg-gradient-to-r <?= $color['bg'] ?> h-full rounded-full transition-all duration-1000 shadow-sm <?= $color['text'] ?> bar-hover" style="width: <?= max($score, 5) ?>%"></div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>

        <!-- Footer -->
        <footer class="mt-12 text-center pb-4"><p class="text-xs font-black text-slate-400 uppercase tracking-widest">EIA Dashboard &copy; 2026 Prepared by Endpoint Management Team</p></footer>
    </div>

    <!-- Back to Top Button -->
    <button id="backToTop" class="fixed bottom-8 right-8 z-[150] w-12 h-12 bg-white/80 backdrop-blur-md border border-white/40 rounded-full shadow-2xl flex items-center justify-center text-blue-600 transition-all duration-500 translate-y-24 opacity-0 hover:bg-blue-600 hover:text-white active:scale-90 group" title="Back to Top">
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
        backToTopBtn.addEventListener('click', () => { window.scrollTo({ top: 0, behavior: 'smooth' }); });
    </script>
</body>
</html>