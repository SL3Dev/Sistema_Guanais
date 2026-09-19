@echo off
setlocal enabledelayedexpansion

REM ============================================================
REM backup.bat - Backup diario do banco espaco_guanais
REM Espaco Guanais - Sistema de Gestao Clinica
REM
REM Gera um dump do MySQL, salva localmente e copia para o
REM OneDrive e para o Google Drive, e apaga backups com mais de
REM 30 dias em todos os locais.
REM
REM Agendado para rodar todo dia as 02:00 via Agendador de
REM Tarefas do Windows (tarefa "Backup Sistema Guanais").
REM
REM IMPORTANTE: ONEDRIVE_DIR e GDRIVE_DIR precisam apontar para
REM uma pasta que o aplicativo OneDrive/Google Drive (instalado
REM no PC) já sincroniza sozinho. Este script so copia o arquivo
REM para dentro dela - quem envia para a nuvem e o app do
REM OneDrive/Google Drive, rodando em segundo plano.
REM ============================================================

set "MYSQLDUMP=C:\xampp\mysql\bin\mysqldump.exe"
set "DB_NAME=espaco_guanais"
set "DB_USER=root"
set "DB_PASS="
set "LOCAL_DIR=C:\xampp\backups\Sistema_Guanais"
set "ONEDRIVE_DIR=C:\Users\IMILE-TI\OneDrive\Backups_Sistema_Guanais"
set "GDRIVE_DIR=G:\Meu Drive\Backups_Sistema_Guanais"
set "RETENTION_DAYS=30"

REM Monta o timestamp no formato AAAA-MM-DD_HHMMSS independente da regional do Windows
for /f "usebackq delims=" %%T in (`powershell -NoProfile -Command "Get-Date -Format 'yyyy-MM-dd_HHmmss'"`) do set "TIMESTAMP=%%T"

set "FILENAME=espaco_guanais_%TIMESTAMP%.sql"

if not exist "%LOCAL_DIR%" mkdir "%LOCAL_DIR%"
if not exist "%ONEDRIVE_DIR%" mkdir "%ONEDRIVE_DIR%"
if exist "G:\" if not exist "%GDRIVE_DIR%" mkdir "%GDRIVE_DIR%"

echo [%DATE% %TIME%] Iniciando backup de %DB_NAME%...

"%MYSQLDUMP%" -u %DB_USER% %DB_NAME% > "%LOCAL_DIR%\%FILENAME%"
if errorlevel 1 (
    echo [%DATE% %TIME%] ERRO: mysqldump falhou. Backup NAO foi gerado.
    exit /b 1
)

if not exist "%LOCAL_DIR%\%FILENAME%" (
    echo [%DATE% %TIME%] ERRO: arquivo de backup nao foi criado.
    exit /b 1
)

copy /Y "%LOCAL_DIR%\%FILENAME%" "%ONEDRIVE_DIR%\%FILENAME%" >nul
if errorlevel 1 (
    echo [%DATE% %TIME%] AVISO: falha ao copiar backup para o OneDrive. Backup local foi mantido.
) else (
    echo [%DATE% %TIME%] Backup copiado para o OneDrive.
)

if exist "G:\" (
    copy /Y "%LOCAL_DIR%\%FILENAME%" "%GDRIVE_DIR%\%FILENAME%" >nul
    if errorlevel 1 (
        echo [%DATE% %TIME%] AVISO: falha ao copiar backup para o Google Drive. Backup local foi mantido.
    ) else (
        echo [%DATE% %TIME%] Backup copiado para o Google Drive.
    )
) else (
    echo [%DATE% %TIME%] AVISO: unidade do Google Drive G: nao encontrada. Pulei a copia para o Google Drive.
)

echo [%DATE% %TIME%] Backup local criado: %LOCAL_DIR%\%FILENAME%

REM Remove backups com mais de %RETENTION_DAYS% dias, em todos os locais
powershell -NoProfile -Command "Get-ChildItem -Path '%LOCAL_DIR%' -Filter 'espaco_guanais_*.sql' | Where-Object { $_.LastWriteTime -lt (Get-Date).AddDays(-%RETENTION_DAYS%) } | Remove-Item -Force"
powershell -NoProfile -Command "Get-ChildItem -Path '%ONEDRIVE_DIR%' -Filter 'espaco_guanais_*.sql' | Where-Object { $_.LastWriteTime -lt (Get-Date).AddDays(-%RETENTION_DAYS%) } | Remove-Item -Force"
if exist "G:\" powershell -NoProfile -Command "Get-ChildItem -Path '%GDRIVE_DIR%' -Filter 'espaco_guanais_*.sql' | Where-Object { $_.LastWriteTime -lt (Get-Date).AddDays(-%RETENTION_DAYS%) } | Remove-Item -Force"

echo [%DATE% %TIME%] Backup concluido com sucesso.
exit /b 0
