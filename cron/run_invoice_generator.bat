@echo off
setlocal

REM ==========================================================
REM NetMonitor - Automatic Monthly Invoice Generator
REM Windows / XAMPP
REM ==========================================================

set PHP=C:\xampp\php\php.exe
set APP=C:\xampp\htdocs\NetworkMonitor

cd /d "%APP%"

"%PHP%" "%APP%\cron\generate_invoices.php"

if errorlevel 1 (
    echo.
    echo [GAGAL] Generator invoice selesai dengan error.
    exit /b 1
)

echo.
echo [OK] Generator invoice selesai.
exit /b 0
