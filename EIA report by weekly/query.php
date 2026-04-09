<?php
$eiaquery = "WITH
/* --- ตรวจสอบการติดตั้ง GLPI Agent --- */
GLPICheck AS (
    SELECT DISTINCT MSJ.MACHINE_ID
    FROM MACHINE_SOFTWARE_JT MSJ
    JOIN SOFTWARE S ON S.ID = MSJ.SOFTWARE_ID
    WHERE S.DISPLAY_NAME LIKE '%GLPI%Agent%'
),
/* ---------------------------------- */

/* Antivirus (ranked by priority) */
AntivirusRanked AS (
    SELECT MACHINE_ID, Antivirus
    FROM (
        SELECT 
            MSJ.MACHINE_ID,
            CASE
                WHEN S.DISPLAY_NAME = 'Sentinel Agent' THEN 'SentinelOne'
                WHEN S.DISPLAY_NAME IN ('Falcon','CrowdStrike Windows Sensor') THEN 'CrowdStrike'
                WHEN S.DISPLAY_NAME IN ('ESET Endpoint Security','ESET Endpoint Antivirus') THEN 'ESET'
            END AS Antivirus,
            ROW_NUMBER() OVER (
                PARTITION BY MSJ.MACHINE_ID
                ORDER BY CASE
                    WHEN S.DISPLAY_NAME = 'Sentinel Agent' THEN 1
                    WHEN S.DISPLAY_NAME IN ('Falcon','CrowdStrike Windows Sensor') THEN 2
                    WHEN S.DISPLAY_NAME IN ('ESET Endpoint Security','ESET Endpoint Antivirus') THEN 3
                    ELSE 99
                END
            ) AS rn
        FROM MACHINE_SOFTWARE_JT MSJ
        JOIN SOFTWARE S ON S.ID = MSJ.SOFTWARE_ID
        WHERE S.DISPLAY_NAME IN (
            'Sentinel Agent',
            'Falcon','CrowdStrike Windows Sensor',
            'ESET Endpoint Security','ESET Endpoint Antivirus'
        )
    ) x
    WHERE rn = 1
),
/* OS Lifecycle Status */
OSStatus AS (
    SELECT
        M.ID AS MACHINE_ID,
        CASE
            WHEN M.OS_NAME LIKE '%Windows 10%'
             AND LOCATE('LTS', M.OS_NAME) > 0
             AND M.OS_BUILD IN ('14393','17763','19044')
            THEN 'Active'

            WHEN (M.OS_NAME LIKE '%Windows 11 Enterprise%'
               OR M.OS_NAME LIKE '%Windows 11 Education%')
             AND M.OS_BUILD = '22631'
            THEN 'Active'

            WHEN M.OS_NAME LIKE '%Windows 11%'
             AND M.OS_BUILD IN ('26100','26200')
            THEN 'Active'

            WHEN M.OS_NAME LIKE '%Windows 11%'
             AND M.OS_BUILD IN ('26120','26220')
            THEN 'Insider'

            WHEN M.OS_NAME LIKE '%macOS%'
             AND CAST(M.OS_MAJOR AS SIGNED) > 12
            THEN 'Active'

            ELSE 'Ended'
        END AS os_eos_status
    FROM MACHINE M
),
/* Missing Critical Patch ให้ดึงจำนวน (COUNT) และชื่อ */
MissingPatch AS (
    SELECT 
        MS.MACHINE_ID,
        COUNT(MS.PATCH_ID) AS Missing_Patches_Count,
        GROUP_CONCAT(P.TITLE SEPARATOR ', ') AS Missing_Patches_List
    FROM PATCH_MACHINE_STATUS MS
    JOIN KBSYS.PATCH P ON P.ID = MS.PATCH_ID
    WHERE MS.DETECT_STATUS = 'NOTPATCHED'
      AND MS.STATUS_DT > CURDATE() - INTERVAL 90 DAY
      AND P.PUBLISHER = 'Microsoft Corporation'
      AND P.SEVERITY = 'Critical'
      AND P.CREATION_DATE > CURDATE() - INTERVAL 90 DAY
    GROUP BY MS.MACHINE_ID
)
SELECT 
    M.NAME AS 'Computer Name',
    A10003.NAME AS BU,
    A10002.NAME AS Company,
    ASSET_DATA_5.FIELD_10007 AS 'Serviced By',
    M.IP AS 'IP Address',
    
    /* ข้อมูล Domain เดิม */
    M.CS_DOMAIN AS Domain,
    
    /* ตรวจสอบ Joined Domain ตามที่กำหนด */
    CASE 
        WHEN M.CS_DOMAIN IN (
            'cmg.co.th', 
            'central.co.th', 
            'cfw.co.th', 
            'officemate.co.th', 
            'cgpos.local', 
            'central.tech', 
            'familymart.co.th'
        ) THEN 'Yes'
        ELSE 'No'
    END AS 'Joined Approved Domain',

    M.CHASSIS_TYPE AS 'Computer Type',
    M.BIOS_SERIAL_NUMBER AS 'Serial no.',
    M.USER_NAME AS 'Logged on user',
    M.USER_FULLNAME AS 'User name',
    M.USER_LOGGED AS 'Last User', 
    M.OS_NAME AS 'OS Name',
    M.OS_BUILD AS 'OS Build',
    M.OS_RELEASE AS 'OS Release',
    (SELECT 
            REPLACE(MACHINE_CUSTOM_INVENTORY.STR_FIELD_VALUE,'<br/>','')
        FROM
            MACHINE_CUSTOM_INVENTORY
        WHERE
            MACHINE_CUSTOM_INVENTORY.ID = M.ID
                AND MACHINE_CUSTOM_INVENTORY.SOFTWARE_ID = 65944) AS 'OS Build.UBR',
   /* OS Lifecycle  */
    OS.os_eos_status AS 'OS End of Support Status',
    
    /* Patch Health Status */
    CASE
        WHEN OS.os_eos_status = 'Ended'
            THEN 'End of Support'
        WHEN OS.os_eos_status = 'Insider'
            THEN 'Not Support'
        WHEN MP.MACHINE_ID IS NULL
            THEN 'Healthy'
        ELSE 'Pending'
    END AS 'Patch Healthy',
    
    /* จำนวน Patch ที่หายไป */
    IFNULL(MP.Missing_Patches_Count, 0) AS 'Total Missing Critical Patches',

    /* รายชื่อ Patch ที่หายไป */
    IFNULL(MP.Missing_Patches_List, 'None') AS 'Missing Critical Patches Name',

    /* ข้อมูล Antivirus แบบเดิม */
    (SELECT 
            REPLACE(MACHINE_CUSTOM_INVENTORY.STR_FIELD_VALUE,'<br/>','')
        FROM
            MACHINE_CUSTOM_INVENTORY
        WHERE
            MACHINE_CUSTOM_INVENTORY.ID = M.ID
                AND MACHINE_CUSTOM_INVENTORY.SOFTWARE_ID = 62583) AS 'Antivirus',

    /* ตรวจสอบ Antivirus ตามเงื่อนไข */
    CASE 
        WHEN (SELECT REPLACE(MACHINE_CUSTOM_INVENTORY.STR_FIELD_VALUE,'<br/>','') 
              FROM MACHINE_CUSTOM_INVENTORY 
              WHERE MACHINE_CUSTOM_INVENTORY.ID = M.ID 
                AND MACHINE_CUSTOM_INVENTORY.SOFTWARE_ID = 62583) 
              IN ('Sentinel', 'ESET', 'CrowdStrike')
        THEN 'Yes'
        ELSE 'No'
    END AS 'Antivirus Compliant',

    (SELECT 
            REPLACE(MACHINE_CUSTOM_INVENTORY.STR_FIELD_VALUE,'<br/>','')
        FROM
            MACHINE_CUSTOM_INVENTORY
        WHERE
            MACHINE_CUSTOM_INVENTORY.ID = M.ID
                AND MACHINE_CUSTOM_INVENTORY.SOFTWARE_ID = 58066) AS 'BIT Locker Status',
                
    /* ข้อมูล Firewall Status แบบเดิม */
    (SELECT 
            REPLACE(MACHINE_CUSTOM_INVENTORY.STR_FIELD_VALUE,'<br/>','')
        FROM
            MACHINE_CUSTOM_INVENTORY
        WHERE
            MACHINE_CUSTOM_INVENTORY.ID = M.ID
                AND MACHINE_CUSTOM_INVENTORY.SOFTWARE_ID = 69119) AS 'Firewall Status',
                
    /* ตรวจสอบ Firewall Status ตามเงื่อนไข Sentinel */
    CASE 
        WHEN (SELECT REPLACE(MACHINE_CUSTOM_INVENTORY.STR_FIELD_VALUE,'<br/>','') 
              FROM MACHINE_CUSTOM_INVENTORY 
              WHERE MACHINE_CUSTOM_INVENTORY.ID = M.ID 
                AND MACHINE_CUSTOM_INVENTORY.SOFTWARE_ID = 62583) LIKE '%Sentinel%' 
        THEN 'Yes'
        WHEN (SELECT REPLACE(MACHINE_CUSTOM_INVENTORY.STR_FIELD_VALUE,'<br/>','') 
              FROM MACHINE_CUSTOM_INVENTORY 
              WHERE MACHINE_CUSTOM_INVENTORY.ID = M.ID 
                AND MACHINE_CUSTOM_INVENTORY.SOFTWARE_ID = 69119) 
              = 'Domain=On;Private=On;Public=On' 
        THEN 'Yes'
        ELSE 'No'
    END AS 'Firewall Compliant',

    /* ข้อมูล Admin Group แบบเดิม */
    (SELECT 
            REPLACE(MACHINE_CUSTOM_INVENTORY.STR_FIELD_VALUE,'<br/>','')
        FROM
            MACHINE_CUSTOM_INVENTORY
        WHERE
            MACHINE_CUSTOM_INVENTORY.ID = M.ID
                AND MACHINE_CUSTOM_INVENTORY.SOFTWARE_ID = 42952) AS 'Members of Administrator Group',
                
    /* ตรวจสอบ Administrator Group พร้อมเงื่อนไขข้อยกเว้น BU = RIS */
    CASE 
        WHEN A10003.NAME = 'RIS' THEN 'Yes'
        WHEN (SELECT REPLACE(MACHINE_CUSTOM_INVENTORY.STR_FIELD_VALUE,'<br/>','') 
              FROM MACHINE_CUSTOM_INVENTORY 
              WHERE MACHINE_CUSTOM_INVENTORY.ID = M.ID 
                AND MACHINE_CUSTOM_INVENTORY.SOFTWARE_ID = 42952) 
              = 'Administrator,Domain Admins,ManageEngineSOM' 
        THEN 'Yes'
        ELSE 'No'
    END AS 'Standard Admin Only',

    (SELECT 
            REPLACE(MACHINE_CUSTOM_INVENTORY.STR_FIELD_VALUE,'<br/>','')
        FROM
            MACHINE_CUSTOM_INVENTORY
        WHERE
            MACHINE_CUSTOM_INVENTORY.ID = M.ID
                AND MACHINE_CUSTOM_INVENTORY.SOFTWARE_ID = 68300) AS 'Shared Folder',
    (SELECT 
            REPLACE(MACHINE_CUSTOM_INVENTORY.STR_FIELD_VALUE,'<br/>','')
        FROM
            MACHINE_CUSTOM_INVENTORY
        WHERE
            MACHINE_CUSTOM_INVENTORY.ID = M.ID
                AND MACHINE_CUSTOM_INVENTORY.SOFTWARE_ID = 42032) AS 'TLS Settings',
    (SELECT 
            REPLACE(MACHINE_CUSTOM_INVENTORY.STR_FIELD_VALUE,'<br/>','')
        FROM
            MACHINE_CUSTOM_INVENTORY
        WHERE
            MACHINE_CUSTOM_INVENTORY.ID = M.ID
                AND MACHINE_CUSTOM_INVENTORY.SOFTWARE_ID = 68808) AS 'USB Storage Permission',
    (SELECT 
            REPLACE(MACHINE_CUSTOM_INVENTORY.STR_FIELD_VALUE,'<br/>','')
        FROM
            MACHINE_CUSTOM_INVENTORY
        WHERE
            MACHINE_CUSTOM_INVENTORY.ID = M.ID
                AND MACHINE_CUSTOM_INVENTORY.SOFTWARE_ID = 72225) AS 'Pending Restart',
                
    /* --- อัปเดต: แสดงสถานะการติดตั้ง GLPI Agent --- */
    CASE 
        WHEN GLPI.MACHINE_ID IS NOT NULL THEN 'Installed'
        ELSE 'Not Install'
    END AS 'GLPI Agent Status',
    /* --------------------------------------- */

    /* Last Reboot */
    CASE 
        WHEN M.LAST_REBOOT IS NULL OR M.LAST_REBOOT = '0000-00-00 00:00:00' THEN 'N/A'
        ELSE CONCAT(' ', DATE_FORMAT(M.LAST_REBOOT, '%Y-%m-%d %H:%i:%s'))
    END AS 'Last Reboot Date',
    
    DATEDIFF(NOW(), M.LAST_REBOOT) AS 'Days Since Last Reboot',
    
    /* Last Inventory */
    CASE 
        WHEN M.LAST_INVENTORY IS NULL OR M.LAST_INVENTORY = '0000-00-00 00:00:00' THEN 'N/A'
        ELSE CONCAT(' ', DATE_FORMAT(M.LAST_INVENTORY, '%Y-%m-%d %H:%i:%s'))
    END AS 'Last Inventory Date',

    /* Inactive 30+ Days */
    CASE 
        WHEN M.LAST_INVENTORY IS NULL OR M.LAST_INVENTORY = '0000-00-00 00:00:00' THEN 'N/A'
        WHEN DATEDIFF(NOW(), M.LAST_INVENTORY) >= 30 THEN 'Yes'
        ELSE 'No'
    END AS 'Inactive 30+ Days'

