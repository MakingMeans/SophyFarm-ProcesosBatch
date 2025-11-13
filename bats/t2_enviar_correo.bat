@echo off
setlocal enabledelayedexpansion

REM === LEER CONFIGURACIÓN ===
for /f "tokens=1,2 delims==" %%a in ('findstr /B "ruta_php" "%~dp0\..\config.properties"') do (
    set "rutaPhp=%%b"
)

REM === EJECUTAR TAREA ===
echo Ejecutando envio del correo...
"C:\xampp\php\php.exe" "!rutaPhp!t2_enviar_correo.php"
pause
