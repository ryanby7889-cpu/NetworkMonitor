@echo off
setlocal

title Network Monitor Collector

cd /d "%~dp0"

echo ========================================
echo Network Monitor Collector
echo ========================================
echo.

"C:\xampp\php\php.exe" "%~dp0collector_loop.php"

pause
