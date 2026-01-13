<?php
// admin/modules/pedidos/vista_ejecutiva.php
require_once '../../config.php';
requireLogin();

$pdo = getConnection();

// Procesar acciones masivas
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion_masiva'])) {
    $accion = $_POST['accion_masiva'];
    $pedidos_seleccionados = $_POST['pedidos'] ?? [];
    
    if (!empty($pedidos_seleccionados)) {
        try {
            switch ($accion) {
                case 'eliminar':
                    $placeholders = str_repeat('?,', count($pedidos_seleccionados) - 1) . '?';
                    $stmt = $pdo->prepare("DELETE FROM pedidos WHERE id IN ($placeholders)");
                    $stmt->execute($pedidos_seleccionados);
                    $_SESSION['mensaje'] = count($pedidos_seleccionados) . " pedido(s) eliminado(s)";
                    break;
                    
                case 'cambiar_estado':
                    $nuevo_estado = $_POST['nuevo_estado'] ?? '';
                    if ($nuevo_estado) {
                        $placeholders = str_repeat('?,', count($pedidos_seleccionados) - 1) . '?';
                        $stmt = $pdo->prepare("UPDATE pedidos SET estado = ? WHERE id IN ($placeholders)");
                        $stmt->execute(array_merge([$nuevo_estado], $pedidos_seleccionados));
                        $_SESSION['mensaje'] = count($pedidos_seleccionados) . " pedido(s) actualizado(s) a '$nuevo_estado'";
                    }
                    break;
                    
                case 'marcar_impreso':
                    $placeholders = str_repeat('?,', count($pedidos_seleccionados) - 1) . '?';
                    $stmt = $pdo->prepare("UPDATE pedidos SET impreso = 1 WHERE id IN ($placeholders)");
                    $stmt->execute($pedidos_seleccionados);
                    $_SESSION['mensaje'] = count($pedidos_seleccionados) . " pedido(s) marcado(s) como impreso";
                    break;
            }
            
            header('Location: ' . $_SERVER['PHP_SELF'] . '?' . $_SERVER['QUERY_STRING']);
            exit;
            
        } catch (Exception $e) {
            $_SESSION['error'] = "Error: " . $e->getMessage();
        }
    }
}

// Filtros simples
$filtro_estado = $_GET['estado'] ?? '';
$filtro_ubicacion = $_GET['ubicacion'] ?? '';
$buscar = $_GET['buscar'] ?? '';
$orden = $_GET['orden'] ?? 'created_at DESC';

// Fecha por defecto: hoy
$fecha_desde = $_GET['fecha_desde'] ?? date('Y-m-d');
$fecha_hasta = $_GET['fecha_hasta'] ?? date('Y-m-d');

// Construir SQL
$sql = "SELECT p.*, 
        CONCAT(COALESCE(p.nombre, ''), ' ', COALESCE(p.apellido, '')) as cliente,
        TIMESTAMPDIFF(MINUTE, p.created_at, NOW()) as minutos_transcurridos
        FROM pedidos p 
        WHERE 1=1";

$params = [];

if ($fecha_desde) {
    $sql .= " AND DATE(p.created_at) >= ?";
    $params[] = $fecha_desde;
}

if ($fecha_hasta) {
    $sql .= " AND DATE(p.created_at) <= ?";
    $params[] = $fecha_hasta;
}

if ($filtro_estado) {
    $sql .= " AND p.estado = ?";
    $params[] = $filtro_estado;
}

if ($filtro_ubicacion) {
    $sql .= " AND p.ubicacion = ?";
    $params[] = $filtro_ubicacion;
}

if ($buscar) {
    $sql .= " AND (p.nombre LIKE ? OR p.apellido LIKE ? OR p.telefono LIKE ? OR p.producto LIKE ?)";
    $params[] = "%$buscar%";
    $params[] = "%$buscar%";
    $params[] = "%$buscar%";
    $params[] = "%$buscar%";
}

$sql .= " ORDER BY " . $orden;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$pedidos = $stmt->fetchAll();

