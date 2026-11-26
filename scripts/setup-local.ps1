# Portable Windows preview. All downloads and state stay inside this project.
$ErrorActionPreference = 'Stop'
Set-Location (Split-Path $PSScriptRoot -Parent)
function Invoke-Checked([string]$Executable, [string[]]$Arguments) {
  & $Executable @Arguments
  if ($LASTEXITCODE -ne 0) { throw "$Executable failed ($LASTEXITCODE)." }
}
if (-not (Get-Command node -ErrorAction SilentlyContinue)) { throw 'Install Node.js 24 before running setup.' }
if ((node --version) -notmatch '^v24\.') { throw 'This project requires Node.js 24.' }
New-Item -ItemType Directory -Force '.tools' | Out-Null
if (-not (Test-Path '.tools/php/php.exe')) {
  $archive = Join-Path $PWD '.tools/php.zip'
  Invoke-WebRequest 'https://windows.php.net/downloads/releases/php-8.3.37-nts-Win32-vs16-x64.zip' -OutFile $archive
  if ((Get-FileHash $archive -Algorithm SHA256).Hash -ne '38808a798485e1c0938dfcce7e942131415fc6c7834f83a5a85a4c4edb5539d6') { throw 'PHP checksum mismatch.' }
  Expand-Archive -LiteralPath $archive -DestinationPath '.tools/php' -Force
}
if (-not (Test-Path '.tools/php/php.ini')) {
  @'
extension_dir="ext"
extension=curl
extension=fileinfo
extension=gd
extension=intl
extension=mbstring
extension=openssl
extension=pdo_mysql
extension=pdo_sqlite
extension=sqlite3
extension=zip
memory_limit=512M
max_execution_time=120
date.timezone=UTC
'@ | Set-Content '.tools/php/php.ini' -Encoding ascii
}
if (-not (Test-Path '.tools/composer.phar')) {
  Invoke-WebRequest 'https://getcomposer.org/download/2.11.3/composer.phar' -OutFile '.tools/composer.phar'
}
if ((Get-FileHash '.tools/composer.phar' -Algorithm SHA256).Hash -ne '04f8b003da4e3a2459056591fbfebd02b4dc3e5adeb158616b90cf55a65113ec') { throw 'Composer checksum mismatch.' }
$env:PATH = "$PWD/.tools/php;$env:PATH"
$env:NUSRY_ENV = 'local'
$env:NUSRY_LOCAL_SQLITE = '1'
$env:COMPOSER_ROOT_VERSION = 'dev-build/phase-2-foundation'
Invoke-Checked '.tools/php/php.exe' @('.tools/composer.phar', 'install', '--no-interaction', '--prefer-dist', '--no-progress')
Invoke-Checked 'npm.cmd' @('ci')
Invoke-Checked 'npm.cmd' @('run', 'build')
Invoke-Checked '.tools/php/php.exe' @('scripts/install.php')
Write-Host 'Ready. Run: powershell -File scripts/serve-local.ps1'
