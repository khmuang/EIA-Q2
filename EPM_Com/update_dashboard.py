import pandas as pd
import os
import json

# Configuration for data extraction
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

def calculate_metrics():
    topic_results = []
    group_overall = {'Branch': {'Y': 0, 'N': 0}, 'HO': {'Y': 0, 'N': 0}, 'DC': {'Y': 0, 'N': 0}}
    
    for config in files_config:
        f = config['file']
        stats = {
            'Branch': {'Y': 0, 'N': 0, 'Teams': {}},
            'HO': {'Y': 0, 'N': 0, 'Teams': {}},
            'DC': {'Y': 0, 'N': 0, 'Teams': {}},
            'All': {'Y': 0, 'N': 0, 'Teams': {}}
        }
        
        if os.path.exists(f):
            try:
                xl = pd.ExcelFile(f)
                all_df = []
                for sn in xl.sheet_names:
                    if 'Pivot' in sn: continue
                    df = pd.read_excel(f, sheet_name=sn)
                    if config['col_group'] in df.columns and config['col_status'] in df.columns:
                        all_df.append(df[[config['col_group'], config['col_status']]])
                
                if all_df:
                    combined_df = pd.concat(all_df)
                    status_col, group_col = config['col_status'], config['col_group']
                    combined_df[status_col] = combined_df[status_col].fillna('N').astype(str).str.strip().str.upper()
                    combined_df[group_col] = combined_df[group_col].fillna('Unknown').astype(str).str.strip()
                    
                    for _, row in combined_df.iterrows():
                        raw_grp, status = row[group_col], ('Y' if row[status_col] == 'Y' else 'N')
                        cat = 'Branch'; uc = raw_grp.upper()
                        if 'HO' in uc or 'HEAD OFFICE' in uc: cat = 'HO'
                        elif 'DC' in uc or 'DATA CENTER' in uc: cat = 'DC'
                        
                        stats[cat][status] += 1
                        stats['All'][status] += 1
                        group_overall[cat][status] += 1
                        
                        if raw_grp not in stats[cat]['Teams']: stats[cat]['Teams'][raw_grp] = {'Y': 0, 'N': 0}
                        stats[cat]['Teams'][raw_grp][status] += 1
                        
                        if raw_grp not in stats['All']['Teams']: stats['All']['Teams'][raw_grp] = {'Y': 0, 'N': 0}
                        stats['All']['Teams'][raw_grp][status] += 1
            except Exception as e: print(f"Error: {e}")
            
        topic_results.append({'topic': config['topic'], 'name': config['name'], 'stats': stats})
    return topic_results, group_overall

