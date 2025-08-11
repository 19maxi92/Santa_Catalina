<?php
// Archivo: admin/modules/productos/debug_500.php
// Diagnóstico completo para errores 500 en módulo productos

ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>🔍 Diagnóstico Error 500 - Módulo Productos</h1>";

echo "<h2>1. Verificando estructura de archivos:</h2>";

// Verificar que estamos en la ruta correcta
echo "<p><strong>Ruta actual:</strong> " . __DIR__ . "</p>";
echo "<p><strong>Archivo actual:</strong> " . __FILE__ . "</p>";

// Verificar archivos principales
$archivos_criticos = [
    '../../config.php' => 'Configuración principal',
    'index.php' => 'Lista de productos',
    'promos.php' => 'Gestión de promos',
    'historial.php' => 'Historial de cambios',
    'test.php' => 'Test del módulo'
];

foreach ($archivos_criticos as $archivo => $descripcion) {
    if (file_exists($archivo)) {
        echo "<p>✅ <strong>$archivo</strong> - $descripcion</p>";
    } else {
        echo "<p>❌ <strong>$archivo</strong> FALTANTE - $descripcion</p>";
    }
}

echo "<h2>2. Probando carga de config.php:</h2>";

try {
    require_once '../../config.php';
    echo "<p>✅ Config.php cargado correctamente</p>";
    echo "<p>✅ APP_NAME: " . APP_NAME . "</p>";
    echo "<p>✅ DB_NAME: " . DB_NAME . "</p>";
    
    if (function_exists('getConnection')) {
        echo "<p>✅ Función getConnection existe</p>";
    } else {
        echo "<p>❌ Función getConnection NO existe</p>";
    }
    
    if (function_exists('requireLogin')) {
        echo "<p>✅ Función requireLogin existe</p>";
    } else {
        echo "<p>❌ Función requireLogin NO existe</p>";
    }
    
} catch (Exception $e) {
    echo "<p>❌ Error cargando config.php: " . $e->getMessage() . "</p>";
    echo "<p>Error en línea: " . $e->getLine() . "</p>";
    echo "<p>Archivo: " . $e->getFile() . "</p>";
    
    // Mostrar las primeras líneas de config.php para debug
    if (file_exists('../../config.php')) {
        echo "<h3>Primeras líneas de config.php:</h3>";
        echo "<pre>";
        $lines = file('../../config.php');
        for ($i = 0; $i < min(10, count($lines)); $i++) {
            echo htmlspecialchars($lines[$i]);
        }
        echo "</pre>";
    }
    exit;
}

echo "<h2>3. Probando conexión a base de datos:</h2>";

try {
    $pdo = getConnection();
    echo "<p>✅ Conexión a BD exitosa</p>";
    
    // Probar consulta básica
    $stmt = $pdo->query("SELECT COUNT(*) FROM productos");
    $count = $stmt->fetchColumn();
    echo "<p>✅ Productos en BD: $count</p>";
    
} catch (Exception $e) {
    echo "<p>❌ Error de conexión BD: " . $e->getMessage() . "</p>";
    exit;
}

echo "<h2>4. Simulando carga de index.php:</h2>";

// Simular las primeras líneas de index.php
try {
    // Esta es la simulación de lo que hace index.php
    if (!function_exists('requireLogin')) {
        throw new Exception('Función requireLogin no disponible');
    }
    
    // No llamar requireLogin aquí porque no tenemos sesión
    echo "<p>✅ Función requireLogin disponible</p>";
    
    // Simular consulta principal de productos
    $sql = "SELECT p.*, 
                   COUNT(pe.id) as total_pedidos,
                   SUM(pe.cantidad) as unidades_vendidas,
                   SUM(pe.precio) as total_facturado,
                   MAX(pe.created_at) as ultima_venta
            FROM productos p
            LEFT JOIN pedidos pe ON pe.producto = p.nombre
            WHERE 1=1
            GROUP BY p.id ORDER BY p.orden_mostrar, p.nombre
            LIMIT 5";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([]);
    $productos = $stmt->fetchAll();
    
    echo "<p>✅ Consulta principal de productos exitosa</p>";
    echo "<p>✅ Productos encontrados: " . count($productos) . "</p>";
    
    if (!empty($productos)) {
        echo "<p>✅ Ejemplo producto: " . htmlspecialchars($productos[0]['nombre']) . "</p>";
    }
    
} catch (Exception $e) {
    echo "<p>❌ Error simulando index.php: " . $e->getMessage() . "</p>";
    echo "<p>Línea: " . $e->getLine() . "</p>";
}

echo "<h2>5. Verificando permisos de archivos:</h2>";

$permisos_info = [
    '../../config.php' => fileperms('../../config.php') ?? 'No existe',
    'index.php' => file_exists('index.php') ? fileperms('index.php') : 'No existe',
    '.' => fileperms('.') // Directorio actual
];

