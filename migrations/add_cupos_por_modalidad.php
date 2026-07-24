<?php
require_once '../admin/config.php';
$pdo = getConnection();

foreach (['max_pedidos_retiro', 'max_pedidos_delivery'] as $col) {
    try {
        $pdo->exec("ALTER TABLE config_pedidos_online_dias ADD COLUMN {$col} INT NOT NULL DEFAULT 30");
        echo "✅ Columna '{$col}' agregada.<br>";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "ℹ️ La columna '{$col}' ya existe.<br>";
        } else {
            throw $e;
        }
    }
}

// Migrar el valor existente de max_pedidos a ambas columnas nuevas
$pdo->exec("UPDATE config_pedidos_online_dias SET max_pedidos_retiro = max_pedidos, max_pedidos_delivery = max_pedidos WHERE max_pedidos_retiro = 30 AND max_pedidos_delivery = 30");

echo "✅ Listo. Ahora Retiro y Delivery tienen cupos independientes por turno/día (arrancan con el mismo valor que tenías configurado, podés ajustarlos por separado desde Configuración).";
