<?php
/**
 * Configuración de pedidos online
 * - Config de turnos por día de semana
 * - Precios de planchas (común / premium)
 */
require_once '../../config.php';
requireLogin();

$pdo = getConnection();

// ─── Migraciones automáticas ──────────────────────────────────────────────────
try {
    $pdo->exec("ALTER TABLE config_pedidos_online ADD COLUMN minutos_antes_corte INT NOT NULL DEFAULT 30");
    $pdo->exec("UPDATE config_pedidos_online SET minutos_antes_corte = 900 WHERE turno = 'Mañana' AND minutos_antes_corte = 30");
} catch (PDOException $e) {}

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS config_pedidos_online_dias (
            id INT AUTO_INCREMENT PRIMARY KEY,
            turno VARCHAR(20) NOT NULL,
            dia_semana TINYINT(1) NOT NULL,
            max_pedidos INT NOT NULL DEFAULT 30,
            activo TINYINT(1) NOT NULL DEFAULT 1,
            UNIQUE KEY uk_turno_dia (turno, dia_semana)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
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
} catch (PDOException $e) {}

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS config_precios_elegidos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tipo VARCHAR(10) NOT NULL,
            precio_efectivo DECIMAL(10,2) NOT NULL DEFAULT 0,
            precio_transferencia DECIMAL(10,2) NOT NULL DEFAULT 0,
            UNIQUE KEY uk_tipo (tipo)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $cp = (int)$pdo->query("SELECT COUNT(*) FROM config_precios_elegidos")->fetchColumn();
    if ($cp === 0) {
        $pdo->exec("INSERT INTO config_precios_elegidos (tipo, precio_efectivo, precio_transferencia) VALUES
            ('comun',   4200, 4200),
            ('premium', 5500, 5500)");
    }
} catch (PDOException $e) {}

// ─── Procesar POST ────────────────────────────────────────────────────────────
$mensaje_exito = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {

    // Guardar config global de turno (hora_inicio, hora_fin, minutos_antes_corte)
    if ($_POST['accion'] === 'actualizar_turno_global') {
        $turno         = $_POST['turno'];
        $minutos_corte = max(0, (int)$_POST['minutos_antes_corte']);
        $pdo->prepare("UPDATE config_pedidos_online SET minutos_antes_corte = ? WHERE turno = ?")
            ->execute([$minutos_corte, $turno]);
        $mensaje_exito = "Configuración de {$turno} actualizada";
    }

    // Guardar config por día
    if ($_POST['accion'] === 'actualizar_dia') {
        $turno      = $_POST['turno'];
        $dia        = (int)$_POST['dia_semana'];
        $max        = max(0, (int)$_POST['max_pedidos']);
        $activo     = isset($_POST['activo']) ? 1 : 0;
        $pdo->prepare("
            INSERT INTO config_pedidos_online_dias (turno, dia_semana, max_pedidos, activo)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE max_pedidos = VALUES(max_pedidos), activo = VALUES(activo)
        ")->execute([$turno, $dia, $max, $activo]);
        $mensaje_exito = "Cupos del día actualizados";
    }

    // Guardar precios de planchas
    if ($_POST['accion'] === 'actualizar_precios') {
        foreach (['comun', 'premium'] as $tipo) {
            $ef  = max(0, (float)str_replace(',', '.', $_POST["precio_{$tipo}_ef"]  ?? 0));
            $tr  = max(0, (float)str_replace(',', '.', $_POST["precio_{$tipo}_tr"]  ?? 0));
            $pdo->prepare("UPDATE config_precios_elegidos SET precio_efectivo=?, precio_transferencia=? WHERE tipo=?")
                ->execute([$ef, $tr, $tipo]);
        }
        $mensaje_exito = "Precios de planchas actualizados";
    }
}

// ─── Leer configuración ───────────────────────────────────────────────────────
$config_global = [];
foreach ($pdo->query("SELECT * FROM config_pedidos_online ORDER BY FIELD(turno,'Mañana','Siesta','Tarde')")->fetchAll() as $r) {
    $config_global[$r['turno']] = $r;
}

// Config por día: indexado [turno][dia_semana]
$config_dias = [];
foreach ($pdo->query("SELECT * FROM config_pedidos_online_dias")->fetchAll() as $r) {
    $config_dias[$r['turno']][$r['dia_semana']] = $r;
}

// Precios elegidos
$precios_elegidos = [];
foreach ($pdo->query("SELECT * FROM config_precios_elegidos")->fetchAll() as $r) {
    $precios_elegidos[$r['tipo']] = $r;
}

// Ocupación de hoy por turno
$ocupacion_hoy = [];
foreach (['Mañana', 'Siesta', 'Tarde'] as $t) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM pedidos WHERE observaciones LIKE ? AND DATE(fecha_entrega) = CURDATE() AND estado != 'Cancelado'");
    $stmt->execute(['%PEDIDO ONLINE%Turno: ' . $t . '%']);
    $ocupacion_hoy[$t] = (int)$stmt->fetchColumn();
}

