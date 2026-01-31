<?php
require_once '../../config.php';
requireLogin();

$pdo = getConnection();

// Procesar acciones AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    header('Content-Type: application/json');

    switch ($_POST['accion']) {
        case 'marcar_impreso':
            $pedido_id = (int)$_POST['pedido_id'];
            $stmt = $pdo->prepare("UPDATE pedidos SET impreso = 1 WHERE id = ?");
            $result = $stmt->execute([$pedido_id]);
            echo json_encode(['success' => $result]);
            exit;

        case 'cambiar_estado':
            $pedido_id = (int)$_POST['pedido_id'];
            $nuevo_estado = htmlspecialchars(strip_tags(trim($_POST['nuevo_estado'])));
            $stmt = $pdo->prepare("UPDATE pedidos SET estado = ? WHERE id = ?");
            $result = $stmt->execute([$nuevo_estado, $pedido_id]);
            echo json_encode(['success' => $result]);
            exit;

        case 'eliminar_pedido':
            $pedido_id = (int)$_POST['pedido_id'];
            $stmt = $pdo->prepare("DELETE FROM pedidos WHERE id = ?");
            $result = $stmt->execute([$pedido_id]);
            echo json_encode(['success' => $result]);
            exit;

        case 'obtener_pedido':
            $pedido_id = (int)$_POST['pedido_id'];
            $stmt = $pdo->prepare("SELECT * FROM pedidos WHERE id = ?");
            $stmt->execute([$pedido_id]);
            $pedido = $stmt->fetch();
            echo json_encode(['success' => true, 'pedido' => $pedido]);
            exit;

        case 'editar_pedido':
            $pedido_id = (int)$_POST['pedido_id'];
            $producto = htmlspecialchars(strip_tags(trim($_POST['producto'])));
            $cantidad = (int)$_POST['cantidad'];
            $precio = (float)$_POST['precio'];
            $observaciones = htmlspecialchars(strip_tags(trim($_POST['observaciones'])));

            // Si hay sabores personalizados, actualizar también
            $sabores_json = isset($_POST['sabores_personalizados_json']) ? $_POST['sabores_personalizados_json'] : null;

            if ($sabores_json) {
                $stmt = $pdo->prepare("UPDATE pedidos SET producto = ?, cantidad = ?, precio = ?, observaciones = ?, sabores_personalizados_json = ? WHERE id = ?");
                $result = $stmt->execute([$producto, $cantidad, $precio, $observaciones, $sabores_json, $pedido_id]);
            } else {
                $stmt = $pdo->prepare("UPDATE pedidos SET producto = ?, cantidad = ?, precio = ?, observaciones = ? WHERE id = ?");
                $result = $stmt->execute([$producto, $cantidad, $precio, $observaciones, $pedido_id]);
            }

            echo json_encode(['success' => $result]);
            exit;
    }
}

// Filtro de ubicación (admin ve todas las ubicaciones)
$ubicacion_filtro = isset($_GET['ubicacion']) ? $_GET['ubicacion'] : 'todas';
$where_ubicacion = $ubicacion_filtro === 'todas' ? '' : "AND ubicacion = :ubicacion";

// Obtener pedidos
$query = "
    SELECT id, nombre, apellido, producto, precio, estado, modalidad,
           observaciones, direccion, telefono, forma_pago, cantidad,
           created_at, fecha_entrega, fecha_display,
           TIMESTAMPDIFF(MINUTE, created_at, NOW()) as minutos_transcurridos,
           impreso, ubicacion
    FROM pedidos
    WHERE (
        (fecha_entrega IS NULL AND DATE(created_at) = CURDATE())
        OR (fecha_entrega IS NOT NULL AND DATE(fecha_entrega) = CURDATE())
    )
    AND estado != 'Entregado'
    $where_ubicacion
    ORDER BY
        CASE estado
            WHEN 'Pendiente' THEN 1
            WHEN 'Preparando' THEN 2
            WHEN 'Listo' THEN 3
        END,
        created_at ASC
";

$stmt = $pdo->prepare($query);
if ($ubicacion_filtro !== 'todas') {
    $stmt->bindValue(':ubicacion', $ubicacion_filtro);
}
$stmt->execute();
$pedidos = $stmt->fetchAll();

// Stats
$total = count($pedidos);
$pendientes = count(array_filter($pedidos, fn($p) => $p['estado'] === 'Pendiente'));
$preparando = count(array_filter($pedidos, fn($p) => $p['estado'] === 'Preparando'));
$listos = count(array_filter($pedidos, fn($p) => $p['estado'] === 'Listo'));
$sin_imprimir = count(array_filter($pedidos, fn($p) => $p['impreso'] == 0));

