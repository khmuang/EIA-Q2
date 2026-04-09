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

df = get_df(xl, 'No Location')
status_col = next((c for c in df.columns if str(c).strip().upper() == "UPDATE STATUS Y/N"), None)

if status_col:
    print(f"Showing non-Y rows in 'No Location':")
    non_y = df[df[status_col].astype(str).str.strip().str.upper() != 'Y']
    print(f"Found {len(non_y)} non-Y rows")
    print(non_y[['Name', status_col]].to_string())
else:
    print("Status column not found")
