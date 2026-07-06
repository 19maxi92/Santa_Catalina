<?php
require_once '../../config.php';
requireLogin();

$pdo = getConnection();

// Crear tabla si no existe
$pdo->exec("
    CREATE TABLE IF NOT EXISTS precios_personalizados (
        id INT AUTO_INCREMENT PRIMARY KEY,
        categoria VARCHAR(20) NOT NULL,
        planchas INT NOT NULL,
        precio INT NOT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        updated_by VARCHAR(50) DEFAULT 'admin',
        UNIQUE KEY uk_cat_planchas (categoria, planchas)
    )
");

// Insertar valores por defecto si la tabla está vacía
$count = $pdo->query("SELECT COUNT(*) FROM precios_personalizados")->fetchColumn();
if ($count == 0) {
    $stmt = $pdo->prepare("INSERT INTO precios_personalizados (categoria, planchas, precio) VALUES (?, ?, ?)");
    foreach ([
        ['premium',1,9000],['premium',2,18000],['premium',3,27000],
        ['premium',4,36000],['premium',5,45000],['premium',6,54000],
        ['elegidos',1,5400],['elegidos',2,10800],['elegidos',3,16000],
        ['elegidos',4,21400],['elegidos',5,26800],['elegidos',6,32000],
    ] as $r) $stmt->execute($r);
}

$mensaje = '';
$error = '';

// Guardar cambios
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar'])) {
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("UPDATE precios_personalizados SET precio = ?, updated_by = ? WHERE categoria = ? AND planchas = ?");
        foreach ($_POST['precios'] as $cat => $planchasArr) {
            foreach ($planchasArr as $planchas => $precio) {
                $precio = (int) str_replace(['.', ',', '$', ' '], '', $precio);
                if ($precio > 0) {
                    $stmt->execute([$precio, $_SESSION['admin_user'] ?? 'admin', $cat, (int)$planchas]);
                }
            }
        }
        $pdo->commit();
        $mensaje = '✅ Precios actualizados correctamente.';
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = '❌ Error: ' . $e->getMessage();
    }
}

// Cargar precios
$filas = $pdo->query("SELECT categoria, planchas, precio, updated_at FROM precios_personalizados ORDER BY categoria, planchas")->fetchAll();
$precios = [];
foreach ($filas as $f) {
    $precios[$f['categoria']][$f['planchas']] = $f['precio'];
}
$ultima_actualizacion = $filas ? max(array_column($filas, 'updated_at')) : null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Precios Personalizados - <?= APP_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100">

<header class="bg-white shadow-md sticky top-0 z-50">
    <div class="container mx-auto px-4 py-3 flex justify-between items-center">
        <div class="flex items-center space-x-4">
            <a href="../../" class="text-gray-600 hover:text-gray-800">
                <i class="fas fa-arrow-left text-xl"></i>
            </a>
            <div>
                <h1 class="text-2xl font-bold text-gray-800">
                    <i class="fas fa-sliders-h text-purple-500 mr-2"></i>Precios Personalizados
                </h1>
                <p class="text-xs text-gray-500">Precios por plancha para Premium y Elegidos (8 sándwiches = 1 plancha)</p>
            </div>
        </div>
        <?php if ($ultima_actualizacion): ?>
        <span class="text-xs text-gray-400">Última actualización: <?= date('d/m H:i', strtotime($ultima_actualizacion)) ?></span>
        <?php endif; ?>
    </div>
</header>

<main class="container mx-auto px-4 py-6 max-w-3xl">

    <?php if ($mensaje): ?>
    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 px-5 py-4 rounded-lg mb-6">
        <i class="fas fa-check-circle mr-2"></i><?= $mensaje ?>
    </div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 px-5 py-4 rounded-lg mb-6">
        <i class="fas fa-exclamation-triangle mr-2"></i><?= $error ?>
    </div>
    <?php endif; ?>

    <form method="POST">
        <?php foreach ([
            'premium' => ['label' => '🟠 Surtidos Premium', 'color' => 'orange'],
            'elegidos' => ['label' => '⭐ Surtidos Elegidos', 'color' => 'yellow'],
        ] as $cat => $info): ?>
        <div class="bg-white rounded-xl shadow-md mb-6 overflow-hidden">
            <div class="bg-<?= $info['color'] ?>-50 border-b border-<?= $info['color'] ?>-200 px-6 py-4">
                <h2 class="text-lg font-bold text-gray-800"><?= $info['label'] ?></h2>
            </div>

            <!-- Header tabla -->
            <div class="grid grid-cols-4 gap-3 px-6 py-3 bg-gray-50 border-b text-xs font-semibold text-gray-500 uppercase tracking-wide">
                <div>Planchas</div>
                <div>Sándwiches</div>
                <div>Precio (transferencia)</div>
                <div>Efectivo (-10%)</div>
            </div>

            <?php for ($p = 1; $p <= 6; $p++):
                $precio = $precios[$cat][$p] ?? 0;
                $efectivo = (int)round($precio * 0.9 / 500) * 500;
            ?>
            <div class="grid grid-cols-4 gap-3 px-6 py-3 border-b border-gray-100 items-center hover:bg-gray-50 transition-colors">
                <div class="font-bold text-gray-700"><?= $p ?> plancha<?= $p > 1 ? 's' : '' ?></div>
                <div class="text-gray-500"><?= $p * 8 ?> sánd.</div>
                <div>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 font-bold">$</span>
                        <input type="number"
                               name="precios[<?= $cat ?>][<?= $p ?>]"
                               value="<?= $precio ?>"
                               step="100"
                               min="0"
                               class="w-full pl-7 pr-3 py-2 border-2 border-gray-200 rounded-lg focus:border-purple-500 focus:ring-2 focus:ring-purple-100 font-semibold text-gray-800 transition-all precio-input"
                               data-planchas="<?= $p ?>"
                               data-cat="<?= $cat ?>">
                    </div>
                </div>
                <div class="text-sm text-green-700 font-semibold efectivo-display" id="ef-<?= $cat ?>-<?= $p ?>">
                    $<?= number_format($efectivo, 0, ',', '.') ?>
                </div>
            </div>
            <?php endfor; ?>
        </div>
        <?php endforeach; ?>

        <div class="flex justify-end">
            <button type="submit" name="guardar"
                    class="px-8 py-3 bg-purple-600 hover:bg-purple-700 text-white rounded-xl font-bold text-lg shadow-lg transition-all transform hover:scale-[1.02]">
                <i class="fas fa-save mr-2"></i>Guardar precios
            </button>
        </div>
    </form>

    <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 mt-6 text-sm text-blue-800">
        <i class="fas fa-info-circle mr-2"></i>
        Los precios se aplican automáticamente al crear pedidos personalizados. El efectivo se calcula restando 10%.
    </div>
</main>

<script>
// Actualizar columna efectivo en tiempo real
document.querySelectorAll('.precio-input').forEach(input => {
    input.addEventListener('input', function() {
        const cat = this.dataset.cat;
        const p = this.dataset.planchas;
        const precio = parseInt(this.value) || 0;
        const efectivo = Math.round(precio * 0.9 / 500) * 500;
        const display = document.getElementById(`ef-${cat}-${p}`);
        if (display) display.textContent = '$' + efectivo.toLocaleString('es-AR');
    });
});
// Evitar scroll en número
document.addEventListener('wheel', function() {
    if (document.activeElement.type === 'number') document.activeElement.blur();
}, { passive: true });
</script>

</body>
</html>
