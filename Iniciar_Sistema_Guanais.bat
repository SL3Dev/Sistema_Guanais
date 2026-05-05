@echo off
title Inicializador - Sistema Guanais

set "XAMPP_DIR=C:\xampp"
set "PROJECT_URL=http://localhost/Sistema_Guanais/"

if not exist "%XAMPP_DIR%\xampp-control.exe" (
    echo [ERRO] Nao foi encontrado o XAMPP em: %XAMPP_DIR%
    echo Ajuste o caminho dentro deste arquivo .bat se necessario.
    pause
    exit /b 1
)

echo Abrindo painel do XAMPP...
start "" "%XAMPP_DIR%\xampp-control.exe"

echo Iniciando Apache...
if exist "%XAMPP_DIR%\apache_start.bat" (
    start "" /min cmd /c ""%XAMPP_DIR%\apache_start.bat""
) else (
    echo [AVISO] Nao encontrei apache_start.bat
)

echo Iniciando MySQL...
if exist "%XAMPP_DIR%\mysql_start.bat" (
    start "" /min cmd /c ""%XAMPP_DIR%\mysql_start.bat""
) else (
    echo [AVISO] Nao encontrei mysql_start.bat
)

echo Aguardando servicos subirem...
timeout /t 4 /nobreak >nul

echo Abrindo sistema no navegador...
start "" "%PROJECT_URL%"

exit /b 0
