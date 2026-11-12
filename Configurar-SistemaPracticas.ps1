$ErrorActionPreference = 'Stop'
$nombreSitio = 'SistemaPracticas.UMG'
$raizProyecto = $PSScriptRoot

# Solicita elevación automáticamente porque hosts y Apache son archivos protegidos.
$identidad = [Security.Principal.WindowsIdentity]::GetCurrent()
$principal = [Security.Principal.WindowsPrincipal]::new($identidad)
if (-not $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
    $argumentos = "-NoProfile -ExecutionPolicy Bypass -File `"$PSCommandPath`""
    Start-Process -FilePath 'powershell.exe' -Verb RunAs -ArgumentList $argumentos
    exit
}

$xampp = 'C:\xampp'
Add-Type -AssemblyName System.Windows.Forms
if (-not (Test-Path (Join-Path $xampp 'apache\conf\httpd.conf'))) {
    $selector = [System.Windows.Forms.FolderBrowserDialog]::new()
    $selector.Description = 'Selecciona la carpeta donde está instalado XAMPP'
    if ($selector.ShowDialog() -ne [System.Windows.Forms.DialogResult]::OK) {
        throw 'No se seleccionó la carpeta de XAMPP.'
    }
    $xampp = $selector.SelectedPath
}

$hosts = Join-Path $env:windir 'System32\drivers\etc\hosts'
$vhosts = Join-Path $xampp 'apache\conf\extra\httpd-vhosts.conf'
$httpd = Join-Path $xampp 'apache\conf\httpd.conf'
$httpdExe = Join-Path $xampp 'apache\bin\httpd.exe'
foreach ($archivo in @($hosts, $vhosts, $httpd)) {
    if (-not (Test-Path -LiteralPath $archivo -PathType Leaf)) {
        throw "No se encontró '$archivo'. Verifica que seleccionaste la carpeta de XAMPP."
    }
}

$marcadorInicio = '# BEGIN SistemaPracticas.UMG'
$marcadorFin = '# END SistemaPracticas.UMG'
$rutaWeb = $raizProyecto.Replace('\', '/')
$bloque = @"
$marcadorInicio
<VirtualHost *:80>
    ServerName $nombreSitio
    DocumentRoot "$rutaWeb"
    <Directory "$rutaWeb">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
$marcadorFin
"@

$textoHosts = [IO.File]::ReadAllText($hosts)
if ($textoHosts -notmatch "(?im)^\s*127\.0\.0\.1\s+$([regex]::Escape($nombreSitio))\s*(?:#.*)?$") {
    Copy-Item -LiteralPath $hosts -Destination "$hosts.SistemaPracticas.bak" -Force
    [IO.File]::AppendAllText($hosts, "`r`n127.0.0.1 $nombreSitio`r`n", [Text.Encoding]::ASCII)
}

$textoVhosts = [IO.File]::ReadAllText($vhosts)
$patron = '(?s)# BEGIN SistemaPracticas\.UMG.*?# END SistemaPracticas\.UMG'
if ($textoVhosts -match $patron) {
    $nuevoVhosts = [regex]::Replace($textoVhosts, $patron, [System.Text.RegularExpressions.MatchEvaluator]{ param($m) $bloque.Trim() })
} else {
    $nuevoVhosts = $textoVhosts.TrimEnd() + "`r`n`r`n" + $bloque.Trim() + "`r`n"
}
if ($nuevoVhosts -ne $textoVhosts) {
    Copy-Item -LiteralPath $vhosts -Destination "$vhosts.SistemaPracticas.bak" -Force
    [IO.File]::WriteAllText($vhosts, $nuevoVhosts, [Text.Encoding]::Default)
}

$textoHttpd = [IO.File]::ReadAllText($httpd)
$nuevoHttpd = $textoHttpd
foreach ($directiva in @(
    'LoadModule vhost_alias_module modules/mod_vhost_alias.so',
    'Include conf/extra/httpd-vhosts.conf'
)) {
    $regex = '(?im)^\s*#\s*' + [regex]::Escape($directiva) + '\s*$'
    $nuevoHttpd = [regex]::Replace($nuevoHttpd, $regex, $directiva)
}
if ($nuevoHttpd -ne $textoHttpd) {
    Copy-Item -LiteralPath $httpd -Destination "$httpd.SistemaPracticas.bak" -Force
    [IO.File]::WriteAllText($httpd, $nuevoHttpd, [Text.Encoding]::Default)
}

if (Test-Path -LiteralPath $httpdExe) {
    & $httpdExe -t
    if ($LASTEXITCODE -ne 0) {
        throw 'Apache encontró un error de configuración. Se guardaron copias .SistemaPracticas.bak junto a los archivos modificados.'
    }
}

[System.Windows.Forms.MessageBox]::Show(
    "Configuración lista. Reinicia Apache desde el panel de XAMPP y abre:`n`nhttp://$nombreSitio/",
    'Sistema de Prácticas',
    [System.Windows.Forms.MessageBoxButtons]::OK,
    [System.Windows.Forms.MessageBoxIcon]::Information
) | Out-Null
