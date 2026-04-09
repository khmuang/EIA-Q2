<?php
/**
 * EIA Dashboard - Background Cache Worker
 * Purpose: Handle heavy SQL queries in the background to prevent server spikes.
 */
date_default_timezone_set('Asia/Bangkok');

if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line.");
}

// Parse arguments (e.g., php cache_worker.php --type=dashboard)
$options = getopt("", ["type:"]);
$type = $options['type'] ?? 'dashboard';

// Ensure we are working in the script's directory
chdir(__DIR__);

// Logging function
function log_message($msg) {
    file_put_contents('worker_log.txt', "[" . date('Y-m-d H:i:s') . "] " . $msg . "\n", FILE_APPEND);
}

require_once 'config.php';
require_once 'query.php';

$lock_file = "{$type}.lock";
$lock_timeout = 300; // 5 minutes safety timeout

// 1. Check if another instance is already running
if (file_exists($lock_file)) {
    if (time() - filemtime($lock_file) < $lock_timeout) {
        log_message("Task [{$type}] is already running. Skipping.");
        exit;
    }
    log_message("Found stale lock for [{$type}]. Removing it.");
    unlink($lock_file);
}

// 2. Create Lock
file_put_contents($lock_file, time());
log_message("Started background update for: {$type}");

try {
    $clean_query = rtrim(trim($eiaquery), ';');
    $success_keys = "('Y', 'Yes', 'Compliant', 'Success', 'Active', 'Healthy', 'Installed')";

    switch ($type) {
        case 'dashboard':
            log_message("Querying Master Data...");
            $clean_master = rtrim(trim($master_query), ';');
            $stmt = $pdo->query("SELECT * FROM ($clean_master) as m");
            $master_data = $stmt->fetch(PDO::FETCH_ASSOC);
            $current_total = (int)$master_data['total'] ?: 1;
            $s_ended = (int)$master_data['s_ended'];
            
            $topics_mapping_keys = ['Domain'=>'s_domain','OS Status'=>'s_os','Patching'=>'s_patch','Antivirus'=>'s_av','Firewall'=>'s_fw','Admin Rights'=>'s_admin'];
            $topic_results = []; $total_success_sum = 0;
            foreach ($topics_mapping_keys as $label => $key) {
                $s = (int)$master_data[$key];
                
                if ($label === 'Patching') {
                    $effective_total = $current_total - $s_ended;
                    $rate = ($effective_total > 0) ? round(($s / $effective_total) * 100, 1) : 0;
                } else {
                    $rate = round(($s / $current_total) * 100, 1);
                }

                $p = $current_total - $s; // Show total risk
                $total_success_sum += $s;
                
                $color_tail = 'rose-500'; $bg_tail = 'bg-rose-50';
                if ($rate >= 81) { $color_tail = 'emerald-500'; $bg_tail = 'bg-emerald-50'; }
                elseif ($rate >= 70) { $color_tail = 'blue-600'; $bg_tail = 'bg-blue-50'; }
                elseif ($rate >= 50) { $color_tail = 'amber-500'; $bg_tail = 'bg-amber-50'; }
                
                $topic_results[] = ['label'=>$label,'success'=>$s,'pending'=>$p,'rate'=>$rate,'color_tail'=>$color_tail,'bg_tail'=>$bg_tail];
            }

            log_message("Querying Team Density...");
            $stmt_teams = $pdo->query("SELECT COALESCE(NULLIF(`Serviced By`, ''), 'None') as serviced_by, COUNT(*) as count FROM ($clean_query) as base_data WHERE `Serviced By` NOT IN ('0', 'Desktop', 'Laptop') OR `Serviced By` IS NULL GROUP BY COALESCE(NULLIF(`Serviced By`, ''), 'None')");
            $serviced_data = $stmt_teams->fetchAll(PDO::FETCH_ASSOC);
            
            log_message("Querying Top BUs...");
            $stmt_bu = $pdo->query("SELECT `BU` as bu, COUNT(*) as success_count FROM ($clean_query) as base_data WHERE `Patch Healthy` IN $success_keys AND `BU` != '' AND `BU` IS NOT NULL GROUP BY `BU` ORDER BY success_count DESC LIMIT 5");
            $bu_data = $stmt_bu->fetchAll(PDO::FETCH_ASSOC);

            $payload = [
                'current_total' => $current_total,
                's_ended' => $s_ended,
                'g_full' => (int)$master_data['g_full'], 'g_minor' => (int)$master_data['g_minor'], 'g_at_risk' => (int)$master_data['g_at_risk'], 'g_critical' => (int)$master_data['g_critical'],
                'p_full' => ($master_data['g_full']/$current_total)*100, 'p_minor' => ($master_data['g_minor']/$current_total)*100, 'p_at_risk' => ($master_data['g_at_risk']/$current_total)*100, 'p_critical' => ($master_data['g_critical']/$current_total)*100,
                's1' => ($master_data['g_full']/$current_total)*100, 's2' => (($master_data['g_full']+$master_data['g_minor'])/$current_total)*100, 's3' => (($master_data['g_full']+$master_data['g_minor']+$master_data['g_at_risk'])/$current_total)*100,
                'topic_results' => $topic_results, 'total_success_sum' => $total_success_sum, 'overall_rate' => round(($total_success_sum / ($current_total * 6)) * 100, 1),
                'serviced_data' => $serviced_data, 'bu_data' => $bu_data, 'max_bu_val' => !empty($bu_data) ? max(array_column($bu_data, 'success_count')) : 1,
                'week' => date("W"), 'year' => date("Y"), 'last_updated' => date("d M Y, H:i")
            ];
            $payload_json = json_encode($payload);
            file_put_contents('dashboard_cache.json.tmp', $payload_json);
            rename('dashboard_cache.json.tmp', 'dashboard_cache.json');
            log_message("Dashboard cache updated successfully.");
            break;

        case 'admin_audit':
            log_message("Updating admin_audit...");
            $sql = "SELECT `Computer Name`, `Serial no.`, `BU`, `Serviced By` as `Service Team`, `User name` as `Full Name`, `Members of Administrator Group`, `Standard Admin Only` FROM ($clean_query) as base_data WHERE `Standard Admin Only` NOT IN $success_keys";
            $items = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            $payload_json = json_encode(['items' => $items, 'total_risk' => count($items), 'last_updated' => date("d M Y, H:i")]);
            file_put_contents('admin_audit_cache.json.tmp', $payload_json);
            rename('admin_audit_cache.json.tmp', 'admin_audit_cache.json');
            log_message("Admin_audit cache updated.");
            break;

        case 'patch_insight':
            log_message("Updating patch_insight...");
            $sql_top = "SELECT `BU`, SUM(`Total Missing Critical Patches`) as total_missing FROM ($clean_query) as base_data GROUP BY `BU` ORDER BY total_missing DESC LIMIT 10";
            $summary_data = $pdo->query($sql_top)->fetchAll(PDO::FETCH_ASSOC);
            $sql_list = "SELECT `Computer Name`, `Serial no.`, `BU`, `Serviced By` as `Service Team`, `User name` as `Full Name`, `Total Missing Critical Patches`, `Missing Critical Patches Name` FROM ($clean_query) as base_data WHERE `Total Missing Critical Patches` > 0 ORDER BY `Total Missing Critical Patches` DESC";
            $all_items = $pdo->query($sql_list)->fetchAll(PDO::FETCH_ASSOC);
            $payload_json = json_encode(['summary_data' => $summary_data, 'all_items' => $all_items, 'total_items' => count($all_items), 'last_updated' => date("d M Y, H:i")]);
            file_put_contents('patch_insight_cache.json.tmp', $payload_json);
            rename('patch_insight_cache.json.tmp', 'patch_insight_cache.json');
            log_message("Patch_insight cache updated.");
            break;

        case 'inactive_assets':
            log_message("Updating inactive_assets...");
            $sql = "SELECT `Computer Name`, `Serial no.`, `BU`, `Serviced By` as `Service Team`, `User name` as `Full Name`, `Last Reboot Date`, `Inactive 30+ Days` FROM ($clean_query) as base_data WHERE `Inactive 30+ Days` IN ('Y', 'Yes') ORDER BY `Last Reboot Date` ASC";
            $items = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            $payload_json = json_encode(['items' => $items, 'total_inactive' => count($items), 'last_updated' => date("d M Y, H:i")]);
            file_put_contents('inactive_assets_cache.json.tmp', $payload_json);
            rename('inactive_assets_cache.json.tmp', 'inactive_assets_cache.json');
            log_message("Inactive_assets cache updated.");
            break;

        case 'bu_ranking':
            log_message("Updating bu_ranking...");
            $sql = "SELECT BU, 
                    COUNT(*) as total_devices,
                    SUM(CASE WHEN `Joined Approved Domain` = 'Yes' THEN 1 ELSE 0 END +
                        CASE WHEN `OS End of Support Status` = 'Active' THEN 1 ELSE 0 END +
                        CASE WHEN `Patch Healthy` = 'Healthy' THEN 1 ELSE 0 END +
                        CASE WHEN `Antivirus Compliant` = 'Yes' THEN 1 ELSE 0 END +
                        CASE WHEN `Firewall Compliant` = 'Yes' THEN 1 ELSE 0 END +
                        CASE WHEN `Standard Admin Only` = 'Yes' THEN 1 ELSE 0 END
                    ) as total_success_points
                    FROM ($clean_query) as base_data
                    WHERE BU IS NOT NULL AND BU != ''
                    GROUP BY BU
                    ORDER BY (
                        SUM(CASE WHEN `Joined Approved Domain` = 'Yes' THEN 1 ELSE 0 END +
                            CASE WHEN `OS End of Support Status` = 'Active' THEN 1 ELSE 0 END +
                            CASE WHEN `Patch Healthy` = 'Healthy' THEN 1 ELSE 0 END +
                            CASE WHEN `Antivirus Compliant` = 'Yes' THEN 1 ELSE 0 END +
                            CASE WHEN `Firewall Compliant` = 'Yes' THEN 1 ELSE 0 END +
                            CASE WHEN `Standard Admin Only` = 'Yes' THEN 1 ELSE 0 END
                        ) / (COUNT(*) * 6)
                    ) DESC";
            $bu_ranking = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            $payload_json = json_encode(['ranking' => $bu_ranking, 'last_updated' => date("d M Y, H:i")]);
            file_put_contents('bu_ranking_cache.json.tmp', $payload_json);
            rename('bu_ranking_cache.json.tmp', 'bu_ranking_cache.json');
            log_message("BU_ranking cache updated.");
            break;
    }
    log_message("Successfully updated: {$type}");
} catch (Exception $e) {
    log_message("Error updating [{$type}]: " . $e->getMessage());
} finally {
    if (file_exists($lock_file)) unlink($lock_file);
}