$mensaje = $_SESSION['mensaje'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['mensaje'], $_SESSION['error']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vista Ejecutiva - Pedidos</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: #f0f0f0;
            font-size: 13px;
        }
        
        .header {
            background: linear-gradient(to bottom, #f5f5f5, #e0e0e0);
            border-bottom: 1px solid #999;
            padding: 8px 12px;
        }
        
        .header h1 {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }
        
        .toolbar {
            background: #fafafa;
            border: 1px solid #ccc;
            border-radius: 3px;
            padding: 6px;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .btn {
            background: linear-gradient(to bottom, #fff, #f0f0f0);
            border: 1px solid #adadad;
            padding: 5px 12px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 12px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            text-decoration: none;
            color: #333;
        }
        
        .btn:hover {
            background: linear-gradient(to bottom, #f8f8f8, #e8e8e8);
            border-color: #888;
        }
        
        .btn:active {
            background: #e8e8e8;
            box-shadow: inset 0 1px 2px rgba(0,0,0,0.1);
        }
        
        .btn-delete { color: #c00; }
        .btn-print { color: #00c; }
        .btn-edit { color: #060; }
        
        .separator {
            width: 1px;
            height: 20px;
            background: #ccc;
            margin: 0 4px;
        }
        
        select, input[type="text"], input[type="date"] {
            padding: 4px 6px;
            border: 1px solid #ababab;
            border-radius: 2px;
            font-size: 12px;
            background: white;
        }
        
        .tabla-container {
            margin: 12px;
            background: white;
            border: 1px solid #ccc;
            border-radius: 3px;
            overflow: auto;
            max-height: calc(100vh - 180px);
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        thead {
            background: linear-gradient(to bottom, #f9f9f9, #e8e8e8);
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        th {
            padding: 6px 10px;
            text-align: left;
            font-weight: 600;
            border-right: 1px solid #d9d9d9;
            border-bottom: 1px solid #999;
            font-size: 12px;
            color: #333;
            user-select: none;
        }
        
        td {
            padding: 4px 10px;
            border-right: 1px solid #e8e8e8;
            border-bottom: 1px solid #e8e8e8;
            font-size: 12px;
        }
        
        tr:hover {
            background: #f0f8ff;
        }
        
        tr.selected {
            background: #d4e5f7;
        }
        
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .badge-pendiente { background: #fff3cd; color: #856404; }
        .badge-preparando { background: #cfe2ff; color: #084298; }
        .badge-listo { background: #d1e7dd; color: #0f5132; }
        .badge-entregado { background: #d1ecf1; color: #055160; }
        
        .urgente {
            background: #ffe6e6 !important;
        }
        
        .mensaje {
            margin: 12px;
            padding: 10px;
            border-radius: 3px;
            border: 1px solid;
        }
        
        .mensaje-success {
            background: #d4edda;
            border-color: #c3e6cb;
            color: #155724;
        }
        
        .mensaje-error {
            background: #f8d7da;
            border-color: #f5c6cb;
            color: #721c24;
        }
        
        input[type="checkbox"] {
            cursor: pointer;
        }
        
        .info-bar {
            background: #fff;
            border-bottom: 1px solid #ccc;
            padding: 6px 12px;
            font-size: 12px;
            color: #666;
            display: flex;
            justify-content: space-between;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>📋 Vista Ejecutiva - Gestión de Pedidos</h1>
    </div>
    
    <?php if ($mensaje): ?>
        <div class="mensaje mensaje-success"><?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="mensaje mensaje-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <div class="info-bar">
        <span><strong><?= count($pedidos) ?></strong> pedidos encontrados</span>
        <span>
            <a href="../../index.php" style="color: #00c; text-decoration: none;">← Volver al Admin</a>
        </span>
    </div>
    
    <form method="GET" id="filtroForm">
        <div class="toolbar" style="margin: 12px;">
            <input type="text" name="buscar" value="<?= htmlspecialchars($buscar) ?>" 
                   placeholder="🔍 Buscar..." style="width: 180px;">
            
            <select name="estado" onchange="document.getElementById('filtroForm').submit()">
                <option value="">Todos los estados</option>
                <option value="Pendiente" <?= $filtro_estado === 'Pendiente' ? 'selected' : '' ?>>⏳ Pendiente</option>
                <option value="Preparando" <?= $filtro_estado === 'Preparando' ? 'selected' : '' ?>>👨‍🍳 Preparando</option>
                <option value="Listo" <?= $filtro_estado === 'Listo' ? 'selected' : '' ?>>✅ Listo</option>
                <option value="Entregado" <?= $filtro_estado === 'Entregado' ? 'selected' : '' ?>>📦 Entregado</option>
            </select>
            
            <select name="ubicacion" onchange="document.getElementById('filtroForm').submit()">
                <option value="">Todas ubicaciones</option>
                <option value="Local 1" <?= $filtro_ubicacion === 'Local 1' ? 'selected' : '' ?>>🏪 Local 1</option>
                <option value="Fábrica" <?= $filtro_ubicacion === 'Fábrica' ? 'selected' : '' ?>>🏭 Fábrica</option>
            </select>
            
            <input type="date" name="fecha_desde" value="<?= htmlspecialchars($fecha_desde) ?>" 
                   onchange="document.getElementById('filtroForm').submit()">
            
            <input type="date" name="fecha_hasta" value="<?= htmlspecialchars($fecha_hasta) ?>" 
                   onchange="document.getElementById('filtroForm').submit()">
            
            <button type="submit" class="btn">Filtrar</button>
            <a href="vista_ejecutiva.php" class="btn">Limpiar</a>
            
            <div class="separator"></div>
            
            <button type="button" class="btn" onclick="seleccionarTodos()">☑ Todos</button>
            <button type="button" class="btn" onclick="deseleccionarTodos()">☐ Ninguno</button>
        </div>
    </form>
    
    <form method="POST" id="accionForm">
        <div class="toolbar" style="margin: 0 12px 12px 12px;">
            <span style="font-weight: 600;">Acciones con seleccionados:</span>
            
            <button type="button" class="btn btn-delete" onclick="eliminarSeleccionados()">
                🗑️ Eliminar
            </button>
            
            <button type="button" class="btn btn-print" onclick="imprimirAutomatico()">
                🖨️ Auto
            </button>
            
            <button type="button" class="btn" onclick="imprimirManual()" style="background: linear-gradient(to bottom, #e3f2fd, #bbdefb);">
                👁️ Manual
            </button>
            
            <div class="separator"></div>
            
            <span>Cambiar estado a:</span>
            <select id="nuevo_estado" name="nuevo_estado">
                <option value="">Seleccionar...</option>
                <option value="Pendiente">⏳ Pendiente</option>
                <option value="Preparando">👨‍🍳 Preparando</option>
                <option value="Listo">✅ Listo</option>
                <option value="Entregado">📦 Entregado</option>
            </select>
            <button type="button" class="btn btn-edit" onclick="cambiarEstadoSeleccionados()">
                ✏️ Aplicar
            </button>
        </div>
        
        <input type="hidden" name="accion_masiva" id="accion_masiva">
        
        <div class="tabla-container">
            <table>
                <thead>
                    <tr>
                        <th style="width: 30px;">
                            <input type="checkbox" id="checkTodos" onclick="toggleTodos(this)">
                        </th>
                        <th style="width: 50px;">ID</th>
                        <th style="width: 150px;">Cliente</th>
                        <th style="width: 120px;">Teléfono</th>
                        <th style="width: 200px;">Producto</th>
                        <th style="width: 80px;">Precio</th>
                        <th style="width: 100px;">Estado</th>
                        <th style="width: 100px;">Modalidad</th>
                        <th style="width: 100px;">Ubicación</th>
                        <th style="width: 120px;">Fecha Pedido</th>
                        <th style="width: 120px;">Fecha Entrega</th>
                        <th style="width: 80px;">Tiempo</th>
                        <th style="width: 60px;">Impreso</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($pedidos)): ?>
                        <tr>
                            <td colspan="12" style="text-align: center; padding: 40px; color: #999;">
                                No hay pedidos para mostrar con los filtros seleccionados
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($pedidos as $pedido): ?>
                            <tr class="<?= $pedido['minutos_transcurridos'] > 60 ? 'urgente' : '' ?>" 
                                onclick="toggleFila(this, event)"
                                title="<?= $pedido['notas_horario'] ? 'Notas: ' . htmlspecialchars($pedido['notas_horario']) : '' ?>">
                                <td>
                                    <input type="checkbox" 
                                           name="pedidos[]" 
                                           value="<?= $pedido['id'] ?>" 
                                           class="check-pedido"
                                           onclick="event.stopPropagation();">
                                </td>
                                <td><strong>#<?= $pedido['id'] ?></strong></td>
                                <td><?= htmlspecialchars($pedido['cliente'] ?: 'Sin nombre') ?></td>
                                <td><?= htmlspecialchars($pedido['telefono'] ?: '-') ?></td>
                                <td><?= htmlspecialchars($pedido['producto']) ?></td>
                                <td><strong>$<?= number_format($pedido['precio'], 0, ',', '.') ?></strong></td>
                                <td>
                                    <span class="badge badge-<?= strtolower($pedido['estado']) ?>">
                                        <?= htmlspecialchars($pedido['estado']) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($pedido['modalidad']) ?></td>
                                <td><?= htmlspecialchars($pedido['ubicacion']) ?></td>
                                <td><?= $pedido['fecha_display'] ?? formatDateTime($pedido['created_at'], 'd/m/Y H:i') ?></td>
                                <td>
                                    <?php if ($pedido['fecha_entrega']): ?>
                                        <strong><?= date('d/m/Y', strtotime($pedido['fecha_entrega'])) ?></strong>
                                        <?php if ($pedido['hora_entrega']): ?>
                                            <br><small style="color: #666;"><?= substr($pedido['hora_entrega'], 0, 5) ?>hs</small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="color: #999;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                    $mins = $pedido['minutos_transcurridos'];
                                    $color = $mins > 60 ? 'red' : ($mins > 30 ? 'orange' : 'green');
                                    ?>
                                    <span style="color: <?= $color ?>; font-weight: 600;">
                                        <?= $mins ?> min
                                    </span>
                                </td>
                                <td style="text-align: center;">
                                    <?= $pedido['impreso'] ? '✅' : '⏳' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </form>

    <script>
        // Funciones de selección
        function toggleTodos(checkbox) {
            const checks = document.querySelectorAll('.check-pedido');
            checks.forEach(check => {
                check.checked = checkbox.checked;
                const fila = check.closest('tr');
                fila.classList.toggle('selected', checkbox.checked);
            });
        }
        
        function toggleFila(fila, event) {
            if (event.target.type === 'checkbox') return;
            const checkbox = fila.querySelector('.check-pedido');
            checkbox.checked = !checkbox.checked;
            fila.classList.toggle('selected', checkbox.checked);
        }
        
        function seleccionarTodos() {
            document.getElementById('checkTodos').checked = true;
            toggleTodos(document.getElementById('checkTodos'));
        }
        
        function deseleccionarTodos() {
            document.getElementById('checkTodos').checked = false;
            toggleTodos(document.getElementById('checkTodos'));
        }
        
        function getSeleccionados() {
            const checks = document.querySelectorAll('.check-pedido:checked');
            return Array.from(checks).map(c => c.value);
        }
        
        // Funciones de acciones masivas
        function eliminarSeleccionados() {
            const seleccionados = getSeleccionados();
            if (seleccionados.length === 0) {
                alert('⚠️ Selecciona al menos un pedido');
                return;
            }
            if (confirm(`¿Eliminar ${seleccionados.length} pedido(s)?\n\nEsta acción no se puede deshacer.`)) {
                document.getElementById('accion_masiva').value = 'eliminar';
                document.getElementById('accionForm').submit();
            }
        }
        
        function cambiarEstadoSeleccionados() {
            const seleccionados = getSeleccionados();
            const nuevoEstado = document.getElementById('nuevo_estado').value;
            
            if (seleccionados.length === 0) {
                alert('⚠️ Selecciona al menos un pedido');
                return;
            }
            if (!nuevoEstado) {
                alert('⚠️ Selecciona un estado');
                return;
            }
            if (confirm(`¿Cambiar ${seleccionados.length} pedido(s) a "${nuevoEstado}"?`)) {
                document.getElementById('accion_masiva').value = 'cambiar_estado';
                document.getElementById('accionForm').submit();
            }
        }
        
        // Funciones de impresión
        function imprimirAutomatico() {
            const seleccionados = getSeleccionados();
            if (seleccionados.length === 0) {
                alert('⚠️ Selecciona al menos un pedido');
                return;
            }
            
            const pedidosStr = seleccionados.join(',');
            const url = 'impresion_automatica.php?pedidos=' + pedidosStr;
            const ventana = window.open(url, 'Impresion_Auto', 'width=450,height=400');
            
            if (!ventana) {
                alert('❌ Error: Permite ventanas emergentes en tu navegador');
            }
        }
        
        function imprimirManual() {
            const seleccionados = getSeleccionados();
            if (seleccionados.length === 0) {
                alert('⚠️ Selecciona al menos un pedido');
                return;
            }
            
            const pedidosStr = seleccionados.join(',');
            const url = 'revision_manual.php?pedidos=' + pedidosStr;
            const ventana = window.open(url, 'Revision_Manual', 'width=500,height=700,scrollbars=yes');
            
            if (!ventana) {
                alert('❌ Error: Permite ventanas emergentes en tu navegador');
            }
        }
    </script>
</body>
</html>