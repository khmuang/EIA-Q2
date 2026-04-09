import pandas as pd
import os

file_path = 'EIA file/1- IT Asset incomplete information.xlsx'
xl = pd.ExcelFile(file_path)

for sheet_name in xl.sheet_names:
    df_raw = pd.read_excel(xl, sheet_name=sheet_name, header=None)
    # Total rows in sheet
    total_rows = len(df_raw)
    
    # Try to find 'Name' column to see how many data rows we *should* have
    header_row = -1
    for i, row in df_raw.iterrows():
        if 'Name' in row.values:
            header_row = i
            break
    
    if header_row != -1:
        data_rows = total_rows - (header_row + 1)
        print(f"Sheet: {sheet_name:<15} | Total Raw Rows: {total_rows:<5} | Header at: {header_row:<3} | Expected Data Rows: {data_rows:<5}")
    else:
        print(f"Sheet: {sheet_name:<15} | Total Raw Rows: {total_rows:<5} | NO HEADER FOUND")