foreach ($permisos_info as $archivo => $permisos) {
    if ($permisos !== 'No existe') {
        $octal = substr(sprintf('%o', $permisos), -4);
        echo "<p>✅ $archivo - Permisos: $octal</p>";
    } else {
        echo "<p>❌ $archivo - No existe</p>";
    }
}

echo "<h2>6. Verificando variables de PHP:</h2>";

echo "<p><strong>PHP Version:</strong> " . phpversion() . "</p>";
echo "<p><strong>Display Errors:</strong> " . ini_get('display_errors') . "</p>";
echo "<p><strong>Error Reporting:</strong> " . error_reporting() . "</p>";
echo "<p><strong>Memory Limit:</strong> " . ini_get('memory_limit') . "</p>";

echo "<h2>7. Probando consultas específicas:</h2>";

try {
    // Probar tablas específicas del módulo
    $tablas = ['productos', 'promos', 'historial_precios'];
    
    foreach ($tablas as $tabla) {
        $stmt = $pdo->query("SELECT COUNT(*) FROM $tabla");
        $count = $stmt->fetchColumn();
        echo "<p>✅ Tabla '$tabla': $count registros</p>";
    }
    
    // Probar consulta con JOIN
    $stmt = $pdo->query("
        SELECT p.nombre, COUNT(pe.id) as pedidos 
        FROM productos p 
        LEFT JOIN pedidos pe ON pe.producto = p.nombre 
        GROUP BY p.id 
        LIMIT 3
    ");
    $resultados = $stmt->fetchAll();
    echo "<p>✅ Consulta con JOIN exitosa: " . count($resultados) . " resultados</p>";
    
} catch (Exception $e) {
    echo "<p>❌ Error en consultas específicas: " . $e->getMessage() . "</p>";
}

echo "<h2>8. Test final - Cargando fragmento de index.php:</h2>";

try {
    // Capturar cualquier salida de error
    ob_start();
    
    // Simular el inicio de index.php sin la parte de sesión
    $pdo = getConnection();
    
    // Simular la consulta de estadísticas
    $stats = $pdo->query("
        SELECT 
            COUNT(*) as total_productos,
            SUM(CASE WHEN activo = 1 THEN 1 ELSE 0 END) as productos_activos,
            SUM(CASE WHEN activo = 0 THEN 1 ELSE 0 END) as productos_inactivos
        FROM productos
    ")->fetch();
    
    $output = ob_get_clean();
    
    if ($output) {
        echo "<p>⚠️ Salida capturada: " . htmlspecialchars($output) . "</p>";
    }
    
    echo "<p>✅ Simulación de index.php exitosa</p>";
    echo "<p>✅ Total productos: " . $stats['total_productos'] . "</p>";
    echo "<p>✅ Productos activos: " . $stats['productos_activos'] . "</p>";
    
} catch (Exception $e) {
    $output = ob_get_clean();
    echo "<p>❌ Error en simulación final: " . $e->getMessage() . "</p>";
    if ($output) {
        echo "<p>Salida de error: " . htmlspecialchars($output) . "</p>";
    }
}

echo "<hr>";
echo "<h2>🔧 RECOMENDACIONES:</h2>";

if (file_exists('index.php') && file_exists('promos.php') && file_exists('historial.php')) {
    echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "<h3>✅ Archivos están presentes</h3>";
    echo "<p>El error 500 probablemente se debe a:</p>";
    echo "<ol>";
    echo "<li><strong>Problema de sesión:</strong> Verifica que la sesión esté iniciada como admin</li>";
    echo "<li><strong>Error de sintaxis:</strong> Revisa la sintaxis PHP en los archivos</li>";
    echo "<li><strong>Rutas incorrectas:</strong> Verifica las rutas de include</li>";
    echo "<li><strong>Configuración del servidor:</strong> Revisa logs de Apache/PHP</li>";
    echo "</ol>";
    echo "</div>";
} else {
    echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "<h3>❌ Faltan archivos críticos</h3>";
    echo "<p>Necesitas crear/subir los archivos faltantes del módulo.</p>";
    echo "</div>";
}

echo "<h3>📝 Próximos pasos:</h3>";
echo "<ol>";
echo "<li><a href='test.php' style='color: blue;'>Ejecutar test.php oficial</a></li>";
echo "<li><strong>Revisar logs de error:</strong> Buscar error_log o logs de Apache</li>";
echo "<li><strong>Probar acceso directo:</strong> Ir a cada archivo individualmente</li>";
echo "<li><strong>Verificar sesión admin:</strong> Asegurar login antes de acceder</li>";
echo "</ol>";

echo "<h3>🛠️ Debug adicional:</h3>";
echo "<p><strong>Si ves este archivo completamente, el problema NO es de PHP básico.</strong></p>";
echo "<p><strong>Ejecutado desde:</strong> " . $_SERVER['REQUEST_URI'] . "</p>";
echo "<p><strong>Servidor:</strong> " . $_SERVER['HTTP_HOST'] . "</p>";
echo "<p><strong>Timestamp:</strong> " . date('Y-m-d H:i:s') . "</p>";
?>