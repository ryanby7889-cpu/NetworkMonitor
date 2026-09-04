@echo off
setlocal
set PHP=C:\xampp\php\php.exe
set APP=C:\xampp\htdocs\NetworkMonitor

cd /d "%APP%"
"%PHP%" "%APP%\cron\update_invoice_status.php"

if errorlevel 1 (
    echo [GAGAL] Update status invoice.
    exit /b 1
)

echo [OK] Status invoice diperbarui.
exit /b 0
