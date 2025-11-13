<?php
require_once __DIR__ . '/../db.class.php';
require_once __DIR__ . '/../config_reader.php';

$config = leerPropiedades(__DIR__ . '/../config.properties');
$rutaArchivos = $config['ruta_archivos'] ?? (__DIR__ . '/../archivos');
$rutaJsons = $config['ruta_jsons'] ?? (__DIR__ . '/../logs/temp');
$rutaLogs = $config['ruta_logs'] ?? (__DIR__ . '/../logs');
$method = strtolower(trim($config['method'] ?? 'selectivo'));

date_default_timezone_set('Etc/GMT+5');
$db = new Database();
$conn = $db->connect();

$archivos = glob("$rutaArchivos/*.csv");
if (empty($archivos)) {
    echo "No se encontraron archivos CSV en $rutaArchivos<br>";
    exit;
}

$resumenEjecucion = [
    'fecha' => date("Y-m-d H:i:s"),
    'method' => $method,
    'csvs' => []
];

// Cargar IDs válidos de unidad y categoría
try {
    $validUnidades = $conn->query("SELECT id FROM unidad")->fetchAll(PDO::FETCH_COLUMN, 0);
    $validCategorias = $conn->query("SELECT id FROM categoria")->fetchAll(PDO::FETCH_COLUMN, 0);
} catch (Exception $e) {
    $validUnidades = [];
    $validCategorias = [];
}

$regexCodigo = '/^PROD\d{6}$/';
$regexNombre = '/^NOM_PROD\d+$/';
$regexDescr  = '/^DES_PROD\d+$/';

