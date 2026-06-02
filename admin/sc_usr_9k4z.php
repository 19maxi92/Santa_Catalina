<?php
// Archivo de gestión de usuarios - ACCESO RESTRINGIDO
// URL directa: /admin/sc_usr_9k4z.php
// Sin links visibles en el sistema

session_start();
require_once 'config.php';

// Contraseña de acceso a ESTA página (solo vos la sabés)
define('MASTER_KEY', 'ScAdmin#2186!xK');

$pdo = getConnection();
$autenticado = isset($_SESSION['sc_master_auth']) && $_SESSION['sc_master_auth'] === true;
$mensaje = '';
$error = '';

// ---- Autenticación de la página ----
if (!$autenticado) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['master_key'])) {
        if ($_POST['master_key'] === MASTER_KEY) {
            $_SESSION['sc_master_auth'] = true;
            $autenticado = true;
        } else {
            $error = 'Clave incorrecta.';
        }
    }
    if (!$autenticado) {
        mostrarLogin($error);
        exit;
    }
}

// ---- Logout de esta página ----
if (isset($_GET['salir'])) {
    unset($_SESSION['sc_master_auth']);
    header('Location: sc_usr_9k4z.php');
    exit;
}

// ---- Procesar cambio de contraseña ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    if ($_POST['accion'] === 'cambiar_password') {
        $uid  = (int)($_POST['uid'] ?? 0);
        $pass = trim($_POST['nueva_password'] ?? '');

        if ($uid && strlen($pass) >= 4) {
            $stmt = $pdo->prepare("UPDATE usuarios SET password = ? WHERE id = ?");
            $stmt->execute([$pass, $uid]);
            $mensaje = "✅ Contraseña actualizada correctamente.";
        } else {
            $error = "⚠️ La contraseña debe tener al menos 4 caracteres.";
        }
    }
}

// ---- Obtener usuarios ----
$usuarios = $pdo->query("SELECT id, usuario, nombre, rol, activo FROM usuarios ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

// ---- Vista ----
function mostrarLogin($err) { ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso restringido</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 min-h-screen flex items-center justify-center">
    <div class="bg-gray-800 p-8 rounded-xl shadow-2xl w-full max-w-sm">
        <h1 class="text-white text-xl font-bold mb-6 text-center">🔐 Acceso restringido</h1>
        <?php if ($err): ?>
            <div class="bg-red-900 text-red-200 px-4 py-2 rounded mb-4 text-sm"><?= htmlspecialchars($err) ?></div>
        <?php endif; ?>
        <form method="POST">
            <input type="password" name="master_key" placeholder="Clave de acceso" autofocus
                   class="w-full bg-gray-700 text-white px-4 py-3 rounded-lg mb-4 focus:outline-none focus:ring-2 focus:ring-purple-500">
            <button type="submit" class="w-full bg-purple-600 hover:bg-purple-700 text-white py-3 rounded-lg font-bold transition">
                Ingresar
            </button>
        </form>
    </div>
</body>
</html>
<?php }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de usuarios</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-900 min-h-screen p-6">
    <div class="max-w-2xl mx-auto">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-white text-2xl font-bold">🔑 Gestión de usuarios</h1>
            <a href="?salir=1" class="text-gray-400 hover:text-red-400 text-sm transition">
                <i class="fas fa-sign-out-alt mr-1"></i>Cerrar sesión
            </a>
        </div>

        <?php if ($mensaje): ?>
            <div class="bg-green-900 text-green-200 px-4 py-3 rounded-lg mb-4"><?= htmlspecialchars($mensaje) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="bg-red-900 text-red-200 px-4 py-3 rounded-lg mb-4"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="space-y-4">
            <?php foreach ($usuarios as $u): ?>
            <div class="bg-gray-800 rounded-xl p-5">
                <div class="flex items-center gap-3 mb-4">
                    <div class="bg-purple-700 rounded-full w-10 h-10 flex items-center justify-center text-white font-bold text-lg">
                        <?= strtoupper(substr($u['usuario'], 0, 1)) ?>
                    </div>
                    <div>
                        <div class="text-white font-semibold"><?= htmlspecialchars($u['nombre'] ?: $u['usuario']) ?></div>
                        <div class="text-gray-400 text-sm">@<?= htmlspecialchars($u['usuario']) ?> · <?= htmlspecialchars($u['rol']) ?> · <?= $u['activo'] ? '<span class="text-green-400">Activo</span>' : '<span class="text-red-400">Inactivo</span>' ?></div>
                    </div>
                </div>
                <form method="POST" class="flex gap-2" onsubmit="return confirm('¿Cambiar contraseña de <?= htmlspecialchars($u['usuario']) ?>?')">
                    <input type="hidden" name="accion" value="cambiar_password">
                    <input type="hidden" name="uid" value="<?= $u['id'] ?>">
                    <input type="password" name="nueva_password" placeholder="Nueva contraseña"
                           class="flex-1 bg-gray-700 text-white px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 text-sm"
                           required minlength="4">
                    <button type="submit"
                            class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg text-sm font-semibold transition whitespace-nowrap">
                        <i class="fas fa-key mr-1"></i>Cambiar
                    </button>
                </form>
            </div>
            <?php endforeach; ?>
        </div>

        <p class="text-gray-600 text-xs text-center mt-8">Acceso privado · Santa Catalina</p>
    </div>
</body>
</html>
