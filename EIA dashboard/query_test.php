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
                
    /* --- อัปเดต: ตรวจสอบ Administrator Group พร้อมเงื่อนไขข้อยกเว้น BU = RIS และรองรับ 7 รูปแบบ --- */
    CASE 
        /* เงื่อนไขข้อยกเว้น: ถ้า BU เป็น RIS ให้ผ่านทันที (Yes) */
        WHEN A10003.NAME = 'RIS' THEN 'Yes'
        
        /* เช็ครายชื่อ Admin Group ตามมาตรฐาน 7 รูปแบบ */
        WHEN (SELECT REPLACE(MACHINE_CUSTOM_INVENTORY.STR_FIELD_VALUE,'<br/>','') 
              FROM MACHINE_CUSTOM_INVENTORY 
              WHERE MACHINE_CUSTOM_INVENTORY.ID = M.ID 
                AND MACHINE_CUSTOM_INVENTORY.SOFTWARE_ID = 42952) 
              IN (
                  'Administrator,Domain Admins,ManageEngineSOM',
                  'Administrator,Domain Admins',
                  'Administrator,ManageEngineSOM',
                  'Administrator',
                  'Domain Admins,ManageEngineSOM',
                  'Domain Admins',
                  'ManageEngineSOM'
              )
        THEN 'Yes'
        
        ELSE 'No'
    END AS 'Standard Admin Only',
    /* ------------------------------------------------------------------------------------------ */

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
                AND MACHINE_CUSTOM_INVENTORY.SOFTWARE_ID = 72225) AS \"Pending Restart\",
                
    /* สถานะการติดตั้ง GLPI Agent */
    CASE 
        WHEN GLPI.MACHINE_ID IS NOT NULL THEN 'Installed'
        ELSE 'Not Install'
    END AS 'GLPI Agent Status',

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
    ((M.OS_NAME LIKE '%windows%'))
ORDER BY 
    M.LAST_REBOOT DESC\";

$master_query = \"SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN `Joined Approved Domain` = 'Yes' THEN 1 ELSE 0 END) as s_domain,
    SUM(CASE WHEN `OS End of Support Status` = 'Active' THEN 1 ELSE 0 END) as s_os,
    SUM(CASE WHEN `Patch Healthy` = 'Healthy' THEN 1 ELSE 0 END) as s_patch,
    SUM(CASE WHEN `Antivirus Compliant` = 'Yes' THEN 1 ELSE 0 END) as s_av,
    SUM(CASE WHEN `Firewall Compliant` = 'Yes' THEN 1 ELSE 0 END) as s_fw,
    SUM(CASE WHEN `Standard Admin Only` = 'Yes' THEN 1 ELSE 0 END) as s_admin,

    /* Grading Groups */
    SUM(CASE WHEN 
        (`Joined Approved Domain` = 'Yes') AND 
        (`OS End of Support Status` = 'Active') AND 
        (`Patch Healthy` = 'Healthy') AND 
        (`Antivirus Compliant` = 'Yes') AND 
        (`Firewall Compliant` = 'Yes') AND 
        (`Standard Admin Only` = 'Yes') 
    THEN 1 ELSE 0 END) as g_full,

    SUM(CASE WHEN (
        CASE WHEN `Joined Approved Domain` = 'Yes' THEN 1 ELSE 0 END +
        CASE WHEN `OS End of Support Status` = 'Active' THEN 1 ELSE 0 END +
        CASE WHEN `Patch Healthy` = 'Healthy' THEN 1 ELSE 0 END +
        CASE WHEN `Antivirus Compliant` = 'Yes' THEN 1 ELSE 0 END +
        CASE WHEN `Firewall Compliant` = 'Yes' THEN 1 ELSE 0 END +
        CASE WHEN `Standard Admin Only` = 'Yes' THEN 1 ELSE 0 END
    ) BETWEEN 4 AND 5 THEN 1 ELSE 0 END) as g_minor,

    SUM(CASE WHEN (
        CASE WHEN `Joined Approved Domain` = 'Yes' THEN 1 ELSE 0 END +
        CASE WHEN `OS End of Support Status` = 'Active' THEN 1 ELSE 0 END +
        CASE WHEN `Patch Healthy` = 'Healthy' THEN 1 ELSE 0 END +
        CASE WHEN `Antivirus Compliant` = 'Yes' THEN 1 ELSE 0 END +
        CASE WHEN `Firewall Compliant` = 'Yes' THEN 1 ELSE 0 END +
        CASE WHEN `Standard Admin Only` = 'Yes' THEN 1 ELSE 0 END
    ) BETWEEN 2 AND 3 THEN 1 ELSE 0 END) as g_at_risk,

    SUM(CASE WHEN (
        CASE WHEN `Joined Approved Domain` = 'Yes' THEN 1 ELSE 0 END +
        CASE WHEN `OS End of Support Status` = 'Active' THEN 1 ELSE 0 END +
        CASE WHEN `Patch Healthy` = 'Healthy' THEN 1 ELSE 0 END +
        CASE WHEN `Antivirus Compliant` = 'Yes' THEN 1 ELSE 0 END +
        CASE WHEN `Firewall Compliant` = 'Yes' THEN 1 ELSE 0 END +
        CASE WHEN `Standard Admin Only` = 'Yes' THEN 1 ELSE 0 END
    ) <= 1 THEN 1 ELSE 0 END) as g_critical

FROM ($eiaquery) as base_data\";
?>