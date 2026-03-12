@echo off
chcp 65001 > nul
title Servidor Impresion - Santa Catalina

cd /d "%~dp0"

:: Verificar que node_modules existe
if not exist "node_modules" (
    echo [ERROR] Dependencias no instaladas.
    echo Ejecuta primero: instalar.bat
    echo.
    pause
    exit /b 1
)

echo Iniciando servidor de impresion...
echo Para detener: cerrar esta ventana o Ctrl+C
echo.

node server.js

:: Si el proceso termina con error, mostrar mensaje
if %ERRORLEVEL% NEQ 0 (
    echo.
    echo [ERROR] El servidor se detuvo inesperadamente.
    echo Revisar el mensaje de error arriba.
    echo.
    pause
)
