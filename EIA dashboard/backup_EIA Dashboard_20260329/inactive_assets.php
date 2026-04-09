<?php
session_start();
date_default_timezone_set('Asia/Bangkok');
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}
require_once 'config.php';
require_once 'query.php';

$cache_file = 'inactive_assets_cache.json';
$cache_lifetime = 3600; // 60 minutes
$lock_file = 'inactive_assets.lock';
$data_from_cache = false;

if (file_exists($cache_file) && (time() - filemtime($cache_file) < $cache_lifetime)) {
    $cache_data = json_decode(file_get_contents($cache_file), true);
    if ($cache_data) {
        $all_items = $cache_data['items'];
        $total_inactive = $cache_data['total_inactive'];
        $last_updated = $cache_data['last_updated'];
        $data_from_cache = true;
    }
}

// Trigger background update if expired and not locked
if (!file_exists($cache_file) || (time() - filemtime($cache_file) > $cache_lifetime)) {
    if (!file_exists($lock_file)) {
        $php_path = file_exists('C:\xampp\php\php.exe') ? 'C:\xampp\php\php.exe' : 'php';
        pclose(popen("start /B $php_path cache_worker.php --type=inactive_assets", "r"));
    }
}

if (!$data_from_cache) {
    $clean_query = rtrim(trim($eiaquery), ';');
    try {
        // Fetch All Inactive Units (No Limit for Caching)
        $sql = "SELECT `Computer Name`, `Serial no.`, `BU`, `Serviced By` as `Service Team`, `User name` as `Full Name`, `Last Reboot Date`, `Inactive 30+ Days` 
                FROM ($clean_query) as base_data 
                WHERE `Inactive 30+ Days` IN ('Y', 'Yes')
                ORDER BY `Last Reboot Date` ASC";
        $stmt = $pdo->query($sql);
        $all_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $total_inactive = count($all_items);
        $last_updated = date("d M Y, H:i");

        // Save to Cache
        file_put_contents($cache_file, json_encode([
            'items' => $all_items,
            'total_inactive' => $total_inactive,
            'last_updated' => $last_updated
        ]));
    } catch (\PDOException $e) { $all_items = []; $total_inactive = 0; $last_updated = "Error"; }
}

