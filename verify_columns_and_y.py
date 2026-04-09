import pandas as pd
import os

EXCEL_DIR = "EIA file"
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

def get_correct_df(file_path, sheet_name=0):
    potential_keys = ['Name', 'BU', 'Service Team', 'Computer Name', 'Bu']
    for h in [2, 3, 1, 0, 4]:
        try:
            df = pd.read_excel(file_path, sheet_name=sheet_name, header=h)
            if any(k in df.columns for k in potential_keys):
                return df, h
        except: continue
    return pd.read_excel(file_path, sheet_name=sheet_name, header=0), 0

print(f"{'ID':<3} | {'Sheet Used':<15} | {'Col Used':<30} | {'Y Count'}")
print("-" * 65)

for fid, name in FILES.items():
    file_path = os.path.join(EXCEL_DIR, name)
    if not os.path.exists(file_path): continue
    
    if fid == 1:
        xl = pd.ExcelFile(file_path)
        total_y = 0
        for s in xl.sheet_names:
            df, h = get_correct_df(file_path, sheet_name=s)
            col = next((c for c in df.columns if "Update Status Y/N" in str(c)), None)
            if col:
                y = int((df[col].astype(str).str.strip().str.upper() == 'Y').sum())
                total_y += y
        print(f"{fid:<3} | Multiple Sheets | Update Status Y/N              | {total_y}")
    else:
        df, h = get_correct_df(file_path)
        status_keys = ["Update Status Y/N", "Updated or Replaced Y/N", "Install Status Y/N", "Firewall enable Y/N", 
                       "Join status Y/N", "Remove accounts", "evidence", "Y/N", "Restart Action", "Status"]
        status_col = None
        for key in status_keys:
            status_col = next((c for c in df.columns if key.lower() in str(c).lower()), None)
            if status_col: break
        
        if status_col:
            y = int((df[status_col].astype(str).str.strip().str.upper() == 'Y').sum())
            print(f"{fid:<3} | Index {h:<9} | {str(status_col):<30} | {y}")
        else:
            print(f"{fid:<3} | Index {h:<9} | NO STATUS COLUMN FOUND         | 0")
