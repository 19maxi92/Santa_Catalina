<?php
/*
=== PROCESADOR DE PEDIDOS EXPRESS - LOCAL 1 ===
Procesa pedidos rápidos creados desde el dashboard de empleados
Optimizado para atención presencial en el local
*/

header('Content-Type: application/json');
session_start();
require_once '../admin/config.php';

// Verificar autenticación de empleado
if (!isset($_SESSION['empleado_logged']) || $_SESSION['empleado_logged'] !== true) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

// Solo aceptar POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

try {
    $pdo = getConnection();
    
    // Obtener datos JSON
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        throw new Exception('Datos JSON inválidos');
    }
    
    // Validar campos obligatorios
    $required_fields = ['nombre', 'apellido', 'modalidad', 'forma_pago', 'tipo_pedido', 'precio'];
    foreach ($required_fields as $field) {
        if (!isset($data[$field]) || empty(trim($data[$field]))) {
            throw new Exception("Campo obligatorio faltante: $field");
        }
    }
    
    // Sanitizar datos
    $nombre = trim($data['nombre']);
    $apellido = trim($data['apellido']);
    $telefono = trim($data['telefono'] ?? '');
    $modalidad = $data['modalidad'];
    $forma_pago = $data['forma_pago'];
    $observaciones = trim($data['observaciones'] ?? '');
    $tipo_pedido = $data['tipo_pedido'];
    $precio = (float)$data['precio'];
    $producto = $data['producto'] ?? '';
    $cantidad = (int)($data['cantidad'] ?? 1);
    
    // Validaciones específicas
    if (!in_array($modalidad, ['Retiro', 'Delivery'])) {
        throw new Exception('Modalidad inválida');
    }
    
    if (!in_array($forma_pago, ['Efectivo', 'Transferencia', 'Tarjeta', 'MercadoPago'])) {
        throw new Exception('Forma de pago inválida');
    }
    
    if ($precio <= 0) {
        throw new Exception('Precio inválido');
    }
    
    // Determinar producto si no está definido
    if (empty($producto)) {
        $productos_predefinidos = [
            'jyq24' => 'Jamón y Queso x24',
            'jyq48' => 'Jamón y Queso x48',
            'surtido_clasico48' => 'Surtido Clásico x48',
            'surtido_especial48' => 'Surtido Especial x48'
        ];
        
        $producto = $productos_predefinidos[$tipo_pedido] ?? 'Personalizado';
    }
    
    // Agregar información del empleado a observaciones
    $empleado_info = "Pedido Express - Empleado ID: " . $_SESSION['empleado_id'];
    if (isset($_SESSION['empleado_nombre'])) {
        $empleado_info .= " (" . $_SESSION['empleado_nombre'] . ")";
    }
    $observaciones = trim($observaciones . "\n" . $empleado_info);
    
    // Si es personalizado y tiene sabores, agregarlos
    if (isset($data['sabores']) && is_array($data['sabores']) && count($data['sabores']) > 0) {
        $observaciones .= "\nSabores seleccionados: " . implode(', ', $data['sabores']);
    }
    
    // Insertar en base de datos
    $stmt = $pdo->prepare("
        INSERT INTO pedidos (
            nombre, apellido, telefono, producto, cantidad, precio, 
            modalidad, forma_pago, ubicacion, estado, observaciones,
            fecha_pedido, created_at
        ) VALUES (
            ?, ?, ?, ?, ?, ?, 
            ?, ?, 'Local 1', 'Pendiente', ?,
            NOW(), NOW()
        )
    ");
    
    $result = $stmt->execute([
        $nombre, $apellido, $telefono, $producto, $cantidad, $precio,
        $modalidad, $forma_pago, $observaciones
    ]);
    
    if (!$result) {
        throw new Exception('Error al guardar el pedido en la base de datos');
    }
    
    $pedido_id = $pdo->lastInsertId();
    
    // Log del pedido express
    error_log("PEDIDO EXPRESS CREADO: ID #$pedido_id - $nombre $apellido - $producto - $" . number_format($precio, 0, ',', '.') . " - Empleado: " . $_SESSION['empleado_id']);
    
    // Respuesta exitosa
    echo json_encode([
        'success' => true,
        'pedido_id' => $pedido_id,
        'mensaje' => 'Pedido Express creado exitosamente',
        'data' => [
            'id' => $pedido_id,
            'cliente' => "$nombre $apellido",
            'producto' => $producto,
            'precio' => $precio,
            'modalidad' => $modalidad,
            'forma_pago' => $forma_pago,
            'estado' => 'Pendiente',
            'created_at' => date('Y-m-d H:i:s')
        ]
    ]);
    
} catch (Exception $e) {
    error_log("ERROR PEDIDO EXPRESS: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} catch (PDOException $e) {
    error_log("ERROR DB PEDIDO EXPRESS: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Error de base de datos'
    ]);
}
?>