import pandas as pd
import glob
import os

files = [
    '1- IT Asset incomplete information.xlsx',
    '2.1 - Update OS - Replace.xlsx',
    '2.2 - Require Restart.xlsx',
    '3- Antivirus not Install.xlsx',
    '4- Built-in Firewall are not enable.xlsx',
    '5- Client devices are not joined to the domain.xlsx',
    '6- Privileged User management.xlsx',
    '7- Document request privileged user.xlsx'
]

summary_mapping = {
    '1- IT Asset incomplete information.xlsx': ['sheet No Company', 'sheet No BU', 'sheet No Group', 'sheet No Location'],
    '2.1 - Update OS - Replace.xlsx': 'Updated or Replaced Y/N',
    '2.2 - Require Restart.xlsx': 'Restart Action  Y/N',
    '3- Antivirus not Install.xlsx': 'Install Status Y/N',
    '4- Built-in Firewall are not enable.xlsx': 'Firewall enable Y/N',
    '5- Client devices are not joined to the domain.xlsx': 'Join status Y/N',
    '6- Privileged User management.xlsx': 'Remove accounts are members of the Administrators group Y/N',
    '7- Document request privileged user.xlsx': 'Is there evidence of the request? Y/N'
}

for f in files:
    if not os.path.exists(f):
        print(f"Skipping {f}: File not found")
        continue
    try:
        xl = pd.ExcelFile(f)
        print(f"\n--- {f} ---")
        for sn in xl.sheet_names:
            df = pd.read_excel(f, sheet_name=sn, nrows=0)
            print(f"Sheet: {sn}, Columns: {df.columns.tolist()}")
    except Exception as e:
        print(f"Error reading {f}: {e}")
