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

        # 4. Create Table
        TABLES = {}
        TABLES['audit_data'] = (
            "CREATE TABLE IF NOT EXISTS `audit_data` ("
            "  `id` int(11) NOT NULL AUTO_INCREMENT,"
            "  `topic_id` int(11) NOT NULL,"
            "  `topic_name` varchar(255) NOT NULL,"
            "  `team_name` varchar(50) NOT NULL,"
            "  `success_y` int(11) DEFAULT 0,"
            "  `pending_n` int(11) DEFAULT 0,"
            "  `last_updated` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,"
            "  PRIMARY KEY (`id`)"
            ") ENGINE=InnoDB")

        for table_name in TABLES:
            table_description = TABLES[table_name]
            try:
                print(f"Creating table {table_name}: ", end='')
                cursor.execute(table_description)
            except mysql.connector.Error as err:
                if err.errno == errorcode.ER_TABLE_EXISTS_ERROR:
                    print("already exists.")
                else:
                    print(err.msg)
            else:
                print("OK")

        print("\n--- Setup Complete! ---")
        print(f"Database: {DB_NAME}")
        print("Table: audit_data (Ready for data injection)")
        
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
