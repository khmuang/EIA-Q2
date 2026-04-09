<?php
require_once 'config.php';
require_once 'query.php';

$clean_query = rtrim(trim($eiaquery), ';');

$team_sql = "
    SELECT 
        COALESCE(NULLIF(`Serviced By`, ''), 'None') as team,
        COUNT(*) as total_devices,
        SUM(
            CASE WHEN `Joined Approved Domain` = 'Yes' THEN 1 ELSE 0 END +
            CASE WHEN `OS End of Support Status` = 'Active' THEN 1 ELSE 0 END +
            CASE WHEN `Patch Healthy` = 'Healthy' THEN 1 ELSE 0 END +
            CASE WHEN `Antivirus Compliant` = 'Yes' THEN 1 ELSE 0 END +
            CASE WHEN `Firewall Compliant` = 'Yes' THEN 1 ELSE 0 END +
            CASE WHEN `Standard Admin Only` = 'Yes' THEN 1 ELSE 0 END
        ) as total_points
    FROM ($clean_query) as base_data
    WHERE `Serviced By` NOT IN ('0', 'Desktop', 'Laptop', 'RIS No service') AND `Serviced By` IS NOT NULL
    GROUP BY `Serviced By`
    ORDER BY (SUM(
            CASE WHEN `Joined Approved Domain` = 'Yes' THEN 1 ELSE 0 END +
            CASE WHEN `OS End of Support Status` = 'Active' THEN 1 ELSE 0 END +
            CASE WHEN `Patch Healthy` = 'Healthy' THEN 1 ELSE 0 END +
            CASE WHEN `Antivirus Compliant` = 'Yes' THEN 1 ELSE 0 END +
            CASE WHEN `Firewall Compliant` = 'Yes' THEN 1 ELSE 0 END +
            CASE WHEN `Standard Admin Only` = 'Yes' THEN 1 ELSE 0 END
        ) / (COUNT(*) * 6)) DESC
";

try {
    $stmt = $pdo->query($team_sql);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "--- Service Team Compliance Analysis ---\n";
    echo str_pad("Team", 20) . " | " . str_pad("Units", 8) . " | " . "Percentage\n";
    echo str_repeat("-", 45) . "\n";
    
    foreach ($results as $row) {
        $pct = round(($row['total_points'] / ($row['total_devices'] * 6)) * 100, 1);
        echo str_pad($row['team'], 20) . " | " . str_pad(number_format($row['total_devices']), 8) . " | " . $pct . "%\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>