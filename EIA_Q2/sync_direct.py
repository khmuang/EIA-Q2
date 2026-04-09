import pandas as pd
import os
import json
from datetime import datetime
from supabase import create_client, Client

# Hardcoded credentials for absolute certainty
SUPABASE_URL = "https://ilavnyfdbndiwuxrfbja.supabase.co"
SUPABASE_KEY = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImlsYXZueWZkYm5kaXd1eHJmYmphIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzM5OTUzNDAsImV4cCI6MjA4OTU3MTM0MH0.JvZuCY_9tvAQBXbqJuC7a8CV8B1FzND-CdJEEmIlbR8"

supabase: Client = create_client(SUPABASE_URL, SUPABASE_KEY)

topics_config = {
    "1.1 IT Asset Management.xlsx": {"id": "1.1", "item_col": "Name", "team_col": "Groups", "status_col": "Asset update status Y/N"},
    "1.2 Install GLPI agent.xlsx": {"id": "1.2", "item_col": "Computer Name", "team_col": "Serviced By", "status_col": "GLPI setup status Y/N"},
    "2. Update OS.xlsx": {"id": "2", "item_col": "Computer Name", "team_col": "Serviced By", "status_col": "OS update status Y/N"},
    "3. Require Restart.xlsx": {"id": "3", "item_col": "Computer Name", "team_col": "Serviced By", "status_col": "Restart update Y/N"},
    "4. Antivirus Installation.xlsx": {"id": "4", "item_col": "Computer Name", "team_col": "Serviced By", "status_col": "AV update Y/N"},
    "5. Built-in Firewall Enablement.xlsx": {"id": "5", "item_col": "Computer Name", "team_col": "Serviced By", "status_col": "Firewall update Y/N"},
    "6. Client join domain.xlsx": {"id": "6", "item_col": "Computer Name", "team_col": "Serviced By", "status_col": "Join domain update Y/N"},
    "7. Privileged User management.xlsx": {"id": "7", "item_col": "Computer Name", "team_col": "Serviced By", "status_col": "Std admin update Y/N"},
    "8. Document Request.xlsx": {"id": "8", "item_col": "Computer Name", "team_col": "Serviced By", "status_col": "Document request update Y/N"}
}

def clean_phase(phase_val):
    if pd.isna(phase_val): return "Q2"
    val = str(phase_val).strip().replace(" ", "")
    return "Q1" if "Q1,Q2" in val else val

def process_and_sync():
    all_records = []
    print("Step 1: Reading Excel files...")
    for filename, mapping in topics_config.items():
        if os.path.exists(filename):
            print(f" - Processing {filename}...")
            df = pd.read_excel(filename, header=2)
            df = df.dropna(subset=[mapping['item_col']])
            for index, row in df.iterrows():
                status = str(row[mapping['status_col']]).strip().upper()
                action_status = 'Y' if status == 'Y' else 'N'
                phase = clean_phase(row['EIA Phase'])
                anon_name = f"ID_{mapping['id'].replace('.','')}_{index:05d}"
                all_records.append({
                    "topic_id": mapping['id'],
                    "item_name": anon_name,
                    "service_team": str(row[mapping['team_col']]).strip() if not pd.isna(row[mapping['team_col']]) else "Unknown",
                    "action_status": action_status,
                    "eia_phase": phase
                })
    
    if not all_records: return print("No records found.")
    
    print(f"Step 2: Syncing {len(all_records)} records to Supabase...")
    # Full Refresh logic
    supabase.table("eia_q2_raw_data").delete().neq("topic_id", "0").execute()
    
    batch_size = 500
    for i in range(0, len(all_records), batch_size):
        batch = all_records[i:i + batch_size]
        supabase.table("eia_q2_raw_data").insert(batch).execute()
        print(f" - Uploaded batch {i // batch_size + 1}")
    
    print("\nSUCCESS: All data synced to Supabase!")

if __name__ == "__main__":
    process_and_sync()
