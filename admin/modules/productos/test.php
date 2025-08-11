<?php
// Test para verificar que el módulo de productos funciona
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h2>🧪 Test Completo del Módulo de Productos</h2>";

echo "<h3>1. Probando carga de config.php:</h3>";
try {
    require_once '../../config.php';
    echo "✅ Config.php cargado correctamente<br>";
    echo "✅ APP_NAME: " . APP_NAME . "<br>";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
    exit;
}

echo "<h3>2. Probando conexión a BD:</h3>";
try {
    $pdo = getConnection();
    echo "✅ Conexión exitosa<br>";
} catch (Exception $e) {
    echo "❌ Error de conexión: " . $e->getMessage() . "<br>";
    exit;
}

echo "<h3>3. Verificando tablas necesarias:</h3>";
try {
    // Verificar tabla productos
    $stmt = $pdo->query("SHOW TABLES LIKE 'productos'");
    if ($stmt->rowCount() > 0) {
        echo "✅ Tabla 'productos' existe<br>";
        
        $count = $pdo->query("SELECT COUNT(*) FROM productos")->fetchColumn();
        echo "✅ Productos en BD: $count<br>";
        
        if ($count > 0) {
            $producto_ejemplo = $pdo->query("SELECT nombre, precio_efectivo, precio_transferencia FROM productos LIMIT 1")->fetch();
            echo "✅ Ejemplo: {$producto_ejemplo['nombre']} - Efectivo: " . formatPrice($producto_ejemplo['precio_efectivo']) . "<br>";
        }
    } else {
        echo "❌ Tabla 'productos' no existe<br>";
    }
    
    // Verificar tabla promos
    $stmt = $pdo->query("SHOW TABLES LIKE 'promos'");
    if ($stmt->rowCount() > 0) {
        echo "✅ Tabla 'promos' existe<br>";
        
        $count = $pdo->query("SELECT COUNT(*) FROM promos")->fetchColumn();
        echo "✅ Promos en BD: $count<br>";
        
        if ($count > 0) {
            $promo_ejemplo = $pdo->query("SELECT nombre, precio_efectivo FROM promos LIMIT 1")->fetch();
            echo "✅ Ejemplo: {$promo_ejemplo['nombre']} - " . formatPrice($promo_ejemplo['precio_efectivo']) . "<br>";
        }
    } else {
        echo "❌ Tabla 'promos' no existe<br>";
    }
    
    // Verificar tabla historial_precios
    $stmt = $pdo->query("SHOW TABLES LIKE 'historial_precios'");
    if ($stmt->rowCount() > 0) {
        echo "✅ Tabla 'historial_precios' existe<br>";
        
        $count = $pdo->query("SELECT COUNT(*) FROM historial_precios")->fetchColumn();
        echo "✅ Registros en historial: $count<br>";
    } else {
        echo "❌ Tabla 'historial_precios' no existe<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Error verificando tablas: " . $e->getMessage() . "<br>";
}

echo "<h3>4. Probando consulta compleja del módulo:</h3>";
try {
    $stmt = $pdo->query("
        SELECT p.*, 
               COUNT(pe.id) as total_pedidos,
               SUM(pe.cantidad) as unidades_vendidas,
               SUM(pe.precio) as total_facturado
        FROM productos p
        LEFT JOIN pedidos pe ON pe.producto = p.nombre
        GROUP BY p.id 
        ORDER BY p.nombre 
        LIMIT 3
    ");
    $productos = $stmt->fetchAll();
    
    echo "✅ Consulta exitosa, productos encontrados: " . count($productos) . "<br>";
    
    if (!empty($productos)) {
        echo "<table style='border-collapse: collapse; width: 100%; margin: 10px 0;'>";
        echo "<tr style='background: #f0f0f0;'>";
        echo "<th style='border: 1px solid #ddd; padding: 8px;'>Nombre</th>";
        echo "<th style='border: 1px solid #ddd; padding: 8px;'>Efectivo</th>";
        echo "<th style='border: 1px solid #ddd; padding: 8px;'>Transfer</th>";
        echo "<th style='border: 1px solid #ddd; padding: 8px;'>Pedidos</th>";
        echo "<th style='border: 1px solid #ddd; padding: 8px;'>Unidades</th>";
        echo "</tr>";
        
        foreach ($productos as $producto) {
            echo "<tr>";
            echo "<td style='border: 1px solid #ddd; padding: 8px;'>{$producto['nombre']}</td>";
            echo "<td style='border: 1px solid #ddd; padding: 8px;'>" . formatPrice($producto['precio_efectivo']) . "</td>";
            echo "<td style='border: 1px solid #ddd; padding: 8px;'>" . formatPrice($producto['precio_transferencia']) . "</td>";
            echo "<td style='border: 1px solid #ddd; padding: 8px;'>{$producto['total_pedidos']}</td>";
            echo "<td style='border: 1px solid #ddd; padding: 8px;'>{$producto['unidades_vendidas']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
} catch (Exception $e) {
    echo "❌ Error en consulta: " . $e->getMessage() . "<br>";
}

echo "<h3>5. Verificando funciones necesarias:</h3>";
try {
    echo "✅ Función formatPrice: " . formatPrice(12000) . "<br>";
    echo "✅ Función sanitize: '" . sanitize("  test  ") . "'<br>";
    
    // Probar función de sesión
    if (function_exists('isLoggedIn')) {
        echo "✅ Función isLoggedIn existe<br>";
    } else {
        echo "❌ Función isLoggedIn no existe<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Error en funciones: " . $e->getMessage() . "<br>";
}

echo "<h3>6. Verificando archivos del módulo:</h3>";
$archivos_necesarios = [
    'index.php' => 'Lista principal de productos',
    'crear_producto.php' => 'Crear nuevo producto',
    'editar_producto.php' => 'Editar producto existente',
    'duplicar_producto.php' => 'Duplicar producto',
    'promos.php' => 'Gestión de promociones',
    'historial.php' => 'Historial de cambios',
    'ajuste_masivo.php' => 'Ajuste masivo de precios'
];

$archivos_ok = 0;
$total_archivos = count($archivos_necesarios);

foreach ($archivos_necesarios as $archivo => $descripcion) {
    if (file_exists($archivo)) {
        echo "✅ $archivo - $descripcion<br>";
        $archivos_ok++;
    } else {
        echo "❌ $archivo FALTANTE - $descripcion<br>";
    }
}

echo "<p><strong>Archivos del módulo: $archivos_ok/$total_archivos (" . round(($archivos_ok/$total_archivos)*100) . "%)</strong></p>";

echo "<h3>7. Probando estadísticas para el dashboard:</h3>";
try {
    $stats = [
        'productos_activos' => $pdo->query("SELECT COUNT(*) FROM productos WHERE activo = 1")->fetchColumn(),
        'productos_inactivos' => $pdo->query("SELECT COUNT(*) FROM productos WHERE activo = 0")->fetchColumn(),
        'promos_activas' => $pdo->query("SELECT COUNT(*) FROM promos WHERE activa = 1")->fetchColumn(),
        'total_productos' => $pdo->query("SELECT COUNT(*) FROM productos")->fetchColumn()
    ];
    
    echo "✅ Estadísticas calculadas correctamente:<br>";
    echo "- Productos activos: {$stats['productos_activos']}<br>";
    echo "- Productos inactivos: {$stats['productos_inactivos']}<br>";
    echo "- Promos activas: {$stats['promos_activas']}<br>";
    echo "- Total productos: {$stats['total_productos']}<br>";
    
} catch (Exception $e) {
    echo "❌ Error calculando estadísticas: " . $e->getMessage() . "<br>";
}

echo "<h3>8. Test de funcionalidades específicas:</h3>";

// Test categorías
try {
    $categorias = $pdo->query("SELECT DISTINCT categoria, COUNT(*) as cantidad FROM productos WHERE categoria IS NOT NULL GROUP BY categoria")->fetchAll();
    echo "✅ Categorías encontradas: " . count($categorias) . "<br>";
    
    foreach ($categorias as $cat) {
        echo "- {$cat['categoria']}: {$cat['cantidad']} productos<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Error verificando categorías: " . $e->getMessage() . "<br>";
}

echo "<hr><h3>🎯 RESUMEN FINAL:</h3>";

// Calcular score general
$score_total = 0;
$score_maximo = 8;

// Verificaciones
if (isset($pdo)) $score_total++;
if ($archivos_ok >= 6) $score_total++;
if (isset($stats) && $stats['total_productos'] > 0) $score_total++;
if (isset($productos) && !empty($productos)) $score_total++;
if (function_exists('formatPrice')) $score_total++;
if (function_exists('sanitize')) $score_total++;
if (function_exists('isLoggedIn')) $score_total++;
if (isset($categorias)) $score_total++;

$porcentaje = round(($score_total / $score_maximo) * 100);

if ($porcentaje >= 90) {
    echo "<div style='background:#d4edda; color:#155724; padding:15px; border-radius:5px; margin:10px 0;'>";
    echo "<h4>🎉 ¡EXCELENTE! ($porcentaje% - $score_total/$score_maximo)</h4>";
    echo "<p>El módulo de productos está funcionando perfectamente.</p>";
    echo "</div>";
} elseif ($porcentaje >= 70) {
    echo "<div style='background:#fff3cd; color:#856404; padding:15px; border-radius:5px; margin:10px 0;'>";
    echo "<h4>⚠️ BUENO ($porcentaje% - $score_total/$score_maximo)</h4>";
    echo "<p>El módulo funciona, pero hay algunos elementos que podrían mejorarse.</p>";
    echo "</div>";
} else {
    echo "<div style='background:#f8d7da; color:#721c24; padding:15px; border-radius:5px; margin:10px 0;'>";
    echo "<h4>❌ NECESITA ATENCIÓN ($porcentaje% - $score_total/$score_maximo)</h4>";
    echo "<p>Hay problemas importantes que deben solucionarse.</p>";
    echo "</div>";
}

echo "<h4>🔗 Enlaces para probar:</h4>";
echo "<div style='background:#e2e3e5; padding:15px; border-radius:5px; margin:10px 0;'>";

if (file_exists('index.php')) {
    echo "<p><a href='index.php' target='_blank' style='background:#007bff; color:white; padding:8px 15px; text-decoration:none; border-radius:3px; margin:2px;'>📋 Lista de Productos</a></p>";
}

if (file_exists('crear_producto.php')) {
    echo "<p><a href='crear_producto.php' target='_blank' style='background:#28a745; color:white; padding:8px 15px; text-decoration:none; border-radius:3px; margin:2px;'>➕ Crear Producto</a></p>";
}

if (file_exists('promos.php')) {
    echo "<p><a href='promos.php' target='_blank' style='background:#6f42c1; color:white; padding:8px 15px; text-decoration:none; border-radius:3px; margin:2px;'>🏷️ Gestionar Promos</a></p>";
}

if (file_exists('historial.php')) {
    echo "<p><a href='historial.php' target='_blank' style='background:#17a2b8; color:white; padding:8px 15px; text-decoration:none; border-radius:3px; margin:2px;'>📊 Historial de Cambios</a></p>";
}

echo "<p><a href='../../' style='background:#6c757d; color:white; padding:8px 15px; text-decoration:none; border-radius:3px; margin:2px;'>🏠 Volver al Admin Principal</a></p>";

echo "</div>";

echo "<h4>📋 Notas importantes:</h4>";
echo "<ul>";
echo "<li>Si todos los enlaces funcionan, el módulo está completamente operativo</li>";
echo "<li>Las estadísticas se actualizan automáticamente en el dashboard principal</li>";
echo "<li>Puedes crear productos, gestionar precios y configurar promociones</li>";
echo "<li>El historial registra todos los cambios para auditoría</li>";
echo "</ul>";

echo "<p><small><em>Test ejecutado el " . date('d/m/Y H:i:s') . " desde " . $_SERVER['HTTP_HOST'] . "</em></small></p>";
?>