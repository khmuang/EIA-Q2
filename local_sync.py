import pandas as pd
import mysql.connector
import os
import json
from datetime import datetime

# 1. การตั้งค่าการเชื่อมต่อ MySQL
db_config = {
    'host': 'localhost',
    'user': 'root',
    'password': '',
    'database': 'eia_q2_db'
}

# 2. รายชื่อไฟล์และ Mapping
topics = {
    "1.1 IT Asset Management.xlsx": {"id": "1.1", "name": "IT Asset Management", "table": "topic_1_1", "status_col": "Asset update status Y/N", "team_col": "Groups"},
    "1.2 Install GLPI agent.xlsx": {"id": "1.2", "name": "Install GLPI agent", "table": "topic_1_2", "status_col": "GLPI setup status Y/N", "team_col": "Serviced By"},
    "2. Update OS.xlsx": {"id": "2", "name": "Update OS", "table": "topic_2", "status_col": "OS update status Y/N", "team_col": "Serviced By"},
    "3. Require Restart.xlsx": {"id": "3", "name": "Require Restart", "table": "topic_3", "status_col": "Restart update Y/N", "team_col": "Serviced By"},
    "4. Antivirus Installation.xlsx": {"id": "4", "name": "Antivirus Installation", "table": "topic_4", "status_col": "AV update Y/N", "team_col": "Serviced By"},
    "5. Built-in Firewall Enablement.xlsx": {"id": "5", "name": "Built-in Firewall Enablement", "table": "topic_5", "status_col": "Firewall update Y/N", "team_col": "Serviced By"},
    "6. Client join domain.xlsx": {"id": "6", "name": "Client join domain", "table": "topic_6", "status_col": "Join domain update Y/N", "team_col": "Serviced By"},
    "7. Privileged User management.xlsx": {"id": "7", "name": "Privileged User management", "table": "topic_7", "status_col": "Std admin update Y/N", "team_col": "Serviced By"},
    "8. Document Request.xlsx": {"id": "8", "name": "Document Request", "table": "topic_8", "status_col": "Document request update Y/N", "team_col": "Serviced By"}
}

def sync_data():
    try:
        conn = mysql.connector.connect(**db_config)
        cursor = conn.cursor(dictionary=True)
        print("Connected to MySQL successfully.")

        summary_topics = []
        team_data = {} # เก็บข้อมูลสรุปรายทีม

        for filename, mapping in topics.items():
            if os.path.exists(filename):
                print(f"Processing {filename}...")
                df = pd.read_excel(filename, header=2)
                
                # Cleaning & Anonymization
                df['action_status'] = df[mapping['status_col']].fillna('N').apply(lambda x: 'Y' if str(x).strip().upper() == 'Y' else 'N')
                df['item_name_anon'] = [f"DEV_{i+1:05d}" for i in range(len(df))]
                df['service_team_val'] = df[mapping['team_col']].fillna('Unknown').astype(str).str.strip()
                df['phase_val'] = df['EIA Phase'].fillna('Q2')

                # Update Local MySQL
                cursor.execute(f"TRUNCATE TABLE {mapping['table']}")
                sql_insert = f"INSERT INTO {mapping['table']} (item_name, service_team, action_status, eia_phase) VALUES (%s, %s, %s, %s)"
                data_tuples = list(zip(df['item_name_anon'], df['service_team_val'], df['action_status'], df['phase_val']))
                cursor.executemany(sql_insert, data_tuples)
                
                # Topic Summary
                total = len(df)
                success = len(df[df['action_status'] == 'Y'])
                pct = round((success / total * 100), 2) if total > 0 else 0
                
                summary_topics.append({
                    "id": mapping['id'],
                    "name": mapping['name'],
                    "total": total,
                    "success": success,
                    "compliance": pct
                })

                # Team Summary logic
                for team, group in df.groupby('service_team_val'):
                    if team not in team_data: team_data[team] = {"topics": {}}
                    t_total = len(group)
                    t_success = len(group[group['action_status'] == 'Y'])
                    team_data[team]["topics"][mapping['id']] = {
                        "total": t_total,
                        "success": t_success,
                        "compliance": round((t_success / t_total * 100), 2) if t_total > 0 else 0
                    }

        # Calculate Overall for each team
        for team in team_data:
            t_scores = [v['compliance'] for v in team_data[team]['topics'].values()]
            team_data[team]['overall'] = round(sum(t_scores) / len(t_scores), 2) if t_scores else 0

        conn.commit()
        
        # Save JSON for Web Dashboard
        dashboard_json = {
            "last_refresh": datetime.now().strftime("%d %b %Y %H:%M"),
            "deadline": "20 May 2026",
            "overall_score": round(sum(d['compliance'] for d in summary_topics) / len(summary_topics), 2) if summary_topics else 0,
            "total_units": sum(d['total'] for d in summary_topics),
            "topics": summary_topics,
            "teams": team_data
        }
        
        with open('dashboard_data.json', 'w', encoding='utf-8') as f:
            json.dump(dashboard_json, f, ensure_ascii=False, indent=2)
        
        print("\nSync Complete! dashboard_data.json updated with Team data.")

    except Exception as e:
        print(f"Error: {e}")
    finally:
        if conn.is_connected():
            cursor.close()
            conn.close()

if __name__ == "__main__":
    sync_data()
