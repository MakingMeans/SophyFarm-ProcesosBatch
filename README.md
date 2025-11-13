# SophyFarm Batch Data Processor – PHP & MySQL

Sistema batch desarrollado en **PHP** para la carga, validación y auditoría de grandes volúmenes de datos de inventario en una base de datos MySQL.  
Diseñado para automatizar tareas de carga masiva, notificación por correo y mensajería instantánea.

## Descripción

**SophyFarm Batch** permite procesar archivos CSV con miles de registros de productos, aplicando reglas de validación, manejo de errores, y registro de auditoría.  
El sistema automatiza tres acciones principales:

- Inserción de datos validados en la base de datos.  
- Envío de reportes por **correo electrónico** con resumen de ejecución.  
- Notificación por **WhatsApp** usando **CallMeBot**.

## Tecnologías

- PHP 8
- MySQL 8
- PHPMailer 6.x
- CallMeBot API
- XAMPP (Apache + PHP)
- Windows Batch Scripts

## Estructura del proyecto

```
taller/
├── archivos/
│   ├── csv_files_here.txt
├── bats/
│   ├── ejecutar_todo.bat
│   ├── t1_cargar_archivos.bat
│   ├── t2_enviar_correo.bat
│   ├── t3_enviar_sms.bat
│   └── t4_limpiar_temp.bat
├── lib/
│   └── PHPMailer/
│       └── Exception.php
│       └── PHPMailer.php
│       └── SMTP.php
├── logs/
│   └── errors_csv_here.txt
│   └── temp/
│     └── temp_jsons_here.txt
├── tareas/
│   ├── t1_cargar_archivos.php
│   ├── t2_enviar_correo.php
│   ├── t3_enviar_sms.php
│   └── t4_limpiar_temp.php
├── .gitignore
├── config.properties
├── config_reader.php
├── db.class.php
└── wget64.exe
```

## Modo de validación

- **SELECTIVO:** Inserta los registros válidos y genera un CSV con los errores detectados.
- **TRANSACCIONAL:** Realiza rollback completo si ocurre algún error, asegurando consistencia total.

Los archivos de errores se guardan en `/logs/` con el prefijo `errors_`.

## Notificaciones

- **Correo (t2):** usa **PHPMailer** con Gmail SMTP.
- **WhatsApp (t3):** usa **CallMeBot API** (`https://api.callmebot.com`).

## Configuración

Archivo: `config.properties`

```properties
db_host=localhost
db_name=sophyfarm
db_user=root
db_pass=
ruta_archivos=C:/xampp/htdocs/taller/archivos/
ruta_logs=C:/xampp/htdocs/taller/logs/
ruta_jsons=C:/xampp/htdocs/taller/logs/temp/
correo_remitente=correo@gmail.com
correo_password=clave_app
whatsapp_phone=+573xxxxxxxx
whatsapp_apikey=xxxxxxxx
```

## Ejecución

1. Copia los CSV en la carpeta `/archivos/`.
2. Ejecuta el archivo batch principal o cada tarea individualmente:
   ```cmd
   tareas\t1_cargar_archivos.php
   tareas\t2_enviar_correo.php
   tareas\t3_enviar_sms.php
   tareas\t4_limpiar_temp.php
   ```
3. Revisa los resultados en:
   - Base de datos → tabla `auditoria`
   - Correos recibidos → resumen detallado
   - WhatsApp → resumen global
