import pandas as pd
import os

file_path = 'EIA file/2.1 - Update OS - Replace.xlsx'
df = pd.read_excel(file_path, sheet_name='Update-Replace', header=2)

# Service Team and Updated or Replaced Y/N columns
team_col = 'Service Team'
status_col = 'Updated or Replaced Y/N'

if team_col in df.columns and status_col in df.columns:
    print(df.groupby(team_col)[status_col].value_counts(dropna=False))
else:
    print(f"Columns not found: {df.columns}")
