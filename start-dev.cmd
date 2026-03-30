@echo off
set "ROOT=%~dp0"
start "CAPSTONE Backend" cmd /k cd /d "%ROOT%backend" ^&^& php artisan migrate ^&^& php artisan serve
start "CAPSTONE Frontend" cmd /k cd /d "%ROOT%" ^&^& npm.cmd run dev
