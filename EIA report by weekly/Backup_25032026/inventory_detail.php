<?php
// 1. Database Connection
require_once 'config.php';

// 2. Fetch Device Data
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$back_url = isset($_GET['back']) ? $_GET['back'] : 'inventory_list.php';

$stmt = $pdo->prepare("SELECT * FROM inventory_reports WHERE id = ?");
$stmt->execute([$id]);
$device = $stmt->fetch();

if (!$device) { die("Device not found."); }

// Helper for labels
function renderRow($label, $val, $is_code = false) {
    $val = $val ?: 'N/A';
    $class = $is_code ? 'font-mono text-slate-400' : 'font-black text-slate-800';
    return "
    <div class='flex justify-between items-center border-b border-slate-50 py-3 px-1 hover:bg-slate-50/50 transition-colors'>
        <span class='text-[11px] font-bold text-slate-400 uppercase tracking-wider'>$label</span>
        <span class='text-sm $class text-right max-w-[250px] truncate' title='$val'>$val</span>
    </div>";
}

function getBadgeFull($val) {
    $success_vals = ['Y', 'Yes', 'Compliant', 'Success', 'Active', 'Allowed', 'Healthy'];
    $is_success = in_array($val, $success_vals);
    $bg = $is_success ? 'bg-emerald-500 shadow-emerald-100' : 'bg-rose-500 shadow-rose-100';
    return "<span class='px-3 py-1 $bg text-white rounded-full text-[10px] font-black uppercase shadow-lg'>$val</span>";
}

function getBadgeRestart($val) {
    $is_pending = (trim(strtolower($val)) == 'yes');
    $bg = $is_pending ? 'bg-rose-500 shadow-rose-100' : 'bg-emerald-500 shadow-emerald-100';
    return "<span class='px-3 py-1 $bg text-white rounded-full text-[10px] font-black uppercase shadow-lg'>$val</span>";
}

