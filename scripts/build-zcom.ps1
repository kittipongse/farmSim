# Build FarmSim EDU package for Z.com (public_html upload)
$ErrorActionPreference = "Stop"
$Root = Split-Path $PSScriptRoot -Parent
$Out = Join-Path $Root "deploy\zcom-upload"
$Zip = Join-Path $Root "deploy\farmsim-edu-zcom.zip"

Write-Host "==> Building frontend..."
Push-Location (Join-Path $Root "frontend")
npm run build
if ($LASTEXITCODE -ne 0) { Pop-Location; exit 1 }
Pop-Location

Write-Host "==> Preparing $Out ..."
if (Test-Path $Out) { Remove-Item $Out -Recurse -Force }
New-Item -ItemType Directory -Path $Out -Force | Out-Null
New-Item -ItemType Directory -Path (Join-Path $Out "uploads") -Force | Out-Null

# Frontend (dist)
Copy-Item -Path (Join-Path $Root "frontend\dist\*") -Destination $Out -Recurse -Force

# Static resources (images not always in dist)
$resourceSrc = Join-Path $Root "frontend\public\resource"
$resourceDst = Join-Path $Out "resource"
if (Test-Path $resourceSrc) {
    Copy-Item -Path $resourceSrc -Destination $resourceDst -Recurse -Force
}

# Backend PHP
$backendItems = @(
    "index.php",
    "controllers",
    "models",
    "config",
    "helpers"
)
foreach ($item in $backendItems) {
    $src = Join-Path $Root "backend\$item"
    if (Test-Path $src) {
        Copy-Item -Path $src -Destination (Join-Path $Out $item) -Recurse -Force
    }
}

# Production .htaccess
Copy-Item -Path (Join-Path $Root "deploy\public_html\.htaccess") -Destination (Join-Path $Out ".htaccess") -Force

# DB config template on server
Copy-Item -Path (Join-Path $Root "backend\config\database.local.example.php") `
    -Destination (Join-Path $Out "config\database.local.example.php") -Force

# uploads placeholder
$gitkeep = Join-Path $Out "uploads\.gitkeep"
if (-not (Test-Path $gitkeep)) { New-Item -ItemType File -Path $gitkeep -Force | Out-Null }

# Remove dev-only files from package
@(
    (Join-Path $Out "config\database.local.php"),
    (Join-Path $Root "backend\router.php")
) | ForEach-Object { if (Test-Path $_) { Remove-Item $_ -Force -ErrorAction SilentlyContinue } }

Write-Host "==> Creating ZIP: $Zip"
if (Test-Path $Zip) { Remove-Item $Zip -Force }
Compress-Archive -Path (Join-Path $Out "*") -DestinationPath $Zip -Force

Write-Host ""
Write-Host "DONE."
Write-Host "  Folder: $Out"
Write-Host "  ZIP:    $Zip"
Write-Host "  Upload contents of zcom-upload/ to public_html on Z.com"
