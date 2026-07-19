# อัปโหลดทั้งโฟลเดอร์ api ไปที่ public_html/farmsim/api/
# ต้องมีครบ: index.php, ping.php, check.php, config/, controllers/, models/, helpers/, uploads/

$ErrorActionPreference = "Stop"
$Root = Split-Path $PSScriptRoot -Parent
$Out = Join-Path $Root "deploy\backend-cloud\api"
$Zip = Join-Path $Root "deploy\backend-cloud-api.zip"

function Write-Utf8NoBom([string]$Path, [string]$Content) {
    $utf8 = New-Object System.Text.UTF8Encoding $false
    [System.IO.File]::WriteAllText($Path, $Content, $utf8)
}

Write-Host "==> Building API package: $Out"
if (Test-Path (Join-Path $Root "deploy\backend-cloud")) {
    Remove-Item (Join-Path $Root "deploy\backend-cloud") -Recurse -Force
}
New-Item -ItemType Directory -Path $Out -Force | Out-Null
New-Item -ItemType Directory -Path (Join-Path $Out "uploads") -Force | Out-Null
$uploadsHtaccess = Join-Path $Root "backend\uploads\.htaccess"
if (Test-Path $uploadsHtaccess) {
    Copy-Item $uploadsHtaccess (Join-Path $Out "uploads\.htaccess") -Force
}

# Main router
Copy-Item (Join-Path $Root "backend\index.php") (Join-Path $Out "index.php") -Force
Copy-Item (Join-Path $Root "backend\api\ping.php") (Join-Path $Out "ping.php") -Force
Copy-Item (Join-Path $Root "backend\api\check.php") (Join-Path $Out "check.php") -Force
Copy-Item (Join-Path $Root "backend\api\health.php") (Join-Path $Out "health.php") -Force
Copy-Item (Join-Path $Root "backend\api\room-test.php") (Join-Path $Out "room-test.php") -Force
Copy-Item (Join-Path $Root "backend\api\dashboard-test.php") (Join-Path $Out "dashboard-test.php") -Force
Copy-Item (Join-Path $Root "backend\api\run-migrations.php") (Join-Path $Out "run-migrations.php") -Force
$migDir = Join-Path $Out "migrations"
New-Item -ItemType Directory -Path $migDir -Force | Out-Null
Copy-Item (Join-Path $Root "database\migration_phase5_crops_v2.sql") (Join-Path $migDir "migration_phase5_crops_v2.sql") -Force
Copy-Item (Join-Path $Root "database\migration_phase7_quiz_questions.sql") (Join-Path $migDir "migration_phase7_quiz_questions.sql") -Force

foreach ($item in @("controllers", "models", "config", "helpers")) {
    Copy-Item (Join-Path $Root "backend\$item") (Join-Path $Out $item) -Recurse -Force
}

Write-Utf8NoBom (Join-Path $Out ".htaccess") @"
RewriteEngine On
RewriteBase /farmsim/api/

# รูปโปรไฟล์ส่งผ่าน PHP (host บางที่บล็อก static ใน uploads/)
RewriteRule ^uploads/ index.php [L,QSA]

RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.php [L,QSA]
"@

if (Test-Path (Join-Path $Root "backend\config\database.local.php")) {
    Copy-Item (Join-Path $Root "backend\config\database.local.php") `
        (Join-Path $Out "config\database.local.php") -Force
}

Write-Utf8NoBom (Join-Path $Root "deploy\backend-cloud\UPLOAD.txt") @"
FarmSim EDU - Backend upload (znix.online / PHP 5.6)

REQUIREMENTS: PHP 5.6+, MySQL 5.6+, extensions: pdo_mysql, mbstring (fileinfo แนะนำ)

1. Upload ENTIRE folder: deploy\backend-cloud\api\
   TO server path: public_html/farmsim/api/

2. Must contain ALL of these:
   - index.php
   - ping.php
   - check.php
   - .htaccess
   - config/ (with database.local.php)
   - controllers/
   - models/
   - helpers/
   - uploads/ (chmod 755)

3. Test URLs:
   https://znix.online/farmsim/api/ping.php
   https://znix.online/farmsim/api/check.php
   https://znix.online/farmsim/api/health.php
   https://znix.online/farmsim/api/health

6. After deploy run MySQL migration once:
   https://znix.online/farmsim/api/run-migrations.php?key=farmsim-phase3

4. Frontend local .env.development:
   VITE_API_BASE_URL=https://znix.online/farmsim/api

5. Verify PHP 5.6 (local): node scripts/verify-php56.js
"@

if (Test-Path $Zip) { Remove-Item $Zip -Force }
Compress-Archive -Path (Join-Path $Out "*") -DestinationPath $Zip -Force

Write-Host "DONE"
Write-Host "  Upload folder: deploy\backend-cloud\api\  ->  public_html/farmsim/api/"
Write-Host "  ZIP: deploy\backend-cloud-api.zip"
