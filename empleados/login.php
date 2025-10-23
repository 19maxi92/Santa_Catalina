<?php
require_once '../admin/config.php';
session_start();

// Si ya está logueado como empleado, redirigir al dashboard
if (isset($_SESSION['empleado_logged']) && $_SESSION['empleado_logged'] === true) {
    header('Location: dashboard.php'); // CAMBIO AQUÍ: era pedidos.php
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
        
        if ($user) {
            // Verificar password y que sea empleado
            if ($password === 'Emple2186' && $user['usuario'] === 'empleado') {
                $_SESSION['empleado_logged'] = true;
                $_SESSION['empleado_user'] = $user['usuario'];
                $_SESSION['empleado_name'] = $user['nombre'];
                $_SESSION['empleado_id'] = $user['id'];
                
                // CAMBIO: Redirigir directo al dashboard
                header('Location: dashboard.php');
                exit;
            } else {
                $error = 'Credenciales incorrectas';
            }
        } else {
            $error = 'Usuario no encontrado';
        }
    } catch (Exception $e) {
        $error = 'Error de conexión: ' . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Empleados - Santa Catalina</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        input {
            font-size: 16px !important; /* Evita zoom en iOS */
        }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-900 to-indigo-900 min-h-screen flex items-center justify-center p-4">

    <div class="bg-white rounded-xl shadow-2xl p-6 sm:p-8 max-w-md w-full">
        <div class="text-center mb-6 sm:mb-8">
            <div class="inline-block bg-blue-100 p-4 rounded-full mb-3">
                <i class="fas fa-users text-4xl sm:text-5xl text-blue-600"></i>
            </div>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-800">Empleados</h1>
            <p class="text-sm sm:text-base text-gray-600">Santa Catalina - Local 1</p>
        </div>

        <?php if ($error): ?>
            <div class="bg-red-100 border-2 border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4 text-sm sm:text-base">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-4 sm:space-y-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    <i class="fas fa-user mr-2 text-blue-500"></i>Usuario
                </label>
                <input type="text" name="usuario" required autocomplete="username"
                       class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all text-base"
                       placeholder="Ingrese su usuario">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    <i class="fas fa-lock mr-2 text-blue-500"></i>Contraseña
                </label>
                <input type="password" name="password" required autocomplete="current-password"
                       class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all text-base"
                       placeholder="Ingrese su contraseña">
            </div>

            <button type="submit"
                    class="w-full bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white font-bold py-3 sm:py-4 px-4 rounded-lg transition-all duration-200 shadow-lg hover:shadow-xl transform hover:scale-[1.02] active:scale-[0.98]">
                <i class="fas fa-sign-in-alt mr-2"></i>
                Ingresar al Dashboard
            </button>
        </form>

        <div class="mt-4 sm:mt-6 text-center space-y-3">
            <p class="text-xs sm:text-sm text-gray-500">
                <i class="fas fa-info-circle mr-1"></i>
                Acceso exclusivo para empleados autorizados
            </p>
            <a href="../" class="text-blue-600 hover:text-blue-700 font-medium text-sm hover:underline transition-colors block">
                <i class="fas fa-arrow-left mr-1"></i>Volver al inicio
            </a>
        </div>
    </div>

</body>
</html>