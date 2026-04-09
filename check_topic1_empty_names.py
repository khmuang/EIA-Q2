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

for sheet_name in xl.sheet_names:
    df = get_df(xl, sheet_name)
    status_col = next((c for c in df.columns if str(c).strip().upper() == "UPDATE STATUS Y/N" or str(c).strip().upper() == "Y"), None)
    
    if status_col:
        # Check for 'Y' where 'Name' is blank
        y_no_name = df[(df[status_col].astype(str).str.strip().str.upper() == 'Y') & (df['Name'].isna() | (df['Name'].astype(str).str.strip() == ''))]
        if len(y_no_name) > 0:
            print(f"Sheet: {sheet_name} - Found {len(y_no_name)} rows with 'Y' but NO NAME")
            print(y_no_name)
        else:
            print(f"Sheet: {sheet_name} - No 'Y' rows without names found.")
