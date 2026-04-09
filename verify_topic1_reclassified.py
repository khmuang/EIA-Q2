import pandas as pd
import os

file_path = 'EIA file/1- IT Asset incomplete information.xlsx'
xl = pd.ExcelFile(file_path)

def get_df_with_correct_header(xl, sheet_name):
    df_raw = pd.read_excel(xl, sheet_name=sheet_name, header=None)
    header_row = 0
    for i, row in df_raw.iterrows():
        if 'Name' in row.values:
            header_row = i
            break
    return pd.read_excel(xl, sheet_name=sheet_name, header=header_row)

teams = ['Branch', 'HO', 'DC']
results = {team: {'Y': 0, 'N': 0, 'Pending': 0, 'Total': 0} for team in teams}

print(f"{'Sheet':<15} | {'Team':<10} | {'Y':<5} | {'N+P':<5} | {'Total':<5}")
print("-" * 50)

for sheet_name in xl.sheet_names:
    df = get_df_with_correct_header(xl, sheet_name)
    group_col = next((c for c in df.columns if "Groups" in str(c)), None)
    loc_col = next((c for c in df.columns if "Location" in str(c)), None)
    status_col = next((c for c in df.columns if str(c).strip().upper() == "UPDATE STATUS Y/N" or str(c).strip().upper() == "Y"), None)
    
    if group_col and status_col and loc_col:
        sheet_stats = {team: {'Y': 0, 'N+P': 0, 'Total': 0} for team in teams}
        for index, row in df.iterrows():
            g_val = str(row[group_col]).upper()
            l_val = str(row[loc_col]).upper()
            
            # Re-classification logic: If 'HO' is in Group OR Location
            # (But 'CENTRAL' alone might be branch, let's stick to 'HO' for now)
            if 'HO' in g_val or 'HO' in l_val: team = 'HO'
            elif 'DC' in g_val or 'DC' in l_val: team = 'DC'
            else: team = 'Branch'
            
            status = str(row[status_col]).strip().upper()
            results[team]['Total'] += 1
            sheet_stats[team]['Total'] += 1
            if status == 'Y':
                results[team]['Y'] += 1
                sheet_stats[team]['Y'] += 1
            else:
                results[team]['Pending'] += 1
                sheet_stats[team]['N+P'] += 1
        
        for team in teams:
            if sheet_stats[team]['Total'] > 0:
                print(f"{sheet_name:<15} | {team:<10} | {sheet_stats[team]['Y']:<5} | {sheet_stats[team]['N+P']:<5} | {sheet_stats[team]['Total']:<5}")

print("\n" + "="*50)
print(f"{'FINAL Team':<10} | {'Total':<8} | {'Y':<8} | {'N+P':<8}")
print("-" * 50)
for team in teams:
    r = results[team]
    print(f"{team:<10} | {r['Total']:<8} | {r['Y']:<8} | {r['Pending']:<8}")
print("="*50)
print(f"Grand Total Y: {sum(r['Y'] for r in results.values())}")
print(f"Grand Total Pending: {sum(r['Pending'] for r in results.values())}")
