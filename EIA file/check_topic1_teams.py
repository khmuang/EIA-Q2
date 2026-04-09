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

print(f"{'Sheet':<15} | {'Group Value':<20} | {'Assigned Team':<15} | {'Count'}")
print("-" * 65)

for sheet_name, header_idx in sheet_configs.items():
    if sheet_name not in xl.sheet_names: continue
    df = pd.read_excel(file_path, sheet_name=sheet_name, header=header_idx)
    group_col = next((c for c in df.columns if "Groups" in str(c)), None)
    
    if group_col:
        # Get distribution of values in Groups column
        dist = df[group_col].astype(str).value_counts()
        for val, count in dist.items():
            u_val = val.upper()
            if 'HO' in u_val: team = 'HO'
            elif 'DC' in u_val: team = 'DC'
            else: team = 'Branch'
            print(f"{sheet_name:<15} | {val:<20} | {team:<15} | {count}")
