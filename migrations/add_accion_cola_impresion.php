<?php
/**
 * Migración: agrega la columna `accion` a `cola_impresion`, para poder
 * distinguir entre "imprimir_comanda" (lo de siempre) y "abrir_cajon"
 * (nuevo: abrir la gaveta de Local 1 al cobrar en efectivo, sin imprimir
 * nada). No toca ninguna fila existente ni ninguna otra tabla.
 *
 * Ejecutar desde navegador (logueado como admin): /migrations/add_accion_cola_impresion.php
 */

require_once __DIR__ . '/../admin/config.php';

header('Content-Type: text/plain; charset=utf-8');

echo "🚀 MIGRACIÓN: columna 'accion' en cola_impresion\n";
echo str_repeat('=', 60) . "\n\n";

try {
    $pdo = getConnection();

    $stmt = $pdo->query("SHOW COLUMNS FROM cola_impresion LIKE 'accion'");
    if ($stmt->fetch()) {
        echo "✅ La columna 'accion' ya existe, no hace falta hacer nada.\n";
        exit;
    }

    // VARCHAR a propósito, no ENUM — ya aprendimos que un ENUM con lista
    // cerrada vacía en silencio cualquier valor que no esté en la lista
    // (fue justo la causa raíz del bug de Villa Elisa).
    $pdo->exec("
        ALTER TABLE cola_impresion
        ADD COLUMN accion VARCHAR(30) NOT NULL DEFAULT 'imprimir_comanda'
        AFTER ubicacion
    ");

    echo "✅ Columna 'accion' agregada a 'cola_impresion'.\n";
    echo "   Valores posibles: 'imprimir_comanda' (default, sin cambios) o 'abrir_cajon'.\n";

} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
}
