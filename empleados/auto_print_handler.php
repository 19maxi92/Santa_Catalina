<?php
// empleados/auto_print_handler.php - Manejo de impresión automática
require_once '../config.php';
requireLogin();

header('Content-Type: application/json');
$pdo = getConnection();

// Verificar si hay nuevos pedidos para imprimir
if (isset($_GET['check_new']) && $_GET['check_new'] == '1') {
    $stmt = $pdo->prepare("
        SELECT id, nombre, apellido, producto, created_at
        FROM pedidos 
        WHERE ubicacion = 'Local 1' 
        AND estado = 'Pendiente' 
        AND DATE(created_at) = CURDATE()
        AND (impreso_auto IS NULL OR impreso_auto = 0)
        AND created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
        ORDER BY created_at DESC
    ");
    $stmt->execute();
    $nuevos_pedidos = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'new_orders' => $nuevos_pedidos,
        'count' => count($nuevos_pedidos),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}

// Marcar pedido como impreso automáticamente
if (isset($_POST['marcar_impreso']) && isset($_POST['pedido_id'])) {
    $pedido_id = (int)$_POST['pedido_id'];
    
    try {
        // Actualizar tabla para marcar como impreso
        $stmt = $pdo->prepare("
            UPDATE pedidos 
            SET impreso_auto = 1, 
                fecha_impreso = NOW() 
            WHERE id = ? AND ubicacion = 'Local 1'
        ");
        $stmt->execute([$pedido_id]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode([
                'success' => true,
                'message' => 'Pedido marcado como impreso',
                'pedido_id' => $pedido_id
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'No se pudo marcar el pedido'
            ]);
        }
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ]);
    }
    exit;
}

// Obtener estadísticas de impresión
if (isset($_GET['stats'])) {
    try {
        $stmt = $pdo->query("
            SELECT 
                COUNT(*) as total_local1,
                SUM(CASE WHEN impreso_auto = 1 THEN 1 ELSE 0 END) as impresos_auto,
                SUM(CASE WHEN impreso_auto IS NULL OR impreso_auto = 0 THEN 1 ELSE 0 END) as pendientes_impresion
            FROM pedidos 
            WHERE ubicacion = 'Local 1' 
            AND DATE(created_at) = CURDATE()
        ");
        $stats = $stmt->fetch();
        
        echo json_encode([
            'success' => true,
            'stats' => $stats,
            'printer_info' => [
                'model' => 'POS80-CX',
                'connection' => 'USB+WiFi',
                'mac_address' => 'C0-25-E9-14-50-03',
                'status' => 'connected'
            ]
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error obteniendo estadísticas: ' . $e->getMessage()
        ]);
    }
    exit;
}

// Verificar estado de la impresora
if (isset($_GET['printer_status'])) {
    // Simulación de verificación de impresora por MAC
    $mac_local1 = 'C0-25-E9-14-50-03';
    $ip_local1 = '192.168.1.41';
    
    // En un entorno real, aquí harías ping o verificación de conexión
    $printer_online = true; // Simulado
    
    echo json_encode([
        'success' => true,
        'printer' => [
            'model' => 'POS80-CX',
            'mac' => $mac_local1,
            'ip' => $ip_local1,
            'online' => $printer_online,
            'location' => 'Local 1',
            'last_print' => date('Y-m-d H:i:s')
        ]
    ]);
    exit;
}

// Respuesta por defecto
echo json_encode([
    'success' => false,
    'message' => 'Acción no válida'
]);
?>