@echo off
cd /d "%~dp0"
echo === INICIANDO PROCESO BATCH SOPHYFARM ===
call t1_cargar_archivos.bat
call t2_enviar_correo.bat
call t3_enviar_sms.bat
call t4_limpiar_logs.bat
echo === PROCESO COMPLETADO ===
pause
