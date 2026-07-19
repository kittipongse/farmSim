@echo off
chcp 65001 >nul
set MYSQL=C:\xampp\mysql\bin\mysql.exe
set DB=cp393722_farmsim

if not exist "%MYSQL%" (
  echo MySQL not found at %MYSQL%
  exit /b 1
)

echo Applying migration_crop_region.sql to %DB% ...
"%MYSQL%" -u root --default-character-set=utf8mb4 %DB% < "%~dp0migration_crop_region.sql"
if errorlevel 1 (
  echo Migration failed. Columns may already exist.
  exit /b 1
)
echo Done.
