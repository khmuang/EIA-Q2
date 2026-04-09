import pandas as pd
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

all_groups = set()

for f in files:
    if not os.path.exists(f):
        continue
    try:
        xl = pd.ExcelFile(f)
        for sn in xl.sheet_names:
            if 'Pivot' in sn:
                continue
            df = pd.read_excel(f, sheet_name=sn)
            # Find relevant column
            col = next((c for c in df.columns if c in ['Groups', 'Service Team']), None)
            if col:
                groups = df[col].dropna().unique()
                all_groups.update([str(g).strip() for g in groups])
    except Exception as e:
        print(f"Error reading {f}: {e}")

print("\nUnique Groups/Service Teams found across all files:")
for g in sorted(list(all_groups)):
    print(f"- {g}")
