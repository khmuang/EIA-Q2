import pandas as pd
import os

file_path = "1- IT Asset incomplete information.xlsx"
sheets = ['No Company', 'No BU', 'No Group', 'No Location']

if os.path.exists(file_path):
    for sheet in sheets:
        print(f"\n=== Sheet: {sheet} ===")
        # Read without header first to find the row with 'Name'
        df_raw = pd.read_excel(file_path, sheet_name=sheet, header=None)
        
        # Find header row index (where 'Name' is found)
        header_row = 0
        for i, row in df_raw.iterrows():
            if 'Name' in row.values:
                header_row = i
                break
        
        # Re-read with correct header
        df = pd.read_excel(file_path, sheet_name=sheet, header=header_row)
        
        # Select relevant columns
        target_cols = ['Name', 'BU', 'Company', 'Locations', 'Operating System - Name', 'Networking - IP']
        available_cols = [c for c in target_cols if c in df.columns]
        
        if not df.empty:
            # Display first 20 rows
            print(df[available_cols].head(20).to_string(index=False))
            if len(df) > 20:
                print(f"\n... (Total {len(df)} items in this sheet)")
        else:
            print("No data items found.")
else:
    print(f"File {file_path} not found.")
