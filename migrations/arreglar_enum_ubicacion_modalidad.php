<?php
/**
 * ARREGLO DE RAÍZ: las columnas `ubicacion` y `modalidad` de la tabla
 * `pedidos` son de tipo ENUM (lista cerrada de valores permitidos):
 *
 *   modalidad enum('Retira','Delivery')   <- el sistema manda "Retiro", no está en la lista
 *   ubicacion enum('Local 1','Fábrica')   <- "Villa Elisa" no está en la lista
 *
 * Cuando se intenta guardar un valor que no está en la lista, MySQL no
 * tira error: lo guarda como cadena vacía en silencio. Por eso los
 * pedidos a Villa Elisa (y el modo "Retiro" en general) quedaban con
 * esos campos en blanco sin que nadie viera ningún error.
 *
 * Este script cambia esas dos columnas a VARCHAR (texto libre), igual
 * que ya es `ubicacion` en la tabla `configuracion_estaciones`. Es un
 * cambio seguro: solo agranda lo que se puede guardar, no borra ni
 * modifica ningún dato existente.
 *
 * Por defecto solo MUESTRA la estructura actual (no toca nada).
 * Ejecutar: /migrations/arreglar_enum_ubicacion_modalidad.php
 * Para aplicar:  /migrations/arreglar_enum_ubicacion_modalidad.php?confirmar=1
 */

require_once __DIR__ . '/../admin/config.php';

header('Content-Type: text/plain; charset=utf-8');

$confirmar = isset($_GET['confirmar']) && $_GET['confirmar'] === '1';

echo "🔧 Arreglar tipo de columna ubicacion / modalidad en `pedidos`\n";
echo str_repeat('=', 70) . "\n\n";

try {
    $pdo = getConnection();

    $stmt = $pdo->query("SHOW COLUMNS FROM pedidos WHERE Field IN ('ubicacion', 'modalidad')");
    $columnas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($columnas as $col) {
        echo "Columna \"{$col['Field']}\" — tipo actual: {$col['Type']} — default: {$col['Default']}\n";
    }
    echo "\n";

    if (!$confirmar) {
        echo "👀 VISTA PREVIA. No se modificó nada todavía.\n\n";
        echo "Se va a ejecutar:\n";
        echo "  ALTER TABLE pedidos MODIFY ubicacion VARCHAR(50) NOT NULL DEFAULT 'Local 1';\n";
        echo "  ALTER TABLE pedidos MODIFY modalidad VARCHAR(20) NOT NULL DEFAULT 'Retiro';\n\n";
        echo "Esto NO borra ni cambia ningún pedido existente — solo permite que a partir de\n";
        echo "ahora se puedan guardar sucursales/modalidades nuevas sin que MySQL las vacíe.\n\n";
        echo "Si estás de acuerdo, volvé a esta misma URL agregando ?confirmar=1\n";
        exit;
    }

    $pdo->exec("ALTER TABLE pedidos MODIFY ubicacion VARCHAR(50) NOT NULL DEFAULT 'Local 1'");
    echo "✅ Columna 'ubicacion' actualizada a VARCHAR(50).\n";

    $pdo->exec("ALTER TABLE pedidos MODIFY modalidad VARCHAR(20) NOT NULL DEFAULT 'Retiro'");
    echo "✅ Columna 'modalidad' actualizada a VARCHAR(20).\n\n";

    $stmt = $pdo->query("SHOW COLUMNS FROM pedidos WHERE Field IN ('ubicacion', 'modalidad')");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
        echo "Columna \"{$col['Field']}\" — tipo nuevo: {$col['Type']} — default: {$col['Default']}\n";
    }

    echo "\n✅ Listo. De acá en más, un pedido a Villa Elisa (o cualquier sucursal futura)\n";
    echo "   va a guardar la ubicación correctamente en vez de quedar en blanco.\n";

} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
}
