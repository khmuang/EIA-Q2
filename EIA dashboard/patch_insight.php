<?php
session_start();
date_default_timezone_set('Asia/Bangkok');
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}
require_once 'config.php';
require_once 'query.php';

$cache_file = 'patch_insight_cache.json';
$cache_lifetime = 10800; // 180 minutes
$lock_file = 'patch_insight.lock';
$data_from_cache = false;
$needs_update = false;

// 1. Load from cache if exists
if (file_exists($cache_file)) {
    $cache_data = json_decode(file_get_contents($cache_file), true);
    if ($cache_data) {
        $summary_data = $cache_data['summary_data'] ?? [];
        $all_items = $cache_data['all_items'] ?? [];
        $total_items = $cache_data['total_items'] ?? 0;
        $last_updated = $cache_data['last_updated'] ?? 'N/A';
        $data_from_cache = true;
    }
}

// 2. Trigger background update logic
if (!file_exists($cache_file)) {
    $needs_update = true;
} elseif (time() - filemtime($cache_file) > $cache_lifetime) {
    if (file_exists($lock_file) && (time() - filemtime($lock_file) > 300)) { @unlink($lock_file); }
    if (!file_exists($lock_file)) {
        $php_path = 'C:\xampp\php\php.exe';
        $worker_path = __DIR__ . DIRECTORY_SEPARATOR . 'cache_worker.php';
        $cmd = "start /B $php_path \"$worker_path\" --type=patch_insight > NUL 2>&1";
        exec($cmd);
    }
}

// 3. FIRST TIME RUN SAFETY: Avoid white screen by NOT running query synchronously if missing
if ($needs_update && !$data_from_cache) {
    // Show a loading/preparing screen instead of running heavy query
    $all_items = []; $summary_data = []; $total_items = 0; $last_updated = "Preparing Data...";
    // Still trigger the worker
    if (!file_exists($lock_file)) {
        $php_path = 'C:\xampp\php\php.exe';
        $worker_path = __DIR__ . DIRECTORY_SEPARATOR . 'cache_worker.php';
        $cmd = "start /B $php_path \"$worker_path\" --type=patch_insight > NUL 2>&1";
        exec($cmd);
    }
}

// --- FILTERING LOGIC ---
$search = trim($_GET['search'] ?? '');
$filter_bu = $_GET['bu'] ?? '';

// Get unique BUs for dropdown
$unique_bus = array_unique(array_column($all_items, 'BU'));
sort($unique_bus);

if ($search !== '' || $filter_bu !== '') {
    $filtered_items = array_filter($all_items, function($item) use ($search, $filter_bu) {
        $match_search = true;
        $match_bu = true;
        if ($search !== '') {
            $search_lc = strtolower($search);
            $match_search = (strpos(strtolower($item['Computer Name']), $search_lc) !== false || strpos(strtolower($item['Serial no.']), $search_lc) !== false || strpos(strtolower($item['Full Name']), $search_lc) !== false);
        }
        if ($filter_bu !== '') { $match_bu = ($item['BU'] === $filter_bu); }
        return $match_search && $match_bu;
    });
} else {
    $filtered_items = $all_items;
}

$total_filtered = count($filtered_items);

