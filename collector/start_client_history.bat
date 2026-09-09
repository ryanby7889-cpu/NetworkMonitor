@echo off
setlocal
title Network Monitor - Client History Collector
cd /d "%~dp0"
echo ========================================
echo Client Traffic History Collector
echo Interval : 60 seconds
echo ========================================
echo.
"C:\xampp\php\php.exe" "%~dp0client_history_loop.php"
pause
