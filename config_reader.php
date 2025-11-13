<?php
function leerPropiedades($ruta) {
    if (!file_exists($ruta)) {
        throw new Exception("Archivo de propiedades no encontrado: $ruta");
    }

    $propiedades = [];
    foreach (file($ruta, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linea) {
        if (str_starts_with(trim($linea), '#')) continue;
        [$clave, $valor] = array_pad(explode('=', trim($linea), 2), 2, '');
        $propiedades[$clave] = trim($valor);
    }

    return $propiedades;
}
?>