FROM
    MACHINE M
    /* Sub query  */
    LEFT JOIN GLPICheck GLPI ON GLPI.MACHINE_ID = M.ID
    LEFT JOIN OSStatus OS ON OS.MACHINE_ID = M.ID
    LEFT JOIN MissingPatch MP ON MP.MACHINE_ID = M.ID
    LEFT JOIN ASSET ON ASSET.MAPPED_ID = M.ID AND ASSET.ASSET_TYPE_ID = 5
    LEFT JOIN ASSET_ASSOCIATION J10003 ON J10003.ASSET_ID = ASSET.ID AND J10003.ASSET_FIELD_ID = 10003
    LEFT JOIN ASSET A10003 ON A10003.ID = J10003.ASSOCIATED_ASSET_ID
    LEFT JOIN ASSET_ASSOCIATION J10002 ON J10002.ASSET_ID = ASSET.ID AND J10002.ASSET_FIELD_ID = 10002
    LEFT JOIN ASSET A10002 ON A10002.ID = J10002.ASSOCIATED_ASSET_ID
    LEFT JOIN ASSET_DATA_5 ON ASSET_DATA_5.ID = ASSET.ASSET_DATA_ID
WHERE
    M.LAST_REBOOT DESC";

// --- NEW FUNCTIONS FOR REFACTORED EIA DASHBOARD ---

