import pandas as pd
import os
import json
from datetime import datetime
from dotenv import load_dotenv
from supabase import create_client, Client

# Load environment variables from .env
load_dotenv()

SUPABASE_URL = os.getenv("SUPABASE_URL", "").strip().strip('"').strip("'")
SUPABASE_KEY = os.getenv("SUPABASE_KEY", "").strip().strip('"').strip("'")

print(f"Debug: URL starts with '{SUPABASE_URL[:10]}...' length={len(SUPABASE_URL)}")
print(f"Debug: Key length={len(SUPABASE_KEY)}")

if not SUPABASE_URL or not SUPABASE_KEY or "YOUR_SUPABASE" in SUPABASE_URL:
    print("Error: SUPABASE_URL and SUPABASE_KEY must be in .env file and not placeholders.")
    exit(1)

supabase: Client = create_client(SUPABASE_URL, SUPABASE_KEY)

# Definition of topics and mappings based on Q2readme.md
topics_config = {
    "1.1 IT Asset Management.xlsx": {
        "id": "1.1", "item_col": "Name", "team_col": "Groups", "status_col": "Asset update status Y/N"
    },
    "1.2 Install GLPI agent.xlsx": {
        "id": "1.2", "item_col": "Computer Name", "team_col": "Serviced By", "status_col": "GLPI setup status Y/N"
    },
    "2. Update OS.xlsx": {
        "id": "2", "item_col": "Computer Name", "team_col": "Serviced By", "status_col": "OS update status Y/N"
    },
    "3. Require Restart.xlsx": {
        "id": "3", "item_col": "Computer Name", "team_col": "Serviced By", "status_col": "Restart update Y/N"
    },
    "4. Antivirus Installation.xlsx": {
        "id": "4", "item_col": "Computer Name", "team_col": "Serviced By", "status_col": "AV update Y/N"
    },
    "5. Built-in Firewall Enablement.xlsx": {
        "id": "5", "item_col": "Computer Name", "team_col": "Serviced By", "status_col": "Firewall update Y/N"
    },
    "6. Client join domain.xlsx": {
        "id": "6", "item_col": "Computer Name", "team_col": "Serviced By", "status_col": "Join domain update Y/N"
    },
    "7. Privileged User management.xlsx": {
        "id": "7", "item_col": "Computer Name", "team_col": "Serviced By", "status_col": "Std admin update Y/N"
    },
    "8. Document Request.xlsx": {
        "id": "8", "item_col": "Computer Name", "team_col": "Serviced By", "status_col": "Document request update Y/N"
    }
}

def clean_phase(phase_val):
    """
    Apply rule 4.13: If Phase is Q1,Q2, display as Q1.
    """
    if pd.isna(phase_val):
        return "Q2"
    val = str(phase_val).strip().replace(" ", "")
    if "Q1,Q2" in val or "Q1,Q2" == val:
        return "Q1"
    return val

def process_and_sync():
    all_records = []
    
    # Check if files exist and process them
    for filename, mapping in topics_config.items():
        if os.path.exists(filename):
            print(f"Processing {filename}...")
            # Read excel, header is at row 3 (0-indexed 2)
            df = pd.read_excel(filename, header=2)
            
            # Filter rows with data
            df = df.dropna(subset=[mapping['item_col']])
            
            # Create a list of dictionaries for insertion
            for index, row in df.iterrows():
                # Rule 2: N and Blank are treated same as fail
                status = str(row[mapping['status_col']]).strip().upper()
                action_status = 'Y' if status == 'Y' else 'N'
                
                # Rule 4.13: EIA Phase transformation
                phase = clean_phase(row['EIA Phase'])
                
                # Rule 3.2: Anonymization (PDPA)
                # Using a generic ID based on row index and topic for safety
                anon_name = f"ID_{mapping['id'].replace('.','')}_{index:05d}"
                
                record = {
                    "topic_id": mapping['id'],
                    "item_name": anon_name,
                    "service_team": str(row[mapping['team_col']]).strip() if not pd.isna(row[mapping['team_col']]) else "Unknown",
                    "action_status": action_status,
                    "eia_phase": phase
                }
                all_records.append(record)
        else:
            print(f"Skipping {filename} (File not found)")

    if not all_records:
        print("No records found to sync.")
        return

    print(f"Total records processed: {len(all_records)}")
    
    # 3.2 Sync to Supabase - Clearing old data first to avoid duplicates (Option 1: Full Refresh)
    print("Clearing old data from Supabase...")
    supabase.table("eia_q2_raw_data").delete().neq("topic_id", "0").execute()
    
    # Batch Insert (Limit to 1000 per request for reliability)
    batch_size = 1000
    for i in range(0, len(all_records), batch_size):
        batch = all_records[i:i + batch_size]
        print(f"Uploading batch {i // batch_size + 1} ({len(batch)} records)...")
        supabase.table("eia_q2_raw_data").insert(batch).execute()
    
    print("Sync complete successfully!")

if __name__ == "__main__":
    process_and_sync()
