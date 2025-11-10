<?php
require_once '../../config.php';
requireLogin();

// === PROTECCIÓN DE ACCESO CON CONTRASEÑA (SIN CACHE) ===
$EMPLEADOS_PASSWORD = 'Santa.Catalina2186';
$password_error = '';
$password_valida = false;

// Verificar contraseña en CADA acceso (sin usar sesión)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['empleados_password'])) {
    if ($_POST['empleados_password'] === $EMPLEADOS_PASSWORD) {
        $password_valida = true;
    } else {
        $password_error = 'Contraseña incorrecta. Acceso denegado.';
        $password_valida = false;
    }
}

// Si no se envió contraseña válida, mostrar formulario
if (!$password_valida) {
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Acceso Restringido - Empleados</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    </head>
    <body class="bg-gradient-to-br from-purple-100 to-blue-100 min-h-screen flex items-center justify-center">
        <div class="max-w-md w-full mx-4">
            <div class="bg-white rounded-lg shadow-xl p-8">
                <div class="text-center mb-6">
                    <i class="fas fa-lock text-5xl text-purple-500 mb-4"></i>
                    <h1 class="text-2xl font-bold text-gray-800">Acceso Restringido</h1>
                    <p class="text-gray-600 mt-2">Módulo de Gestión de Empleados</p>
                </div>

                <?php if ($password_error): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                        <i class="fas fa-exclamation-circle mr-2"></i><?= htmlspecialchars($password_error) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-key mr-1"></i>Contraseña de Acceso
                        </label>
                        <input type="password"
                               name="empleados_password"
                               required
                               autofocus
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                               placeholder="Ingrese la contraseña">
                    </div>

                    <button type="submit"
                            class="w-full bg-purple-600 hover:bg-purple-700 text-white font-semibold py-3 rounded-lg transition-colors">
                        <i class="fas fa-unlock mr-2"></i>Acceder
                    </button>
                </form>

                <div class="mt-6 text-center">
                    <a href="../../index.php" class="text-sm text-gray-600 hover:text-purple-600">
                        <i class="fas fa-arrow-left mr-1"></i>Volver al Dashboard
                    </a>
                </div>

                <div class="mt-6 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                    <p class="text-xs text-yellow-800">
                        <i class="fas fa-info-circle mr-1"></i>
                        <strong>Nota:</strong> Este módulo contiene información sensible de empleados y nóminas.
                        El acceso requiere contraseña en cada acceso.
                    </p>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}
// === FIN PROTECCIÓN DE ACCESO ===

$pdo = getConnection();

// Crear tabla empleados si no existe
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS empleados_nomina (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nombre VARCHAR(100) NOT NULL,
            apellido VARCHAR(100) NOT NULL,
            documento VARCHAR(20),
            salario_mensual DECIMAL(10,2) DEFAULT 0,
            fecha_ingreso DATE,
            activo TINYINT(1) DEFAULT 1,
            notas TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS pagos_empleados (
            id INT AUTO_INCREMENT PRIMARY KEY,
            empleado_id INT NOT NULL,
            monto DECIMAL(10,2) NOT NULL,
            fecha_pago DATE NOT NULL,
            periodo_mes INT,
            periodo_anio INT,
            metodo_pago VARCHAR(50) DEFAULT 'Efectivo',
            notas TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (empleado_id) REFERENCES empleados_nomina(id) ON DELETE CASCADE
        )
    ");
} catch (Exception $e) {
    // Tablas ya existen
}

