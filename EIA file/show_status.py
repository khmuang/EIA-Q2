import pandas as pd
import os
from datetime import datetime

# --- CONFIGURATION ---
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

STANDARD_TOTAL = 25169

def process_data():
    print(f"\n[1/1] Reading and Analyzing 8 Excel Files (Counting Y and N+Blank)...")
    results = []
    
    for fid, name in FILES.items():
        file_path = os.path.join(EXCEL_DIR, name)
        
        if os.path.exists(file_path):
            try:
                # header=1 skip first title row
                df = pd.read_excel(file_path, header=1)
                
                cols = df.columns.tolist()
                # success_col: 'Update Status Y/N' or the second to last column
                success_col = 'Update Status Y/N' if 'Update Status Y/N' in cols else (cols[-2] if len(cols) > 1 else None)
                
                if success_col:
                    # Treat anything not 'Y' as 'N+Blank'
                    # Strip and uppercase to be robust
                    success_series = df[success_col].astype(str).str.strip().str.upper()
                    y = int((success_series == 'Y').sum())
                    n_blank = int((success_series != 'Y').sum())
                else:
                    y, n_blank = 0, 0
            except Exception as e:
                print(f"  ! Error reading {name}: {e}")
                y, n_blank = 0, 0
        else:
            print(f"  ! File Not Found: {name}")
            y, n_blank = 0, 0
            
        results.append({"id": fid, "title": name.replace(".xlsx", "").split("- ", 1)[-1], "y": y, "n": n_blank})

    return results

def show_report(results):
    print("\n" + "="*80)
    print(f"{'TOPIC REPORT (Y vs N+Blank)':^80}")
    print("="*80)
    print(f"{'ID':<3} | {'Topic Name':<45} | {'Y':<10} | {'(N+Blank)':<10} | {'Total':<10}")
    print("-"*80)
    
    grand_y = 0
    grand_n = 0
    
    for r in results:
        total = r['y'] + r['n']
        grand_y += r['y']
        grand_n += r['n']
        title = (r['title'][:42] + '..') if len(r['title']) > 42 else r['title']
        print(f"{r['id']:<3} | {title:<45} | {r['y']:<10} | {r['n']:<10} | {total:<10}")
        
    print("-"*80)
    grand_total = grand_y + grand_n
    print(f"{'GRAND TOTAL':<51} | {grand_y:<10} | {grand_n:<10} | {grand_total:<10}")
    print("="*80)
    
    if grand_total != STANDARD_TOTAL:
        print(f"WARNING: Current grand total ({grand_total}) does NOT match Standard ({STANDARD_TOTAL})")
    else:
        print("SUCCESS: Integrity Check Passed (25169).")

if __name__ == "__main__":
    results = process_data()
    show_report(results)
