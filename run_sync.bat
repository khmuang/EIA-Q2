@echo off
setlocal
cd /d "%~dp0"

echo ==========================================
echo    EIA Q2 MODERN DASHBOARD - FULL SYNC
echo ==========================================

echo [1/4] Extracting Data from Excel in EIAQ2/ subfolder...
python update_dashboard.py

if %ERRORLEVEL% NEQ 0 (
    echo [ERROR] Python Data Sync Failed. Please check the errors above.
    pause
    exit /b
)

echo [2/4] Mirroring UI Changes (EIAQ2 -> Root)...
copy /Y "EIAQ2\index.html" "index.html"
if not exist "assets" mkdir "assets"
xcopy /S /Y /I "EIAQ2\assets" "assets"

echo [3/4] Staging EVERYTHING for GitHub (UI + Data + Assets)...
git add .

echo [4/4] Committing and Pushing to GitHub...
git commit -m "Full Sync (UI & Data) - %date% %time%"
git push origin main

echo ==========================================
echo    DASHBOARD UPDATED SUCCESSFULLY!
echo    URL: https://khmuang.github.io/EIA-Tracking-Q2/
echo    (Note: Press Ctrl + F5 in browser to see changes)
echo ==========================================
pause
