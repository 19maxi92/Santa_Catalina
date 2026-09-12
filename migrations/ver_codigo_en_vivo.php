<?php
/**
 * Diagnóstico (solo lectura): muestra el CÓDIGO FUENTE real de
 * procesar_pedido_express.php tal cual está en el servidor ahora mismo
 * (no lo que hay en git) — para comparar y confirmar si el archivo que
 * realmente se ejecuta está desactualizado respecto al repositorio.
 *
 * Ejecutar: /migrations/ver_codigo_en_vivo.php
 */

header('Content-Type: text/plain; charset=utf-8');

$archivo = __DIR__ . '/../admin/modules/pedidos/procesar_pedido_express.php';

if (!file_exists($archivo)) {
    echo "No se encontró el archivo en: $archivo\n";
    exit;
}

echo "📄 Contenido real de procesar_pedido_express.php en este servidor\n";
echo "Última modificación: " . date('Y-m-d H:i:s', filemtime($archivo)) . "\n";
echo str_repeat('=', 70) . "\n\n";

echo file_get_contents($archivo);
