<?php
/*
=== PROCESADOR DE PEDIDOS EXPRESS - LOCAL 1 ===
VERSIÓN CON SISTEMA DE PLANCHAS - IGUAL AL ADMIN
Procesa pedidos rápidos creados desde el dashboard de empleados
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
    
    // Validar campos obligatorios básicos
    $required_fields = ['nombre', 'modalidad', 'forma_pago', 'tipo_pedido', 'precio'];
    foreach ($required_fields as $field) {
        if (!isset($data[$field])) {
            throw new Exception("Campo obligatorio faltante: $field");
        }
    }
    
    // Sanitizar datos básicos
    $nombre = trim($data['nombre']);
    $apellido = trim($data['apellido'] ?? '');
    $telefono = trim($data['telefono'] ?? '');
    $modalidad = $data['modalidad'];
    $forma_pago = $data['forma_pago'];
    $observaciones = trim($data['observaciones'] ?? '');
    $tipo_pedido = $data['tipo_pedido'];
    $precio = (float)$data['precio'];
    $producto = $data['producto'] ?? '';
    $cantidad = (int)($data['cantidad'] ?? 1);
    
    // Validaciones específicas
    if (empty($nombre)) {
        throw new Exception('El nombre es obligatorio');
    }
    
    if (!in_array($modalidad, ['Retiro', 'Delivery'])) {
        throw new Exception('Modalidad inválida');
    }
    
    if (!in_array($forma_pago, ['Efectivo', 'Transferencia', 'Tarjeta', 'MercadoPago'])) {
        throw new Exception('Forma de pago inválida');
    }
    
    if ($precio <= 0) {
        throw new Exception('Precio inválido');
    }
    
    // MANEJO ESPECIAL PARA PERSONALIZADO CON PLANCHAS - IGUAL AL ADMIN
    if ($tipo_pedido === 'personalizado' && isset($data['sabores_personalizados_json'])) {
        $sabores_json = $data['sabores_personalizados_json'];
        
        // Validar que tenga el JSON de planchas
        if (empty($sabores_json)) {
            throw new Exception('Debe seleccionar al menos un sabor');
        }
        
        $sabores_array = json_decode($sabores_json, true);
        if (empty($sabores_array)) {
            throw new Exception('Error al procesar los sabores seleccionados');
        }
        
        // Calcular cantidad y planchas
        $total_planchas = array_sum($sabores_array);
        $cantidad = $total_planchas * 8;
        
        // El producto ya viene formateado desde el frontend
        // Ejemplo: "Personalizado x48 (6 planchas)"
        
        // Las observaciones ya vienen con el detalle formateado de sabores
        // No necesitamos procesarlo aquí, ya está en formato correcto
        
    } else if ($tipo_pedido === 'personalizado') {
        // Personalizado sin el nuevo sistema (legacy - no debería pasar)
        if ($cantidad <= 0) {
            throw new Exception('Cantidad inválida para pedido personalizado');
        }
    } else {
        // Determinar producto si no está definido (pedidos comunes)
        if (empty($producto)) {
            $productos_predefinidos = [
                'jyq24' => 'Jamón y Queso x24',
                'jyq48' => 'Jamón y Queso x48',
                'surtido_clasico48' => 'Surtido Clásico x48',
                'surtido_especial48' => 'Surtido Especial x48'
            ];
            
            $producto = $productos_predefinidos[$tipo_pedido] ?? 'Pedido Express';
        }
    }
    
    // Agregar información del empleado a observaciones
    $empleado_info = "\n\n--- Info del Sistema ---";
    $empleado_info .= "\nPedido Express - Empleado ID: " . $_SESSION['empleado_id'];
    if (isset($_SESSION['empleado_nombre'])) {
        $empleado_info .= " (" . $_SESSION['empleado_nombre'] . ")";
    }
    $empleado_info .= "\nFecha/Hora: " . date('d/m/Y H:i:s');
    $observaciones = trim($observaciones . $empleado_info);
    
    // Generar fecha formateada para mostrar (timezone Argentina)
    $fecha_display = date('d/m H:i');

    // Insertar en base de datos
    $stmt = $pdo->prepare("
        INSERT INTO pedidos (
            nombre, apellido, telefono, producto, cantidad, precio,
            modalidad, forma_pago, ubicacion, estado, observaciones,
            fecha_pedido, created_at, fecha_display
        ) VALUES (
            ?, ?, ?, ?, ?, ?,
            ?, ?, 'Local 1', 'Pendiente', ?,
            NOW(), NOW(), ?
        )
    ");

    $result = $stmt->execute([
        $nombre,
        $apellido,
        $telefono,
        $producto,
        $cantidad,
        $precio,
        $modalidad,
        $forma_pago,
        $observaciones,
        $fecha_display
    ]);
    
    if (!$result) {
        throw new Exception('Error al guardar el pedido en la base de datos');
    }
    
    $pedido_id = $pdo->lastInsertId();
    
    // Log detallado del pedido express
    $log_msg = "PEDIDO EXPRESS CREADO: ID #$pedido_id";
    $log_msg .= " | Cliente: $nombre" . ($apellido ? " $apellido" : "");
    $log_msg .= " | Producto: $producto";
    $log_msg .= " | Precio: $" . number_format($precio, 0, ',', '.');
    $log_msg .= " | Cantidad: $cantidad";
    $log_msg .= " | Modalidad: $modalidad";
    $log_msg .= " | Pago: $forma_pago";
    $log_msg .= " | Empleado: " . $_SESSION['empleado_id'];
    
    if ($tipo_pedido === 'personalizado' && isset($data['sabores_personalizados_json'])) {
        $log_msg .= " | PERSONALIZADO CON PLANCHAS";
        $sabores_count = count(json_decode($data['sabores_personalizados_json'], true));
        $log_msg .= " | Sabores: $sabores_count";
    }
    
    error_log($log_msg);
    
    // Respuesta exitosa
    echo json_encode([
        'success' => true,
        'pedido_id' => $pedido_id,
        'mensaje' => 'Pedido Express creado exitosamente',
        'data' => [
            'id' => $pedido_id,
            'cliente' => trim("$nombre $apellido"),
            'producto' => $producto,
            'cantidad' => $cantidad,
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
        'error' => 'Error de base de datos: ' . $e->getMessage()
    ]);
}
?>