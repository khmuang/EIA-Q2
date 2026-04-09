<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// 1. Database Connection & Query Include
require_once 'config.php';
require_once 'query.php';

// 2. Fetch Device Data via Live Query
$name = isset($_GET['name']) ? trim($_GET['name']) : '';
$back_url = isset($_GET['back']) ? $_GET['back'] : 'inventory_list.php';

if (empty($name)) { die("<div style='padding:20px; font-family:sans-serif;'><h2 style='color:red;'>Device name not provided.</h2><a href='inventory_list.php'>Back to List</a></div>"); }

try {
    $clean_query = rtrim(trim($eiaquery), ';');
    $sql = "SELECT * FROM ($clean_query) as base_data WHERE `Computer Name` = ? LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$name]);
    $raw_device = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (\PDOException $e) {
    die("<div style='color:red; font-family:sans-serif; padding:20px;'><strong>Database Query Failed:</strong> " . htmlspecialchars($e->getMessage()) . "</div>");
}

if (!$raw_device) { die("<div style='padding:20px; font-family:sans-serif;'><h2 style='color:red;'>Device not found</h2><p>Cannot find computer: ".htmlspecialchars($name)."</p><br><a href='inventory_list.php'>Back to List</a></div>"); }

// 3. Map Live Query Keys to Template Keys exactly
$device = [
    'computer_name' => $raw_device['Computer Name'] ?? 'N/A',
    'serial_no' => $raw_device['Serial no.'] ?? 'N/A',
    'computer_type' => $raw_device['Computer Type'] ?? 'N/A',
    'bu' => $raw_device['BU'] ?? 'N/A',
    'inactive_30_days' => $raw_device['Inactive 30+ Days'] ?? 'No',
    'joined_approved_domain' => $raw_device['Joined Approved Domain'] ?? 'No',
    'os_eos_status' => $raw_device['OS End of Support Status'] ?? 'Ended',
    'patch_healthy' => $raw_device['Patch Healthy'] ?? 'Pending',
    'av_compliant' => $raw_device['Antivirus Compliant'] ?? 'No',
    'firewall_compliant' => $raw_device['Firewall Compliant'] ?? 'No',
    'standard_admin_only' => $raw_device['Standard Admin Only'] ?? 'No',
    'user_name' => $raw_device['User name'] ?? 'N/A',
    'logged_on_user' => $raw_device['Logged on user'] ?? 'N/A',
    'last_user' => $raw_device['Last User'] ?? 'N/A',
    'company' => $raw_device['Company'] ?? 'N/A',
    'serviced_by' => $raw_device['Serviced By'] ?? 'None',
    'os_name' => $raw_device['OS Name'] ?? 'N/A',
    'os_build' => $raw_device['OS Build'] ?? 'N/A',
    'os_release' => $raw_device['OS Release'] ?? 'N/A',
    'os_build_ubr' => $raw_device['OS Build.UBR'] ?? 'N/A',
    'antivirus_name' => $raw_device['Antivirus'] ?? 'N/A',
    'ip_address' => $raw_device['IP Address'] ?? 'N/A',
    'domain_name' => $raw_device['Domain'] ?? 'N/A',
    'bitlocker_status' => $raw_device['BIT Locker Status'] ?? 'N/A',
    'usb_permission' => $raw_device['USB Storage Permission'] ?? 'N/A',
    'last_reboot_date' => $raw_device['Last Reboot Date'] ?? 'N/A',
    'days_since_last_reboot' => $raw_device['Days Since Last Reboot'] ?? '0',
    'pending_restart' => $raw_device['Pending Restart'] ?? 'No',
    'glpi_agent_status' => $raw_device['GLPI Agent Status'] ?? 'Not Install',
    'total_missing_patches' => $raw_device['Total Missing Critical Patches'] ?? '0',
    'missing_patches_name' => $raw_device['Missing Critical Patches Name'] ?? 'None',
    'admin_members' => $raw_device['Members of Administrator Group'] ?? 'N/A',
    'report_week' => date("W"),
    'report_year' => date("Y")
];

// Helper for labels
function renderRow($label, $val, $is_code = false) {
    if ($val === null || $val === '') $val = 'N/A';
    $class = $is_code ? 'font-mono text-slate-400' : 'font-black text-slate-800';
    return "
    <div class='flex justify-between items-center border-b border-slate-50 py-3 px-1 hover:bg-slate-50/50 transition-colors'>
        <span class='text-[11px] font-bold text-slate-400 uppercase tracking-wider'>$label</span>
        <span class='text-sm $class text-right max-w-[250px] truncate' title='$val'>$val</span>
    </div>";
}

function getBadgeFull($val) {
    if ($val === null || $val === '') $val = 'No';
    $success_vals = ['Y', 'Yes', 'Compliant', 'Success', 'Active', 'Allowed', 'Healthy', 'Installed'];
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
    <!-- Tailwind CSS (Production Ready) -->
    <link href="dist/output.css" rel="stylesheet">
    <style>
        body { 
            font-family: 'Sarabun', sans-serif; 
            background-image: linear-gradient(120deg, #a1c4fd 0%, #c2e9fb 100%);
            min-height: 100vh;
            background-attachment: fixed;
        }
        .glass-card { 
            background: rgba(255, 255, 255, 0.85); 
            backdrop-filter: blur(5px); 
            -webkit-backdrop-filter: blur(5px);
            border: 1px solid rgba(255, 255, 255, 0.9); 
            box-shadow: 0 10px 12px -5px rgba(30, 64, 175, 0.1), inset 0 0 5px rgba(255, 255, 255, 0.5);
        }
        
        /* Ultra Vibrant & Deep Shadows (150% Enhanced) - Adjusted 50% Fuzziness */
        .hover-shadow-blue:hover { 
            box-shadow: 0 65px 67px -20px rgba(37, 99, 235, 0.95), 0 35px 37px -15px rgba(37, 99, 235, 0.75), 0 0 15px 4px rgba(37, 99, 235, 0.5); 
            border-color: rgba(37, 99, 235, 0.6);
            transform: translateY(-8px) scale(1.01);
        }
        .hover-shadow-indigo:hover { 
            box-shadow: 0 65px 67px -20px rgba(79, 70, 229, 0.95), 0 35px 37px -15px rgba(79, 70, 229, 0.75), 0 0 15px 4px rgba(79, 70, 229, 0.5); 
            border-color: rgba(79, 70, 229, 0.6);
            transform: translateY(-8px) scale(1.01);
        }
        .hover-shadow-emerald:hover { 
            box-shadow: 0 65px 67px -20px rgba(5, 150, 105, 0.95), 0 35px 37px -15px rgba(5, 150, 105, 0.75), 0 0 15px 4px rgba(5, 150, 105, 0.5); 
            border-color: rgba(5, 150, 105, 0.6);
            transform: translateY(-8px) scale(1.01);
        }
        .hover-shadow-slate:hover { 
            box-shadow: 0 65px 67px -20px rgba(30, 41, 59, 0.8), 0 35px 37px -15px rgba(30, 41, 59, 0.6), 0 0 15px 4px rgba(30, 41, 59, 0.4); 
            border-color: rgba(30, 41, 59, 0.5);
            transform: translateY(-8px) scale(1.01);
        }
        .hover-shadow-orange:hover { 
            box-shadow: 0 65px 67px -20px rgba(234, 88, 12, 0.95), 0 35px 37px -15px rgba(234, 88, 12, 0.75), 0 0 15px 4px rgba(234, 88, 12, 0.5); 
            border-color: rgba(234, 88, 12, 0.6);
            transform: translateY(-8px) scale(1.01);
        }
    </style>
</head>
<body class="p-4 md:p-10 text-slate-800">
    <div class="max-w-7xl mx-auto">
        
        <!-- Hero Section -->
        <div class="bg-white/90 backdrop-blur-xl rounded-xl p-5 md:p-8 shadow-2xl shadow-blue-900/10 border border-white mb-8 flex flex-col xl:flex-row justify-between items-center gap-8 relative overflow-hidden">
            <!-- Faint Computer Background Image -->
            <div class="img-blur">
                <img src="Image/bg-computer-7.jpg" alt="Computer Background" class="w-full h-full object-cover">
            </div>

            <div class="flex-1 w-full relative z-10">
                <div class="flex flex-wrap items-center gap-3 mb-3">
                    <span class="px-3 py-1 bg-slate-800 text-white rounded-full text-xs font-black uppercase tracking-widest shadow-sm"><?= htmlspecialchars($device['computer_type']) ?></span>
                    <span class="px-3 py-1 bg-blue-600 text-white rounded-full text-xs font-black uppercase tracking-widest shadow-sm border border-blue-500"><?= htmlspecialchars($device['bu']) ?></span>
                    <?php 
                        $is_inactive_hero = (trim(strtolower($device['inactive_30_days'])) == 'yes');
                        $hero_badge_bg = $is_inactive_hero ? 'bg-rose-100 text-rose-600 border-rose-200' : 'bg-emerald-100 text-emerald-600 border-emerald-200';
                        $hero_status_text = $is_inactive_hero ? 'Inactive 30+ Days' : 'Active Status';
                    ?>
                    <span class="px-3 py-1 <?= $hero_badge_bg ?> border rounded-full text-xs font-black uppercase tracking-widest flex items-center gap-1.5 shadow-sm">
                        <?php if ($is_inactive_hero): ?>
                            <span class="w-2 h-2 bg-rose-500 rounded-full animate-pulse shadow-md"></span>
                        <?php else: ?>
                            <span class="w-2 h-2 bg-emerald-500 rounded-full shadow-md"></span>
                        <?php endif; ?>
                        <?= $hero_status_text ?>
                    </span>
                </div>
                <h1 class="text-3xl md:text-5xl font-black tracking-tight text-slate-800 mb-1"><?= htmlspecialchars($device['computer_name']) ?></h1>
                <p class="text-xs md:text-sm font-bold text-slate-500 flex items-center gap-2">Serial No: <span class="bg-slate-100/80 text-slate-600 px-2.5 py-0.5 rounded-lg border border-slate-200 text-xs font-black tracking-widest"><?= htmlspecialchars($device['serial_no']) ?></span></p>
                
                <!-- Enhanced Quick Summary -->
                <div class="mt-6 p-5 bg-slate-50/80 dark:bg-slate-900/30 rounded-2xl border border-slate-200/50 shadow-inner max-w-4xl">
                    <div class="flex items-center gap-2 mb-3 border-b border-slate-200/50 pb-2">
                        <span class="text-xs font-black text-blue-600 uppercase tracking-widest">Quick Compliance Insight</span>
                    </div>
                    <?php 
                        $success_words = ['compliant', 'yes', 'y', 'active', 'success', 'healthy', 'installed', 'allowed'];
                        $is_os_ok = in_array(trim(strtolower($device['os_eos_status'])), $success_words);
                        $is_av_ok = in_array(trim(strtolower($device['av_compliant'])), $success_words);
                        $is_fw_ok = in_array(trim(strtolower($device['firewall_compliant'])), $success_words);
                        $is_patch_ok = in_array(trim(strtolower($device['patch_healthy'])), $success_words);
                        $is_domain_ok = in_array(trim(strtolower($device['joined_approved_domain'])), $success_words);
                        $is_admin_ok = in_array(trim(strtolower($device['standard_admin_only'])), $success_words);
                        $is_restart_ok = (trim(strtolower($device['pending_restart'])) == 'no');

                        // Calculate score for summary
                        $p_count = 0;
                        foreach([$is_os_ok, $is_av_ok, $is_fw_ok, $is_patch_ok, $is_domain_ok, $is_admin_ok] as $st) if($st) $p_count++;
                        $s_pct = round(($p_count / 6) * 100);
                        
                        $conclusion = ""; $concl_color = "";
                        if ($s_pct == 100) { $conclusion = "มีความปลอดภัยสูง"; $concl_color = "text-emerald-600"; }
                        elseif ($s_pct >= 80) { $conclusion = "เกือบจะปลอดภัยแล้ว"; $concl_color = "text-blue-600"; }
                        elseif ($s_pct >= 50) { $conclusion = "ต้องได้รับการแก้ไขด่วน"; $concl_color = "text-amber-600"; }
                        else { $conclusion = "เจ้าหน้าที่เข้าแก้ไขโดยเร็ว"; $concl_color = "text-rose-600"; }
                    ?>
                    <p class="text-xs md:text-sm leading-loose text-slate-600 dark:text-slate-300 font-bold">
                        คอมพิวเตอร์ <span class="text-blue-700 dark:text-blue-400 font-black px-1">k. <?= htmlspecialchars($device['user_name'] ?: '-') ?></span> 
                        มี OS status <span class="<?= $is_os_ok ? 'text-emerald-600' : 'text-rose-600' ?> font-black underline decoration-2"><?= htmlspecialchars($device['os_eos_status']) ?></span>, 
                        มี AV status <span class="<?= $is_av_ok ? 'text-emerald-600' : 'text-rose-600' ?> font-black underline decoration-2"><?= htmlspecialchars($device['av_compliant']) ?></span>, 
                        มี Firewall status <span class="<?= $is_fw_ok ? 'text-emerald-600' : 'text-rose-600' ?> font-black underline decoration-2"><?= htmlspecialchars($device['firewall_compliant']) ?></span>, 
                        มี Patch <span class="<?= $is_patch_ok ? 'text-emerald-600' : 'text-rose-600' ?> font-black underline decoration-2"><?= htmlspecialchars($device['patch_healthy']) ?></span>, 
                        พบ Join domain <span class="<?= $is_domain_ok ? 'text-emerald-600' : 'text-rose-600' ?> font-black underline decoration-2"><?= htmlspecialchars($device['joined_approved_domain']) ?></span>, 
                        พบว่า Std Admin <span class="<?= $is_admin_ok ? 'text-emerald-600' : 'text-rose-600' ?> font-black underline decoration-2"><?= htmlspecialchars($device['standard_admin_only']) ?></span> 
                        และ รอ Restart เป็น <span class="<?= $is_restart_ok ? 'text-emerald-600' : 'text-rose-600' ?> font-black underline decoration-2"><?= htmlspecialchars($device['pending_restart']) ?></span>
                        <span class="ml-2 px-3 py-1 bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm inline-block">
                            สรุป : <span class="<?= $concl_color ?> font-black"><?= $conclusion ?></span>
                        </span>
                    </p>
                </div>
            </div>
            
            <div class="w-full xl:w-auto flex flex-col md:flex-row xl:flex-col gap-4 items-center xl:items-end relative z-10">
                <div class="flex gap-2 w-full justify-center md:justify-start xl:justify-end">
                    <a href="index.php" class="bg-blue-600 px-4 py-2 rounded-xl shadow-lg border border-blue-500 text-white font-bold flex items-center gap-2 hover:bg-blue-700 transition-all group text-xs uppercase tracking-widest">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                        Back to Dashboard
                    </a>
                    <a href="<?= htmlspecialchars($back_url) ?>" class="bg-white/80 backdrop-blur-sm px-4 py-2 rounded-xl shadow-md border border-slate-200 text-slate-500 font-bold flex items-center gap-2 hover:text-blue-600 hover:border-blue-300 transition-all group text-xs uppercase tracking-widest">
                        <svg class="w-4 h-4 group-hover:-translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                        Inventory List
                    </a>
                </div>

                <!-- Compliance Score Card -->
                <div class="bg-slate-50/80 p-4 md:px-8 md:py-4 rounded-3xl border border-slate-100 text-center min-w-[200px] shadow-inner relative overflow-hidden backdrop-blur-sm flex flex-col items-center">
                    <p class="text-xs font-black text-slate-400 uppercase tracking-widest mb-1 relative z-10">Compliance Score</p>
                    <?php 
                        $success_keys = ['Y', 'Yes', 'Compliant', 'Success', 'Active', 'Allowed', 'Healthy', 'Installed'];
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
                        
                        $score_color = 'text-rose-500'; $bg_glow = 'bg-rose-50';
                        if ($score_pct == 100) { $score_color = 'text-emerald-500'; $bg_glow = 'bg-emerald-50'; }
                        elseif ($score_pct >= 70) { $score_color = 'text-blue-600'; $bg_glow = 'bg-blue-50'; }
                        elseif ($score_pct >= 50) { $score_color = 'text-amber-500'; $bg_glow = 'bg-amber-50'; }
                    ?>
                    <div class="absolute inset-0 <?= $bg_glow ?> opacity-40"></div>
                    <p class="text-4xl font-black <?= $score_color ?> relative z-10 drop-shadow-sm leading-none"><?= $score_pct ?><span class="text-lg ml-0.5 opacity-50">%</span></p>
                    <p class="text-[9px] font-black text-slate-400 mt-1.5 uppercase tracking-widest relative z-10 bg-white/80 px-3 py-0.5 rounded-full shadow-sm border border-slate-100"><?= $passed_count ?> OF 6 PASSED</p>
                </div>
            </div>
        </div>

        <!-- Information Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            
            <!-- User & Location -->
            <div class="glass-card relative rounded-xl p-8 shadow-xl hover-shadow-blue transition-all duration-300 group">
                <!-- Hover Indicator -->
                <div class="absolute top-6 right-6 w-10 h-10 bg-gradient-to-tr from-white/60 to-white/20 rounded-full border border-white shadow-lg flex items-center justify-center opacity-0 group-hover:opacity-100 transform scale-50 group-hover:scale-100 transition-all duration-500 pointer-events-none z-10">
                    <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"></path></svg>
                </div>
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
            <div class="glass-card relative rounded-xl p-8 shadow-xl hover-shadow-indigo transition-all duration-300 group">
                <!-- Hover Indicator -->
                <div class="absolute top-6 right-6 w-10 h-10 bg-gradient-to-tr from-white/60 to-white/20 rounded-full border border-white shadow-lg flex items-center justify-center opacity-0 group-hover:opacity-100 transform scale-50 group-hover:scale-100 transition-all duration-500 pointer-events-none z-10">
                    <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"></path></svg>
                </div>
                <h3 class="text-sm font-black text-indigo-600 uppercase tracking-widest mb-6 flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 00-2-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                    System & OS Info
                </h3>
                <?= renderRow('OS Name', $device['os_name']) ?>
                <?= renderRow('OS Build', $device['os_build'], true) ?>
                <?= renderRow('OS Release', $device['os_release'], true) ?>
                <?= renderRow('OS Build UBR', $device['os_build_ubr'], true) ?>
                <div class='flex justify-between items-center py-3 border-b border-slate-50'>
                    <span class='text-xs font-bold text-slate-400 uppercase tracking-wider'>OS EoS Status</span>
                    <?= getBadgeFull($device['os_eos_status']) ?>
                </div>
            </div>

            <!-- Security Compliance -->
            <div class="glass-card relative rounded-xl p-8 shadow-xl hover-shadow-emerald transition-all duration-300 group">
                <!-- Hover Indicator -->
                <div class="absolute top-6 right-6 w-10 h-10 bg-gradient-to-tr from-white/60 to-white/20 rounded-full border border-white shadow-lg flex items-center justify-center opacity-0 group-hover:opacity-100 transform scale-50 group-hover:scale-100 transition-all duration-500 pointer-events-none z-10">
                    <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"></path></svg>
                </div>
                <h3 class="text-sm font-black text-emerald-600 uppercase tracking-widest mb-6 flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                    Security & Protection
                </h3>
                <div class='flex justify-between items-center py-2 border-b border-slate-50'>
                    <span class='text-xs font-bold text-slate-400 uppercase tracking-wider'>Antivirus Status</span>
                    <?= getBadgeFull($device['av_compliant']) ?>
                </div>
                <div class='flex justify-between items-center py-2 border-b border-slate-50'>
                    <span class='text-xs font-bold text-slate-400 uppercase tracking-wider'>Firewall Status</span>
                    <?= getBadgeFull($device['firewall_compliant']) ?>
                </div>
                <div class='flex justify-between items-center py-2 border-b border-slate-50'>
                    <span class='text-xs font-bold text-slate-400 uppercase tracking-wider'>Patching Status</span>
                    <?= getBadgeFull($device['patch_healthy']) ?>
                </div>
                <div class='flex justify-between items-center py-2 border-b border-slate-50'>
                    <span class='text-xs font-bold text-slate-400 uppercase tracking-wider'>Domain Approved</span>
                    <?= getBadgeFull($device['joined_approved_domain']) ?>
                </div>
                <?= renderRow('AV Software', $device['antivirus_name']) ?>
            </div>

            <!-- Admin & Network -->
            <div class="glass-card relative rounded-xl p-8 shadow-xl hover-shadow-slate transition-all duration-300 group">
                <!-- Hover Indicator -->
                <div class="absolute top-6 right-6 w-10 h-10 bg-gradient-to-tr from-white/60 to-white/20 rounded-full border border-white shadow-lg flex items-center justify-center opacity-0 group-hover:opacity-100 transform scale-50 group-hover:scale-100 transition-all duration-500 pointer-events-none z-10">
                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"></path></svg>
                </div>
                <h3 class="text-sm font-black text-slate-700 uppercase tracking-widest mb-6 flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                    Network & Access
                </h3>
                <?= renderRow('IP Address', $device['ip_address'], true) ?>
                <?= renderRow('AD Domain', $device['domain_name']) ?>
                <?= renderRow('BitLocker', $device['bitlocker_status']) ?>
                <?= renderRow('USB Permission', $device['usb_permission']) ?>
                <div class='flex justify-between items-center py-3'>
                    <span class='text-xs font-bold text-slate-400 uppercase tracking-wider'>Standard Admin</span>
                    <?= getBadgeFull($device['standard_admin_only']) ?>
                </div>
            </div>

            <!-- Maintenance & Uptime -->
            <div class="glass-card relative rounded-xl p-8 shadow-xl hover-shadow-orange transition-all duration-300 group">
                <!-- Hover Indicator -->
                <div class="absolute top-6 right-6 w-10 h-10 bg-gradient-to-tr from-white/60 to-white/20 rounded-full border border-white shadow-lg flex items-center justify-center opacity-0 group-hover:opacity-100 transform scale-50 group-hover:scale-100 transition-all duration-500 pointer-events-none z-10">
                    <svg class="w-4 h-4 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"></path></svg>
                </div>
                <h3 class="text-sm font-black text-orange-600 uppercase tracking-widest mb-6 flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    Uptime & Health
                </h3>
                <?php 
                    $reboot_display = 'N/A';
                    $raw_reboot = trim($device['last_reboot_date'] ?? 'N/A');
                    if ($raw_reboot !== '' && $raw_reboot !== 'N/A') {
                        $ts = strtotime($raw_reboot);
                        if ($ts !== false) {
                            $reboot_display = date('d M Y, H:i', $ts);
                        }
                    }
                ?>
                <?= renderRow('Last Reboot', $reboot_display) ?>
                <div class='flex justify-between items-center border-b border-slate-50 py-3'>
                    <span class='text-xs font-bold text-slate-400 uppercase tracking-wider'>Days Since Reboot</span>
                    <span class='text-2xl font-black <?= (int)$device['days_since_last_reboot'] > 30 ? 'text-rose-500' : 'text-slate-800' ?>'><?= $device['days_since_last_reboot'] ?></span>
                </div>
                <div class='flex justify-between items-center py-3 border-b border-slate-50'>
                    <span class='text-xs font-bold text-slate-400 uppercase tracking-wider'>Pending Restart</span>
                    <?= getBadgeRestart($device['pending_restart']) ?>
                </div>
                <div class='flex justify-between items-center py-3 border-b border-slate-50'>
                    <span class='text-xs font-bold text-slate-400 uppercase tracking-wider'>Inactive 30+ Days</span>
                    <?= getBadgeInactive($device['inactive_30_days']) ?>
                </div>
                <div class='flex justify-between items-center py-3'>
                    <span class='text-xs font-bold text-slate-400 uppercase tracking-wider'>GLPI Agent Status</span>
                    <?php 
                        $glpi_bg = ($device['glpi_agent_status'] == 'Installed') ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600';
                    ?>
                    <span class='text-xs font-black uppercase tracking-widest <?= $glpi_bg ?> px-3 py-1 rounded-full border border-white shadow-sm'><?= htmlspecialchars($device['glpi_agent_status']) ?></span>
                </div>
            </div>

            <!-- Patches & Admin Groups -->
            <div class="glass-card relative rounded-xl p-8 shadow-xl hover-shadow-slate transition-all duration-300 group bg-slate-50/50">
                <!-- Hover Indicator -->
                <div class="absolute top-6 right-6 w-10 h-10 bg-gradient-to-tr from-white/60 to-white/20 rounded-full border border-white shadow-lg flex items-center justify-center opacity-0 group-hover:opacity-100 transform scale-50 group-hover:scale-100 transition-all duration-500 pointer-events-none z-10">
                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"></path></svg>
                </div>
                <h3 class="text-sm font-black text-slate-400 uppercase tracking-widest mb-6 flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                    Advanced Info
                </h3>
                <div class="mb-4">
                    <p class="text-xs font-black text-slate-400 uppercase tracking-widest mb-2 flex justify-between">
                        Missing Patches
                        <?php 
                            $patch_count = (int)$device['total_missing_patches'];
                            $badge_class = ($patch_count > 0) ? 'bg-rose-100 text-rose-600 border-rose-200' : 'bg-slate-100 text-slate-500 border-slate-200';
                        ?>
                        <span class="<?= $badge_class ?> px-2.5 py-0.5 rounded-full border shadow-inner transition-colors duration-300"><?= $patch_count ?> items</span>
                    </p>
                    <div class="bg-white p-4 rounded-2xl border border-slate-100 text-xs font-mono text-slate-500 max-h-24 overflow-y-auto shadow-inner leading-relaxed">
                        <?= htmlspecialchars($device['missing_patches_name'] ?: 'None') ?>
                    </div>
                </div>
                <div>
                    <p class="text-xs font-black text-slate-400 uppercase tracking-widest mb-2">Admin Members</p>
                    <div class="bg-white p-4 rounded-2xl border border-slate-100 text-sm font-mono text-slate-700 max-h-32 overflow-y-auto shadow-inner leading-relaxed">
                        <?= htmlspecialchars($device['admin_members'] ?: 'N/A') ?>
                    </div>
                </div>
            </div>

        </div>
        
        <p class="mt-20 text-center text-xs font-black text-slate-500 uppercase tracking-widest">&copy; 2026 EIA COMPLIANCE UNIT | Prepared by Endpoint Management Team</p>
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
       });
    </script>
</body>
</html>
