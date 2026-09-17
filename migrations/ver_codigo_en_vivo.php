<?php
/**
 * Diagnóstico (solo lectura): muestra el CÓDIGO FUENTE real de un archivo
 * puntual tal cual está en el servidor ahora mismo (no lo que hay en git).
 * Restringido a una lista fija de archivos conocidos y solo para admin logueado,
 * por seguridad (nunca config.php: tiene las credenciales).
 *
 * Ejecutar: /migrations/ver_codigo_en_vivo.php?archivo=estaciones
 * Sin parámetro, muestra procesar_pedido_express.php (el de siempre).
 */

require_once __DIR__ . '/../admin/config.php';

header('Content-Type: text/plain; charset=utf-8');

// Solo admin logueado: estos archivos son código del servidor, no se muestran a cualquiera.
if (!isLoggedIn()) {
    http_response_code(403);
    echo "Hay que estar logueado como admin para ver esto.\n";
    exit;
}

// config.php NO está en la lista a propósito: tiene las credenciales de la base de datos.
$permitidos = [
    'procesar_pedido_express' => __DIR__ . '/../admin/modules/pedidos/procesar_pedido_express.php',
    'estaciones' => __DIR__ . '/../api-impresion/estaciones.php',
    'ver_pedidos' => __DIR__ . '/../admin/modules/pedidos/ver_pedidos.php',
    'cola_impresion' => __DIR__ . '/../admin/cola_impresion.php',
    'dashboard_local1' => __DIR__ . '/../empleados/dashboard_local1.php',
];

$clave = $_GET['archivo'] ?? 'procesar_pedido_express';

if (!isset($permitidos[$clave])) {
    echo "Archivo no permitido. Opciones válidas: " . implode(', ', array_keys($permitidos)) . "\n";
    exit;
}

$archivo = $permitidos[$clave];

if (!file_exists($archivo)) {
    echo "No se encontró el archivo en: $archivo\n";
    exit;
}

echo "📄 Contenido real de $clave en este servidor\n";
echo "Última modificación: " . date('Y-m-d H:i:s', filemtime($archivo)) . "\n";
echo str_repeat('=', 70) . "\n\n";

echo file_get_contents($archivo);
