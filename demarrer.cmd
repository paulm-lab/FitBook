@echo off
rem Double-clic : lance demarrer.ps1 sans modifier la strategie d'execution de PowerShell.
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0demarrer.ps1" %*
pause
