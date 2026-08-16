<?php
require_once '../../config.php';
requireLogin();

$pdo = getConnection();

// Columna de sucursal asignada (self-healing)
try { $pdo->exec("ALTER TABLE usuarios ADD COLUMN ubicacion VARCHAR(50) DEFAULT 'Local 1'"); } catch (PDOException $e) {}

$UBICACIONES = ['Local 1', 'Fábrica', 'Villa Elisa'];

$mensaje = '';
$error = '';

// Crear usuario nuevo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'crear_usuario') {
    $usuario    = trim($_POST['usuario'] ?? '');
    $password   = trim($_POST['password'] ?? '');
    $nombre     = trim($_POST['nombre'] ?? '');
    $ubicacion  = $_POST['ubicacion'] ?? 'Local 1';

    if ($usuario === '' || $password === '' || $nombre === '') {
        $error = 'Completá usuario, contraseña y nombre.';
    } elseif (strlen($password) < 4) {
        $error = 'La contraseña debe tener al menos 4 caracteres.';
    } elseif (!in_array($ubicacion, $UBICACIONES)) {
        $error = 'Sucursal inválida.';
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE usuario = ?");
        $stmt->execute([$usuario]);
        if ($stmt->fetchColumn() > 0) {
            $error = 'Ya existe un usuario con ese nombre de acceso.';
        } else {
            $stmt = $pdo->prepare("INSERT INTO usuarios (usuario, password, nombre, rol, ubicacion, activo) VALUES (?, ?, ?, 'Empleado', ?, 1)");
            $stmt->execute([$usuario, $password, $nombre, $ubicacion]);
            $mensaje = "✅ Usuario '$usuario' creado correctamente para $ubicacion.";
        }
    }
}

// Cambiar sucursal de un usuario existente
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'cambiar_ubicacion') {
    $uid = (int)($_POST['uid'] ?? 0);
    $ubicacion = $_POST['ubicacion'] ?? '';
    if ($uid && in_array($ubicacion, $UBICACIONES)) {
        $pdo->prepare("UPDATE usuarios SET ubicacion = ? WHERE id = ?")->execute([$ubicacion, $uid]);
        $mensaje = "✅ Sucursal actualizada.";
    }
}

// Cambiar contraseña
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'cambiar_password') {
    $uid  = (int)($_POST['uid'] ?? 0);
    $pass = trim($_POST['nueva_password'] ?? '');
    if ($uid && strlen($pass) >= 4) {
        $pdo->prepare("UPDATE usuarios SET password = ? WHERE id = ?")->execute([$pass, $uid]);
        $mensaje = "✅ Contraseña actualizada.";
    } else {
        $error = 'La contraseña debe tener al menos 4 caracteres.';
    }
}

// Activar / desactivar
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'toggle_activo') {
    $uid = (int)($_POST['uid'] ?? 0);
    $nuevo = (int)($_POST['nuevo_estado'] ?? 0);
    if ($uid) {
        $pdo->prepare("UPDATE usuarios SET activo = ? WHERE id = ?")->execute([$nuevo, $uid]);
        $mensaje = $nuevo ? "✅ Usuario activado." : "✅ Usuario desactivado.";
    }
}

$usuarios = $pdo->query("SELECT id, usuario, nombre, rol, ubicacion, activo FROM usuarios ORDER BY activo DESC, id ASC")->fetchAll(PDO::FETCH_ASSOC);

