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
</head>
<body class="bg-gradient-to-br from-blue-900 to-indigo-900 min-h-screen flex items-center justify-center">
    
    <div class="bg-white rounded-xl shadow-2xl p-8 max-w-md w-full mx-4">
        <div class="text-center mb-8">
            <i class="fas fa-users text-5xl text-blue-600 mb-4"></i>
            <h1 class="text-3xl font-bold text-gray-800">Empleados</h1>
            <p class="text-gray-600">Santa Catalina - Local 1</p>
        </div>

        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    <i class="fas fa-user mr-2"></i>Usuario
                </label>
                <input type="text" name="usuario" required 
                       class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                       placeholder="Ingrese su usuario">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    <i class="fas fa-lock mr-2"></i>Contraseña
                </label>
                <input type="password" name="password" required 
                       class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                       placeholder="Ingrese su contraseña">
            </div>

            <button type="submit" 
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-lg transition duration-200">
                <i class="fas fa-sign-in-alt mr-2"></i>
                Ingresar al Dashboard
            </button>
        </form>

        <div class="mt-6 text-center">
            <p class="text-sm text-gray-500">
                <i class="fas fa-info-circle mr-1"></i>
                Acceso exclusivo para empleados autorizados
            </p>
        </div>
    </div>

</body>
</html>