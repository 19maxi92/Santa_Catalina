<?php
// api_precios.php - API para obtener precios dinámicamente desde la base de datos

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

require_once 'config.php';

try {
    $pdo = getConnection();

    // Obtener todos los productos activos con sus precios
    $stmt = $pdo->prepare("
        SELECT
            nombre,
            precio,
            descripcion,
            categoria,
            activo
        FROM productos
        WHERE activo = 1
        ORDER BY nombre
    ");

    $stmt->execute();
    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Formatear los datos para facilitar el uso en el frontend
    // Crear un objeto con las claves específicas que usa el sistema
    $precios_express = [
        'jyq24' => [
            'nombre' => 'Jamón y Queso x24',
            'precio' => 0,
            'cantidad' => 24
        ],
        'jyq48' => [
            'nombre' => 'Jamón y Queso x48',
            'precio' => 0,
            'cantidad' => 48
        ],
        'surtido_clasico48' => [
            'nombre' => 'Surtido Clásico x48',
            'precio' => 0,
            'cantidad' => 48
        ],
        'surtido_especial48' => [
            'nombre' => 'Surtido Especial x48',
            'precio' => 0,
            'cantidad' => 48
        ],
        'personalizado_base' => [
            'nombre' => 'Precio por sándwich',
            'precio' => 750,
            'cantidad' => 1
        ]
    ];

    // Mapear los productos de la base de datos a las claves de pedidos express
    foreach ($productos as $producto) {
        $nombre = strtolower($producto['nombre']);

        // Detectar JyQ x24
        if (strpos($nombre, 'jamón') !== false && strpos($nombre, 'queso') !== false && strpos($nombre, '24') !== false) {
            $precios_express['jyq24']['precio'] = (float)$producto['precio'];
            $precios_express['jyq24']['nombre'] = $producto['nombre'];
        }

        // Detectar JyQ x48
        if (strpos($nombre, 'jamón') !== false && strpos($nombre, 'queso') !== false && strpos($nombre, '48') !== false) {
            $precios_express['jyq48']['precio'] = (float)$producto['precio'];
            $precios_express['jyq48']['nombre'] = $producto['nombre'];
        }

        // Detectar Surtido Clásico x48
        if (strpos($nombre, 'surtido') !== false && strpos($nombre, 'clásico') !== false && strpos($nombre, '48') !== false) {
            $precios_express['surtido_clasico48']['precio'] = (float)$producto['precio'];
            $precios_express['surtido_clasico48']['nombre'] = $producto['nombre'];
        }

        // Detectar Surtido Especial x48
        if (strpos($nombre, 'surtido') !== false && strpos($nombre, 'especial') !== false && strpos($nombre, '48') !== false) {
            $precios_express['surtido_especial48']['precio'] = (float)$producto['precio'];
            $precios_express['surtido_especial48']['nombre'] = $producto['nombre'];
        }
    }

    // Respuesta exitosa
    echo json_encode([
        'success' => true,
        'precios' => $precios_express,
        'todos_productos' => $productos,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error al obtener precios: ' . $e->getMessage()
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
?>