$ICONO_UBICACION = ['Local 1' => '🏪', 'Fábrica' => '🏭', 'Villa Elisa' => '🏬'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios - <?= APP_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100">

    <header class="bg-white shadow-md sticky top-0 z-50">
        <div class="container mx-auto px-4 py-3 flex justify-between items-center">
            <div class="flex items-center">
                <a href="../../index.php" class="text-gray-600 hover:text-gray-800 mr-4">
                    <i class="fas fa-arrow-left text-xl"></i>
                </a>
                <h1 class="text-xl font-bold text-gray-800">
                    <i class="fas fa-users text-purple-500 mr-2"></i>Gestión de Usuarios
                </h1>
            </div>
        </div>
    </header>

    <main class="container mx-auto px-4 py-6 max-w-4xl">

        <?php if ($mensaje): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            <?= htmlspecialchars($mensaje) ?>
        </div>
        <?php endif; ?>
        <?php if ($error): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <i class="fas fa-exclamation-circle mr-2"></i><?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <!-- Crear nuevo usuario -->
        <div class="bg-white rounded-lg shadow mb-6">
            <div class="p-4 border-b">
                <h2 class="text-lg font-bold text-gray-800">
                    <i class="fas fa-user-plus text-green-500 mr-2"></i>Crear nuevo usuario
                </h2>
                <p class="text-xs text-gray-500 mt-1">Este usuario podrá ingresar en <span class="font-mono">/empleados/login.php</span> con acceso solo a su sucursal.</p>
            </div>
            <form method="POST" class="p-4 space-y-4">
                <input type="hidden" name="accion" value="crear_usuario">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nombre y apellido *</label>
                        <input type="text" name="nombre" required class="w-full px-3 py-2 border-2 rounded-lg" placeholder="Ej: Juana Pérez">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Sucursal *</label>
                        <select name="ubicacion" required class="w-full px-3 py-2 border-2 rounded-lg bg-white">
                            <?php foreach ($UBICACIONES as $u): ?>
                                <option value="<?= htmlspecialchars($u) ?>"><?= $ICONO_UBICACION[$u] ?> <?= htmlspecialchars($u) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Usuario (para ingresar) *</label>
                        <input type="text" name="usuario" required class="w-full px-3 py-2 border-2 rounded-lg" placeholder="ej: juana">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Contraseña *</label>
                        <input type="text" name="password" required minlength="4" class="w-full px-3 py-2 border-2 rounded-lg" placeholder="mínimo 4 caracteres">
                    </div>
                </div>
                <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-5 py-2 rounded-lg font-semibold">
                    <i class="fas fa-save mr-2"></i>Crear usuario
                </button>
            </form>
        </div>

        <!-- Lista de usuarios -->
        <div class="bg-white rounded-lg shadow">
            <div class="p-4 border-b">
                <h2 class="text-lg font-bold text-gray-800">
                    <i class="fas fa-list mr-2"></i>Usuarios (<?= count($usuarios) ?>)
                </h2>
            </div>
            <div class="divide-y">
                <?php foreach ($usuarios as $u): ?>
                <div class="p-4 flex flex-wrap items-center justify-between gap-3 <?= !$u['activo'] ? 'opacity-50' : '' ?>">
                    <div>
                        <div class="font-semibold text-gray-900">
                            <?= htmlspecialchars($u['nombre'] ?: $u['usuario']) ?>
                            <span class="text-xs text-gray-400 font-normal">@<?= htmlspecialchars($u['usuario']) ?></span>
                        </div>
                        <div class="text-xs text-gray-500">
                            <?= htmlspecialchars($u['rol'] ?: '—') ?>
                            · <?= $u['activo'] ? '<span class="text-green-600">Activo</span>' : '<span class="text-red-500">Inactivo</span>' ?>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <!-- Cambiar sucursal -->
                        <form method="POST" class="flex items-center gap-1">
                            <input type="hidden" name="accion" value="cambiar_ubicacion">
                            <input type="hidden" name="uid" value="<?= $u['id'] ?>">
                            <select name="ubicacion" onchange="this.form.submit()" class="text-sm border-2 rounded-lg px-2 py-1 bg-white">
                                <?php foreach ($UBICACIONES as $opt): ?>
                                    <option value="<?= htmlspecialchars($opt) ?>" <?= ($u['ubicacion'] ?? 'Local 1') === $opt ? 'selected' : '' ?>>
                                        <?= $ICONO_UBICACION[$opt] ?> <?= htmlspecialchars($opt) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </form>

                        <!-- Cambiar contraseña -->
                        <form method="POST" class="flex items-center gap-1" onsubmit="return confirm('¿Cambiar contraseña de <?= htmlspecialchars($u['usuario']) ?>?')">
                            <input type="hidden" name="accion" value="cambiar_password">
                            <input type="hidden" name="uid" value="<?= $u['id'] ?>">
                            <input type="password" name="nueva_password" placeholder="Nueva contraseña" minlength="4" required
                                   class="text-sm border-2 rounded-lg px-2 py-1 w-36">
                            <button type="submit" class="text-blue-600 hover:text-blue-800 text-sm px-2" title="Cambiar contraseña">
                                <i class="fas fa-key"></i>
                            </button>
                        </form>

                        <!-- Activar / desactivar -->
                        <form method="POST" onsubmit="return confirm('¿Confirmar?')">
                            <input type="hidden" name="accion" value="toggle_activo">
                            <input type="hidden" name="uid" value="<?= $u['id'] ?>">
                            <input type="hidden" name="nuevo_estado" value="<?= $u['activo'] ? 0 : 1 ?>">
                            <button type="submit" class="<?= $u['activo'] ? 'text-red-600 hover:text-red-800' : 'text-green-600 hover:text-green-800' ?> text-sm px-2"
                                    title="<?= $u['activo'] ? 'Desactivar' : 'Activar' ?>">
                                <i class="fas fa-<?= $u['activo'] ? 'user-slash' : 'user-check' ?>"></i>
                            </button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

    </main>

</body>
</html>
