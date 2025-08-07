<?php
// Debug: Mostrar errores
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../admin/config.php';
session_start();

// Si ya está logueado como empleado, redirigir al dashboard
if (isset($_SESSION['empleado_logged']) && $_SESSION['empleado_logged'] === true) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$debug_info = [];

if ($_POST) {
    $usuario = sanitize($_POST['usuario']);
    $password = $_POST['password'];
    
    $debug_info[] = "Usuario ingresado: " . $usuario;
    $debug_info[] = "Password ingresado: " . $password;
    
    try {
        $pdo = getConnection();
        $debug_info[] = "Conexión exitosa a la BD";
        
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE usuario = ? AND activo = 1");
        $stmt->execute([$usuario]);
        $user = $stmt->fetch();
        
        if ($user) {
            $debug_info[] = "Usuario encontrado en BD: " . $user['usuario'];
            $debug_info[] = "Password en BD: " . $user['password'];
            $debug_info[] = "Nombre: " . $user['nombre'];
            
            // Verificar password y que sea empleado
            if ($password === 'Emple2186' && $user['usuario'] === 'empleado') {
                $_SESSION['empleado_logged'] = true;
                $_SESSION['empleado_user'] = $user['usuario'];
                $_SESSION['empleado_name'] = $user['nombre'];
                $_SESSION['empleado_id'] = $user['id'];
                
                $debug_info[] = "Login exitoso - Redirigiendo...";
                
                header('Location: dashboard.php');
                exit;
            } else {
                $debug_info[] = "Fallo en verificación - Password o usuario incorrecto";
                $error = 'Credenciales incorrectas';
            }
        } else {
            $debug_info[] = "Usuario no encontrado en BD";
            $error = 'Usuario no encontrado';
        }
    } catch (Exception $e) {
        $debug_info[] = "Error de conexión: " . $e->getMessage();
        $error = 'Error de conexión: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso Empleados - Santa Catalina</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gradient-to-br from-blue-400 to-blue-600 min-h-screen flex items-center justify-center">
    <div class="bg-white p-8 rounded-lg shadow-xl w-full max-w-md">
        <div class="text-center mb-6">
            <i class="fas fa-users text-4xl text-blue-500 mb-3"></i>
            <h1 class="text-2xl font-bold text-gray-800">Santa Catalina</h1>
            <p class="text-gray-600">Acceso para Empleados</p>
        </div>
        
        <?php if ($error): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <i class="fas fa-exclamation-triangle mr-2"></i><?= $error ?>
        </div>
        <?php endif; ?>
        
        <!-- Debug Info (solo para testing) -->
        <?php if (!empty($debug_info)): ?>
        <div class="bg-yellow-100 border border-yellow-400 text-yellow-800 px-4 py-3 rounded mb-4 text-sm">
            <strong>Debug Info:</strong>
            <?php foreach ($debug_info as $info): ?>
                <div>• <?= htmlspecialchars($info) ?></div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <form method="POST" class="space-y-4">
            <div>
                <label class="block text-gray-700 mb-2">
                    <i class="fas fa-user mr-1"></i>Usuario
                </label>
                <input type="text" name="usuario" required 
                       value="empleado"
                       class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                       placeholder="empleado">
            </div>
            
            <div>
                <label class="block text-gray-700 mb-2">
                    <i class="fas fa-lock mr-1"></i>Contraseña
                </label>
                <input type="password" name="password" required
                       class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>
            
            <button type="submit" class="w-full bg-blue-500 hover:bg-blue-600 text-white py-2 px-4 rounded-lg transition">
                <i class="fas fa-sign-in-alt mr-2"></i>Ingresar
            </button>
        </form>
        
        <div class="text-center mt-6 space-y-2">
            <a href="../index.php" class="text-blue-500 hover:underline text-sm">
                <i class="fas fa-arrow-left mr-1"></i>Volver al sitio
            </a>
            <div class="text-xs text-gray-500 border-t pt-2">
                ¿Eres administrador? <a href="../admin/login.php" class="text-orange-500 hover:underline">Acceso Admin</a>
            </div>
        </div>
        
        <!-- Info de prueba -->
        <div class="text-center mt-4 p-3 bg-blue-50 rounded text-xs text-gray-600">
            <strong>Credenciales de prueba:</strong><br>
            Usuario: empleado<br>
            Contraseña: Emple2186
        </div>
    </div>
</body>
</html>