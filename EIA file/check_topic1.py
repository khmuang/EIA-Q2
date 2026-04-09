import pandas as pd
import os

file_path = '1- IT Asset incomplete information.xlsx'
xl = pd.ExcelFile(file_path)
for s in xl.sheet_names:
    h = 2 if s != 'No Location' else 1
    df = pd.read_excel(xl, sheet_name=s, header=h)
    col = next((c for c in df.columns if 'Update Status Y/N' in str(c)), None)
    if col:
        print(f"Sheet: {s} (header={h})")
        print(df[col].astype(str).str.strip().str.upper().value_counts())
        print(f"Total rows in df: {len(df)}")
        print("-" * 30)
