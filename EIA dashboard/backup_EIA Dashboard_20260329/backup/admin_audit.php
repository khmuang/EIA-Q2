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
    // Count Total Risk Units
    $total_risk = $pdo->query("SELECT COUNT(*) FROM ($clean_query) as base_data WHERE `Standard Admin Only` NOT IN ('Y', 'Yes', 'Compliant', 'Success')")->fetchColumn();
    $total_pages = ceil($total_risk / $limit) ?: 1;

    // Fetch Paginated Items with Full Name and Service Team
    $sql = "SELECT `Computer Name`, `BU`, `Serviced By` as `Service Team`, `User name` as `Full Name`, `Members of Administrator Group`, `Standard Admin Only` 
            FROM ($clean_query) as base_data 
            WHERE `Standard Admin Only` NOT IN ('Y', 'Yes', 'Compliant', 'Success')
            LIMIT $limit OFFSET $offset";
    $items = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
} catch (\PDOException $e) { $items = []; $total_risk = 0; $total_pages = 1; }
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EIA Compliance Dashboard — Admin Rights Audit</title>
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
        thead th { position: sticky; top: 0; z-index: 20; background: rgba(226, 232, 240, 0.98); color: #0f172a; }
        .dark thead th { background: rgba(15, 23, 42, 0.95); color: #f1f5f9; }
    </style>
</head>
<body class="p-4 md:p-8">
    <div class="max-w-[1600px] mx-auto">
        <div class="glass-card rounded-xl overflow-hidden shadow-2xl border border-white mb-10">
            <div class="p-6 md:p-8 flex flex-col md:flex-row justify-between items-center gap-6 border-b border-white/40 bg-white/30">
                <div class="flex items-center gap-5">
                    <div class="w-14 h-14 bg-white rounded-2xl flex items-center justify-center shadow-xl p-1.5"><img src="Logo RIS.png" alt="RIS Logo" class="w-full h-full object-contain"></div>
                    <div>
                        <h1 class="text-3xl font-black text-slate-800 dark:text-white tracking-tighter" style="font-family: Montserrat;">Admin Rights Audit</h1>
                        <div class="flex items-center gap-2 mt-0.5"><span class="w-2 h-2 rounded-full bg-rose-500 animate-pulse"></span><span class="text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase tracking-widest">Privilege Risk Monitoring</span></div>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-3 justify-end">
                    <div class="text-[10px] font-black text-rose-600 bg-rose-50 px-4 py-2 rounded-xl border border-rose-100 shadow-inner uppercase tracking-widest">High Risk: <?= number_format($total_risk) ?> Units</div>
                    <a href="index.php" class="bg-blue-600 px-4 py-2.5 rounded-xl shadow-lg border border-blue-500 text-white font-bold flex items-center gap-2 hover:bg-blue-700 transition-all text-[10px] uppercase tracking-widest active:scale-95">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                        Back to Dashboard
                    </a>
                </div>
            </div>

            <div class="overflow-x-auto max-h-[650px]">
                <table class="w-full text-left whitespace-nowrap">
                    <thead>
                        <tr>
                            <th class="px-6 py-5 text-xs font-black uppercase tracking-wider">Computer Name</th>
                            <th class="px-6 py-5 text-xs font-black uppercase tracking-wider">BU</th>
                            <th class="px-6 py-5 text-xs font-black uppercase tracking-wider">Service Team</th>
                            <th class="px-6 py-5 text-xs font-black uppercase tracking-wider">Full Name</th>
                            <th class="px-6 py-5 text-xs font-black uppercase tracking-wider">Admin Members</th>
                            <th class="px-6 py-5 text-xs font-black uppercase tracking-wider text-center border-r border-slate-200">Status</th>
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
                            <td class="px-6 py-4"><div class="text-[11px] font-mono text-slate-600 dark:text-slate-400 max-w-md truncate" title="<?= htmlspecialchars($row['Members of Administrator Group']) ?>"><?= htmlspecialchars($row['Members of Administrator Group']) ?></div></td>
                            <td class="px-6 py-4 text-center"><span class="px-3 py-1 bg-rose-100 text-rose-600 rounded-full text-[9px] font-black uppercase tracking-widest border border-rose-200">Non-Standard</span></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($items)): ?>
                        <tr><td colspan="6" class="px-6 py-24 text-center text-slate-400 italic">No admin rights issues found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Bar -->
            <div class="p-6 bg-white/50 dark:bg-slate-800/50 border-t border-white/40 flex flex-col md:flex-row justify-between items-center gap-6">
                <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest">
                    Showing <?= min($offset + 1, $total_risk) ?> - <?= min($offset + $limit, $total_risk) ?> of <?= number_format($total_risk) ?>
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