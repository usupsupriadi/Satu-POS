@echo off
setlocal
title Satu POS
cd /d "%~dp0"

where php >nul 2>&1
if errorlevel 1 (
    echo PHP tidak ditemukan di PATH. Pastikan PHP sudah terpasang.
    pause
    exit /b 1
)

echo Memeriksa CA bundle ^& koneksi SSL ke Pusher...
php "scripts\check-ssl.php"
if errorlevel 1 (
    echo.
    echo [PERINGATAN] Verifikasi SSL PHP bermasalah. Realtime/Pusher bisa gagal
    echo dan checkout bisa error "cURL error 60".
    echo Perbaiki: set curl.cainfo dan openssl.cafile di php.ini ke file cacert.pem.
    echo Unduh bundle dari https://curl.se/ca/cacert.pem - panduan lengkap: DEPLOY.md bagian A.
    echo.
    ping -n 9 127.0.0.1 >nul
)
echo.

netstat -ano | findstr ":8000" | findstr "LISTENING" >nul 2>&1
if not errorlevel 1 (
    echo Server Satu POS sudah berjalan. Membuka browser...
    start "" "http://127.0.0.1:8000/admin"
    exit /b 0
)

rem Hapus penanda Vite dev yang tertinggal (bikin halaman blank kalau Vite mati)
netstat -ano | findstr ":5173" | findstr "LISTENING" >nul 2>&1
if errorlevel 1 if exist "public\hot" del /q "public\hot"

echo Menjalankan server Satu POS...
start "Satu POS - Server" cmd /k "php artisan serve"

if /i "%~1"=="dev" (
    echo Mode dev: menjalankan Vite...
    start "Satu POS - Vite" cmd /k "npm run dev"
)

set /a tries=0
:waitloop
netstat -ano | findstr ":8000" | findstr "LISTENING" >nul 2>&1
if not errorlevel 1 goto ready
set /a tries+=1
if %tries% GEQ 45 goto notready
ping -n 2 127.0.0.1 >nul
goto waitloop

:ready
echo Server siap. Membuka browser...
start "" "http://127.0.0.1:8000/admin"
exit /b 0

:notready
echo Server belum siap setelah 45 detik. Cek jendela "Satu POS - Server" untuk pesan error.
start "" "http://127.0.0.1:8000/admin"
pause
exit /b 1
