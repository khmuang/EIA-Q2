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

print(f"{'Sheet':<15} | {'Team':<6} | {'Status':<10} | {'Count'}")
print("-" * 50)

for sheet_name, header_idx in sheet_configs.items():
    if sheet_name not in xl.sheet_names: continue
    df = pd.read_excel(file_path, sheet_name=sheet_name, header=header_idx)
    group_col = next((c for c in df.columns if "Groups" in str(c)), None)
    status_col = next((c for c in df.columns if str(c).strip().upper() == "UPDATE STATUS Y/N" or str(c).strip().upper() == "Y"), None)
    
    if group_col and status_col:
        # Branch Filter (Not HO and Not DC)
        mask = (~df[group_col].astype(str).str.upper().str.contains('HO', na=False)) & (~df[group_col].astype(str).str.upper().str.contains('DC', na=False))
        branch_df = df[mask]
        dist = branch_df[status_col].astype(str).str.strip().str.upper().value_counts()
        for val, count in dist.items():
            print(f"{sheet_name:<15} | Branch | {val:<10} | {count}")