$dias_semana = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
$dia_hoy     = (int)date('w'); // 0=Dom
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración Pedidos Online - <?= APP_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100">

<header class="bg-white shadow-md">
    <div class="container mx-auto px-4 py-4 flex items-center gap-4">
        <a href="../../index.php" class="text-gray-600 hover:text-gray-800">
            <i class="fas fa-arrow-left text-xl"></i>
        </a>
        <h1 class="text-2xl font-bold text-gray-800">
            <i class="fas fa-cog text-purple-600 mr-2"></i>Configuración Pedidos Online
        </h1>
        <div class="ml-auto flex gap-2">
            <a href="localidades.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-semibold">
                <i class="fas fa-map-marker-alt mr-1"></i> Localidades Delivery
            </a>
        </div>
    </div>
</header>

<main class="container mx-auto px-4 py-8 max-w-6xl">

    <?php if ($mensaje_exito): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-lg">
            <i class="fas fa-check-circle mr-2"></i><?= htmlspecialchars($mensaje_exito) ?>
        </div>
    <?php endif; ?>

    <!-- Ocupación de hoy -->
    <div class="grid grid-cols-3 gap-4 mb-8">
        <?php foreach (['Mañana', 'Siesta', 'Tarde'] as $t): ?>
            <?php
            $diaConfig = $config_dias[$t][$dia_hoy] ?? null;
            $maxHoy    = $diaConfig ? $diaConfig['max_pedidos'] : '—';
            $ocupHoy   = $ocupacion_hoy[$t];
            ?>
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-sm font-bold text-gray-500 uppercase mb-1"><?= $t ?> — HOY</div>
                <div class="text-3xl font-black text-gray-900"><?= $ocupHoy ?><span class="text-base text-gray-400 font-normal">/<?= $maxHoy ?></span></div>
                <div class="text-xs text-gray-400 mt-1">pedidos ocupados</div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Grid cupos por día de semana -->
    <div class="bg-white rounded-xl shadow-lg overflow-hidden mb-8">
        <div class="bg-gradient-to-r from-orange-500 to-red-500 text-white px-6 py-4">
            <h2 class="text-lg font-bold"><i class="fas fa-calendar-week mr-2"></i>Cupos por día de semana</h2>
            <p class="text-sm opacity-80">Configurá el máximo de pedidos por turno para cada día</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 border-b">
                        <th class="px-4 py-3 text-left font-bold text-gray-700">Turno</th>
                        <?php foreach ($dias_semana as $idx => $dia): ?>
                            <th class="px-3 py-3 text-center font-bold <?= $idx === $dia_hoy ? 'text-orange-600 bg-orange-50' : 'text-gray-600' ?>">
                                <?= $dia ?>
                                <?php if ($idx === $dia_hoy): ?><div class="text-xs font-normal text-orange-500">hoy</div><?php endif; ?>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (['Mañana', 'Siesta', 'Tarde'] as $turno): ?>
                        <?php $g = $config_global[$turno] ?? []; ?>
                        <tr class="border-b hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <div class="font-bold text-gray-900"><?= $turno ?></div>
                                <div class="text-xs text-gray-400"><?= substr($g['hora_inicio']??'', 0, 5) ?> – <?= substr($g['hora_fin']??'', 0, 5) ?></div>
                            </td>
                            <?php foreach ($dias_semana as $idx => $dia): ?>
                                <?php
                                $dc      = $config_dias[$turno][$idx] ?? ['max_pedidos' => 30, 'activo' => 1];
                                $max     = $dc['max_pedidos'];
                                $activo  = $dc['activo'];
                                $esHoy   = $idx === $dia_hoy;
                                ?>
                                <td class="px-2 py-2 text-center <?= $esHoy ? 'bg-orange-50' : '' ?>">
                                    <form method="POST" class="inline-block">
                                        <input type="hidden" name="accion" value="actualizar_dia">
                                        <input type="hidden" name="turno" value="<?= $turno ?>">
                                        <input type="hidden" name="dia_semana" value="<?= $idx ?>">
                                        <div class="flex flex-col items-center gap-1">
                                            <input type="number" name="max_pedidos" value="<?= $max ?>" min="0" max="200"
                                                   class="w-16 text-center border rounded-lg px-1 py-1 text-sm font-semibold <?= $activo ? 'border-gray-300' : 'border-gray-200 bg-gray-100 text-gray-400' ?>"
                                                   <?= !$activo ? 'disabled' : '' ?>>
                                            <label class="flex items-center gap-1 text-xs cursor-pointer">
                                                <input type="checkbox" name="activo" value="1" <?= $activo ? 'checked' : '' ?>
                                                       onchange="this.closest('form').querySelector('input[name=max_pedidos]').disabled=!this.checked;
                                                                 this.closest('form').querySelector('input[name=max_pedidos]').className=this.checked?'w-16 text-center border border-gray-300 rounded-lg px-1 py-1 text-sm font-semibold':'w-16 text-center border border-gray-200 bg-gray-100 text-gray-400 rounded-lg px-1 py-1 text-sm'">
                                                <span class="<?= $activo ? 'text-green-600' : 'text-gray-400' ?>"><?= $activo ? 'ON' : 'OFF' ?></span>
                                            </label>
                                            <button type="submit" class="text-xs bg-orange-500 hover:bg-orange-600 text-white px-2 py-0.5 rounded font-semibold">
                                                OK
                                            </button>
                                        </div>
                                    </form>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Config global de turnos (corte de horario) -->
    <div class="bg-white rounded-xl shadow-lg overflow-hidden mb-8">
        <div class="bg-gradient-to-r from-purple-500 to-indigo-500 text-white px-6 py-4">
            <h2 class="text-lg font-bold"><i class="fas fa-stopwatch mr-2"></i>Corte de horario por turno</h2>
            <p class="text-sm opacity-80">Minutos antes del inicio del turno en que se cortan los pedidos de Delivery</p>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 p-6">
            <?php foreach (['Mañana', 'Siesta', 'Tarde'] as $turno): ?>
                <?php $g = $config_global[$turno] ?? []; ?>
                <form method="POST">
                    <input type="hidden" name="accion" value="actualizar_turno_global">
                    <input type="hidden" name="turno" value="<?= $turno ?>">
                    <div class="border-2 border-gray-200 rounded-xl p-4">
                        <div class="font-black text-gray-900 mb-1"><?= $turno ?></div>
                        <div class="text-xs text-gray-500 mb-3"><?= substr($g['hora_inicio']??'', 0, 5) ?> – <?= substr($g['hora_fin']??'', 0, 5) ?></div>
                        <label class="block text-xs font-bold text-gray-600 mb-1">Minutos de corte</label>
                        <input type="number" name="minutos_antes_corte" value="<?= $g['minutos_antes_corte'] ?? 30 ?>" min="0" max="1440"
                               class="w-full border-2 border-gray-300 rounded-lg px-3 py-2 font-semibold text-center text-lg mb-1">
                        <?php
                        $min = $g['minutos_antes_corte'] ?? 30;
                        if ($min >= 60) {
                            $hs = floor($min/60); $r = $min%60;
                            echo "<p class='text-xs text-gray-400 mb-3'>= {$hs}h" . ($r ? " {$r}min" : '') . " antes del turno</p>";
                        } else {
                            echo "<p class='text-xs text-gray-400 mb-3'>= {$min} min antes del turno</p>";
                        }
                        ?>
                        <button type="submit" class="w-full bg-purple-500 hover:bg-purple-600 text-white py-2 rounded-lg font-bold text-sm">
                            <i class="fas fa-save mr-1"></i> Guardar
                        </button>
                    </div>
                </form>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Precios de planchas elegidos -->
    <div class="bg-white rounded-xl shadow-lg overflow-hidden mb-8">
        <div class="bg-gradient-to-r from-green-500 to-emerald-600 text-white px-6 py-4">
            <h2 class="text-lg font-bold"><i class="fas fa-tag mr-2"></i>Precios por plancha — Pedidos "A mi gusto"</h2>
            <p class="text-sm opacity-80">Precio por plancha de 8 sándwiches según tipo de sabor</p>
        </div>
        <form method="POST" class="p-6">
            <input type="hidden" name="accion" value="actualizar_precios">
            <div class="grid grid-cols-2 gap-6">
                <?php foreach (['comun' => ['label' => 'Plancha Común', 'color' => 'green', 'icon' => '🟢'], 'premium' => ['label' => 'Plancha Premium', 'color' => 'orange', 'icon' => '🟠']] as $tipo => $info): ?>
                    <?php $p = $precios_elegidos[$tipo] ?? ['precio_efectivo' => 0, 'precio_transferencia' => 0]; ?>
                    <div class="border-2 border-<?= $info['color'] ?>-200 rounded-xl p-4 bg-<?= $info['color'] ?>-50">
                        <div class="font-bold text-gray-900 mb-3"><?= $info['icon'] ?> <?= $info['label'] ?></div>
                        <div class="space-y-3">
                            <div>
                                <label class="block text-xs font-bold text-gray-600 mb-1">Efectivo (por plancha de 8)</label>
                                <div class="flex items-center gap-1">
                                    <span class="text-gray-500 font-bold">$</span>
                                    <input type="number" name="precio_<?= $tipo ?>_ef" value="<?= number_format($p['precio_efectivo'], 0, '.', '') ?>"
                                           min="0" step="100"
                                           class="flex-1 border-2 border-gray-300 rounded-lg px-3 py-2 font-semibold text-lg">
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-600 mb-1">Transferencia (por plancha de 8)</label>
                                <div class="flex items-center gap-1">
                                    <span class="text-gray-500 font-bold">$</span>
                                    <input type="number" name="precio_<?= $tipo ?>_tr" value="<?= number_format($p['precio_transferencia'], 0, '.', '') ?>"
                                           min="0" step="100"
                                           class="flex-1 border-2 border-gray-300 rounded-lg px-3 py-2 font-semibold text-lg">
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="submit" class="mt-4 w-full bg-green-500 hover:bg-green-600 text-white py-3 rounded-xl font-bold">
                <i class="fas fa-save mr-2"></i> Guardar precios
            </button>
        </form>
    </div>

    <!-- Info -->
    <div class="bg-blue-50 border-l-4 border-blue-500 p-6 rounded-lg">
        <h3 class="text-lg font-bold text-blue-900 mb-3"><i class="fas fa-info-circle mr-2"></i>Información</h3>
        <ul class="space-y-2 text-sm text-blue-800">
            <li><i class="fas fa-check mr-2 text-blue-600"></i>Los cupos se descuentan por fecha de entrega, no por fecha de pedido.</li>
            <li><i class="fas fa-check mr-2 text-blue-600"></i>OFF en un día significa que el turno no acepta pedidos ese día.</li>
            <li><i class="fas fa-check mr-2 text-blue-600"></i>El corte de horario aplica solo a pedidos Delivery. Retiro no tiene corte.</li>
            <li><i class="fas fa-check mr-2 text-blue-600"></i>El precio premium aplica por cada plancha de sabor premium elegida.</li>
            <li><i class="fas fa-check mr-2 text-blue-600"></i>Link clientes: <code class="bg-white px-2 py-1 rounded text-blue-700"><?= $_SERVER['HTTP_HOST'] ?>/pedido_online/</code></li>
        </ul>
    </div>

</main>
</body>
</html>
