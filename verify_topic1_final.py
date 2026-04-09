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

for sheet_name in xl.sheet_names:
    df = get_df_with_correct_header(xl, sheet_name)
    group_col = next((c for c in df.columns if "Groups" in str(c)), None)
    status_col = next((c for c in df.columns if str(c).strip().upper() == "UPDATE STATUS Y/N" or str(c).strip().upper() == "Y"), None)
    
    if group_col and status_col:
        sheet_y = 0
        sheet_pending = 0
        print(f"Processing Sheet: {sheet_name}")
        for index, row in df.iterrows():
            val = str(row[group_col]).upper()
            if 'HO' in val: team = 'HO'
            elif 'DC' in val: team = 'DC'
            else: team = 'Branch'
            
            status = str(row[status_col]).strip().upper()
            results[team]['Total'] += 1
            if status == 'Y':
                results[team]['Y'] += 1
                sheet_y += 1
            elif status == 'N':
                results[team]['N'] += 1
                sheet_pending += 1
            else:
                results[team]['Pending'] += 1
                sheet_pending += 1
        print(f"  -> Sheet Success: {sheet_y}, Sheet Pending: {sheet_pending}, Sheet Total: {len(df)}")

print("\n" + "="*50)
print(f"{'Team':<10} | {'Total':<8} | {'Y':<8} | {'N':<8} | {'Pending':<8}")
print("-" * 50)
for team in teams:
    r = results[team]
    print(f"{team:<10} | {r['Total']:<8} | {r['Y']:<8} | {r['N']:<8} | {r['Pending']:<8}")
print("="*50)
print(f"Grand Total Y: {sum(r['Y'] for r in results.values())}")
print(f"Grand Total Pending (N+Blank): {sum(r['N'] + r['Pending'] for r in results.values())}")
