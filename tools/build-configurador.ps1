$ErrorActionPreference = 'Stop'
$raiz = Split-Path -Parent $PSScriptRoot
$fuente = Join-Path $PSScriptRoot 'ConfiguradorSistemaPracticas.cs'
$manifest = Join-Path $PSScriptRoot 'Configurador.manifest'
$sql = Join-Path $raiz 'database\basededatos.sql'

$compiladores = @(
    (Join-Path $env:WINDIR 'Microsoft.NET\Framework64\v4.0.30319\csc.exe'),
    (Join-Path $env:WINDIR 'Microsoft.NET\Framework\v4.0.30319\csc.exe')
)
$compilador = $compiladores | Where-Object { Test-Path -LiteralPath $_ } | Select-Object -First 1
if (-not $compilador) { throw 'No se encontró el compilador de .NET Framework. Instala .NET Framework 4.x para generar el ejecutable.' }
foreach ($archivo in @($fuente, $manifest, $sql)) {
    if (-not (Test-Path -LiteralPath $archivo -PathType Leaf)) { throw "Falta un archivo necesario para crear la distribución: $archivo" }
}

$ejecutable = Join-Path $raiz 'Ejecutar primero.exe'
$argumentos = @(
    '/nologo', '/target:winexe', '/platform:anycpu',
    "/out:$ejecutable", "/win32manifest:$manifest",
    '/reference:System.Windows.Forms.dll', '/reference:System.Core.dll',
    $fuente
)
& $compilador @argumentos
if ($LASTEXITCODE -ne 0) { throw 'Falló la compilación del configurador.' }
Copy-Item -LiteralPath $sql -Destination (Join-Path $raiz 'basededatos.sql') -Force
Write-Host "Ejecutable y base de datos actualizados en $raiz"