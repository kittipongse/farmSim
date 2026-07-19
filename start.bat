@echo off
chcp 65001 >nul
echo FarmSim EDU - Local Dev
echo.
echo มือถือต้องอยู่ Wi-Fi เดียวกับคอมพิวเตอร์
echo เปิด Dashboard ด้วย Network URL ที่ Vite แสดง (ไม่ใช่ localhost)
echo.
echo [Backend]  http://localhost:8080
start "FarmSim Backend" cmd /k "cd /d %~dp0backend && C:\xampp\php\php.exe -S localhost:8080 -t . router.php"
timeout /t 2 /nobreak >nul
echo [Frontend] http://localhost:5173  (+ Network URL ใน terminal)
start "FarmSim Frontend" cmd /k "cd /d %~dp0frontend && npm run dev"
echo.
echo ถ้า QR ยังใช้ไม่ได้ สร้าง frontend\.env.local แล้วใส่:
echo VITE_PUBLIC_BASE_URL=http://YOUR_LAN_IP:5173
echo.
pause
