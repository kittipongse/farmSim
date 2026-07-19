@echo off
chcp 65001 >nul
echo FarmSim EDU — Phase 3 migration (Breaking News + plan adjustment)
echo.

set MYSQL=C:\xampp\mysql\bin\mysql.exe
set DB=cp393722_farmsim

if not exist "%MYSQL%" (
  echo MySQL not found at %MYSQL%
  echo Edit path in this file for your XAMPP install.
  pause
  exit /b 1
)

echo Applying migration_phase3_breaking_news.sql to %DB% ...
"%MYSQL%" -u root --default-character-set=utf8mb4 %DB% < "%~dp0migration_phase3_breaking_news.sql"
if errorlevel 1 goto :fail

echo Done.
goto :end

:fail
echo Error — check MySQL is running and database %DB% exists.
pause
exit /b 1

:end
pause
