<?php
require_once 'config.php';
require_once 'query.php';

try {
    $sql = "SELECT 
        COUNT(*) as total, 
        SUM(CASE WHEN `Patch Healthy` = 'Healthy' THEN 1 ELSE 0 END) as patch_success,
        SUM(CASE WHEN `OS End of Support Status` = 'Ended' THEN 1 ELSE 0 END) as os_ended
        FROM ($eiaquery) as base_data";

    $stmt = $pdo->query($sql);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    $total = (int)$data['total'];
    $success = (int)$data['patch_success'];
    $ended = (int)$data['os_ended'];

    $rate_old = ($success / $total) * 100;
    $manageable_total = $total - $ended;
    $rate_new = ($manageable_total > 0) ? ($success / $manageable_total) * 100 : 0;

    echo "--- COMPARISON REPORT (Based on query.php) ---\n";
    echo "Total Assets (Grand Total) : " . number_format($total) . " units\n";
    echo "OS Status 'Ended'          : " . number_format($ended) . " units\n";
    echo "Net Manageable Assets      : " . number_format($manageable_total) . " units\n";
    echo "Patch Success (Healthy)    : " . number_format($success) . " units\n";
    echo "-------------------------------------------\n";
    echo "Case 1: Include Ended (Current)  -> " . round($rate_old, 2) . "%\n";
    echo "Case 2: Exclude Ended (Proposed) -> " . round($rate_new, 2) . "%\n";
    echo "-------------------------------------------\n";
    echo "Potential KPI Improvement: +" . round($rate_new - $rate_old, 2) . "%\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>