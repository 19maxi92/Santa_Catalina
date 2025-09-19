<?php
/*
=== VERIFICADOR DE AUTO-IMPRESIÓN PARA LOCAL 1 ===
Este script se ejecuta cada 5 segundos desde el dashboard Local 1
Detecta pedidos nuevos de Local 1 y simula envío automático a impresión
*/

header('Content-Type: application/json');
session_start();
require_once '../admin/config.php';

// Verificar autenticación y permisos
if (!isset($_SESSION['empleado_logged']) || !isset($_SESSION['empleado_rol']) || $_SESSION['empleado_rol'] !== 'local1') {
    echo json_encode(['error' => 'No autorizado', 'nuevos_pedidos' => 0]);
    exit;
}

// Verificar que es estación Local 1 autorizada
function esEstacionLocal1() {
    return (
        isset($_COOKIE['ESTACION_LOCAL1']) && $_COOKIE['ESTACION_LOCAL1'] === 'true' &&
        isset($_SESSION['estacion_tipo']) && $_SESSION['estacion_tipo'] === 'LOCAL1' &&
        isset($_SESSION['auto_impresion']) && $_SESSION['auto_impresion'] === true
    );
}

if (!esEstacionLocal1()) {
    echo json_encode(['error' => 'Estación no autorizada', 'nuevos_pedidos' => 0]);
    exit;
}

