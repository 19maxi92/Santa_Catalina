<?php
require_once 'config.php';

echo "<h1>Test de Timezone</h1>";

// Test 1: PHP Timezone
echo "<h2>1. PHP Configuration</h2>";
echo "Timezone configurado: " . date_default_timezone_get() . "<br>";
echo "Fecha PHP actual: " . date('Y-m-d H:i:s') . "<br>";

// Test 2: MySQL Timezone y NOW()
try {
    $pdo = getConnection();

    echo "<h2>2. MySQL Configuration</h2>";

    $result = $pdo->query("SELECT @@session.time_zone as session_tz, @@global.time_zone as global_tz");
    $row = $result->fetch();
    echo "MySQL Session Timezone: " . $row['session_tz'] . "<br>";
    echo "MySQL Global Timezone: " . $row['global_tz'] . "<br>";

    $result = $pdo->query("SELECT NOW() as mysql_now, UTC_TIMESTAMP() as utc_now");
    $row = $result->fetch();
    echo "MySQL NOW(): " . $row['mysql_now'] . "<br>";
    echo "MySQL UTC_TIMESTAMP(): " . $row['utc_now'] . "<br>";

    // Test 3: Comparar con último pedido
    echo "<h2>3. Último Pedido en BD</h2>";
    $result = $pdo->query("SELECT id, nombre, created_at FROM pedidos ORDER BY id DESC LIMIT 1");
    $pedido = $result->fetch();
    if ($pedido) {
        echo "ID: " . $pedido['id'] . "<br>";
        echo "Nombre: " . $pedido['nombre'] . "<br>";
        echo "created_at en BD: " . $pedido['created_at'] . "<br>";
    }

    // Test 4: Productos y precios
    echo "<h2>4. Productos en BD</h2>";
    $result = $pdo->query("SELECT nombre, precio_efectivo, precio_transferencia FROM productos WHERE activo = 1 ORDER BY nombre LIMIT 10");
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Producto</th><th>Precio Efectivo</th><th>Precio Transferencia</th></tr>";
    while ($prod = $result->fetch()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($prod['nombre']) . "</td>";
        echo "<td>$" . number_format($prod['precio_efectivo'], 0, ',', '.') . "</td>";
        echo "<td>$" . number_format($prod['precio_transferencia'], 0, ',', '.') . "</td>";
        echo "</tr>";
    }
    echo "</table>";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
?>
