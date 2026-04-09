import pandas as pd
import os

# Data for Executive Summary
data = [
    {"ID": 1, "Topic Name": "IT Asset incomplete info", "Baseline (Total)": 344, "Initial Success": 280, "Current Success (V25)": 306, "Improvement": 26, "Compliance %": 88.95},
    {"ID": 2, "Topic Name": "Update OS - Replace", "Baseline (Total)": 11119, "Initial Success": 139, "Current Success (V25)": 345, "Improvement": 206, "Compliance %": 3.10},
    {"ID": 3, "Topic Name": "Require Restart", "Baseline (Total)": 2384, "Initial Success": 2205, "Current Success (V25)": 2316, "Improvement": 111, "Compliance %": 97.15},
    {"ID": 4, "Topic Name": "Antivirus not Install", "Baseline (Total)": 631, "Initial Success": 337, "Current Success (V25)": 366, "Improvement": 29, "Compliance %": 58.00},
    {"ID": 5, "Topic Name": "Built-in Firewall enable", "Baseline (Total)": 6973, "Initial Success": 5075, "Current Success (V25)": 6222, "Improvement": 1147, "Compliance %": 89.23},
    {"ID": 6, "Topic Name": "Join Domain status", "Baseline (Total)": 536, "Initial Success": 311, "Current Success (V25)": 389, "Improvement": 78, "Compliance %": 72.57},
    {"ID": 7, "Topic Name": "Privileged User mgmt", "Baseline (Total)": 3173, "Initial Success": 333, "Current Success (V25)": 2097, "Improvement": 1764, "Compliance %": 66.09},
    {"ID": 8, "Topic Name": "Document request", "Baseline (Total)": 9, "Initial Success": 3, "Current Success (V25)": 3, "Improvement": 0, "Compliance %": 33.33}
]

df = pd.DataFrame(data)

# Calculate Grand Total
grand_total = {
    "ID": "",
    "Topic Name": "GRAND TOTAL",
    "Baseline (Total)": df["Baseline (Total)"].sum(),
    "Initial Success": df["Initial Success"].sum(),
    "Current Success (V25)": df["Current Success (V25)"].sum(),
    "Improvement": df["Improvement"].sum(),
    "Compliance %": round((df["Current Success (V25)"].sum() / df["Baseline (Total)"].sum()) * 100, 2)
}

df_final = pd.concat([df, pd.DataFrame([grand_total])], ignore_index=True)

# Output Path
output_dir = "Executive_Summary_V25_Mar2026"
output_file = os.path.join(output_dir, "EIA_Executive_Summary_Comparison_V25.xlsx")

# Write to Excel
with pd.ExcelWriter(output_file, engine='openpyxl') as writer:
    df_final.to_excel(writer, sheet_name='Summary Comparison', index=False)
    
    # Optional: If you have detailed CSV, add it as a second sheet
    if os.path.exists(os.path.join(output_dir, "Appendix_Raw_Data_Detailed.csv")):
        df_appendix = pd.read_csv(os.path.join(output_dir, "Appendix_Raw_Data_Detailed.csv"))
        df_appendix.to_excel(writer, sheet_name='Detailed Appendix', index=False)

print(f"SUCCESS: Executive Summary exported to {output_file}")
