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
    # Topic 1: Multiple Sheets
    if fid == 1:
        try:
            xl = pd.ExcelFile(file_path)
            total_y = 0
            total_n = 0
            for sheet in xl.sheet_names:
                # Find header row index
                df_raw = pd.read_excel(file_path, sheet_name=sheet, header=None)
                header_row = 0
                for i, row in df_raw.iterrows():
                    if 'Name' in row.values:
                        header_row = i
                        break
                
                df = pd.read_excel(file_path, sheet_name=sheet, header=header_row)
                cols = df.columns.tolist()
                status_col = next((c for c in cols if "Update Status Y/N" in str(c)), None)
                if status_col:
                    success_series = df[status_col].astype(str).str.strip().str.upper()
                    total_y += int((success_series == 'Y').sum())
                    total_n += int((success_series != 'Y').sum())
                else:
                    total_n += len(df)
            return total_y, total_n
        except Exception as e:
            print(f"Error processing Topic 1: {e}")
            return 0, 0

    # Topic 3 (2.2): Pivot Table
    if fid == 3:
        try:
            df = pd.read_excel(file_path, header=1)
            if 'Unnamed: 0' in df.columns:
                df = df.rename(columns={'Unnamed: 0': 'Row Labels', 'Unnamed: 1': 'Y', 'Unnamed: 2': 'Blank', 'Unnamed: 3': 'N'})
            teams = ['Branch', 'DC', 'HO']
            team_df = df[df['Row Labels'].isin(teams)].copy()
            team_df['Y'] = pd.to_numeric(team_df['Y'], errors='coerce').fillna(0)
            team_df['Blank'] = pd.to_numeric(team_df['Blank'], errors='coerce').fillna(0)
            team_df['N'] = pd.to_numeric(team_df['N'], errors='coerce').fillna(0)
            y = int(team_df['Y'].sum())
            n_blank = int(team_df['Blank'].sum() + team_df['N'].sum())
            return y, n_blank
        except Exception as e:
            print(f"Error processing Topic 3: {e}")
            return 0, 0

    # Others
    try:
        # Detect header row dynamically
        # We look for "Bu" or "Service Team" or "Computer Name"
        potential_headers = [2, 3, 1, 0]
        df_found = None
        for h in potential_headers:
            df = pd.read_excel(file_path, header=h)
            if any(k in df.columns for k in ['Bu', 'Service Team', 'Computer Name', 'Name', 'BU']):
                df_found = df
                break
        
        if df_found is None:
            df_found = pd.read_excel(file_path, header=2) # default fallback

        cols = df_found.columns.tolist()
        status_col = None
        # Priority list for status columns
        priority_keys = ["Update Status Y/N", "Updated or Replaced Y/N", "Install Status Y/N", 
                         "Firewall enable Y/N", "Join status Y/N", "Remove accounts", "evidence", "Y/N"]
        
        for key in priority_keys:
            for col in cols:
                if key in str(col):
                    status_col = col
                    break
            if status_col: break
            
        if not status_col and len(cols) > 1:
            # Last resort: second to last column
            status_col = cols[-2]
            
        if status_col:
            success_series = df_found[status_col].astype(str).str.strip().str.upper()
            y = int((success_series == 'Y').sum())
            n_blank = int((success_series != 'Y').sum())
            return y, n_blank
        else:
            return 0, len(df_found)
            
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
