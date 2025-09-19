<?php
// api_orders.php - API para obtener pedidos del Local 1
// Subir este archivo a la raíz de tu sitio web

require_once 'config.php';

// Headers para API
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// Función para responder con error
function sendError($message, $code = 400) {
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

// Función para responder con éxito
function sendSuccess($data) {
    echo json_encode(['success' => true, 'data' => $data, 'timestamp' => date('Y-m-d H:i:s')]);
}

try {
    $pdo = getConnection();
    
    // Verificar qué acción se solicita
    $action = $_GET['action'] ?? 'get_orders';
    
    switch ($action) {
        case 'get_orders':
            // Obtener pedidos nuevos del Local 1
            $stmt = $pdo->prepare("
                SELECT 
                    id, 
                    nombre, 
                    apellido, 
                    producto, 
                    precio,
                    telefono,
                    observaciones,
                    created_at,
                    estado,
                    ubicacion
                FROM pedidos 
                WHERE ubicacion = 'Local 1' 
                AND estado = 'Pendiente' 
                AND DATE(created_at) = CURDATE()
                AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
                ORDER BY created_at DESC
            ");
            
            $stmt->execute();
            $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Agregar información adicional
            $response = [
                'orders' => $orders,
                'count' => count($orders),
                'last_check' => date('Y-m-d H:i:s'),
                'server_time' => date('Y-m-d H:i:s')
            ];
            
            sendSuccess($response);
            break;
            
        case 'mark_printed':
            // Marcar pedido como impreso
            if (!isset($_POST['order_id'])) {
                sendError('order_id requerido');
            }
            
            $order_id = (int)$_POST['order_id'];
            
            // Verificar si las columnas existen
            $stmt = $pdo->query("SHOW COLUMNS FROM pedidos LIKE 'impreso_auto'");
            if ($stmt->rowCount() == 0) {
                // Crear columnas si no existen
                $pdo->exec("ALTER TABLE pedidos ADD COLUMN impreso_auto BOOLEAN DEFAULT FALSE");
                $pdo->exec("ALTER TABLE pedidos ADD COLUMN fecha_impreso DATETIME NULL");
            }
            
            // Marcar como impreso
            $stmt = $pdo->prepare("
                UPDATE pedidos 
                SET impreso_auto = 1, fecha_impreso = NOW() 
                WHERE id = ? AND ubicacion = 'Local 1'
            ");
            $success = $stmt->execute([$order_id]);
            
            if ($success && $stmt->rowCount() > 0) {
                sendSuccess(['order_id' => $order_id, 'marked' => true]);
            } else {
                sendError('No se pudo marcar el pedido como impreso');
            }
            break;
            
        case 'get_stats':
            // Obtener estadísticas del día
            $stmt = $pdo->query("
                SELECT 
                    COUNT(*) as total_hoy,
                    SUM(CASE WHEN ubicacion = 'Local 1' THEN 1 ELSE 0 END) as local1_hoy,
                    SUM(CASE WHEN ubicacion = 'Local 1' AND estado = 'Pendiente' THEN 1 ELSE 0 END) as local1_pendientes,
                    SUM(CASE WHEN ubicacion = 'Local 1' AND impreso_auto = 1 THEN 1 ELSE 0 END) as local1_impresos
                FROM pedidos 
                WHERE DATE(created_at) = CURDATE()
            ");
            
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);
            sendSuccess($stats);
            break;
            
        case 'test':
            // Test de conectividad
            $test_result = [
                'connection' => 'OK',
                'server_time' => date('Y-m-d H:i:s'),
                'php_version' => PHP_VERSION,
                'database' => DB_NAME
            ];
            
            sendSuccess($test_result);
            break;
            
        default:
            sendError('Acción no válida');
    }
    
} catch (Exception $e) {
    sendError('Error del servidor: ' . $e->getMessage(), 500);
}
?>