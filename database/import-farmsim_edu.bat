@echo off
chcp 65001 >nul
set MYSQL=C:\xampp\mysql\bin\mysql.exe
set DB=cp393722_farmsim

if not exist "%MYSQL%" (
  echo ERROR: MySQL not found at %MYSQL%
  echo Edit MYSQL path in this file if XAMPP is elsewhere.
  pause
  exit /b 1
)

echo Importing farmsim_edu.sql into %DB% ...
echo.

"%MYSQL%" -u root --default-character-set=utf8mb4 -e "CREATE DATABASE IF NOT EXISTS %DB% CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
"%MYSQL%" -u root --default-character-set=utf8mb4 < "%~dp0farmsim_edu.sql"
if errorlevel 1 (
  echo Import FAILED.
  pause
  exit /b 1
)

echo Import OK — database %DB% is ready.
pause