// Pagination
$limit = 50;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;
$total_pages = ceil($total_filtered / $limit) ?: 1;
$items = array_slice($filtered_items, $offset, $limit);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patch Management Insight | EIA Dashboard</title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%232563eb' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><path d='M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z'></path></svg>">
    <!-- Tailwind CSS (Production Ready) -->
    <link href="dist/output.css" rel="stylesheet">
    <script>
        if (localStorage.getItem('darkMode') === 'true') document.documentElement.classList.add('dark');
        function toggleDarkMode() {
            const isDark = document.documentElement.classList.toggle('dark');
            localStorage.setItem('darkMode', isDark);
        }

        // Auto-refresh logic when data is preparing
        <?php if ($last_updated === "Preparing Data..."): ?>
        console.log("Data is preparing... setting auto-reload in 5s");
        setTimeout(function() {
            window.location.reload();
        }, 5000);
        <?php endif; ?>
    </script>
    <style>
        :root { --body-bg: linear-gradient(120deg, #a1c4fd 0%, #c2e9fb 100%); --card-bg: rgba(255, 255, 255, 0.8); --card-border: rgba(255, 255, 255, 0.9); }
        .dark { --body-bg: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); --card-bg: rgba(30, 41, 59, 0.7); --card-border: rgba(255, 255, 255, 0.1); }
        body { font-family: 'Sarabun', sans-serif; background-image: var(--body-bg); min-height: 100vh; background-attachment: fixed; transition: all 0.3s ease; }
        .glass-card { background: var(--card-bg); backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); border: 1px solid var(--card-border); box-shadow: 0 10px 30px -10px rgba(0, 0, 0, 0.1); }
        thead th { position: sticky; top: 0; z-index: 20; background: rgba(226, 232, 240, 0.98); color: #0f172a; box-shadow: 0 1px 0 rgba(148, 163, 184, 1); }
        .dark thead th { background: rgba(15, 23, 42, 0.95); color: #f1f5f9; }
        tr.hover-row:hover { transform: scale(1.002) translateY(-1px); background: rgba(255, 255, 255, 0.95) !important; z-index: 10; position: relative; }
        .shimmer { position: relative; overflow: hidden; }
        .shimmer::before { content: ''; position: absolute; top: 0; left: -100%; width: 50%; height: 100%; background: linear-gradient(to right, transparent, rgba(255,255,255,0.3), transparent); transform: skewX(-20deg); animation: shimmerEffect 4s infinite; }
        @keyframes shimmerEffect { 0% { left: -100%; } 20% { left: 200%; } 100% { left: 200%; } }
    </style>
</head>
<body class="p-4 md:p-8">
    <div class="max-w-[1600px] mx-auto">
        <!-- Summary Stats Section -->
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6 mb-8">
            <div class="lg:col-span-3 glass-card rounded-xl p-8 relative overflow-hidden">
                <div class="img-blur"><img src="Image/bg-computer-4.jpg" alt="BG" class="w-full h-full object-cover"></div>
                <div class="flex items-center gap-5 mb-8 relative z-10">
                    <div class="w-14 h-14 bg-white rounded-2xl flex items-center justify-center shadow-xl p-1.5 border border-slate-100"><img src="Logo RIS.png" alt="RIS Logo" class="w-full h-full object-contain"></div>
                    <div>
                        <h1 class="text-3xl lg:text-4xl font-black text-slate-800 dark:text-white tracking-tight" style="font-family: 'Montserrat', sans-serif;">Patch Insight</h1>
                        <div class="flex items-center gap-2 mt-0.5"><span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span><span class="text-xs font-black text-slate-500 dark:text-slate-400 uppercase tracking-widest">Critical Updates Monitoring</span></div>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-10 gap-y-4 relative z-10">
                    <?php if (empty($summary_data)): ?>
                        <div class="col-span-2 py-10 text-center text-slate-400 font-bold italic">No summary data available. Data may be preparing...</div>
                    <?php else: ?>
                        <?php foreach ($summary_data as $s): 
                            $max_val = $summary_data[0]['total_missing'] ?: 1;
                            $pct = ($s['total_missing'] / $max_val) * 100;
                        ?>
                        <a href="patch_insight.php?bu=<?= urlencode($s['BU']) ?>" class="flex flex-col gap-1.5 group/bar hover:scale-[1.02] transition-transform duration-300 cursor-pointer" title="Click to filter by <?= htmlspecialchars($s['BU']) ?>">
                            <div class="flex justify-between text-xs font-black uppercase tracking-tighter">
                                <span class="dark:text-slate-300 group-hover/bar:text-blue-500 transition-colors"><?= htmlspecialchars($s['BU']) ?></span>
                                <span class="text-rose-500"><?= number_format($s['total_missing']) ?> Patches</span>
                            </div>
                            <div class="w-full bg-slate-100 dark:bg-slate-800 rounded-full h-2 overflow-hidden border border-slate-200/50 dark:border-slate-700/50 shadow-inner">
                                <div class="bg-gradient-to-r from-rose-500 to-orange-400 h-full rounded-full shimmer group-hover/bar:brightness-110 transition-all" style="width: <?= $pct ?>%"></div>
                            </div>
                        </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            <div class="glass-card rounded-xl p-8 flex flex-col justify-between items-center text-center relative z-10">
                <div class="text-right w-full mb-2">
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest leading-none">Last Updated</p>
                    <p class="text-xs font-black text-slate-500 dark:text-slate-300 italic mt-1"><?= $last_updated ?></p>
                </div>
                <div class="bg-rose-50 dark:bg-rose-900/20 px-4 py-3 rounded-2xl border border-rose-100 dark:border-rose-800/50 w-full mb-4">
                    <p class="text-xs font-black text-rose-400 uppercase tracking-widest mb-1">Total Impacted</p>
                    <p class="text-2xl font-black text-rose-600 dark:text-rose-400"><?= number_format($total_items) ?> Units</p>
                </div>
                <a href="index.php" class="bg-blue-600 w-full py-3 rounded-xl shadow-sm border border-blue-500 text-white font-black text-xs uppercase tracking-widest hover:bg-blue-700 transition-all group flex items-center justify-center gap-2 active:scale-95">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                    Back to Dashboard
                </a>
            </div>
        </div>

        <!-- Integrated Filter Bar -->
        <div class="glass-card rounded-xl overflow-hidden shadow-2xl border border-white mb-6">
            <div class="px-6 py-4 bg-white/50 border-b border-gray-100 flex flex-wrap items-center gap-4">
                <form method="GET" class="flex flex-wrap items-center gap-3 w-full">
                    <div class="relative flex-1 min-w-[300px]">
                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                               placeholder="Search computer, serial, or name..." 
                               class="w-full pl-10 pr-4 py-2 bg-white border border-gray-200 rounded-xl text-xs font-bold focus:ring-2 focus:ring-blue-500 outline-none shadow-inner">
                        <svg class="w-4 h-4 absolute left-3.5 top-2.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                    </div>
                    
                    <select name="bu" class="px-4 py-2 bg-white border border-gray-200 rounded-xl text-xs font-bold focus:ring-2 focus:ring-blue-500 outline-none shadow-inner min-w-[150px]">
                        <option value="">All Business Units</option>
                        <?php foreach ($unique_bus as $bu_opt): ?>
                            <option value="<?= htmlspecialchars($bu_opt) ?>" <?= $filter_bu === $bu_opt ? 'selected' : '' ?>><?= htmlspecialchars($bu_opt) ?></option>
                        <?php endforeach; ?>
                    </select>

                    <button type="submit" class="bg-slate-800 text-white px-6 py-2 rounded-xl text-xs font-black uppercase tracking-widest hover:bg-slate-900 transition-all shadow-md">Apply Filters</button>
                    
                    <?php if (!empty($search) || !empty($filter_bu)): ?>
                        <a href="patch_insight.php" class="bg-rose-50 text-rose-600 px-4 py-2 rounded-xl text-xs font-black uppercase tracking-widest hover:bg-rose-600 hover:text-white transition-all border border-rose-100 shadow-sm ml-2">
                            Clear Filters
                        </a>
                    <?php endif; ?>
                </form>
            </div>

            <div class="overflow-x-auto max-h-[500px]">
                <table class="w-full text-left whitespace-nowrap border-separate border-spacing-0">
                    <thead>
                        <tr>
                            <th class="px-6 py-5 text-xs font-black uppercase tracking-wider">Computer Name</th>
                            <th class="px-6 py-5 text-xs font-black uppercase tracking-wider">Serial No.</th>
                            <th class="px-6 py-5 text-xs font-black uppercase tracking-wider">BU</th>
                            <th class="px-6 py-5 text-xs font-black uppercase tracking-wider">Service Team</th>
                            <th class="px-6 py-5 text-xs font-black uppercase tracking-wider">Full Name</th>
                            <th class="px-6 py-5 text-xs font-black uppercase tracking-wider text-center border-r border-slate-200 dark:border-slate-700">Missing Count</th>
                            <th class="px-6 py-5 text-xs font-black uppercase tracking-wider">Missing Patch Details</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700 bg-white/40 dark:bg-slate-800/20">
                        <?php if (empty($items)): ?>
                            <tr><td colspan="7" class="px-6 py-24 text-center text-slate-400 italic font-bold">No results found or data is preparing. Please wait a moment and refresh.</td></tr>
                        <?php else: ?>
                            <?php foreach ($items as $row): ?>
                            <tr class="hover-row transition-all duration-300 group">
                                <td class="px-6 py-4"><a href="inventory_detail.php?name=<?= urlencode($row['Computer Name']) ?>&back=<?= urlencode($_SERVER['REQUEST_URI']) ?>" class="font-bold text-slate-700 dark:text-slate-300 hover:text-indigo-600 transition-colors flex items-center gap-2 tracking-tight uppercase text-xs"><?= htmlspecialchars($row['Computer Name']) ?><svg class="w-3.5 h-3.5 opacity-0 group-hover:opacity-100 transition-opacity" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"></path></svg></a></td>
                                <td class="px-6 py-4 text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest"><?= htmlspecialchars($row['Serial no.']) ?></td>
                                <td class="px-6 py-4"><span class="px-2 py-0.5 bg-white dark:bg-slate-700 border rounded text-[10px] font-bold text-slate-500 uppercase"><?= htmlspecialchars($row['BU']) ?></span></td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-2">
                                        <?php 
                                            $team_val = trim($row['Service Team'] ?? 'None');
                                            $icons = [
                                                'HO' => ['icon' => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H5a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>', 'color' => 'text-indigo-600'],
                                                'DC' => ['icon' => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path></svg>', 'color' => 'text-blue-600'],
                                                'Branch' => ['icon' => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>', 'color' => 'text-emerald-600'],
                                                'RIS No Service' => ['icon' => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728L5.636 5.636"></path></svg>', 'color' => 'text-rose-500']
                                            ];
                                            $current = $icons[$team_val] ?? ['icon' => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>', 'color' => 'text-slate-400'];
                                        ?>
                                        <span class="<?= $current['color'] ?> opacity-80"><?= $current['icon'] ?></span>
                                        <span class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase"><?= htmlspecialchars($team_val) ?></span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-tight"><?= htmlspecialchars($row['Full Name'] ?: '-') ?></td>
                                <td class="px-6 py-4 text-center font-bold text-rose-600 tracking-tight"><?= $row['Total Missing Critical Patches'] ?></td>
                                <td class="px-6 py-4"><div class="text-[10px] font-mono text-slate-500 dark:text-slate-400 max-w-2xl truncate" title="<?= htmlspecialchars($row['Missing Critical Patches Name']) ?>"><?= htmlspecialchars($row['Missing Critical Patches Name']) ?></div></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="p-6 bg-white/50 dark:bg-slate-800/50 border-t border-white/40 flex flex-col md:flex-row justify-between items-center gap-6">
                <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest">
                    Showing <?= min($offset + 1, $total_filtered) ?> - <?= min($offset + $limit, $total_filtered) ?> of <?= number_format($total_filtered) ?> impacted
                </div>
                <div class="flex gap-2">
                    <?php 
                    $query_params = $_GET;
                    function get_page_url($p, $params) { $params['page'] = $p; return '?' . http_build_query($params); }
                    ?>
                    <?php if ($page > 1): ?>
                    <a href="<?= get_page_url($page - 1, $query_params) ?>" class="px-4 py-2 bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 rounded-xl text-[10px] font-black text-slate-500 dark:text-slate-300 hover:bg-slate-50 transition-all uppercase">Prev</a>
                    <?php endif; ?>
                    <div class="flex items-center px-5 text-[10px] font-black text-white bg-blue-600 rounded-xl shadow-lg border border-blue-500">
                        PAGE <?= $page ?> / <?= $total_pages ?>
                    </div>
                    <?php if ($page < $total_pages): ?>
                    <a href="<?= get_page_url($page + 1, $query_params) ?>" class="px-4 py-2 bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 rounded-xl text-[10px] font-black text-slate-500 dark:text-slate-300 hover:bg-slate-50 transition-all uppercase">Next</a>
                    <?php endif; ?>
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
        backToTopBtn.addEventListener('click', () => { window.scrollTo({ top: 0, behavior: 'smooth' }); });
    </script>
</body>
</html>
</body>
</html>