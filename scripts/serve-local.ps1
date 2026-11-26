param([int]$Port = 8765)
$ErrorActionPreference = 'Stop'
Set-Location (Split-Path $PSScriptRoot -Parent)
if (-not (Test-Path '.local/site.sqlite')) { throw 'Run scripts/setup-local.ps1 first.' }
$env:NUSRY_ENV = 'local'
$env:NUSRY_LOCAL_SQLITE = '1'
& .tools/php/php.exe -S "127.0.0.1:$Port" -t "$PWD/web" "$PWD/web/.ht.router.php"
exit $LASTEXITCODE
