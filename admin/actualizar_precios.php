<?php
/**
 * Script para actualizar todos los precios en el admin
 * admin/actualizar_precios.php
 */

require_once 'config.php';
session_start();

// Verificar que sea admin
if (!isset($_SESSION['admin_user']) && !isset($_SESSION['admin_username'])) {
    echo "<h1>❌ Acceso Denegado</h1>";
    echo "<p>Debes iniciar sesión como administrador para ejecutar este script.</p>";
    echo "<p><a href='login.php'>Iniciar Sesión</a></p>";
    exit;
}

echo "<h1>🔄 Actualizando Precios del Admin</h1>";

try {
    $pdo = getConnection();
    
    // Nuevos precios según la carta actualizada
    $precios_nuevos = [
        '24 Jamón y Queso' => ['efectivo' => 12500, 'transferencia' => 12500],
        '48 Jamón y Queso' => ['efectivo' => 22000, 'transferencia' => 24000],
        
        '24 Surtidos Clásicos' => ['efectivo' => 12500, 'transferencia' => 12500], 
        '48 Surtidos Clásicos' => ['efectivo' => 20000, 'transferencia' => 22000],
        
        '24 Surtidos Especiales' => ['efectivo' => 12500, 'transferencia' => 12500],
        '48 Surtidos Especiales' => ['efectivo' => 22000, 'transferencia' => 24000],
        
        '24 Surtidos Premium' => ['efectivo' => 22500, 'transferencia' => 22500],
        '48 Surtidos Premium' => ['efectivo' => 44000, 'transferencia' => 44000],
        
        // Nuevos productos Surtidos Elegidos
        '8 Surtidos Elegidos' => ['efectivo' => 4200, 'transferencia' => 4200],
        '16 Surtidos Elegidos' => ['efectivo' => 8400, 'transferencia' => 8400],
        '24 Surtidos Elegidos' => ['efectivo' => 12500, 'transferencia' => 12500],
        '32 Surtidos Elegidos' => ['efectivo' => 16700, 'transferencia' => 16700],
        '40 Surtidos Elegidos' => ['efectivo' => 20900, 'transferencia' => 20900],
        '48 Surtidos Elegidos' => ['efectivo' => 25000, 'transferencia' => 25000]
    ];
    
    echo "<h2>📋 Actualizando productos existentes:</h2>";
    
    $actualizados = 0;
    $creados = 0;
    
    foreach ($precios_nuevos as $nombre => $precios) {
        // Verificar si el producto existe
        $stmt = $pdo->prepare("SELECT id, precio_efectivo, precio_transferencia FROM productos WHERE nombre = ?");
        $stmt->execute([$nombre]);
        $producto_existente = $stmt->fetch();
        
        if ($producto_existente) {
            // Actualizar producto existente
            
            // Guardar en historial si cambió el precio
            if ($producto_existente['precio_efectivo'] != $precios['efectivo'] || 
                $producto_existente['precio_transferencia'] != $precios['transferencia']) {
                
                $stmt = $pdo->prepare("
                    INSERT INTO historial_precios 
                    (producto_id, tipo, precio_anterior_efectivo, precio_anterior_transferencia, 
                     precio_nuevo_efectivo, precio_nuevo_transferencia, motivo, usuario, fecha_cambio) 
                    VALUES (?, 'producto', ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $producto_existente['id'],
                    $producto_existente['precio_efectivo'],
                    $producto_existente['precio_transferencia'],
                    $precios['efectivo'],
                    $precios['transferencia'],
                    'Actualización masiva de precios - Nueva carta 2025',
                    $_SESSION['admin_user']
                ]);
            }
            
            // Actualizar precios
            $stmt = $pdo->prepare("
                UPDATE productos SET 
                precio_efectivo = ?, 
                precio_transferencia = ?,
                updated_at = NOW(),
                updated_by = ?
                WHERE nombre = ?
            ");
            $stmt->execute([
                $precios['efectivo'],
                $precios['transferencia'], 
                $_SESSION['admin_user'],
                $nombre
            ]);
            
            echo "<p>✅ Actualizado: <strong>$nombre</strong> - Efectivo: $" . number_format($precios['efectivo']) . " | Transferencia: $" . number_format($precios['transferencia']) . "</p>";
            $actualizados++;
            
        } else {
            // Crear producto nuevo (especialmente Surtidos Elegidos)
            $categoria = 'Elegidos';
            if (strpos($nombre, 'Premium') !== false) $categoria = 'Premium';
            elseif (strpos($nombre, 'Especiales') !== false) $categoria = 'Especiales';
            elseif (strpos($nombre, 'Clásicos') !== false) $categoria = 'Clásicos';
            elseif (strpos($nombre, 'Jamón') !== false) $categoria = 'Clásicos';
            
            $descripcion = '';
            if (strpos($nombre, 'Elegidos') !== false) {
                $descripcion = 'Surtidos personalizables. El cliente puede elegir los sabores que desee de nuestra variedad disponible.';
            } elseif (strpos($nombre, 'Premium') !== false) {
                $descripcion = 'Sabores premium: ananá, atún, berenjena, durazno, jamón crudo, morrón, palmito, panceta, pollo, roquefort, salame.';
            } elseif (strpos($nombre, 'Especiales') !== false) {
                $descripcion = 'Sabores clásicos más choclo y aceitunas: jamón y queso, lechuga, tomate, huevo, choclo, aceitunas.';
            } elseif (strpos($nombre, 'Clásicos') !== false) {
                $descripcion = 'Sabores tradicionales: jamón y queso, lechuga, tomate, huevo.';
            } else {
                $descripcion = 'Clásicos sándwiches de jamón y queso.';
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO productos (nombre, precio_efectivo, precio_transferencia, categoria, descripcion, activo, updated_by, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, 1, ?, NOW(), NOW())
            ");
            $stmt->execute([
                $nombre,
                $precios['efectivo'],
                $precios['transferencia'],
                $categoria,
                $descripcion,
                $_SESSION['admin_user']
            ]);
            
            echo "<p>🆕 Creado: <strong>$nombre</strong> - Efectivo: $" . number_format($precios['efectivo']) . " | Transferencia: $" . number_format($precios['transferencia']) . "</p>";
            $creados++;
        }
    }
    
    echo "<h2>✅ Actualización completada</h2>";
    echo "<p><strong>Productos actualizados:</strong> $actualizados</p>";
    echo "<p><strong>Productos creados:</strong> $creados</p>";
    
    echo "<div style='background:#d4edda; color:#155724; padding:15px; border-radius:5px; margin:15px 0;'>";
    echo "<h4>🎉 ¡Precios actualizados exitosamente!</h4>";
    echo "<p>✅ Los precios del admin están sincronizados con la página principal</p>";
    echo "<p>✅ Se crearon los nuevos productos 'Surtidos Elegidos'</p>";
    echo "<p>✅ Se guardó el historial de cambios para auditoría</p>";
    echo "</div>";
    
    echo "<h3>📋 Próximos pasos:</h3>";
    echo "<ol>";
    echo "<li><a href='modules/productos/index.php' style='color:blue;'>Verificar productos actualizados</a></li>";
    echo "<li><a href='modules/pedidos/crear_pedido.php' style='color:green;'>Probar crear pedido con nuevos precios</a></li>";
    echo "<li><a href='modules/productos/historial.php' style='color:orange;'>Ver historial de cambios</a></li>";
    echo "</ol>";
    
} catch (Exception $e) {
    echo "<div style='background:#f8d7da; color:#721c24; padding:15px; border-radius:5px;'>";
    echo "<h4>❌ Error durante la actualización:</h4>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "</div>";
}

echo "<hr>";
echo "<p><a href='index.php' style='background:#007bff; color:white; padding:10px 20px; text-decoration:none; border-radius:5px;'>← Volver al Admin</a></p>";
?>