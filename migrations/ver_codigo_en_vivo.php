<?php
/**
 * Diagnóstico (solo lectura): muestra el CÓDIGO FUENTE real de un archivo
 * puntual tal cual está en el servidor ahora mismo (no lo que hay en git).
 * Restringido a una lista fija de archivos conocidos, por seguridad.
 *
 * Ejecutar: /migrations/ver_codigo_en_vivo.php?archivo=estaciones
 * Sin parámetro, muestra procesar_pedido_express.php (el de siempre).
 */

header('Content-Type: text/plain; charset=utf-8');

$permitidos = [
    'procesar_pedido_express' => __DIR__ . '/../admin/modules/pedidos/procesar_pedido_express.php',
    'estaciones' => __DIR__ . '/../api-impresion/estaciones.php',
    'config' => __DIR__ . '/../admin/config.php',
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
