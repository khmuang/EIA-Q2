@echo off
setlocal
cd /d "%~dp0"

echo ==========================================
echo    EIA Q2 MODERN DASHBOARD - AUTO SYNC
echo ==========================================

echo [1/3] Extracting Data from Excel in EIAQ2/ subfolder...
python update_dashboard.py

if %ERRORLEVEL% NEQ 0 (
    echo [ERROR] Python Sync Failed. Please check the errors above.
    pause
    exit /b
)

echo [2/3] Staging data for GitHub...
git add data.js

echo [3/3] Committing and Pushing to GitHub...
git commit -m "Auto Data Sync - %date% %time%"
git push origin main

echo ==========================================
echo    DASHBOARD UPDATED SUCCESSFULLY!
echo    URL: https://khmuang.github.io/EIA-Tracking-Q2/
echo ==========================================
pause
