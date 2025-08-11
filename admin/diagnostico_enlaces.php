<?php
// Diagnóstico de enlaces del módulo de productos
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h2>🔍 Diagnóstico de Enlaces - Módulo de Productos</h2>";

// Definir la estructura de directorios esperada
$estructura_esperada = [
    'modules/' => 'Directorio de módulos',
    'modules/productos/' => 'Directorio del módulo de productos',
    'modules/productos/index.php' => 'Lista principal de productos',
    'modules/productos/crear_producto.php' => 'Crear nuevo producto',
    'modules/productos/editar_producto.php' => 'Editar producto',
    'modules/productos/duplicar_producto.php' => 'Duplicar producto',
    'modules/productos/promos.php' => 'Gestión de promociones',
    'modules/productos/historial.php' => 'Historial de cambios',
    'modules/productos/ajuste_masivo.php' => 'Ajuste masivo de precios',
    'modules/productos/test.php' => 'Test del módulo',
    'modules/productos/install.php' => 'Instalador'
];

echo "<h3>1. Verificando estructura de archivos:</h3>";

$archivos_faltantes = [];
$archivos_existentes = [];

foreach ($estructura_esperada as $ruta => $descripcion) {
    if (file_exists($ruta)) {
        echo "<p>✅ <strong>$ruta</strong> - $descripcion</p>";
        $archivos_existentes[] = $ruta;
    } else {
        echo "<p>❌ <strong>$ruta</strong> - $descripcion <span style='color:red;'>(FALTANTE)</span></p>";
        $archivos_faltantes[] = $ruta;
    }
}

echo "<h3>2. Verificando permisos de archivos:</h3>";

foreach ($archivos_existentes as $archivo) {
    if (is_readable($archivo)) {
        echo "<p>✅ $archivo - Legible</p>";
    } else {
        echo "<p>❌ $archivo - NO legible (revisar permisos)</p>";
    }
}

echo "<h3>3. Probando conexión a base de datos:</h3>";

try {
    require_once 'config.php';
    $pdo = getConnection();
    echo "<p>✅ Conexión a base de datos exitosa</p>";
    
    // Verificar tablas necesarias
    $tablas_necesarias = ['productos', 'promos', 'historial_precios'];
    
    foreach ($tablas_necesarias as $tabla) {
        try {
            $stmt = $pdo->query("SHOW TABLES LIKE '$tabla'");
            if ($stmt->rowCount() > 0) {
                $count = $pdo->query("SELECT COUNT(*) FROM $tabla")->fetchColumn();
                echo "<p>✅ Tabla '$tabla' existe con $count registros</p>";
            } else {
                echo "<p>❌ Tabla '$tabla' NO existe</p>";
            }
        } catch (Exception $e) {
            echo "<p>❌ Error verificando tabla '$tabla': " . $e->getMessage() . "</p>";
        }
    }
    
} catch (Exception $e) {
    echo "<p>❌ Error de conexión a BD: " . $e->getMessage() . "</p>";
}

echo "<h3>4. Generando enlaces de prueba:</h3>";

$enlaces_productos = [
    'modules/productos/index.php' => 'Lista de productos',
    'modules/productos/crear_producto.php' => 'Crear producto',
    'modules/productos/promos.php' => 'Gestión de promos',
    'modules/productos/historial.php' => 'Historial de cambios',
    'modules/productos/test.php' => 'Test del módulo'
];

echo "<div style='background:#f0f0f0; padding:15px; border-radius:5px; margin:10px 0;'>";
echo "<h4>Enlaces para probar manualmente:</h4>";

foreach ($enlaces_productos as $enlace => $descripcion) {
    if (file_exists($enlace)) {
        echo "<p><a href='$enlace' target='_blank' style='color:blue; text-decoration:underline;'>🔗 $enlace</a> - $descripcion</p>";
    } else {
        echo "<p><span style='color:red;'>❌ $enlace - ARCHIVO NO EXISTE</span></p>";
    }
}
echo "</div>";

echo "<h3>5. Verificando configuración del servidor:</h3>";

// Verificar configuración PHP
echo "<p>✅ Versión PHP: " . phpversion() . "</p>";
echo "<p>✅ Directorio actual: " . getcwd() . "</p>";
echo "<p>✅ Documento raíz: " . $_SERVER['DOCUMENT_ROOT'] . "</p>";

// Verificar .htaccess
if (file_exists('.htaccess')) {
    echo "<p>✅ Archivo .htaccess existe</p>";
} else {
    echo "<p>⚠️ Archivo .htaccess no existe (puede ser normal)</p>";
}

echo "<h3>6. Información del request actual:</h3>";

echo "<p><strong>URL actual:</strong> " . $_SERVER['REQUEST_URI'] . "</p>";
echo "<p><strong>Host:</strong> " . $_SERVER['HTTP_HOST'] . "</p>";
echo "<p><strong>Método:</strong> " . $_SERVER['REQUEST_METHOD'] . "</p>";

echo "<h3>7. Resumen y recomendaciones:</h3>";

if (empty($archivos_faltantes)) {
    echo "<div style='background:#d4edda; color:#155724; padding:15px; border-radius:5px; margin:10px 0;'>";
    echo "<h4>✅ ¡Excelente! Todos los archivos están presentes</h4>";
    echo "<p>El módulo de productos debería funcionar correctamente.</p>";
    echo "<p><strong>Próximo paso:</strong> <a href='modules/productos/test.php' style='color:blue;'>Ejecutar test completo del módulo</a></p>";
    echo "</div>";
} else {
    echo "<div style='background:#f8d7da; color:#721c24; padding:15px; border-radius:5px; margin:10px 0;'>";
    echo "<h4>❌ Faltan archivos importantes:</h4>";
    echo "<ul>";
    foreach ($archivos_faltantes as $archivo) {
        echo "<li>$archivo</li>";
    }
    echo "</ul>";
    echo "<p><strong>Solución:</strong> Subir los archivos faltantes al servidor o ejecutar el instalador.</p>";
    echo "</div>";
}

// Mostrar información de debug adicional
echo "<h3>8. Debug adicional:</h3>";

echo "<details>";
echo "<summary>Ver información detallada de PHP</summary>";
echo "<pre>";
echo "Error reporting: " . error_reporting() . "\n";
echo "Display errors: " . ini_get('display_errors') . "\n";
echo "Memory limit: " . ini_get('memory_limit') . "\n";
echo "Max execution time: " . ini_get('max_execution_time') . "\n";
echo "Upload max filesize: " . ini_get('upload_max_filesize') . "\n";
echo "</pre>";
echo "</details>";

echo "<hr>";
echo "<h4>🎯 Conclusión:</h4>";
echo "<p>Si todos los elementos muestran ✅, el problema puede estar en:</p>";
echo "<ul>";
echo "<li>Configuración del servidor web</li>";
echo "<li>Permisos de archivos</li>";
echo "<li>Cache del navegador</li>";
echo "<li>Configuración de .htaccess</li>";
echo "</ul>";

echo "<h4>📞 Acciones recomendadas:</h4>";
echo "<ol>";
echo "<li><a href='modules/productos/test.php' style='color:blue;'>Ejecutar test del módulo</a></li>";
echo "<li><a href='modules/productos/index.php' style='color:green;'>Probar acceso directo al módulo</a></li>";
echo "<li><a href='index.php' style='color:orange;'>Volver al dashboard principal</a></li>";
echo "</ol>";
?>