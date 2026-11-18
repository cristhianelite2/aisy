$ErrorActionPreference = 'Stop'

$RuntimeRoot = Split-Path -Parent $PSScriptRoot
$PgCtl = Join-Path $RuntimeRoot 'pgsql\bin\pg_ctl.exe'
$PgData = Join-Path $RuntimeRoot 'pgsql-data'
$PgLog = Join-Path $RuntimeRoot 'pgsql.log'
$VenvPy = Join-Path $RuntimeRoot 'odoo-venv\Scripts\python.exe'
$OdooBin = Join-Path $RuntimeRoot 'odoo\odoo-bin'
$OdooConf = Join-Path $RuntimeRoot 'odoo.conf'
$WaDir = Join-Path $RuntimeRoot 'wa-gateway'
$WaLog = Join-Path $WaDir 'wa-gateway.log'

function Test-Port([int]$port) {
    return [bool](Get-NetTCPConnection -LocalPort $port -State Listen -ErrorAction SilentlyContinue)
}

# 1. Start PostgreSQL (:5439)
if (-not (Test-Port 5439)) {
    Write-Host "Starting PostgreSQL (:5439)..." -ForegroundColor Cyan
    & $PgCtl -D $PgData -l $PgLog -o "-p 5439" start | Out-Null
    for ($i = 0; $i -lt 15; $i++) {
        if (Test-Port 5439) { break }
        Start-Sleep -Seconds 1
    }
}

# 2. Start Odoo 17 (:8069)
if (-not (Test-Port 8069)) {
    Write-Host "Starting Odoo 17 (:8069)..." -ForegroundColor Cyan
    Start-Process -FilePath $VenvPy -ArgumentList @($OdooBin, '-c', $OdooConf) -WorkingDirectory $RuntimeRoot -WindowStyle Hidden | Out-Null
    for ($i = 0; $i -lt 30; $i++) {
        if (Test-Port 8069) { break }
        Start-Sleep -Seconds 1
    }
}

# 3. Start WhatsApp QR Gateway (:8085)
if (-not (Test-Port 8085)) {
    Write-Host "Starting WhatsApp QR Gateway (:8085)..." -ForegroundColor Cyan
    $vbs = Join-Path $env:TEMP 'aisy-wa-gateway.vbs'
    Set-Content -LiteralPath $vbs -Encoding ASCII -Value @"
Set shell = CreateObject("WScript.Shell")
shell.CurrentDirectory = "$WaDir"
shell.Run "cmd.exe /d /c ""node server.js >> """"$WaLog"""" 2>&1""", 0, False
"@
    Start-Process -FilePath 'wscript.exe' -ArgumentList "`"$vbs`"" -WorkingDirectory $WaDir | Out-Null
    for ($i = 0; $i -lt 15; $i++) {
        if (Test-Port 8085) { break }
        Start-Sleep -Seconds 1
    }
}

& (Join-Path $PSScriptRoot 'status-runtime.ps1')
