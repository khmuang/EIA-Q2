import pandas as pd
import os

file_path = 'EIA file/6- Privileged User management.xlsx'
df = pd.read_excel(file_path, sheet_name='Admin group', header=3)

team_col = 'Service Team'
status_col = 'Remove accounts are members of the Administrators group Y/N'

if team_col in df.columns and status_col in df.columns:
    print(df.groupby(team_col)[status_col].value_counts(dropna=False))
else:
    print(f"Columns not found: {df.columns}")
