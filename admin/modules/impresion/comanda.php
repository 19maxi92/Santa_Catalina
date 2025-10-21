<?php
// admin/modules/impresion/comanda.php - VERSIÓN COMPLETA CON OBSERVACIONES
require_once '../../config.php';
requireLogin();

$pedido_id = (int)($_GET['pedido'] ?? 0);
$es_admin = isset($_GET['admin']) && $_GET['admin'] === '1';

if ($pedido_id <= 0) {
    die('Error: ID de pedido no válido');
}

$pdo = getConnection();

$stmt = $pdo->prepare("
    SELECT p.*,
           cf.nombre as cliente_fijo_nombre,
           cf.apellido as cliente_fijo_apellido
    FROM pedidos p
    LEFT JOIN clientes_fijos cf ON p.cliente_fijo_id = cf.id
    WHERE p.id = ?
");
$stmt->execute([$pedido_id]);
$pedido = $stmt->fetch();

if (!$pedido) {
    die('Error: Pedido no encontrado');
}

$es_cliente_fijo = !empty($pedido['cliente_fijo_id']);
$nombre_completo = $es_cliente_fijo 
    ? $pedido['cliente_fijo_nombre'] . ' ' . $pedido['cliente_fijo_apellido']
    : $pedido['nombre'] . ' ' . $pedido['apellido'];

$fecha_pedido = new DateTime($pedido['fecha_pedido']);
$ahora = new DateTime();
$diferencia = $fecha_pedido->diff($ahora);
$minutos_transcurridos = ($diferencia->h * 60) + $diferencia->i;

$urgencia = '';
if ($minutos_transcurridos > 120) {
    $urgencia = '🔥 URGENTE';
} elseif ($minutos_transcurridos > 60) {
    $urgencia = '⚠️ PRIORIDAD';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Comanda Completa #<?= $pedido_id ?></title>
    <style>
        @page { size: 80mm auto; margin: 0; }
        
        body {
            font-family: 'Courier New', monospace;
            font-size: 10px;
            width: 302px;
            margin: 0;
            padding: 0;
            background: white;
        }
        
        .no-print {
            text-align: center;
            margin-bottom: 15px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 5px;
        }
        
        .comanda-header {
            text-align: center;
            border-bottom: 2px solid #000;
            padding-bottom: 8px;
            margin-bottom: 10px;
        }
        
        .comanda-header h1 {
            font-size: 16px;
            margin: 2px 0;
            font-weight: bold;
        }
        
        .comanda-header p {
            font-size: 9px;
            margin: 1px 0;
        }
        
        .seccion {
            margin: 8px 0;
            padding: 6px;
            border: 1px solid #ddd;
            background: #f9f9f9;
        }
        
        .seccion-titulo {
            font-weight: bold;
            font-size: 10px;
            margin-bottom: 4px;
        }
        
        .seccion-contenido {
            font-size: 9px;
            line-height: 1.4;
        }
        
        .producto-principal {
            font-size: 14px;
            font-weight: bold;
            text-align: center;
            padding: 10px;
            background: #000;
            color: #fff;
            margin: 10px 0;
        }
        
        .precio-total {
            font-size: 20px;
            font-weight: bold;
            text-align: center;
            padding: 8px;
            border: 2px solid #000;
            margin: 10px 0;
        }
        
        .footer {
            font-size: 8px;
            text-align: center;
            margin-top: 10px;
            padding-top: 8px;
            border-top: 1px dashed #999;
        }
        
        @media print {
            .no-print { display: none !important; }
            body { padding: 0; }
            @page { size: 80mm auto; margin: 0; }
        }
    </style>
</head>
<body>

    <div class="no-print">
        <h3 style="margin: 0 0 10px 0;">🖨️ Comanda Lista para Imprimir</h3>
        <button onclick="imprimirYCerrar()" style="background: #28a745; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; margin-right: 10px;">
            🖨️ IMPRIMIR
        </button>
        <button onclick="window.close()" style="background: #6c757d; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer;">
            ❌ Cancelar
        </button>
    </div>

    <div class="comanda-header">
        <h1>SANTA CATALINA</h1>
        <p>Sándwiches de Miga</p>
        <p>Tel: 11 5981-3546</p>
    </div>

    <?php if ($urgencia): ?>
        <div style="background: #ff0000; color: #fff; text-align: center; padding: 6px; font-weight: bold; font-size: 11px; margin-bottom: 10px;">
            <?= $urgencia ?>
        </div>
    <?php endif; ?>

    <div class="seccion">
        <div class="seccion-titulo">CLIENTE:</div>
        <div class="seccion-contenido">
            <strong><?= htmlspecialchars($nombre_completo) ?></strong>
            <?php if ($pedido['telefono']): ?>
                <br>Tel: <?= htmlspecialchars($pedido['telefono']) ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="producto-principal">
        <?= htmlspecialchars($pedido['producto']) ?>
    </div>

    <?php if (strpos($pedido['producto'], 'Personalizado') !== false && !empty($pedido['observaciones'])): ?>
        <?php
        $obs = $pedido['observaciones'];
        if (preg_match('/===\s*SABORES PERSONALIZADOS\s*===\n(.*?)(?:\n---|$)/s', $obs, $matches)) {
            $sabores_texto = trim($matches[1]);
            if (!empty($sabores_texto)):
        ?>
            <div class="seccion">
                <div class="seccion-titulo">SABORES DETALLADOS:</div>
                <div class="seccion-contenido">
                    <?= nl2br(htmlspecialchars($sabores_texto)) ?>
                </div>
            </div>
        <?php 
            endif;
        }
        ?>
    <?php endif; ?>

    <div class="precio-total">
        <?= formatPrice($pedido['precio']) ?>
    </div>

    <div class="seccion">
        <div class="seccion-titulo">MODALIDAD Y PAGO:</div>
        <div class="seccion-contenido">
            <strong>Modalidad:</strong> <?= htmlspecialchars($pedido['modalidad']) ?><br>
            <strong>Forma de pago:</strong> <?= htmlspecialchars($pedido['forma_pago']) ?>
        </div>
    </div>

    <!-- ⭐ NUEVO: OBSERVACIONES GENERALES -->
    <?php if (!empty($pedido['observaciones'])): ?>
        <?php
        $obs_limpia = $pedido['observaciones'];
        $obs_limpia = preg_replace('/===\s*SABORES PERSONALIZADOS\s*===.*?(?=\n---|$)/s', '', $obs_limpia);
        $obs_limpia = preg_replace('/---\s*Info del Sistema\s*---.*$/s', '', $obs_limpia);
        $obs_limpia = preg_replace('/Pedido Express - Empleado ID:.*$/m', '', $obs_limpia);
        $obs_limpia = preg_replace('/Fecha\/Hora:.*$/m', '', $obs_limpia);
        $obs_limpia = preg_replace('/🔗\s*PEDIDO COMBINADO.*$/m', '', $obs_limpia);
        $obs_limpia = preg_replace('/^Turno:.*$/m', '', $obs_limpia);
        $obs_limpia = trim(preg_replace('/\n\s*\n+/', "\n", $obs_limpia));
        
        if (!empty($obs_limpia)):
        ?>
            <div class="seccion" style="background: #fffbea; border-color: #ffb700;">
                <div class="seccion-titulo">📝 OBSERVACIONES:</div>
                <div class="seccion-contenido" style="font-size: 10px; font-weight: bold;">
                    <?= nl2br(htmlspecialchars($obs_limpia)) ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <div class="footer">
        <p><strong>Pedido:</strong> #<?= $pedido_id ?></p>
        <p><strong>Fecha:</strong> <?= date('d/m/Y H:i', strtotime($pedido['fecha_pedido'])) ?></p>
        <?php if ($minutos_transcurridos > 0): ?>
            <p><strong>Tiempo:</strong> <?= $minutos_transcurridos ?> minutos</p>
        <?php endif; ?>
        <p style="margin-top: 6px;">¡Gracias por elegirnos!</p>
    </div>

    <script>
        function imprimirYCerrar() {
            const controles = document.querySelector('.no-print');
            if (controles) {
                controles.style.display = 'none';
            }
            
            window.print();
            
            setTimeout(() => {
                if (confirm('¿Se imprimió correctamente?')) {
                    window.close();
                }
            }, 1000);
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                imprimirYCerrar();
            } else if (e.key === 'Escape') {
                window.close();
            }
        });

        window.focus();
    </script>

</body>
</html>