try {
    $pdo = getConnection();
    
    // Buscar pedidos de Local 1 que podrían necesitar impresión automática
    // Por ahora, simulamos la lógica ya que no tenemos la tabla log_auto_impresion
    $stmt = $pdo->prepare("
        SELECT id, nombre, apellido, producto, telefono, modalidad, 
               precio, forma_pago, observaciones, created_at,
               TIMESTAMPDIFF(MINUTE, created_at, NOW()) as minutos_espera
        FROM pedidos 
        WHERE ubicacion = 'Local 1' 
          AND estado IN ('Pendiente', 'Preparando')
          AND DATE(created_at) = CURDATE()
          AND created_at >= DATE_SUB(NOW(), INTERVAL 2 MINUTE)
        ORDER BY created_at ASC
        LIMIT 3
    ");
    $stmt->execute();
    $pedidos_recientes = $stmt->fetchAll();
    
    $comandas_procesadas = 0;
    $errores = [];
    
    foreach ($pedidos_recientes as $pedido) {
        try {
            // Simular procesamiento de auto-impresión
            $resultado_impresion = simularAutoImpresion($pedido);
            
            if ($resultado_impresion['exitoso']) {
                $comandas_procesadas++;
                
                // Log simple en archivo (ya que no tenemos tabla de logs por ahora)
                error_log("AUTO-IMPRESION LOCAL1: Pedido #{$pedido['id']} procesado - " . date('Y-m-d H:i:s'));
                
            } else {
                $errores[] = "Pedido #{$pedido['id']}: " . $resultado_impresion['error'];
            }
            
        } catch (Exception $e) {
            $errores[] = "Pedido #{$pedido['id']}: " . $e->getMessage();
            error_log("AUTO-IMPRESION ERROR: " . $e->getMessage());
        }
    }
    
    // Preparar respuesta
    $respuesta = [
        'success' => true,
        'nuevos_pedidos' => $comandas_procesadas,
        'pedidos_verificados' => count($pedidos_recientes),
        'timestamp' => date('Y-m-d H:i:s'),
        'estacion' => $_SESSION['pc_identificador'] ?? 'LOCAL1'
    ];
    
    if (!empty($errores)) {
        $respuesta['errores'] = $errores;
        $respuesta['tiene_errores'] = true;
    }
    
    echo json_encode($respuesta);
    
} catch (PDOException $e) {
    error_log("AUTO-IMPRESION DB ERROR: " . $e->getMessage());
    echo json_encode([
        'error' => 'Error de base de datos',
        'nuevos_pedidos' => 0,
        'mensaje' => 'Error interno del sistema'
    ]);
}

/**
 * Función que simula el envío a impresión automática
 * En un entorno real, aquí se conectaría con la impresora POS80-CX
 */
function simularAutoImpresion($pedido) {
    try {
        // SIMULACIÓN: En desarrollo, solo registramos que se "imprimiría"
        // En producción, aquí iría la conexión real con la impresora
        
        // Simulamos un 95% de éxito
        $exito = (rand(1, 100) <= 95);
        
        if ($exito) {
            // Simular impresión exitosa
            return [
                'exitoso' => true,
                'mensaje' => 'Comanda enviada a POS80-CX',
                'metodo' => 'simulado',
                'timestamp' => date('H:i:s')
            ];
        } else {
            // Simular error ocasional
            return [
                'exitoso' => false,
                'error' => 'Error de comunicación con impresora'
            ];
        }
        
        /* 
        EJEMPLO PARA INTEGRACIÓN REAL CON IMPRESORA:
        
        // Generar URL de la comanda
        $url_comanda = "../admin/modules/impresion/comanda.php?pedido={$pedido['id']}&auto=1";
        
        // En Linux, podrías usar algo como:
        $comando_impresion = "firefox --print --print-to-filename=/tmp/comanda_{$pedido['id']}.pdf '$url_comanda'";
        exec($comando_impresion, $output, $return_code);
        
        // O enviar directamente a impresora:
        $comando_directo = "lp -d pos80cx /tmp/comanda_{$pedido['id']}.pdf";
        exec($comando_directo, $output2, $return_code2);
        
        if ($return_code === 0 && $return_code2 === 0) {
            return ['exitoso' => true];
        } else {
            return ['exitoso' => false, 'error' => 'Error en comandos: ' . implode(' ', $output)];
        }
        */
        
    } catch (Exception $e) {
        return [
            'exitoso' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Función para obtener configuración de impresión específica de Local 1
 */
function obtenerConfiguracionImpresion() {
    return [
        'impresora' => 'POS80-CX',
        'modelo' => 'BVVU2410240055',
        'ancho_papel' => '80mm',
        'interface' => 'USB+WIFI',
        'comando_soporte' => 'ESC/POS',
        'velocidad' => '230mm/s',
        'modo_corte' => 'automatico',
        'densidad' => 'media',
        'ubicacion' => 'Local 1 - Mostrador'
    ];
}

/**
 * Función para registrar estadísticas de impresión
 */
function registrarEstadistica($tipo, $cantidad = 1) {
    $log_file = '../logs/auto_impresion_' . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $usuario = $_SESSION['empleado_name'] ?? 'unknown';
    
    $mensaje = "[$timestamp] LOCAL1 - $tipo: $cantidad (Usuario: $usuario, IP: $ip)";
    
    // Crear directorio de logs si no existe
    $log_dir = dirname($log_file);
    if (!is_dir($log_dir)) {
        @mkdir($log_dir, 0755, true);
    }
    
    // Escribir log
    @file_put_contents($log_file, $mensaje . PHP_EOL, FILE_APPEND | LOCK_EX);
}

// Registrar que se ejecutó la verificación
registrarEstadistica('Verificacion ejecutada');

// Para debugging - no incluir en producción
if (isset($_GET['debug']) && $_GET['debug'] === '1') {
    $debug_info = [
        'session_empleado' => $_SESSION['empleado_name'] ?? 'null',
        'session_rol' => $_SESSION['empleado_rol'] ?? 'null',
        'estacion_tipo' => $_SESSION['estacion_tipo'] ?? 'null',
        'auto_impresion' => $_SESSION['auto_impresion'] ?? 'null',
        'pc_identificador' => $_SESSION['pc_identificador'] ?? 'null',
        'cookies' => $_COOKIE,
        'configuracion_impresora' => obtenerConfiguracionImpresion(),
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    error_log("DEBUG AUTO-IMPRESION LOCAL1: " . json_encode($debug_info));
    
    // Si se solicita debug, incluir información adicional en la respuesta
    if (isset($respuesta)) {
        $respuesta['debug'] = $debug_info;
    }
}
?>