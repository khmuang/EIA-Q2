# EIA Compliance Dashboard Project Memory

## 📋 Project Overview
Project for monitoring IT Asset and Security Compliance across 8 core topics. Data is extracted from multiple Excel files and visualized in a dual-dashboard system (Summary & Detail).

## 🚀 Quick Start (Commands)
To update both dashboards with the latest Excel data:
```powershell
python update_dashboard.py
```

## 🛠️ Features & Usage
- **Dual Output:** 
  1. `EIA_Compliance_Summary_V2.html`: High-level light-themed summary (Hybrid Design).
  2. `EIA_Compliance_Dashboard.html`: Detailed dark-themed interactive dashboard with team breakdowns.
- **Sticky Filters:** Navigation bar stays at the top while scrolling in the Detail dashboard.
- **Standard V23 Logic:** Hardcoded to verify Grand Total 25,169 and specific Success counts.
- **Deep Linking:** Clickable cards in the Summary view link directly to specific topics in the Detail view.

## 📂 Key Files
- `update_dashboard.py`: The main engine that calculates metrics and generates both HTML files.
- `EIA_Compliance_Summary_V2.html`: Modern light-mode overview with health rings and urgent items.
- `EIA_Compliance_Dashboard.html`: Full detail view with Glassmorphism and team-level metrics.
- `calculate_summary.py`: Auxiliary script for raw Excel result generation.

## 🧠 Business Logic & Rules (Standard V23)
1. **Grand Total:** Verified at **25,169** total rows across all topics.
2. **Topic 1 (Asset Info):** Uses Dynamic Header search (Total 344).
3. **Topic 6 (Join Domain):** Uses **Unique Computer Name** logic for Success (355).
4. **Column Mapping:**
   - Topic 4 (Antivirus): Index 20.
   - Topic 7 (Privileged User): Index 21.
5. **Categorization:**
   - **HO:** Contains "HO" or "Head Office".
   - **DC:** Contains "DC" or "Data Center".
   - **Branch:** Default category.

## 🎨 UI Standards (Premium V2)
- **Glassmorphism:** Translucent card designs with background blur.
- **Shimmer Effect:** Subtle light animations on cards.
- **Health Indicators:** Emerald (>=90%), Blue (70-89%), Amber (50-69%), Rose (<50%).
- **Interactive:** Theme toggling and real-time filtering (All/Branch/HO/DC).

---
*Updated on Mar 16, 2026, for Standard V23 Compliance.*
