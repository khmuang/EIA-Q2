<?php
// 1. Database Connection & Queries
require_once 'config.php';
require_once 'query.php';

// 2. Get Latest Week
$latest = getLatestReportWeek($pdo);
$week = $latest['week'];
$year = $latest['year'];

// 3. Query Top BUs by Average Compliance Score
$bu_data = getTopBUComplianceScore($pdo, $week, $year);

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All BU Compliance Performance</title>
    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%232563eb' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><path d='M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z'></path></svg>">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { 
            font-family: 'Sarabun', sans-serif; 
            background-image: linear-gradient(-225deg, #69EACB 0%, #EACCF8 48%, #6654F1 100%);
            min-height: 100vh;
            padding: 40px 20px;
        }
        .custom-scrollbar::-webkit-scrollbar { width: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(0,0,0,0.1); border-radius: 10px; }
    </style>
</head>
<body>

    <div class="max-w-4xl mx-auto bg-white/90 backdrop-blur-md rounded-[3rem] shadow-2xl border border-white p-10 md:p-16">
        
        <!-- Header -->
        <div class="flex flex-col md:flex-row justify-between items-center mb-12 gap-6 text-center md:text-left border-b border-slate-100 pb-10">
            <div>
                <h1 class="text-4xl font-black text-slate-900 tracking-tighter uppercase text-blue-600">Full BU Ranking</h1>
                <p class="text-slate-500 font-bold uppercase tracking-[0.3em] text-xs mt-1">Compliance Performance across Organization</p>
            </div>
            <a href="index.php" class="bg-blue-600 px-6 py-3.5 rounded-2xl shadow-xl hover:bg-blue-700 hover:-translate-y-1 transition-all text-white flex items-center gap-3 group">
                <svg class="w-5 h-5 group-hover:-translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                <span class="text-sm font-black uppercase tracking-wider">Back to Dashboard</span>
            </a>
        </div>

        <!-- Ranking List (Scrollable) -->
        <div class="space-y-6 max-h-[600px] overflow-y-auto pr-4 custom-scrollbar">
            <?php 
            $bar_colors = [
                ['from' => 'from-emerald-500', 'to' => 'to-teal-400', 'border' => 'hover:border-[#064e3b]', 'text' => 'group-hover:text-emerald-600', 'bg_idx' => 'group-hover:bg-emerald-600'],
                ['from' => 'from-blue-600', 'to' => 'to-indigo-400', 'border' => 'hover:border-[#1e3a8a]', 'text' => 'group-hover:text-blue-600', 'bg_idx' => 'group-hover:bg-blue-600'],
                ['from' => 'from-purple-600', 'to' => 'to-pink-400', 'border' => 'hover:border-[#581c87]', 'text' => 'group-hover:text-purple-600', 'bg_idx' => 'group-hover:bg-purple-600'],
                ['from' => 'from-amber-500', 'to' => 'to-orange-400', 'border' => 'hover:border-[#78350f]', 'text' => 'group-hover:text-amber-600', 'bg_idx' => 'group-hover:bg-amber-600'],
                ['from' => 'from-rose-600', 'to' => 'to-red-400', 'border' => 'hover:border-[#7f1d1d]', 'text' => 'group-hover:text-rose-600', 'bg_idx' => 'group-hover:bg-rose-600']
            ];

            foreach ($bu_data as $index => $bu): 
                $score = round(($bu['total_success_points'] / ($bu['total_devices'] * 6)) * 100, 1);
                $color = $bar_colors[$index % count($bar_colors)];
            ?>
            <a href="inventory_list.php?bu=<?= urlencode($bu['bu']) ?>" class="group block">
                <div class="flex justify-between items-end mb-2 px-2">
                    <div class="flex items-center gap-3">
                        <span class="w-8 h-8 flex items-center justify-center bg-slate-100 text-slate-500 <?= $color['bg_idx'] ?> group-hover:text-white rounded-lg text-xs font-black transition-all"><?= $index + 1 ?></span>
                        <span class="text-sm font-black text-slate-700 uppercase tracking-tight <?= $color['text'] ?> transition-colors">
                            <?= htmlspecialchars($bu['bu']) ?>
                        </span>
                    </div>
                    <div class="text-right">
                        <span class="text-lg font-black text-slate-900"><?= $score ?>%</span>
                        <span class="text-[9px] font-bold text-slate-400 uppercase ml-1">Score</span>
                    </div>
                </div>
                
                <div class="w-full bg-slate-100/50 rounded-2xl h-10 p-1.5 border border-slate-200/50 shadow-inner group-hover:border-blue-300 transition-all duration-500">
                    <div class="bg-gradient-to-r <?= $color['from'] ?> <?= $color['to'] ?> h-full rounded-xl transition-all duration-1000 shadow-lg relative flex items-center px-4 group-hover:border-r-[6px] <?= $color['border'] ?>" 
                         style="width: <?= max($score, 10) ?>%">
                        <div class="absolute inset-0 bg-white/10 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                        <span class="text-[9px] text-white font-black drop-shadow-md whitespace-nowrap uppercase tracking-widest">
                            <?= number_format($bu['total_devices']) ?> Devices
                        </span>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>

        <!-- Footer Info -->
        <div class="mt-16 pt-8 border-t border-slate-100 flex justify-between items-center">
            <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest italic">
                * Score averaged across 6 key security monitoring topics
            </p>
            <div class="flex items-center gap-3 bg-slate-900 text-white px-4 py-2 rounded-xl shadow-lg">
                <span class="w-2 h-2 bg-emerald-500 rounded-full animate-pulse"></span>
                <span class="text-[9px] font-black uppercase opacity-60 tracking-widest">Active Records:</span>
                <span class="text-xs font-black whitespace-nowrap">W<?= $week ?> / <?= $year ?></span>
            </div>
        </div>
    </div>

</body>
</html>