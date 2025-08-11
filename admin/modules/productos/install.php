<?php
// Script de instalación automática del módulo de productos
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h2>🚀 Instalador del Módulo de Productos</h2>";

try {
    require_once '../../config.php';
    $pdo = getConnection();
    echo "<p>✅ Conexión a base de datos exitosa</p>";
} catch (Exception $e) {
    echo "<p>❌ Error de conexión: " . $e->getMessage() . "</p>";
    exit;
}

echo "<h3>Paso 1: Verificando tablas existentes...</h3>";

// Función para verificar si una tabla existe
function tablaExiste($pdo, $tabla) {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE '$tabla'");
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        return false;
    }
}

// Función para verificar si una columna existe
function columnaExiste($pdo, $tabla, $columna) {
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM $tabla LIKE '$columna'");
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        return false;
    }
}

// Verificar tabla productos
if (tablaExiste($pdo, 'productos')) {
    echo "<p>✅ Tabla 'productos' existe</p>";
    
    // Verificar columnas necesarias en productos
    $columnas_productos = ['categoria', 'descripcion', 'orden_mostrar', 'updated_by'];
    foreach ($columnas_productos as $columna) {
        if (columnaExiste($pdo, 'productos', $columna)) {
            echo "<p>✅ Columna 'productos.$columna' existe</p>";
        } else {
            echo "<p>⚠️ Columna 'productos.$columna' no existe - se agregará</p>";
        }
    }
} else {
    echo "<p>❌ Tabla 'productos' no existe - se debe crear primero</p>";
    exit;
}

echo "<h3>Paso 2: Creando tablas faltantes...</h3>";