// Pagination from Cached Array
$limit = 50;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;
$total_pages = ceil($total_inactive / $limit) ?: 1;
$items = array_slice($all_items, $offset, $limit);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EIA Compliance Dashboard — Inactive Assets</title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%232563eb' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><path d='M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z'></path></svg>">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@700;800;900&family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script>
        if (localStorage.getItem('darkMode') === 'true') document.documentElement.classList.add('dark');
        function toggleDarkMode() {
            const isDark = document.documentElement.classList.toggle('dark');
            localStorage.setItem('darkMode', isDark);
        }
    </script>
    <style>
        :root { --body-bg: linear-gradient(120deg, #a1c4fd 0%, #c2e9fb 100%); --card-bg: rgba(255, 255, 255, 0.8); --card-border: rgba(255, 255, 255, 0.9); }
        .dark { --body-bg: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); --card-bg: rgba(30, 41, 59, 0.7); --card-border: rgba(255, 255, 255, 0.1); }
        body { font-family: 'Sarabun', sans-serif; background-image: var(--body-bg); min-height: 100vh; background-attachment: fixed; transition: all 0.3s ease; }
        .glass-card { background: var(--card-bg); backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); border: 1px solid var(--card-border); box-shadow: 0 10px 30px -10px rgba(0, 0, 0, 0.1); }
        thead th { position: sticky; top: 0; z-index: 20; background: rgba(226, 232, 240, 0.98); color: #0f172a; box-shadow: 0 1px 0 rgba(148, 163, 184, 1); }
        .dark thead th { background: rgba(15, 23, 42, 0.95); color: #f1f5f9; }
        tr.hover-row:hover { transform: scale(1.002) translateY(-1px); background: rgba(255, 255, 255, 0.95) !important; z-index: 10; position: relative; }
    </style>
</head>
<body class="p-4 md:p-8">
    <div class="max-w-[1600px] mx-auto">
        <div class="glass-card rounded-xl overflow-hidden shadow-2xl border border-white">
            <div class="p-6 md:p-8 flex flex-col md:flex-row justify-between items-center gap-6 border-b border-white/40 bg-white/30 relative overflow-hidden">
                <div class="absolute inset-0 z-0 opacity-[0.04] pointer-events-none grayscale"><img src="Image/bg-computer-3.jpg" alt="BG" class="w-full h-full object-cover"></div>
                <div class="relative z-10 flex items-center gap-4">
                    <div class="w-14 h-14 bg-white rounded-2xl flex items-center justify-center shadow-xl p-1.5 border border-slate-100"><img src="Logo RIS.png" alt="RIS Logo" class="w-full h-full object-contain"></div>
                    <div>
                        <h1 class="text-3xl font-black text-slate-800 dark:text-white tracking-tighter" style="font-family: Montserrat;">Inactive Assets</h1>
                        <div class="flex items-center gap-2 mt-0.5"><span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span><span class="text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase tracking-widest">Asset Lifecycle Management</span></div>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-3 justify-end relative z-10 text-right">
                    <div class="hidden md:block mr-2">
                        <p class="text-[8px] font-black text-slate-400 uppercase tracking-widest leading-none">Last Updated</p>
                        <p class="text-[10px] font-black text-slate-500 dark:text-slate-300 italic mt-1"><?= $last_updated ?></p>
                    </div>
                    <div class="text-[10px] font-black text-amber-600 bg-amber-50 dark:bg-amber-900/20 px-4 py-2 rounded-xl border border-amber-100 dark:border-amber-800/50 shadow-inner uppercase tracking-widest">Total Inactive: <?= number_format($total_inactive) ?> Units</div>
                    <a href="index.php" class="bg-blue-600 px-4 py-2.5 rounded-xl shadow-lg border border-blue-500 text-white font-bold flex items-center gap-2 hover:bg-blue-700 transition-all text-[10px] uppercase tracking-widest active:scale-95">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                        Back to Dashboard
                    </a>
                </div>
            </div>

            <div class="overflow-x-auto max-h-[600px]">
                <table class="w-full text-left whitespace-nowrap border-separate border-spacing-0">
                    <thead>
                        <tr>
                            <th class="px-6 py-5 text-xs font-black uppercase tracking-wider">Computer Name</th>
                            <th class="px-6 py-5 text-xs font-black uppercase tracking-wider">Serial No.</th>
                            <th class="px-6 py-5 text-xs font-black uppercase tracking-wider">BU</th>
                            <th class="px-6 py-5 text-xs font-black uppercase tracking-wider">Service Team</th>
                            <th class="px-6 py-5 text-xs font-black uppercase tracking-wider">Full Name</th>
                            <th class="px-6 py-5 text-xs font-black uppercase tracking-wider text-center border-r border-slate-200 dark:border-slate-700">Last Online</th>
                            <th class="px-6 py-5 text-xs font-black uppercase tracking-wider text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700 bg-white/40 dark:bg-slate-800/20">
                        <?php foreach ($items as $row): ?>
                        <tr class="hover-row transition-all duration-300 group">
                            <td class="px-6 py-4">
                                <a href="inventory_detail.php?name=<?= urlencode($row['Computer Name']) ?>&back=<?= urlencode($_SERVER['REQUEST_URI']) ?>" class="font-black text-slate-800 dark:text-slate-200 hover:text-indigo-600 dark:hover:text-indigo-400 flex items-center gap-2 transition-colors">
                                    <?= htmlspecialchars($row['Computer Name']) ?>
                                    <svg class="w-3.5 h-3.5 opacity-0 group-hover:opacity-100 transition-opacity" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                                </a>
                            </td>
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
                            <td class="px-6 py-4 text-center text-xs font-black text-slate-500 dark:text-slate-400"><?= htmlspecialchars($row['Last Reboot Date']) ?></td>
                            <td class="px-6 py-4 text-center"><span class="px-3 py-1 bg-amber-100 text-amber-600 rounded-full text-[9px] font-black uppercase tracking-widest border border-amber-200">Inactive 30+ Days</span></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($items)): ?>
                        <tr><td colspan="7" class="px-6 py-24 text-center text-slate-400 italic">No inactive assets found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Bar -->
            <div class="p-6 bg-white/50 dark:bg-slate-800/50 border-t border-white/40 flex flex-col md:flex-row justify-between items-center gap-6">
                <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest">
                    Showing <?= min($offset + 1, $total_inactive) ?> - <?= min($offset + $limit, $total_inactive) ?> of <?= number_format($total_inactive) ?>
                </div>
                <div class="flex gap-2">
                    <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>" class="px-4 py-2 bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 rounded-xl text-[10px] font-black text-slate-500 dark:text-slate-300 hover:bg-slate-50 transition-all uppercase">Prev</a>
                    <?php endif; ?>
                    <div class="flex items-center px-5 text-[10px] font-black text-white bg-blue-600 rounded-xl shadow-lg border border-blue-500">
                        PAGE <?= $page ?> / <?= $total_pages ?>
                    </div>
                    <?php if ($page < $total_pages): ?>
                    <a href="?page=<?= $page + 1 ?>" class="px-4 py-2 bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 rounded-xl text-[10px] font-black text-slate-500 dark:text-slate-300 hover:bg-slate-50 transition-all uppercase">Next</a>
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
        backToTopBtn.addEventListener('click', () => {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    </script>
</body>
</html>