def generate_html(topic_data, group_overall):
    data_json = json.dumps(topic_data)
    
    html_template = f"""<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8"><title>EIA Compliance Summary V2 - Business Edition</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {{ font-family: 'Sarabun', sans-serif; background-color: #f8fafc; color: #1e293b; min-height: 100vh; }}
        .sticky-header {{ position: sticky; top: 0; z-index: 50; background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(12px); border-bottom: 1px solid #e2e8f0; }}
        .filter-btn {{ transition: all 0.2s; }}
        .filter-btn.active {{ background: #059669; color: white; box-shadow: 0 4px 12px rgba(5,150,105,0.2); }}
        .topic-card {{ border: 1px solid #e2e8f0; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); background: white; }}
        .topic-card:hover {{ transform: translateY(-4px); box-shadow: 0 12px 24px rgba(0,0,0,0.05); border-color: #059669; }}
        .team-list {{ background: #f8fafc; border-radius: 12px; margin-top: 15px; max-height: 180px; overflow-y: auto; border: 1px solid #f1f5f9; }}
        .team-row {{ display: grid; grid-template-columns: 1fr auto auto; gap: 15px; padding: 6px 12px; border-bottom: 1px solid #f1f5f9; align-items: center; font-size: 10px; }}
        .team-row:last-child {{ border-bottom: none; }}
        ::-webkit-scrollbar {{ width: 4px; }}
        ::-webkit-scrollbar-thumb {{ background: #cbd5e1; border-radius: 10px; }}
        .chart-container {{ background: white; border: 1px solid #e2e8f0; border-radius: 20px; padding: 24px; margin-bottom: 32px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }}
    </style>
</head>
<body class="p-0">

    <!-- Sticky Header -->
    <div class="sticky-header mb-6">
        <div class="max-w-7xl mx-auto px-6 py-4 flex flex-col md:flex-row justify-between items-center gap-4">
            <div class="flex items-center gap-4">
                <div class="w-10 h-10 bg-emerald-600 rounded-lg flex items-center justify-center text-white text-xl shadow-md">📊</div>
                <div>
                    <h1 class="text-lg font-black text-slate-800 uppercase tracking-tight">EIA Compliance Summary <span id="current-filter-label" class="text-emerald-600">ALL</span></h1>
                    <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest">IT Asset & Security Management</p>
                </div>
            </div>
            <div class="flex bg-slate-100 p-1 rounded-xl border border-slate-200 gap-1">
                <button onclick="updateFilter('All')" id="btn-All" class="filter-btn px-6 py-2 rounded-lg text-xs font-black uppercase active">All</button>
                <button onclick="updateFilter('Branch')" id="btn-Branch" class="filter-btn px-6 py-2 rounded-lg text-xs font-black uppercase">Branch</button>
                <button onclick="updateFilter('HO')" id="btn-HO" class="filter-btn px-6 py-2 rounded-lg text-xs font-black uppercase">HO</button>
                <button onclick="updateFilter('DC')" id="btn-DC" class="filter-btn px-6 py-2 rounded-lg text-xs font-black uppercase">DC</button>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-6 pb-12">
        
        <!-- Overall KPIs -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
            <div class="bg-white border p-5 rounded-2xl shadow-sm">
                <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1">Success</p>
                <h2 id="summary-y" class="text-3xl font-black text-emerald-600">0</h2>
            </div>
            <div class="bg-white border p-5 rounded-2xl shadow-sm">
                <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1">Pending</p>
                <h2 id="summary-n" class="text-3xl font-black text-rose-500">0</h2>
            </div>
            <div class="bg-white border p-5 rounded-2xl shadow-sm">
                <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1">Total</p>
                <h2 id="summary-total" class="text-3xl font-black text-slate-700">0</h2>
            </div>
            <div class="bg-slate-800 p-5 rounded-2xl shadow-lg border-b-4 border-emerald-500">
                <p class="text-[9px] font-black text-emerald-400 uppercase tracking-widest mb-1">Health Rate</p>
                <h2 id="summary-pct" class="text-3xl font-black text-white">0%</h2>
            </div>
        </div>

        <!-- Overview Chart Section -->
        <div class="chart-container">
            <h3 class="text-xs font-black text-slate-400 uppercase tracking-widest mb-6 flex items-center gap-2">
                <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                Compliance Overview (% Success Rate)
            </h3>
            <div class="h-[400px]">
                <canvas id="overviewChart"></canvas>
            </div>
        </div>

        <!-- Topics Grid -->
        <h3 class="text-xs font-black text-slate-400 uppercase tracking-widest mb-4 px-2">Detailed Topic Breakdown</h3>
        <div id="topics-container" class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Cards injected by JS -->
        </div>

    </div>

    <script>
        const rawData = {data_json};
        let overviewChart = null;
        
        function formatNum(n) {{ return n.toLocaleString(); }}

        function updateFilter(filter) {{
            document.querySelectorAll('.filter-btn').forEach(btn => btn.classList.remove('active'));
            document.getElementById('btn-' + filter).classList.add('active');
            document.getElementById('current-filter-label').innerText = filter.toUpperCase();

            let grandY = 0, grandN = 0;
            const container = document.getElementById('topics-container');
            container.innerHTML = '';

            const chartLabels = [];
            const chartData = [];
            const chartColors = [];
            const chartRaw = [];

            rawData.forEach(t => {{
                const s = t.stats[filter];
                const total = s.Y + s.N;
                const pct = total > 0 ? ((s.Y / total) * 100).toFixed(1) : 0;
                grandY += s.Y; grandN += s.N;
                const colorKey = pct >= 80 ? 'emerald' : pct >= 50 ? 'amber' : 'rose';
                const colorHex = pct >= 80 ? '#10b981' : pct >= 50 ? '#f59e0b' : '#f43f5e';

                chartLabels.push('Topic ' + t.topic + ': ' + t.name.substring(0, 30) + '...');
                chartData.push(pct);
                chartColors.push(colorHex);
                chartRaw.push({{ y: s.Y, n: s.N }});

                let teamRows = '';
                Object.keys(s.Teams).sort().forEach(tm => {{
                    const ty = s.Teams[tm].Y; const tn = s.Teams[tm].N;
                    teamRows += `
                        <div class="team-row">
                            <span class="font-bold text-slate-600 truncate">${{tm}}</span>
                            <span class="text-emerald-600 font-black">Y: ${{ty}}</span>
                            <span class="text-rose-500 font-black">N: ${{tn}}</span>
                        </div>`;
                }});

                container.innerHTML += `
                    <div class="topic-card rounded-2xl p-6 flex flex-col">
                        <div class="flex justify-between items-start mb-4">
                            <div class="flex items-center gap-3">
                                <span class="bg-${{colorKey}}-50 text-${{colorKey}}-600 w-8 h-8 rounded-lg flex items-center justify-center font-black text-xs border border-${{colorKey}}-100">
                                    ${{t.topic}}
                                </span>
                                <h4 class="text-sm font-black text-slate-800">${{t.name}}</h4>
                            </div>
                            <span class="text-lg font-black text-${{colorKey}}-600">${{pct}}%</span>
                        </div>
                        
                        <div class="flex gap-6 mb-4 px-2">
                            <div><p class="text-[8px] font-black text-slate-400 uppercase">Success</p><p class="text-xl font-black text-emerald-600">${{formatNum(s.Y)}}</p></div>
                            <div><p class="text-[8px] font-black text-slate-400 uppercase">Pending</p><p class="text-xl font-black text-rose-500">${{formatNum(s.N)}}</p></div>
                        </div>

                        <div class="w-full bg-slate-100 h-1.5 rounded-full overflow-hidden mb-4">
                            <div class="bg-${{colorKey}}-500 h-full rounded-full" style="width: ${{pct}}%"></div>
                        </div>

                        <div class="team-list">
                            <div class="px-3 py-2 bg-slate-200/50 text-[9px] font-black text-slate-500 uppercase flex justify-between">
                                <span>Service Team / Group</span>
                                <span>Status (Y/N)</span>
                            </div>
                            ${{teamRows || '<div class="p-4 text-center text-[10px] text-slate-400 italic">No team data found</div>'}}
                        </div>
                    </div>
                `;
            }});

            const totalSum = grandY + grandN;
            const overallPct = totalSum > 0 ? ((grandY / totalSum) * 100).toFixed(1) : 0;
            
            document.getElementById('summary-y').innerText = formatNum(grandY);
            document.getElementById('summary-n').innerText = formatNum(grandN);
            document.getElementById('summary-total').innerText = formatNum(totalSum);
            document.getElementById('summary-pct').innerText = overallPct + '%';

            updateChart(chartLabels, chartData, chartColors, chartRaw);
        }}

        function updateChart(labels, data, colors, raw) {{
            const ctx = document.getElementById('overviewChart').getContext('2d');
            
            if (overviewChart) {{
                overviewChart.destroy();
            }}

            overviewChart = new Chart(ctx, {{
                type: 'bar',
                data: {{
                    labels: labels,
                    datasets: [{{
                        label: '% Success Rate',
                        data: data,
                        backgroundColor: colors,
                        borderRadius: 8,
                        barThickness: 20
                    }}]
                }},
                options: {{
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {{
                        x: {{ 
                            beginAtZero: true,
                            max: 100,
                            grid: {{ color: '#f1f5f9' }},
                            ticks: {{ 
                                font: {{ family: 'Sarabun', size: 10 }}, 
                                color: '#64748b',
                                callback: function(value) {{ return value + '%'; }}
                            }}
                        }},
                        y: {{ 
                            grid: {{ display: false }},
                            ticks: {{ font: {{ family: 'Sarabun', size: 10, weight: 'bold' }}, color: '#64748b' }}
                        }}
                    }},
                    plugins: {{
                        legend: {{ display: false }},
                        tooltip: {{
                            callbacks: {{
                                label: function(context) {{
                                    const i = context.dataIndex;
                                    const r = raw[i];
                                    return [
                                        'Success Rate: ' + context.formattedValue + '%',
                                        'Success (Y): ' + r.y.toLocaleString(),
                                        'Pending (N): ' + r.n.toLocaleString()
                                    ];
                                }}
                            }}
                        }}
                    }}
                }}
            }});
        }}

        updateFilter('All');
    </script>
</body></html>"""

    with open('EIA_Compliance_Summary_V2.html', 'w', encoding='utf-8') as f:
        f.write(html_template)

if __name__ == "__main__":
    print("Reverting to Horizontal % Success Chart (Stable)...")
    data, overall = calculate_metrics()
    generate_html(data, overall)
    print("Success: EIA_Compliance_Summary_V2.html reverted to stable version.")
