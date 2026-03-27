$ErrorActionPreference = "Stop"

$root = Split-Path -Parent $PSScriptRoot
Set-Location $root

if (-not (Test-Path "vendor/autoload.php")) {
    Write-Host "Instalando dependencias de PHP..."
    composer install --no-dev --optimize-autoloader
}

Write-Host "Compilando frontend..."
npm ci
npm run build

$releaseDir = Join-Path $root "release"
$packageDir = Join-Path $releaseDir "squadcontrol"
$zipPath = Join-Path $releaseDir "squadcontrol-release.zip"
$tokenPath = Join-Path $releaseDir "installer-token.txt"

if (Test-Path $packageDir) { Remove-Item -Recurse -Force $packageDir }
if (Test-Path $zipPath) { Remove-Item -Force $zipPath }
if (Test-Path $tokenPath) { Remove-Item -Force $tokenPath }
New-Item -ItemType Directory -Path $packageDir | Out-Null

$exclude = @(
    ".git",
    ".github",
    "node_modules",
    "tests",
    "release",
    "storage/logs/*",
    "storage/framework/cache/*",
    "storage/framework/sessions/*",
    "storage/framework/views/*"
)

Write-Host "Copiando archivos de release..."
robocopy $root $packageDir /E /NFL /NDL /NJH /NJS /NP /XD .git .github node_modules tests release | Out-Null

if (-not (Test-Path (Join-Path $packageDir "storage\framework\cache"))) { New-Item -ItemType Directory -Path (Join-Path $packageDir "storage\framework\cache") -Force | Out-Null }
if (-not (Test-Path (Join-Path $packageDir "storage\framework\sessions"))) { New-Item -ItemType Directory -Path (Join-Path $packageDir "storage\framework\sessions") -Force | Out-Null }
if (-not (Test-Path (Join-Path $packageDir "storage\framework\views"))) { New-Item -ItemType Directory -Path (Join-Path $packageDir "storage\framework\views") -Force | Out-Null }
if (-not (Test-Path (Join-Path $packageDir "storage\logs"))) { New-Item -ItemType Directory -Path (Join-Path $packageDir "storage\logs") -Force | Out-Null }

$bytes = New-Object byte[] 24
[System.Security.Cryptography.RandomNumberGenerator]::Create().GetBytes($bytes)
$installerToken = [Convert]::ToBase64String($bytes).Replace("+", "").Replace("/", "").Replace("=", "")
$sha256 = [System.Security.Cryptography.SHA256]::Create()
$hashBytes = $sha256.ComputeHash([System.Text.Encoding]::UTF8.GetBytes($installerToken))
$installerTokenHash = -join ($hashBytes | ForEach-Object { $_.ToString("x2") })

$tokenHashFile = Join-Path $packageDir "storage\framework\install_token.hash"
Set-Content -Path $tokenHashFile -Value $installerTokenHash -Encoding Ascii -NoNewline

@(
    "Instalador SquadControl",
    "",
    "Token de instalacion (un solo uso):",
    $installerToken,
    "",
    "Usa esta URL: https://tu-dominio.com/install.php?token=$installerToken"
) | Set-Content -Path $tokenPath -Encoding Ascii

Write-Host "Generando ZIP..."
Compress-Archive -Path (Join-Path $packageDir "*") -DestinationPath $zipPath -CompressionLevel Optimal

Write-Host "Release creado en: $zipPath"
Write-Host "Token de instalacion guardado en: $tokenPath"

