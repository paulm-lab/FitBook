<#
.SYNOPSIS
    Arrête l'environnement de développement FitBook démarré par demarrer.ps1.

.DESCRIPTION
    Arrête le front Angular (port 4200), l'API Symfony et son worker d'e-mails, puis Mailpit.
    MySQL (WAMP) n'est pas touché.

.PARAMETER GarderMailpit
    Laisse Mailpit tourner (les e-mails restent consultables).
#>
param([switch] $GarderMailpit)

$backend = Join-Path $PSScriptRoot 'backend'

function Ok([string] $texte) { Write-Host "  [ok] $texte" -ForegroundColor Green }

# Front Angular : le processus Node qui écoute le port 4200, et sa fenêtre PowerShell.
# Front Angular : la fenêtre ouverte par demarrer.ps1 (et ses processus Node), ou tout
# « ng serve » du dossier frontend lancé à la main.
$fenetres = Get-CimInstance Win32_Process -Filter "Name='powershell.exe'" |
    Where-Object { $_.CommandLine -like '*FitBook - front Angular*' }
$serveurs = Get-CimInstance Win32_Process -Filter "Name='node.exe'" |
    Where-Object { $_.CommandLine -like '*ng*serve*' -or $_.CommandLine -like '*@angular*' }
foreach ($p in @($fenetres) + @($serveurs)) {
    if ($p) { cmd /c "taskkill /PID $($p.ProcessId) /T /F >nul 2>&1" }
}
if ($fenetres -or $serveurs) { Ok 'Front Angular arrêté.' }

# API et worker (gérés par Symfony CLI), plus un éventuel worker lancé à la main.
Push-Location $backend
try { cmd /c 'symfony server:stop >nul 2>&1' } finally { Pop-Location }
Get-CimInstance Win32_Process -Filter "Name='php.exe'" |
    Where-Object { $_.CommandLine -like '*messenger:consume*' } |
    ForEach-Object { Stop-Process -Id $_.ProcessId -Force }
# Filet de sécurité : un « symfony serve » lancé au premier plan n'est pas arrêté par
# server:stop ; ses processus PHP CGI non plus.
Get-CimInstance Win32_Process -Filter "Name='symfony.exe' OR Name='php-cgi.exe'" |
    Where-Object { $_.CommandLine -like '* serve*' -or $_.CommandLine -like '*symfony-cli*' } |
    ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }
Ok 'API et worker d''e-mails arrêtés.'

if (-not $GarderMailpit) {
    Get-Process mailpit -ErrorAction SilentlyContinue | Stop-Process -Force
    Ok 'Mailpit arrêté (les e-mails sont conservés dans sa base).'
}

Write-Host "`nFitBook est arrêté. MySQL (WAMP) reste démarré." -ForegroundColor Green
