<#
.SYNOPSIS
    Démarre tout l'environnement de développement FitBook d'un coup.

.DESCRIPTION
    1. Vérifie MySQL (WAMP) sur le port 3306.
    2. Démarre Mailpit (e-mails de test) : http://127.0.0.1:8025, SMTP 1025.
    3. Démarre l'API Symfony en arrière-plan (http://127.0.0.1:8000) et son worker d'e-mails
       (backend/.symfony.local.yaml).
    4. Ouvre une fenêtre pour le front Angular (http://localhost:4200) et le navigateur.

    Chaque service déjà démarré est laissé tel quel : le script peut être relancé sans risque.
    Arrêt : .\arreter.ps1 (ou arreter.cmd).

.PARAMETER SansFront
    Ne démarre pas le front Angular (API, worker et Mailpit seulement).

.PARAMETER SansNavigateur
    N'ouvre pas le navigateur à la fin.
#>
param(
    [switch] $SansFront,
    [switch] $SansNavigateur
)

$ErrorActionPreference = 'Stop'
$racine = $PSScriptRoot
$backend = Join-Path $racine 'backend'
$frontend = Join-Path $racine 'frontend'
$mailpitDossier = Join-Path $env:LOCALAPPDATA 'Programs\mailpit'
$mailpit = Join-Path $mailpitDossier 'mailpit.exe'

function Etape([string] $texte) { Write-Host "`n> $texte" -ForegroundColor Cyan }
function Ok([string] $texte) { Write-Host "  [ok] $texte" -ForegroundColor Green }
function Attention([string] $texte) { Write-Host "  [!]  $texte" -ForegroundColor Yellow }

# Vraie tentative de connexion (plus fiable que la table des ports, qui peut être incomplète),
# en IPv4 puis en IPv6 : « ng serve » écoute sur localhost, souvent résolu en ::1.
function PortOuvert([int] $port) {
    foreach ($adresse in '127.0.0.1', '::1') {
        $client = New-Object System.Net.Sockets.TcpClient([System.Net.IPAddress]::Parse($adresse).AddressFamily)
        try {
            if ($client.ConnectAsync($adresse, $port).Wait(500) -and $client.Connected) { return $true }
        } catch {
            # Port fermé sur cette adresse : on essaie la suivante.
        } finally {
            $client.Dispose()
        }
    }
    return $false
}

function AttendrePort([int] $port, [int] $secondes = 30) {
    for ($i = 0; $i -lt $secondes; $i++) {
        if (PortOuvert $port) { return $true }
        Start-Sleep -Seconds 1
    }
    return $false
}

Write-Host 'FitBook - démarrage de l''environnement de développement' -ForegroundColor White

# --- 1. MySQL ------------------------------------------------------------------
Etape 'MySQL (WAMP)'
if (PortOuvert 3306) {
    Ok 'MySQL répond sur le port 3306.'
} else {
    try {
        # Fonctionne si la console a les droits administrateur ; sinon, démarrer WAMP.
        Start-Service wampmysqld64 -ErrorAction Stop
        if (AttendrePort 3306 20) { Ok 'Service wampmysqld64 démarré.' } else { throw 'délai dépassé' }
    } catch {
        Attention 'MySQL n''est pas démarré : lancez WampServer (icône verte), puis relancez ce script.'
        Attention 'L''API démarrera quand même, mais ses appels échoueront tant que MySQL est arrêté.'
    }
}

# --- 2. Mailpit ----------------------------------------------------------------
Etape 'Mailpit (e-mails de développement)'
if (PortOuvert 1025) {
    Ok 'Mailpit tourne déjà : http://127.0.0.1:8025'
} elseif (-not (Test-Path $mailpit)) {
    Attention "Mailpit introuvable ($mailpit)."
    Attention 'Téléchargez mailpit-windows-amd64.zip sur https://github.com/axllent/mailpit/releases et décompressez-le dans ce dossier.'
} else {
    Start-Process -FilePath $mailpit -WindowStyle Hidden -ArgumentList @(
        '--listen', '127.0.0.1:8025',
        '--smtp', '127.0.0.1:1025',
        '--database', (Join-Path $mailpitDossier 'mailpit.db')
    )
    if (AttendrePort 1025 15) { Ok 'Mailpit démarré : http://127.0.0.1:8025' } else { Attention 'Mailpit ne répond pas.' }
}

# --- 3. API Symfony + worker d'e-mails -----------------------------------------
Etape 'API Symfony et worker d''e-mails'
if (-not (Get-Command symfony -ErrorAction SilentlyContinue)) {
    throw 'Symfony CLI introuvable dans le PATH (https://symfony.com/download).'
}
Push-Location $backend
try {
    # Un ancien serveur en HTTPS sur le port 8000 casserait le proxy du front : on le remplace.
    $reponse = $null
    if (PortOuvert 8000) {
        try { $reponse = Invoke-WebRequest 'http://127.0.0.1:8000/api/activites' -UseBasicParsing -MaximumRedirection 0 -TimeoutSec 10 } catch { $reponse = $_.Exception.Response }
    }
    if ($reponse -and [int]$reponse.StatusCode -eq 200) {
        Ok 'L''API tourne déjà : http://127.0.0.1:8000'
    } else {
        if (PortOuvert 8000) {
            Attention 'Un serveur occupe le port 8000 sans répondre en HTTP (HTTPS ?) : il est arrêté.'
            cmd /c 'symfony server:stop >nul 2>&1'
        }
        # Un worker lancé à la main ferait doublon avec celui de .symfony.local.yaml.
        Get-CimInstance Win32_Process -Filter "Name='php.exe'" |
            Where-Object { $_.CommandLine -like '*messenger:consume*' } |
            ForEach-Object { Stop-Process -Id $_.ProcessId -Force }

        # Fenêtre cachée plutôt que « symfony serve -d » : sous Windows, le mode démon échoue
        # quand .symfony.local.yaml existe (journal verrouillé par Symfony CLI lui-même).
        Start-Process -FilePath 'symfony' -ArgumentList 'serve' -WorkingDirectory $backend -WindowStyle Hidden
        if (AttendrePort 8000 30) {
            Ok 'API démarrée : http://127.0.0.1:8000 (documentation : /api/doc)'
            Ok 'Worker d''e-mails démarré (backend\.symfony.local.yaml).'
        } else {
            Attention 'L''API ne répond pas : lancez « symfony serve » dans backend\ pour voir l''erreur.'
        }
    }
} finally {
    Pop-Location
}

# --- 4. Front Angular ------------------------------------------------------------
if (-not $SansFront) {
    Etape 'Front Angular'
    if (PortOuvert 4200) {
        Ok 'Le front tourne déjà : http://localhost:4200'
    } else {
        if (-not (Test-Path (Join-Path $frontend 'node_modules'))) {
            Attention 'Dépendances absentes : npm install (une seule fois)...'
            Push-Location $frontend
            try { cmd /c 'npm install' } finally { Pop-Location }
        }
        # Fenêtre séparée : les erreurs de compilation restent visibles ; la fermer arrête le front.
        Start-Process -FilePath 'powershell.exe' -WorkingDirectory $frontend -ArgumentList @(
            '-NoExit', '-NoProfile', '-Command',
            "`$Host.UI.RawUI.WindowTitle = 'FitBook - front Angular'; npm start"
        )
        if (AttendrePort 4200 90) { Ok 'Front démarré : http://localhost:4200' } else { Attention 'Le front compile encore : voir sa fenêtre.' }
    }
}

# --- Récapitulatif ---------------------------------------------------------------
Write-Host "`nFitBook est prêt." -ForegroundColor Green
Write-Host '  Site        http://localhost:4200'
Write-Host '  API         http://127.0.0.1:8000/api/doc'
Write-Host '  E-mails     http://127.0.0.1:8025'
Write-Host '  Comptes     sportif1@fitbook.test / Sportif123!  ·  admin@fitbook.test / Admin123!'
Write-Host '  Arrêt       .\arreter.ps1'

if (-not $SansFront -and -not $SansNavigateur) {
    Start-Process 'http://localhost:4200'
}
