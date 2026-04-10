@echo off
echo ==========================================
echo    EIA Q2 DASHBOARD - AUTO UPDATE
echo ==========================================

echo [1/3] Extracting Data from Excel and Updating MySQL...
python local_sync.py

if %ERRORLEVEL% NEQ 0 (
    echo [ERROR] MySQL Sync Failed. Please check if MySQL is running.
    pause
    exit /b
)

echo [2/3] Preparing data for GitHub...
git add dashboard_data.json
git commit -m "Auto Update: %date% %time%"

echo [3/3] Pushing to GitHub...
git push origin main

echo ==========================================
echo    UPDATE COMPLETE!
echo ==========================================
pause
