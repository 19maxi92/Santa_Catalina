<?php
/*
=== PROCESADOR DE PEDIDOS EXPRESS - ADMIN ===
VERSIÓN CON SISTEMA DE PLANCHAS Y MULTI-UBICACIÓN
Procesa pedidos rápidos creados desde el dashboard de administración
*/

header('Content-Type: application/json');
require_once '../../config.php';

// Verificar autenticación de admin
if (!isLoggedIn()) {
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
    error_log("DEBUG: Input recibido: " . substr($input, 0, 500)); // Log primeros 500 caracteres

    $data = json_decode($input, true);

    if (!$data) {
        $jsonError = json_last_error_msg();
        error_log("ERROR: JSON inválido - " . $jsonError);
        throw new Exception('Datos JSON inválidos: ' . $jsonError);
    }

    error_log("DEBUG: Datos decodificados correctamente");

    // Validar campos obligatorios básicos
    $required_fields = ['nombre', 'modalidad', 'forma_pago', 'tipo_pedido', 'precio', 'ubicacion'];
    foreach ($required_fields as $field) {
        if (!isset($data[$field])) {
            throw new Exception("Campo obligatorio faltante: $field");
        }
    }

    // Sanitizar datos básicos
    $nombre = trim($data['nombre']);
    $apellido = trim($data['apellido'] ?? '');
    $telefono = trim($data['telefono'] ?? '');
    $direccion = trim($data['direccion'] ?? '');
    $modalidad = $data['modalidad'];
    $forma_pago = $data['forma_pago'];
    $ubicacion = $data['ubicacion'];
    $fecha_entrega = $data['fecha_entrega'] ?? null;
    $observaciones = trim($data['observaciones'] ?? '');
    $tipo_pedido = $data['tipo_pedido'];
    $precio = (float)$data['precio'];
    $producto = $data['producto'] ?? '';
    $cantidad = (int)($data['cantidad'] ?? 1);
    $estado = $data['estado'] ?? 'Pendiente';

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

    if (empty($ubicacion)) {
        throw new Exception('La ubicación es obligatoria');
    }

    if ($precio <= 0) {
        throw new Exception('Precio inválido');
    }

    // MANEJO ESPECIAL PARA PERSONALIZADO CON PLANCHAS
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

    // Agregar información del admin a observaciones
    $admin_info = "\n\n--- Info del Sistema ---";
    $admin_info .= "\nPedido Express - Admin: " . $_SESSION['admin_user'];
    if (isset($_SESSION['admin_name'])) {
        $admin_info .= " (" . $_SESSION['admin_name'] . ")";
    }
    // Usar DateTime con timezone explícito
    $dt_info = new DateTime('now', new DateTimeZone('America/Argentina/Buenos_Aires'));
    $admin_info .= "\nFecha/Hora: " . $dt_info->format('d/m/Y H:i:s');
    $observaciones = trim($observaciones . $admin_info);

    // Generar fecha formateada para mostrar (timezone Argentina)
    $dt = new DateTime('now', new DateTimeZone('America/Argentina/Buenos_Aires'));
    $fecha_display = $dt->format('d/m H:i');

    // Insertar en base de datos
    $stmt = $pdo->prepare("
        INSERT INTO pedidos (
            nombre, apellido, telefono, direccion, producto, cantidad, precio,
            modalidad, forma_pago, ubicacion, estado, observaciones,
            fecha_entrega, fecha_pedido, created_at, fecha_display
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?,
            ?, NOW(), NOW(), ?
        )
    ");

    $result = $stmt->execute([
        $nombre,
        $apellido,
        $telefono,
        $direccion,
        $producto,
        $cantidad,
        $precio,
        $modalidad,
        $forma_pago,
        $ubicacion,
        $estado,
        $observaciones,
        $fecha_entrega,
        $fecha_display
    ]);

    if (!$result) {
        throw new Exception('Error al guardar el pedido en la base de datos');
    }

    $pedido_id = $pdo->lastInsertId();

    // Log detallado del pedido express
    $log_msg = "PEDIDO EXPRESS ADMIN CREADO: ID #$pedido_id";
    $log_msg .= " | Cliente: $nombre" . ($apellido ? " $apellido" : "");
    $log_msg .= " | Producto: $producto";
    $log_msg .= " | Precio: $" . number_format($precio, 0, ',', '.');
    $log_msg .= " | Cantidad: $cantidad";
    $log_msg .= " | Modalidad: $modalidad";
    $log_msg .= " | Pago: $forma_pago";
    $log_msg .= " | Ubicación: $ubicacion";
    $log_msg .= " | Admin: " . $_SESSION['admin_user'];

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
            'ubicacion' => $ubicacion,
            'estado' => $estado,
            'created_at' => date('Y-m-d H:i:s')
        ]
    ]);

} catch (Exception $e) {
    error_log("ERROR PEDIDO EXPRESS ADMIN: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} catch (PDOException $e) {
    error_log("ERROR DB PEDIDO EXPRESS ADMIN: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Error de base de datos: ' . $e->getMessage()
    ]);
}
?>