// Procesar acciones
if ($_POST) {
    if (isset($_POST['accion'])) {
        switch ($_POST['accion']) {
            case 'agregar_empleado':
                $stmt = $pdo->prepare("
                    INSERT INTO empleados_nomina (nombre, apellido, documento, salario_mensual, fecha_ingreso, notas)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $_POST['nombre'],
                    $_POST['apellido'],
                    $_POST['documento'],
                    $_POST['salario_mensual'],
                    $_POST['fecha_ingreso'],
                    $_POST['notas']
                ]);
                $mensaje = "Empleado agregado correctamente";
                break;

            case 'registrar_pago':
                $stmt = $pdo->prepare("
                    INSERT INTO pagos_empleados (empleado_id, monto, fecha_pago, periodo_mes, periodo_anio, metodo_pago, notas)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $_POST['empleado_id'],
                    $_POST['monto'],
                    $_POST['fecha_pago'],
                    $_POST['periodo_mes'],
                    $_POST['periodo_anio'],
                    $_POST['metodo_pago'],
                    $_POST['notas_pago']
                ]);
                $mensaje = "Pago registrado correctamente";
                break;

            case 'desactivar':
                $stmt = $pdo->prepare("UPDATE empleados_nomina SET activo = 0 WHERE id = ?");
                $stmt->execute([$_POST['id']]);
                $mensaje = "Empleado desactivado";
                break;

            case 'activar':
                $stmt = $pdo->prepare("UPDATE empleados_nomina SET activo = 1 WHERE id = ?");
                $stmt->execute([$_POST['id']]);
                $mensaje = "Empleado activado";
                break;
        }
    }
}

// Obtener lista de empleados
$mostrar_inactivos = isset($_GET['inactivos']) && $_GET['inactivos'] == '1';
$where = $mostrar_inactivos ? "" : "WHERE activo = 1";

