import pandas as pd
import os

file_path = '1- IT Asset incomplete information.xlsx'
xl = pd.ExcelFile(file_path)
sheet_configs = {
    'No Company': 2,
    'No BU': 2,
    'No Group': 2,
    'No Location': 0
}

teams = ['Branch', 'HO', 'DC']
results = {team: {'Y': 0, 'N': 0, 'Pending': 0, 'Total': 0} for team in teams}

for sheet_name, header_idx in sheet_configs.items():
    if sheet_name not in xl.sheet_names: continue
    df = pd.read_excel(file_path, sheet_name=sheet_name, header=header_idx)
    group_col = next((c for c in df.columns if "Groups" in str(c)), None)
    status_col = next((c for c in df.columns if str(c).strip().upper() == "UPDATE STATUS Y/N" or str(c).strip().upper() == "Y"), None)
    
    if group_col and status_col:
        for index, row in df.iterrows():
            val = str(row[group_col]).upper()
            if 'HO' in val: team = 'HO'
            elif 'DC' in val: team = 'DC'
            else: team = 'Branch'
            
            status = str(row[status_col]).strip().upper()
            results[team]['Total'] += 1
            if status == 'Y':
                results[team]['Y'] += 1
            elif status == 'N':
                results[team]['N'] += 1
            else:
                results[team]['Pending'] += 1

print(f"{'Team':<10} | {'Total':<8} | {'Y':<8} | {'N':<8} | {'Pending':<8}")
print("-" * 50)
for team in teams:
    r = results[team]
    print(f"{team:<10} | {r['Total']:<8} | {r['Y']:<8} | {r['N']:<8} | {r['Pending']:<8}")
