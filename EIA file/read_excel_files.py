import pandas as pd
import os

files = [
    "1- IT Asset incomplete information.xlsx",
    "2.1 - Update OS - Replace.xlsx",
    "2.2 - Require Restart.xlsx",
    "3- Antivirus not Install.xlsx",
    "4- Built-in Firewall are not enable.xlsx",
    "5- Client devices are not joined to the domain.xlsx",
    "6- Privileged User management.xlsx",
    "7- Document request privileged user.xlsx"
]

for file in files:
    if os.path.exists(file):
        print(f"--- {file} ---")
        try:
            xl = pd.ExcelFile(file)
            print(f"Sheets: {xl.sheet_names}")
            for sheet in xl.sheet_names:
                df = pd.read_excel(file, sheet_name=sheet)
                print(f"Sheet '{sheet}': {len(df)} rows, Columns: {df.columns.tolist()}")
        except Exception as e:
            print(f"Error reading {file}: {e}")
        print("\n")
    else:
        print(f"File {file} not found.\n")
