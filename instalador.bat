@echo off
setlocal enabledelayedexpansion

REM ============================================================
REM instalador.bat - Instalador do Espaco Guanais
REM
REM Detecta qualquer instalacao anterior (pasta do sistema e/ou
REM banco de dados espaco_guanais) na maquina, pede confirmacao e,
REM se confirmado, apaga tudo e instala uma copia nova e limpa.
REM
REM Uso normal:  instalar.bat
REM Modo teste (nao apaga nada de verdade, so mostra o que faria):
REM              instalar.bat /teste
REM ============================================================

set "MODO_TESTE=0"
if /i "%~1"=="/teste" set "MODO_TESTE=1"

set "XAMPP_DIR=C:\xampp"
set "DEST_DIR=%XAMPP_DIR%\htdocs\Sistema_Guanais"
set "SRC_DIR=%~dp0"
if "%SRC_DIR:~-1%"=="\" set "SRC_DIR=%SRC_DIR:~0,-1%"
set "DB_NAME=espaco_guanais"
set "MYSQL=%XAMPP_DIR%\mysql\bin\mysql.exe"
set "MYSQLD=%XAMPP_DIR%\mysql\bin\mysqld.exe"
set "HTTPD=%XAMPP_DIR%\apache\bin\httpd.exe"

echo ========================================
echo  Instalador - Espaco Guanais
if "%MODO_TESTE%"=="1" echo  (MODO TESTE - nada sera apagado de verdade)
echo ========================================
echo.

REM 1) Verificar se o XAMPP esta instalado nesta maquina
if not exist "%MYSQL%" (
    echo ERRO: XAMPP nao foi encontrado em %XAMPP_DIR%.
    echo Instale o XAMPP antes de continuar: https://www.apachefriends.org/pt_br/index.html
    echo.
    pause
    exit /b 1
)

REM 2) Garantir que o MySQL esta rodando, para poder checar o banco
tasklist /fi "imagename eq mysqld.exe" 2>nul | find /i "mysqld.exe" >nul
if errorlevel 1 (
    echo Iniciando MySQL...
    start "" /min "%MYSQLD%" --defaults-file="%XAMPP_DIR%\mysql\bin\my.ini"
    timeout /t 5 /nobreak >nul
)

REM 3) Detectar resquicios de uma instalacao anterior
set "ACHOU_PASTA=0"
set "ACHOU_BANCO=0"
if exist "%DEST_DIR%" set "ACHOU_PASTA=1"

set "TMPFILE=%TEMP%\guanais_dbcheck_%RANDOM%.txt"
"%MYSQL%" -u root -N -e "SHOW DATABASES LIKE '%DB_NAME%'" > "%TMPFILE%" 2>nul
for %%A in ("%TMPFILE%") do if %%~zA GTR 0 set "ACHOU_BANCO=1"
del "%TMPFILE%" >nul 2>&1

if "%ACHOU_PASTA%"=="0" if "%ACHOU_BANCO%"=="0" goto :semResquicio

echo ATENCAO: foi encontrada uma instalacao anterior nesta maquina:
if "%ACHOU_PASTA%"=="1" echo   - Pasta do sistema: %DEST_DIR%
if "%ACHOU_BANCO%"=="1" echo   - Banco de dados: %DB_NAME%
echo.
echo Continuar vai APAGAR essa instalacao anterior (pasta e/ou banco de
echo dados) e instalar uma copia nova e limpa do zero. Essa acao nao
echo pode ser desfeita.
echo.
set /p CONFIRMA="Digite CONFIRMAR para continuar (ou feche esta janela para cancelar): "
if /i not "%CONFIRMA%"=="CONFIRMAR" (
    echo.
    echo Instalacao cancelada. Nada foi alterado.
    pause
    exit /b 0
)
echo.

if "%ACHOU_BANCO%"=="1" (
    if "%MODO_TESTE%"=="1" (
        echo [MODO TESTE] Apagaria o banco de dados "%DB_NAME%".
    ) else (
        echo Apagando banco de dados anterior...
        "%MYSQL%" -u root -e "DROP DATABASE IF EXISTS %DB_NAME%"
    )
)
if "%ACHOU_PASTA%"=="1" (
    if "%MODO_TESTE%"=="1" (
        echo [MODO TESTE] Apagaria a pasta "%DEST_DIR%".
    ) else (
        echo Apagando pasta anterior...
        rmdir /s /q "%DEST_DIR%"
    )
)

:semResquicio

REM 4) Copiar os arquivos do sistema para o XAMPP
if "%MODO_TESTE%"=="1" (
    echo [MODO TESTE] Copiaria os arquivos de "%SRC_DIR%" para "%DEST_DIR%".
) else (
    echo Copiando arquivos do sistema...
    if not exist "%DEST_DIR%" mkdir "%DEST_DIR%"
    xcopy "%SRC_DIR%\*" "%DEST_DIR%\" /E /I /H /Y /EXCLUDE:%~dp0instalador_excluir.txt >nul
)

REM 5) Iniciar o Apache
tasklist /fi "imagename eq httpd.exe" 2>nul | find /i "httpd.exe" >nul
if errorlevel 1 (
    echo Iniciando Apache...
    start "" /min "%HTTPD%"
    timeout /t 3 /nobreak >nul
)

REM 6) Rodar o instalador do banco de dados (cria tabelas + usuario admin)
if "%MODO_TESTE%"=="1" (
    echo [MODO TESTE] Chamaria api/install.php para criar o banco e o usuario admin.
) else (
    echo Configurando banco de dados...
    powershell -NoProfile -Command "try { Invoke-WebRequest -Uri 'http://127.0.0.1/Sistema_Guanais/api/install.php' -UseBasicParsing | Out-Null; Write-Host 'Banco configurado com sucesso.' } catch { Write-Host 'AVISO: nao foi possivel confirmar a configuracao do banco automaticamente. Abra http://127.0.0.1/Sistema_Guanais/api/install.php manualmente.' }"
)

echo.
echo ========================================
echo  Instalacao concluida!
echo ========================================
echo Acesse: http://127.0.0.1/Sistema_Guanais/
echo Usuario: admin
echo Senha: 0301
echo IMPORTANTE: troque a senha do admin logo apos o primeiro acesso.
echo ========================================
echo.

if "%MODO_TESTE%"=="0" start "" "http://127.0.0.1/Sistema_Guanais/"
pause
