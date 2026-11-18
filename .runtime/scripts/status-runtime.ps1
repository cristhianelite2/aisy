param(
    [switch]$Json,
    [switch]$RequireAll
)

$ErrorActionPreference = 'SilentlyContinue'

function Test-TcpPort([int]$port) {
    $conns = Get-NetTCPConnection -LocalPort $port -State Listen -ErrorAction SilentlyContinue
    if ($conns) { return $true }
    try {
        $client = New-Object System.Net.Sockets.TcpClient
        $iar = $client.BeginConnect('127.0.0.1', $port, $null, $null)
        $ok = $iar.AsyncWaitHandle.WaitOne(250, $false)
        if ($ok -and $client.Connected) {
            $client.EndConnect($iar)
            $client.Close()
            return $true
        }
        $client.Close()
    } catch {}
    return $false
}

function Test-HttpOk([string]$url) {
    try {
        $resp = Invoke-WebRequest -Uri $url -TimeoutSec 3 -UseBasicParsing -MaximumRedirection 2
        return ($resp.StatusCode -ge 200 -and $resp.StatusCode -lt 400)
    } catch {
        return $false
    }
}

$pgPort = Test-TcpPort 5439
$odooPort = Test-TcpPort 8069
$odooHttp = if ($odooPort) { Test-HttpOk 'http://127.0.0.1:8069/web/login' } else { $false }
$waPort = Test-TcpPort 8085
$waHttp = if ($waPort) { Test-HttpOk 'http://127.0.0.1:8085/health' } else { $false }

$status = [ordered]@{
    postgres_port = 5439
    postgres_up   = [bool]$pgPort
    odoo_port     = 8069
    odoo_up       = [bool]($odooPort -and $odooHttp)
    wa_port       = 8085
    wa_gateway_up = [bool]($waPort -and $waHttp)
}

if ($Json) {
    $status | ConvertTo-Json -Compress
} else {
    Write-Host ("PostgreSQL (:5439): " + ($(if ($status.postgres_up) { "UP" } else { "DOWN" })))
    Write-Host ("Odoo 17    (:8069): " + ($(if ($status.odoo_up) { "UP" } else { "DOWN" })))
    Write-Host ("WA Gateway (:8085): " + ($(if ($status.wa_gateway_up) { "UP" } else { "DOWN" })))
}

if ($RequireAll -and -not ($status.postgres_up -and $status.odoo_up -and $status.wa_gateway_up)) {
    exit 1
}
exit 0
