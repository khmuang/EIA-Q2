import mysql.connector
import datetime

# 1. Database Configuration
db_config = {
    'host': 'localhost',
    'user': 'root',
    'password': '',
    'database': 'eia_compliance'
}

def fetch_dashboard_data():
    try:
        conn = mysql.connector.connect(**db_config)
        cursor = conn.cursor(dictionary=True)
        
        # Get Current Week/Year
        now = datetime.datetime.now()
        week = now.isocalendar()[1]
        year = now.year
        
        # 2. Fetch Grand Total
        cursor.execute("SELECT COUNT(*) as total FROM inventory_reports WHERE report_week = %s AND report_year = %s", (week, year))
        total_data = cursor.fetchone()
        current_total = total_data['total'] if total_data['total'] > 0 else 1
        
        # 3. Fetch Topic Summaries
        topic_queries = {
            'Domain': 'joined_approved_domain',
            'OS': 'os_eos_status',
            'Patch': 'patch_healthy',
            'AV': 'av_compliant',
            'Firewall': 'firewall_compliant',
            'Admin': 'standard_admin_only'
        }
        
        topic_results = {}
        for label, col in topic_queries.items():
            query = f"SELECT SUM(CASE WHEN {col} IN ('Y', 'Yes', 'Compliant', 'Success') THEN 1 ELSE 0 END) as success FROM inventory_reports WHERE report_week = %s AND report_year = %s"
            cursor.execute(query, (week, year))
            res = cursor.fetchone()
            s = int(res['success'] or 0)
            p = current_total - s
            topic_results[label] = {
                'Success': s, 
                'Pending': p, 
                'Rate': round((s/current_total)*100, 1)
            }

        # 4. Summary by Serviced By
        cursor.execute("SELECT serviced_by, COUNT(*) as count FROM inventory_reports WHERE report_week = %s AND report_year = %s GROUP BY serviced_by", (week, year))
        serviced_summary = {row['serviced_by']: row['count'] for row in cursor.fetchall()}

        # 5. Top 5 Successful BU (Patch Healthy)
        cursor.execute("""
            SELECT bu, COUNT(*) as success_count 
            FROM inventory_reports 
            WHERE patch_healthy IN ('Y', 'Yes', 'Compliant', 'Success') 
            AND report_week = %s AND report_year = %s 
            GROUP BY bu 
            ORDER BY success_count DESC 
            LIMIT 5
        """, (week, year))
        bu_data = cursor.fetchall()
        bu_labels = [row['bu'] for row in bu_data]
        bu_values = [row['success_count'] for row in bu_data]

        conn.close()
        return current_total, topic_results, serviced_summary, bu_labels, bu_values

    except Exception as e:
        print(f"Database Error: {e}")
        return 0, {}, {}, [], []

# Get Data
current_total, topic_results, serviced_summary, bu_labels, bu_values = fetch_dashboard_data()
grand_total_standard = 25169
patch_rate = topic_results.get('Patch', {'Rate': 0})['Rate']
patch_pending = topic_results.get('Patch', {'Pending': 0})['Pending']

