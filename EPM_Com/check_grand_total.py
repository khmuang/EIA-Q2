import pandas as pd
import os

files_config = [
    {'topic': '1', 'name': 'IT Asset incomplete information', 'file': '1- IT Asset incomplete information.xlsx', 'col_status': 'Update Status Y/N', 'col_group': 'Groups'},
    {'topic': '2.1', 'name': 'Update OS - Replace', 'file': '2.1 - Update OS - Replace.xlsx', 'col_status': 'Updated or Replaced Y/N', 'col_group': 'Service Team'},
    {'topic': '2.2', 'name': 'Require Restart', 'file': '2.2 - Require Restart.xlsx', 'col_status': 'Restart Action  Y/N', 'col_group': 'Service Team'},
    {'topic': '3', 'name': 'Antivirus not Install', 'file': '3- Antivirus not Install.xlsx', 'col_status': 'Install Status Y/N', 'col_group': 'Service Team'},
    {'topic': '4', 'name': 'Built-in Firewall enable', 'file': '4- Built-in Firewall are not enable.xlsx', 'col_status': 'Firewall enable Y/N', 'col_group': 'Service Team'},
    {'topic': '5', 'name': 'Join Domain status', 'file': '5- Client devices are not joined to the domain.xlsx', 'col_status': 'Join status Y/N', 'col_group': 'Service Team'},
    {'topic': '6', 'name': 'Privileged User management', 'file': '6- Privileged User management.xlsx', 'col_status': 'Remove accounts are members of the Administrators group Y/N', 'col_group': 'Service Team'},
    {'topic': '7', 'name': 'Document request evidence', 'file': '7- Document request privileged user.xlsx', 'col_status': 'Is there evidence of the request? Y/N', 'col_group': 'Service Team'}
]

total_grand_total = 0
for config in files_config:
    f = config['file']
    if not os.path.exists(f): continue
    try:
        xl = pd.ExcelFile(f)
        topic_total = 0
        topic_y = 0
        for sn in xl.sheet_names:
            if 'Pivot' in sn: continue
            df = pd.read_excel(xl, sn)
            if config['col_status'] in df.columns:
                topic_total += len(df)
                y_count = len(df[df[config['col_status']].astype(str).str.strip().str.upper() == 'Y'])
                topic_y += y_count
                print(f"  Sheet '{sn}': Rows={len(df)}, Y={y_count}")
        print(f"Topic {config['topic']}: Total={topic_total}, Y={topic_y}, N={topic_total-topic_y}")
        total_grand_total += topic_total
    except Exception as e:
        print(f"Error {f}: {e}")

print(f"\nGRAND TOTAL: {total_grand_total}")
