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

all_names = []
for sheet_name in xl.sheet_names:
    df = get_df(xl, sheet_name)
    all_names.extend(df['Name'].astype(str).tolist())

print(f"Total Names (with duplicates): {len(all_names)}")
print(f"Unique Names: {len(set(all_names))}")

# Check for duplicates
from collections import Counter
counts = Counter(all_names)
dups = {k: v for k, v in counts.items() if v > 1}
print(f"Number of Duplicate Names: {len(dups)}")
for name, count in list(dups.items())[:10]:
    print(f"  {name}: {count} times")
