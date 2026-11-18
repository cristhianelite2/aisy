$ErrorActionPreference = 'SilentlyContinue'

$RuntimeRoot = Split-Path -Parent $PSScriptRoot
$PgCtl = Join-Path $RuntimeRoot 'pgsql\bin\pg_ctl.exe'
$PgData = Join-Path $RuntimeRoot 'pgsql-data'

function Stop-Port([int]$port) {
    Get-NetTCPConnection -LocalPort $port -State Listen -ErrorAction SilentlyContinue |
        Select-Object -ExpandProperty OwningProcess -Unique |
        ForEach-Object { Stop-Process -Id $_ -Force -ErrorAction SilentlyContinue }
}

Stop-Port 8085
Stop-Port 8069

if (Test-Path -LiteralPath $PgCtl) {
    & $PgCtl -D $PgData stop -m fast | Out-Null
}
Stop-Port 5439
Write-Host "Aisy portable runtime stopped." -ForegroundColor Yellow
