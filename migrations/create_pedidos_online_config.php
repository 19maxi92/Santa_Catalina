<?php
/**
 * Crear tablas para sistema de pedidos online
 * Ejecutar una sola vez
 */
require_once '../admin/config.php';

try {
    $pdo = getConnection();

    // Tabla de configuración de stock/límites por turno
    $sql = "CREATE TABLE IF NOT EXISTS config_pedidos_online (
        id INT PRIMARY KEY AUTO_INCREMENT,
        turno ENUM('Mañana', 'Siesta', 'Tarde') NOT NULL UNIQUE,
        hora_inicio TIME NOT NULL,
        hora_fin TIME NOT NULL,
        max_pedidos INT NOT NULL DEFAULT 50,
        stock_actual INT NOT NULL DEFAULT 50,
        activo TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";

    $pdo->exec($sql);
    echo "✅ Tabla config_pedidos_online creada\n";

    // Insertar configuración inicial
    $sql_insert = "INSERT INTO config_pedidos_online (turno, hora_inicio, hora_fin, max_pedidos, stock_actual, activo) VALUES
        ('Mañana', '09:00:00', '13:00:00', 30, 30, 1),
        ('Siesta', '13:00:00', '16:00:00', 20, 20, 1),
        ('Tarde', '16:00:00', '21:00:00', 40, 40, 1)
    ON DUPLICATE KEY UPDATE turno = turno";

    $pdo->exec($sql_insert);
    echo "✅ Configuración inicial insertada\n";

    echo "\n✅ ¡MIGRACIÓN COMPLETADA!\n";
    echo "Las tablas para pedidos online están listas.\n";

} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}
?>
