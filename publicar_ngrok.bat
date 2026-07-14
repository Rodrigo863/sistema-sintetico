@echo off
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0publicar_ngrok.ps1" %*
pause