// Obtener ubicaciones disponibles
$ubicaciones = $pdo->query("
    SELECT DISTINCT ubicacion FROM pedidos
    WHERE (
        (fecha_entrega IS NULL AND DATE(created_at) = CURDATE())
        OR (fecha_entrega IS NOT NULL AND DATE(fecha_entrega) = CURDATE())
    )
    ORDER BY ubicacion
")->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Dashboard Pedidos</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Arial', sans-serif; }

        /* VISTA COMPACTA */
        .pedido-compacto {
            transition: all 0.2s ease;
            border-left: 4px solid #ccc;
        }
        .pedido-compacto:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            transform: translateY(-2px);
        }
        .pedido-pendiente { border-left-color: #f59e0b; background: #fffbeb; }
        .pedido-preparando { border-left-color: #3b82f6; background: #eff6ff; }
        .pedido-listo { border-left-color: #10b981; background: #f0fdf4; }
        .sin-imprimir { border-right: 4px solid #ef4444; }
        .urgente { animation: pulse 2s infinite; }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.8; } }

        /* BOTONES COMPACTOS */
        .btn-compact {
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .btn-compact:hover { transform: scale(1.05); }

        /* TABS DE FILTRO */
        .filter-tab {
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
            font-weight: 600;
            font-size: 13px;
        }
        .filter-tab.active {
            background: #f97316;
            color: white;
            box-shadow: 0 4px 8px rgba(249, 115, 22, 0.3);
        }

        /* TOGGLE VISTA */
        .view-toggle {
            background: white;
            border: 2px solid #e5e7eb;
            padding: 6px;
            border-radius: 8px;
            display: flex;
            gap: 4px;
        }
        .view-toggle button {
            padding: 6px 12px;
            border-radius: 4px;
            border: none;
            background: transparent;
            cursor: pointer;
            transition: all 0.2s;
        }
        .view-toggle button.active {
            background: #f97316;
            color: white;
        }

        /* VISTA LISTA */
        .lista-item {
            display: grid;
            grid-template-columns: 60px 1fr 120px 200px 100px 140px;
            gap: 12px;
            align-items: center;
            padding: 12px;
            border-bottom: 1px solid #e5e7eb;
            transition: all 0.2s;
        }
        .lista-item:hover {
            background: #f9fafb;
        }

        /* BADGE PEQUEÑO */
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
        }

        /* BOTÓN FLOTANTE REPOSICIONADO */
        .btn-flotante {
            position: fixed;
            bottom: 20px;
            left: 20px;
            z-index: 40;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }

        /* SCROLL OPTIMIZADO */
        .pedidos-container {
            max-height: calc(100vh - 200px);
            overflow-y: auto;
        }
        .pedidos-container::-webkit-scrollbar {
            width: 8px;
        }
        .pedidos-container::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        .pedidos-container::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 4px;
        }
    </style>
</head>
<body class="bg-gray-50">

    <!-- HEADER COMPACTO RESPONSIVE -->
    <header class="bg-orange-600 text-white p-2 sm:p-3 shadow-lg sticky top-0 z-50">
        <div class="max-w-7xl mx-auto">
            <div class="flex justify-between items-center mb-2 sm:mb-0">
                <div class="flex items-center space-x-2 sm:space-x-3">
                    <h1 class="text-base sm:text-xl font-bold">
                        <i class="fas fa-utensils mr-1"></i>
                        <span class="hidden sm:inline">ADMIN - DASHBOARD PEDIDOS</span>
                        <span class="sm:hidden">ADMIN</span>
                    </h1>
                    <div id="clock" class="text-orange-100 text-xs sm:text-sm"></div>
                </div>

                <div class="flex items-center space-x-1 sm:space-x-2">
                    <a href="../../index.php" class="bg-orange-500 hover:bg-orange-400 px-2 sm:px-3 py-1 sm:py-1.5 rounded text-xs">
                        <i class="fas fa-home sm:mr-1"></i><span class="hidden sm:inline">Inicio</span>
                    </a>
                    <a href="ver_pedidos.php" class="bg-green-500 hover:bg-green-400 px-2 sm:px-3 py-1 sm:py-1.5 rounded text-xs" title="Ver historial completo y filtrar pedidos">
                        <i class="fas fa-history sm:mr-1"></i><span class="hidden sm:inline">Historial</span><span class="sm:hidden">Hist</span>
                    </a>
                    <a href="../../logout.php" class="bg-red-500 hover:bg-red-600 px-2 sm:px-3 py-1 sm:py-1.5 rounded text-xs">
                        <i class="fas fa-sign-out-alt sm:mr-1"></i><span class="hidden sm:inline">Salir</span>
                    </a>
                </div>
            </div>

            <!-- STATS COMPACTOS RESPONSIVE -->
            <div class="grid grid-cols-5 gap-1 sm:gap-4 text-center text-xs mt-2">
                <div>
                    <div class="text-sm sm:text-lg font-bold"><?= $total ?></div>
                    <div class="text-orange-200 text-xs">Total</div>
                </div>
                <div class="text-yellow-300">
                    <div class="text-sm sm:text-lg font-bold"><?= $pendientes ?></div>
                    <div class="text-xs">Pend.</div>
                </div>
                <div class="text-blue-200">
                    <div class="text-sm sm:text-lg font-bold"><?= $preparando ?></div>
                    <div class="text-xs">Prep.</div>
                </div>
                <div class="text-green-200">
                    <div class="text-sm sm:text-lg font-bold"><?= $listos ?></div>
                    <div class="text-xs">Listos</div>
                </div>
                <div class="text-red-200">
                    <div class="text-sm sm:text-lg font-bold"><?= $sin_imprimir ?></div>
                    <div class="text-xs hidden sm:inline">Sin Imp.</div>
                    <div class="text-xs sm:hidden">S/I</div>
                </div>
            </div>
        </div>
    </header>

    <!-- BARRA DE FILTROS Y VISTA RESPONSIVE -->
    <div class="bg-white border-b p-2 sm:p-3 sticky top-16 sm:top-20 z-40 shadow-sm">
        <div class="max-w-7xl mx-auto">
            <!-- FILTRO DE UBICACIÓN (ADMIN) -->
            <div class="mb-2 flex flex-wrap items-center gap-2">
                <span class="text-sm font-semibold text-gray-700">
                    <i class="fas fa-map-marker-alt mr-1"></i>Ubicación:
                </span>
                <a href="?ubicacion=todas" class="filter-tab <?= $ubicacion_filtro === 'todas' ? 'active' : 'bg-gray-100 text-gray-700' ?> text-xs sm:text-sm">
                    Todas (<?= $total ?>)
                </a>
                <?php foreach ($ubicaciones as $ub): ?>
                    <?php
                        $count_ub = count(array_filter($pedidos, fn($p) => $p['ubicacion'] === $ub));
                        if ($ubicacion_filtro === $ub || $ubicacion_filtro === 'todas') {
                    ?>
                    <a href="?ubicacion=<?= urlencode($ub) ?>"
                       class="filter-tab <?= $ubicacion_filtro === $ub ? 'active' : 'bg-gray-100 text-gray-700' ?> text-xs sm:text-sm">
                        <?= htmlspecialchars($ub) ?> (<?= $ubicacion_filtro === 'todas' ? count(array_filter($pedidos, fn($p) => $p['ubicacion'] === $ub)) : $count_ub ?>)
                    </a>
                    <?php } endforeach; ?>
            </div>

            <!-- FILTROS POR ESTADO RESPONSIVE -->
            <div class="flex flex-wrap gap-1 sm:gap-2 mb-2">
                <div class="filter-tab active text-xs sm:text-sm" onclick="filtrarEstado('todos')" data-estado="todos">
                    Todos (<?= $total ?>)
                </div>
                <div class="filter-tab bg-yellow-100 text-yellow-800 text-xs sm:text-sm" onclick="filtrarEstado('Pendiente')" data-estado="Pendiente">
                    <span class="hidden sm:inline">Pendientes</span><span class="sm:hidden">Pend.</span> (<?= $pendientes ?>)
                </div>
                <div class="filter-tab bg-blue-100 text-blue-800 text-xs sm:text-sm" onclick="filtrarEstado('Preparando')" data-estado="Preparando">
                    <span class="hidden sm:inline">Preparando</span><span class="sm:hidden">Prep.</span> (<?= $preparando ?>)
                </div>
                <div class="filter-tab bg-green-100 text-green-800 text-xs sm:text-sm" onclick="filtrarEstado('Listo')" data-estado="Listo">
                    Listos (<?= $listos ?>)
                </div>
            </div>

            <!-- TOGGLE VISTA -->
            <div class="view-toggle">
                <button class="active" onclick="cambiarVista('cards')" data-vista="cards">
                    <i class="fas fa-th-large"></i>
                </button>
                <button onclick="cambiarVista('lista')" data-vista="lista">
                    <i class="fas fa-list"></i>
                </button>
            </div>
        </div>
    </div>

    <main class="max-w-7xl mx-auto p-4">
        <?php if (empty($pedidos)): ?>
            <div class="text-center py-20">
                <i class="fas fa-coffee text-6xl text-gray-300 mb-4"></i>
                <h2 class="text-2xl text-gray-500">No hay pedidos pendientes</h2>
                <p class="text-gray-400">Todos los pedidos están al día</p>
            </div>
        <?php else: ?>

            <!-- VISTA CARDS COMPACTA -->
            <div id="vistaCards" class="pedidos-container grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3">
                <?php foreach ($pedidos as $pedido): ?>
                    <div class="pedido-compacto pedido-<?= strtolower($pedido['estado']) ?>
                                <?= !$pedido['impreso'] ? 'sin-imprimir' : '' ?>
                                <?= $pedido['minutos_transcurridos'] > 60 ? 'urgente' : '' ?>
                                bg-white rounded-lg shadow p-3"
                         data-estado="<?= $pedido['estado'] ?>"
                         data-id="<?= $pedido['id'] ?>">

                        <!-- Header Compacto -->
                        <div class="flex justify-between items-start mb-2">
                            <div class="flex-1">
                                <div class="font-bold text-sm text-gray-800">
                                    #<?= $pedido['id'] ?> - <?= htmlspecialchars($pedido['nombre'] . ' ' . $pedido['apellido']) ?>
                                </div>
                                <div class="text-xs text-gray-500 flex items-center gap-2 flex-wrap">
                                    <span><i class="fas fa-clock"></i> <?= $pedido['minutos_transcurridos'] ?>min</span>
                                    <span class="badge bg-purple-500 text-white"><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($pedido['ubicacion']) ?></span>
                                    <?php if (!$pedido['impreso']): ?>
                                        <span class="badge bg-red-500 text-white">SIN IMP.</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Producto Compacto -->
                        <div class="mb-2 text-sm">
                            <div class="font-bold text-gray-800"><?= htmlspecialchars($pedido['producto']) ?></div>
                            <div class="text-green-600 font-bold text-lg">$<?= number_format($pedido['precio'], 0, ',', '.') ?></div>
                            <div class="text-xs text-gray-600 flex gap-2">
                                <span><i class="fas fa-<?= $pedido['modalidad'] === 'Retiro' ? 'store' : 'motorcycle' ?>"></i> <?= $pedido['modalidad'] ?></span>
                                <span><i class="fas fa-money-bill"></i> <?= $pedido['forma_pago'] ?></span>
                            </div>
                        </div>

                        <!-- Observaciones Colapsables -->
                        <?php if ($pedido['observaciones']): ?>
                            <details class="mb-2">
                                <summary class="text-xs text-blue-600 cursor-pointer hover:underline">
                                    <i class="fas fa-sticky-note"></i> Ver obs.
                                </summary>
                                <div class="text-xs bg-blue-50 p-2 mt-1 rounded">
                                    <?= nl2br(htmlspecialchars(substr($pedido['observaciones'], 0, 100))) ?>
                                    <?= strlen($pedido['observaciones']) > 100 ? '...' : '' ?>
                                </div>
                            </details>
                        <?php endif; ?>

                        <!-- Acciones Compactas (ADMIN con botón eliminar) -->
                        <div class="flex gap-1 flex-wrap">
                            <?php if ($pedido['estado'] === 'Pendiente'): ?>
                                <button onclick="cambiarEstado(<?= $pedido['id'] ?>, 'Preparando')"
                                        class="btn-compact" style="background: #3b82f6; color: white;">
                                    <i class="fas fa-fire"></i> Preparar
                                </button>
                            <?php elseif ($pedido['estado'] === 'Preparando'): ?>
                                <button onclick="cambiarEstado(<?= $pedido['id'] ?>, 'Listo')"
                                        class="btn-compact" style="background: #10b981; color: white;">
                                    <i class="fas fa-check"></i> Listo
                                </button>
                            <?php elseif ($pedido['estado'] === 'Listo'): ?>
                                <button onclick="cambiarEstado(<?= $pedido['id'] ?>, 'Entregado')"
                                        class="btn-compact" style="background: #6b7280; color: white;">
                                    <i class="fas fa-handshake"></i> Entregar
                                </button>
                            <?php endif; ?>

                            <button onclick="abrirEditarPedido(<?= $pedido['id'] ?>)"
                                    class="btn-compact" style="background: #8b5cf6; color: white;" title="Editar pedido">
                                <i class="fas fa-edit"></i>
                            </button>

                            <?php if ($pedido['impreso']): ?>
                                <!-- Botón imprimir bloqueado -->
                                <button class="btn-compact" style="background: #9ca3af; color: white; cursor: not-allowed;" disabled title="Ya impreso">
                                    <i class="fas fa-print"></i> <i class="fas fa-check text-xs"></i>
                                </button>
                                <!-- Botón emergencia re-imprimir -->
                                <button onclick="reimprimirEmergencia(<?= $pedido['id'] ?>)"
                                        class="btn-compact" style="background: #dc2626; color: white;" title="Re-imprimir (Emergencia)">
                                    <i class="fas fa-redo"></i>
                                </button>
                            <?php else: ?>
                                <!-- Botón imprimir normal -->
                                <button onclick="imprimir(<?= $pedido['id'] ?>)"
                                        class="btn-compact" style="background: #f59e0b; color: white;">
                                    <i class="fas fa-print"></i>
                                </button>
                            <?php endif; ?>

                            <!-- BOTÓN ELIMINAR (SOLO ADMIN) -->
                            <button onclick="eliminarPedido(<?= $pedido['id'] ?>)"
                                    class="btn-compact" style="background: #ef4444; color: white;" title="Eliminar pedido">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- VISTA LISTA -->
            <div id="vistaLista" class="pedidos-container hidden bg-white rounded-lg shadow">
                <div class="lista-item font-bold text-xs text-gray-600 bg-gray-50 border-b-2 border-gray-300">
                    <div>#ID</div>
                    <div>CLIENTE / PRODUCTO</div>
                    <div>UBICACIÓN</div>
                    <div>MODALIDAD / PAGO</div>
                    <div>PRECIO</div>
                    <div class="text-center">ACCIONES</div>
                </div>
                <?php foreach ($pedidos as $pedido): ?>
                    <div class="lista-item pedido-<?= strtolower($pedido['estado']) ?>
                                <?= !$pedido['impreso'] ? 'sin-imprimir' : '' ?>"
                         data-estado="<?= $pedido['estado'] ?>"
                         data-id="<?= $pedido['id'] ?>">

                        <div class="font-bold text-sm">
                            #<?= $pedido['id'] ?>
                            <div class="text-xs text-gray-500"><?= $pedido['minutos_transcurridos'] ?>min</div>
                            <?php if (!$pedido['impreso']): ?>
                                <span class="badge bg-red-500 text-white mt-1">SIN IMP</span>
                            <?php endif; ?>
                        </div>

                        <div>
                            <div class="font-bold text-sm"><?= htmlspecialchars($pedido['nombre'] . ' ' . $pedido['apellido']) ?></div>
                            <div class="text-xs text-gray-600"><?= htmlspecialchars($pedido['producto']) ?></div>
                        </div>

                        <div class="text-xs">
                            <span class="badge bg-purple-500 text-white">
                                <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($pedido['ubicacion']) ?>
                            </span>
                        </div>

                        <div class="text-xs">
                            <div><i class="fas fa-<?= $pedido['modalidad'] === 'Retiro' ? 'store' : 'motorcycle' ?>"></i> <?= $pedido['modalidad'] ?></div>
                            <div><i class="fas fa-money-bill"></i> <?= $pedido['forma_pago'] ?></div>
                        </div>

                        <div class="font-bold text-green-600">
                            $<?= number_format($pedido['precio'], 0, ',', '.') ?>
                        </div>

                        <div class="flex gap-1 justify-center flex-wrap">
                            <?php if ($pedido['estado'] === 'Pendiente'): ?>
                                <button onclick="cambiarEstado(<?= $pedido['id'] ?>, 'Preparando')"
                                        class="btn-compact" style="background: #3b82f6; color: white;">
                                    <i class="fas fa-fire"></i>
                                </button>
                            <?php elseif ($pedido['estado'] === 'Preparando'): ?>
                                <button onclick="cambiarEstado(<?= $pedido['id'] ?>, 'Listo')"
                                        class="btn-compact" style="background: #10b981; color: white;">
                                    <i class="fas fa-check"></i>
                                </button>
                            <?php elseif ($pedido['estado'] === 'Listo'): ?>
                                <button onclick="cambiarEstado(<?= $pedido['id'] ?>, 'Entregado')"
                                        class="btn-compact" style="background: #6b7280; color: white;">
                                    <i class="fas fa-handshake"></i>
                                </button>
                            <?php endif; ?>

                            <button onclick="abrirEditarPedido(<?= $pedido['id'] ?>)"
                                    class="btn-compact" style="background: #8b5cf6; color: white;">
                                <i class="fas fa-edit"></i>
                            </button>

                            <?php if ($pedido['impreso']): ?>
                                <!-- Botón imprimir bloqueado -->
                                <button class="btn-compact" style="background: #9ca3af; color: white; cursor: not-allowed;" disabled title="Ya impreso">
                                    <i class="fas fa-print"></i> <i class="fas fa-check text-xs"></i>
                                </button>
                                <!-- Botón emergencia re-imprimir -->
                                <button onclick="reimprimirEmergencia(<?= $pedido['id'] ?>)"
                                        class="btn-compact" style="background: #dc2626; color: white;" title="Re-imprimir (Emergencia)">
                                    <i class="fas fa-redo"></i>
                                </button>
                            <?php else: ?>
                                <!-- Botón imprimir normal -->
                                <button onclick="imprimir(<?= $pedido['id'] ?>)"
                                        class="btn-compact" style="background: #f59e0b; color: white;">
                                    <i class="fas fa-print"></i>
                                </button>
                            <?php endif; ?>

                            <button onclick="eliminarPedido(<?= $pedido['id'] ?>)"
                                    class="btn-compact" style="background: #ef4444; color: white;">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

        <?php endif; ?>
    </main>

    <!-- BOTÓN FLOTANTE (IZQUIERDA ABAJO) -->
    <button onclick="abrirPedidoExpress()"
            class="btn-flotante bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-5 rounded-full">
        <i class="fas fa-plus-circle mr-2"></i>
        Express
    </button>

  <!-- ============================================ -->
<!-- MODAL PEDIDO EXPRESS CON SISTEMA DE PASOS -->
<!-- ============================================ -->

<div id="modalPedidoExpress" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-y-auto">

        <!-- Header con indicador de pasos -->
        <div class="bg-gradient-to-r from-green-600 to-green-700 text-white p-6 rounded-t-lg sticky top-0 z-10">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-2xl font-bold">
                    <i class="fas fa-bolt mr-2"></i>Pedido Express - Admin
                </h2>
                <button onclick="cerrarPedidoExpress()" class="text-white hover:text-gray-200">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>

            <!-- Indicador de pasos -->
            <div class="flex items-center justify-center space-x-2">
                <div id="indicador-paso-1" class="paso-indicador activo">
                    <div class="w-10 h-10 rounded-full bg-white text-green-600 flex items-center justify-center font-bold">1</div>
                    <span class="text-xs mt-1">Datos</span>
                </div>
                <div class="flex-1 h-1 bg-white bg-opacity-30 mx-2"></div>
                <div id="indicador-paso-2" class="paso-indicador">
                    <div class="w-10 h-10 rounded-full bg-white bg-opacity-30 text-white flex items-center justify-center font-bold">2</div>
                    <span class="text-xs mt-1">Tipo</span>
                </div>
                <div class="flex-1 h-1 bg-white bg-opacity-30 mx-2"></div>
                <div id="indicador-paso-3" class="paso-indicador">
                    <div class="w-10 h-10 rounded-full bg-white bg-opacity-30 text-white flex items-center justify-center font-bold">3</div>
                    <span class="text-xs mt-1">Pedido</span>
                </div>
                <div class="flex-1 h-1 bg-white bg-opacity-30 mx-2"></div>
                <div id="indicador-paso-4" class="paso-indicador">
                    <div class="w-10 h-10 rounded-full bg-white bg-opacity-30 text-white flex items-center justify-center font-bold">4</div>
                    <span class="text-xs mt-1">Resumen</span>
                </div>
            </div>
        </div>

        <form id="formPedidoExpress">
            <div class="p-6">

                <!-- ============================================ -->
                <!-- PASO 1: DATOS DEL CLIENTE -->
                <!-- ============================================ -->
                <div id="paso1" class="paso-container">
                    <h3 class="text-xl font-bold text-gray-800 mb-6 flex items-center">
                        <i class="fas fa-user-circle mr-3 text-green-600"></i>
                        Datos del Cliente
                    </h3>

                    <!-- Datos personales -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Nombre *
                            </label>
                            <input type="text" id="nombre" required
                                   class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:border-green-500 focus:ring-2 focus:ring-green-200 text-lg"
                                   placeholder="Juan">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Apellido *
                            </label>
                            <input type="text" id="apellido" required
                                   class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:border-green-500 focus:ring-2 focus:ring-green-200 text-lg"
                                   placeholder="Pérez">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Teléfono
                            </label>
                            <input type="tel" id="telefono"
                                   class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:border-green-500 focus:ring-2 focus:ring-green-200 text-lg"
                                   placeholder="11 1234-5678">
                        </div>
                    </div>

                    <!-- Ubicación (ADMIN selecciona ubicación) -->
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-3">Ubicación *</label>
                        <div class="grid grid-cols-2 gap-4">
                            <label class="pago-card">
                                <input type="radio" name="ubicacion" value="Local 1" class="hidden" checked>
                                <div class="text-3xl mb-2">🏪</div>
                                <div class="font-bold">Local 1</div>
                            </label>
                            <label class="pago-card">
                                <input type="radio" name="ubicacion" value="Local 2" class="hidden">
                                <div class="text-3xl mb-2">🏬</div>
                                <div class="font-bold">Local 2</div>
                            </label>
                        </div>
                    </div>

                    <!-- Turno -->
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-3">Turno *</label>
                        <div class="grid grid-cols-3 gap-4">
                            <div class="turno-card" onclick="seleccionarTurno('Mañana', this)">
                                <input type="radio" name="turno" value="Mañana" class="hidden">
                                <div class="text-4xl mb-2">🌅</div>
                                <div class="font-bold">MAÑANA</div>
                                <div class="text-sm text-gray-600">09:00 - 13:00</div>
                            </div>
                            <div class="turno-card" onclick="seleccionarTurno('Siesta', this)">
                                <input type="radio" name="turno" value="Siesta" class="hidden">
                                <div class="text-4xl mb-2">☀️</div>
                                <div class="font-bold">SIESTA</div>
                                <div class="text-sm text-gray-600">13:00 - 16:00</div>
                            </div>
                            <div class="turno-card" onclick="seleccionarTurno('Tarde', this)">
                                <input type="radio" name="turno" value="Tarde" class="hidden">
                                <div class="text-4xl mb-2">🌙</div>
                                <div class="font-bold">TARDE</div>
                                <div class="text-sm text-gray-600">16:00 - 21:00</div>
                            </div>
                        </div>
                    </div>

                    <!-- Forma de pago -->
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-3">Forma de Pago *</label>
                        <div class="grid grid-cols-2 gap-4">
                            <label class="pago-card">
                                <input type="radio" name="forma_pago" value="Efectivo" class="hidden">
                                <div class="text-3xl mb-2">💵</div>
                                <div class="font-bold">Efectivo</div>
                            </label>
                            <label class="pago-card">
                                <input type="radio" name="forma_pago" value="Transferencia" class="hidden">
                                <div class="text-3xl mb-2">💳</div>
                                <div class="font-bold">Transferencia</div>
                            </label>
                        </div>
                    </div>

                    <!-- Estado de pago (para clientes por WhatsApp) -->
                    <div class="mb-6 bg-blue-50 border-2 border-blue-200 rounded-lg p-4">
                        <label class="flex items-center cursor-pointer">
                            <input type="checkbox" id="yaPagado" name="ya_pagado" value="1" class="w-5 h-5 text-green-600 mr-3">
                            <div>
                                <span class="font-bold text-gray-800">✅ Ya está pagado</span>
                                <p class="text-xs text-gray-600 mt-1">(Para clientes que pagaron por WhatsApp/anticipado)</p>
                            </div>
                        </label>
                    </div>

                    <!-- Botón siguiente -->
                    <div class="flex justify-end">
                        <button type="button" onclick="irAPaso(2)"
                                class="px-8 py-3 bg-green-600 hover:bg-green-700 text-white rounded-lg font-bold text-lg">
                            SIGUIENTE <i class="fas fa-arrow-right ml-2"></i>
                        </button>
                    </div>
                </div>

                <!-- Los demás pasos (2, 3a, 3b, 4) son idénticos al empleado -->
                <!-- Por brevedad, incluiré solo las diferencias clave -->

                <!-- PASO 2, 3A, 3B, 4 son IGUALES al empleado, los incluyo completos a continuación -->

                <?php
                // Incluir el resto del modal desde empleados/dashboard.php
                // Por brevedad del código, copiaré solo la estructura básica
                ?>

                <!-- PASO 2: SELECCIONAR TIPO DE PEDIDO -->
                <div id="paso2" class="paso-container hidden">
                    <h3 class="text-xl font-bold text-gray-800 mb-6 flex items-center">
                        <i class="fas fa-clipboard-list mr-3 text-green-600"></i>
                        ¿Qué tipo de pedido?
                    </h3>

                    <div class="grid grid-cols-2 gap-6 mb-6">
                        <!-- Pedido Común -->
                        <div class="tipo-pedido-card" onclick="seleccionarTipoPedido('comun')">
                            <div class="text-6xl mb-4">🍔</div>
                            <h4 class="text-2xl font-bold mb-2">COMÚN</h4>
                            <p class="text-gray-600">Combos armados</p>
                        </div>

                        <!-- Pedido Personalizado -->
                        <div class="tipo-pedido-card" onclick="seleccionarTipoPedido('personalizado')">
                            <div class="text-6xl mb-4">🎨</div>
                            <h4 class="text-2xl font-bold mb-2">PERSONALIZADO</h4>
                            <p class="text-gray-600">Elegir planchas</p>
                        </div>
                    </div>

                    <!-- Botones navegación -->
                    <div class="flex justify-between">
                        <button type="button" onclick="irAPaso(1)"
                                class="px-6 py-3 bg-gray-500 hover:bg-gray-600 text-white rounded-lg font-semibold">
                            <i class="fas fa-arrow-left mr-2"></i>VOLVER A DATOS
                        </button>
                        <button type="button" onclick="cerrarPedidoExpress()"
                                class="px-6 py-3 bg-red-500 hover:bg-red-600 text-white rounded-lg font-semibold">
                            <i class="fas fa-times mr-2"></i>CANCELAR
                        </button>
                    </div>
                </div>

                <!-- PASO 3A: PEDIDO COMÚN - Similar al empleado -->
                <div id="paso3comun" class="paso-container hidden">
                    <h3 class="text-xl font-bold text-gray-800 mb-6 flex items-center">
                        <i class="fas fa-hamburger mr-3 text-green-600"></i>
                        Seleccioná los combos
                    </h3>

                    <div class="space-y-3 mb-6">
                        <!-- JyQ x24 -->
                        <div class="combo-item" data-tipo="jyq24" data-precio="18000">
                            <label class="flex items-center p-4 border-2 border-gray-300 rounded-lg hover:border-green-500 cursor-pointer transition-all">
                                <input type="checkbox" class="combo-checkbox w-5 h-5 mr-4">
                                <div class="flex-1">
                                    <div class="font-bold text-lg">Jamón y Queso x24</div>
                                    <div class="text-green-600 font-bold">$18.000</div>
                                </div>
                                <div class="flex items-center space-x-3">
                                    <button type="button" onclick="cambiarCantidadCombo(this, -1)" class="cantidad-btn">-</button>
                                    <span class="cantidad-display font-bold text-lg w-8 text-center">1</span>
                                    <button type="button" onclick="cambiarCantidadCombo(this, 1)" class="cantidad-btn">+</button>
                                </div>
                            </label>
                        </div>

                        <!-- JyQ x48 -->
                        <div class="combo-item" data-tipo="jyq48" data-precio="22000">
                            <label class="flex items-center p-4 border-2 border-gray-300 rounded-lg hover:border-green-500 cursor-pointer transition-all">
                                <input type="checkbox" class="combo-checkbox w-5 h-5 mr-4">
                                <div class="flex-1">
                                    <div class="font-bold text-lg">Jamón y Queso x48</div>
                                    <div class="text-green-600 font-bold">$22.000</div>
                                </div>
                                <div class="flex items-center space-x-3">
                                    <button type="button" onclick="cambiarCantidadCombo(this, -1)" class="cantidad-btn">-</button>
                                    <span class="cantidad-display font-bold text-lg w-8 text-center">1</span>
                                    <button type="button" onclick="cambiarCantidadCombo(this, 1)" class="cantidad-btn">+</button>
                                </div>
                            </label>
                        </div>

                        <!-- Surtido Clásico -->
                        <div class="combo-item" data-tipo="surtido_clasico48" data-precio="20000">
                            <label class="flex items-center p-4 border-2 border-gray-300 rounded-lg hover:border-green-500 cursor-pointer transition-all">
                                <input type="checkbox" class="combo-checkbox w-5 h-5 mr-4">
                                <div class="flex-1">
                                    <div class="font-bold text-lg">Surtido Clásico x48</div>
                                    <div class="text-green-600 font-bold">$20.000</div>
                                </div>
                                <div class="flex items-center space-x-3">
                                    <button type="button" onclick="cambiarCantidadCombo(this, -1)" class="cantidad-btn">-</button>
                                    <span class="cantidad-display font-bold text-lg w-8 text-center">1</span>
                                    <button type="button" onclick="cambiarCantidadCombo(this, 1)" class="cantidad-btn">+</button>
                                </div>
                            </label>
                        </div>

                        <!-- Surtido Especial -->
                        <div class="combo-item" data-tipo="surtido_especial48" data-precio="25000">
                            <label class="flex items-center p-4 border-2 border-gray-300 rounded-lg hover:border-green-500 cursor-pointer transition-all">
                                <input type="checkbox" class="combo-checkbox w-5 h-5 mr-4">
                                <div class="flex-1">
                                    <div class="font-bold text-lg">Surtido Especial x48</div>
                                    <div class="text-green-600 font-bold">$25.000</div>
                                </div>
                                <div class="flex items-center space-x-3">
                                    <button type="button" onclick="cambiarCantidadCombo(this, -1)" class="cantidad-btn">-</button>
                                    <span class="cantidad-display font-bold text-lg w-8 text-center">1</span>
                                    <button type="button" onclick="cambiarCantidadCombo(this, 1)" class="cantidad-btn">+</button>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Observaciones -->
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Observaciones (opcional)</label>
                        <textarea id="observaciones_comun" rows="3"
                                  class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:border-green-500"
                                  placeholder="Ej: Sin lechuga, tomate a parte..."></textarea>
                    </div>

                    <!-- Botones navegación -->
                    <div class="flex justify-between">
                        <button type="button" onclick="irAPaso(2)"
                                class="px-6 py-3 bg-gray-500 hover:bg-gray-600 text-white rounded-lg font-semibold">
                            <i class="fas fa-arrow-left mr-2"></i>VOLVER A TIPO
                        </button>
                        <button type="button" onclick="agregarPedidosComunes()"
                                class="px-8 py-3 bg-green-600 hover:bg-green-700 text-white rounded-lg font-bold">
                            <i class="fas fa-check mr-2"></i>AGREGAR PEDIDO(S)
                        </button>
                    </div>
                </div>

                <!-- PASO 3B: PEDIDO PERSONALIZADO -->
                <div id="paso3personalizado" class="paso-container hidden">
                    <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                        <i class="fas fa-palette mr-3 text-green-600"></i>
                        Armá tu pedido personalizado
                    </h3>

                    <!-- Contador total -->
                    <div class="bg-green-50 border-2 border-green-300 rounded-lg p-4 mb-4">
                        <div class="flex justify-between items-center">
                            <span class="text-lg font-semibold">Total de planchas:</span>
                            <span id="totalPlanchas" class="text-3xl font-bold text-green-600">0</span>
                        </div>
                        <div class="text-sm text-gray-600 mt-1">
                            <span id="totalSandwiches">0</span> sándwiches totales (8 por plancha)
                        </div>
                    </div>

                    <!-- Sabores Comunes -->
                    <div class="mb-4">
                        <h4 class="font-bold text-green-700 mb-2">🟢 SABORES COMUNES</h4>
                        <div id="saboresComunes" class="grid grid-cols-4 gap-2"></div>
                    </div>

                    <!-- Sabores Premium -->
                    <div class="mb-4">
                        <h4 class="font-bold text-orange-600 mb-2">🟠 SABORES PREMIUM</h4>
                        <div id="saboresPremium" class="grid grid-cols-5 gap-2"></div>
                    </div>

                    <!-- Botón deshacer -->
                    <div class="mb-4">
                        <button type="button" onclick="deshacer()"
                                class="px-4 py-2 bg-yellow-500 hover:bg-yellow-600 text-white rounded font-semibold">
                            <i class="fas fa-undo mr-2"></i>Deshacer última plancha
                        </button>
                    </div>

                    <!-- Precio -->
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Precio Total *</label>
                        <input type="number" id="precioPersonalizado" step="500" required
                               class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:border-green-500 text-lg"
                               placeholder="Ej: 14500">
                    </div>

                    <!-- Observaciones -->
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Observaciones (opcional)</label>
                        <textarea id="observaciones_personalizado" rows="3"
                                  class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:border-green-500"
                                  placeholder="Ej: Con pan y queso extra..."></textarea>
                    </div>

                    <!-- Botones navegación -->
                    <div class="flex justify-between">
                        <button type="button" onclick="irAPaso(2)"
                                class="px-6 py-3 bg-gray-500 hover:bg-gray-600 text-white rounded-lg font-semibold">
                            <i class="fas fa-arrow-left mr-2"></i>VOLVER A TIPO
                        </button>
                        <button type="button" onclick="agregarPedidoPersonalizado()"
                                class="px-8 py-3 bg-green-600 hover:bg-green-700 text-white rounded-lg font-bold">
                            <i class="fas fa-check mr-2"></i>AGREGAR PEDIDO
                        </button>
                    </div>
                </div>

                <!-- PASO 4: RESUMEN -->
                <div id="paso4" class="paso-container hidden">
                    <h3 class="text-xl font-bold text-gray-800 mb-6 flex items-center">
                        <i class="fas fa-list-check mr-3 text-green-600"></i>
                        Resumen del Pedido
                    </h3>

                    <!-- Info del cliente -->
                    <div class="bg-blue-50 border-2 border-blue-300 rounded-lg p-4 mb-6">
                        <div class="font-bold text-lg mb-2" id="resumenCliente">Cliente: -</div>
                        <div class="text-sm text-gray-700">
                            <span id="resumenUbicacion">Ubicación: -</span> |
                            <span id="resumenTurno">Turno: -</span> |
                            <span id="resumenPago">Pago: -</span>
                        </div>
                    </div>

                    <!-- Lista de pedidos -->
                    <div id="listaPedidosResumen" class="space-y-3 mb-6">
                        <!-- Se llena dinámicamente -->
                    </div>

                    <!-- Total -->
                    <div class="bg-green-50 border-2 border-green-500 rounded-lg p-4 mb-6">
                        <div class="flex justify-between items-center">
                            <span class="text-xl font-bold">TOTAL:</span>
                            <span id="totalFinal" class="text-3xl font-bold text-green-600">$0</span>
                        </div>
                    </div>

                    <!-- Botones finales -->
                    <div class="flex flex-col gap-3">
                        <button type="button" onclick="irAPaso(1)"
                                class="px-6 py-3 bg-gray-400 hover:bg-gray-500 text-white rounded-lg font-semibold">
                            <i class="fas fa-edit mr-2"></i>EDITAR DATOS DEL CLIENTE
                        </button>
                        <button type="button" onclick="irAPaso(2)"
                                class="px-6 py-3 bg-blue-500 hover:bg-blue-600 text-white rounded-lg font-bold text-lg">
                            <i class="fas fa-plus mr-2"></i>AGREGAR OTRO PEDIDO
                        </button>
                        <button type="button" onclick="finalizarYCrearPedidos()"
                                class="px-6 py-3 bg-green-600 hover:bg-green-700 text-white rounded-lg font-bold text-xl">
                            <i class="fas fa-check-circle mr-2"></i>FINALIZAR Y CREAR PEDIDOS
                        </button>
                        <button type="button" onclick="cerrarPedidoExpress()"
                                class="px-6 py-3 bg-red-500 hover:bg-red-600 text-white rounded-lg font-semibold">
                            <i class="fas fa-times-circle mr-2"></i>CANCELAR TODO
                        </button>
                    </div>
                </div>

            </div>
        </form>
    </div>
</div>

<!-- ============================================ -->
<!-- ESTILOS ADICIONALES -->
<!-- ============================================ -->
<style>
.paso-indicador {
    display: flex;
    flex-direction: column;
    align-items: center;
    transition: all 0.3s;
}

.paso-indicador.activo div:first-child {
    background: white;
    color: #16a34a;
    box-shadow: 0 0 0 4px rgba(255,255,255,0.3);
}

.paso-indicador.completado div:first-child {
    background: #22c55e;
    color: white;
}

.turno-card {
    border: 3px solid #e5e7eb;
    border-radius: 12px;
    padding: 20px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s;
}

.turno-card:hover {
    border-color: #22c55e;
    transform: translateY(-2px);
}

.turno-card.seleccionado {
    border-color: #16a34a;
    background: #f0fdf4;
}

.pago-card {
    border: 3px solid #e5e7eb;
    border-radius: 12px;
    padding: 20px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s;
    display: block;
}

.pago-card:hover {
    border-color: #22c55e;
    transform: translateY(-2px);
}

.pago-card:has(input:checked) {
    border-color: #16a34a;
    background: #f0fdf4;
}

.tipo-pedido-card {
    border: 4px solid #e5e7eb;
    border-radius: 16px;
    padding: 40px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s;
}

.tipo-pedido-card:hover {
    border-color: #22c55e;
    transform: scale(1.05);
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
}

.cantidad-btn {
    width: 36px;
    height: 36px;
    border: 2px solid #22c55e;
    border-radius: 8px;
    background: white;
    color: #16a34a;
    font-weight: bold;
    font-size: 18px;
    cursor: pointer;
    transition: all 0.2s;
}

.cantidad-btn:hover {
    background: #22c55e;
    color: white;
}

.combo-item label:has(input:checked) {
    border-color: #16a34a !important;
    background: #f0fdf4 !important;
}

.sabor-btn {
    transition: all 0.2s;
}

.sabor-btn:active {
    transform: scale(0.95);
}
</style>

<!-- ============================================ -->
<!-- MODAL EDITAR PEDIDO -->
<!-- ============================================ -->
<div id="modalEditarPedido" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">

        <!-- Header -->
        <div class="bg-gradient-to-r from-purple-600 to-purple-700 text-white p-6 rounded-t-lg">
            <div class="flex justify-between items-center">
                <h2 class="text-2xl font-bold">
                    <i class="fas fa-edit mr-2"></i>Editar Pedido #<span id="editPedidoId">-</span>
                </h2>
                <button onclick="cerrarEditarPedido()" class="text-white hover:text-gray-200">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>
        </div>

        <form id="formEditarPedido" class="p-6">
            <!-- Producto -->
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Producto *</label>
                <input type="text" id="editProducto" required
                       class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:border-purple-500 focus:ring-2 focus:ring-purple-200 text-lg"
                       placeholder="Ej: Jamón y Queso x24">
            </div>

            <!-- Cantidad -->
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Cantidad *</label>
                <input type="number" id="editCantidad" required min="1"
                       class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:border-purple-500 focus:ring-2 focus:ring-purple-200 text-lg"
                       placeholder="24">
            </div>

            <!-- Precio -->
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Precio *</label>
                <input type="number" id="editPrecio" required min="0" step="100"
                       class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:border-purple-500 focus:ring-2 focus:ring-purple-200 text-lg"
                       placeholder="18000">
            </div>

            <!-- Sabores personalizados (solo si aplica) -->
            <div id="editSaboresContainer" class="mb-4 hidden">
                <label class="block text-sm font-medium text-gray-700 mb-2">Sabores Personalizados</label>
                <div class="bg-purple-50 border-2 border-purple-200 rounded-lg p-4">
                    <p class="text-sm text-gray-600 mb-3">Edita las planchas por sabor:</p>
                    <div id="editSaboresList" class="space-y-2"></div>
                </div>
            </div>

            <!-- Observaciones -->
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">Observaciones</label>
                <textarea id="editObservaciones" rows="4"
                          class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:border-purple-500 focus:ring-2 focus:ring-purple-200"
                          placeholder="Observaciones adicionales..."></textarea>
            </div>

            <!-- Botones -->
            <div class="flex gap-3">
                <button type="button" onclick="guardarEdicionPedido()"
                        class="flex-1 px-6 py-3 bg-purple-600 hover:bg-purple-700 text-white rounded-lg font-bold text-lg">
                    <i class="fas fa-save mr-2"></i>GUARDAR CAMBIOS
                </button>
                <button type="button" onclick="cerrarEditarPedido()"
                        class="px-6 py-3 bg-gray-500 hover:bg-gray-600 text-white rounded-lg font-semibold">
                    <i class="fas fa-times mr-2"></i>CANCELAR
                </button>
            </div>
        </form>
    </div>
</div>

    <script>
// ============================================
// 🎯 SISTEMA DE PASOS - PEDIDO EXPRESS ADMIN
// ============================================

// Variables globales
let pasoActual = 1;
let pedidosAcumulados = [];
let datosCliente = null;
let planchasPorSabor = {};
let historial = [];

// IMPORTANTE: Precios ahora se cargan dinámicamente desde la base de datos
// Valores por defecto (se actualizan al abrir el modal)
let precios = {
    'jyq24': { nombre: 'Jamón y Queso x24', precio: 18000, cantidad: 24 },
    'jyq48': { nombre: 'Jamón y Queso x48', precio: 22000, cantidad: 48 },
    'surtido_clasico48': { nombre: 'Surtido Clásico x48', precio: 20000, cantidad: 48 },
    'surtido_especial48': { nombre: 'Surtido Especial x48', precio: 25000, cantidad: 48 }
};

// Función para cargar precios dinámicamente desde la API
async function cargarPreciosActualizados() {
    try {
        const response = await fetch('/api_precios.php');
        const data = await response.json();

        if (data.success) {
            // Actualizar el objeto precios con los valores de la base de datos
            precios = data.precios;

            // Actualizar los elementos HTML con los nuevos precios
            document.querySelectorAll('.combo-item').forEach(item => {
                const tipo = item.dataset.tipo;
                if (precios[tipo]) {
                    const precioValor = precios[tipo].precio;

                    // Actualizar data-precio
                    item.dataset.precio = precioValor;

                    // Actualizar el texto del precio mostrado
                    const precioDisplay = item.querySelector('.text-green-600');
                    if (precioDisplay) {
                        precioDisplay.textContent = '$' + new Intl.NumberFormat('es-AR').format(precioValor);
                    }
                }
            });

            console.log('Precios actualizados correctamente:', precios);
        } else {
            console.error('Error al cargar precios:', data.error);
        }
    } catch (error) {
        console.error('Error al cargar precios desde API:', error);
        // En caso de error, se usan los precios por defecto
    }
}

const saboresComunes = [
    'Jamón y Queso', 'Lechuga', 'Tomate', 'Huevo',
    'Choclo', 'Aceitunas', 'Zanahoria y Queso', 'Zanahoria y Huevo'
];

const saboresPremium = [
    'Ananá', 'Atún', 'Berenjena', 'Jamón Crudo',
    'Morrón', 'Palmito', 'Panceta', 'Pollo', 'Roquefort', 'Salame'
];

// ============================================
// FUNCIONES DE NAVEGACIÓN
// ============================================

async function abrirPedidoExpress() {
    document.getElementById('modalPedidoExpress').classList.remove('hidden');
    document.body.style.overflow = 'hidden';

    // IMPORTANTE: Cargar precios actualizados desde la base de datos
    await cargarPreciosActualizados();

    irAPaso(1);
}

function cerrarPedidoExpress() {
    document.getElementById('modalPedidoExpress').classList.add('hidden');
    document.body.style.overflow = 'auto';
    resetearTodo();
}

function resetearTodo() {
    pasoActual = 1;
    pedidosAcumulados = [];
    datosCliente = null;
    planchasPorSabor = {};
    historial = [];

    // Resetear formulario
    document.getElementById('formPedidoExpress').reset();
    document.querySelectorAll('.turno-card').forEach(c => c.classList.remove('seleccionado'));
    document.querySelectorAll('.combo-checkbox').forEach(c => c.checked = false);
    document.querySelectorAll('.cantidad-display').forEach(c => c.textContent = '1');
}

function irAPaso(numeroPaso) {
    // Validar antes de avanzar
    if (numeroPaso > pasoActual) {
        if (pasoActual === 1 && !validarPaso1()) return;
    }

    // Ocultar todos los pasos
    document.querySelectorAll('.paso-container').forEach(p => p.classList.add('hidden'));

    // Mostrar el paso solicitado
    if (numeroPaso === 1) {
        document.getElementById('paso1').classList.remove('hidden');
    } else if (numeroPaso === 2) {
        document.getElementById('paso2').classList.remove('hidden');
    } else if (numeroPaso === 4) {
        actualizarResumen();
        document.getElementById('paso4').classList.remove('hidden');
    }

    // Actualizar indicadores
    actualizarIndicadores(numeroPaso);
    pasoActual = numeroPaso;
}

function actualizarIndicadores(paso) {
    for (let i = 1; i <= 4; i++) {
        const indicador = document.getElementById(`indicador-paso-${i}`);
        if (!indicador) continue;

        indicador.classList.remove('activo', 'completado');

        if (i === paso) {
            indicador.classList.add('activo');
        } else if (i < paso) {
            indicador.classList.add('completado');
        }
    }
}

// ============================================
// VALIDACIONES
// ============================================

function validarPaso1() {
    const nombre = document.getElementById('nombre').value.trim();
    const apellido = document.getElementById('apellido').value.trim();
    const ubicacion = document.querySelector('input[name="ubicacion"]:checked');
    const turno = document.querySelector('input[name="turno"]:checked');
    const formaPago = document.querySelector('input[name="forma_pago"]:checked');

    if (!nombre || !apellido) {
        alert('🏃‍♂️ Ingresá nombre y apellido');
        return false;
    }

    if (!ubicacion) {
        alert('📍 Seleccioná la ubicación');
        return false;
    }

    if (!turno) {
        alert('⏰ Seleccioná el turno');
        return false;
    }

    if (!formaPago) {
        alert('💳 Seleccioná la forma de pago');
        return false;
    }

    // Guardar datos del cliente
    datosCliente = {
        nombre: nombre,
        apellido: apellido,
        telefono: document.getElementById('telefono').value.trim(),
        ubicacion: ubicacion.value,
        turno: turno.value,
        formaPago: formaPago.value,
        yaPagado: document.getElementById('yaPagado').checked
    };

    return true;
}

// ============================================
// PASO 1: SELECCIÓN DE DATOS
// ============================================

function seleccionarTurno(turno, elemento) {
    document.querySelectorAll('.turno-card').forEach(c => c.classList.remove('seleccionado'));
    elemento.classList.add('seleccionado');
    elemento.querySelector('input[type="radio"]').checked = true;
}

// ============================================
// PASO 2: TIPO DE PEDIDO
// ============================================

function seleccionarTipoPedido(tipo) {
    if (tipo === 'comun') {
        document.getElementById('paso2').classList.add('hidden');
        document.getElementById('paso3comun').classList.remove('hidden');
        pasoActual = 3;
        actualizarIndicadores(3);
    } else if (tipo === 'personalizado') {
        document.getElementById('paso2').classList.add('hidden');
        document.getElementById('paso3personalizado').classList.remove('hidden');
        pasoActual = 3;
        actualizarIndicadores(3);

        // Generar botones de sabores si no existen
        if (document.getElementById('saboresComunes').children.length === 0) {
            generarBotonesSabores();
        }
    }
}

// ============================================
// PASO 3A: PEDIDOS COMUNES
// ============================================

function cambiarCantidadCombo(boton, cambio) {
    const item = boton.closest('.combo-item');
    const display = item.querySelector('.cantidad-display');
    let cantidad = parseInt(display.textContent);

    cantidad += cambio;
    if (cantidad < 1) cantidad = 1;
    if (cantidad > 99) cantidad = 99;

    display.textContent = cantidad;

    // Marcar checkbox si cantidad > 1
    const checkbox = item.querySelector('.combo-checkbox');
    if (cantidad > 1) {
        checkbox.checked = true;
    }
}

function agregarPedidosComunes() {
    const combosSeleccionados = [];
    const items = document.querySelectorAll('.combo-item');

    items.forEach(item => {
        const checkbox = item.querySelector('.combo-checkbox');
        if (checkbox.checked) {
            const tipo = item.dataset.tipo;
            const cantidad = parseInt(item.querySelector('.cantidad-display').textContent);
            const info = precios[tipo];

            // Crear un pedido por cada cantidad
            for (let i = 0; i < cantidad; i++) {
                combosSeleccionados.push({
                    tipo_pedido: tipo,
                    producto: info.nombre,
                    cantidad: info.cantidad,
                    precio: info.precio,
                    observaciones: document.getElementById('observaciones_comun').value.trim()
                });
            }
        }
    });

    if (combosSeleccionados.length === 0) {
        alert('⚠️ Seleccioná al menos un combo');
        return;
    }

    // Agregar a la lista de pedidos
    pedidosAcumulados.push(...combosSeleccionados);

    // Resetear selección de combos
    items.forEach(item => {
        item.querySelector('.combo-checkbox').checked = false;
        item.querySelector('.cantidad-display').textContent = '1';
    });
    document.getElementById('observaciones_comun').value = '';

    // Ir al resumen
    irAPaso(4);

    alert(`✅ ${combosSeleccionados.length} pedido(s) agregado(s)`);
}

// ============================================
// PASO 3B: PEDIDO PERSONALIZADO
// ============================================

function generarBotonesSabores() {
    const contenedorComunes = document.getElementById('saboresComunes');
    contenedorComunes.innerHTML = saboresComunes.map(sabor => `
        <button type="button" onclick="agregarPlancha('${sabor}')"
                class="sabor-btn p-3 bg-white border-2 border-green-300 rounded-lg text-xs font-medium hover:bg-green-100 transition-all">
            <div class="font-bold">${sabor}</div>
            <div id="count-${sabor.replace(/\s+/g, '-')}" class="text-green-600 font-bold mt-1 text-lg">0</div>
        </button>
    `).join('');

    const contenedorPremium = document.getElementById('saboresPremium');
    contenedorPremium.innerHTML = saboresPremium.map(sabor => `
        <button type="button" onclick="agregarPlancha('${sabor}')"
                class="sabor-btn p-3 bg-white border-2 border-orange-300 rounded-lg text-xs font-medium hover:bg-orange-100 transition-all">
            <div class="font-bold">${sabor}</div>
            <div id="count-${sabor.replace(/\s+/g, '-')}" class="text-orange-600 font-bold mt-1 text-lg">0</div>
        </button>
    `).join('');
}

function agregarPlancha(sabor) {
    historial.push(JSON.parse(JSON.stringify(planchasPorSabor)));
    planchasPorSabor[sabor] = (planchasPorSabor[sabor] || 0) + 1;
    actualizarContadores();
}

function deshacer() {
    if (historial.length > 0) {
        planchasPorSabor = historial.pop();
        actualizarContadores();
    }
}

function actualizarContadores() {
    [...saboresComunes, ...saboresPremium].forEach(sabor => {
        const id = 'count-' + sabor.replace(/\s+/g, '-');
        const elemento = document.getElementById(id);
        if (elemento) {
            elemento.textContent = planchasPorSabor[sabor] || 0;
        }
    });

    const totalPlanchas = Object.values(planchasPorSabor).reduce((a, b) => a + b, 0);
    const totalSandwiches = totalPlanchas * 8;

    const elemPlanchas = document.getElementById('totalPlanchas');
    const elemSandwiches = document.getElementById('totalSandwiches');

    if (elemPlanchas) elemPlanchas.textContent = totalPlanchas;
    if (elemSandwiches) elemSandwiches.textContent = totalSandwiches;
}

function agregarPedidoPersonalizado() {
    const totalPlanchas = Object.values(planchasPorSabor).reduce((sum, val) => sum + val, 0);

    if (totalPlanchas === 0) {
        alert('⚠️ Agregá al menos una plancha');
        return;
    }

    const precio = parseFloat(document.getElementById('precioPersonalizado').value);

    if (!precio || precio <= 0) {
        alert('💰 Ingresá el precio del pedido personalizado');
        return;
    }

    const totalSandwiches = totalPlanchas * 8;

    // Crear detalle de sabores
    let detalleSabores = '\n=== SABORES PERSONALIZADOS ===';
    for (let sabor in planchasPorSabor) {
        const planchas = planchasPorSabor[sabor];
        const sandwiches = planchas * 8;
        detalleSabores += `\n• ${sabor}: ${planchas} plancha${planchas > 1 ? 's' : ''} (${sandwiches} sándwiches)`;
    }

    const observaciones = document.getElementById('observaciones_personalizado').value.trim();

    // Agregar pedido
    pedidosAcumulados.push({
        tipo_pedido: 'personalizado',
        producto: `Personalizado x${totalSandwiches} (${totalPlanchas} plancha${totalPlanchas > 1 ? 's' : ''})`,
        cantidad: totalSandwiches,
        precio: precio,
        sabores_personalizados_json: JSON.stringify(planchasPorSabor),
        observaciones: observaciones + detalleSabores
    });

    // Resetear personalizado
    planchasPorSabor = {};
    historial = [];
    actualizarContadores();
    document.getElementById('precioPersonalizado').value = '';
    document.getElementById('observaciones_personalizado').value = '';

    // Ir al resumen
    irAPaso(4);

    alert('✅ Pedido personalizado agregado');
}

// ============================================
// PASO 4: RESUMEN
// ============================================

function actualizarResumen() {
    if (!datosCliente) return;

    // Actualizar info del cliente
    document.getElementById('resumenCliente').textContent =
        `Cliente: ${datosCliente.nombre} ${datosCliente.apellido}`;
    document.getElementById('resumenUbicacion').textContent =
        `Ubicación: ${datosCliente.ubicacion}`;
    document.getElementById('resumenTurno').textContent =
        `Turno: ${datosCliente.turno}`;
    document.getElementById('resumenPago').textContent =
        `Pago: ${datosCliente.formaPago}`;

    // Actualizar lista de pedidos
    const lista = document.getElementById('listaPedidosResumen');
    lista.innerHTML = '';

    let total = 0;

    pedidosAcumulados.forEach((pedido, index) => {
        total += pedido.precio;

        const div = document.createElement('div');
        div.className = 'bg-white border-2 border-green-300 rounded-lg p-4';
        div.innerHTML = `
            <div class="flex justify-between items-start">
                <div class="flex-1">
                    <div class="font-bold text-lg">${index + 1}. ${pedido.producto}</div>
                    ${pedido.observaciones ? `<div class="text-sm text-gray-600 mt-1">${pedido.observaciones.split('\n')[0]}</div>` : ''}
                </div>
                <div class="text-right">
                    <div class="text-xl font-bold text-green-600">${pedido.precio.toLocaleString()}</div>
                    <button type="button" onclick="eliminarPedidoResumen(${index})"
                            class="text-red-500 hover:text-red-700 text-sm mt-1">
                        <i class="fas fa-trash"></i> Eliminar
                    </button>
                </div>
            </div>
        `;
        lista.appendChild(div);
    });

    // Actualizar total
    document.getElementById('totalFinal').textContent = `$${total.toLocaleString()}`;

    // Mostrar mensaje si no hay pedidos
    if (pedidosAcumulados.length === 0) {
        lista.innerHTML = '<div class="text-center text-gray-500 py-8">No hay pedidos agregados aún</div>';
    }
}

function eliminarPedidoResumen(index) {
    if (confirm('¿Eliminar este pedido?')) {
        pedidosAcumulados.splice(index, 1);
        actualizarResumen();
    }
}

// ============================================
// FINALIZAR Y CREAR PEDIDOS
// ============================================

function finalizarYCrearPedidos() {
    if (pedidosAcumulados.length === 0) {
        alert('⚠️ No hay pedidos para crear');
        return;
    }

    if (!confirm(`¿Crear ${pedidosAcumulados.length} pedido(s) para ${datosCliente.nombre} ${datosCliente.apellido}?`)) {
        return;
    }

    const btn = event.target;
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Creando...';
    btn.disabled = true;

    // Crear promesas para todos los pedidos
    const promesas = pedidosAcumulados.map((item, index) => {
        const pedidoCompleto = {
            nombre: datosCliente.nombre,
            apellido: datosCliente.apellido,
            telefono: datosCliente.telefono,
            modalidad: 'Retiro', // Express siempre es Retiro
            forma_pago: datosCliente.formaPago,
            tipo_pedido: item.tipo_pedido,
            precio: item.precio,
            producto: item.producto,
            cantidad: item.cantidad,
            ubicacion: datosCliente.ubicacion,
            estado: 'Pendiente',
            observaciones: `Turno: ${datosCliente.turno}${datosCliente.yaPagado ? '\n✅ PAGADO (WhatsApp)' : ''}\n${item.observaciones || ''}`
        };

        if (pedidosAcumulados.length > 1) {
            pedidoCompleto.observaciones += `\n🔗 PEDIDO COMBINADO (${index + 1}/${pedidosAcumulados.length})`;
        }

        if (item.sabores_personalizados_json) {
            pedidoCompleto.sabores_personalizados_json = item.sabores_personalizados_json;
        }

        return fetch('procesar_pedido_express.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(pedidoCompleto)
        }).then(r => r.json());
    });

    Promise.all(promesas)
        .then(resultados => {
            if (resultados.every(r => r.success)) {
                const ids = resultados.map(r => `#${r.pedido_id}`).join(', ');
                const total = resultados.reduce((sum, r) => sum + r.data.precio, 0);

                let msg = `✅ ${resultados.length} pedido(s) creado(s)!\n\n`;
                msg += `IDs: ${ids}\n`;
                msg += `Cliente: ${resultados[0].data.cliente}\n\n`;
                resultados.forEach((r, i) => {
                    msg += `${i + 1}. ${r.data.producto} - $${r.data.precio.toLocaleString()}\n`;
                });
                msg += `\nTOTAL: $${total.toLocaleString()}`;

                alert(msg);
                cerrarPedidoExpress();
                location.reload();
            } else {
                alert('❌ Algunos pedidos fallaron. Revisá la consola.');
                console.error('Errores:', resultados.filter(r => !r.success));
            }
        })
        .catch(error => {
            alert('❌ Error de conexión');
            console.error(error);
        })
        .finally(() => {
            btn.innerHTML = originalText;
            btn.disabled = false;
        });
}

