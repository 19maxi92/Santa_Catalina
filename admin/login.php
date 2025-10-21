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
</head>
<body class="bg-gradient-to-br from-orange-400 to-orange-600 min-h-screen flex items-center justify-center">
    <div class="bg-white p-8 rounded-lg shadow-xl w-full max-w-md">
        <div class="text-center mb-6">
            <h1 class="text-2xl font-bold text-gray-800">Santa Catalina</h1>
            <p class="text-gray-600">Panel de Administración</p>
        </div>
        
        <?php if ($error): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <?= $error ?>
        </div>
        <?php endif; ?>
        
        <form method="POST" class="space-y-4">
            <div>
                <label class="block text-gray-700 mb-2">Usuario</label>
                <input type="text" name="usuario" required 
                       class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
            </div>
            
            <div>
                <label class="block text-gray-700 mb-2">Contraseña</label>
                <input type="password" name="password" required
                       class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
            </div>
            
            <button type="submit" class="w-full bg-orange-500 hover:bg-orange-600 text-white py-2 px-4 rounded-lg transition">
                <i class="fas fa-sign-in-alt mr-2"></i>Ingresar
            </button>
        </form>
        
        <div class="text-center mt-4">
            <a href="../" class="text-orange-500 hover:underline">← Volver al sitio</a>
        </div>
    </div>
</body>
</html>