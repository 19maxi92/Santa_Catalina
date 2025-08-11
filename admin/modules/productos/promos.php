<?php
require_once '../../config.php';
requireLogin();

$pdo = getConnection();

$mensaje = '';
$error = '';

// Procesar acciones
if ($_POST) {
    switch ($_POST['accion']) {
        case 'crear_promo':
            try {
                $nombre = sanitize($_POST['nombre']);
                $descripcion = sanitize($_POST['descripcion']);
                $precio_efectivo = (float)$_POST['precio_efectivo'];
                $precio_transferencia = (float)$_POST['precio_transferencia'];
                $fecha_inicio = $_POST['fecha_inicio'] ?: null;
                $fecha_fin = $_POST['fecha_fin'] ?: null;
                $dias_semana = isset($_POST['dias_semana']) ? json_encode($_POST['dias_semana']) : null;
                $hora_inicio = $_POST['hora_inicio'] ?: null;
                $hora_fin = $_POST['hora_fin'] ?: null;
                $condiciones = sanitize($_POST['condiciones']);
                $orden_mostrar = (int)$_POST['orden_mostrar'];
                $activa = isset($_POST['activa']) ? 1 : 0;

                if (empty($nombre)) {
                    throw new Exception('El nombre de la promo es obligatorio');
                }

                if ($precio_efectivo <= 0 || $precio_transferencia <= 0) {
                    throw new Exception('Los precios deben ser mayores a 0');
                }

                if ($fecha_inicio && $fecha_fin && $fecha_inicio > $fecha_fin) {
                    throw new Exception('La fecha de inicio no puede ser posterior a la fecha de fin');
                }

                $stmt = $pdo->prepare("
                    INSERT INTO promos (nombre, descripcion, precio_efectivo, precio_transferencia, 
                                      fecha_inicio, fecha_fin, dias_semana, hora_inicio, hora_fin,
                                      condiciones, orden_mostrar, activa, created_by) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    $nombre, $descripcion, $precio_efectivo, $precio_transferencia,
                    $fecha_inicio, $fecha_fin, $dias_semana, $hora_inicio, $hora_fin,
                    $condiciones, $orden_mostrar, $activa, $_SESSION['admin_user']
                ]);

                $mensaje = 'Promo creada correctamente';

            } catch (Exception $e) {
                $error = $e->getMessage();
            }
            break;

        case 'toggle_promo':
            $id = (int)$_POST['id'];
            $estado = $_POST['estado'] === '1' ? 0 : 1;
            try {
                $stmt = $pdo->prepare("UPDATE promos SET activa = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$estado, $id]);
                $mensaje = $estado ? 'Promo activada' : 'Promo desactivada';
            } catch (Exception $e) {
                $error = 'Error al cambiar estado de la promo';
            }
            break;

        case 'eliminar_promo':
            $id = (int)$_POST['id'];
            try {
                $stmt = $pdo->prepare("DELETE FROM promos WHERE id = ?");
                $stmt->execute([$id]);
                $mensaje = 'Promo eliminada correctamente';
            } catch (Exception $e) {
                $error = 'Error al eliminar promo';
            }
            break;
    }
}

// Obtener promos
$filtro_estado = isset($_GET['estado']) ? sanitize($_GET['estado']) : '';
$filtro_vigencia = isset($_GET['vigencia']) ? sanitize($_GET['vigencia']) : '';

$sql = "SELECT * FROM promos WHERE 1=1";
$params = [];

if ($filtro_estado !== '') {
    $sql .= " AND activa = ?";
    $params[] = $filtro_estado;
}

if ($filtro_vigencia) {
    switch ($filtro_vigencia) {
        case 'vigente':
            $sql .= " AND (fecha_inicio IS NULL OR CURDATE() >= fecha_inicio) 
                      AND (fecha_fin IS NULL OR CURDATE() <= fecha_fin)";
            break;
        case 'proxima':
            $sql .= " AND fecha_inicio > CURDATE()";
            break;
        case 'vencida':
            $sql .= " AND fecha_fin < CURDATE()";
            break;
    }
}

$sql .= " ORDER BY orden_mostrar, created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$promos = $stmt->fetchAll();