// ============================================
// FUNCIONES DEL DASHBOARD
// ============================================

function cambiarVista(vista) {
    document.querySelectorAll('.view-toggle button').forEach(btn => btn.classList.remove('active'));
    event.currentTarget.classList.add('active');

    if (vista === 'cards') {
        document.getElementById('vistaCards').classList.remove('hidden');
        document.getElementById('vistaLista').classList.add('hidden');
    } else {
        document.getElementById('vistaCards').classList.add('hidden');
        document.getElementById('vistaLista').classList.remove('hidden');
    }

    localStorage.setItem('vistaPreferida', vista);
}

window.addEventListener('DOMContentLoaded', () => {
    const vistaGuardada = localStorage.getItem('vistaPreferida');
    if (vistaGuardada === 'lista') {
        document.querySelector('[data-vista="lista"]')?.click();
    }
});

function filtrarEstado(estado) {
    document.querySelectorAll('.filter-tab[data-estado]').forEach(tab => tab.classList.remove('active'));
    document.querySelector(`.filter-tab[data-estado="${estado}"]`)?.classList.add('active');

    const cards = document.querySelectorAll('#vistaCards > [data-estado]');
    const items = document.querySelectorAll('#vistaLista > [data-estado]');

    cards.forEach(pedido => {
        pedido.style.display = (estado === 'todos' || pedido.dataset.estado === estado) ? '' : 'none';
    });

    items.forEach(pedido => {
        pedido.style.display = (estado === 'todos' || pedido.dataset.estado === estado) ? '' : 'none';
    });
}

