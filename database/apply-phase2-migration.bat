@echo off
chcp 65001 >nul
set MYSQL=C:\xampp\mysql\bin\mysql.exe
set DB=cp393722_farmsim

if not exist "%MYSQL%" (
  echo ERROR: MySQL not found at %MYSQL%
  pause
  exit /b 1
)

echo Applying Phase 2 migration to %DB% ...
"%MYSQL%" -u root --default-character-set=utf8mb4 %DB% < "%~dp0migration_phase2.sql"
if errorlevel 1 (
  echo Migration FAILED. For new DB use import-farmsim_edu.bat instead.
  pause
  exit /b 1
)

echo Migration OK.
pause
