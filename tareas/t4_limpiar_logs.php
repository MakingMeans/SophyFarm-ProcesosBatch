<?php
require_once __DIR__ . '/../config_reader.php';
$config = leerPropiedades(__DIR__ . '/../config.properties');
$rutaJsons = $config['ruta_jsons'];

$jsonFiles = glob("$rutaJsons/temp_ejec_*.json");
if (empty($jsonFiles)) {
    echo "No hay archivos JSON temporales para eliminar.<br>";
    exit;
}

$contador = 0;
foreach ($jsonFiles as $json) {
    if (unlink($json)) $contador++;
}
echo "Limpieza completada. Se eliminaron $contador archivos JSON temporales.<br>";
?>
