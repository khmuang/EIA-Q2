import pandas as pd
import os
import json
import datetime

# --- CONFIGURATION (SharePoint Integration) ---
SHAREPOINT_ROOT = r"D:\Users\Djmanny\Central Group\RIS Endpoint support - Q2"

# Auto-detect location for output
BASE_DIR = os.path.dirname(os.path.abspath(__file__))
if BASE_DIR.endswith('EIAQ2'):
    PROJECT_ROOT = os.path.dirname(BASE_DIR)
else:
    PROJECT_ROOT = BASE_DIR

OUTPUT_FILE = os.path.join(PROJECT_ROOT, 'data.js')
ALT_OUTPUT_FILE = os.path.join(PROJECT_ROOT, 'EIAQ2', 'data.js')

# Ensure a copy exists in EIAQ2 as well for consistency
ALT_OUTPUT_FILE = os.path.join(PROJECT_ROOT, 'EIAQ2', 'data.js')

TOPICS_CONFIG = {
    "1.1 IT Asset Management.xlsx": {"id": "1.1", "subfolder": "1.1 IT Asset Management", "status_col": "Asset update status Y/N", "team_col": "Groups"},
    "1.2 Install GLPI agent.xlsx": {"id": "1.2", "subfolder": "1.2 Install GLPI agent", "status_col": "GLPI setup status Y/N", "team_col": "Serviced By"},
    "2. Update OS.xlsx": {"id": "2", "subfolder": "2. Update OS", "status_col": "OS update status Y/N", "team_col": "Serviced By"},
    "3. Require Restart.xlsx": {"id": "3", "subfolder": "3. Require Restart", "status_col": "Restart update Y/N", "team_col": "Serviced By"},
    "4. Antivirus Installation.xlsx": {"id": "4", "subfolder": "4. Antivirus Installation", "status_col": "AV update Y/N", "team_col": "Serviced By"},
    "5. Built-in Firewall Enablement.xlsx": {"id": "5", "subfolder": "5. Built-in Firewall Enablement", "status_col": "Firewall update Y/N", "team_col": "Serviced By"},
    "6. Client join domain.xlsx": {"id": "6", "subfolder": "6. Client join domain", "status_col": "Join domain update Y/N", "team_col": "Serviced By"},
    "7. Privileged User management.xlsx": {"id": "7", "subfolder": "7. Privileged User management", "status_col": "Std admin update Y/N", "team_col": "Serviced By"},
    "8. Document Request.xlsx": {"id": "8", "subfolder": "8. Document Request", "status_col": "Document request update Y/N", "team_col": "Serviced By"}
}

def sync():
    print(f"[START] Starting Dashboard Data Sync...")
    print(f"[PATH] Looking for Excel files in SharePoint: {SHAREPOINT_ROOT}")
    multi_matrix = {}
    
    for filename, mapping in TOPICS_CONFIG.items():
        file_path = os.path.join(SHAREPOINT_ROOT, mapping['subfolder'], filename)
        if not os.path.exists(file_path):
            print(f"⚠️  Skipping missing file (SharePoint): {file_path}")
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
    
    # Also update the companion file in EIAQ2 (for local consistency)
    try:
        with open(ALT_OUTPUT_FILE, 'w', encoding='utf-8') as f:
            f.write(js_content)
    except Exception as e:
        print(f"[WARNING] Note: Could not update secondary data.js in EIAQ2: {e}")

    print(f"[SUCCESS] Sync complete!")
    print(f"ROOT data.js: {OUTPUT_FILE}")
    print(f"EIAQ2 data.js: {ALT_OUTPUT_FILE}")
    print("\n--- NEXT STEPS FOR GITHUB ---")
    print(f"1. git add data.js")
    print(f"2. git commit -m \"Update dashboard data - {now}\"")
    print(f"3. git push")
    print("----------------------------\n")

if __name__ == "__main__":
    sync()
