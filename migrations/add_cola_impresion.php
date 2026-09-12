<?php
/**
 * Migración: crea las tablas para la impresión automática de comandas
 * (estaciones_impresion, cola_impresion). No toca ninguna tabla existente.
 * Ejecutar desde navegador (logueado como admin de Hostinger): /migrations/add_cola_impresion.php
 */

require_once __DIR__ . '/../admin/config.php';

header('Content-Type: text/plain; charset=utf-8');

echo "🚀 MIGRACIÓN: cola de impresión automática\n";
echo str_repeat('=', 60) . "\n\n";

try {
    $pdo = getConnection();

    // 1. Tabla de estaciones (cada PC/Electron autorizada a imprimir)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS estaciones_impresion (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nombre VARCHAR(100) NOT NULL COMMENT 'Etiqueta para identificarla, ej: PC Mostrador Local 1',
            token VARCHAR(64) NOT NULL UNIQUE COMMENT 'Clave secreta que usa la app Electron para autenticarse',
            ubicacion VARCHAR(50) NOT NULL COMMENT 'Local 1 / Fábrica / Villa Elisa',
            activa TINYINT(1) NOT NULL DEFAULT 1,
            primera_conexion DATETIME NULL COMMENT 'Cursor: ignora pedidos creados antes de este momento',
            ultima_conexion DATETIME NULL COMMENT 'Último heartbeat recibido, para saber si está viva',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ Tabla 'estaciones_impresion' lista.\n\n";

    // 2. Tabla de la cola: una copia liviana por pedido, separada de la tabla `pedidos`
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS cola_impresion (
            id INT AUTO_INCREMENT PRIMARY KEY,
            pedido_id INT NOT NULL,
            codigo VARCHAR(40) NOT NULL UNIQUE COMMENT 'Identificador único del trabajo de impresión',
            ubicacion VARCHAR(50) NOT NULL,
            estado ENUM('pendiente','reservado','impreso','error') NOT NULL DEFAULT 'pendiente',
            estacion_id INT NULL COMMENT 'Qué estación reservó/imprimió este trabajo',
            intentos INT NOT NULL DEFAULT 0,
            error_mensaje TEXT NULL,
            reservado_en DATETIME NULL,
            impreso_en DATETIME NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_ubicacion_estado_creado (ubicacion, estado, created_at),
            INDEX idx_pedido (pedido_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ Tabla 'cola_impresion' lista.\n\n";

    echo "Listo. Ninguna tabla existente fue modificada.\n";
    echo "Próximo paso: crear una estación desde el panel (o insertarla a mano) para obtener un token.\n";

} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}
