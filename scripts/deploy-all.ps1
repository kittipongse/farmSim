# Full deploy: MySQL (local) + build backend/frontend + FTP backend + Vercel frontend + remote MySQL migration
# อ้างอิง: deploy.md
$ErrorActionPreference = "Stop"
$Root = Split-Path $PSScriptRoot -Parent
$DeployDoc = Join-Path $Root "deploy.md"

Write-Host "========== FarmSim EDU — Full Deploy ==========" -ForegroundColor Cyan
if (Test-Path $DeployDoc) {
    Write-Host "Reference: deploy.md" -ForegroundColor DarkGray
} else {
    Write-Warning "deploy.md not found — see readme.md Deployment section"
}

$Mysql = "C:\xampp\mysql\bin\mysql.exe"
$Db = "cp393722_farmsim"

# 1) Local MySQL migration
Write-Host "`n[1/5] MySQL migration (local XAMPP)..." -ForegroundColor Yellow
if (Test-Path $Mysql) {
    try {
        & $Mysql -u root --default-character-set=utf8mb4 -e "CREATE DATABASE IF NOT EXISTS $Db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>$null
        if ($LASTEXITCODE -ne 0) { throw "MySQL not running" }
        Get-Content (Join-Path $Root "database\migration_phase3_breaking_news.sql") | & $Mysql -u root --default-character-set=utf8mb4 $Db
        if ($LASTEXITCODE -ne 0) { throw "Phase 3 migration failed" }
        Get-Content (Join-Path $Root "database\migration_phase5_crops_v2.sql") | & $Mysql -u root --default-character-set=utf8mb4 $Db 2>&1
        if ($LASTEXITCODE -ne 0) {
            Write-Warning "Phase 5 SQL via mysql CLI failed (may need manual run) — remote migration will retry"
        }
        Write-Host "  Local DB migration OK" -ForegroundColor Green
    } catch {
        Write-Warning "Local migration skipped: $_"
        Write-Host "  Start XAMPP MySQL or run database\apply-phase3-migration.bat later"
    }
} else {
    Write-Warning "XAMPP MySQL not found — skip local migration"
}

# รีเซ็ต exit code เผื่อ local MySQL ล้มเหลว (ไม่ให้ทำ build check พังตาม)
$global:LASTEXITCODE = 0

# 2) Build backend package
Write-Host "`n[2/5] Build backend (deploy/backend-cloud/api)..." -ForegroundColor Yellow
& (Join-Path $Root "scripts\build-backend-cloud.ps1")
if ($LASTEXITCODE -ne 0) { exit 1 }

# 3) Build frontend + znix bundle
Write-Host "`n[3/5] Build frontend (deploy/farmsim-upload)..." -ForegroundColor Yellow
& (Join-Path $Root "scripts\build-farmsim.ps1")
if ($LASTEXITCODE -ne 0) { exit 1 }

# 4) FTP upload backend
Write-Host "`n[4/5] FTP upload backend to znix.online..." -ForegroundColor Yellow
try {
    & (Join-Path $Root "scripts\upload-backend-ftp.ps1")
    Write-Host "  Remote migration..." -ForegroundColor Yellow
    Start-Sleep -Seconds 3
    $migrateUrl = "https://znix.online/farmsim/api/run-migrations.php?key=farmsim-phase3"
    try {
        $resp = Invoke-RestMethod -Uri $migrateUrl -TimeoutSec 60
        Write-Host "  Remote MySQL: $($resp.message)" -ForegroundColor Green
        if ($resp.applied -and $resp.applied.Count -gt 0) {
            Write-Host "  Applied: $($resp.applied -join ', ')"
        }
    } catch {
        Write-Warning "Remote migration call failed: $_"
        Write-Host "  Run manually: $migrateUrl"
    }
} catch {
    Write-Warning "FTP upload skipped: $_"
    Write-Host "  Set `$env:FARMSIM_FTP_PASSWORD and re-run scripts\upload-backend-ftp.ps1"
    Write-Host "  Then open: https://znix.online/farmsim/api/run-migrations.php?key=farmsim-phase3"
}

# 5) Vercel production
Write-Host "`n[5/5] Vercel deploy (production)..." -ForegroundColor Yellow
Push-Location (Join-Path $Root "frontend")
npx --yes vercel deploy --prod --yes --scope "zybercools-projects"
$vercelExit = $LASTEXITCODE
Pop-Location
if ($vercelExit -ne 0) {
    Write-Warning "Vercel deploy failed — run manually from frontend/: npx vercel deploy --prod --yes"
} else {
    Write-Host "  Vercel deploy OK" -ForegroundColor Green
}

Write-Host "`n========== Deploy complete ==========" -ForegroundColor Cyan
Write-Host "Backend API:  https://znix.online/farmsim/api/health"
Write-Host "Frontend:     https://farm-sim-mu.vercel.app"
Write-Host "Local bundle: deploy\farmsim-upload\ (znix /farmsim/)"
Write-Host "Backend ZIP:  deploy\backend-cloud-api.zip"
