# EIA Report Weekly - Project Context (gemini3.md)
## Last Update: 2026-03-20 (V25 Finalized - High End UI)

## Current Architecture & Files
- **Database:** MySQL `eia_compliance`, Table `inventory_reports` (35 Columns).
- **Core Files:**
    1. `index.php`: Main Dashboard (Clean UI with Pastel Cards & BU Ranking Link).
    2. `inventory_list.php`: Searchable list with Pagination, Compliance Scores, and Full Export.
    3. `inventory_detail.php`: Deep-dive view with Ultra Glow Shadows and Hero Status indicators.
    4. `top_bu_compliance.php`: Full BU ranking based on 6-topic average score.
    5. `setup_and_import_full.py`: Data ingestion script (Last run: 17,144 records).

## Key Standards & Logic
- **UI Standard:** Premium V25 (Fixed Gradient Background, Ultra Glow Shadows on hover, Sarabun Font).
- **Compliance Score:** Calculated as an average of 6 topics: Domain, OS, Patch, AV, Firewall, Admin Rights.
- **Color Themes:** 
    - Emerald (>=81%): Green Glow.
    - Blue (70-80%): Blue Glow.
    - Amber (50-69%): Orange Glow.
    - Rose (<50%): Red Glow.
- **Inactive Logic:** 30+ Days status shown as "Inactive" (Red + Pulse Icon) or "Active" (Green).

## Recent Enhancements
- Implemented **3-layer Ultra Glow Shadows** on `inventory_detail.php` cards.
- Highlighted **BU Badge** in Hero Section with solid Blue-600 background.
- Integrated **Inactive status** into the Hero Section.
- Standardized all titles and footers ("Prepared by Endpoint Management Team").
- Updated all links between Index, List, and Detail pages.

## Deployment
- Files are mirrored at `C:\xampp\htdocs\project-folder` for LIVE testing.
- Backup folder `backup_20260320` contains the previous stable versions.
