<?php
session_start();
require_once 'tlogica.php';

$logica = new Logica();


$carpetaArchivos = __DIR__ . "/archivos";
$archivos = glob($carpetaArchivos . "/*.csv");

echo "<h2>Proceso de importacion Batch</h2>";

if (empty($archivos)) {
    echo "<p>No se encontraron archivos .csv en la carpeta <b>archivos/</b>.</p>";
    exit;
}

foreach ($archivos as $archivo) {
    echo "<hr>";
    echo "<h3>Procesando archivo: " . basename($archivo) . "</h3>";

    $resultado = $logica->cargarInformacion($archivo);

    if ($resultado["ok"]) {
        echo "<p>Archivo procesado correctamente.</p>";
        echo "<ul>";
        echo "<li><b>Total:</b> {$resultado['total']}</li>";
        echo "<li><b>Registros exitosos:</b> {$resultado['insertados']}</li>";
        echo "<li><b>Registros no exitosos:</b> {$resultado['errores']}</li>";
        echo "</ul>";
    } else {
        echo "<p>Error: {$resultado['msg']}</p>";
    }
}

echo "<hr><b>Proceso completado.</b><br>";
?>
