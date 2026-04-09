import pandas as pd
import json
import sys

try:
    xls = pd.ExcelFile('EIA summary.xlsx')
    summary = {}
    for sheet in xls.sheet_names:
        df = pd.read_excel(xls, sheet_name=sheet)
        summary[sheet] = df.head(10).to_dict(orient='records')
    print(json.dumps(summary, indent=2, default=str))
except Exception as e:
    print(f"Error reading Excel file: {e}", file=sys.stderr)
