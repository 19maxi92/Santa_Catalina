<?php
// admin/modules/impresion/comanda_bebidas.php
require_once '../../config.php';
requireLogin();

$pedido_id = isset($_GET['pedido']) ? (int)$_GET['pedido'] : 0;
if (!$pedido_id) die('ID de pedido requerido');

$pdo = getConnection();

$stmt = $pdo->prepare("
    SELECT p.*, cf.nombre as cliente_fijo_nombre, cf.apellido as cliente_fijo_apellido
    FROM pedidos p
    LEFT JOIN clientes_fijos cf ON p.cliente_fijo_id = cf.id
    WHERE p.id = ?
");
$stmt->execute([$pedido_id]);
$pedido = $stmt->fetch();

if (!$pedido) die('Pedido no encontrado');
if (empty($pedido['bebidas_json'])) die('Este pedido no tiene bebidas cargadas.');

$nombre_completo = !empty($pedido['cliente_fijo_nombre'])
    ? $pedido['cliente_fijo_nombre'] . ' ' . $pedido['cliente_fijo_apellido']
    : $pedido['nombre'] . ' ' . $pedido['apellido'];

$items = json_decode($pedido['bebidas_json'], true) ?: [];
// Normalizar: puede ser array de {nombre,cantidad} o mapa {nombre:cantidad}
if (!empty($items) && !isset($items[0])) {
    $arr = [];
    foreach ($items as $nombre => $cantidad) $arr[] = ['nombre' => $nombre, 'cantidad' => $cantidad];
    $items = $arr;
}

$precio_formatted = '$' . number_format((int)($pedido['bebidas_precio'] ?? 0), 0, ',', '.');
$fecha_now = date('d/m/Y H:i');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Comanda Bebidas #<?= $pedido_id ?></title>
    <style>
        @page { size: 90mm auto; margin: 0; }
        body {
            font-family: 'Courier New', monospace;
            font-size: 10px;
            width: 340px;
            margin: 0;
            padding: 0;
            background: white;
        }
        .controles {
            background: #e0f7fa;
            padding: 10px;
            text-align: center;
            margin-bottom: 10px;
        }
        .btn {
            background: #00796b;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
            margin: 2px;
        }
        .ticket {
            width: 340px;
            padding: 10px;
            box-sizing: border-box;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #000;
            padding-bottom: 6px;
            margin-bottom: 6px;
        }
        .header-titulo {
            font-size: 14px;
            font-weight: bold;
            letter-spacing: 2px;
        }
        .header-beb {
            font-size: 16px;
            font-weight: bold;
            margin: 4px 0;
            letter-spacing: 1px;
        }
        .comanda-id {
            font-size: 18px;
            font-weight: bold;
        }
        .separador {
            border-top: 1px dashed #000;
            margin: 5px 0;
        }
        .linea {
            display: flex;
            justify-content: space-between;
            margin: 2px 0;
            font-size: 11px;
        }
        .linea-bebida {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 13px;
            font-weight: bold;
            margin: 4px 0;
            border-bottom: 1px dotted #ccc;
            padding-bottom: 3px;
        }
        .qty-badge {
            background: #000;
            color: #fff;
            padding: 1px 6px;
            border-radius: 3px;
            font-size: 14px;
        }
        .total-linea {
            display: flex;
            justify-content: space-between;
            font-size: 14px;
            font-weight: bold;
            border-top: 2px solid #000;
            padding-top: 5px;
            margin-top: 5px;
        }
        .footer {
            text-align: center;
            font-size: 9px;
            color: #555;
            margin-top: 8px;
            border-top: 1px solid #ccc;
            padding-top: 5px;
        }
        @media print {
            .controles { display: none; }
            body { width: 90mm; }
        }
    </style>
</head>
<body>

<div class="controles">
    <button class="btn" onclick="window.print()">🖨️ Imprimir</button>
    <button class="btn" style="background:#555" onclick="window.close()">✕ Cerrar</button>
</div>

<div class="ticket">
    <!-- HEADER -->
    <div class="header">
        <div class="header-titulo">SANTA CATALINA</div>
        <div class="header-beb">🥤 BEBIDAS</div>
        <div class="comanda-id">Pedido #<?= $pedido_id ?></div>
    </div>

    <!-- CLIENTE -->
    <div class="linea">
        <span><strong><?= htmlspecialchars($nombre_completo) ?></strong></span>
        <span><?= htmlspecialchars($pedido['telefono']) ?></span>
    </div>
    <div class="linea">
        <span>Pedido: <?= htmlspecialchars($pedido['producto']) ?></span>
    </div>

    <div class="separador"></div>

    <!-- BEBIDAS -->
    <?php foreach ($items as $item): ?>
    <div class="linea-bebida">
        <span><?= htmlspecialchars($item['nombre']) ?></span>
        <span class="qty-badge">x<?= (int)$item['cantidad'] ?></span>
    </div>
    <?php endforeach; ?>

    <!-- TOTAL -->
    <?php if ((int)($pedido['bebidas_precio'] ?? 0) > 0): ?>
    <div class="total-linea">
        <span>TOTAL BEBIDAS</span>
        <span><?= $precio_formatted ?></span>
    </div>
    <?php endif; ?>

    <div class="footer">
        Impreso: <?= $fecha_now ?> — <?= htmlspecialchars($_SESSION['usuario'] ?? '') ?>
    </div>
</div>

<script>
    document.addEventListener('keydown', e => {
        if (e.key === 'Enter' || e.key === ' ') window.print();
        if (e.key === 'Escape') window.close();
    });
</script>
</body>
</html>
