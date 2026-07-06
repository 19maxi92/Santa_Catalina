<?php
require_once '../admin/config.php';
$pdo = getConnection();

$pdo->exec("
    CREATE TABLE IF NOT EXISTS precios_personalizados (
        id INT AUTO_INCREMENT PRIMARY KEY,
        categoria VARCHAR(20) NOT NULL,
        planchas INT NOT NULL,
        precio INT NOT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        updated_by VARCHAR(50) DEFAULT 'admin',
        UNIQUE KEY uk_cat_planchas (categoria, planchas)
    )
");

$datos = [
    ['premium', 1, 9000],
    ['premium', 2, 18000],
    ['premium', 3, 27000],
    ['premium', 4, 36000],
    ['premium', 5, 45000],
    ['premium', 6, 54000],
    ['elegidos', 1, 5400],
    ['elegidos', 2, 10800],
    ['elegidos', 3, 16000],
    ['elegidos', 4, 21400],
    ['elegidos', 5, 26800],
    ['elegidos', 6, 32000],
];

$stmt = $pdo->prepare("INSERT IGNORE INTO precios_personalizados (categoria, planchas, precio) VALUES (?, ?, ?)");
foreach ($datos as [$cat, $planchas, $precio]) {
    $stmt->execute([$cat, $planchas, $precio]);
}

echo "✅ Tabla precios_personalizados creada y cargada correctamente.";
