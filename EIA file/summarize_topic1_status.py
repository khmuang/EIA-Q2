import pandas as pd
import os

file_path = "1- IT Asset incomplete information.xlsx"
sheets = ['No Company', 'No BU', 'No Group', 'No Location']

summary_data = []

if os.path.exists(file_path):
    for sheet in sheets:
        # Read without header first to find the row with 'Name'
        df_raw = pd.read_excel(file_path, sheet_name=sheet, header=None)
        
        # Find header row index
        header_row = 0
        for i, row in df_raw.iterrows():
            if 'Name' in row.values:
                header_row = i
                break
        
        # Re-read with correct header
        df = pd.read_excel(file_path, sheet_name=sheet, header=header_row)
        
        col_name = 'Update Status Y/N'
        if col_name in df.columns:
            # Clean the data: convert to string, upper case, and handle NaNs
            status_counts = df[col_name].fillna('Pending').astype(str).str.upper().value_counts()
            
            y_count = status_counts.get('Y', 0)
            n_count = status_counts.get('N', 0)
            pending_count = status_counts.get('PENDING', 0)
            total = len(df)
            
            summary_data.append({
                'Sheet': sheet,
                'Total': total,
                'Updated (Y)': y_count,
                'Not Updated (N)': n_count,
                'Pending (Blank)': pending_count
            })
        else:
            summary_data.append({
                'Sheet': sheet,
                'Total': len(df),
                'Updated (Y)': 'N/A',
                'Not Updated (N)': 'N/A',
                'Pending (Blank)': 'N/A'
            })

    # Create summary DataFrame
    summary_df = pd.DataFrame(summary_data)
    print(summary_df.to_string(index=False))
else:
    print(f"File {file_path} not found.")
