<?php
// Test para verificar que config.php funciona desde empleados/
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h2>Test de configuración desde empleados/</h2>";

echo "<h3>1. Probando require_once '../config.php':</h3>";
try {
    require_once '../config.php';
    echo "✅ Config.php cargado correctamente<br>";
    echo "✅ APP_NAME: " . APP_NAME . "<br>";
    echo "✅ DB_NAME: " . DB_NAME . "<br>";
} catch (Exception $e) {
    echo "❌ Error cargando config.php: " . $e->getMessage() . "<br>";
    exit;
}

echo "<h3>2. Probando conexión a BD:</h3>";
try {
    $pdo = getConnection();
    echo "✅ Conexión a BD exitosa<br>";
} catch (Exception $e) {
    echo "❌ Error conectando a BD: " . $e->getMessage() . "<br>";
    exit;
}

echo "<h3>3. Probando consulta usuarios:</h3>";
try {
    $stmt = $pdo->query("SELECT * FROM usuarios WHERE activo = 1");
    $usuarios = $stmt->fetchAll();
    
    echo "✅ Consulta exitosa, usuarios encontrados:<br>";
    foreach ($usuarios as $user) {
        echo "- ID: " . $user['id'] . ", Usuario: " . $user['usuario'] . ", Nombre: " . $user['nombre'] . "<br>";
    }
} catch (Exception $e) {
    echo "❌ Error en consulta: " . $e->getMessage() . "<br>";
}

echo "<h3>4. Probando función sanitize:</h3>";
try {
    $test = sanitize("  test  ");
    echo "✅ Función sanitize funciona: '$test'<br>";
} catch (Exception $e) {
    echo "❌ Error en función sanitize: " . $e->getMessage() . "<br>";
}

echo "<h3>5. Probando formatPrice:</h3>";
try {
    $precio = formatPrice(1500);
    echo "✅ Función formatPrice funciona: $precio<br>";
} catch (Exception $e) {
    echo "❌ Error en función formatPrice: " . $e->getMessage() . "<br>";
}

echo "<hr><strong>Si todo aparece con ✅, el problema no está en config.php</strong>";
?>