# 6. Generate HTML
html_content = f"""
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EIA Dashboard V25 (MySQL Live)</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&family=Sarabun:wght@400;700&display=swap" rel="stylesheet">
    <style>
        :root {{ --glass: rgba(255, 255, 255, 0.05); }}
        body {{ font-family: 'Inter', 'Sarabun', sans-serif; background: #0f172a; color: white; min-height: 100vh; }}
        .glass-card {{ background: var(--glass); backdrop-filter: blur(16px); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 1.5rem; position: relative; overflow: hidden; transition: all 0.4s ease; }}
        .glass-card:hover {{ transform: translateY(-8px); background: rgba(255, 255, 255, 0.08); }}
        .health-pulse {{ width: 14px; height: 14px; border-radius: 50%; background: #10b981; box-shadow: 0 0 15px #10b981; animation: pulse 2s infinite; }}
        @keyframes pulse {{ 0% {{ transform: scale(0.95); }} 70% {{ transform: scale(1); box-shadow: 0 0 0 10px rgba(16, 185, 129, 0); }} 100% {{ transform: scale(0.95); }} }}
    </style>
</head>
<body class="p-4 md:p-12">
    <div class="max-w-7xl mx-auto">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-12 gap-6">
            <div>
                <h1 class="text-5xl font-black tracking-tighter mb-2 bg-clip-text text-transparent bg-gradient-to-r from-blue-400 to-emerald-400">EIA COMPLIANCE <span class="text-white">DATABASE</span></h1>
                <p class="text-slate-400 font-bold uppercase tracking-widest text-xs">Standard V25 | {datetime.datetime.now().strftime('%d %B %Y')}</p>
            </div>
            <div class="flex items-center gap-6 bg-white/5 p-4 rounded-3xl border border-white/10">
                <div class="health-pulse"></div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-12">
            <div class="glass-card p-8 border-t-4 border-blue-500">
                <p class="text-xs font-black uppercase text-slate-400 mb-2">Grand Total Population</p>
                <h2 class="text-6xl font-black text-white mb-2">{current_total:,}</h2>
                <p class="text-xs text-slate-500">Target: {grand_total_standard:,}</p>
            </div>
            <div class="glass-card p-8 border-t-4 border-emerald-500">
                <p class="text-xs font-black uppercase text-slate-400 mb-2">Overall Compliance</p>
                <h2 class="text-6xl font-black text-emerald-400">{patch_rate}%</h2>
                <div class="w-full bg-white/10 rounded-full h-3 mt-6"><div class="bg-emerald-500 h-3 rounded-full" style="width: {patch_rate}%"></div></div>
            </div>
            <div class="glass-card p-8 border-t-4 border-rose-500">
                <p class="text-xs font-black uppercase text-slate-400 mb-2">Urgent Remediation</p>
                <h2 class="text-6xl font-black text-rose-500">{patch_pending:,}</h2>
                <p class="text-xs text-rose-500/60 mt-2 uppercase">Required Action</p>
            </div>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-12">
            {"".join([f'<div class="glass-card p-6"><p class="text-[10px] font-black uppercase text-slate-500 mb-1">{l}</p><p class="text-3xl font-black text-white">{r["Success"]:,}</p><p class="text-xs font-bold text-blue-400 mt-1">{r["Rate"]}% OK</p></div>' for l, r in topic_results.items()])}
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <div class="glass-card p-10">
                <h3 class="text-2xl font-black mb-8 flex items-center gap-3"><span class="w-2 h-10 bg-blue-500 rounded-full"></span>By Service Area</h3>
                <div class="space-y-8">
                    {"".join([f'<div><div class="flex justify-between text-sm font-black mb-3"><span>{k}</span><span>{v:,}</span></div><div class="w-full bg-white/5 rounded-full h-4 p-1"><div class="bg-blue-600 h-2 rounded-full" style="width: {(v/current_total)*100}%"></div></div></div>' for k, v in serviced_summary.items()])}
                </div>
            </div>
            <div class="glass-card p-10">
                <h3 class="text-2xl font-black mb-8 flex items-center gap-3"><span class="w-2 h-10 bg-emerald-500 rounded-full"></span>Top BU</h3>
                <div class="flex flex-col gap-5">
                    {"".join([f'<div class="flex items-center gap-6"><span class="w-32 text-right text-xs font-black text-slate-500">{l}</span><div class="flex-1 bg-white/5 rounded-2xl h-10 overflow-hidden"><div class="bg-emerald-500 h-10 flex items-center px-4" style="width: {(v/max(bu_values))*100 if bu_values else 0}%"><span class="text-xs font-black text-white">{v:,}</span></div></div></div>' for l, v in zip(bu_labels, bu_values)])}
                </div>
            </div>
        </div>
    </div>
</body>
</html>
"""

with open('EIA_Dashboard_V25.html', 'w', encoding='utf-8') as f:
    f.write(html_content)
print("EIA_Dashboard_V25.html generated successfully via MySQL.")