function cambiarEstado(pedidoId, nuevoEstado) {
    if (confirm(`¿Cambiar a "${nuevoEstado}"?`)) {
        const formData = new FormData();
        formData.append('accion', 'cambiar_estado');
        formData.append('pedido_id', pedidoId);
        formData.append('nuevo_estado', nuevoEstado);

        fetch('dashboard.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) location.reload();
            else alert('Error al cambiar estado');
        });
    }
}

function eliminarPedido(pedidoId) {
    if (confirm('⚠️ ¿ELIMINAR este pedido permanentemente?\n\nEsta acción NO se puede deshacer.')) {
        const formData = new FormData();
        formData.append('accion', 'eliminar_pedido');
        formData.append('pedido_id', pedidoId);

        fetch('dashboard.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('✅ Pedido eliminado correctamente');
                location.reload();
            } else {
                alert('❌ Error al eliminar pedido');
            }
        })
        .catch(error => {
            alert('❌ Error de conexión');
            console.error(error);
        });
    }
}

function imprimir(pedidoId) {
    const url = `../../../empleados/comanda_simple.php?pedido=${pedidoId}`;
    const ventana = window.open(url, '_blank', 'width=400,height=650,scrollbars=yes');

    if (!ventana) {
        alert('❌ Permitir ventanas emergentes');
        return false;
    }

    ventana.focus();
    setTimeout(() => marcarImpreso(pedidoId), 2000);
    return true;
}