function getBadgeInactive($val) {
    $is_inactive = (trim(strtolower($val)) == 'yes');
    if ($is_inactive) {
        return "<span class='flex items-center gap-1.5 text-rose-600 font-black text-sm uppercase tracking-tighter'>
                    <svg class='w-4 h-4 animate-pulse' fill='currentColor' viewBox='0 0 20 20'><path fill-rule='evenodd' d='M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z' clip-rule='evenodd'></path></svg>
                    Inactive
                </span>";
    } else {
        return "<span class='text-emerald-600 font-black text-sm uppercase tracking-tighter'>Active</span>";
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($device['computer_name']) ?> - Full Detail</title>
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
        .glass-card { background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(12px); border: 1px solid white; }
        
        /* Ultra Vibrant & Deep Shadows (3-Layer Glow) */
        .hover-shadow-blue:hover { 
            box-shadow: 0 45px 90px -15px rgba(37, 99, 235, 0.85), 0 25px 50px -10px rgba(37, 99, 235, 0.6), 0 0 20px 2px rgba(37, 99, 235, 0.4); 
            border-color: rgba(37, 99, 235, 0.5);
        }
        .hover-shadow-indigo:hover { 
            box-shadow: 0 45px 90px -15px rgba(79, 70, 229, 0.85), 0 25px 50px -10px rgba(79, 70, 229, 0.6), 0 0 20px 2px rgba(79, 70, 229, 0.4); 
            border-color: rgba(79, 70, 229, 0.5);
        }
        .hover-shadow-emerald:hover { 
            box-shadow: 0 45px 90px -15px rgba(5, 150, 105, 0.85), 0 25px 50px -10px rgba(5, 150, 105, 0.6), 0 0 20px 2px rgba(5, 150, 105, 0.4); 
            border-color: rgba(5, 150, 105, 0.5);
        }
        .hover-shadow-slate:hover { 
            box-shadow: 0 45px 90px -15px rgba(30, 41, 59, 0.7), 0 25px 50px -10px rgba(30, 41, 59, 0.5), 0 0 20px 2px rgba(30, 41, 59, 0.3); 
            border-color: rgba(30, 41, 59, 0.4);
        }
        .hover-shadow-orange:hover { 
            box-shadow: 0 45px 90px -15px rgba(234, 88, 12, 0.85), 0 25px 50px -10px rgba(234, 88, 12, 0.6), 0 0 20px 2px rgba(234, 88, 12, 0.4); 
            border-color: rgba(234, 88, 12, 0.5);
        }
    </style>
</head>
<body class="p-4 md:p-10 text-slate-800">
    <div class="max-w-7xl mx-auto">
        
        <!-- Header & Navigation -->
        <div class="mb-10 flex justify-between items-end">
            <div class="flex gap-2">
                <a href="index.php" class="bg-blue-600 px-4 py-2 rounded-xl shadow-sm border border-blue-500 text-white font-bold flex items-center gap-2 hover:bg-blue-700 transition-all group text-xs">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                    Back to Dashboard
                </a>
                <a href="<?= htmlspecialchars($back_url) ?>" class="bg-white px-4 py-2 rounded-xl shadow-sm border border-slate-200 text-slate-500 font-bold flex items-center gap-2 hover:text-blue-600 hover:border-blue-200 transition-all group text-xs">
                    <svg class="w-4 h-4 group-hover:-translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                    Back to Inventory List
                </a>
            </div>
            <div class="text-right">
                <p class="text-[10px] font-black text-slate-300 uppercase tracking-widest">Data Version</p>
                <p class="text-xs font-black text-slate-500">W<?= $device['report_week'] ?> / <?= $device['report_year'] ?></p>
            </div>
        </div>

        <!-- Hero Section -->
        <div class="bg-white rounded-[3rem] p-10 shadow-2xl shadow-slate-200/50 border border-white mb-10 flex flex-col md:flex-row justify-between items-center gap-10">
            <div class="flex-1">
                <div class="flex items-center gap-3 mb-4">
                    <span class="px-4 py-1 bg-slate-800 text-white rounded-full text-[10px] font-black uppercase tracking-widest shadow-md"><?= htmlspecialchars($device['computer_type']) ?></span>
                    <span class="px-4 py-1 bg-blue-600 text-white rounded-full text-[10px] font-black uppercase tracking-widest shadow-lg shadow-blue-200 border border-blue-500"><?= htmlspecialchars($device['bu']) ?></span>
                    <?php 
                        $is_inactive_hero = (trim(strtolower($device['inactive_30_days'])) == 'yes');
                        $hero_badge_bg = $is_inactive_hero ? 'bg-rose-100 text-rose-600 border-rose-200' : 'bg-emerald-100 text-emerald-600 border-emerald-200';
                        $hero_status_text = $is_inactive_hero ? 'Inactive 30+ Days' : 'Active Status';
                    ?>
                    <span class="px-4 py-1 <?= $hero_badge_bg ?> border rounded-full text-[10px] font-black uppercase tracking-widest flex items-center gap-2">
                        <?php if ($is_inactive_hero): ?>
                            <span class="w-2 h-2 bg-rose-500 rounded-full animate-pulse"></span>
                        <?php else: ?>
                            <span class="w-2 h-2 bg-emerald-500 rounded-full"></span>
                        <?php endif; ?>
                        <?= $hero_status_text ?>
                    </span>
                </div>
                <h1 class="text-5xl md:text-6xl font-black tracking-tighter text-slate-900"><?= htmlspecialchars($device['computer_name']) ?></h1>
                <p class="text-lg font-bold text-slate-400 mt-2">Serial No: <span class="text-slate-600"><?= htmlspecialchars($device['serial_no']) ?></span></p>
            </div>
            <div class="w-full md:w-auto flex flex-col gap-4">
                <div class="bg-slate-50 p-6 rounded-[2rem] border border-slate-100 text-center min-w-[180px]">
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Compliance Score</p>
                    <?php 
                        $success_keys = ['Y', 'Yes', 'Compliant', 'Success', 'Active', 'Allowed', 'Healthy'];
                        $check_fields = [
                            $device['joined_approved_domain'],
                            $device['os_eos_status'],
                            $device['patch_healthy'],
                            $device['av_compliant'],
                            $device['firewall_compliant'],
                            $device['standard_admin_only']
                        ];
                        $passed_count = 0;
                        foreach($check_fields as $f) {
                            if (in_array($f, $success_keys)) $passed_count++;
                        }
                        $score_pct = round(($passed_count / 6) * 100);
                        
                        // Dynamic Color
                        $score_color = 'text-rose-500';
                        if ($score_pct == 100) $score_color = 'text-emerald-500';
                        elseif ($score_pct >= 70) $score_color = 'text-blue-600';
                        elseif ($score_pct >= 50) $score_color = 'text-amber-500';
                    ?>
                    <p class="text-5xl font-black <?= $score_color ?>"><?= $score_pct ?>%</p>
                    <p class="text-[9px] font-bold text-slate-400 mt-1 uppercase tracking-tighter"><?= $passed_count ?> OF 6 TOPICS PASSED</p>
                </div>
            </div>
        </div>

        <!-- Information Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            
            <!-- User & Location -->
            <div class="glass-card rounded-[2.5rem] p-8 shadow-xl hover:-translate-y-2 hover:shadow-2xl hover:shadow-blue-500/20 transition-all duration-300 group">
                <h3 class="text-sm font-black text-blue-600 uppercase tracking-widest mb-6 flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                    User & Ownership
                </h3>
                <?= renderRow('Full Name', $device['user_name']) ?>
                <?= renderRow('Logged User', $device['logged_on_user'], true) ?>
                <?= renderRow('Last Active User', $device['last_user'], true) ?>
                <?= renderRow('Company', $device['company']) ?>
                <?= renderRow('Service Team', $device['serviced_by']) ?>
            </div>

            <!-- OS Details -->
            <div class="glass-card rounded-[2.5rem] p-8 shadow-xl hover:-translate-y-2 hover:shadow-2xl hover:shadow-indigo-500/20 transition-all duration-300 group">
                <h3 class="text-sm font-black text-indigo-600 uppercase tracking-widest mb-6 flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                    System & OS Info
                </h3>
                <?= renderRow('OS Name', $device['os_name']) ?>
                <?= renderRow('OS Build', $device['os_build'], true) ?>
                <?= renderRow('OS Release', $device['os_release'], true) ?>
                <?= renderRow('OS Build UBR', $device['os_build_ubr'], true) ?>
                <div class='flex justify-between items-center py-3 border-b border-slate-50'>
                    <span class='text-[11px] font-bold text-slate-400 uppercase tracking-wider'>OS EoS Status</span>
                    <?= getBadgeFull($device['os_eos_status']) ?>
                </div>
            </div>

            <!-- Security Compliance -->
            <div class="glass-card rounded-[2.5rem] p-8 shadow-xl hover:-translate-y-2 hover:shadow-2xl hover:shadow-emerald-500/20 transition-all duration-300 group">
                <h3 class="text-sm font-black text-emerald-600 uppercase tracking-widest mb-6 flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                    Security & Protection
                </h3>
                <div class='flex justify-between items-center py-2 border-b border-slate-50'>
                    <span class='text-[11px] font-bold text-slate-400 uppercase tracking-wider'>Antivirus Status</span>
                    <?= getBadgeFull($device['av_compliant']) ?>
                </div>
                <div class='flex justify-between items-center py-2 border-b border-slate-50'>
                    <span class='text-[11px] font-bold text-slate-400 uppercase tracking-wider'>Firewall Status</span>
                    <?= getBadgeFull($device['firewall_compliant']) ?>
                </div>
                <div class='flex justify-between items-center py-2 border-b border-slate-50'>
                    <span class='text-[11px] font-bold text-slate-400 uppercase tracking-wider'>Patching Status</span>
                    <?= getBadgeFull($device['patch_healthy']) ?>
                </div>
                <div class='flex justify-between items-center py-2 border-b border-slate-50'>
                    <span class='text-[11px] font-bold text-slate-400 uppercase tracking-wider'>Domain Approved</span>
                    <?= getBadgeFull($device['joined_approved_domain']) ?>
                </div>
                <?= renderRow('AV Software', $device['antivirus_name']) ?>
            </div>

            <!-- Admin & Network -->
            <div class="glass-card rounded-[2.5rem] p-8 shadow-xl hover:-translate-y-2 hover:shadow-2xl hover:shadow-slate-500/20 transition-all duration-300 group">
                <h3 class="text-sm font-black text-slate-700 uppercase tracking-widest mb-6 flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                    Network & Access
                </h3>
                <?= renderRow('IP Address', $device['ip_address'], true) ?>
                <?= renderRow('AD Domain', $device['domain_name']) ?>
                <?= renderRow('BitLocker', $device['bitlocker_status']) ?>
                <?= renderRow('USB Permission', $device['usb_permission']) ?>
                <div class='flex justify-between items-center py-3'>
                    <span class='text-[11px] font-bold text-slate-400 uppercase tracking-wider'>Standard Admin</span>
                    <?= getBadgeFull($device['standard_admin_only']) ?>
                </div>
            </div>

            <!-- Maintenance & Uptime -->
            <div class="glass-card rounded-[2.5rem] p-8 shadow-xl hover:-translate-y-2 hover:shadow-2xl hover:shadow-orange-500/20 transition-all duration-300 group">
                <h3 class="text-sm font-black text-orange-600 uppercase tracking-widest mb-6 flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    Uptime & Health
                </h3>
                <?= renderRow('Last Reboot', $device['last_reboot_date'] ? date('d M Y, H:i', strtotime($device['last_reboot_date'])) : 'N/A') ?>
                <div class='flex justify-between items-center border-b border-slate-50 py-3'>
                    <span class='text-[11px] font-bold text-slate-400 uppercase tracking-wider'>Days Since Reboot</span>
                    <span class='text-2xl font-black <?= $device['days_since_last_reboot'] > 30 ? 'text-rose-500' : 'text-slate-800' ?>'><?= $device['days_since_last_reboot'] ?></span>
                </div>
                <div class='flex justify-between items-center py-3 border-b border-slate-50'>
                    <span class='text-[11px] font-bold text-slate-400 uppercase tracking-wider'>Pending Restart</span>
                    <?= getBadgeRestart($device['pending_restart']) ?>
                </div>
                <div class='flex justify-between items-center py-3'>
                    <span class='text-[11px] font-bold text-slate-400 uppercase tracking-wider'>Inactive 30+ Days</span>
                    <?= getBadgeInactive($device['inactive_30_days']) ?>
                </div>
            </div>

            <!-- Patches & Admin Groups -->
            <div class="glass-card rounded-[2.5rem] p-8 shadow-xl hover:-translate-y-2 hover:shadow-2xl hover:shadow-slate-400/20 transition-all duration-300 group bg-slate-50/50">
                <h3 class="text-sm font-black text-slate-400 uppercase tracking-widest mb-6 flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                    Advanced Info
                </h3>
                <div class="mb-4">
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Missing Patches (<?= $device['total_missing_patches'] ?>)</p>
                    <div class="bg-white p-3 rounded-xl border border-slate-100 text-[10px] font-mono text-slate-400 max-h-20 overflow-y-auto">
                        <?= $device['missing_patches_name'] ?: 'None' ?>
                    </div>
                </div>
                <div>
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Admin Members</p>
                    <div class="bg-white p-3 rounded-xl border border-slate-100 text-[10px] font-mono text-slate-400 max-h-20 overflow-y-auto">
                        <?= $device['admin_members'] ?: 'N/A' ?>
                    </div>
                </div>
            </div>

        </div>
        
        <p class="mt-20 text-center text-[10px] font-black text-slate-500 uppercase tracking-[0.6em]">&copy; 2026 EIA COMPLIANCE UNIT | Prepared by Endpoint Management Team</p>
    </div>
</body>
</html>