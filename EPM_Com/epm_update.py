import pandas as pd
import json
import os
import re
from datetime import datetime

# CONFIGURATION
EXCEL_FILE = 'EPM_Com/EMP_Com.xlsx'
DATA_SHEET = 'EIA Repot 05032026'
OUTPUT_HTML = 'EPM_Com/epm_dashboard.html'

def process_epm_data():
    if not os.path.exists(EXCEL_FILE):
        print(f"Error: {EXCEL_FILE} not found.")
        return None

    print(f"Reading data from {EXCEL_FILE}...")
    df = pd.read_excel(EXCEL_FILE, sheet_name=DATA_SHEET)
    
    # Filter columns to only what we need
    required_fields = [
        'Serviced By', 'OS End of Support Status', 'Patch Healthy', 
        'Missing Critical Patches', 'Antivirus', 'Antivirus Compliant', 
        'Firewall Compliant', 'Standard Admin Only', 'Pending Restart', 
        'GLPI Agent Status', 'Days Since Last Reboot', 'Inactive 30+ Days'
    ]
    
    # Ensure all columns exist
    df = df[[c for c in required_fields if c in df.columns]]
    df['Serviced By'] = df['Serviced By'].fillna('Unknown').astype(str).str.strip()

    sections = []
    
    # Helper to define Success (Y) vs Pending (N) for each field
    # Adjust these logic rules based on your data values
    compliance_rules = {
        'OS End of Support Status': lambda x: str(x).strip().upper() in ['ACTIVE', 'SUPPORTED', 'Y'],
        'Patch Healthy': lambda x: str(x).strip().upper() in ['HEALTHY', 'YES', 'Y', 'TRUE'],
        'Missing Critical Patches': lambda x: str(x).strip() in ['0', 'None', 'NONE'],
        'Antivirus Compliant': lambda x: str(x).strip().upper() in ['YES', 'Y', 'COMPLIANT'],
        'Firewall Compliant': lambda x: str(x).strip().upper() in ['YES', 'Y', 'COMPLIANT'],
        'Standard Admin Only': lambda x: str(x).strip().upper() in ['YES', 'Y', 'TRUE'],
        'Pending Restart': lambda x: str(x).strip().upper() in ['NO', 'N', 'FALSE'],
        'GLPI Agent Status': lambda x: str(x).strip().upper() in ['INSTALLED', 'ACTIVE', 'YES', 'Y'],
        'Inactive 30+ Days': lambda x: str(x).strip().upper() in ['NO', 'N', 'FALSE'],
        'Days Since Last Reboot': lambda x: pd.to_numeric(x, errors='coerce') <= 7 if pd.to_numeric(x, errors='coerce') is not None else False
    }

    topics = [c for c in required_fields if c != 'Serviced By']
    
    for i, topic in enumerate(topics, 1):
        rule = compliance_rules.get(topic, lambda x: False)
        
        # Apply rule to get Y/N
        df['temp_status'] = df[topic].apply(lambda x: 'Y' if rule(x) else 'N')
        
        # Group by Serviced By
        grp = df.groupby(['Serviced By', 'temp_status']).size().unstack(fill_value=0).reset_index()
        if 'Y' not in grp.columns: grp['Y'] = 0
        if 'N' not in grp.columns: grp['N'] = 0
        
        details = []
        for _, row in grp.iterrows():
            details.append({
                "Service Team": str(row['Serviced By']),
                "Y": int(row['Y']),
                "N": int(row['N'])
            })
            
        sections.append({
            "id": i,
            "title": topic,
            "details": details
        })

    # Metadata
    now = datetime.now()
    thai_year = now.year + 543
    timestamp_str = f"{now.strftime('%d/%m')}/{thai_year} {now.strftime('%H:%M:%S')}"

    return {
        "timestamp": timestamp_str,
        "sections": sections
    }

def update_dashboard(data):
    if not data: return
    
    # Read template (we'll create this next)
    template_path = 'EPM_Com/epm_dashboard.html'
    if not os.path.exists(template_path):
        print("Template not found. Creating a new one...")
        return # We will create the HTML file in the next step

    with open(template_path, 'r', encoding='utf-8') as f:
        html = f.read()

    # Inject data
    updated_html = re.sub(r'const rawData = \{.*?\};', f'const rawData = {json.dumps(data, ensure_ascii=False, indent=4)};', html, flags=re.DOTALL)
    
    with open(template_path, 'w', encoding='utf-8') as f:
        f.write(updated_html)
    
    print(f"Successfully updated {template_path}")

if __name__ == "__main__":
    data = process_epm_data()
    # Note: We need to create epm_dashboard.html before calling update_dashboard
    print("Data processed. JSON result ready.")
    print(json.dumps(data, ensure_ascii=False, indent=2)[:500] + "...")
    
    # Store data globally for the next step
    with open('EPM_Com/data.json', 'w', encoding='utf-8') as f:
        json.dump(data, f, ensure_ascii=False, indent=4)
