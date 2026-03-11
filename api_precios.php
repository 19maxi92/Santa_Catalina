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
            id,
            nombre,
            precio_efectivo,
            precio_transferencia,
            descripcion,
            categoria,
            activo
        FROM productos
        WHERE activo = 1
        ORDER BY nombre
    ");

    $stmt->execute();
    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Mapa rápido por nombre exacto
    $map = [];
    foreach ($productos as $p) {
        $map[$p['nombre']] = $p;
    }

    // Construir respuesta express con precios efectivo y transferencia
    $precios_express = [];
    $definiciones = [
        'jyq24'             => '24 Jamón y Queso',
        'jyq48'             => '48 Jamón y Queso',
        'clas24'            => '24 Surtidos Clásicos',
        'clas48'            => '48 Surtidos Clásicos',
        'esp24'             => '24 Surtidos Especiales',
        'esp48'             => '48 Surtidos Especiales',
        'prem24'            => '24 Surtidos Premium',
        'prem48'            => '48 Surtidos Premium',
        'eleg8'             => '8 Surtidos Elegidos',
        'eleg16'            => '16 Surtidos Elegidos',
        'eleg24'            => '24 Surtidos Elegidos',
        'eleg32'            => '32 Surtidos Elegidos',
        'eleg40'            => '40 Surtidos Elegidos',
        'eleg48'            => '48 Surtidos Elegidos',
    ];

    foreach ($definiciones as $key => $nombre_bd) {
        if (isset($map[$nombre_bd])) {
            $p = $map[$nombre_bd];
            $precios_express[$key] = [
                'nombre'               => $p['nombre'],
                'precio_efectivo'      => (float)$p['precio_efectivo'],
                'precio_transferencia' => (float)$p['precio_transferencia'],
            ];
        }
    }

    // Respuesta exitosa
    echo json_encode([
        'success'          => true,
        'precios'          => $precios_express,
        'todos_productos'  => $productos,
        'timestamp'        => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error al obtener precios: ' . $e->getMessage()
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
?>
