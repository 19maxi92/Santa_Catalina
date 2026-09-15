<?php
header('Content-Type: text/plain; charset=utf-8');
ob_implicit_flush(true);
if (ob_get_level() > 0) ob_end_flush();

echo "1. Arrancó\n"; flush();

require_once __DIR__ . '/../admin/config.php';
echo "2. config.php cargado\n"; flush();

echo "3. Probando session_start()...\n"; flush();
if (session_status() === PHP_SESSION_NONE) session_start();
echo "4. Sesión OK. admin_logged = " . (isset($_SESSION['admin_logged']) ? var_export($_SESSION['admin_logged'], true) : 'no seteado') . "\n"; flush();

echo "5. Probando getConnection()...\n"; flush();
$pdo = getConnection();
echo "6. Conexión a la base OK\n"; flush();

echo "7. Probando SELECT en estaciones_impresion...\n"; flush();
$stmt = $pdo->query("SELECT * FROM estaciones_impresion ORDER BY id DESC");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "8. Query OK, " . count($rows) . " fila(s)\n"; flush();

echo "9. TODO BIEN — no debería haber 503\n";
