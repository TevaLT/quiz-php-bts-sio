@echo off
rem Lance le site du quiz sur http://localhost:8000 (Ctrl+C pour arreter).
setlocal
cd /d "%~dp0"
set PORT=8000
echo Quiz BTS SIO SLAM : http://localhost:%PORT%
start "" "http://localhost:%PORT%"
php -S localhost:%PORT% -t public public/index.php
