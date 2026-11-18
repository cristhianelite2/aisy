@echo off
setlocal
cd /d "%~dp0"
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0.runtime\scripts\start-runtime.ps1"
start "" /B "D:\Apps\xampp\php\php.exe" artisan serve --host=127.0.0.1 --port=8095 --no-reload
