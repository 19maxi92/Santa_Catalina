<?php
session_start();

// Destruir solo las sesiones de empleado
unset($_SESSION['empleado_logged']);
unset($_SESSION['empleado_user']);
unset($_SESSION['empleado_name']);
unset($_SESSION['empleado_id']);

header('Location: ../index.php');
exit;
?>