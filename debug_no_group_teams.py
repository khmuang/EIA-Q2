import pandas as pd
import os

file_path = 'EIA file/1- IT Asset incomplete information.xlsx'
xl = pd.ExcelFile(file_path)

def get_df(xl, sheet_name):
    df_raw = pd.read_excel(xl, sheet_name=sheet_name, header=None)
    header_row = 0
    for i, row in df_raw.iterrows():
        if 'Name' in row.values:
            header_row = i
            break
    return pd.read_excel(xl, sheet_name=sheet_name, header=header_row)

df = get_df(xl, 'No Group')
status_col = next((c for c in df.columns if str(c).strip().upper() == "UPDATE STATUS Y/N"), None)
group_col = next((c for c in df.columns if "Groups" in str(c)), None)

if status_col and group_col:
    print(f"Checking non-Y rows in 'No Group' with Team classification:")
    non_y = df[df[status_col].astype(str).str.strip().str.upper() != 'Y']
    for index, row in non_y.iterrows():
        g_val = str(row[group_col]).upper()
        if 'HO' in g_val: team = 'HO'
        elif 'DC' in g_val: team = 'DC'
        else: team = 'Branch'
        print(f"Name: {row['Name']:<15} | Group: {row[group_col]:<15} | Team: {team:<8} | Status: {row[status_col]}")
else:
    print("Columns not found")
