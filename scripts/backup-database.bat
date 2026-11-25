@echo off
rem Backup database Satu POS untuk instalasi Windows lokal.
rem
rem Pemakaian manual:
rem   scripts\backup-database.bat
rem
rem Otomatis tiap hari lewat Task Scheduler (jalankan sekali di cmd sebagai admin):
rem   schtasks /Create /SC DAILY /ST 02:30 /TN "Satu POS - Backup Database" ^
rem     /TR "\"%~dp0backup-database.bat\""
rem
rem Log: storage\logs\backup-database.log

setlocal
cd /d "%~dp0.."

set "LOG_FILE=storage\logs\backup-database.log"
if not exist "storage\logs" mkdir "storage\logs"

echo === %DATE% %TIME% mulai backup database ===>> "%LOG_FILE%"

php artisan backup:database %* >> "%LOG_FILE%" 2>&1
set "STATUS=%ERRORLEVEL%"

if "%STATUS%"=="0" (
    echo === %DATE% %TIME% backup selesai ===>> "%LOG_FILE%"
) else (
    echo === %DATE% %TIME% backup GAGAL ^(exit %STATUS%^) ===>> "%LOG_FILE%"
)

if not "%STATUS%"=="0" (
    echo.
    echo Backup gagal dengan kode %STATUS%. Lihat %LOG_FILE%
)

exit /b %STATUS%
