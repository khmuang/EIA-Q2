@echo off
setlocal
cd /d "%~dp0"

echo ===========================================
echo    EIA Q2 ULTIMATE SYNC TOOL (EIAQ2 DEV)
echo ===========================================

:: [1/4] Run Data Extraction
echo [1/4] Extracting Data from Local Excel...
python update_dashboard.py

if %ERRORLEVEL% NEQ 0 (
    echo [ERROR] Data Extraction Failed.
    pause
    exit /b
)

:: [2/4] Mirror to Root for Deployment
echo [2/4] Mirroring UI to Project Root...
copy /Y "index.html" "..\index.html"
if not exist "..\assets" mkdir "..\assets"
xcopy /S /Y /I "assets" "..\assets"
copy /Y "data.js" "..\data.js"

:: [3/4] Selective Git Update
echo [3/4] Staging Approved Dashboard Files...
cd ..
:: Only add the files that are safe for GitHub
git add index.html
git add data.js
git add assets/
git add EIAQ2/index.html
git add EIAQ2/update_dashboard.py
git add EIAQ2/run_sync.bat
git add .gitignore

:: [4/4] Commit and Push
echo [4/4] Pushing to GitHub...
git commit -m "Secure Sync: UI & Data Update (Selective Push)"
git push origin main

echo ===========================================
echo    DASHBOARD UPDATED SUCCESSFULLY!
echo    URL: https://khmuang.github.io/EIA-Tracking-Q2/
echo ===========================================
cd EIAQ2
pause