function reimprimirEmergencia(pedidoId) {
    if (!confirm('⚠️ RE-IMPRIMIR PEDIDO\n\nEsta función es solo para emergencias.\n¿Confirmar re-impresión?')) {
        return;
    }

    const url = `../../../empleados/comanda_simple.php?pedido=${pedidoId}`;
    const ventana = window.open(url, '_blank', 'width=400,height=650,scrollbars=yes');

    if (!ventana) {
        alert('❌ Permitir ventanas emergentes');
        return false;
    }

    ventana.focus();
    return true;
}

function marcarImpreso(pedidoId) {
    const formData = new FormData();
    formData.append('accion', 'marcar_impreso');
    formData.append('pedido_id', pedidoId);

    fetch('dashboard.php', {
            method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            setTimeout(() => location.reload(), 1000);
        }
    });
}

function updateClock() {
    const now = new Date();
    const h = String(now.getHours()).padStart(2, '0');
    const m = String(now.getMinutes()).padStart(2, '0');
    const s = String(now.getSeconds()).padStart(2, '0');
    const elem = document.getElementById('clock');
    if (elem) elem.textContent = `${h}:${m}:${s}`;
}
setInterval(updateClock, 1000);
updateClock();

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && !document.getElementById('modalPedidoExpress').classList.contains('hidden')) {
        cerrarPedidoExpress();
    }
});

