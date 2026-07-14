param(
    [string]$ProjectName = "proyecto sintetico",
    [string]$XamppPath = "C:\xampp",
    [string]$DbUser = "root",
    [string]$DbPass = "",
    [switch]$SkipDatabase,
    [switch]$SkipCopy
)

$ErrorActionPreference = "Stop"

function Write-Step {
    param([string]$Message)
    Write-Host ""
    Write-Host "==> $Message" -ForegroundColor Cyan
}

function Test-Port {
    param([int]$Port)

    try {
        $client = New-Object Net.Sockets.TcpClient
        $async = $client.BeginConnect("127.0.0.1", $Port, $null, $null)
        $connected = $async.AsyncWaitHandle.WaitOne(700, $false)
        if ($connected) {
            $client.EndConnect($async)
        }
        $client.Close()
        return $connected
    } catch {
        return $false
    }
}

function Get-CommandPath {
    param([string]$Name)

    $cmd = Get-Command $Name -ErrorAction SilentlyContinue
    if ($cmd) {
        return $cmd.Source
    }

    return $null
}

function Get-NgrokUrl {
    if (!(Test-Port 4040)) {
        return $null
    }

    for ($i = 0; $i -lt 30; $i++) {
        try {
            $tunnels = Invoke-RestMethod -Uri "http://127.0.0.1:4040/api/tunnels" -TimeoutSec 2
            $httpsTunnel = $tunnels.tunnels | Where-Object { $_.proto -eq "https" } | Select-Object -First 1
            if ($httpsTunnel.public_url) {
                return $httpsTunnel.public_url
            }
        } catch {
            Start-Sleep -Seconds 1
        }
    }

    return $null
}

$ProjectPath = $PSScriptRoot
$HtdocsPath = Join-Path $XamppPath "htdocs"
$TargetPath = Join-Path $HtdocsPath $ProjectName
$SchemaPath = Join-Path $ProjectPath "schema.sql"
$PublicProjectPath = $ProjectName.Replace(" ", "%20")

Write-Step "Verificando XAMPP"
if (!(Test-Path $XamppPath)) {
    throw "No encontre XAMPP en $XamppPath. Ejecuta con -XamppPath 'TU_RUTA' si lo tienes en otra carpeta."
}
if (!(Test-Path $HtdocsPath)) {
    throw "No encontre htdocs en $HtdocsPath."
}

if (!$SkipCopy) {
    Write-Step "Copiando proyecto a htdocs"
    if ((Test-Path $TargetPath) -and ((Resolve-Path $ProjectPath).Path -ieq (Resolve-Path -LiteralPath $TargetPath).Path)) {
        Write-Host "El proyecto ya parece estar dentro de htdocs. Salto la copia."
    } else {
        New-Item -ItemType Directory -Force -Path $TargetPath | Out-Null
        $robocopyArgs = @(
            $ProjectPath,
            $TargetPath,
            "/E",
            "/XD", ".git", ".agents", ".codex",
            "/XF", "*.log", "*.tmp", "*.bak", "*.swp",
            "/NFL", "/NDL", "/NJH", "/NJS", "/NP"
        )
        & robocopy @robocopyArgs | Out-Null
        if ($LASTEXITCODE -gt 7) {
            throw "Robocopy fallo con codigo $LASTEXITCODE."
        }
        Write-Host "Proyecto listo en: $TargetPath"
    }
}

Write-Step "Iniciando Apache y MySQL si hace falta"
$xamppStart = Join-Path $XamppPath "xampp_start.exe"
if (!(Test-Port 80) -or !(Test-Port 3306)) {
    if (Test-Path $xamppStart) {
        Start-Process -FilePath $xamppStart -WorkingDirectory $XamppPath
        Start-Sleep -Seconds 8
    } else {
        Write-Warning "No encontre xampp_start.exe. Inicia Apache y MySQL desde el panel de XAMPP."
    }
}

if (!(Test-Port 80)) {
    Write-Warning "Apache no responde en el puerto 80. Revisa XAMPP antes de abrir el link."
}
if (!(Test-Port 3306)) {
    Write-Warning "MySQL no responde en el puerto 3306. Revisa XAMPP antes de usar el sistema."
}

if (!$SkipDatabase) {
    Write-Step "Inicializando base de datos desde schema.sql"
    $mysqlExe = Join-Path $XamppPath "mysql\bin\mysql.exe"
    if (!(Test-Path $mysqlExe)) {
        Write-Warning "No encontre mysql.exe en $mysqlExe. Salto la inicializacion de base de datos."
    } elseif (!(Test-Path $SchemaPath)) {
        Write-Warning "No encontre schema.sql. Salto la inicializacion de base de datos."
    } else {
        $mysqlArgs = @("-u$DbUser")
        if ($DbPass -ne "") {
            $mysqlArgs += "-p$DbPass"
        }
        Get-Content -Raw -LiteralPath $SchemaPath | & $mysqlExe @mysqlArgs
        if ($LASTEXITCODE -ne 0) {
            throw "No se pudo importar schema.sql. Revisa usuario/contrasena de MySQL."
        }
        Write-Host "Base de datos lista: sistema_clientes"
    }
}

Write-Step "Abriendo ngrok"
$ngrokExe = Get-CommandPath "ngrok.exe"
if (!$ngrokExe) {
    $localNgrok = Join-Path $ProjectPath "ngrok.exe"
    if (Test-Path $localNgrok) {
        $ngrokExe = $localNgrok
    }
}
if (!$ngrokExe) {
    $downloadsNgrok = Join-Path $env:USERPROFILE "Downloads\ngrok-v3-stable-windows-amd64\ngrok.exe"
    if (Test-Path $downloadsNgrok) {
        $ngrokExe = $downloadsNgrok
    }
}

if (!$ngrokExe) {
    Write-Host "No encontre ngrok instalado." -ForegroundColor Yellow
    Write-Host "Descargalo desde https://ngrok.com/download, inicia sesion y ejecuta:"
    Write-Host "  ngrok config add-authtoken TU_TOKEN"
    Write-Host "Despues vuelve a correr este script."
    exit 1
}

$currentNgrokUrl = Get-NgrokUrl
if (!$currentNgrokUrl) {
    Start-Process -FilePath $ngrokExe -ArgumentList @("http", "80") -WindowStyle Hidden
    Start-Sleep -Seconds 4
}

$ngrokUrl = if ($currentNgrokUrl) { $currentNgrokUrl } else { Get-NgrokUrl }
if (!$ngrokUrl) {
    throw "Ngrok se abrio, pero no pude leer el link desde http://127.0.0.1:4040. Abre esa direccion y copia el HTTPS manualmente."
}

$AppUrl = "$ngrokUrl/$PublicProjectPath/"
$PublicReservationUrl = "$ngrokUrl/$PublicProjectPath/reservas_publicas.php"

Write-Host ""
Write-Host "LISTO" -ForegroundColor Green
Write-Host "Sistema completo:"
Write-Host "  $AppUrl" -ForegroundColor Green
Write-Host "Reservas publicas:"
Write-Host "  $PublicReservationUrl" -ForegroundColor Green
Write-Host ""
Write-Host "Acceso inicial: administrador / admin123"
Write-Host "Cambia esa contrasena antes de pasar el link a otras personas."
