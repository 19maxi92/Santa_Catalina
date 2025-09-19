<?php
/*
=== GENERADOR DE HASH PARA CONTRASEÑA LOCAL1 ===
Script para generar el hash correcto de la contraseña
*/

// Contraseña que queremos
$password = 'local1pass';

// Generar hash seguro
$hash = password_hash($password, PASSWORD_DEFAULT);

echo "<h2>🔐 Generador de Hash para Local 1</h2>";
echo "<div style='background: #f5f5f5; padding: 20px; margin: 10px 0; border-radius: 5px;'>";
echo "<h3>Contraseña:</h3>";
echo "<code style='color: blue; font-size: 16px;'>$password</code><br><br>";
echo "<h3>Hash generado:</h3>";
echo "<code style='color: green; font-size: 14px; word-break: break-all;'>$hash</code>";
echo "</div>";

echo "<h3>📋 SQL para actualizar:</h3>";
echo "<div style='background: #e8f4f8; padding: 15px; margin: 10px 0; border-radius: 5px;'>";
echo "<code style='color: #333;'>";
echo "UPDATE usuarios SET password = '$hash' WHERE usuario = 'local1';";
echo "</code>";
echo "</div>";

// Verificar que el hash funciona
if (password_verify($password, $hash)) {
    echo "<div style='background: #d4edda; color: #155724; padding: 10px; border-radius: 5px; margin: 10px 0;'>";
    echo "✅ <strong>Verificación exitosa:</strong> El hash es correcto para la contraseña '$password'";
    echo "</div>";
} else {
    echo "<div style='background: #f8d7da; color: #721c24; padding: 10px; border-radius: 5px; margin: 10px 0;'>";
    echo "❌ <strong>Error:</strong> El hash no es válido";
    echo "</div>";
}

echo "<hr>";
echo "<h3>🛠️ Instrucciones:</h3>";
echo "<ol>";
echo "<li>Copia el SQL de arriba</li>";
echo "<li>Ejecútalo en tu phpMyAdmin</li>";
echo "<li>Intenta hacer login nuevamente con: <strong>local1</strong> / <strong>local1pass</strong></li>";
echo "</ol>";

// Información adicional
echo "<hr>";
echo "<h3>🔍 Información de Debug:</h3>";
echo "<ul>";
echo "<li><strong>Método de hash:</strong> " . PASSWORD_DEFAULT . "</li>";
echo "<li><strong>Algoritmo:</strong> bcrypt</li>";
echo "<li><strong>Longitud del hash:</strong> " . strlen($hash) . " caracteres</li>";
echo "</ul>";

// Test con el hash que se muestra en el debug
$hash_debug = '$2y$10592IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';
echo "<h3>🧪 Test del hash actual en BD:</h3>";
if (password_verify('local1pass', $hash_debug)) {
    echo "<div style='background: #d4edda; color: #155724; padding: 10px; border-radius: 5px;'>";
    echo "✅ El hash actual SÍ funciona con 'local1pass'";
    echo "</div>";
} else {
    echo "<div style='background: #f8d7da; color: #721c24; padding: 10px; border-radius: 5px;'>";
    echo "❌ El hash actual NO funciona con 'local1pass' - necesita actualización";
    echo "</div>";
}
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
code { background: #f1f1f1; padding: 2px 4px; border-radius: 3px; }
h2 { color: #333; }
h3 { color: #666; margin-top: 20px; }
</style>