import pandas as pd
import os
import json
import datetime

# --- CONFIGURATION ---
FOLDER_PATH = r'D:\Users\Djmanny\.gemini\tmp\project\EIAQ2'
OUTPUT_FILE = os.path.join(FOLDER_PATH, 'data.js')

TOPICS_CONFIG = {
    "1.1 IT Asset Management.xlsx": {"id": "1.1", "status_col": "Asset update status Y/N", "team_col": "Groups"},
    "1.2 Install GLPI agent.xlsx": {"id": "1.2", "status_col": "GLPI setup status Y/N", "team_col": "Serviced By"},
    "2. Update OS.xlsx": {"id": "2", "status_col": "OS update status Y/N", "team_col": "Serviced By"},
    "3. Require Restart.xlsx": {"id": "3", "status_col": "Restart update Y/N", "team_col": "Serviced By"},
    "4. Antivirus Installation.xlsx": {"id": "4", "status_col": "AV update Y/N", "team_col": "Serviced By"},
    "5. Built-in Firewall Enablement.xlsx": {"id": "5", "status_col": "Firewall update Y/N", "team_col": "Serviced By"},
    "6. Client join domain.xlsx": {"id": "6", "status_col": "Join domain update Y/N", "team_col": "Serviced By"},
    "7. Privileged User management.xlsx": {"id": "7", "status_col": "Std admin update Y/N", "team_col": "Serviced By"},
    "8. Document Request.xlsx": {"id": "8", "status_col": "Document request update Y/N", "team_col": "Serviced By"}
}

def sync():
    print("Starting Dashboard Data Sync...")
    multi_matrix = {}
    
    for filename, mapping in TOPICS_CONFIG.items():
        file_path = os.path.join(FOLDER_PATH, filename)
        if not os.path.exists(file_path):
            print(f"⚠️  Skipping missing file: {filename}")
            continue
        
        try:
            # We assume header is at row 3 (Index 2)
            df = pd.read_excel(file_path, header=2)
            status_col = mapping['status_col']
            team_col = mapping['team_col']
            topic_id = mapping['id']

            # Dynamic column finding if exact name doesn't match
            if status_col not in df.columns:
                found = [c for c in df.columns if status_col.split()[0].lower() in str(c).lower()]
                status_col = found[0] if found else None
                
            if team_col not in df.columns:
                found = [c for c in df.columns if "service" in str(c).lower() or "group" in str(c).lower()]
                team_col = found[0] if found else None

            if status_col and team_col:
                # Clean Team Name
                df['team_clean'] = df[team_col].fillna('Unknown').astype(str).str.strip()
                # Clean Status
                df['y_n'] = df[status_col].fillna('N').apply(lambda x: 'Y' if str(x).strip().upper() == 'Y' else 'N')
                # Clean Phase (Map Q1,Q2 -> Q1)
                if 'EIA Phase' in df.columns:
                    df['phase_clean'] = df['EIA Phase'].fillna('Unknown').astype(str).str.strip().replace('Q1,Q2', 'Q1')
                else:
                    df['phase_clean'] = 'Q2'
                
                # Grouping
                for (team, phase), group in df.groupby(['team_clean', 'phase_clean']):
                    if team not in multi_matrix: multi_matrix[team] = {}
                    if topic_id not in multi_matrix[team]: multi_matrix[team][topic_id] = {}
                    
                    multi_matrix[team][topic_id][phase] = {
                        "total": int(len(group)),
                        "success": int((group['y_n'] == 'Y').sum())
                    }
            else:
                print(f"❌ Error: Required columns not found in {filename}")

        except Exception as e:
            print(f"❌ Error processing {filename}: {e}")

    # --- SAVE TO DATA.JS ---
    now = datetime.datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    js_content = f"// Automatically generated at {now}\nconst DASHBOARD_DATA = {json.dumps(multi_matrix, indent=4)};\n"
    js_content += f"const LAST_UPDATED = '{now}';"

    with open(OUTPUT_FILE, 'w', encoding='utf-8') as f:
        f.write(js_content)

    print(f"Sync complete! Data saved to: {OUTPUT_FILE}")
    print("\n--- NEXT STEPS FOR GITHUB ---")
    print("1. git add data.js")
    print("2. git commit -m \"Update dashboard data - {now}\"")
    print("3. git push")
    print("----------------------------\n")

if __name__ == "__main__":
    sync()
