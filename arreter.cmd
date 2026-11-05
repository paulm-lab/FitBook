@echo off
rem Double-clic : lance arreter.ps1 sans modifier la strategie d'execution de PowerShell.
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0arreter.ps1" %*
pause
