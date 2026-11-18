$ErrorActionPreference = 'Stop'

$RuntimeRoot = Split-Path -Parent $PSScriptRoot
$DownloadsDir = Join-Path $RuntimeRoot 'downloads'
$PgDir = Join-Path $RuntimeRoot 'pgsql'
$PgData = Join-Path $RuntimeRoot 'pgsql-data'
$PgLog = Join-Path $RuntimeRoot 'pgsql.log'
$OdooDir = Join-Path $RuntimeRoot 'odoo'
$OdooVenv = Join-Path $RuntimeRoot 'odoo-venv'
$OdooConf = Join-Path $RuntimeRoot 'odoo.conf'
$OdooLog = Join-Path $RuntimeRoot 'odoo.log'
$WaDir = Join-Path $RuntimeRoot 'wa-gateway'

New-Item -ItemType Directory -Force -Path $DownloadsDir | Out-Null

Write-Host "[1/5] Checking Portable PostgreSQL 16..." -ForegroundColor Cyan
$pgCtl = Join-Path $PgDir 'bin\pg_ctl.exe'
if (-not (Test-Path -LiteralPath $pgCtl)) {
    $pgZip = Join-Path $DownloadsDir 'postgresql-16-win64.zip'
    if (-not (Test-Path -LiteralPath $pgZip)) {
        Write-Host "Downloading PostgreSQL 16.4 Windows x64 portable binaries..."
        $pgUrl = 'https://get.enterprisedb.com/postgresql/postgresql-16.4-1-windows-x64-binaries.zip'
        Invoke-WebRequest -Uri $pgUrl -OutFile $pgZip -UseBasicParsing
    }
    Write-Host "Extracting PostgreSQL..."
    $extractTmp = Join-Path $DownloadsDir 'pg_extract'
    if (Test-Path -LiteralPath $extractTmp) { Remove-Item -Recurse -Force $extractTmp }
    Expand-Archive -Path $pgZip -DestinationPath $extractTmp -Force
    $innerPg = Join-Path $extractTmp 'pgsql'
    if (Test-Path -LiteralPath $PgDir) { Remove-Item -Recurse -Force $PgDir }
    Move-Item -Path $innerPg -Destination $PgDir
    Remove-Item -Recurse -Force $extractTmp -ErrorAction SilentlyContinue
}

if (-not (Test-Path -LiteralPath (Join-Path $PgData 'PG_VERSION'))) {
    Write-Host "Initializing PostgreSQL data cluster in $PgData on port 5439..."
    $initdb = Join-Path $PgDir 'bin\initdb.exe'
    & $initdb -D $PgData -U odoo -E UTF8 --locale=C -A trust
    if ($LASTEXITCODE -ne 0) { throw "initdb failed with exit code $LASTEXITCODE" }

    $pgConf = Join-Path $PgData 'postgresql.conf'
    Add-Content -Path $pgConf -Value "`nport = 5439`nlisten_addresses = '127.0.0.1'`nmax_connections = 100`n"
}

Write-Host "[2/5] Starting Portable PostgreSQL (:5439) and ensuring database aisy_erp..." -ForegroundColor Cyan
$pgStatus = & $pgCtl -D $PgData status 2>&1
if ($LASTEXITCODE -ne 0) {
    & $pgCtl -D $PgData -l $PgLog -o "-p 5439" start
    Start-Sleep -Seconds 2
}
$createdb = Join-Path $PgDir 'bin\createdb.exe'
$psql = Join-Path $PgDir 'bin\psql.exe'
$dbExists = & $psql -h 127.0.0.1 -p 5439 -U odoo -d postgres -tAc "SELECT 1 FROM pg_database WHERE datname='aisy_erp'" 2>$null
if ($dbExists -ne '1') {
    & $createdb -h 127.0.0.1 -p 5439 -U odoo -E UTF8 aisy_erp
}

Write-Host "[3/5] Checking Odoo 17.0 Community source & Python .venv..." -ForegroundColor Cyan
if (-not (Test-Path -LiteralPath (Join-Path $OdooDir 'odoo-bin'))) {
    Write-Host "Cloning Odoo 17.0 Community (shallow)..."
    git clone --depth 1 --branch 17.0 https://github.com/odoo/odoo.git $OdooDir
    if ($LASTEXITCODE -ne 0) { throw "Failed to clone Odoo 17.0" }
}

