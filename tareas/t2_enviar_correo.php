<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../config_reader.php';
require_once __DIR__ . '/../lib/PHPMailer/Exception.php';
require_once __DIR__ . '/../lib/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../lib/PHPMailer/SMTP.php';

$config = leerPropiedades(__DIR__ . '/../config.properties');
$rutaJsons = $config['ruta_jsons'];

$mail = new PHPMailer(true);
$mail->isSMTP();
$mail->Host = 'smtp.gmail.com';
$mail->SMTPAuth = true;
$mail->Username = $config['correo_remitente'];
$mail->Password = $config['correo_password'];
$mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
$mail->Port = 587;

$mail->setFrom($config['correo_remitente'], 'SophyFarm Batch');
$mail->addAddress($config['correo_destinatario']);
$mail->isHTML(true);
$mail->Subject = "Resumen de Procesos Batch - SophyFarm";

$cuerpo = "
<div style='font-family:Segoe UI,Arial,sans-serif;background:#f4f6fa;padding:20px;'>
  <div style='background:#fff;border-radius:10px;max-width:700px;margin:auto;box-shadow:0 3px 8px rgba(0,0,0,0.1);padding:25px;'>
    <h2 style='color:#2a7ae2;text-align:center;margin-bottom:5px;'>Resumen de Procesos Batch - SophyFarm</h2>
    <p style='text-align:center;color:#666;font-size:14px;margin-top:0;'>Reporte de cargas automáticas desde el sistema</p>
    <hr style='border:none;border-top:1px solid #ddd;margin:20px 0;'>
    <table style='width:100%;border-collapse:collapse;font-size:14px;'>
      <thead>
        <tr style='background:#2a7ae2;color:white;text-align:center;'>
          <th>Archivo</th><th>Total</th><th>Exitosos</th><th>Fallidos</th><th>Duración</th><th>Pico Memoria</th>
        </tr>
      </thead>
      <tbody>";

$jsonFiles = glob("$rutaJsons/temp_ejec_*.json");
if (empty($jsonFiles)) {
    $cuerpo .= "<tr><td colspan='6' style='text-align:center;color:#777;padding:10px;'>No hay ejecuciones recientes.</td></tr>";
} else {
    foreach ($jsonFiles as $json) {
        $data = json_decode(file_get_contents($json), true);
        foreach ($data['csvs'] as $csv) {
            $cuerpo .= "<tr style='text-align:center;background:#f9f9f9;'>
                <td>{$csv['archivo']}</td>
                <td>{$csv['total']}</td>
                <td style='color:black;font-weight:bold;'>{$csv['insertados']}</td>
                <td style='color:black;font-weight:bold;'>{$csv['errores']}</td>
                <td>{$csv['duracion']} s</td>
                <td>{$csv['pico']} MB</td>
            </tr>";
        }
    }
}

$cuerpo .= "</tbody></table>
    <hr style='border:none;border-top:1px solid #ddd;margin:20px 0;'>
    <p style='text-align:center;color:#888;font-size:12px;'>
      Este es un correo automático del sistema batch de SophyFarm.<br>
      <strong>David Santiago García Preciado</strong>
    </p>
  </div>
</div>";

$mail->Body = $cuerpo;
$mail->send();

echo "Correo enviado correctamente.<br>";
?>
