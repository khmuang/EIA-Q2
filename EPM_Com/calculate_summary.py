import pandas as pd
import os

# Config from EIA summary.xlsx and previous research
files_config = [
    {'file': '1- IT Asset incomplete information.xlsx', 'topic': '1', 'col_status': 'Update Status Y/N', 'col_group': 'Groups'},
    {'file': '2.1 - Update OS - Replace.xlsx', 'topic': '2.1', 'col_status': 'Updated or Replaced Y/N', 'col_group': 'Service Team'},
    {'file': '2.2 - Require Restart.xlsx', 'topic': '2.2', 'col_status': 'Restart Action  Y/N', 'col_group': 'Service Team'},
    {'file': '3- Antivirus not Install.xlsx', 'topic': '3', 'col_status': 'Install Status Y/N', 'col_group': 'Service Team'},
    {'file': '4- Built-in Firewall are not enable.xlsx', 'topic': '4', 'col_status': 'Firewall enable Y/N', 'col_group': 'Service Team'},
    {'file': '5- Client devices are not joined to the domain.xlsx', 'topic': '5', 'col_status': 'Join status Y/N', 'col_group': 'Service Team'},
    {'file': '6- Privileged User management.xlsx', 'topic': '6', 'col_status': 'Remove accounts are members of the Administrators group Y/N', 'col_group': 'Service Team'},
    {'file': '7- Document request privileged user.xlsx', 'topic': '7', 'col_status': 'Is there evidence of the request? Y/N', 'col_group': 'Service Team'}
]

results = []

for config in files_config:
    f = config['file']
    if not os.path.exists(f):
        continue
    try:
        xl = pd.ExcelFile(f)
        all_df = []
        for sn in xl.sheet_names:
            if 'Pivot' in sn: continue
            df = pd.read_excel(f, sheet_name=sn)
            # Find relevant columns
            if config['col_group'] in df.columns and config['col_status'] in df.columns:
                all_df.append(df[[config['col_group'], config['col_status']]])
        
        if not all_df: continue
        
        combined_df = pd.concat(all_df)
        # Process Status: Replace NaN with 'N', then everything else to 'N' if not 'Y'
        combined_df[config['col_status']] = combined_df[config['col_status']].fillna('N').astype(str).str.strip().str.upper()
        # Clean Group name
        combined_df[config['col_group']] = combined_df[config['col_group']].fillna('Unknown').astype(str).str.strip()
        
        # Group and count
        summary = combined_df.groupby([config['col_group'], config['col_status']]).size().unstack(fill_value=0)
        
        # Ensure 'Y' and 'N' columns exist
        if 'Y' not in summary: summary['Y'] = 0
        if 'N' not in summary: summary['N'] = 0
        
        # Include other non-Y values into N
        for col in summary.columns:
            if col not in ['Y', 'N']:
                summary['N'] += summary[col]
        
        summary = summary[['Y', 'N']]
        
        for group in ['Branch', 'HO', 'DC']:
            if group in summary.index:
                results.append({
                    'Topic': config['topic'],
                    'File': f,
                    'Group': group,
                    'Success (Y)': summary.loc[group, 'Y'],
                    'Pending (N)': summary.loc[group, 'N']
                })
            else:
                results.append({
                    'Topic': config['topic'],
                    'File': f,
                    'Group': group,
                    'Success (Y)': 0,
                    'Pending (N)': 0
                })
                
    except Exception as e:
        print(f"Error processing {f}: {e}")

final_summary = pd.DataFrame(results)
print("\n--- EIA Dashboard Summary by Group ---")
# Pivot table for better viewing
pivot_summary = final_summary.pivot(index=['Topic', 'File'], columns='Group', values=['Success (Y)', 'Pending (N)'])
# Reorder columns to show Y and N together for each group
pivot_summary = pivot_summary.sort_index(axis=1, level=1)
print(pivot_summary.to_string())

# Save to Excel for user
final_summary.to_excel('EIA_Group_Summary_Result.xlsx', index=False)
print("\nResults saved to 'EIA_Group_Summary_Result.xlsx'")