console.log('🚀 Dashboard Admin con Sistema de Pasos cargado');

// ============================================
// EDITAR PEDIDO
// ============================================

let pedidoEditando = null;

async function abrirEditarPedido(pedidoId) {
    try {
        // Obtener datos del pedido
        const formData = new FormData();
        formData.append('accion', 'obtener_pedido');
        formData.append('pedido_id', pedidoId);

        const response = await fetch('dashboard.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            pedidoEditando = data.pedido;

            // Llenar el formulario
            document.getElementById('editPedidoId').textContent = pedidoEditando.id;
            document.getElementById('editProducto').value = pedidoEditando.producto || '';
            document.getElementById('editCantidad').value = pedidoEditando.cantidad || '';
            document.getElementById('editPrecio').value = pedidoEditando.precio || '';
            document.getElementById('editObservaciones').value = pedidoEditando.observaciones || '';

            // Si tiene sabores personalizados, mostrar la sección de edición
            if (pedidoEditando.sabores_personalizados_json) {
                const sabores = JSON.parse(pedidoEditando.sabores_personalizados_json);
                document.getElementById('editSaboresContainer').classList.remove('hidden');

                const saboresList = document.getElementById('editSaboresList');
                saboresList.innerHTML = '';

                for (let sabor in sabores) {
                    const div = document.createElement('div');
                    div.className = 'flex items-center gap-3';
                    div.innerHTML = `
                        <label class="flex-1 text-sm font-medium text-gray-700">${sabor}</label>
                        <input type="number" min="0" value="${sabores[sabor]}"
                               data-sabor="${sabor}"
                               class="edit-sabor-input w-20 px-3 py-2 border-2 border-purple-300 rounded focus:border-purple-500"
                               placeholder="0">
                        <span class="text-xs text-gray-500">planchas</span>
                    `;
                    saboresList.appendChild(div);
                }
            } else {
                document.getElementById('editSaboresContainer').classList.add('hidden');
            }

            // Mostrar modal
            document.getElementById('modalEditarPedido').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        } else {
            alert('❌ Error al cargar el pedido');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('❌ Error de conexión');
    }
}

function cerrarEditarPedido() {
    document.getElementById('modalEditarPedido').classList.add('hidden');
    document.body.style.overflow = 'auto';
    pedidoEditando = null;
}

async function guardarEdicionPedido() {
    if (!pedidoEditando) return;

    const producto = document.getElementById('editProducto').value.trim();
    const cantidad = parseInt(document.getElementById('editCantidad').value);
    const precio = parseFloat(document.getElementById('editPrecio').value);
    const observaciones = document.getElementById('editObservaciones').value.trim();

    if (!producto || !cantidad || !precio) {
        alert('⚠️ Completá todos los campos requeridos');
        return;
    }

    // Si hay sabores personalizados, recopilarlos
    let saboresPersonalizados = null;
    if (!document.getElementById('editSaboresContainer').classList.contains('hidden')) {
        const saboresInputs = document.querySelectorAll('.edit-sabor-input');
        saboresPersonalizados = {};

        saboresInputs.forEach(input => {
            const sabor = input.dataset.sabor;
            const planchas = parseInt(input.value) || 0;
            if (planchas > 0) {
                saboresPersonalizados[sabor] = planchas;
            }
        });

        // Actualizar el producto con el nuevo total
        const totalPlanchas = Object.values(saboresPersonalizados).reduce((a, b) => a + b, 0);
        const totalSandwiches = totalPlanchas * 8;

        // Si el producto es personalizado, actualizarlo con los nuevos totales
        if (pedidoEditando.producto.includes('Personalizado')) {
            document.getElementById('editProducto').value = `Personalizado x${totalSandwiches} (${totalPlanchas} plancha${totalPlanchas !== 1 ? 's' : ''})`;
            document.getElementById('editCantidad').value = totalSandwiches;
        }
    }

    if (!confirm('¿Guardar los cambios en este pedido?')) {
        return;
    }

    try {
        const formData = new FormData();
        formData.append('accion', 'editar_pedido');
        formData.append('pedido_id', pedidoEditando.id);
        formData.append('producto', document.getElementById('editProducto').value.trim());
        formData.append('cantidad', document.getElementById('editCantidad').value);
        formData.append('precio', document.getElementById('editPrecio').value);
        formData.append('observaciones', observaciones);

        if (saboresPersonalizados && Object.keys(saboresPersonalizados).length > 0) {
            formData.append('sabores_personalizados_json', JSON.stringify(saboresPersonalizados));
        }

        const response = await fetch('dashboard.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            alert('✅ Pedido actualizado correctamente');
            cerrarEditarPedido();
            location.reload();
        } else {
            alert('❌ Error al actualizar el pedido');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('❌ Error de conexión');
    }
}

    </script>

</body>
</html>
