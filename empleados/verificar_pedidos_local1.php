<?php
/*
=== VERIFICADOR SIMPLE DE PEDIDOS LOCAL 1 ===
Solo verifica si hay pedidos nuevos de Local 1 para PCs vinculadas
*/

header('Content-Type: application/json');
session_start();
require_once '../admin/config.php';

// Verificar que esté logueado
if (!isset($_SESSION['empleado_logged']) || $_SESSION['empleado_logged'] !== true) {
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

// Verificar que la PC esté vinculada
$pc_vinculada = (isset($_COOKIE['PC_LOCAL1_VINCULADA']) && $_COOKIE['PC_LOCAL1_VINCULADA'] === 'true') || 
                (isset($_SESSION['pc_local1_vinculada']) && $_SESSION['pc_local1_vinculada'] === true);

if (!$pc_vinculada) {
    echo json_encode(['error' => 'PC no vinculada', 'nuevos_pedidos' => []]);
    exit;
}

try {
    $pdo = getConnection();
    
    // Buscar pedidos de Local 1 de los últimos 3 minutos que están "Pendientes"
    $stmt = $pdo->prepare("
        SELECT id, nombre, apellido, producto, created_at,
               TIMESTAMPDIFF(MINUTE, created_at, NOW()) as minutos_desde_creacion
        FROM pedidos 
        WHERE ubicacion = 'Local 1' 
          AND estado = 'Pendiente'
          AND created_at >= DATE_SUB(NOW(), INTERVAL 3 MINUTE)
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $stmt->execute();
    $pedidos_recientes = $stmt->fetchAll();
    
    // Simular que todos los pedidos muy recientes (últimos 2 minutos) son "nuevos"
    $pedidos_nuevos = array_filter($pedidos_recientes, function($pedido) {
        return $pedido['minutos_desde_creacion'] <= 2;
    });
    
    echo json_encode([
        'success' => true,
        'nuevos_pedidos' => array_values($pedidos_nuevos),
        'cantidad' => count($pedidos_nuevos),
        'timestamp' => date('Y-m-d H:i:s'),
        'pc_vinculada' => true,
        'total_verificados' => count($pedidos_recientes)
    ]);
    
} catch (PDOException $e) {
    error_log("VERIFICAR PEDIDOS LOCAL1 ERROR: " . $e->getMessage());
    echo json_encode([
        'error' => 'Error de base de datos',
        'nuevos_pedidos' => []
    ]);
}
?>