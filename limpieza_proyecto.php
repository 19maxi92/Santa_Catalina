<?php
/**
 * Script de Limpieza del Proyecto
 * Elimina archivos innecesarios y deja solo lo esencial para producción
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>🧹 Limpieza del Proyecto</h1>";
echo "<p><em>Eliminando archivos innecesarios para arrancar en limpio...</em></p>";

// Archivos a eliminar (debug, test, install, duplicados)
$archivos_eliminar = [
    // Archivos de diagnóstico y debug
    'admin/diagnostico_enlaces.php',
    'admin/modules/productos/debug_500.php',
    'admin/modules/productos/install.php',
    'admin/modules/productos/test.php',
    'admin/modules/impresion/test.php',
    
    // Archivos temporales o de prueba
    'admin/modules/productos/temp.php',
    'admin/modules/productos/backup.php',
    'admin/modules/productos/old_index.php',
    
    // Logs y archivos temporales
    'admin/error_log',
    'admin/debug.log',
    'admin/temp.txt',
    
    // Archivos de configuración temporales
    'admin/config_backup.php',
    'admin/config_old.php',
    
    // Archivos README o documentación de desarrollo
    'admin/README.txt',
    'admin/TODO.txt',
    'admin/INSTALL.txt'
];

// Directorios temporales o innecesarios
$directorios_eliminar = [
    'admin/temp/',
    'admin/backup/',
    'admin/old/',
    'admin/modules/temp/',
    'admin/logs/'
];

// Contadores
$archivos_eliminados = 0;
$directorios_eliminados = 0;
$errores = 0;

echo "<h2>🗑️ Eliminando archivos innecesarios:</h2>";

foreach ($archivos_eliminar as $archivo) {
    if (file_exists($archivo)) {
        if (unlink($archivo)) {
            echo "<p>✅ Eliminado: <code>$archivo</code></p>";
            $archivos_eliminados++;
        } else {
            echo "<p>❌ Error eliminando: <code>$archivo</code></p>";
            $errores++;
        }
    } else {
        echo "<p>⏭️ No existe: <code>$archivo</code></p>";
    }
}

echo "<h2>📁 Eliminando directorios vacíos:</h2>";

foreach ($directorios_eliminar as $directorio) {
    if (is_dir($directorio)) {
        if (rmdir($directorio)) {
            echo "<p>✅ Eliminado directorio: <code>$directorio</code></p>";
            $directorios_eliminados++;
        } else {
            echo "<p>⚠️ No se pudo eliminar (no vacío): <code>$directorio</code></p>";
        }
    } else {
        echo "<p>⏭️ No existe: <code>$directorio</code></p>";
    }
}

echo "<h2>🔍 Verificando estructura final:</h2>";

// Estructura esperada después de la limpieza
$estructura_final = [
    'admin/' => 'Directorio principal del admin',
    'admin/config.php' => 'Configuración principal',
    'admin/index.php' => 'Dashboard principal',
    'admin/login.php' => 'Sistema de login',
    'admin/logout.php' => 'Cerrar sesión',
    
    // Módulo de productos
    'admin/modules/' => 'Directorio de módulos',
    'admin/modules/productos/' => 'Módulo de productos',
    'admin/modules/productos/index.php' => 'Lista de productos',
    'admin/modules/productos/crear_producto.php' => 'Crear producto',
    'admin/modules/productos/editar_producto.php' => 'Editar producto',
    'admin/modules/productos/duplicar_producto.php' => 'Duplicar producto',
    'admin/modules/productos/promos.php' => 'Gestión de promociones',
    'admin/modules/productos/historial.php' => 'Historial de cambios',
    'admin/modules/productos/ajuste_masivo.php' => 'Ajuste masivo de precios',
    
    // Módulo de pedidos
    'admin/modules/pedidos/' => 'Módulo de pedidos',
    'admin/modules/pedidos/index.php' => 'Lista de pedidos',
    'admin/modules/pedidos/ver_pedidos.php' => 'Ver detalles de pedidos',
    
    // Módulo de impresión
    'admin/modules/impresion/' => 'Módulo de impresión',
    'admin/modules/impresion/comanda.php' => 'Imprimir comandas',
    'admin/modules/impresion/config.php' => 'Configuración de impresión'
];

$archivos_ok = 0;
$archivos_faltantes = 0;

echo "<h3>📋 Archivos esenciales:</h3>";

foreach ($estructura_final as $archivo => $descripcion) {
    if (file_exists($archivo)) {
        echo "<p>✅ <strong>$archivo</strong> - $descripcion</p>";
        $archivos_ok++;
    } else {
        echo "<p>❌ <strong>$archivo</strong> - $descripcion <span style='color:red;'>(FALTANTE)</span></p>";
        $archivos_faltantes++;
    }
}

echo "<h2>📊 Resumen de la limpieza:</h2>";

$total_archivos_estructura = count($estructura_final);
$porcentaje_completo = round(($archivos_ok / $total_archivos_estructura) * 100);

echo "<div style='background:#f8f9fa; padding:20px; border-radius:8px; margin:15px 0;'>";
echo "<h3>🎯 Estadísticas:</h3>";
echo "<ul>";
echo "<li><strong>Archivos eliminados:</strong> $archivos_eliminados</li>";
echo "<li><strong>Directorios eliminados:</strong> $directorios_eliminados</li>";
echo "<li><strong>Errores:</strong> $errores</li>";
echo "<li><strong>Archivos esenciales presentes:</strong> $archivos_ok/$total_archivos_estructura ($porcentaje_completo%)</li>";
echo "</ul>";
echo "</div>";

if ($porcentaje_completo >= 90) {
    echo "<div style='background:#d4edda; color:#155724; padding:15px; border-radius:5px; margin:10px 0;'>";
    echo "<h4>🎉 PROYECTO LIMPIO Y COMPLETO</h4>";
    echo "<p>✅ La estructura está completa y lista para producción</p>";
    echo "<p>✅ Se eliminaron $archivos_eliminados archivos innecesarios</p>";
    echo "</div>";
} elseif ($porcentaje_completo >= 70) {
    echo "<div style='background:#fff3cd; color:#856404; padding:15px; border-radius:5px; margin:10px 0;'>";
    echo "<h4>⚠️ PROYECTO MAYORMENTE LIMPIO</h4>";
    echo "<p>El proyecto está limpio pero faltan algunos archivos importantes</p>";
    echo "</div>";
} else {
    echo "<div style='background:#f8d7da; color:#721c24; padding:15px; border-radius:5px; margin:10px 0;'>";
    echo "<h4>❌ NECESITA ATENCIÓN</h4>";
    echo "<p>Faltan archivos importantes del proyecto</p>";
    echo "</div>";
}

echo "<h2>🚀 Próximos pasos:</h2>";

echo "<div style='background:#e2e3e5; padding:15px; border-radius:5px; margin:10px 0;'>";
echo "<h4>1. Verificación post-limpieza:</h4>";
echo "<ol>";
echo "<li>Probar acceso al admin: <a href='admin/' style='color:blue;'>admin/</a></li>";
echo "<li>Verificar módulo de productos: <a href='admin/modules/productos/' style='color:blue;'>admin/modules/productos/</a></li>";
echo "<li>Revisar configuración: <a href='admin/config.php' style='color:blue;'>admin/config.php</a></li>";
echo "</ol>";

echo "<h4>2. Configuración para producción:</h4>";
echo "<ul>";
echo "<li>Desactivar display_errors en config.php</li>";
echo "<li>Configurar logs de error apropiados</li>";
echo "<li>Verificar permisos de archivos</li>";
echo "<li>Hacer backup de la configuración actual</li>";
echo "</ul>";

echo "<h4>3. Preparación para migración a Linux:</h4>";
echo "<ul>";
echo "<li>Documentar dependencias necesarias</li>";
echo "<li>Revisar rutas de archivos (usar '/' en lugar de '\\')</li>";
echo "<li>Verificar compatibilidad de funciones PHP</li>";
echo "<li>Preparar script de instalación para Linux</li>";
echo "</ul>";
echo "</div>";

echo "<h2>📝 Archivos de configuración importantes:</h2>";

echo "<div style='background:#f8f9fa; padding:15px; border-radius:5px; font-family:monospace; font-size:12px;'>";
echo "<strong>Para revisar manualmente:</strong><br>";
echo "- admin/config.php (configuración principal)<br>";
echo "- admin/.htaccess (reglas del servidor)<br>";
echo "- admin/modules/impresion/config.php (configuración de impresora)<br>";
echo "</div>";

echo "<hr>";
echo "<p><strong>✨ Proyecto limpio y listo!</strong></p>";
echo "<p><em>Ejecutado el " . date('d/m/Y H:i:s') . "</em></p>";
?>