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

for sheet_name, header_idx in sheet_configs.items():
    if sheet_name not in xl.sheet_names: continue
    df = pd.read_excel(file_path, sheet_name=sheet_name, header=header_idx)
    group_col = next((c for c in df.columns if "Groups" in str(c)), None)
    loc_col = next((c for c in df.columns if "Location" in str(c)), None)
    
    if group_col and loc_col:
        # Rows where Location says HO/Central but Group says Branch
        mask = (df[loc_col].astype(str).str.upper().str.contains('HO|CENTRAL', na=False)) & \
               (~df[group_col].astype(str).str.upper().str.contains('HO', na=False))
        mismatched = df[mask]
        if len(mismatched) > 0:
            print(f"Sheet: {sheet_name} - Found {len(mismatched)} HO rows labeled as Branch")
            print(mismatched[[group_col, loc_col]].head())
