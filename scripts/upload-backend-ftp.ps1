# Upload deploy\backend-cloud\api\ to znix.online via FTP (curl)
# Password: $env:FARMSIM_FTP_PASSWORD or scripts\farmsim.secrets.local.ps1 or database.local.php fallback

$ErrorActionPreference = "Stop"
$Root = Split-Path $PSScriptRoot -Parent
$LocalApi = Join-Path $Root "deploy\backend-cloud\api"
$FtpHost = "ftp.50148239-46-20221029100049.webstarterz.com"
$FtpUser = "farmsim@znix.online"
$FtpBase = "ftp://$FtpHost/"

if (-not (Test-Path $LocalApi)) {
    Write-Error "Run scripts\build-backend-cloud.ps1 first"
}

$pass = $env:FARMSIM_FTP_PASSWORD
if (-not $pass) {
    $secretsFile = Join-Path $PSScriptRoot "farmsim.secrets.local.ps1"
    if (Test-Path $secretsFile) {
        . $secretsFile
        $pass = $env:FARMSIM_FTP_PASSWORD
    }
}
if (-not $pass) {
    $dbLocal = Join-Path $Root "backend\config\database.local.php"
    if (Test-Path $dbLocal) {
        $dbText = Get-Content $dbLocal -Raw
        if ($dbText -match "'password'\s*=>\s*'([^']+)'") {
            $pass = $Matches[1]
            Write-Host "  Using password from backend/config/database.local.php (FTP fallback)"
        }
    }
}
if (-not $pass) {
    Write-Error "Set FARMSIM_FTP_PASSWORD environment variable"
}

$auth = "${FtpUser}:$pass"

function Upload-CurlFile([string]$localPath, [string]$remoteName) {
    $uri = "$FtpBase$remoteName"
    & curl.exe -sS --ftp-create-dirs -T $localPath -u $auth $uri
    if ($LASTEXITCODE -ne 0) {
        throw "FTP upload failed: $remoteName"
    }
    Write-Host "  UP $remoteName"
}

function Upload-Tree([string]$localDir, [string]$remotePrefix) {
    Get-ChildItem -Path $localDir -Force | ForEach-Object {
        $remoteName = if ($remotePrefix) { "$remotePrefix/$($_.Name)" } else { $_.Name }
        if ($_.PSIsContainer) {
            Upload-Tree $_.FullName $remoteName
        } else {
            Upload-CurlFile $_.FullName $remoteName
        }
    }
}

Write-Host "==> FTP upload (curl) to $FtpBase"
Upload-Tree $LocalApi ""
Write-Host "DONE FTP backend"
