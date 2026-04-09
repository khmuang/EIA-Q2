import pandas as pd
import os

file_path = "1- IT Asset incomplete information.xlsx"
sheets = ['No Company', 'No BU', 'No Group', 'No Location']

if os.path.exists(file_path):
    for sheet in sheets:
        print(f"\n=== Sheet: {sheet} ===")
        # Header is on row 1 (0-indexed)
        df = pd.read_excel(file_path, sheet_name=sheet, header=1)
        
        # Select relevant columns to display
        display_cols = ['Name', 'BU', 'Company', 'Locations', 'Operating System - Name', 'Networking - IP']
        # Check if columns exist before selecting
        actual_cols = [col for col in display_cols if col in df.columns]
        
        if not df.empty:
            # Show top 10 rows for brevity, or more if needed
            print(df[actual_cols].head(15).to_string(index=False))
            print(f"... and {len(df) - 15} more rows" if len(df) > 15 else "")
        else:
            print("No data in this sheet.")
else:
    print(f"File {file_path} not found.")
