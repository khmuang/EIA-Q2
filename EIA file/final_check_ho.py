import pandas as pd
import os

file_path = 'EIA file/1- IT Asset incomplete information.xlsx'
xl = pd.ExcelFile(file_path)
sheet_configs = {'No Company': 2, 'No BU': 2, 'No Group': 2, 'No Location': 0}

total_ho_y = 0
total_ho_n = 0

for sheet_name, header_idx in sheet_configs.items():
    if sheet_name not in xl.sheet_names: continue
    df = pd.read_excel(file_path, sheet_name=sheet_name, header=header_idx)
    cols = df.columns.tolist()
    group_col = next((c for c in cols if "Groups" in str(c)), None)
    loc_col = next((c for c in cols if "Location" in str(c)), None)
    status_col = next((c for c in cols if str(c).strip().upper() == "UPDATE STATUS Y/N" or str(c).strip().upper() == "Y"), None)
    
    if group_col and status_col:
        for _, row in df.iterrows():
            if pd.isna(row[group_col]) and pd.isna(row[status_col]): continue
            g_val = str(row[group_col]).upper()
            l_val = str(row[loc_col]).upper() if loc_col else ""
            
            # Classification
            if 'DC' in g_val or 'DC' in l_val: team = 'DC'
            elif 'HO' in g_val or 'HO' in l_val or 'CENTRAL' in l_val or 'TOWER' in l_val: team = 'HO'
            else: team = 'Branch'
            
            if team == 'HO':
                if str(row[status_col]).strip().upper() == 'Y': total_ho_y += 1
                else: total_ho_n += 1

print(f"Topic 1 HO Stats -> Success: {total_ho_y}, Pending: {total_ho_n}")