$venvPy = Join-Path $OdooVenv 'Scripts\python.exe'
$venvPip = Join-Path $OdooVenv 'Scripts\pip.exe'
if (-not (Test-Path -LiteralPath $venvPy)) {
    Write-Host "Creating Python 3.11 virtual environment for Odoo..."
    python -m venv $OdooVenv
    if ($LASTEXITCODE -ne 0) { throw "Failed to create Python venv" }
    & $venvPy -m pip install --upgrade pip setuptools wheel
}

$odooCheck = & $venvPy -c "import werkzeug, psycopg2, lxml, babel, PIL, reportlab, dateutil, jinja2, markupsafe, passlib, polib, requests, decorator, psutil, yaml, libsass" 2>&1
if ($LASTEXITCODE -ne 0) {
    Write-Host "Installing Odoo 17 Python dependencies..."
    & $venvPip install `
        "Babel>=2.9.1" "chardet>=4.0.0" "cryptography>=41.0.0" "decorator>=4.4.2" "docutils>=0.17" `
        "freezegun>=1.1.0" "geoip2>=2.9.0" "idna>=2.10" "Jinja2>=3.1.2" "libsass>=0.22.0" `
        "lxml>=4.9.3" "MarkupSafe>=2.1.2" "num2words>=0.5.12" "ofxparse>=0.21" "passlib>=1.7.4" `
        "Pillow>=10.0.0" "polib>=1.1.1" "psutil>=5.9.0" "psycopg2-binary>=2.9.9" "pyOpenSSL>=23.0.0" `
        "PyPDF2>=2.12.1" "pyserial>=3.5" "python-dateutil>=2.8.2" "python-stdnum>=1.18" `
        "pytz>=2023.3" "PyYAML>=6.0.1" "qrcode>=7.4.2" "reportlab>=4.0.4" "requests>=2.31.0" `
        "urllib3>=1.26.18,<2.1" "vobject>=0.9.6.1" "Werkzeug==2.3.8" "xlrd>=2.0.1" `
        "XlsxWriter>=3.1.2" "xlwt>=1.3.0" "zeep>=4.2.1" "rjsmin>=1.2.1" "asn1crypto>=1.5.1"
    if ($LASTEXITCODE -ne 0) { throw "Failed to install Python dependencies" }
}

Write-Host "[4/5] Writing odoo.conf and initializing Odoo database (aisy_erp)..." -ForegroundColor Cyan
$addonsPath = (Join-Path $OdooDir 'addons')
$confContent = @"
[options]
admin_passwd = aisy_master_2026
db_host = 127.0.0.1
db_port = 5439
db_user = odoo
db_password = odoo
db_name = aisy_erp
dbfilter = ^aisy_erp$
http_interface = 127.0.0.1
http_port = 8069
addons_path = $addonsPath
logfile = $OdooLog
without_demo = all
limit_time_cpu = 120
limit_time_real = 240
"@
Set-Content -LiteralPath $OdooConf -Value $confContent -Encoding UTF8

$irModuleExists = & $psql -h 127.0.0.1 -p 5439 -U odoo -d aisy_erp -tAc "SELECT to_regclass('public.ir_module_module')" 2>$null
if (-not $irModuleExists -or $irModuleExists.Trim() -eq '') {
    Write-Host "Initializing Odoo 17 modules (base, sale_management, point_of_sale, pos_restaurant, calendar, stock, loyalty, crm)..."
    $odooBin = Join-Path $OdooDir 'odoo-bin'
    & $venvPy $odooBin -c $OdooConf -d aisy_erp -i base,sale_management,point_of_sale,pos_restaurant,calendar,stock,loyalty,crm --without-demo=all --stop-after-init
    if ($LASTEXITCODE -ne 0) { throw "Odoo database initialization failed" }
} else {
    Write-Host "Odoo database aisy_erp already initialized."
}

Write-Host "[5/5] Installing WhatsApp QR Gateway dependencies (Node.js)..." -ForegroundColor Cyan
if (-not (Test-Path -LiteralPath (Join-Path $WaDir 'node_modules'))) {
    Push-Location $WaDir
    try {
        npm install --no-audit --no-fund
        if ($LASTEXITCODE -ne 0) { throw "npm install failed in wa-gateway" }
    } finally {
        Pop-Location
    }
}

Write-Host "Portable runtime installation complete!" -ForegroundColor Green
