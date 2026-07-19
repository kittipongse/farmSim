@echo off
chcp 65001 >nul
echo FarmSim EDU — install database (schema + seed)
echo.

set MYSQL=C:\xampp\mysql\bin\mysql.exe
set DB=cp393722_farmsim

if not exist "%MYSQL%" (
  echo MySQL not found at %MYSQL%
  echo Edit path in this file for your XAMPP install.
  pause
  exit /b 1
)

echo [1/3] Create database %DB% ...
"%MYSQL%" -u root --default-character-set=utf8mb4 -e "CREATE DATABASE IF NOT EXISTS %DB% CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
"%MYSQL%" -u root --default-character-set=utf8mb4 < "%~dp0schema.sql"
if errorlevel 1 goto :fail

echo [2/3] Import seed data...
"%MYSQL%" -u root --default-character-set=utf8mb4 %DB% < "%~dp0seed.sql"
if errorlevel 1 goto :fail

echo [3/4] Import crop catalog (Phase 5)...
"%MYSQL%" -u root --default-character-set=utf8mb4 %DB% < "%~dp0migration_phase5_crops_v2.sql"
if errorlevel 1 goto :fail

echo [4/4] Done — database %DB% ready (crops v2)
goto :end

:fail
echo Error — check MySQL is running and root credentials.
pause
exit /b 1

:end
pause
