<?php
/**
 * Auditoría Completa del Proyecto
 * Analiza y categoriza todos los archivos del proyecto
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>🔍 Auditoría Completa del Proyecto</h1>";
echo "<p><em>Analizando toda la estructura de archivos...</em></p>";

// Función para escanear directorios recursivamente
function escanearDirectorio($directorio, $prefijo = '') {
    $archivos = [];
    if (is_dir($directorio)) {
        $items = scandir($directorio);
        foreach ($items as $item) {
            if ($item !== '.' && $item !== '..') {
                $ruta_completa = $directorio . '/' . $item;
                if (is_dir($ruta_completa)) {
                    $archivos = array_merge($archivos, escanearDirectorio($ruta_completa, $prefijo . $item . '/'));
                } else {
                    $archivos[] = $prefijo . $item;
                }
            }
        }
    }
    return $archivos;
}

// Categorías de archivos
$categorias = [
    'ESENCIALES' => [
        'descripcion' => 'Archivos críticos para el funcionamiento',
        'patrones' => ['config.php', 'index.php', 'login.php', 'logout.php'],
        'archivos' => []
    ],
    'MODULOS' => [
        'descripcion' => 'Archivos de módulos funcionales',
        'patrones' => ['modules/', 'crear_', 'editar_', 'ver_', 'promos.php', 'historial.php', 'comanda.php'],
        'archivos' => []
    ],
    'DEBUG_TEST' => [
        'descripcion' => 'Archivos de debug, test e instalación (ELIMINAR)',
        'patrones' => ['debug', 'test.php', 'install.php', 'diagnostico', '_500.php'],
        'archivos' => []
    ],
    'TEMPORALES' => [
        'descripcion' => 'Archivos temporales o backup (ELIMINAR)',
        'patrones' => ['temp', 'backup', 'old', '_bak', '.log', '.tmp'],
        'archivos' => []
    ],
    'ASSETS' => [
        'descripcion' => 'Recursos estáticos (CSS, JS, imágenes)',
        'patrones' => ['.css', '.js', '.png', '.jpg', '.jpeg', '.gif', '.ico'],
        'archivos' => []
    ],
    'OTROS' => [
        'descripcion' => 'Otros archivos (revisar manualmente)',
        'patrones' => [],
        'archivos' => []
    ]
];

// Escanear desde admin/
$directorio_base = 'admin';
$todos_archivos = escanearDirectorio($directorio_base);

echo "<h2>📁 Archivos encontrados: " . count($todos_archivos) . "</h2>";

// Categorizar archivos
foreach ($todos_archivos as $archivo) {
    $categorizado = false;
    
    foreach ($categorias as $categoria => &$info) {
        if ($categoria === 'OTROS') continue; // OTROS es la categoría por defecto
        
        foreach ($info['patrones'] as $patron) {
            if (strpos($archivo, $patron) !== false) {
                $info['archivos'][] = $archivo;
                $categorizado = true;
                break 2;
            }
        }
    }
    
    // Si no se categorizó, va a OTROS
    if (!$categorizado) {
        $categorias['OTROS']['archivos'][] = $archivo;
    }
}

// Mostrar resultados por categoría
foreach ($categorias as $categoria => $info) {
    $cantidad = count($info['archivos']);
    
    if ($cantidad === 0) continue;
    
    $color = '';
    $icono = '';
    $accion = '';
    
    switch ($categoria) {
        case 'ESENCIALES':
            $color = 'background:#d4edda; color:#155724;';
            $icono = '🟢';
            $accion = 'MANTENER';
            break;
        case 'MODULOS':
            $color = 'background:#cce7ff; color:#004085;';
            $icono = '🔵';
            $accion = 'MANTENER';
            break;
        case 'DEBUG_TEST':
            $color = 'background:#f8d7da; color:#721c24;';
            $icono = '🔴';
            $accion = 'ELIMINAR';
            break;
        case 'TEMPORALES':
            $color = 'background:#fff3cd; color:#856404;';
            $icono = '🟡';
            $accion = 'ELIMINAR';
            break;
        case 'ASSETS':
            $color = 'background:#e2e3e5; color:#383d41;';
            $icono = '⚪';
            $accion = 'REVISAR';
            break;
        default:
            $color = 'background:#f8f9fa; color:#6c757d;';
            $icono = '⚫';
            $accion = 'REVISAR';
            break;
    }
    
    echo "<div style='$color padding:15px; border-radius:5px; margin:10px 0;'>";
    echo "<h3>$icono $categoria ($cantidad archivos) - $accion</h3>";
    echo "<p><em>{$info['descripcion']}</em></p>";
    
    echo "<details>";
    echo "<summary>Ver archivos</summary>";
    echo "<ul style='margin:10px 0; font-family:monospace; font-size:12px;'>";
    
    foreach ($info['archivos'] as $archivo) {
        $ruta_completa = $directorio_base . '/' . $archivo;
        $size = file_exists($ruta_completa) ? filesize($ruta_completa) : 0;
        $size_formatted = $size > 1024 ? round($size/1024, 1) . 'KB' : $size . 'B';
        
        echo "<li>$archivo <span style='color:#6c757d;'>($size_formatted)</span></li>";
    }
    
    echo "</ul>";
    echo "</details>";
    echo "</div>";
}

echo "<h2>📊 Análisis de Limpieza</h2>";

$eliminar_debug = count($categorias['DEBUG_TEST']['archivos']);
$eliminar_temp = count($categorias['TEMPORALES']['archivos']);
$total_eliminar = $eliminar_debug + $eliminar_temp;
$mantener = count($categorias['ESENCIALES']['archivos']) + count($categorias['MODULOS']['archivos']);
$revisar = count($categorias['ASSETS']['archivos']) + count($categorias['OTROS']['archivos']);

echo "<div style='background:#f8f9fa; padding:20px; border-radius:8px; margin:15px 0;'>";
echo "<h3>🎯 Recomendaciones:</h3>";
echo "<table border='1' cellpadding='8' cellspacing='0' style='width:100%; border-collapse:collapse;'>";
echo "<tr style='background:#343a40; color:white;'>";
echo "<th>Categoría</th><th>Cantidad</th><th>Acción</th><th>Impacto</th>";
echo "</tr>";

echo "<tr style='background:#d4edda;'>";
echo "<td><strong>🟢 MANTENER</strong></td>";
echo "<td>$mantener archivos</td>";
echo "<td>No tocar</td>";
echo "<td>Archivos críticos del sistema</td>";
echo "</tr>";

echo "<tr style='background:#f8d7da;'>";
echo "<td><strong>🔴 ELIMINAR</strong></td>";
echo "<td>$total_eliminar archivos</td>";
echo "<td>Borrar completamente</td>";
echo "<td>Libera espacio y reduce confusión</td>";
echo "</tr>";

echo "<tr style='background:#fff3cd;'>";
echo "<td><strong>⚪ REVISAR</strong></td>";
echo "<td>$revisar archivos</td>";
echo "<td>Evaluar manualmente</td>";
echo "<td>Pueden ser necesarios o no</td>";
echo "</tr>";

echo "</table>";
echo "</div>";

// Script de limpieza automática
echo "<h2>🤖 Script de Limpieza Automática</h2>";

echo "<div style='background:#f8f9fa; border:1px solid #e9ecef; padding:15px; border-radius:5px; font-family:monospace; font-size:12px;'>";
echo "<strong>Archivos que se eliminarían automáticamente:</strong><br><br>";

$archivos_eliminar = array_merge($categorias['DEBUG_TEST']['archivos'], $categorias['TEMPORALES']['archivos']);

foreach ($archivos_eliminar as $archivo) {
    echo "admin/$archivo<br>";
}

echo "<br><strong>Total a eliminar: " . count($archivos_eliminar) . " archivos</strong>";
echo "</div>";

echo "<h2>🚀 Próximos Pasos</h2>";

echo "<div style='background:#e2e3e5; padding:15px; border-radius:5px; margin:10px 0;'>";
echo "<h4>1. Ejecutar limpieza automática:</h4>";
echo "<p>Ejecutar el script de limpieza que generé antes para eliminar archivos innecesarios</p>";

echo "<h4>2. Revisar archivos marcados como 'REVISAR':</h4>";
echo "<ul>";
foreach ($categorias['OTROS']['archivos'] as $archivo) {
    echo "<li><code>admin/$archivo</code></li>";
}
echo "</ul>";

echo "<h4>3. Verificar funcionalidad:</h4>";
echo "<ul>";
echo "<li>Probar login al admin</li>";
echo "<li>Verificar módulos principales</li>";
echo "<li>Comprobar conexión a base de datos</li>";
echo "</ul>";

echo "<h4>4. Preparar para Linux:</h4>";
echo "<ul>";
echo "<li>Documentar dependencias</li>";
echo "<li>Revisar rutas de archivos</li>";
echo "<li>Preparar script de migración</li>";
echo "</ul>";
echo "</div>";

// Mostrar estructura recomendada final
echo "<h2>🏗️ Estructura Recomendada Final</h2>";

$estructura_ideal = [
    'admin/' => 'Directorio raíz del admin',
    'admin/config.php' => 'Configuración principal',
    'admin/index.php' => 'Dashboard',
    'admin/login.php' => 'Login',
    'admin/logout.php' => 'Logout',
    'admin/modules/' => 'Módulos del sistema',
    'admin/modules/productos/' => 'Gestión de productos',
    'admin/modules/pedidos/' => 'Gestión de pedidos',
    'admin/modules/impresion/' => 'Sistema de impresión'
];

echo "<div style='background:#e7f3ff; padding:15px; border-radius:5px; margin:10px 0;'>";
echo "<h4>📋 Estructura limpia ideal:</h4>";
echo "<ul>";
foreach ($estructura_ideal as $ruta => $desc) {
    $existe = is_dir($ruta) || file_exists($ruta) ? '✅' : '❌';
    echo "<li>$existe <code>$ruta</code> - $desc</li>";
}
echo "</ul>";
echo "</div>";

echo "<hr>";
echo "<p><strong>✨ Auditoría completada!</strong></p>";
echo "<p><em>Ejecutado el " . date('d/m/Y H:i:s') . "</em></p>";
?>