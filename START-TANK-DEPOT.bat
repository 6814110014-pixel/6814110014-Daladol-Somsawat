@echo off
cd /d "%~dp0"
echo Tank Depot - local Laravel server
echo Start MySQL in XAMPP first.
echo Open http://127.0.0.1:8000 in your browser.
echo Press Ctrl+C to stop.
php artisan serve --host=127.0.0.1 --port=8000 --no-reload
pause
