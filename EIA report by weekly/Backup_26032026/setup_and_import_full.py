import pandas as pd
import mysql.connector
from mysql.connector import Error
import datetime

# 1. Configuration
db_config = {
    'host': 'localhost',
    'user': 'root',
    'password': '',
    'database': 'eia_compliance'
}

def setup_database_full():
    try:
        conn = mysql.connector.connect(host=db_config['host'], user=db_config['user'], password=db_config['password'])
        cursor = conn.cursor()
        cursor.execute(f"CREATE DATABASE IF NOT EXISTS {db_config['database']} CHARACTER SET utf8mb4")
        cursor.execute(f"USE {db_config['database']}")
        
        # Create Table with ALL fields from Excel
        create_table_sql = """
        CREATE TABLE IF NOT EXISTS inventory_reports (
            id INT AUTO_INCREMENT PRIMARY KEY,
            computer_name VARCHAR(100),
            bu VARCHAR(50),
            company VARCHAR(100),
            serviced_by VARCHAR(50),
            ip_address VARCHAR(50),
            domain_name VARCHAR(100),
            joined_approved_domain VARCHAR(50),
            computer_type VARCHAR(50),
            serial_no VARCHAR(100),
            logged_on_user VARCHAR(100),
            user_name VARCHAR(200),
            last_user VARCHAR(100),
            os_name VARCHAR(200),
            os_build VARCHAR(50),
            os_release VARCHAR(50),
            os_build_ubr VARCHAR(50),
            os_eos_status VARCHAR(50),
            patch_healthy VARCHAR(50),
            total_missing_patches INT,
            missing_patches_name TEXT,
            antivirus_name VARCHAR(200),
            av_compliant VARCHAR(50),
            bitlocker_status VARCHAR(100),
            firewall_status VARCHAR(100),
            firewall_compliant VARCHAR(50),
            admin_members TEXT,
            standard_admin_only VARCHAR(50),
            shared_folder TEXT,
            tls_settings TEXT,
            usb_permission VARCHAR(100),
            pending_restart VARCHAR(20),
            last_reboot_date DATETIME,
            days_since_last_reboot INT,
            last_inventory_date DATETIME,
            inactive_30_days VARCHAR(20),
            glpi_agent_status VARCHAR(100),
            report_week INT,
            report_year INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB;
        """
        cursor.execute("DROP TABLE IF EXISTS inventory_reports") # Reset table for full schema
        cursor.execute(create_table_sql)
        conn.commit()
        print("Database schema updated to support ALL fields.")
        return conn
    except Error as e:
        print(f"Error: {e}")
        return None

def import_full_data(conn):
    try:
        df = pd.read_excel('EIA Report.xlsx')
        
        # Full Mapping based on Excel column names
        mapping = {
            'Computer Name': 'computer_name', 'BU': 'bu', 'Company': 'company', 'Serviced By': 'serviced_by',
            'IP Address': 'ip_address', 'Domain': 'domain_name', 'Joined Approved Domain': 'joined_approved_domain',
            'Computer Type': 'computer_type', 'Serial no.': 'serial_no', 'Logged on user': 'logged_on_user',
            'User name': 'user_name', 'Last User': 'last_user', 'OS Name': 'os_name', 'OS Build': 'os_build',
            'OS Release': 'os_release', 'OS Build.UBR': 'os_build_ubr', 'OS End of Support Status': 'os_eos_status',
            'Patch Healthy': 'patch_healthy', 'Total Missing Critical Patches': 'total_missing_patches',
            'Missing Critical Patches Name': 'missing_patches_name', 'Antivirus': 'antivirus_name',
            'Antivirus Compliant': 'av_compliant', 'BIT Locker Status': 'bitlocker_status',
            'Firewall Status': 'firewall_status', 'Firewall Compliant': 'firewall_compliant',
            'Members of Administrator Group': 'admin_members', 'Standard Admin Only': 'standard_admin_only',
            'Shared Folder': 'shared_folder', 'TLS Settings': 'tls_settings', 'USB Storage Permission': 'usb_permission',
            'Pending Restart': 'pending_restart', 'Last Reboot Date': 'last_reboot_date',
            'Days Since Last Reboot': 'days_since_last_reboot', 'Last Inventory Date': 'last_inventory_date',
            'Inactive 30+ Days': 'inactive_30_days', 'GLPI Agent Status': 'glpi_agent_status'
        }
        
        df_renamed = df.rename(columns=mapping)
        for col_expected in mapping.values():
            if col_expected not in df_renamed.columns:
                df_renamed[col_expected] = None
        
        df_db = df_renamed[list(mapping.values())].copy()
        
        # Fix numeric/date types
        df_db['total_missing_patches'] = pd.to_numeric(df_db['total_missing_patches'], errors='coerce').fillna(0).astype(int)
        df_db['days_since_last_reboot'] = pd.to_numeric(df_db['days_since_last_reboot'], errors='coerce').fillna(0).astype(int)
        df_db['last_reboot_date'] = pd.to_datetime(df_db['last_reboot_date'], errors='coerce').where(pd.notnull, None)
        df_db['last_inventory_date'] = pd.to_datetime(df_db['last_inventory_date'], errors='coerce').where(pd.notnull, None)
        
        now = datetime.datetime.now()
        df_db['report_week'] = now.isocalendar()[1]
        df_db['report_year'] = now.year
        
        cursor = conn.cursor()
        cols = ", ".join(df_db.columns)
        placeholders = ", ".join(["%s"] * len(df_db.columns))
        sql = f"INSERT INTO inventory_reports ({cols}) VALUES ({placeholders})"
        
        data_tuples = [tuple(x) for x in df_db.values]
        chunk_size = 1000
        for i in range(0, len(data_tuples), chunk_size):
            cursor.executemany(sql, data_tuples[i:i + chunk_size])
            conn.commit()
            print(f"Imported {min(i + chunk_size, len(data_tuples))} rows...")

        print(f"Success: {len(df_db)} records with ALL fields imported.")
    except Exception as e:
        print(f"Import Error: {e}")

connection = setup_database_full()
if connection:
    import_full_data(connection)
    connection.close()
