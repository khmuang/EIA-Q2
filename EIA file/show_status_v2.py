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

def get_data(fid, file_path):
    # Specialized handling for 2.2 as it's a pivot table
    if fid == 3: # 2.2 - Require Restart.xlsx
        try:
            df = pd.read_excel(file_path, header=1)
            # Find row labels
            if 'Unnamed: 0' in df.columns:
                df = df.rename(columns={'Unnamed: 0': 'Row Labels', 'Unnamed: 1': 'Y', 'Unnamed: 2': 'Blank', 'Unnamed: 3': 'N'})
            
            # Filter rows that are teams (Branch, DC, HO)
            teams = ['Branch', 'DC', 'HO']
            team_df = df[df['Row Labels'].isin(teams)].copy()
            
            # Fill NaNs with 0
            team_df['Y'] = pd.to_numeric(team_df['Y'], errors='coerce').fillna(0)
            team_df['Blank'] = pd.to_numeric(team_df['Blank'], errors='coerce').fillna(0)
            team_df['N'] = pd.to_numeric(team_df['N'], errors='coerce').fillna(0)
            
            y = int(team_df['Y'].sum())
            n_blank = int(team_df['Blank'].sum() + team_df['N'].sum())
            return y, n_blank
        except Exception as e:
            print(f"Error processing 2.2: {e}")
            return 0, 0

    # General handling for others
    try:
        # Try header=2 first
        df = pd.read_excel(file_path, header=2)
        
        # If headers are Unnamed, try header=1 or header=3
        if 'Unnamed: 0' in df.columns:
             df = pd.read_excel(file_path, header=1)
             if 'Unnamed: 0' in df.columns:
                 df = pd.read_excel(file_path, header=3)
        
        cols = df.columns.tolist()
        
        # Find status column
        status_col = None
        for col in cols:
            if any(key in str(col) for key in ['Y/N', 'Status', 'evidence', 'Remove']):
                status_col = col
                break
        
        if not status_col and len(cols) > 1:
            # Fallback to second to last column
            status_col = cols[-2]
            
        if status_col:
            success_series = df[status_col].astype(str).str.strip().str.upper()
            y = int((success_series == 'Y').sum())
            n_blank = int((success_series != 'Y').sum())
            
            # If y + n_blank == 0, maybe we have some NaNs in the column that were stringified
            # actually sum() above handles it.
            return y, n_blank
        else:
            return 0, 0
            
    except Exception as e:
        print(f"Error processing {file_path}: {e}")
        return 0, 0

def main():
    print(f"\nAnalyzing 8 Excel Files (Y vs N+Blank)...")
    results = []
    
    for fid, name in FILES.items():
        file_path = os.path.join(EXCEL_DIR, name)
        if os.path.exists(file_path):
            y, n_blank = get_data(fid, file_path)
            results.append({"id": fid, "title": name.replace(".xlsx", "").split("- ", 1)[-1], "y": y, "n": n_blank})
        else:
            print(f"File Not Found: {name}")
            results.append({"id": fid, "title": name.replace(".xlsx", "").split("- ", 1)[-1], "y": 0, "n": 0})

    print("\n" + "="*80)
    print(f"{'PRE-UPDATE TOPIC REPORT (Y vs N+Blank)':^80}")
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
    
    STANDARD_TOTAL = 25169
    if grand_total != STANDARD_TOTAL:
        print(f"WARNING: Current grand total ({grand_total}) does NOT match Standard ({STANDARD_TOTAL})")
    else:
        print("SUCCESS: Integrity Check Passed (25169).")

if __name__ == "__main__":
    main()