$empleados = $pdo->query("
    SELECT e.*,
           (SELECT COUNT(*) FROM pagos_empleados WHERE empleado_id = e.id) as total_pagos,
           (SELECT COALESCE(SUM(monto), 0) FROM pagos_empleados WHERE empleado_id = e.id) as total_pagado,
           (SELECT fecha_pago FROM pagos_empleados WHERE empleado_id = e.id ORDER BY fecha_pago DESC LIMIT 1) as ultimo_pago
    FROM empleados_nomina e
    $where
    ORDER BY activo DESC, apellido ASC
")->fetchAll();

// Obtener últimos pagos
$ultimos_pagos = $pdo->query("
    SELECT p.*, CONCAT(e.nombre, ' ', e.apellido) as empleado_nombre
    FROM pagos_empleados p
    JOIN empleados_nomina e ON p.empleado_id = e.id
    ORDER BY p.created_at DESC
    LIMIT 10
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Empleados - <?= APP_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100">

    <!-- Header -->
    <header class="bg-white shadow-md sticky top-0 z-50">
        <div class="container mx-auto px-4 py-3 flex justify-between items-center">
            <div class="flex items-center">
                <a href="../../index.php" class="text-gray-600 hover:text-gray-800 mr-4">
                    <i class="fas fa-arrow-left text-xl"></i>
                </a>
                <h1 class="text-xl font-bold text-gray-800">
                    <i class="fas fa-users text-purple-500 mr-2"></i>Gestión de Empleados
                </h1>
            </div>
        </div>
    </header>

    <main class="container mx-auto px-4 py-6">

        <?php if (isset($mensaje)): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            <i class="fas fa-check-circle mr-2"></i><?= $mensaje ?>
        </div>
        <?php endif; ?>

        <!-- Botones de acción -->
        <div class="flex flex-wrap gap-3 mb-6">
            <button onclick="toggleModal('modalAgregar')" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded">
                <i class="fas fa-user-plus mr-2"></i>Agregar Empleado
            </button>
            <button onclick="toggleModal('modalPago')" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">
                <i class="fas fa-money-bill-wave mr-2"></i>Registrar Pago
            </button>
            <a href="?inactivos=<?= $mostrar_inactivos ? '0' : '1' ?>" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded">
                <i class="fas fa-filter mr-2"></i><?= $mostrar_inactivos ? 'Solo Activos' : 'Ver Inactivos' ?>
            </a>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- Lista de Empleados -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-lg shadow">
                    <div class="p-4 border-b">
                        <h2 class="text-lg font-bold text-gray-800">
                            <i class="fas fa-list mr-2"></i>Empleados (<?= count($empleados) ?>)
                        </h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Empleado</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Salario</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Último Pago</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total Pagado</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($empleados as $emp): ?>
                                <tr class="hover:bg-gray-50 <?= !$emp['activo'] ? 'opacity-50' : '' ?>">
                                    <td class="px-4 py-3">
                                        <div class="font-medium text-gray-900"><?= $emp['nombre'] . ' ' . $emp['apellido'] ?></div>
                                        <div class="text-xs text-gray-500">Ingreso: <?= date('d/m/Y', strtotime($emp['fecha_ingreso'])) ?></div>
                                    </td>
                                    <td class="px-4 py-3 text-sm font-medium text-gray-900">
                                        $<?= number_format($emp['salario_mensual'], 0, ',', '.') ?>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-500">
                                        <?= $emp['ultimo_pago'] ? date('d/m/Y', strtotime($emp['ultimo_pago'])) : 'Nunca' ?>
                                    </td>
                                    <td class="px-4 py-3 text-sm font-medium text-green-600">
                                        $<?= number_format($emp['total_pagado'], 0, ',', '.') ?>
                                        <div class="text-xs text-gray-500">(<?= $emp['total_pagos'] ?> pagos)</div>
                                    </td>
                                    <td class="px-4 py-3 text-sm">
                                        <div class="flex gap-2">
                                            <button onclick="pagarEmpleado(<?= $emp['id'] ?>, '<?= $emp['nombre'] . ' ' . $emp['apellido'] ?>', <?= $emp['salario_mensual'] ?>)"
                                                    class="text-blue-600 hover:text-blue-800" title="Registrar pago">
                                                <i class="fas fa-dollar-sign"></i>
                                            </button>
                                            <button onclick="verHistorial(<?= $emp['id'] ?>)"
                                                    class="text-purple-600 hover:text-purple-800" title="Ver historial">
                                                <i class="fas fa-history"></i>
                                            </button>
                                            <form method="POST" class="inline" onsubmit="return confirm('¿Confirmar?')">
                                                <input type="hidden" name="accion" value="<?= $emp['activo'] ? 'desactivar' : 'activar' ?>">
                                                <input type="hidden" name="id" value="<?= $emp['id'] ?>">
                                                <input type="hidden" name="empleados_password" value="<?= $EMPLEADOS_PASSWORD ?>">
                                                <button type="submit" class="<?= $emp['activo'] ? 'text-red-600 hover:text-red-800' : 'text-green-600 hover:text-green-800' ?>"
                                                        title="<?= $emp['activo'] ? 'Desactivar' : 'Activar' ?>">
                                                    <i class="fas fa-<?= $emp['activo'] ? 'times-circle' : 'check-circle' ?>"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Últimos Pagos -->
            <div>
                <div class="bg-white rounded-lg shadow">
                    <div class="p-4 border-b">
                        <h2 class="text-lg font-bold text-gray-800">
                            <i class="fas fa-receipt mr-2"></i>Últimos Pagos
                        </h2>
                    </div>
                    <div class="p-4 space-y-3 max-h-96 overflow-y-auto">
                        <?php foreach ($ultimos_pagos as $pago): ?>
                        <div class="border-l-4 border-green-500 bg-green-50 p-3 rounded">
                            <div class="font-medium text-sm text-gray-900"><?= $pago['empleado_nombre'] ?></div>
                            <div class="text-lg font-bold text-green-600">$<?= number_format($pago['monto'], 0, ',', '.') ?></div>
                            <div class="text-xs text-gray-500">
                                <?= date('d/m/Y', strtotime($pago['fecha_pago'])) ?> - <?= $pago['metodo_pago'] ?>
                            </div>
                            <?php if ($pago['periodo_mes']): ?>
                            <div class="text-xs text-gray-600 mt-1">
                                Período: <?= $pago['periodo_mes'] ?>/<?= $pago['periodo_anio'] ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

        </div>

    </main>

    <!-- Modal Agregar Empleado -->
    <div id="modalAgregar" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-xl font-bold text-gray-800">
                        <i class="fas fa-user-plus mr-2 text-green-500"></i>Agregar Empleado
                    </h3>
                    <button onclick="toggleModal('modalAgregar')" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="accion" value="agregar_empleado">
                    <input type="hidden" name="empleados_password" value="<?= $EMPLEADOS_PASSWORD ?>">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nombre *</label>
                            <input type="text" name="nombre" required class="w-full px-3 py-2 border rounded-lg">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Apellido *</label>
                            <input type="text" name="apellido" required class="w-full px-3 py-2 border rounded-lg">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Documento</label>
                        <input type="text" name="documento" class="w-full px-3 py-2 border rounded-lg">
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Salario Mensual *</label>
                            <input type="number" name="salario_mensual" step="0.01" required class="w-full px-3 py-2 border rounded-lg">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Fecha Ingreso *</label>
                            <input type="date" name="fecha_ingreso" required value="<?= date('Y-m-d') ?>" class="w-full px-3 py-2 border rounded-lg">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Notas</label>
                        <textarea name="notas" rows="2" class="w-full px-3 py-2 border rounded-lg"></textarea>
                    </div>
                    <button type="submit" class="w-full bg-green-600 hover:bg-green-700 text-white py-2 px-4 rounded-lg font-medium">
                        <i class="fas fa-save mr-2"></i>Guardar Empleado
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Registrar Pago -->
    <div id="modalPago" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-xl font-bold text-gray-800">
                        <i class="fas fa-money-bill-wave mr-2 text-blue-500"></i>Registrar Pago
                    </h3>
                    <button onclick="toggleModal('modalPago')" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="accion" value="registrar_pago">
                    <input type="hidden" name="empleados_password" value="<?= $EMPLEADOS_PASSWORD ?>">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Empleado *</label>
                        <select name="empleado_id" id="empleado_select" required class="w-full px-3 py-2 border rounded-lg">
                            <option value="">Seleccionar...</option>
                            <?php foreach ($empleados as $emp): ?>
                                <?php if ($emp['activo']): ?>
                                <option value="<?= $emp['id'] ?>" data-salario="<?= $emp['salario_mensual'] ?>">
                                    <?= $emp['nombre'] . ' ' . $emp['apellido'] ?> ($<?= number_format($emp['salario_mensual'], 0, ',', '.') ?>)
                                </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Monto *</label>
                            <input type="number" name="monto" id="monto_pago" step="0.01" required class="w-full px-3 py-2 border rounded-lg">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Fecha Pago *</label>
                            <input type="date" name="fecha_pago" required value="<?= date('Y-m-d') ?>" class="w-full px-3 py-2 border rounded-lg">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Mes</label>
                            <select name="periodo_mes" class="w-full px-3 py-2 border rounded-lg">
                                <option value="">-</option>
                                <?php for($i=1; $i<=12; $i++): ?>
                                <option value="<?= $i ?>" <?= $i == date('n') ? 'selected' : '' ?>><?= $i ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Año</label>
                            <select name="periodo_anio" class="w-full px-3 py-2 border rounded-lg">
                                <?php for($y = date('Y'); $y >= date('Y')-2; $y--): ?>
                                <option value="<?= $y ?>"><?= $y ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Método de Pago *</label>
                        <select name="metodo_pago" required class="w-full px-3 py-2 border rounded-lg">
                            <option value="Efectivo">Efectivo</option>
                            <option value="Transferencia">Transferencia</option>
                            <option value="Cheque">Cheque</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Notas</label>
                        <textarea name="notas_pago" rows="2" class="w-full px-3 py-2 border rounded-lg"></textarea>
                    </div>
                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-2 px-4 rounded-lg font-medium">
                        <i class="fas fa-check mr-2"></i>Registrar Pago
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Historial (placeholder) -->
    <div id="modalHistorial" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full max-h-96 overflow-y-auto">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-xl font-bold text-gray-800">
                        <i class="fas fa-history mr-2 text-purple-500"></i>Historial de Pagos
                    </h3>
                    <button onclick="toggleModal('modalHistorial')" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                <div id="historial-content">
                    <!-- Se carga con AJAX -->
                </div>
            </div>
        </div>
    </div>

    <script>
    function toggleModal(modalId) {
        const modal = document.getElementById(modalId);
        modal.classList.toggle('hidden');
    }

    function pagarEmpleado(id, nombre, salario) {
        document.getElementById('empleado_select').value = id;
        document.getElementById('monto_pago').value = salario;
        toggleModal('modalPago');
    }

    function verHistorial(empleadoId) {
        fetch(`historial_pagos.php?empleado_id=${empleadoId}`)
            .then(r => r.text())
            .then(html => {
                document.getElementById('historial-content').innerHTML = html;
                toggleModal('modalHistorial');
            });
    }

    // Auto-llenar monto al seleccionar empleado
    document.getElementById('empleado_select').addEventListener('change', function() {
        const option = this.options[this.selectedIndex];
        const salario = option.dataset.salario;
        if (salario) {
            document.getElementById('monto_pago').value = salario;
        }
    });
    </script>

</body>
</html>