// 1. Get Latest Week/Year
function getLatestReportWeek($pdo) {
    $stmt = $pdo->query("SELECT MAX(report_week) as max_w, MAX(report_year) as max_y FROM inventory_reports");
    $latest = $stmt->fetch(PDO::FETCH_ASSOC);
    return [
        'week' => $latest['max_w'] ?: date("W"),
        'year' => $latest['max_y'] ?: date("Y")
    ];
}

// 2. Dashboard: Get Grand Total
function getCurrentTotal($pdo, $week, $year) {
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM inventory_reports WHERE report_week = ? AND report_year = ?");
    $stmt->execute([$week, $year]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['total'] ?: 1;
}

// 3. Dashboard: Get Topic Success Count (Dynamic Column)
function getTopicSuccessCount($pdo, $week, $year, $column) {
    $success_keys = ['Y', 'Yes', 'Compliant', 'Success', 'Active', 'Allowed', 'Healthy'];
    // Cannot bind column names with PDO, so interpolate safely based on map
    $stmt = $pdo->prepare("SELECT SUM(CASE WHEN $column IN ('" . implode("','", $success_keys) . "') THEN 1 ELSE 0 END) as success FROM inventory_reports WHERE report_week = ? AND report_year = ?");
    $stmt->execute([$week, $year]);
    $res = $stmt->fetch(PDO::FETCH_ASSOC);
    return (int)$res['success'];
}

// 4. Dashboard: Serviced By Data
function getServicedByDensity($pdo, $week, $year) {
    $stmt = $pdo->prepare("SELECT COALESCE(NULLIF(serviced_by, ''), 'None') as serviced_by, COUNT(*) as count FROM inventory_reports WHERE report_week = ? AND report_year = ? GROUP BY COALESCE(NULLIF(serviced_by, ''), 'None')");
    $stmt->execute([$week, $year]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// 5. Dashboard: Top BUs (Patching)
function getTopBUPatchSuccess($pdo, $week, $year) {
    $success_keys = ['Y', 'Yes', 'Compliant', 'Success', 'Active', 'Allowed', 'Healthy'];
    $stmt = $pdo->prepare("SELECT bu, COUNT(*) as success_count FROM inventory_reports WHERE patch_healthy IN ('" . implode("','", $success_keys) . "') AND report_week = ? AND report_year = ? GROUP BY bu ORDER BY success_count DESC LIMIT 5");
    $stmt->execute([$week, $year]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// 6. Detailed Device
function getInventoryDeviceById($pdo, $id) {
    $stmt = $pdo->prepare("SELECT * FROM inventory_reports WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// 7. Full BU Ranking
function getTopBUComplianceScore($pdo, $week, $year) {
    $success_keys = ['Y', 'Yes', 'Compliant', 'Success', 'Active', 'Healthy', 'Allowed'];
    $success_list = "'" . implode("','", $success_keys) . "'";
    $sql = "
        SELECT 
            bu, 
            COUNT(*) as total_devices,
            SUM(
                (CASE WHEN joined_approved_domain IN ($success_list) THEN 1 ELSE 0 END) +
                (CASE WHEN os_eos_status IN ($success_list) THEN 1 ELSE 0 END) +
                (CASE WHEN patch_healthy IN ($success_list) THEN 1 ELSE 0 END) +
                (CASE WHEN av_compliant IN ($success_list) THEN 1 ELSE 0 END) +
                (CASE WHEN firewall_compliant IN ($success_list) THEN 1 ELSE 0 END) +
                (CASE WHEN standard_admin_only IN ($success_list) THEN 1 ELSE 0 END)
            ) as total_success_points
        FROM inventory_reports 
        WHERE report_week = ? AND report_year = ? 
        GROUP BY bu 
        ORDER BY (
            SUM(
                (CASE WHEN joined_approved_domain IN ($success_list) THEN 1 ELSE 0 END) +
                (CASE WHEN os_eos_status IN ($success_list) THEN 1 ELSE 0 END) +
                (CASE WHEN patch_healthy IN ($success_list) THEN 1 ELSE 0 END) +
                (CASE WHEN av_compliant IN ($success_list) THEN 1 ELSE 0 END) +
                (CASE WHEN firewall_compliant IN ($success_list) THEN 1 ELSE 0 END) +
                (CASE WHEN standard_admin_only IN ($success_list) THEN 1 ELSE 0 END)
            ) / (COUNT(*) * 6)
        ) DESC 
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$week, $year]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// 8. Filter UI: Distinct options
function getDistinctBUs($pdo) {
    return $pdo->query("SELECT DISTINCT bu FROM inventory_reports WHERE bu IS NOT NULL ORDER BY bu")->fetchAll(PDO::FETCH_COLUMN);
}

function getDistinctTeams($pdo) {
    return $pdo->query("SELECT DISTINCT serviced_by FROM inventory_reports WHERE serviced_by IS NOT NULL ORDER BY serviced_by")->fetchAll(PDO::FETCH_COLUMN);
}

// 9. List Items with Dynamic Where
function getInventoryList($pdo, $limit, $offset, $where_sql, $params) {
    // Avoid mixing named (:limit) and positional (?) placeholders in PDO
    $stmt = $pdo->prepare("SELECT * FROM inventory_reports WHERE $where_sql ORDER BY computer_name ASC LIMIT ? OFFSET ?");
    
    $i = 1;
    // Bind the WHERE parameters first
    foreach($params as $val) {
        $stmt->bindValue($i++, $val);
    }
    
    // Bind LIMIT and OFFSET as the final positional parameters
    $stmt->bindValue($i++, (int)$limit, PDO::PARAM_INT);
    $stmt->bindValue($i++, (int)$offset, PDO::PARAM_INT);
    
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getInventoryTotalCount($pdo, $where_sql, $params) {
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM inventory_reports WHERE $where_sql");
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row['total'];
}

function getInventoryForExport($pdo, $where_sql, $params) {
    $stmt = $pdo->prepare("SELECT * FROM inventory_reports WHERE $where_sql ORDER BY computer_name ASC");
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

?>