// Crear tabla promos
if (!tablaExiste($pdo, 'promos')) {
    try {
        $sql = "CREATE TABLE `promos` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `nombre` varchar(100) NOT NULL,
          `descripcion` text DEFAULT NULL,
          `precio_efectivo` decimal(10,2) NOT NULL,
          `precio_transferencia` decimal(10,2) NOT NULL,
          `fecha_inicio` date DEFAULT NULL,
          `fecha_fin` date DEFAULT NULL,
          `activa` tinyint(1) DEFAULT 1,
          `created_at` timestamp NULL DEFAULT current_timestamp(),
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $pdo->exec($sql);
        echo "<p>✅ Tabla 'promos' creada correctamente</p>";
    } catch (Exception $e) {
        echo "<p>❌ Error creando tabla 'promos': " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p>✅ Tabla 'promos' ya existe</p>";
}

// Crear tabla historial_precios
if (!tablaExiste($pdo, 'historial_precios')) {
    try {
        $sql = "CREATE TABLE `historial_precios` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `producto_id` int(11) DEFAULT NULL,
          `tipo` varchar(50) NOT NULL DEFAULT 'producto',
          `precio_anterior_efectivo` decimal(10,2) DEFAULT NULL,
          `precio_anterior_transferencia` decimal(10,2) DEFAULT NULL,
          `precio_nuevo_efectivo` decimal(10,2) DEFAULT NULL,
          `precio_nuevo_transferencia` decimal(10,2) DEFAULT NULL,
          `motivo` text DEFAULT NULL,
          `usuario` varchar(100) DEFAULT NULL,
          `fecha_cambio` timestamp NULL DEFAULT current_timestamp(),
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $pdo->exec($sql);
        echo "<p>✅ Tabla 'historial_precios' creada correctamente</p>";
    } catch (Exception $e) {
        echo "<p>❌ Error creando tabla 'historial_precios': " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p>✅ Tabla 'historial_precios' ya existe</p>";
}

echo "<h3>Paso 3: Agregando columnas faltantes...</h3>";

// Agregar columnas faltantes a productos
$columnas_agregar = [
    'categoria' => "varchar(50) DEFAULT 'Standard'",
    'descripcion' => "text DEFAULT NULL",
    'orden_mostrar' => "int(11) DEFAULT 0",
    'updated_by' => "varchar(100) DEFAULT NULL"
];

foreach ($columnas_agregar as $columna => $definicion) {
    if (!columnaExiste($pdo, 'productos', $columna)) {
        try {
            $sql = "ALTER TABLE productos ADD COLUMN $columna $definicion";
            $pdo->exec($sql);
            echo "<p>✅ Columna '$columna' agregada a tabla productos</p>";
        } catch (Exception $e) {
            echo "<p>❌ Error agregando columna '$columna': " . $e->getMessage() . "</p>";
        }
    }
}

echo "<h3>Paso 4: Insertando datos de ejemplo...</h3>";

// Insertar promos de ejemplo
try {
    $count_promos = $pdo->query("SELECT COUNT(*) FROM promos")->fetchColumn();
    
    if ($count_promos == 0) {
        $sql = "INSERT INTO promos (nombre, descripcion, precio_efectivo, precio_transferencia, activa) VALUES 
                ('Promo Fin de Semana', '24 Surtidos Premium a precio especial', 19000, 20000, 1),
                ('Combo Oficina', '48 Jamón y Queso + 24 Surtidos', 32000, 34000, 1)";
        
        $pdo->exec($sql);
        echo "<p>✅ Promos de ejemplo insertadas</p>";
    } else {
        echo "<p>✅ Ya existen $count_promos promos en la base de datos</p>";
    }
} catch (Exception $e) {
    echo "<p>❌ Error insertando promos: " . $e->getMessage() . "</p>";
}

echo "<h3>Paso 5: Verificación final...</h3>";

// Verificación final
try {
    $productos_count = $pdo->query("SELECT COUNT(*) FROM productos")->fetchColumn();
    $promos_count = $pdo->query("SELECT COUNT(*) FROM promos")->fetchColumn();
    $historial_count = $pdo->query("SELECT COUNT(*) FROM historial_precios")->fetchColumn();
    
    echo "<p>✅ Productos en BD: $productos_count</p>";
    echo "<p>✅ Promos en BD: $promos_count</p>";
    echo "<p>✅ Registros en historial: $historial_count</p>";
    
    // Probar consulta compleja del módulo
    $stmt = $pdo->query("
        SELECT p.*, 
               COUNT(pe.id) as total_pedidos,
               SUM(pe.cantidad) as unidades_vendidas,
               SUM(pe.precio) as total_facturado
        FROM productos p
        LEFT JOIN pedidos pe ON pe.producto = p.nombre
        GROUP BY p.id 
        LIMIT 1
    ");
    
    if ($stmt) {
        echo "<p>✅ Consulta del módulo funciona correctamente</p>";
    }
    
} catch (Exception $e) {
    echo "<p>❌ Error en verificación: " . $e->getMessage() . "</p>";
}

echo "<hr><h3>🎉 Instalación Completada</h3>";
echo "<p><strong>El módulo de productos está listo para usar!</strong></p>";

echo "<h4>Próximos pasos:</h4>";
echo "<ol>";
echo "<li><a href='test.php' style='color:blue;'>🔗 Ejecutar test completo</a></li>";
echo "<li><a href='index.php' style='color:green;'>🔗 Acceder al módulo de productos</a></li>";
echo "<li><a href='../../' style='color:orange;'>🔗 Volver al admin principal</a></li>";
echo "</ol>";

echo "<h4>Archivos disponibles:</h4>";
$archivos = [
    'index.php' => 'Lista de productos',
    'crear_producto.php' => 'Crear producto',
    'editar_producto.php' => 'Editar producto',
    'duplicar_producto.php' => 'Duplicar producto',
    'promos.php' => 'Gestión de promos',
    'historial.php' => 'Historial de cambios',
    'ajuste_masivo.php' => 'Ajuste masivo de precios'
];

echo "<ul>";
foreach ($archivos as $archivo => $descripcion) {
    $existe = file_exists($archivo) ? '✅' : '❌';
    $link = file_exists($archivo) ? "<a href='$archivo' style='color:blue;'>$archivo</a>" : $archivo;
    echo "<li>$existe $link - $descripcion</li>";
}
echo "</ul>";
?>