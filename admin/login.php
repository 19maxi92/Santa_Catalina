<?php
require_once 'config.php';
session_start();

// Si ya está logueado, redirigir
if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_POST) {
    $usuario = sanitize($_POST['usuario']);
    $password = $_POST['password'];
    
    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE usuario = ? AND activo = 1");
        $stmt->execute([$usuario]);
        $user = $stmt->fetch();
        
        // Verificar password simple (cambiar por hash después)
        if ($user && $password === 'Sangu2186') {
            $_SESSION['admin_logged'] = true;
            $_SESSION['admin_user'] = $user['usuario'];
            $_SESSION['admin_name'] = $user['nombre'];
            header('Location: index.php');
            exit;
        } else {
            $error = 'Usuario o contraseña incorrectos';
        }
    } catch (Exception $e) {
        $error = 'Error de conexión';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= APP_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        input {
            font-size: 16px !important; /* Evita zoom en iOS */
        }
    </style>
</head>
<body class="bg-gradient-to-br from-orange-400 to-orange-600 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white p-6 sm:p-8 rounded-xl shadow-2xl w-full max-w-md">
        <div class="text-center mb-6 sm:mb-8">
            <div class="inline-block bg-orange-100 p-4 rounded-full mb-3">
                <i class="fas fa-utensils text-3xl sm:text-4xl text-orange-500"></i>
            </div>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-800 mb-1">Santa Catalina</h1>
            <p class="text-sm sm:text-base text-gray-600">Panel de Administración</p>
        </div>

        <?php if ($error): ?>
        <div class="bg-red-100 border-2 border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4 text-sm sm:text-base">
            <i class="fas fa-exclamation-circle mr-2"></i><?= $error ?>
        </div>
        <?php endif; ?>

        <form method="POST" class="space-y-4 sm:space-y-5">
            <div>
                <label class="block text-gray-700 mb-2 font-medium text-sm sm:text-base">
                    <i class="fas fa-user mr-2 text-orange-500"></i>Usuario
                </label>
                <input type="text" name="usuario" required autocomplete="username"
                       class="w-full px-4 py-3 border-2 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition-all text-base">
            </div>

            <div>
                <label class="block text-gray-700 mb-2 font-medium text-sm sm:text-base">
                    <i class="fas fa-lock mr-2 text-orange-500"></i>Contraseña
                </label>
                <input type="password" name="password" required autocomplete="current-password"
                       class="w-full px-4 py-3 border-2 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition-all text-base">
            </div>

            <button type="submit" class="w-full bg-gradient-to-r from-orange-500 to-orange-600 hover:from-orange-600 hover:to-orange-700 text-white py-3 sm:py-4 px-4 rounded-lg font-bold text-base sm:text-lg shadow-lg hover:shadow-xl transition-all transform hover:scale-[1.02] active:scale-[0.98]">
                <i class="fas fa-sign-in-alt mr-2"></i>Ingresar al Panel
            </button>
        </form>

        <div class="text-center mt-6">
            <a href="../" class="text-orange-600 hover:text-orange-700 font-medium text-sm sm:text-base hover:underline transition-colors">
                <i class="fas fa-arrow-left mr-1"></i>Volver al sitio
            </a>
        </div>
    </div>
</body>
</html>