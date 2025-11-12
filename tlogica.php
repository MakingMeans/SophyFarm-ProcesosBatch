<?php
// ==========================================
// SophyFarm - Procesamiento de archivos batch
// PHP 8 y PHPMailer 6.x
// ==========================================
set_time_limit(0);
ini_set('memory_limit', '512M');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once 'db.class.php';
require_once __DIR__ . '/lib/PHPMailer/Exception.php';
require_once __DIR__ . '/lib/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/lib/PHPMailer/SMTP.php';
date_default_timezone_set('Etc/GMT+5');
class Logica {
    private $db;

    public function __construct() {
        $this->db = new Database();
    }

    public function cargarInformacion($rutaArchivo){

    $inicio = microtime(true);
    $memoriaInicial = memory_get_usage(true);

    try {
        if (!file_exists($rutaArchivo)) {
            throw new Exception("El archivo no existe: $rutaArchivo");
        }

        $archivo = fopen($rutaArchivo, "r");
        if (!$archivo) {
            throw new Exception("No se pudo abrir el archivo: $rutaArchivo");
        }

        $insertados = 0;
        $errores = 0;
        $total = 0;
        $erroresDetalle = [];
        $filasErroneas = [];

        $conn = $this->db->connect();
        $validUnidades = [];
        $validCategorias = [];

        try {
            $stmtU = $conn->query("SELECT id FROM unidad");
            foreach ($stmtU->fetchAll(PDO::FETCH_COLUMN, 0) as $val) {
                $validUnidades[] = intval($val);
            }
        } catch (Exception $e) {
            $validUnidades = [];
        }

        try {
            $stmtC = $conn->query("SELECT id FROM categoria");
            foreach ($stmtC->fetchAll(PDO::FETCH_COLUMN, 0) as $val) {
                $validCategorias[] = intval($val);
            }
        } catch (Exception $e) {
            $validCategorias = [];
        }

        $cabecera = fgetcsv($archivo);
        if ($cabecera === false) {
            fclose($archivo);
            throw new Exception("El archivo esta vacio o no tiene cabecera");
        }

        $expectedCols = 15;
        if (count($cabecera) < $expectedCols) {
            fclose($archivo);
            throw new Exception("La cabecera no tiene las columnas esperadas ($expectedCols)");
        }

        $regexCodigo = '/^PROD\d{6}$/';
        $regexNombre = '/^NOM_PROD\d+$/';
        $regexDescr  = '/^DES_PROD\d+$/';

        #$logDir = __DIR__ . '/logs/logs_txt';
        $errDir = __DIR__ . '/logs';
        #if (!file_exists($logDir)) mkdir($logDir, 0777, true);
        if (!file_exists($errDir)) mkdir($errDir, 0777, true);

        #$logPath = $logDir . '/errores_' . date('Ymd_His') . '.log';
        #$logFile = fopen($logPath, 'a');

        $sql = "INSERT INTO elemento (
                    codigo_elemnto, nmbre_elemnto, dscrpcion_elemnto, ctgria_elemnto, und_elemnto,
                    exstncia_elemnto, bdga_elemnto, precio_venta_ac, precio_venta_an,
                    costo_venta, mrgen_utldad, tiene_iva, stock_minimo, stock_maximo, estado
                ) VALUES (
                    :codigo, :nombre, :descripcion, :categoria, :unidad,
                    :existencia, :bodega, :precio_ac, :precio_an,
                    :costo, :margen, :tiene_iva, :stock_min, :stock_max, :estado
                )";
        $stmtInsert = $conn->prepare($sql);

        while (($fila = fgetcsv($archivo)) !== false) {
            $total++;
            if (count($fila) < $expectedCols) {
                $errores++;
                $msg = "Linea $total: columnas insuficientes (" . count($fila) . ")";
                $erroresDetalle[] = $msg;
                #fwrite($logFile, $msg . "\n");
                $fila[] = $msg;
                $filasErroneas[] = $fila;
                continue;
            }

            if (count($fila) === 16) {
                array_shift($fila);
            }

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

            if (!preg_match($regexCodigo, $codigo)) { $lineOk = false; $msgs[] = "formato de codigo invalido ($codigo)"; }
            if (!preg_match($regexNombre, $nombre)) { $lineOk = false; $msgs[] = "formato de nombre invalido ($nombre)"; }
            if (!preg_match($regexDescr, $descripcion)) { $lineOk = false; $msgs[] = "formato de descripcion invalido ($descripcion)"; }

            if ($codigo === '') { $lineOk = false; $msgs[] = "codigo vacio"; }
            if ($nombre === '') { $lineOk = false; $msgs[] = "nombre vacio"; }

            if ($precio_ac === null || $precio_ac < 0) { $lineOk = false; $msgs[] = "precio_venta_ac invalido"; }
            if ($precio_an === null || $precio_an < 0) { $lineOk = false; $msgs[] = "precio_venta_an invalido"; }
            if ($costo === null || $costo < 0) { $lineOk = false; $msgs[] = "costo_venta invalido"; }

            if ($existencia === null || $existencia < 0) { $lineOk = false; $msgs[] = "existencia invalida"; }
            if ($stock_min === null || $stock_min < 0) { $lineOk = false; $msgs[] = "stock_minimo invalido"; }
            if ($stock_max === null || $stock_max < 0) { $lineOk = false; $msgs[] = "stock_maximo invalido"; }

            if (!empty($validUnidades) && $unidad !== null && !in_array($unidad, $validUnidades, true)) {
                $lineOk = false; $msgs[] = "unidad no existe (id: $unidad)";
            }
            if (!empty($validCategorias) && $categoria !== null && !in_array($categoria, $validCategorias, true)) {
                $lineOk = false; $msgs[] = "categoria no existe (id: $categoria)";
            }

            if (!$lineOk) {
                $errores++;
                $detalleLinea = "Archivo: " . basename($rutaArchivo) .
                    " | Linea $total | Errores: " . implode("; ", $msgs) .
                    " | Datos: " . implode(",", $fila) . "\n";
                #fwrite($logFile, $detalleLinea);
                $erroresDetalle[] = "Linea $total: " . implode("; ", $msgs);
                $fila[] = implode("; ", $msgs);
                $filasErroneas[] = $fila;
                continue;
            }

            try {
                $stmtInsert->execute([
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
                $msg = "Linea $total: error BD: " . $e->getMessage();
                #fwrite($logFile, $msg . "\n");
                $erroresDetalle[] = $msg;
                $fila[] = $msg;
                $filasErroneas[] = $fila;
            }
        }

        fclose($archivo);
        #fclose($logFile);

        if (!empty($filasErroneas)) {
            $nombreErrorCSV = 'errores_' . basename($rutaArchivo);
            $pathErrorCSV = $errDir . '/' . $nombreErrorCSV;

            $csvError = fopen($pathErrorCSV, 'w');
            $cabecera[] = 'detalle_error';
            fputcsv($csvError, $cabecera);
            foreach ($filasErroneas as $fila) {
                fputcsv($csvError, $fila);
            }
            fclose($csvError);
        }

        $detalle = "";
        if (!empty($erroresDetalle)) {
            $detalle = implode("\n", array_slice($erroresDetalle, 0, 2000));
        }

        try {
            $stmtAudit = $conn->prepare("INSERT INTO auditoria (nombre_archivo, fecha_carga, registros_insertados, registros_fallidos, total_registros, detalle_error)
                                         VALUES (:nombre, NOW(), :ins, :fail, :total, :detalle)");
            $stmtAudit->execute([
                ":nombre" => basename($rutaArchivo),
                ":ins" => $insertados,
                ":fail" => $errores,
                ":total" => $total,
                ":detalle" => $detalle
            ]);
        } catch (PDOException $e) {
            $erroresDetalle[] = "Error al registrar auditoria: " . $e->getMessage();
        }

        $fin = microtime(true);
        $memoriaFinal = memory_get_usage(true);
        $memoriaPico = memory_get_peak_usage(true);

        $duracionFormateada = number_format($fin - $inicio, 2) . " segundos";
        $memoriaUsada = number_format(($memoriaFinal - $memoriaInicial) / 1048576, 2) . " MB";
        $memoriaPicoMB = number_format($memoriaPico / 1048576, 2) . " MB";

        $this->enviarDataCorreo(basename($rutaArchivo), $insertados, $errores, $total, $duracionFormateada, $memoriaUsada, $memoriaPicoMB);
        $this->enviarDataSMS(basename($rutaArchivo), $insertados, $errores, $total, $duracionFormateada, $memoriaUsada, $memoriaPicoMB);

        return [
            "ok" => true,
            "archivo" => basename($rutaArchivo),
            "insertados" => $insertados,
            "errores" => $errores,
            "total" => $total,
            "erroresDetalle" => $erroresDetalle,
            "tiempo" => $duracionFormateada,
            "memoria" => $memoriaUsada
        ];

    } catch (PDOException $e) {
        $msg = "Error en la base de datos: " . $e->getMessage();
        file_put_contents(__DIR__ . '/logs/error_global.log', "[" . date("Y-m-d H:i:s") . "] $msg\n", FILE_APPEND);
        return ["ok" => false, "msg" => $msg];
    } catch (Exception $e) {
        $msg = "Error general en la carga: " . $e->getMessage();
        file_put_contents(__DIR__ . '/logs/error_global.log', "[" . date("Y-m-d H:i:s") . "] $msg\n", FILE_APPEND);
        return ["ok" => false, "msg" => $msg];
    }
}


private function enviarDataCorreo($nombreFile, $cantidadInsertados, $cantidadErrores, $cantidadTotal, $duracion, $memoriaUsada, $memoriaPico) {
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'santorinipio@gmail.com';
        $mail->Password = 'ewfe zrvw wmqq pmsi';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom('santorinipio@gmail.com', 'SophyFarm Taller');
        $mail->addAddress('santorinipio@gmail.com', 'Me');

        $mail->isHTML(true);
        $mail->Subject = "Carga completada - SophyFarm: $nombreFile";

        $mail->Body = "
        <div style='font-family: Arial, Helvetica, sans-serif; background-color:#f7f9fc; padding:25px;'>
          <div style='max-width:600px; margin:auto; background:#fff; border-radius:8px; box-shadow:0 2px 8px rgba(0,0,0,0.1); padding:25px;'>
            
            <div style='text-align:center;'>
              <h2 style='color:#2a7ae2; margin-bottom:5px;'>SophyFarm - Resumen de carga</h2>
              <p style='color:#666; font-size:14px; margin-top:0;'>Archivo procesado correctamente</p>
            </div>

            <hr style='border:none; height:1px; background:#e0e0e0; margin:20px 0;'>

            <table style='width:100%; border-collapse:collapse; font-size:15px;'>
              <tr><td><strong>📁 Archivo:</strong></td><td style='text-align:right;'>$nombreFile</td></tr>
              <tr><td><strong>+ Total registros:</strong></td><td style='text-align:right;'>$cantidadTotal</td></tr>
              <tr><td><strong>- Exitosos:</strong></td><td style='text-align:right;'>$cantidadInsertados</td></tr>
              <tr><td><strong>- Fallidos:</strong></td><td style='text-align:right;'>$cantidadErrores</td></tr>
              <tr><td><strong>- Fecha de proceso:</strong></td><td style='text-align:right;'>" . date("d/m/Y H:i:s") . " (UTC-5)</td></tr>
              <tr><td><strong>- Duracion total:</strong></td><td style='text-align:right;'>$duracion</td></tr>
              <tr><td><strong>- Pico de memoria:</strong></td><td style='text-align:right;'>$memoriaPico</td></tr>
            </table>

            <hr style='border:none; height:1px; background:#e0e0e0; margin:20px 0;'>

            <div style='text-align:center;'>
              <p style='font-size:14px; color:#555;'>
                Sistema de carga automatica de <strong>SophyFarm</strong>.<br>
              </p>
              <p style='font-size:12px; color:#999; margin-top:15px;'>
                © " . date("Y") . " SophyFarm — David Santiago Garcia Preciado
              </p>
            </div>

          </div>
        </div>";

        $mail->send();
        echo "Correo enviado correctamente.<br>";

    } catch (Exception $e) {
        echo "No se pudo enviar el correo.<br>";
        echo "ErrorInfo: " . $mail->ErrorInfo . "<br>";
        echo "Exception: " . $e->getMessage() . "<br>";
    }
}

private function enviarDataSMS($nombreFile, $cantidadInsertados, $cantidadErrores, $cantidadTotal, $duracion, $memoriaUsada, $memoriaPico) {
        $apikey = '7511846';
        $phone  = '+573173615539';

        $mensaje = "📦 *SophyFarm - Carga completada*\n"
             . "──────────────────────────────\n"
             . "+ *Archivo:* $nombreFile\n"
             . "- *Total:* $cantidadTotal\n"
             . "- *Exitosos:* $cantidadInsertados\n"
             . "- *Fallidos:* $cantidadErrores\n"
             . "- *Duracion:* $duracion\n"
             . "- *Memoria pico:* $memoriaPico\n"
             . "- *Hora:* " . date("d/m/Y H:i:s") . " (UTC-5)\n"
             . "──────────────────────────────\n"
             . "_SophyFarm - Sistema Batch 2025_";

        $url = "https://api.callmebot.com/whatsapp.php?phone={$phone}&text=" . urlencode($mensaje) . "&apikey={$apikey}";

        try {
            $response = file_get_contents($url);
            if (strpos($response, 'Message queued') !== false) {
                echo "Mensaje de WhatsApp enviado correctamente.<br>";
            } else {
                echo "No se pudo confirmar el envio del mensaje. Respuesta: $response<br>";
            }
        } catch (Exception $e) {
            echo "Error al enviar mensaje WhatsApp: " . $e->getMessage() . "<br>";
        }
    }

}
?>
