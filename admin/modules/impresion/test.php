<?php
// admin/modules/impresion/test.php
require_once '../../config.php';
requireLogin();

$pdo = getConnection();

echo "<h2>🧪 Test Completo del Módulo de Impresión</h2>";

echo "<h3>1. Verificando archivos del módulo:</h3>";
$archivos_modulo = [
    'comanda.php' => 'Generador de comandas',
    'config.php' => 'Configuración de impresora',
    'test.php' => 'Test del módulo'
];

foreach ($archivos_modulo as $archivo => $descripcion) {
    if (file_exists($archivo)) {
        echo "<p>✅ <strong>$archivo</strong> - $descripcion</p>";
    } else {
        echo "<p>❌ <strong>$archivo</strong> FALTANTE - $descripcion</p>";
    }
}

echo "<h3>2. Probando conexión y datos:</h3>";
try {
    $pedidos_test = $pdo->query("SELECT COUNT(*) FROM pedidos")->fetchColumn();
    echo "<p>✅ Pedidos disponibles para test: $pedidos_test</p>";
    
    if ($pedidos_test > 0) {
        $pedido_ejemplo = $pdo->query("SELECT id, nombre, apellido, producto FROM pedidos LIMIT 1")->fetch();
        echo "<p>✅ Pedido de ejemplo: #{$pedido_ejemplo['id']} - {$pedido_ejemplo['nombre']} {$pedido_ejemplo['apellido']}</p>";
        
        echo "<p><a href='comanda.php?pedido={$pedido_ejemplo['id']}' target='_blank' style='background:#007bff; color:white; padding:8px 15px; text-decoration:none; border-radius:3px;'>
                🖨️ Test Comanda Real
              </a></p>";
    }
} catch (Exception $e) {
    echo "<p>❌ Error accediendo a datos: " . $e->getMessage() . "</p>";
}

echo "<h3>3. Enlaces de prueba:</h3>";
echo "<div style='background:#f0f0f0; padding:15px; border-radius:5px;'>";
echo "<p><a href='config.php' target='_blank' style='background:#28a745; color:white; padding:8px 15px; text-decoration:none; border-radius:3px;'>⚙️ Configurar Impresora</a></p>";
echo "<p><a href='comanda.php?pedido=1' target='_blank' style='background:#17a2b8; color:white; padding:8px 15px; text-decoration:none; border-radius:3px;'>📄 Test Comanda</a></p>";
echo "<p><a href='../pedidos/ver_pedidos.php' target='_blank' style='background:#6c757d; color:white; padding:8px 15px; text-decoration:none; border-radius:3px;'>📋 Ver Pedidos</a></p>";
echo "</div>";

echo "<h3>4. Verificación de integración:</h3>";
$ver_pedidos_path = '../pedidos/ver_pedidos.php';
if (file_exists($ver_pedidos_path)) {
    echo "<p>✅ Archivo ver_pedidos.php encontrado</p>";
    echo "<p>⚠️ <strong>SIGUIENTE PASO:</strong> Modificar la función imprimirComanda() en ver_pedidos.php</p>";
} else {
    echo "<p>❌ No se encontró ver_pedidos.php</p>";
}

echo "<h3>5. Código para integrar en ver_pedidos.php:</h3>";
echo "<div style='background:#f8f9fa; border:1px solid #e9ecef; padding:15px; border-radius:5px; font-family:monospace; font-size:12px;'>";
echo "<strong>REEMPLAZAR la función imprimirComanda() existente por:</strong><br><br>";
echo htmlspecialchars("
// NUEVA función imprimirComanda() - REEMPLAZAR la existente
function imprimirComanda(pedidoId) {
    // Abrir módulo de impresión en nueva ventana
    const url = '../impresion/comanda.php?pedido=' + pedidoId;
    const ventana = window.open(url, '_blank', 'width=500,height=700,scrollbars=yes');
    
    if (!ventana) {
        alert('Error: No se pudo abrir la ventana de impresión.\\nVerificar que no esté bloqueada por el navegador.');
        return;
    }
    
    // Focus en la nueva ventana
    ventana.focus();
    
    // Log para debug
    console.log('🖨️ Abriendo comanda para pedido #' + pedidoId);
}
");
echo "</div>";

echo "<hr>";
echo "<h3>🎯 ESTADO DEL MÓDULO:</h3>";

$archivos_ok = 0;
foreach ($archivos_modulo as $archivo => $desc) {
    if (file_exists($archivo)) $archivos_ok++;
}

$porcentaje = round(($archivos_ok / count($archivos_modulo)) * 100);

if ($porcentaje == 100) {
    echo "<div style='background:#d4edda; color:#155724; padding:15px; border-radius:5px;'>";
    echo "<h4>🎉 MÓDULO COMPLETO ($porcentaje%)</h4>";
    echo "<p>✅ Todos los archivos están presentes</p>";
    echo "<p>✅ El módulo está listo para usar</p>";
    echo "<p><strong>SIGUIENTE:</strong> Modificar ver_pedidos.php con el código de arriba</p>";
    echo "</div>";
} else {
    echo "<div style='background:#f8d7da; color:#721c24; padding:15px; border-radius:5px;'>";
    echo "<h4>⚠️ MÓDULO INCOMPLETO ($porcentaje%)</h4>";
    echo "<p>Faltan archivos del módulo</p>";
    echo "</div>";
}

echo "<h4>📋 Checklist de implementación:</h4>";
echo "<ul>";
echo "<li>☐ 1. Crear directorio admin/modules/impresion/</li>";
echo "<li>☐ 2. Subir archivos: comanda.php, config.php, test.php</li>";
echo "<li>☐ 3. Modificar función en ver_pedidos.php</li>";
echo "<li>☐ 4. Configurar impresora 3nstar RPT006S</li>";
echo "<li>☐ 5. Hacer test de impresión</li>";
echo "</ul>";

echo "<p><small>Test ejecutado el " . date('d/m/Y H:i:s') . "</small></p>";
?>