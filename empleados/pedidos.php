<?php
// Habilitar errores para debug
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../config.php';
session_start();

// Verificar acceso de empleado
if (!isset($_SESSION['empleado_logged']) || $_SESSION['empleado_logged'] !== true) {
    header('Location: login.php');
    exit;
}

$pdo = getConnection();

// Funciones auxiliares (por si no están en config.php)
if (!function_exists('sanitize')) {
    function sanitize($input) {
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('formatPrice')) {
    function formatPrice($precio) {
        return '$' . number_format($precio, 0, ',', '.');
    }
}

// Manejar acciones
$mensaje = '';
$error = '';

if ($_POST) {
    if (isset($_POST['accion']) && $_POST['accion'] === 'cambiar_estado') {
        $id = (int)$_POST['id'];
        $estado = $_POST['estado'];
        $estados_validos = ['Pendiente', 'Preparando', 'Listo', 'Entregado'];
        
        if (in_array($estado, $estados_validos)) {
            try {
                $stmt = $pdo->prepare("UPDATE pedidos SET estado = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$estado, $id]);
                $mensaje = 'Estado actualizado correctamente';
            } catch (Exception $e) {
                $error = 'Error al actualizar estado: ' . $e->getMessage();
            }
        } else {
            $error = 'Estado no válido';
        }
    }
    
    // Acción para marcar como impreso
    if (isset($_POST['accion']) && $_POST['accion'] === 'marcar_impreso') {
        $id = (int)$_POST['id'];
        try {
            $stmt = $pdo->prepare("UPDATE pedidos SET impreso = 1, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$id]);
            $mensaje = 'Comanda marcada como impresa';
        } catch (Exception $e) {
            $error = 'Error al marcar como impreso: ' . $e->getMessage();
        }
    }
}

// Filtros
$filtro_estado = isset($_GET['estado']) ? sanitize($_GET['estado']) : '';
$filtro_fecha_desde = isset($_GET['fecha_desde']) ? sanitize($_GET['fecha_desde']) : '';
$filtro_fecha_hasta = isset($_GET['fecha_hasta']) ? sanitize($_GET['fecha_hasta']) : '';
$buscar = isset($_GET['buscar']) ? sanitize($_GET['buscar']) : '';

// Para empleados: mostrar solo hoy por defecto
if (empty($filtro_fecha_desde) && empty($filtro_fecha_hasta) && empty($buscar) && empty($filtro_estado)) {
    $filtro_fecha_desde = date('Y-m-d');
    $filtro_fecha_hasta = date('Y-m-d');
}

// Construir consulta - Solo Local 1 para empleados
$sql = "SELECT p.*, cf.nombre as cliente_nombre, cf.apellido as cliente_apellido 
        FROM pedidos p 
        LEFT JOIN clientes_fijos cf ON p.cliente_fijo_id = cf.id 
        WHERE p.ubicacion = 'Local 1'";
$params = [];

// Aplicar filtros
if ($filtro_fecha_desde && $filtro_fecha_hasta) {
    $sql .= " AND DATE(p.created_at) BETWEEN ? AND ?";
    $params[] = $filtro_fecha_desde;
    $params[] = $filtro_fecha_hasta;
} elseif ($filtro_fecha_desde) {
    $sql .= " AND DATE(p.created_at) >= ?";
    $params[] = $filtro_fecha_desde;
} elseif ($filtro_fecha_hasta) {
    $sql .= " AND DATE(p.created_at) <= ?";
    $params[] = $filtro_fecha_hasta;
}

if ($filtro_estado) {
    $sql .= " AND p.estado = ?";
    $params[] = $filtro_estado;
}

if ($buscar) {
    $sql .= " AND (p.nombre LIKE ? OR p.apellido LIKE ? OR p.telefono LIKE ? OR p.producto LIKE ?)";
    $params[] = "%$buscar%";
    $params[] = "%$buscar%";
    $params[] = "%$buscar%";
    $params[] = "%$buscar%";
}

$sql .= " ORDER BY p.created_at DESC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $pedidos = $stmt->fetchAll();
} catch (Exception $e) {
    $error = 'Error al cargar pedidos: ' . $e->getMessage();
    $pedidos = [];
}

// Calcular tiempo transcurrido
foreach ($pedidos as &$pedido) {
    $tiempo_creacion = new DateTime($pedido['created_at']);
    $tiempo_actual = new DateTime();
    $diferencia = $tiempo_actual->diff($tiempo_creacion);
    $pedido['minutos_transcurridos'] = ($diferencia->h * 60) + $diferencia->i;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Santa Catalina - Panel Empleados - Local 1</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @keyframes pulse-warning {
            0%, 100% { background-color: #fef3c7; }
            50% { background-color: #fde68a; }
        }
        .urgente-warning {
            animation: pulse-warning 2s infinite;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Header -->
    <header class="bg-blue-600 text-white p-4">
        <div class="container mx-auto flex justify-between items-center">
            <div class="flex items-center space-x-4">
                <h1 class="text-xl font-bold">
                    <i class="fas fa-store mr-2"></i>Santa Catalina - Local 1
                </h1>
                <span class="bg-blue-500 px-3 py-1 rounded-full text-sm">Panel Empleados</span>
            </div>
            <div class="flex items-center space-x-4">
                <a href="dashboard.php" class="bg-blue-500 hover:bg-blue-400 px-3 py-2 rounded text-sm">
                    <i class="fas fa-tachometer-alt mr-1"></i>Dashboard
                </a>
                <a href="logout.php" class="bg-red-500 hover:bg-red-400 px-3 py-2 rounded text-sm">
                    <i class="fas fa-sign-out-alt mr-1"></i>Salir
                </a>
            </div>
        </div>
    </header>

    <main class="container mx-auto px-4 py-6">
        <!-- Mensajes -->
        <?php if ($mensaje): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <i class="fas fa-check-circle mr-2"></i><?= htmlspecialchars($mensaje) ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <i class="fas fa-exclamation-triangle mr-2"></i><?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- Filtros -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-lg font-semibold mb-4">
                <i class="fas fa-filter mr-2 text-blue-500"></i>Filtros - Local 1
            </h2>
            
            <form method="GET" class="space-y-4">
                <!-- Botones rápidos -->
                <div class="flex flex-wrap gap-2 mb-4">
                    <button type="button" onclick="setFiltroRapido('hoy')" 
                            class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-2 rounded text-sm">
                        <i class="fas fa-calendar-day mr-1"></i>Hoy
                    </button>
                    <button type="button" onclick="setFiltroRapido('ayer')" 
                            class="bg-gray-500 hover:bg-gray-600 text-white px-3 py-2 rounded text-sm">
                        <i class="fas fa-calendar-minus mr-1"></i>Ayer
                    </button>
                    <button type="button" onclick="setFiltroRapido('semana')" 
                            class="bg-green-500 hover:bg-green-600 text-white px-3 py-2 rounded text-sm">
                        <i class="fas fa-calendar-week mr-1"></i>Esta Semana
                    </button>
                    <button type="button" onclick="limpiarFiltros()" 
                            class="bg-orange-500 hover:bg-orange-600 text-white px-3 py-2 rounded text-sm">
                        <i class="fas fa-eraser mr-1"></i>Limpiar
                    </button>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">Desde:</label>
                        <input type="date" name="fecha_desde" value="<?= htmlspecialchars($filtro_fecha_desde) ?>" 
                               class="w-full border rounded px-3 py-2">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Hasta:</label>
                        <input type="date" name="fecha_hasta" value="<?= htmlspecialchars($filtro_fecha_hasta) ?>" 
                               class="w-full border rounded px-3 py-2">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Estado:</label>
                        <select name="estado" class="w-full border rounded px-3 py-2">
                            <option value="">Todos los estados</option>
                            <option value="Pendiente" <?= $filtro_estado === 'Pendiente' ? 'selected' : '' ?>>Pendiente</option>
                            <option value="Preparando" <?= $filtro_estado === 'Preparando' ? 'selected' : '' ?>>Preparando</option>
                            <option value="Listo" <?= $filtro_estado === 'Listo' ? 'selected' : '' ?>>Listo</option>
                            <option value="Entregado" <?= $filtro_estado === 'Entregado' ? 'selected' : '' ?>>Entregado</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Buscar:</label>
                        <input type="text" name="buscar" value="<?= htmlspecialchars($buscar) ?>" 
                               placeholder="Nombre, teléfono, producto..." 
                               class="w-full border rounded px-3 py-2">
                    </div>
                </div>
                
                <div class="flex gap-2">
                    <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded">
                        <i class="fas fa-search mr-2"></i>Filtrar
                    </button>
                    <a href="?" class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded">
                        <i class="fas fa-times mr-2"></i>Limpiar filtros
                    </a>
                </div>
            </form>
        </div>

        <!-- Lista de pedidos -->
        <div class="bg-white rounded-lg shadow">
            <div class="px-6 py-4 border-b">
                <h2 class="text-xl font-semibold">
                    <i class="fas fa-list mr-2 text-green-500"></i>
                    Pedidos Local 1 
                    (<?= count($pedidos) ?> pedidos)
                </h2>
            </div>

            <?php if (empty($pedidos)): ?>
                <div class="p-6 text-center text-gray-500">
                    <i class="fas fa-inbox text-4xl mb-4"></i>
                    <p class="text-lg">No hay pedidos que mostrar</p>
                    <p class="text-sm">Intenta ajustar los filtros</p>
                </div>
            <?php else: ?>
                <div class="space-y-3 p-4">
                    <?php foreach ($pedidos as $pedido): ?>
                        <div class="border rounded-lg p-4 hover:bg-gray-50 
                                    <?= $pedido['minutos_transcurridos'] > 60 ? 'border-red-300 urgente-warning' : '' ?>
                                    <?= $pedido['minutos_transcurridos'] > 30 && $pedido['minutos_transcurridos'] <= 60 ? 'border-yellow-300 bg-yellow-50' : '' ?>">
                            
                            <div class="flex justify-between items-start">
                                <div class="flex-1">
                                    <!-- Encabezado -->
                                    <div class="flex items-center justify-between mb-2">
                                        <h3 class="font-bold text-lg text-gray-800">
                                            <i class="fas fa-hashtag text-blue-500 mr-1"></i>
                                            Pedido #<?= $pedido['id'] ?>
                                        </h3>
                                        
                                        <!-- Cambiar estado -->
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="accion" value="cambiar_estado">
                                            <input type="hidden" name="id" value="<?= $pedido['id'] ?>">
                                            <select name="estado" onchange="this.form.submit()" 
                                                    class="border rounded px-2 py-1 text-sm">
                                                <option value="Pendiente" <?= $pedido['estado'] === 'Pendiente' ? 'selected' : '' ?>>Pendiente</option>
                                                <option value="Preparando" <?= $pedido['estado'] === 'Preparando' ? 'selected' : '' ?>>Preparando</option>
                                                <option value="Listo" <?= $pedido['estado'] === 'Listo' ? 'selected' : '' ?>>Listo</option>
                                                <option value="Entregado" <?= $pedido['estado'] === 'Entregado' ? 'selected' : '' ?>>Entregado</option>
                                            </select>
                                        </form>
                                    </div>

                                    <!-- Cliente -->
                                    <div class="mb-2">
                                        <span class="font-medium">
                                            <i class="fas fa-user mr-1 text-gray-500"></i>
                                            <?= $pedido['cliente_nombre'] ? 
                                                htmlspecialchars($pedido['cliente_nombre'] . ' ' . $pedido['cliente_apellido']) . ' (Cliente Fijo)' : 
                                                htmlspecialchars($pedido['nombre'] . ' ' . $pedido['apellido']) 
                                            ?>
                                        </span>
                                        <span class="text-gray-600 ml-3">
                                            <i class="fas fa-phone mr-1"></i>
                                            <?= htmlspecialchars($pedido['telefono']) ?>
                                        </span>
                                    </div>

                                    <!-- Producto y precio -->
                                    <div class="mb-2">
                                        <div class="flex items-center justify-between">
                                            <span class="font-medium text-green-700">
                                                <i class="fas fa-sandwich mr-1"></i>
                                                <?= htmlspecialchars($pedido['producto']) ?>
                                            </span>
                                            <span class="text-xl font-bold text-green-600">
                                                <?= formatPrice($pedido['precio']) ?>
                                            </span>
                                        </div>
                                        <div class="flex items-center text-sm text-gray-600 mt-1">
                                            <span class="mr-4">
                                                <i class="fas fa-credit-card mr-1"></i>
                                                <?= htmlspecialchars($pedido['forma_pago']) ?>
                                            </span>
                                            <span>
                                                <?php if ($pedido['modalidad'] === 'Delivery'): ?>
                                                    <i class="fas fa-truck text-green-600 mr-1"></i>Delivery
                                                <?php else: ?>
                                                    <i class="fas fa-store text-blue-600 mr-1"></i>Retira
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Dirección si es delivery -->
                                    <?php if ($pedido['modalidad'] === 'Delivery' && $pedido['direccion']): ?>
                                        <div class="text-sm text-gray-600 mb-2">
                                            <i class="fas fa-map-marker-alt text-red-500 mr-1"></i>
                                            <?= htmlspecialchars($pedido['direccion']) ?>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Observaciones -->
                                    <?php if ($pedido['observaciones']): ?>
                                        <div class="text-sm text-gray-600 mb-2">
                                            <i class="fas fa-comment text-yellow-500 mr-1"></i>
                                            <strong>Obs:</strong> <?= htmlspecialchars($pedido['observaciones']) ?>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Tiempo transcurrido -->
                                    <div class="flex justify-between items-center">
                                        <div class="text-sm text-gray-500">
                                            <i class="fas fa-clock mr-1"></i>
                                            Hace <?= $pedido['minutos_transcurridos'] ?> min
                                            (<?= date('H:i', strtotime($pedido['created_at'])) ?>)
                                            <span class="ml-2">
                                                <i class="fas fa-calendar mr-1"></i>
                                                <?= date('d/m/Y', strtotime($pedido['created_at'])) ?>
                                            </span>
                                        </div>
                                        
                                        <!-- Indicadores de urgencia -->
                                        <div class="flex items-center space-x-2">
                                            <?php if ($pedido['minutos_transcurridos'] > 60): ?>
                                                <span class="bg-red-100 text-red-800 px-2 py-1 rounded text-xs font-medium">
                                                    <i class="fas fa-exclamation-triangle mr-1"></i>URGENTE
                                                </span>
                                            <?php elseif ($pedido['minutos_transcurridos'] > 30): ?>
                                                <span class="bg-orange-100 text-orange-800 px-2 py-1 rounded text-xs font-medium">
                                                    <i class="fas fa-clock mr-1"></i>PRIORIDAD
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Acciones -->
                                <div class="flex flex-col space-y-2 ml-4">
                                    <!-- Impresión -->
                                    <?php if ($pedido['impreso']): ?>
                                        <div class="flex flex-col items-center space-y-1">
                                            <span class="bg-green-100 text-green-800 px-3 py-1 rounded-full text-xs">
                                                <i class="fas fa-check-circle mr-1"></i>Impresa
                                            </span>
                                            <button onclick="imprimirComanda(<?= $pedido['id'] ?>)" 
                                                    class="bg-gray-500 hover:bg-gray-600 text-white px-3 py-1 rounded text-xs">
                                                <i class="fas fa-print mr-1"></i>Re-imprimir
                                            </button>
                                        </div>
                                    <?php else: ?>
                                        <div class="flex flex-col items-center space-y-1">
                                            <span class="bg-red-100 text-red-800 px-3 py-1 rounded-full text-xs">
                                                <i class="fas fa-exclamation-circle mr-1"></i>Sin Imprimir
                                            </span>
                                            <button onclick="imprimirComanda(<?= $pedido['id'] ?>)" 
                                                    class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-xs">
                                                <i class="fas fa-print mr-1"></i>Imprimir
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- WhatsApp -->
                                    <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $pedido['telefono']) ?>?text=Hola%20<?= urlencode($pedido['nombre']) ?>,%20tu%20pedido%20#<?= $pedido['id'] ?>%20está%20<?= urlencode(strtolower($pedido['estado'])) ?>" 
                                       target="_blank"
                                       class="bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded text-xs text-center">
                                        <i class="fab fa-whatsapp mr-1"></i>WhatsApp
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Footer -->
        <div class="mt-8 text-center text-gray-500">
            <div class="bg-white rounded-lg p-4 shadow">
                <p class="mb-2">
                    <i class="fas fa-sync-alt mr-1"></i>
                    Página actualizada automáticamente cada 30 segundos
                </p>
                <p class="text-sm">
                    🔴 Más de 1 hora = Urgente | 🟠 Más de 30 min = Prioridad
                </p>
            </div>
        </div>
    </main>

    <!-- Modal de impresión -->
    <div id="impresionModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-lg p-6 w-full max-w-md mx-4">
            <h3 class="text-xl font-bold mb-4">
                <i class="fas fa-print text-blue-500 mr-2"></i>Imprimir Comanda Simple
            </h3>
            <div id="impresionContent">
                <p>Preparando impresión...</p>
            </div>
            <div class="flex justify-end space-x-2 mt-6">
                <button onclick="cerrarModal()" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded">
                    Cancelar
                </button>
                <button onclick="confirmarImpresion()" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded">
                    <i class="fas fa-print mr-1"></i>Confirmar Impresión
                </button>
            </div>
        </div>
    </div>

    <script>
        let pedidoAImprimir = null;

        // Filtros rápidos
        function setFiltroRapido(tipo) {
            const hoy = new Date();
            let desde, hasta;
            
            switch(tipo) {
                case 'hoy':
                    desde = hasta = formatDate(hoy);
                    break;
                    
                case 'ayer':
                    const ayer = new Date(hoy);
                    ayer.setDate(ayer.getDate() - 1);
                    desde = hasta = formatDate(ayer);
                    break;
                    
                case 'semana':
                    const inicioSemana = new Date(hoy);
                    const diaSemana = inicioSemana.getDay();
                    const diasAtras = diaSemana === 0 ? 6 : diaSemana - 1;
                    inicioSemana.setDate(inicioSemana.getDate() - diasAtras);
                    desde = formatDate(inicioSemana);
                    hasta = formatDate(hoy);
                    break;
            }
            
            if (desde && hasta) {
                document.querySelector('input[name="fecha_desde"]').value = desde;
                document.querySelector('input[name="fecha_hasta"]').value = hasta;
                document.querySelector('form').submit();
            }
        }
        
        function limpiarFiltros() {
            window.location.href = '?';
        }
        
        function formatDate(date) {
            const año = date.getFullYear();
            const mes = String(date.getMonth() + 1).padStart(2, '0');
            const dia = String(date.getDate()).padStart(2, '0');
            return `${año}-${mes}-${dia}`;
        }

        // Funciones de impresión
        function imprimirComanda(pedidoId) {
            pedidoAImprimir = pedidoId;
            
            document.getElementById('impresionContent').innerHTML = `
                <div class="text-center">
                    <i class="fas fa-print text-4xl text-blue-500 mb-3"></i>
                    <p class="mb-2">Se imprimirá una comanda simple del pedido <strong>#${pedidoId}</strong> para Local 1.</p>
                    <p class="text-sm text-gray-600">Formato: Nombre, Pedido, Fecha, Turno (M/S/T), Precio</p>
                    <p class="text-xs text-gray-500 mt-2">Asegúrate de que la impresora esté encendida.</p>
                </div>
            `;
            
            document.getElementById('impresionModal').classList.remove('hidden');
        }

        function confirmarImpresion() {
            if (pedidoAImprimir) {
                // Abrir comanda simple
                const url = `comanda_simple.php?pedido=${pedidoAImprimir}`;
                const ventana = window.open(url, '_blank', 'width=400,height=600,scrollbars=yes');
                
                if (!ventana) {
                    alert('Error: No se pudo abrir la ventana de impresión.\nVerifica que no esté bloqueada por el navegador.');
                    cerrarModal();
                    return;
                }
                
                // Marcar como impreso
                fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `accion=marcar_impreso&id=${pedidoAImprimir}`
                }).then(() => {
                    console.log('Pedido marcado como impreso');
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                });
                
                ventana.focus();
            }
            cerrarModal();
        }

        function cerrarModal() {
            document.getElementById('impresionModal').classList.add('hidden');
            pedidoAImprimir = null;
        }

        // Auto refresh cada 30 segundos
        setInterval(function() {
            if (!document.activeElement || document.activeElement.tagName !== 'SELECT') {
                location.reload();
            }
        }, 30000);
    </script>
</body>
</html>