foreach ($archivos as $rutaArchivo) {
    echo "<h3>Procesando archivo: " . basename($rutaArchivo) . "</h3>";

    $inicio = microtime(true);
    $memInicial = memory_get_usage(true);
    $insertados = 0;
    $errores = 0;
    $total = 0;
    $filasErroneas = [];

    $archivo = fopen($rutaArchivo, 'r');
    if (!$archivo) {
        echo "No se pudo abrir el archivo.<br>";
        continue;
    }

    $cabecera = fgetcsv($archivo);
    if (!$cabecera) {
        echo "El archivo está vacío.<br>";
        fclose($archivo);
        continue;
    }

    $expectedCols = 15;
    $conn->beginTransaction();

    $stmt = $conn->prepare("INSERT INTO elemento (
        codigo_elemnto, nmbre_elemnto, dscrpcion_elemnto, ctgria_elemnto, und_elemnto,
        exstncia_elemnto, bdga_elemnto, precio_venta_ac, precio_venta_an,
        costo_venta, mrgen_utldad, tiene_iva, stock_minimo, stock_maximo, estado
    ) VALUES (
        :codigo, :nombre, :descripcion, :categoria, :unidad,
        :existencia, :bodega, :precio_ac, :precio_an,
        :costo, :margen, :tiene_iva, :stock_min, :stock_max, :estado
    )");

    while (($fila = fgetcsv($archivo)) !== false) {
        $total++;

        // Validar cantidad de columnas
        if (count($fila) < $expectedCols) {
            $errores++;
            $fila[] = "Columnas insuficientes (" . count($fila) . ")";
            $filasErroneas[] = $fila;
            if ($method === 'transaccional') break;
            continue;
        }

        // Mapear variables
        $codigo     = trim($fila[0]);
        $nombre     = trim($fila[1]);
        $descripcion= trim($fila[2]);
        $categoria  = is_numeric($fila[3]) ? intval($fila[3]) : null;
        $unidad     = is_numeric($fila[4]) ? intval($fila[4]) : null;
        $existencia = is_numeric($fila[5]) ? intval($fila[5]) : null;
        $bodega     = is_numeric($fila[6]) ? intval($fila[6]) : null;
        $precio_ac  = is_numeric($fila[7]) ? floatval($fila[7]) : null;
        $precio_an  = is_numeric($fila[8]) ? floatval($fila[8]) : null;
        $costo      = is_numeric($fila[9]) ? floatval($fila[9]) : null;
        $margen     = is_numeric($fila[10]) ? floatval($fila[10]) : null;
        $tiene_iva  = trim($fila[11]);
        $stock_min  = is_numeric($fila[12]) ? intval($fila[12]) : null;
        $stock_max  = is_numeric($fila[13]) ? intval($fila[13]) : null;
        $estado     = trim($fila[14]);

        $lineOk = true;
        $msgs = [];

        // Validaciones de formato
        if (!preg_match($regexCodigo, $codigo)) { $lineOk = false; $msgs[] = "formato de codigo invalido ($codigo)"; }
        if (!preg_match($regexNombre, $nombre)) { $lineOk = false; $msgs[] = "formato de nombre invalido ($nombre)"; }
        if (!preg_match($regexDescr, $descripcion)) { $lineOk = false; $msgs[] = "formato de descripcion invalido ($descripcion)"; }

        // Campos vacíos o inválidos
        if ($codigo === '') { $lineOk = false; $msgs[] = "codigo vacio"; }
        if ($nombre === '') { $lineOk = false; $msgs[] = "nombre vacio"; }

        if ($precio_ac === null || $precio_ac < 0) { $lineOk = false; $msgs[] = "precio_venta_ac invalido"; }
        if ($precio_an === null || $precio_an < 0) { $lineOk = false; $msgs[] = "precio_venta_an invalido"; }
        if ($costo === null || $costo < 0) { $lineOk = false; $msgs[] = "costo_venta invalido"; }

        if ($existencia === null || $existencia < 0) { $lineOk = false; $msgs[] = "existencia invalida"; }
        if ($stock_min === null || $stock_min < 0) { $lineOk = false; $msgs[] = "stock_minimo invalido"; }
        if ($stock_max === null || $stock_max < 0) { $lineOk = false; $msgs[] = "stock_maximo invalido"; }

        // Verificar existencia en tablas relacionadas
        if (!empty($validUnidades) && $unidad !== null && !in_array($unidad, $validUnidades, true)) {
            $lineOk = false; $msgs[] = "unidad no existe (id: $unidad)";
        }
        if (!empty($validCategorias) && $categoria !== null && !in_array($categoria, $validCategorias, true)) {
            $lineOk = false; $msgs[] = "categoria no existe (id: $categoria)";
        }

        if (!$lineOk) {
            $errores++;
            $fila[] = implode("; ", $msgs);
            $filasErroneas[] = $fila;
            if ($method === 'transaccional') break;
            continue;
        }

        try {
            $stmt->execute([
                ":codigo" => $codigo,
                ":nombre" => $nombre,
                ":descripcion" => $descripcion,
                ":categoria" => $categoria,
                ":unidad" => $unidad,
                ":existencia" => $existencia,
                ":bodega" => $bodega,
                ":precio_ac" => $precio_ac,
                ":precio_an" => $precio_an,
                ":costo" => $costo,
                ":margen" => $margen,
                ":tiene_iva" => $tiene_iva,
                ":stock_min" => $stock_min,
                ":stock_max" => $stock_max,
                ":estado" => $estado,
            ]);
            $insertados++;
        } catch (PDOException $e) {
            $errores++;
            $fila[] = "Error BD: " . $e->getMessage();
            $filasErroneas[] = $fila;
            if ($method === 'transaccional') break;
        }
    }

    fclose($archivo);

    // Rollback si transaccional y hubo errores
    if ($method === 'transaccional' && $errores > 0) {
        $conn->rollBack();
        $status = "ROLLBACK";
        echo "Errores detectados. Se realizó rollback total.<br>";
    } else {
        $conn->commit();
        $status = ($errores > 0) ? "PARCIAL" : "OK";
    }

    // CSV de errores
    if (!empty($filasErroneas)) {
        $nombreErrorCSV = 'errors_' . basename($rutaArchivo);
        $pathErrorCSV = "$rutaLogs/$nombreErrorCSV";
        $csvError = fopen($pathErrorCSV, 'w');
        $cabecera[] = 'detalle_error';
        fputcsv($csvError, $cabecera);
        foreach ($filasErroneas as $filaErr) {
            fputcsv($csvError, $filaErr);
        }
        fclose($csvError);
        echo "Archivo de errores generado: $nombreErrorCSV<br>";
    }

    // Auditoría
    $stmtAudit = $conn->prepare("
        INSERT INTO auditoria (nombre_archivo, fecha_carga, registros_insertados, registros_fallidos, total_registros, detalle_error)
        VALUES (:nombre, NOW(), :ins, :fail, :total, :detalle)
    ");
    $stmtAudit->execute([
        ":nombre" => basename($rutaArchivo),
        ":ins" => $insertados,
        ":fail" => $errores,
        ":total" => $total,
        ":detalle" => ($errores > 0 ? "Ver errors_" . basename($rutaArchivo) : "Sin errores")
    ]);

    $duracion = number_format(microtime(true) - $inicio, 2);
    $memoriaPico = number_format(memory_get_peak_usage(true) / 1048576, 2);

    $resumenEjecucion['csvs'][] = [
        'archivo' => basename($rutaArchivo),
        'insertados' => $insertados,
        'errores' => $errores,
        'total' => $total,
        'duracion' => $duracion,
        'pico' => $memoriaPico,
        'status' => $status
    ];

    echo "Finalizado " . basename($rutaArchivo) . " — Exitosos: $insertados / Fallidos: $errores / Estado: $status<br><br>";
}

// Guardar resumen JSON
$nombreEjecucion = 'temp_ejec_' . date('Ymd_His') . '.json';
file_put_contents("$rutaJsons/$nombreEjecucion", json_encode($resumenEjecucion, JSON_PRETTY_PRINT));

echo "Ejecución completada. Resumen guardado como $nombreEjecucion<br>";
?>
