<?php
require_once '../admin/config.php';
$pdo = getConnection();

try {
    $pdo->exec("ALTER TABLE pedidos ADD COLUMN tomado TINYINT(1) NOT NULL DEFAULT 1");
    echo "✅ Columna 'tomado' agregada correctamente.";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "ℹ️ La columna 'tomado' ya existe, no se hizo nada.";
    } else {
        throw $e;
    }
}
