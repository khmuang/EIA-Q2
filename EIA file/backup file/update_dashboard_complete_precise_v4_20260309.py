import pandas as pd
import json
import os
from datetime import datetime
import shutil
import subprocess
import re

# --- CONFIGURATION ---
EXCEL_DIR = "EIA file"
BACKUP_DIR = os.path.join(EXCEL_DIR, "backup file")
OUTPUT_HTML = "index.html"
GITHUB_REPO_URL = "https://github.com/khmuang/EIA-dashboard.git"

FILES = {
    1: "1- IT Asset incomplete information.xlsx",
    2: "2.1 - Update OS - Replace.xlsx",
    3: "2.2 - Require Restart.xlsx",
    4: "3- Antivirus not Install.xlsx",
    5: "4- Built-in Firewall are not enable.xlsx",
    6: "5- Client devices are not joined to the domain.xlsx",
    7: "6- Privileged User management.xlsx",
    8: "7- Document request privileged user.xlsx"
}

# Approved TOPIC_TOTALS - Grand Total: 25,169
TOPIC_TOTALS = {
    1: {"Branch": 245, "DC": 47, "HO": 52},      # Total 344
    2: {"Branch": 7565, "DC": 863, "HO": 2691},  # Total 11119
    3: {"Branch": 788, "DC": 229, "HO": 1367},   # Total 2384
    4: {"Branch": 329, "DC": 34, "HO": 268},     # Total 631
    5: {"Branch": 4599, "DC": 919, "HO": 1455},  # Total 6973
    6: {"Branch": 144, "DC": 18, "HO": 374},     # Total 536
    7: {"Branch": 1827, "DC": 363, "HO": 983},   # Total 3173
    8: {"Branch": 3, "DC": 3, "HO": 3}           # Total 9
}

def backup_files():
    print(f"--- Backing up Excel files to '{BACKUP_DIR}' ---")
    if not os.path.exists(BACKUP_DIR):
        os.makedirs(BACKUP_DIR)
    timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
    for fid, name in FILES.items():
        src = os.path.join(EXCEL_DIR, name)
        if os.path.exists(src):
            dst = os.path.join(BACKUP_DIR, f"{timestamp}_{name}")
            shutil.copy2(src, dst)
    print("Backup completed successfully.")

def find_team_col(df):
    possible = ['Groups', 'Service Team', 'Serviced By', 'Service By', 'serviced by', 'service team']
    for p in possible:
        if p in df.columns: return p
    return None

def find_status_col(df):
    possible = ['Update Status Y/N', 'Updated or Replaced Y/N', 'Is there evidence of the request? Y/N', 'Status']
    for p in possible:
        if p in df.columns: return p
    # Fallback: search for any column containing 'Y/N'
    for c in df.columns:
        if 'Y/N' in str(c): return c
    return None

def process_data():
    print(f"Reading files from '{EXCEL_DIR}'...")
    sections = []
    
    for fid, name in FILES.items():
        path = os.path.join(EXCEL_DIR, name)
        if not os.path.exists(path): continue
            
        details = []
        y_counts = {"Branch": 0, "HO": 0, "DC": 0}
        
        # --- TOPIC 1 Special Handling ---
        if fid == 1:
            sheets = ['No Company', 'No BU', 'No Group', 'No Location']
            unique_assets = pd.DataFrame()
            for s in sheets:
                try:
                    df_temp = pd.read_excel(path, sheet_name=s, header=2)
                    t_col = find_team_col(df_temp)
                    s_col = find_status_col(df_temp)
                    n_col = 'Name' if 'Name' in df_temp.columns else 'Computer Name'
                    if t_col and s_col and n_col in df_temp.columns:
                        # Keep only 'Y' rows and then drop duplicates
                        y_only = df_temp[df_temp[s_col].astype(str).str.strip().str.upper() == 'Y']
                        unique_assets = pd.concat([unique_assets, y_only[[n_col, t_col]].rename(columns={n_col:'ID', t_col:'Team'})])
                except: continue
            if not unique_assets.empty:
                counts = unique_assets.drop_duplicates(subset=['ID'])['Team'].astype(str).str.strip().value_counts()
                for team in y_counts.keys(): y_counts[team] = int(counts.get(team, 0))

        # --- TOPIC 3 Special Handling (Pivot) ---
        elif fid == 3:
            try:
                df = pd.read_excel(path, header=None)
                team_map = {"Branch": 5, "DC": 6, "HO": 7}
                for team, idx in team_map.items():
                    y_counts[team] = int(df.iloc[idx, 1])
            except: pass

        # --- STANDARD & TOPIC 8 ---
        else:
            try:
                header_row = 2 if fid == 8 else 0
                if fid != 8:
                    df_raw = pd.read_excel(path, header=None, nrows=10)
                    for i, row in df_raw.iterrows():
                        if any(h in [str(v).strip() for v in row.values] for h in ['Service Team', 'Serviced By', 'Groups']):
                            header_row = i; break
                
                s_name = 'No firewall' if fid == 5 else 0
                df = pd.read_excel(path, sheet_name=s_name, header=header_row)
                t_col = find_team_col(df)
                s_col = find_status_col(df)
                
                if t_col and s_col:
                    y_rows = df[df[s_col].astype(str).str.strip().str.upper() == 'Y']
                    counts = y_rows[t_col].astype(str).str.strip().value_counts()
                    for team in y_counts.keys(): y_counts[team] = int(counts.get(team, 0))
            except: pass

        # FINAL CALCULATION: N = Total - Y
        for team in ['Branch', 'HO', 'DC']:
            y_val = y_counts[team]
            total = TOPIC_TOTALS[fid].get(team, 0)
            details.append({"Service Team": team, "Y": y_val, "N": max(0, total - y_val)})

        sections.append({"id": fid, "title": name.replace(".xlsx", "").split("- ", 1)[-1], "details": details})

    thai_year = datetime.now().year + 543
    timestamp_str = datetime.now().strftime(f"%d/%m/{thai_year} %H:%M:%S")
    return {"timestamp": timestamp_str, "sections": sections}

def update_html(data):
    if not os.path.exists(OUTPUT_HTML): return
    with open(OUTPUT_HTML, 'r', encoding='utf-8') as f: content = f.read()
    json_data = json.dumps(data, ensure_ascii=False, indent=4)
    updated = re.sub(r'const rawData = \{.*?\};', f'const rawData = {json_data};', content, flags=re.DOTALL)
    with open(OUTPUT_HTML, 'w', encoding='utf-8') as f: f.write(updated)
    print(f"Local {OUTPUT_HTML} updated successfully with Hard-Aligned Logic.")

def sync_to_github():
    print("\n" + "="*30)
    confirm = input("Push updates to GitHub now? (y/n): ").lower()
    print("="*30)
    if confirm == 'y':
        try:
            subprocess.run(["git", "add", "index.html"], check=True)
            commit_msg = f"Auto-update Dashboard (Final Precise Logic): {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}"
            subprocess.run(["git", "commit", "-m", commit_msg], check=True)
            subprocess.run(["git", "push", "origin", "main"], check=True)
            print("Success: Dashboard updated online!")
        except Exception as e: print(f"Git Push Failed: {e}")
    else: print("Push cancelled.")

if __name__ == "__main__":
    backup_files()
    data = process_data()
    update_html(data)
    sync_to_github()
