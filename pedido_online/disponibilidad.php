<?php
/**
 * AJAX: disponibilidad de cupos por fecha
 * GET ?fecha=YYYY-MM-DD
 * Returns JSON: { "Mañana": {max, ocupados, disponible, activo, hora_inicio, minutos_antes_corte}, ... }
 */
require_once '../admin/config.php';
header('Content-Type: application/json; charset=utf-8');

$fecha = $_GET['fecha'] ?? '';
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
    http_response_code(400);
    echo json_encode(['error' => 'Fecha inválida']);
    exit;
}

// Validar que no sea fecha pasada
$hoy = (new DateTime('today', new DateTimeZone('America/Argentina/Buenos_Aires')))->format('Y-m-d');
if ($fecha < $hoy) {
    http_response_code(400);
    echo json_encode(['error' => 'Fecha pasada']);
    exit;
}

$pdo = getConnection();

// Migrar tablas si no existen
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS config_pedidos_online_dias (
            id INT AUTO_INCREMENT PRIMARY KEY,
            turno VARCHAR(20) NOT NULL,
            dia_semana TINYINT(1) NOT NULL COMMENT '0=Dom,1=Lun,2=Mar,3=Mie,4=Jue,5=Vie,6=Sab',
            max_pedidos INT NOT NULL DEFAULT 30,
            activo TINYINT(1) NOT NULL DEFAULT 1,
            UNIQUE KEY uk_turno_dia (turno, dia_semana)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Sembrar si está vacío
    $count = (int)$pdo->query("SELECT COUNT(*) FROM config_pedidos_online_dias")->fetchColumn();
    if ($count === 0) {
        $base = $pdo->query("SELECT turno, max_pedidos FROM config_pedidos_online")->fetchAll();
        $ins = $pdo->prepare("INSERT IGNORE INTO config_pedidos_online_dias (turno, dia_semana, max_pedidos, activo) VALUES (?,?,?,1)");
        foreach ($base as $b) {
            for ($d = 0; $d <= 6; $d++) {
                $ins->execute([$b['turno'], $d, $b['max_pedidos']]);
            }
        }
    }
} catch (PDOException $e) { /* ya existe */ }

// Agregar columna turno_entrega si no existe
try {
    $pdo->exec("ALTER TABLE pedidos ADD COLUMN turno_entrega VARCHAR(20) DEFAULT NULL");
} catch (PDOException $e) { /* ya existe */ }

// Día de semana de la fecha pedida (0=Dom, 1=Lun ... 6=Sab)
$diaSemana = (int)date('w', strtotime($fecha));

// Config global por turno (hora_inicio, hora_fin, minutos_antes_corte)
$globalConfig = [];
$stmt = $pdo->query("SELECT turno, hora_inicio, hora_fin, minutos_antes_corte FROM config_pedidos_online");
foreach ($stmt->fetchAll() as $row) {
    $globalConfig[$row['turno']] = $row;
}

$result = [];
foreach (['Mañana', 'Siesta', 'Tarde'] as $turno) {
    // Config de ese día
    $stmt = $pdo->prepare("SELECT max_pedidos, activo FROM config_pedidos_online_dias WHERE turno = ? AND dia_semana = ?");
    $stmt->execute([$turno, $diaSemana]);
    $dayConfig = $stmt->fetch();

    $global = $globalConfig[$turno] ?? ['hora_inicio' => '00:00', 'hora_fin' => '00:00', 'minutos_antes_corte' => 30];
    $maxPedidos = $dayConfig ? (int)$dayConfig['max_pedidos'] : 30;
    $activo     = $dayConfig ? (bool)$dayConfig['activo'] : false;

    // Contar pedidos confirmados para esa fecha y turno
    // Incluye tanto pedidos online (turno_entrega o LIKE) como pedidos admin (turno_entrega)
    $cnt = $pdo->prepare("
        SELECT COUNT(*) FROM pedidos
        WHERE DATE(fecha_entrega) = ?
          AND estado != 'Cancelado'
          AND (
            turno_entrega = ?
            OR (turno_entrega IS NULL AND observaciones LIKE ?)
          )
    ");
    $cnt->execute([$fecha, $turno, '%PEDIDO ONLINE%Turno: ' . $turno . '%']);
    $ocupados = (int)$cnt->fetchColumn();

    $disponible = $activo ? max(0, $maxPedidos - $ocupados) : 0;

    $result[$turno] = [
        'turno'               => $turno,
        'hora_inicio'         => substr($global['hora_inicio'], 0, 5),
        'hora_fin'            => substr($global['hora_fin'], 0, 5),
        'minutos_antes_corte' => (int)$global['minutos_antes_corte'],
        'max_pedidos'         => $maxPedidos,
        'ocupados'            => $ocupados,
        'disponible'          => $disponible,
        'activo'              => $activo,
    ];
}

echo json_encode($result);
