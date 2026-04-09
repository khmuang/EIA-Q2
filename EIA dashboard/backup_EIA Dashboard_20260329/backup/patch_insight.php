<?php
session_start();
date_default_timezone_set('Asia/Bangkok');
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}
require_once 'config.php';
require_once 'query.php';

// Pagination Logic
$limit = 50;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

$clean_query = rtrim(trim($eiaquery), ';');
try {
    // Top BU Summary (Always full list for chart)
    $sql_top = "SELECT `BU`, SUM(`Total Missing Critical Patches`) as total_missing FROM ($clean_query) as base_data GROUP BY `BU` ORDER BY total_missing DESC LIMIT 10";
    $summary_data = $pdo->query($sql_top)->fetchAll(PDO::FETCH_ASSOC);

    // Count Total Machines with Missing Patches
    $total_items = $pdo->query("SELECT COUNT(*) FROM ($clean_query) as base_data WHERE `Total Missing Critical Patches` > 0")->fetchColumn();
    $total_pages = ceil($total_items / $limit) ?: 1;

    // Fetch Paginated Items with added columns
    $sql_list = "SELECT `Computer Name`, `BU`, `Serviced By` as `Service Team`, `User name` as `Full Name`, `Total Missing Critical Patches`, `Missing Critical Patches Name` 
                 FROM ($clean_query) as base_data 
                 WHERE `Total Missing Critical Patches` > 0 
                 ORDER BY `Total Missing Critical Patches` DESC LIMIT $limit OFFSET $offset";
    $items = $pdo->query($sql_list)->fetchAll(PDO::FETCH_ASSOC);
} catch (\PDOException $e) { $items = []; $summary_data = []; $total_items = 0; $total_pages = 1; }
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EIA Compliance Dashboard — Patch Management Insight</title>
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
        .glass-card { background: var(--card-bg); backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); border: 1px solid var(--card-border); box-shadow: 0 10px 30px -5px rgba(0, 0, 0, 0.1); }
        thead th { position: sticky; top: 0; z-index: 20; background: rgba(226, 232, 240, 0.98); color: #0f172a; }
        .dark thead th { background: rgba(15, 23, 42, 0.95); color: #f1f5f9; }
    </style>
</head>
<body class="p-4 md:p-8">
    <div class="max-w-[1600px] mx-auto">
        
        <!-- Summary Cards -->
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6 mb-8">
            <div class="lg:col-span-3 glass-card rounded-xl p-8">
                <div class="flex items-center gap-5 mb-8">
                    <div class="w-14 h-14 bg-white rounded-2xl flex items-center justify-center shadow-xl p-1.5"><img src="Logo RIS.png" alt="RIS Logo" class="w-full h-full object-contain"></div>
                    <div>
                        <h1 class="text-3xl font-black text-slate-800 dark:text-white tracking-tighter" style="font-family: Montserrat;">Patch Insight</h1>
                        <p class="text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase tracking-widest">Critical Updates Monitoring</p>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-10 gap-y-4">
                    <?php foreach ($summary_data as $s): 
                        $max = $summary_data[0]['total_missing'] ?: 1;
                        $pct = ($s['total_missing'] / $max) * 100;
                    ?>
                    <div class="flex flex-col gap-1.5">
                        <div class="flex justify-between text-[11px] font-black uppercase tracking-tighter">
                            <span class="dark:text-slate-300"><?= htmlspecialchars($s['BU']) ?></span>
                            <span class="text-rose-500"><?= number_format($s['total_missing']) ?> Patches</span>
                        </div>
                        <div class="w-full bg-slate-100 dark:bg-slate-800 rounded-full h-2 overflow-hidden border border-slate-200/50 dark:border-slate-700/50">
                            <div class="bg-gradient-to-r from-rose-500 to-orange-400 h-full rounded-full" style="width: <?= $pct ?>%"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="glass-card rounded-xl p-8 flex flex-col justify-between items-center text-center">
                <div class="bg-rose-50 dark:bg-rose-900/20 px-4 py-2 rounded-2xl border border-rose-100 dark:border-rose-800/50 w-full mb-4">
                    <p class="text-[9px] font-black text-rose-400 uppercase tracking-widest mb-1">Total Impacted</p>
                    <p class="text-2xl font-black text-rose-600 dark:text-rose-400"><?= number_format($total_items) ?> Units</p>
                </div>
                <a href="index.php" class="bg-blue-600 w-full py-3 rounded-2xl text-white font-black text-[10px] uppercase tracking-widest shadow-lg border border-blue-500 hover:bg-blue-700 transition-all active:scale-95">Back to Dashboard</a>
            </div>
        </div>

        <!-- Table Card -->
        <div class="glass-card rounded-xl overflow-hidden shadow-2xl border border-white">
            <div class="overflow-x-auto max-h-[500px]">
                <table class="w-full text-left whitespace-nowrap">
                    <thead>
                        <tr>
                            <th class="px-6 py-5 text-xs font-black uppercase tracking-wider">Computer Name</th>
                            <th class="px-6 py-5 text-xs font-black uppercase tracking-wider">BU</th>
                            <th class="px-6 py-5 text-xs font-black uppercase tracking-wider">Service Team</th>
                            <th class="px-6 py-5 text-xs font-black uppercase tracking-wider">Full Name</th>
                            <th class="px-6 py-5 text-xs font-black uppercase tracking-wider text-center">Missing Count</th>
                            <th class="px-6 py-5 text-xs font-black uppercase tracking-wider border-r border-slate-200">Missing Patch Details</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700 bg-white/40 dark:bg-slate-800/20">
                        <?php foreach ($items as $row): ?>
                        <tr class="hover:bg-white/80 dark:hover:bg-slate-800/60 transition-all group">
                            <td class="px-6 py-4">
                                <a href="inventory_detail.php?name=<?= urlencode($row['Computer Name']) ?>&back=<?= urlencode($_SERVER['REQUEST_URI']) ?>" class="font-black text-slate-800 dark:text-slate-200 hover:text-indigo-600 dark:hover:text-indigo-400 flex items-center gap-2 transition-colors">
                                    <?= htmlspecialchars($row['Computer Name']) ?>
                                    <svg class="w-3.5 h-3.5 opacity-0 group-hover:opacity-100 transition-opacity" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                                </a>
                            </td>
                            <td class="px-6 py-4"><span class="px-2 py-0.5 bg-white dark:bg-slate-700 border rounded text-[10px] font-bold text-slate-500 uppercase"><?= htmlspecialchars($row['BU']) ?></span></td>
                            <td class="px-6 py-4 text-xs font-bold text-slate-500 dark:text-slate-400"><?= htmlspecialchars($row['Service Team'] ?: '-') ?></td>
                            <td class="px-6 py-4 text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-tight"><?= htmlspecialchars($row['Full Name'] ?: '-') ?></td>
                            <td class="px-6 py-4 text-center font-black text-rose-600"><?= $row['Total Missing Critical Patches'] ?></td>
                            <td class="px-6 py-4"><div class="text-[10px] font-mono text-slate-500 dark:text-slate-400 max-w-2xl truncate" title="<?= htmlspecialchars($row['Missing Critical Patches Name']) ?>"><?= htmlspecialchars($row['Missing Critical Patches Name']) ?></div></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($items)): ?>
                        <tr><td colspan="6" class="px-6 py-24 text-center text-slate-400 italic">No patching issues found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Bar -->
            <div class="p-6 bg-white/50 dark:bg-slate-800/50 border-t border-white/40 flex flex-col md:flex-row justify-between items-center gap-6">
                <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest">
                    Showing <?= min($offset + 1, $total_items) ?> - <?= min($offset + $limit, $total_items) ?> of <?= number_format($total_items) ?> impacted machines
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
        <footer class="mt-12 text-center"><p class="text-[10px] font-black text-slate-400 uppercase tracking-[0.5em]">EIA Dashboard &copy; 2026 Prepared by Endpoint Management Team</p></footer>
    </div>
</body>
</html>