// Estadísticas
$stats = $pdo->query("
    SELECT 
        COUNT(*) as total_promos,
        SUM(CASE WHEN activa = 1 THEN 1 ELSE 0 END) as promos_activas,
        SUM(CASE WHEN activa = 1 
                 AND (fecha_inicio IS NULL OR CURDATE() >= fecha_inicio) 
                 AND (fecha_fin IS NULL OR CURDATE() <= fecha_fin) 
            THEN 1 ELSE 0 END) as promos_vigentes
    FROM promos
")->fetch();

$dias_semana_nombres = [
    'lunes' => 'Lunes',
    'martes' => 'Martes', 
    'miercoles' => 'Miércoles',
    'jueves' => 'Jueves',
    'viernes' => 'Viernes',
    'sabado' => 'Sábado',
    'domingo' => 'Domingo'
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Promos - <?= APP_NAME ?></title>
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
                    <i class="fas fa-tags text-purple-500 mr-2"></i>Gestión de Promos
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
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            <i class="fas fa-check-circle mr-2"></i><?= $mensaje ?>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <i class="fas fa-exclamation-circle mr-2"></i><?= $error ?>
        </div>
        <?php endif; ?>

        <!-- Estadísticas -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="bg-white p-4 rounded-lg shadow text-center">
                <div class="text-2xl font-bold text-purple-600"><?= $stats['total_promos'] ?></div>
                <div class="text-sm text-gray-600">Total Promos</div>
            </div>
            <div class="bg-white p-4 rounded-lg shadow text-center">
                <div class="text-2xl font-bold text-green-600"><?= $stats['promos_activas'] ?></div>
                <div class="text-sm text-gray-600">Promos Activas</div>
            </div>
            <div class="bg-white p-4 rounded-lg shadow text-center">
                <div class="text-2xl font-bold text-blue-600"><?= $stats['promos_vigentes'] ?></div>
                <div class="text-sm text-gray-600">Vigentes Hoy</div>
            </div>
        </div>

        <!-- Barra de herramientas -->
        <div class="bg-white rounded-lg shadow mb-6 p-4">
            <div class="flex flex-col md:flex-row justify-between items-center space-y-4 md:space-y-0 mb-4">
                <div class="flex space-x-3">
                    <button onclick="mostrarFormularioPromo()" class="bg-purple-500 hover:bg-purple-600 text-white px-4 py-2 rounded-lg">
                        <i class="fas fa-plus mr-2"></i>Nueva Promo
                    </button>
                    <a href="index.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg">
                        <i class="fas fa-boxes mr-2"></i>Ver Productos
                    </a>
                </div>
            </div>

            <!-- Filtros -->
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <select name="estado" class="w-full px-3 py-2 border rounded-lg">
                        <option value="">Todos los estados</option>
                        <option value="1" <?= $filtro_estado === '1' ? 'selected' : '' ?>>Solo Activas</option>
                        <option value="0" <?= $filtro_estado === '0' ? 'selected' : '' ?>>Solo Inactivas</option>
                    </select>
                </div>
                
                <div>
                    <select name="vigencia" class="w-full px-3 py-2 border rounded-lg">
                        <option value="">Todas las vigencias</option>
                        <option value="vigente" <?= $filtro_vigencia === 'vigente' ? 'selected' : '' ?>>Vigentes ahora</option>
                        <option value="proxima" <?= $filtro_vigencia === 'proxima' ? 'selected' : '' ?>>Próximas</option>
                        <option value="vencida" <?= $filtro_vigencia === 'vencida' ? 'selected' : '' ?>>Vencidas</option>
                    </select>
                </div>
                
                <div>
                    <button type="submit" class="w-full bg-purple-500 hover:bg-purple-600 text-white py-2 px-4 rounded-lg">
                        <i class="fas fa-filter mr-1"></i>Filtrar
                    </button>
                </div>

                <div>
                    <a href="?" class="w-full bg-gray-400 hover:bg-gray-500 text-white py-2 px-4 rounded-lg text-center block">
                        <i class="fas fa-eraser mr-1"></i>Limpiar
                    </a>
                </div>
            </form>
        </div>

        <!-- Lista de promos -->
        <div class="space-y-4">
            <?php if (empty($promos)): ?>
                <div class="bg-white p-12 rounded-lg shadow text-center text-gray-500">
                    <i class="fas fa-tags text-6xl mb-4 text-gray-300"></i>
                    <h3 class="text-xl mb-2">No hay promos</h3>
                    <p>No se encontraron promociones con los filtros aplicados</p>
                    <div class="mt-4">
                        <button onclick="mostrarFormularioPromo()" class="bg-purple-500 hover:bg-purple-600 text-white px-4 py-2 rounded-lg">
                            <i class="fas fa-plus mr-2"></i>Crear Primera Promo
                        </button>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($promos as $promo): ?>
                    <?php
                    // Determinar estado de vigencia
                    $hoy = date('Y-m-d');
                    $vigencia = 'Sin fechas';
                    $vigencia_color = 'bg-gray-100 text-gray-800';
                    
                    if ($promo['fecha_inicio'] && $promo['fecha_fin']) {
                        if ($hoy < $promo['fecha_inicio']) {
                            $vigencia = 'Próxima';
                            $vigencia_color = 'bg-blue-100 text-blue-800';
                        } elseif ($hoy > $promo['fecha_fin']) {
                            $vigencia = 'Vencida';
                        $vigencia_color = 'bg-red-100 text-red-800';
                    } else {
                        $vigencia = 'Vigente';
                        $vigencia_color = 'bg-green-100 text-green-800';
                    }

                    // Días de la semana
                    $dias_activos = [];
                    if ($promo['dias_semana']) {
                        $dias_json = json_decode($promo['dias_semana'], true);
                        if ($dias_json) {
                            foreach ($dias_json as $dia) {
                                if (isset($dias_semana_nombres[$dia])) {
                                    $dias_activos[] = $dias_semana_nombres[$dia];
                                }
                            }
                        }
                    }
                    ?>
                    
                    <div class="bg-white rounded-lg shadow border-l-4 border-purple-400 <?= $promo['activa'] ? '' : 'opacity-50' ?>">
                        <div class="p-6">
                            <div class="flex justify-between items-start mb-4">
                                <div class="flex-1">
                                    <div class="flex items-center space-x-3 mb-2">
                                        <h3 class="text-xl font-bold text-gray-800">
                                            <?= htmlspecialchars($promo['nombre']) ?>
                                        </h3>
                                        <span class="px-2 py-1 text-xs font-medium rounded-full <?= $vigencia_color ?>">
                                            <?= $vigencia ?>
                                        </span>
                                        <?php if (!$promo['activa']): ?>
                                            <span class="px-2 py-1 text-xs font-medium rounded-full bg-red-100 text-red-800">
                                                Inactiva
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if ($promo['descripcion']): ?>
                                        <p class="text-gray-600 mb-3"><?= htmlspecialchars($promo['descripcion']) ?></p>
                                    <?php endif; ?>
                                    
                                    <div class="grid md:grid-cols-2 gap-4 text-sm">
                                        <div>
                                            <!-- Precios -->
                                            <div class="mb-3">
                                                <div class="font-medium text-green-600">
                                                    Efectivo: <?= formatPrice($promo['precio_efectivo']) ?>
                                                </div>
                                                <div class="text-gray-600">
                                                    Transfer: <?= formatPrice($promo['precio_transferencia']) ?>
                                                </div>
                                            </div>
                                            
                                            <!-- Fechas -->
                                            <?php if ($promo['fecha_inicio'] || $promo['fecha_fin']): ?>
                                                <div class="mb-3">
                                                    <div class="font-medium text-gray-700 mb-1">
                                                        <i class="fas fa-calendar mr-1"></i>Período:
                                                    </div>
                                                    <div class="text-gray-600">
                                                        <?php if ($promo['fecha_inicio']): ?>
                                                            Desde: <?= date('d/m/Y', strtotime($promo['fecha_inicio'])) ?>
                                                        <?php endif; ?>
                                                        <?php if ($promo['fecha_fin']): ?>
                                                            <br>Hasta: <?= date('d/m/Y', strtotime($promo['fecha_fin'])) ?>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div>
                                            <!-- Días de la semana -->
                                            <?php if (!empty($dias_activos)): ?>
                                                <div class="mb-3">
                                                    <div class="font-medium text-gray-700 mb-1">
                                                        <i class="fas fa-calendar-week mr-1"></i>Días:
                                                    </div>
                                                    <div class="flex flex-wrap gap-1">
                                                        <?php foreach ($dias_activos as $dia): ?>
                                                            <span class="px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded"><?= $dia ?></span>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <!-- Horarios -->
                                            <?php if ($promo['hora_inicio'] || $promo['hora_fin']): ?>
                                                <div class="mb-3">
                                                    <div class="font-medium text-gray-700 mb-1">
                                                        <i class="fas fa-clock mr-1"></i>Horario:
                                                    </div>
                                                    <div class="text-gray-600">
                                                        <?= $promo['hora_inicio'] ? substr($promo['hora_inicio'], 0, 5) : '00:00' ?> - 
                                                        <?= $promo['hora_fin'] ? substr($promo['hora_fin'], 0, 5) : '23:59' ?>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <!-- Condiciones -->
                                            <?php if ($promo['condiciones']): ?>
                                                <div class="mb-3">
                                                    <div class="font-medium text-gray-700 mb-1">
                                                        <i class="fas fa-info-circle mr-1"></i>Condiciones:
                                                    </div>
                                                    <div class="text-gray-600 text-sm">
                                                        <?= htmlspecialchars($promo['condiciones']) ?>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Acciones -->
                                <div class="ml-4">
                                    <div class="flex flex-col space-y-2">
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="accion" value="toggle_promo">
                                            <input type="hidden" name="id" value="<?= $promo['id'] ?>">
                                            <input type="hidden" name="estado" value="<?= $promo['activa'] ?>">
                                            <button type="submit" 
                                                    class="px-3 py-2 text-sm font-medium rounded 
                                                    <?= $promo['activa'] 
                                                        ? 'bg-green-100 text-green-800 hover:bg-green-200' 
                                                        : 'bg-red-100 text-red-800 hover:bg-red-200' ?>">
                                                <?= $promo['activa'] ? '✅ Activar' : '❌ Desactivar' ?>
                                            </button>
                                        </form>
                                        
                                        <button onclick="editarPromo(<?= $promo['id'] ?>)" 
                                                class="px-3 py-2 text-sm bg-blue-100 text-blue-800 hover:bg-blue-200 rounded">
                                            <i class="fas fa-edit mr-1"></i>Editar
                                        </button>
                                        
                                        <button onclick="duplicarPromo(<?= $promo['id'] ?>)" 
                                                class="px-3 py-2 text-sm bg-yellow-100 text-yellow-800 hover:bg-yellow-200 rounded">
                                            <i class="fas fa-copy mr-1"></i>Duplicar
                                        </button>
                                        
                                        <button onclick="eliminarPromo(<?= $promo['id'] ?>, '<?= htmlspecialchars($promo['nombre']) ?>')" 
                                                class="px-3 py-2 text-sm bg-red-100 text-red-800 hover:bg-red-200 rounded">
                                            <i class="fas fa-trash mr-1"></i>Eliminar
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Footer con metadata -->
                            <div class="border-t pt-3 mt-4 text-xs text-gray-500 flex justify-between">
                                <div>
                                    Orden: <?= $promo['orden_mostrar'] ?> | 
                                    Creada: <?= date('d/m/Y H:i', strtotime($promo['created_at'])) ?>
                                </div>
                                <div>
                                    ID: #<?= $promo['id'] ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>

    <!-- Modal Crear/Editar Promo -->
    <div id="promoModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-lg w-full max-w-4xl mx-4 max-h-screen overflow-y-auto">
            <div class="p-6">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-xl font-bold">
                        <i class="fas fa-tags text-purple-500 mr-2"></i>
                        <span id="modal-title">Nueva Promoción</span>
                    </h3>
                    <button onclick="cerrarPromoModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                
                <form id="promoForm" method="POST">
                    <input type="hidden" name="accion" value="crear_promo">
                    <input type="hidden" name="promo_id" id="promo_id">
                    
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <!-- Columna 1: Información básica -->
                        <div class="space-y-4">
                            <h4 class="font-semibold text-gray-700 border-b pb-2">Información Básica</h4>
                            
                            <div>
                                <label class="block text-gray-700 mb-2 font-medium">
                                    Nombre de la Promo <span class="text-red-500">*</span>
                                </label>
                                <input type="text" name="nombre" id="promo_nombre" required 
                                       class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-purple-500"
                                       placeholder="Ej: Combo Fin de Semana">
                            </div>
                            
                            <div>
                                <label class="block text-gray-700 mb-2 font-medium">Descripción</label>
                                <textarea name="descripcion" id="promo_descripcion" rows="3"
                                          class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-purple-500"
                                          placeholder="Describe la promoción y qué incluye..."></textarea>
                            </div>
                            
                            <!-- Precios -->
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-gray-700 mb-2 font-medium">
                                        Precio Efectivo <span class="text-red-500">*</span>
                                    </label>
                                    <div class="relative">
                                        <span class="absolute left-3 top-2 text-gray-500">$</span>
                                        <input type="number" name="precio_efectivo" id="promo_precio_efectivo" 
                                               required min="100" step="100"
                                               class="w-full pl-8 pr-4 py-2 border rounded-lg focus:ring-2 focus:ring-green-500">
                                    </div>
                                </div>
                                
                                <div>
                                    <label class="block text-gray-700 mb-2 font-medium">
                                        Precio Transferencia <span class="text-red-500">*</span>
                                    </label>
                                    <div class="relative">
                                        <span class="absolute left-3 top-2 text-gray-500">$</span>
                                        <input type="number" name="precio_transferencia" id="promo_precio_transferencia" 
                                               required min="100" step="100"
                                               class="w-full pl-8 pr-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                                    </div>
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-gray-700 mb-2 font-medium">Condiciones y Restricciones</label>
                                <textarea name="condiciones" id="promo_condiciones" rows="2"
                                          class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-purple-500"
                                          placeholder="Ej: No acumulable con otras ofertas. Válido hasta agotar stock."></textarea>
                            </div>
                        </div>
                        
                        <!-- Columna 2: Configuración temporal -->
                        <div class="space-y-4">
                            <h4 class="font-semibold text-gray-700 border-b pb-2">Configuración Temporal</h4>
                            
                            <!-- Fechas -->
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-gray-700 mb-2 font-medium">Fecha Inicio</label>
                                    <input type="date" name="fecha_inicio" id="promo_fecha_inicio"
                                           class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-purple-500">
                                </div>
                                
                                <div>
                                    <label class="block text-gray-700 mb-2 font-medium">Fecha Fin</label>
                                    <input type="date" name="fecha_fin" id="promo_fecha_fin"
                                           class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-purple-500">
                                </div>
                            </div>
                            
                            <!-- Días de la semana -->
                            <div>
                                <label class="block text-gray-700 mb-2 font-medium">Días de la Semana</label>
                                <div class="grid grid-cols-2 gap-2 text-sm">
                                    <?php foreach ($dias_semana_nombres as $key => $nombre): ?>
                                        <label class="flex items-center">
                                            <input type="checkbox" name="dias_semana[]" value="<?= $key ?>" 
                                                   class="mr-2 dia-semana">
                                            <?= $nombre ?>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                                <div class="text-xs text-gray-500 mt-1">
                                    Deja vacío para todos los días
                                </div>
                            </div>
                            
                            <!-- Horarios -->
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-gray-700 mb-2 font-medium">Hora Inicio</label>
                                    <input type="time" name="hora_inicio" id="promo_hora_inicio"
                                           class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-purple-500">
                                </div>
                                
                                <div>
                                    <label class="block text-gray-700 mb-2 font-medium">Hora Fin</label>
                                    <input type="time" name="hora_fin" id="promo_hora_fin"
                                           class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-purple-500">
                                </div>
                            </div>
                            
                            <!-- Orden y estado -->
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-gray-700 mb-2 font-medium">Orden de Aparición</label>
                                    <input type="number" name="orden_mostrar" id="promo_orden" value="100" 
                                           min="0" step="10"
                                           class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-purple-500">
                                </div>
                                
                                <div class="flex items-end">
                                    <label class="flex items-center">
                                        <input type="checkbox" name="activa" id="promo_activa" value="1" checked 
                                               class="mr-2">
                                        <span class="text-gray-700">Promo activa</span>
                                    </label>
                                </div>
                            </div>
                            
                            <!-- Botones rápidos -->
                            <div class="bg-gray-50 p-3 rounded-lg">
                                <div class="text-sm font-medium text-gray-700 mb-2">Configuraciones Rápidas:</div>
                                <div class="space-y-1">
                                    <button type="button" onclick="configFinDeSemana()" 
                                            class="bg-blue-100 text-blue-800 px-2 py-1 rounded text-xs mr-2">
                                        Fin de Semana
                                    </button>
                                    <button type="button" onclick="configSemanaLaboral()" 
                                            class="bg-green-100 text-green-800 px-2 py-1 rounded text-xs mr-2">
                                        Lun-Vie
                                    </button>
                                    <button type="button" onclick="configTodoElMes()" 
                                            class="bg-purple-100 text-purple-800 px-2 py-1 rounded text-xs">
                                        Todo el Mes
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Botones del modal -->
                    <div class="flex justify-end space-x-2 mt-6 pt-4 border-t">
                        <button type="button" onclick="cerrarPromoModal()" 
                                class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded">
                            Cancelar
                        </button>
                        <button type="submit" 
                                class="bg-purple-500 hover:bg-purple-600 text-white px-4 py-2 rounded">
                            <i class="fas fa-save mr-1"></i>Guardar Promo
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Mostrar formulario de nueva promo
        function mostrarFormularioPromo() {
            document.getElementById('modal-title').textContent = 'Nueva Promoción';
            document.getElementById('promoForm').reset();
            document.querySelector('input[name="accion"]').value = 'crear_promo';
            document.getElementById('promo_id').value = '';
            document.getElementById('promoModal').classList.remove('hidden');
        }

        // Cerrar modal
        function cerrarPromoModal() {
            document.getElementById('promoModal').classList.add('hidden');
        }

        // Eliminar promo
        function eliminarPromo(id, nombre) {
            if (confirm(`¿Estás seguro de eliminar la promo "${nombre}"?\n\nEsta acción no se puede deshacer.`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="accion" value="eliminar_promo">
                    <input type="hidden" name="id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Configuraciones rápidas
        function configFinDeSemana() {
            // Desmarcar todos
            document.querySelectorAll('.dia-semana').forEach(cb => cb.checked = false);
            // Marcar sábado y domingo
            document.querySelector('input[value="sabado"]').checked = true;
            document.querySelector('input[value="domingo"]').checked = true;
        }

        function configSemanaLaboral() {
            // Desmarcar todos
            document.querySelectorAll('.dia-semana').forEach(cb => cb.checked = false);
            // Marcar lunes a viernes
            ['lunes', 'martes', 'miercoles', 'jueves', 'viernes'].forEach(dia => {
                document.querySelector(`input[value="${dia}"]`).checked = true;
            });
        }

        function configTodoElMes() {
            const hoy = new Date();
            const primerDia = new Date(hoy.getFullYear(), hoy.getMonth(), 1);
            const ultimoDia = new Date(hoy.getFullYear(), hoy.getMonth() + 1, 0);
            
            document.getElementById('promo_fecha_inicio').value = primerDia.toISOString().split('T')[0];
            document.getElementById('promo_fecha_fin').value = ultimoDia.toISOString().split('T')[0];
        }

        // Cerrar modal al hacer clic fuera
        document.getElementById('promoModal').addEventListener('click', function(e) {
            if (e.target === this) {
                cerrarPromoModal();
            }
        });

        // Validaciones del formulario
        document.getElementById('promoForm').addEventListener('submit', function(e) {
            const fechaInicio = document.getElementById('promo_fecha_inicio').value;
            const fechaFin = document.getElementById('promo_fecha_fin').value;
            
            if (fechaInicio && fechaFin && fechaInicio > fechaFin) {
                e.preventDefault();
                alert('La fecha de inicio no puede ser posterior a la fecha de fin.');
                return;
            }

            const horaInicio = document.getElementById('promo_hora_inicio').value;
            const horaFin = document.getElementById('promo_hora_fin').value;
            
            if (horaInicio && horaFin && horaInicio >= horaFin) {
                e.preventDefault();
                alert('La hora de inicio debe ser anterior a la hora de fin.');
                return;
            }
        });

        // Placeholder para funciones futuras
        function editarPromo(id) {
            alert('Función de edición en desarrollo. Por ahora, cree una nueva promo.');
        }

        function duplicarPromo(id) {
            alert('Función de duplicación en desarrollo. Por ahora, cree una nueva promo basada en esta.');
        }
    </script>
</body>
</html>encida';
                            $vigencia_color = 'bg-red-100 text-red-800';
                        } else {
                            $vigencia = 'Vigente';
                            $vigencia_color = 'bg-green-100 text-green-800';
                        }
                    } elseif ($promo['fecha_inicio'] && $hoy < $promo['fecha_inicio']) {
                        $vigencia = 'Próxima';
                        $vigencia_color = 'bg-blue-100 text-blue-800';
                    } elseif ($promo['fecha_fin'] && $hoy > $promo['fecha_fin']) {
                        $vigencia = 'V