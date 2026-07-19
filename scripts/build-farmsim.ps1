# Build for https://znix.online/farmsim/
$ErrorActionPreference = "Stop"
$Root = Split-Path $PSScriptRoot -Parent
$Out = Join-Path $Root "deploy\farmsim-upload"
$Zip = Join-Path $Root "deploy\farmsim-znix.zip"

Write-Host "==> Building frontend (base /farmsim/)..."
Push-Location (Join-Path $Root "frontend")
npm run build
if ($LASTEXITCODE -ne 0) { Pop-Location; exit 1 }
Pop-Location

Write-Host "==> Preparing $Out ..."
if (Test-Path $Out) { Remove-Item $Out -Recurse -Force }
New-Item -ItemType Directory -Path $Out -Force | Out-Null
New-Item -ItemType Directory -Path (Join-Path $Out "uploads") -Force | Out-Null

Copy-Item -Path (Join-Path $Root "frontend\dist\*") -Destination $Out -Recurse -Force

$resourceSrc = Join-Path $Root "frontend\public\resource"
if (Test-Path $resourceSrc) {
    Copy-Item -Path $resourceSrc -Destination (Join-Path $Out "resource") -Recurse -Force
}

$backendItems = @("index.php", "controllers", "models", "config", "helpers")
foreach ($item in $backendItems) {
    $src = Join-Path $Root "backend\$item"
    if (Test-Path $src) {
        Copy-Item -Path $src -Destination (Join-Path $Out $item) -Recurse -Force
    }
}

Copy-Item -Path (Join-Path $Root "deploy\farmsim\.htaccess") -Destination (Join-Path $Out ".htaccess") -Force

if (Test-Path (Join-Path $Root "backend\config\database.local.php")) {
    Copy-Item -Path (Join-Path $Root "backend\config\database.local.php") `
        -Destination (Join-Path $Out "config\database.local.php") -Force
}

Write-Host "==> Creating ZIP: $Zip"
if (Test-Path $Zip) { Remove-Item $Zip -Force }
Compress-Archive -Path (Join-Path $Out "*") -DestinationPath $Zip -Force

Write-Host ""
Write-Host "DONE — upload to public_html/farmsim/ on server"
Write-Host "  Folder: $Out"
Write-Host "  ZIP:    $Zip"
