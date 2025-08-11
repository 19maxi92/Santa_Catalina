<?php
require_once '../../config.php';
requireLogin();

$pdo = getConnection();

$mensaje = '';
$error = '';
$productos_actualizados = [];

if ($_POST) {
    try {
        $tipo_ajuste = $_POST['tipo_ajuste'];
        $valor_ajuste = (float)$_POST['valor_ajuste'];
        $aplicar_efectivo = $_POST['aplicar_efectivo'] === '1';
        $aplicar_transferencia = $_POST['aplicar_transferencia'] === '1';
        $motivo = sanitize($_POST['motivo']);

        if ($valor_ajuste <= 0) {
            throw new Exception('El valor del ajuste debe ser mayor a 0');
        }

        if (!$aplicar_efectivo && !$aplicar_transferencia) {
            throw new Exception('Debe seleccionar al menos un tipo de precio para ajustar');
        }

        if (empty($motivo)) {
            throw new Exception('Debe especificar un motivo para el ajuste');
        }

        // Obtener todos los productos activos
        $stmt = $pdo->prepare("SELECT * FROM productos WHERE activo = 1");
        $stmt->execute();
        $productos = $stmt->fetchAll();

        if (empty($productos)) {
            throw new Exception('No hay productos activos para ajustar');
        }

        $pdo->beginTransaction();

        foreach ($productos as $producto) {
            $precio_efectivo_anterior = $producto['precio_efectivo'];
            $precio_transferencia_anterior = $producto['precio_transferencia'];
            
            $nuevo_efectivo = $precio_efectivo_anterior;
            $nuevo_transferencia = $precio_transferencia_anterior;

            // Aplicar ajuste según el tipo
            if ($aplicar_efectivo) {
                if ($tipo_ajuste === 'porcentaje') {
                    $nuevo_efectivo = round($precio_efectivo_anterior * (1 + $valor_ajuste / 100) / 100) * 100;
                } else {
                    $nuevo_efectivo = $precio_efectivo_anterior + $valor_ajuste;
                }
            }

            if ($aplicar_transferencia) {
                if ($tipo_ajuste === 'porcentaje') {
                    $nuevo_transferencia = round($precio_transferencia_anterior * (1 + $valor_ajuste / 100) / 100) * 100;
                } else {
                    $nuevo_transferencia = $precio_transferencia_anterior + $valor_ajuste;
                }
            }

            // Validar que los nuevos precios sean válidos
            if ($nuevo_efectivo <= 0 || $nuevo_transferencia <= 0) {
                throw new Exception("El ajuste resultaría en precios inválidos para el producto: {$producto['nombre']}");
            }

            // Actualizar el producto
            $stmt = $pdo->prepare("
                UPDATE productos 
                SET precio_efectivo = ?, precio_transferencia = ?, updated_by = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$nuevo_efectivo, $nuevo_transferencia, $_SESSION['admin_user'], $producto['id']]);

            // Registrar en el historial
            $stmt = $pdo->prepare("
                INSERT INTO historial_precios 
                (producto_id, tipo, precio_anterior_efectivo, precio_anterior_transferencia, 
                 precio_nuevo_efectivo, precio_nuevo_transferencia, motivo, usuario, fecha_cambio) 
                VALUES (?, 'producto', ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $producto['id'],
                $precio_efectivo_anterior,
                $precio_transferencia_anterior,
                $nuevo_efectivo,
                $nuevo_transferencia,
                $motivo,
                $_SESSION['admin_user']
            ]);

            // Guardar para mostrar resumen
            $productos_actualizados[] = [
                'nombre' => $producto['nombre'],
                'efectivo_anterior' => $precio_efectivo_anterior,
                'transferencia_anterior' => $precio_transferencia_anterior,
                'efectivo_nuevo' => $nuevo_efectivo,
                'transferencia_nuevo' => $nuevo_transferencia
            ];
        }

        $pdo->commit();
        $mensaje = 'Ajuste masivo aplicado correctamente a ' . count($productos_actualizados) . ' productos';

    } catch (Exception $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajuste Masivo Aplicado - <?= APP_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <!-- Header -->
    <header class="bg-white shadow-md">
        <div class="container mx-auto px-4 py-3 flex justify-between items-center">
            <div class="flex items-center">
                <a href="index.php" class="text-gray-600 hover:text-gray-800 mr-4">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <h1 class="text-xl font-bold text-gray-800">
                    <i class="fas fa-calculator text-orange-500 mr-2"></i>Resultado del Ajuste Masivo
                </h1>
            </div>
            <a href="../../logout.php" class="bg-red-500 hover:bg-red-600 text-white px-3 py-2 rounded">
                <i class="fas fa-sign-out-alt mr-1"></i>Salir
            </a>
        </div>
    </header>

    <main class="container mx-auto px-4 py-6">
        
        <!-- Mensajes -->
        <?php if ($mensaje): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
            <div class="flex items-center">
                <i class="fas fa-check-circle mr-2 text-2xl"></i>
                <div>
                    <div class="font-bold"><?= $mensaje ?></div>
                    <div class="text-sm mt-1">Todos los cambios han sido registrados en el historial</div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
            <div class="flex items-center">
                <i class="fas fa-exclamation-circle mr-2 text-2xl"></i>
                <div>
                    <div class="font-bold">Error en el ajuste masivo</div>
                    <div class="text-sm mt-1"><?= $error ?></div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($productos_actualizados)): ?>
            <!-- Resumen del ajuste -->
            <div class="bg-white rounded-lg shadow mb-6 p-6">
                <h2 class="text-xl font-bold text-gray-800 mb-4">
                    <i class="fas fa-chart-line text-green-500 mr-2"></i>Resumen del Ajuste
                </h2>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                    <div class="bg-blue-50 p-4 rounded-lg text-center">
                        <div class="text-2xl font-bold text-blue-600"><?= count($productos_actualizados) ?></div>
                        <div class="text-sm text-gray-600">Productos Actualizados</div>
                    </div>
                    <div class="bg-green-50 p-4 rounded-lg text-center">
                        <div class="text-2xl font-bold text-green-600">
                            <?= isset($_POST['aplicar_efectivo']) && $_POST['aplicar_efectivo'] === '1' ? '✅' : '❌' ?>
                        </div>
                        <div class="text-sm text-gray-600">Precios Efectivo</div>
                    </div>
                    <div class="bg-purple-50 p-4 rounded-lg text-center">
                        <div class="text-2xl font-bold text-purple-600">
                            <?= isset($_POST['aplicar_transferencia']) && $_POST['aplicar_transferencia'] === '1' ? '✅' : '❌' ?>
                        </div>
                        <div class="text-sm text-gray-600">Precios Transferencia</div>
                    </div>
                </div>

                <!-- Información del ajuste aplicado -->
                <?php if (isset($_POST['tipo_ajuste'])): ?>
                <div class="bg-gray-50 p-4 rounded-lg mb-4">
                    <h3 class="font-bold text-gray-700 mb-2">Ajuste Aplicado:</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                        <div>
                            <strong>Tipo:</strong> 
                            <?= $_POST['tipo_ajuste'] === 'porcentaje' ? 'Porcentaje' : 'Monto fijo' ?>
                        </div>
                        <div>
                            <strong>Valor:</strong> 
                            <?= $_POST['valor_ajuste'] ?><?= $_POST['tipo_ajuste'] === 'porcentaje' ? '%' : ' pesos' ?>
                        </div>
                        <div class="md:col-span-2">
                            <strong>Motivo:</strong> <?= htmlspecialchars($_POST['motivo']) ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Tabla detallada de cambios -->
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="p-6 border-b">
                    <h3 class="text-lg font-bold text-gray-800">
                        <i class="fas fa-list mr-2"></i>Detalle de Cambios por Producto
                    </h3>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Producto</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Precio Efectivo</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Precio Transferencia</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cambios</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach ($productos_actualizados as $producto): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4">
                                        <div class="font-medium text-gray-900">
                                            <?= htmlspecialchars($producto['nombre']) ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="space-y-1">
                                            <?php if ($producto['efectivo_anterior'] != $producto['efectivo_nuevo']): ?>
                                                <div class="text-sm text-gray-400 line-through">
                                                    <?= formatPrice($producto['efectivo_anterior']) ?>
                                                </div>
                                                <div class="font-bold text-green-600">
                                                    <?= formatPrice($producto['efectivo_nuevo']) ?>
                                                </div>
                                                <div class="text-xs text-green-600">
                                                    <?php 
                                                    $diferencia = $producto['efectivo_nuevo'] - $producto['efectivo_anterior'];
                                                    echo ($diferencia > 0 ? '+' : '') . formatPrice($diferencia);
                                                    ?>
                                                </div>
                                            <?php else: ?>
                                                <div class="text-gray-600">
                                                    <?= formatPrice($producto['efectivo_anterior']) ?>
                                                </div>
                                                <div class="text-xs text-gray-500">Sin cambios</div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="space-y-1">
                                            <?php if ($producto['transferencia_anterior'] != $producto['transferencia_nuevo']): ?>
                                                <div class="text-sm text-gray-400 line-through">
                                                    <?= formatPrice($producto['transferencia_anterior']) ?>
                                                </div>
                                                <div class="font-bold text-blue-600">
                                                    <?= formatPrice($producto['transferencia_nuevo']) ?>
                                                </div>
                                                <div class="text-xs text-blue-600">
                                                    <?php 
                                                    $diferencia = $producto['transferencia_nuevo'] - $producto['transferencia_anterior'];
                                                    echo ($diferencia > 0 ? '+' : '') . formatPrice($diferencia);
                                                    ?>
                                                </div>
                                            <?php else: ?>
                                                <div class="text-gray-600">
                                                    <?= formatPrice($producto['transferencia_anterior']) ?>
                                                </div>
                                                <div class="text-xs text-gray-500">Sin cambios</div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="space-y-1">
                                            <?php 
                                            $cambios = [];
                                            if ($producto['efectivo_anterior'] != $producto['efectivo_nuevo']) {
                                                $cambios[] = 'Efectivo';
                                            }
                                            if ($producto['transferencia_anterior'] != $producto['transferencia_nuevo']) {
                                                $cambios[] = 'Transferencia';
                                            }
                                            ?>
                                            
                                            <?php if (!empty($cambios)): ?>
                                                <?php foreach ($cambios as $cambio): ?>
                                                    <span class="inline-block px-2 py-1 text-xs bg-orange-100 text-orange-800 rounded">
                                                        <?= $cambio ?>
                                                    </span>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <span class="inline-block px-2 py-1 text-xs bg-gray-100 text-gray-600 rounded">
                                                    Sin cambios
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Estadísticas del ajuste -->
            <div class="mt-6 bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-bold text-gray-800 mb-4">
                    <i class="fas fa-chart-bar mr-2"></i>Estadísticas del Ajuste
                </h3>
                
                <?php
                $total_incremento_efectivo = 0;
                $total_incremento_transferencia = 0;
                $productos_efectivo_cambiados = 0;
                $productos_transferencia_cambiados = 0;

                foreach ($productos_actualizados as $producto) {
                    if ($producto['efectivo_anterior'] != $producto['efectivo_nuevo']) {
                        $total_incremento_efectivo += ($producto['efectivo_nuevo'] - $producto['efectivo_anterior']);
                        $productos_efectivo_cambiados++;
                    }
                    if ($producto['transferencia_anterior'] != $producto['transferencia_nuevo']) {
                        $total_incremento_transferencia += ($producto['transferencia_nuevo'] - $producto['transferencia_anterior']);
                        $productos_transferencia_cambiados++;
                    }
                }
                ?>

                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="text-center">
                        <div class="text-2xl font-bold text-green-600">
                            <?= formatPrice($total_incremento_efectivo) ?>
                        </div>
                        <div class="text-sm text-gray-600">Incremento Total Efectivo</div>
                        <div class="text-xs text-gray-500"><?= $productos_efectivo_cambiados ?> productos</div>
                    </div>
                    
                    <div class="text-center">
                        <div class="text-2xl font-bold text-blue-600">
                            <?= formatPrice($total_incremento_transferencia) ?>
                        </div>
                        <div class="text-sm text-gray-600">Incremento Total Transfer.</div>
                        <div class="text-xs text-gray-500"><?= $productos_transferencia_cambiados ?> productos</div>
                    </div>
                    
                    <div class="text-center">
                        <div class="text-2xl font-bold text-purple-600">
                            <?= formatPrice($total_incremento_efectivo + $total_incremento_transferencia) ?>
                        </div>
                        <div class="text-sm text-gray-600">Incremento Total General</div>
                        <div class="text-xs text-gray-500">Ambos tipos de precio</div>
                    </div>
                    
                    <div class="text-center">
                        <div class="text-2xl font-bold text-orange-600">
                            <?= number_format((($productos_efectivo_cambiados + $productos_transferencia_cambiados) / (count($productos_actualizados) * 2)) * 100, 1) ?>%
                        </div>
                        <div class="text-sm text-gray-600">Tasa de Cambio</div>
                        <div class="text-xs text-gray-500">Precios modificados</div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Acciones -->
        <div class="mt-8 flex justify-center space-x-4">
            <a href="index.php" class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-3 rounded-lg">
                <i class="fas fa-boxes mr-2"></i>Ver Lista de Productos
            </a>
            
            <a href="historial.php" class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-3 rounded-lg">
                <i class="fas fa-history mr-2"></i>Ver Historial de Cambios
            </a>
            
            <?php if (!empty($productos_actualizados)): ?>
                <button onclick="exportarReporte()" class="bg-green-500 hover:bg-green-600 text-white px-6 py-3 rounded-lg">
                    <i class="fas fa-download mr-2"></i>Exportar Reporte
                </button>
            <?php endif; ?>
        </div>

        <!-- Información adicional -->
        <div class="mt-8 bg-blue-50 border border-blue-200 rounded-lg p-6">
            <h3 class="font-bold text-blue-800 mb-3">
                <i class="fas fa-info-circle mr-2"></i>Información del Ajuste Masivo
            </h3>
            <div class="text-sm text-blue-700 space-y-2">
                <p><strong>Fecha y hora:</strong> <?= date('d/m/Y H:i:s') ?></p>
                <p><strong>Usuario:</strong> <?= $_SESSION['admin_name'] ?> (<?= $_SESSION['admin_user'] ?>)</p>
                <p><strong>Alcance:</strong> Solo productos activos</p>
                <p><strong>Registro:</strong> Todos los cambios están registrados en el historial para auditoría</p>
                <?php if (!empty($productos_actualizados)): ?>
                    <p><strong>Reversión:</strong> Este ajuste puede ser revertido aplicando un ajuste inverso con el mismo porcentaje/monto pero en sentido contrario</p>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script>
        function exportarReporte() {
            // Crear contenido del reporte
            let contenido = 'Reporte de Ajuste Masivo de Precios\n';
            contenido += 'Fecha: ' + new Date().toLocaleString('es-AR') + '\n';
            contenido += 'Usuario: <?= $_SESSION['admin_name'] ?>\n\n';
            
            <?php if (isset($_POST['tipo_ajuste'])): ?>
            contenido += 'Ajuste aplicado:\n';
            contenido += 'Tipo: <?= $_POST['tipo_ajuste'] === 'porcentaje' ? 'Porcentaje' : 'Monto fijo' ?>\n';
            contenido += 'Valor: <?= $_POST['valor_ajuste'] ?><?= $_POST['tipo_ajuste'] === 'porcentaje' ? '%' : ' pesos' ?>\n';
            contenido += 'Motivo: <?= htmlspecialchars($_POST['motivo']) ?>\n\n';
            <?php endif; ?>
            
            contenido += 'Productos actualizados:\n';
            contenido += 'Producto,Efectivo Anterior,Efectivo Nuevo,Transfer Anterior,Transfer Nuevo\n';
            
            <?php foreach ($productos_actualizados as $producto): ?>
            contenido += '<?= htmlspecialchars($producto['nombre']) ?>,';
            contenido += '<?= $producto['efectivo_anterior'] ?>,';
            contenido += '<?= $producto['efectivo_nuevo'] ?>,';
            contenido += '<?= $producto['transferencia_anterior'] ?>,';
            contenido += '<?= $producto['transferencia_nuevo'] ?>\n';
            <?php endforeach; ?>
            
            // Crear y descargar archivo
            const blob = new Blob([contenido], { type: 'text/plain;charset=utf-8' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'ajuste_masivo_' + new Date().toISOString().split('T')[0] + '.csv';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
        }
    </script>
</body>
</html>