import pandas as pd
import mysql.connector
from mysql.connector import Error
import datetime

# 1. Configuration
db_config = {
    'host': 'localhost',
    'user': 'root',
    'password': '', # Default for XAMPP
    'database': 'eia_compliance'
}

def setup_database():
    try:
        # Connect to MySQL Server (initial)
        conn = mysql.connector.connect(host=db_config['host'], user=db_config['user'], password=db_config['password'])
        cursor = conn.cursor()
        
        # Create Database
        cursor.execute(f"CREATE DATABASE IF NOT EXISTS {db_config['database']} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci")
        cursor.execute(f"USE {db_config['database']}")
        
        # Create Table (Matching EIA Repot.xlsx structure)
        create_table_sql = """
        CREATE TABLE IF NOT EXISTS inventory_reports (
            id INT AUTO_INCREMENT PRIMARY KEY,
            computer_name VARCHAR(100),
            bu VARCHAR(50),
            company VARCHAR(100),
            serviced_by VARCHAR(50),
            ip_address VARCHAR(50),
            domain_name VARCHAR(100),
            joined_approved_domain VARCHAR(20),
            computer_type VARCHAR(50),
            os_name VARCHAR(100),
            os_eos_status VARCHAR(50),
            patch_healthy VARCHAR(20),
            av_compliant VARCHAR(20),
            firewall_compliant VARCHAR(20),
            standard_admin_only VARCHAR(20),
            last_reboot_date DATETIME,
            days_since_last_reboot INT,
            last_inventory_date DATETIME,
            inactive_30_days VARCHAR(5),
            report_week INT,
            report_year INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB;
        """
        cursor.execute(create_table_sql)
        print("Database and Table 'inventory_reports' are ready.")
        conn.commit()
        return conn
    except Error as e:
        print(f"Error connecting to MySQL: {e}")
        return None

def import_data(conn):
    try:
        # Load Data
        df = pd.read_excel('EIA Repot.xlsx')
        
        # Clean Data (Mapping Excel Columns to DB Columns)
        # Select and Rename columns to match SQL table
        mapping = {
            'Computer Name': 'computer_name',
            'BU': 'bu',
            'Company': 'company',
            'Serviced By': 'serviced_by',
            'IP Address': 'ip_address',
            'Domain': 'domain_name',
            'Joined Approved Domain': 'joined_approved_domain',
            'Computer Type': 'computer_type',
            'OS Name': 'os_name',
            'OS End of Support Status': 'os_eos_status',
            'Patch Healthy': 'patch_healthy',
            'Antivirus Compliant': 'av_compliant',
            'Firewall Compliant': 'firewall_compliant',
            'Standard Admin Only': 'standard_admin_only',
            'Last Reboot Date': 'last_reboot_date',
            'Days Since Last Reboot': 'days_since_last_reboot',
            'Last Inventory Date': 'last_inventory_date',
            'Inactive 30+ Days': 'inactive_30_days'
        }
        
        df_db = df[list(mapping.keys())].rename(columns=mapping)
        
        # Handle Date/Nulls
        df_db['last_reboot_date'] = pd.to_datetime(df_db['last_reboot_date']).where(df_db['last_reboot_date'].notnull(), None)
        df_db['last_inventory_date'] = pd.to_datetime(df_db['last_inventory_date']).where(df_db['last_inventory_date'].notnull(), None)
        df_db['days_since_last_reboot'] = df_db['days_since_last_reboot'].fillna(0).astype(int)
        
        # Add metadata
        now = datetime.datetime.now()
        df_db['report_week'] = now.isocalendar()[1]
        df_db['report_year'] = now.year
        
        # Prepare for Bulk Insert
        cursor = conn.cursor()
        cols = ", ".join(df_db.columns)
        placeholders = ", ".join(["%s"] * len(df_db.columns))
        sql = f"INSERT INTO inventory_reports ({cols}) VALUES ({placeholders})"
        
        # Convert DataFrame to list of tuples for SQL execution
        data_tuples = [tuple(x) for x in df_db.values]
        
        # Clear existing data for this week before import (to avoid duplicates if re-run)
        cursor.execute(f"DELETE FROM inventory_reports WHERE report_week = {now.isocalendar()[1]} AND report_year = {now.year}")
        
        # Execute Chunked Insert (to avoid max_allowed_packet error)
        chunk_size = 1000
        for i in range(0, len(data_tuples), chunk_size):
            chunk = data_tuples[i:i + chunk_size]
            cursor.executemany(sql, chunk)
            conn.commit()
            print(f"Imported records {i} to {min(i + chunk_size, len(data_tuples))}...")

        print(f"Successfully imported all {len(df_db)} records into MySQL.")
        
    except Exception as e:
        print(f"Error during import: {e}")

# Run
connection = setup_database()
if connection:
    import_data(connection)
    connection.close()
