@echo off
chcp 65001 >nul
set MYSQL="C:\xampp\mysql\bin\mysql.exe"
if not exist %MYSQL% (
  echo MySQL not found at %MYSQL%
  echo Edit path in apply-phase5-migration.bat
  pause
  exit /b 1
)
echo Applying migration_phase5_crops_v2.sql ...
%MYSQL% -u root --default-character-set=utf8mb4 < "%~dp0migration_phase5_crops_v2.sql"
if errorlevel 1 (
  echo Migration failed.
  pause
  exit /b 1
)
echo Done. Thailand: 72 crops, USA: 11 crops, 332 region rates.
pause
