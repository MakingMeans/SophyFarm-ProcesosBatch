<?php
require_once __DIR__ . '/../config_reader.php';
$config = leerPropiedades(__DIR__ . '/../config.properties');
$rutaJsons = $config['ruta_jsons'];
$apikey = $config['whatsapp_apikey'];
$phone  = $config['whatsapp_phone'];

$jsonFiles = glob("$rutaJsons/temp_ejec_*.json");
if (empty($jsonFiles)) {
    echo "No hay archivos JSON de ejecución para enviar.<br>";
    exit;
}

date_default_timezone_set('America/Bogota');

// Obtener la ejecución más reciente
rsort($jsonFiles);
$ultimoJson = $jsonFiles[0];
$data = json_decode(file_get_contents($ultimoJson), true);

if (empty($data['csvs'])) {
    echo "El archivo JSON más reciente no contiene datos válidos.<br>";
    exit;
}

$totalArchivos = count($data['csvs']);
$totalRegistros = 0;
$totalInsertados = 0;
$totalFallidos = 0;
$totalDuracion = 0;
$totalMemoria = 0;

// Calcular totales y promedios
foreach ($data['csvs'] as $csv) {
    $totalRegistros += $csv['total'];
    $totalInsertados += $csv['insertados'];
    $totalFallidos += $csv['errores'];
    $totalDuracion += floatval($csv['duracion']);
    $totalMemoria += floatval($csv['pico']);
}

$promedioRegistros = $totalRegistros / $totalArchivos;
$promedioInsertados = $totalInsertados / $totalArchivos;
$promedioFallidos = $totalFallidos / $totalArchivos;
$promedioDuracion = $totalDuracion / $totalArchivos;
$promedioMemoria = $totalMemoria / $totalArchivos;

$mensaje = "*SophyFarm - Resumen de Procesos Batch*\n";
$mensaje .= "Fecha: " . date("d/m/Y H:i:s") . " (UTC-5)\n";
$mensaje .= "──────────────────────────────\n";
$mensaje .= "*Archivos procesados:* $totalArchivos\n";
$mensaje .= "*Promedio registros:* " . number_format($promedioRegistros, 0) . "\n";
$mensaje .= "*Promedio exitosos:* " . number_format($promedioInsertados, 0) . "\n";
$mensaje .= "*Promedio fallidos:* " . number_format($promedioFallidos, 0) . "\n";
$mensaje .= "*Duración promedio:* " . number_format($promedioDuracion, 2) . "s\n";
$mensaje .= "*Memoria promedio:* " . number_format($promedioMemoria, 2) . "MB\n";
$mensaje .= "──────────────────────────────\n";
$mensaje .= "SophyFarm - Sistema Batch 2025";

$url = "https://api.callmebot.com/whatsapp.php?phone={$phone}&text=" . urlencode($mensaje) . "&apikey={$apikey}";

try {
    $response = file_get_contents($url);
    if (strpos($response, 'Message queued') !== false) {
        echo "WhatsApp enviado correctamente.<br>";
    } else {
        echo "No se pudo confirmar el envío. Respuesta: $response<br>";
    }
} catch (Exception $e) {
    echo "Error al enviar mensaje WhatsApp: " . $e->getMessage() . "<br>";
}
?>
