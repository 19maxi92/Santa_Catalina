@echo off
chcp 65001 > nul
echo ============================================
echo   Instalador - Servidor Impresion
echo   Santa Catalina
echo ============================================
echo.

:: Verificar Node.js
where node > nul 2>&1
if %ERRORLEVEL% NEQ 0 (
    echo [ERROR] Node.js no esta instalado.
    echo.
    echo Instalar desde: https://nodejs.org  (version LTS)
    echo Despues volver a ejecutar este archivo.
    echo.
    pause
    exit /b 1
)

for /f "tokens=*" %%v in ('node --version') do set NODE_VERSION=%%v
echo [OK] Node.js %NODE_VERSION% encontrado

:: Verificar npm
where npm > nul 2>&1
if %ERRORLEVEL% NEQ 0 (
    echo [ERROR] npm no encontrado. Reinstalar Node.js.
    pause
    exit /b 1
)

echo [OK] npm encontrado
echo.
echo Instalando dependencias...
echo (Puede tardar 1-2 minutos la primera vez)
echo.

cd /d "%~dp0"
npm install

if %ERRORLEVEL% NEQ 0 (
    echo.
    echo [ERROR] Fallo npm install.
    echo.
    echo Si el error menciona "build tools" o "Visual Studio":
    echo   Instalar: npm install -g windows-build-tools
    echo   (ejecutar cmd como Administrador)
    echo.
    pause
    exit /b 1
)

echo.
echo ============================================
echo   Instalacion completada correctamente
echo ============================================
echo.
echo CONFIGURACION:
echo   - Abrir config.json para cambiar nombre de impresora
echo   - Si nombreImpresora esta vacio, usa la impresora predeterminada
echo.
echo Para iniciar el servidor:
echo   Doble clic en  iniciar.bat
echo.
echo Para que inicie automaticamente con Windows:
echo   1. Click derecho en iniciar.bat
echo   2. "Copiar"
echo   3. Abrir: Inicio > Ejecutar > shell:startup
echo   4. Pegar el acceso directo ahi
echo.
pause
