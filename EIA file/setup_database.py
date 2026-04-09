import mysql.connector
from mysql.connector import errorcode

# --- CONFIGURATION (Default XAMPP settings) ---
db_config = {
    'user': 'root',
    'password': '',
    'host': '127.0.0.1',
}

DB_NAME = 'eia_compliance'

def setup_db():
    try:
        # 1. Connect to MySQL Server
        print("Connecting to MySQL server...")
        cnx = mysql.connector.connect(**db_config)
        cursor = cnx.cursor()

        # 2. Create Database
        print(f"Creating database '{DB_NAME}' if not exists...")
        cursor.execute(f"CREATE DATABASE IF NOT EXISTS {DB_NAME} DEFAULT CHARACTER SET 'utf8' COLLATE 'utf8_general_ci'")
        
        # 3. Select Database
        cnx.database = DB_NAME
        print(f"Using database '{DB_NAME}'.")

        # 4. Create/Upgrade Table
        # We add 'quarter' and 'audit_year' for historical reporting
        print("Checking/Updating table structure...")
        
        table_query = (
            "CREATE TABLE IF NOT EXISTS `audit_data` ("
            "  `id` int(11) NOT NULL AUTO_INCREMENT,"
            "  `topic_id` int(11) NOT NULL,"
            "  `topic_name` varchar(255) NOT NULL,"
            "  `team_name` varchar(50) NOT NULL,"
            "  `success_y` int(11) DEFAULT 0,"
            "  `pending_n` int(11) DEFAULT 0,"
            "  `quarter` varchar(2) DEFAULT 'Q1',"  # Added for Q1-Q4
            "  `audit_year` int(4) DEFAULT 2026,"   # Added for Year tracking
            "  `last_updated` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,"
            "  PRIMARY KEY (`id`),"
            "  INDEX `idx_report` (`audit_year`, `quarter`, `topic_id`)" # For fast history lookup
            ") ENGINE=InnoDB")

        cursor.execute(table_query)
        
        # Ensure columns exist if table was created previously without them
        columns_to_add = [
            ("quarter", "varchar(2) DEFAULT 'Q1' AFTER `pending_n`"),
            ("audit_year", "int(4) DEFAULT 2026 AFTER `quarter`"),
        ]

        for col_name, col_def in columns_to_add:
            try:
                cursor.execute(f"ALTER TABLE `audit_data` ADD COLUMN {col_name} {col_def}")
                print(f"Column '{col_name}' added successfully.")
            except mysql.connector.Error as err:
                if err.errno == 1060: # Column already exists
                    pass
                else:
                    print(f"Error adding {col_name}: {err.msg}")

        print("\n--- Setup & Upgrade Complete! ---")
        print(f"Database: {DB_NAME}")
        print("Table: audit_data (Now supports Quarterly & Yearly reporting)")
        
        cursor.close()
        cnx.close()

    except mysql.connector.Error as err:
        if err.errno == errorcode.ER_ACCESS_DENIED_ERROR:
            print("Error: Access denied (Check username/password)")
        elif err.errno == errorcode.ER_BAD_DB_ERROR:
            print("Error: Database does not exist")
        else:
            print(f"Error: {err}")

if __name__ == "__main__":
    setup_db()
