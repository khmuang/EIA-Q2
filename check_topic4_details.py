import pandas as pd
import os

file_path = 'EIA file/3- Antivirus not Install.xlsx'
df_raw = pd.read_excel(file_path, sheet_name='No AV', header=None)
header_row = 0
for i, row in df_raw.iterrows():
    if 'Bu' in row.values:
        header_row = i
        break
df = pd.read_excel(file_path, sheet_name='No AV', header=header_row)

team_col = 'Service Team'
status_col = 'Install Status Y/N'

if team_col in df.columns and status_col in df.columns:
    print(df.groupby(team_col)[status_col].value_counts(dropna=False))
else:
    print(f"Columns not found: {df.columns}")
