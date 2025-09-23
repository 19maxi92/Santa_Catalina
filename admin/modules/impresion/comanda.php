<?php
// admin/modules/impresion/comanda.php - VERSIÓN ACTUALIZADA CON FECHA DE ENTREGA
require_once '../../config.php';
requireLogin();

// Validar parámetros
$pedido_id = (int)($_GET['pedido'] ?? 0);
$es_admin = isset($_GET['admin']) && $_GET['admin'] === '1';
$auto_print = isset($_GET['auto']) && $_GET['auto'] === '1';

if ($pedido_id <= 0) {
    die('Error: ID de pedido no válido');
}

$pdo = getConnection();

// CONSULTA MEJORADA - Incluye información de clientes fijos y detalles del pedido
$stmt = $pdo->prepare("
    SELECT p.*,
           cf.nombre as cliente_fijo_nombre,
           cf.apellido as cliente_fijo_apellido,
           cf.telefono as cliente_fijo_telefono,
           cf.direccion as cliente_fijo_direccion
    FROM pedidos p
    LEFT JOIN clientes_fijos cf ON p.cliente_fijo_id = cf.id
    WHERE p.id = ?
");

$stmt->execute([$pedido_id]);
$pedido = $stmt->fetch();

if (!$pedido) {
    die('Error: Pedido no encontrado');
}

// Determinar datos del cliente
$es_cliente_fijo = !empty($pedido['cliente_fijo_id']);
$nombre_completo = $es_cliente_fijo 
    ? $pedido['cliente_fijo_nombre'] . ' ' . $pedido['cliente_fijo_apellido']
    : $pedido['nombre'] . ' ' . $pedido['apellido'];

// Calcular tiempo transcurrido
$fecha_pedido = new DateTime($pedido['fecha_pedido']);
$ahora = new DateTime();
$diferencia = $fecha_pedido->diff($ahora);
$minutos_transcurridos = ($diferencia->h * 60) + $diferencia->i;

// Determinar urgencia
$urgencia = '';
if ($minutos_transcurridos > 120) {
    $urgencia = '🔥 URGENTE - ' . round($minutos_transcurridos/60, 1) . 'h';
} elseif ($minutos_transcurridos > 60) {
    $urgencia = '⚠️ PRIORIDAD - ' . round($minutos_transcurridos/60, 1) . 'h';
}

// ANALIZAR EL PRODUCTO PARA MOSTRAR CORRECTAMENTE
$producto_display = $pedido['producto'];
$sabores_info = '';

// Si es personalizado, extraer información de las observaciones
if (strpos($pedido['producto'], 'Personalizado') !== false) {
    // Buscar información específica en las observaciones
    $observaciones = $pedido['observaciones'];
    
    // Detectar J y Q
    if (preg_match('/J\s*y\s*Q|J\s*\+\s*Q|JyQ|J&Q/i', $observaciones)) {
        $planchas = ceil($pedido['cantidad'] / 8);
        $producto_display = $pedido['cantidad'] . ' sándwiches J y Q (' . $planchas . ' plancha' . ($planchas > 1 ? 's' : '') . ')';
    }
    // Buscar otros sabores
    elseif (preg_match('/Sabores:\s*(.+)(?:\n|$)/i', $observaciones, $matches)) {
        $sabores_info = trim($matches[1]);
        $planchas = ceil($pedido['cantidad'] / 8);
        $producto_display = $pedido['cantidad'] . ' sándwiches personalizados (' . $planchas . ' plancha' . ($planchas > 1 ? 's' : '') . ')';
    }
    // Si no hay información específica, usar el producto original
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comanda #<?= $pedido['id'] ?> - Santa Catalina</title>
    <style>
        @page { 
            size: 80mm auto; 
            margin: 0; 
        }
        
        body {
            font-family: 'Courier New', monospace;
            font-size: 11px;
            line-height: 1.3;
            width: 80mm;
            margin: 0;
            padding: 3mm;
            background: white;
            color: black;
        }
        
        .comanda-header {
            text-align: center;
            border-bottom: 2px solid #000;
            padding-bottom: 8px;
            margin-bottom: 10px;
        }
        
        .comanda-header h1 {
            font-size: 16px;
            font-weight: bold;
            margin: 0 0 2px 0;
            letter-spacing: 1px;
        }
        
        .comanda-header p {
            margin: 1px 0;
            font-size: 9px;
        }
        
        .pedido-numero {
            text-align: center;
            font-size: 14px;
            font-weight: bold;
            background: #000;
            color: #fff;
            padding: 4px;
            margin: 8px 0;
        }
        
        .urgencia {
            text-align: center;
            font-size: 10px;
            font-weight: bold;
            background: #ff4444;
            color: #fff;
            padding: 2px;
            margin: 2px 0;
        }
        
        .seccion {
            margin: 6px 0;
            border-bottom: 1px dashed #ccc;
            padding-bottom: 4px;
        }
        
        .seccion-titulo {
            font-weight: bold;
            font-size: 10px;
            margin-bottom: 2px;
        }
        
        .seccion-contenido {
            font-size: 11px;
            padding-left: 4px;
        }
        
        .producto-principal {
            background: #f8f8f8;
            padding: 6px;
            margin: 8px 0;
            border: 1px solid #000;
            text-align: center;
            font-weight: bold;
            font-size: 12px;
        }
        
        .sabores {
            background: #e8f4f8;
            padding: 4px;
            margin: 4px 0;
            border-left: 3px solid #007acc;
            font-size: 10px;
        }
        
        .precio {
            text-align: center;
            font-size: 14px;
            font-weight: bold;
            background: #f0f0f0;
            padding: 6px;
            margin: 8px 0;
            border: 1px solid #000;
        }
        
        .cliente-fijo {
            background: #d4edda;
            color: #155724;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 9px;
            font-weight: bold;
            margin-left: 4px;
        }
        
        .footer {
            text-align: center;
            margin-top: 10px;
            padding-top: 6px;
            border-top: 1px dashed #000;
            font-size: 9px;
        }
        
        /* Estilos para vista previa en pantalla */
        @media screen {
            body {
                width: 80mm;
                margin: 20px auto;
                border: 2px solid #333;
                padding: 10px;
                box-shadow: 0 0 10px rgba(0,0,0,0.3);
            }
            
            .no-print {
                display: block !important;
            }
        }
        
        @media print {
            .no-print {
                display: none !important;
            }
            
            body {
                margin: 0;
                padding: 3mm;
                border: none;
                box-shadow: none;
            }
        }
    </style>
</head>
<body>
    <!-- Botones de control (solo en pantalla) -->
    <div class="no-print" style="text-align: center; margin-bottom: 15px; padding: 10px; background: #f8f9fa; border-radius: 5px;">
        <h3 style="margin: 0 0 10px 0;">🖨️ Comanda Lista para Imprimir</h3>
        
        <?php if ($es_admin): ?>
            <div style="background: #e3f2fd; padding: 8px; border-radius: 4px; margin-bottom: 10px; font-size: 12px;">
                <i class="fas fa-user-shield" style="color: #1976d2;"></i>
                <strong>Modo Administrador</strong> - Con fecha de entrega opcional
            </div>
        <?php endif; ?>
        
        <button onclick="imprimirYCerrar()" style="background: #28a745; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; margin-right: 10px; font-size: 14px;">
            🖨️ IMPRIMIR COMANDA
        </button>
        <button onclick="window.close()" style="background: #6c757d; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; font-size: 14px;">
            ❌ Cancelar
        </button>
        
        <div style="margin-top: 10px; font-size: 12px; color: #666;">
            <strong>Impresora:</strong> POS80-CX 80mm | <strong>Pedido:</strong> #<?= $pedido['id'] ?>
            <?php if ($es_admin): ?>
                | <strong>Origen:</strong> Admin Panel
            <?php endif; ?>
        </div>
    </div>

    <!-- INICIO DE LA COMANDA -->
    <div class="comanda-header">
        <h1>SANTA CATALINA</h1>
        <p>Sándwiches de Miga</p>
        <p>Tel: 11 5981-3546</p>
        <p>Camino Gral. Belgrano 7241</p>
    </div>

    <div class="pedido-numero">
        PEDIDO #<?= $pedido['id'] ?>
        <?php if ($urgencia): ?>
            <div class="urgencia"><?= $urgencia ?></div>
        <?php endif; ?>
    </div>

    <div class="seccion">
        <div class="seccion-titulo">CLIENTE:</div>
        <div class="seccion-contenido">
            <?= htmlspecialchars($nombre_completo) ?>
            <?php if ($es_cliente_fijo): ?>
                <span class="cliente-fijo">FIJO</span>
            <?php endif; ?>
        </div>
    </div>

    <div class="seccion">
        <div class="seccion-titulo">TELEFONO:</div>
        <div class="seccion-contenido"><?= htmlspecialchars($pedido['telefono']) ?></div>
    </div>

    <div class="seccion">
        <div class="seccion-titulo">MODALIDAD:</div>
        <div class="seccion-contenido">
            <?= htmlspecialchars($pedido['modalidad']) ?>
            <?php if ($pedido['modalidad'] === 'Delivery' && $pedido['direccion']): ?>
                <br><strong>Dirección:</strong> <?= htmlspecialchars($pedido['direccion']) ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- PRODUCTO PRINCIPAL -->
    <div class="producto-principal">
        <?= htmlspecialchars($producto_display) ?>
    </div>

    <?php if ($sabores_info): ?>
        <div class="sabores">
            <strong>SABORES:</strong> <?= htmlspecialchars($sabores_info) ?>
        </div>
    <?php endif; ?>

    <!-- PRECIO -->
    <div class="precio">
        TOTAL: $<?= number_format($pedido['precio'], 0, ',', '.') ?>
        <div style="font-size: 10px; font-weight: normal; margin-top: 2px;">
            <?= htmlspecialchars($pedido['forma_pago']) ?>
        </div>
    </div>

    <!-- FECHA DE ENTREGA (SOLO PARA ADMIN) -->
    <?php if ($es_admin && ($pedido['fecha_entrega'] || $pedido['hora_entrega'])): ?>
        <div class="seccion">
            <div class="seccion-titulo">📅 FECHA DE ENTREGA:</div>
            <div class="seccion-contenido">
                <?php if ($pedido['fecha_entrega']): ?>
                    <strong><?= date('d/m/Y', strtotime($pedido['fecha_entrega'])) ?></strong>
                <?php endif; ?>
                <?php if ($pedido['hora_entrega']): ?>
                    a las <strong><?= date('H:i', strtotime($pedido['hora_entrega'])) ?>hs</strong>
                <?php endif; ?>
                <?php if ($pedido['notas_horario']): ?>
                    <br><small><?= htmlspecialchars($pedido['notas_horario']) ?></small>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- OBSERVACIONES -->
    <?php if ($pedido['observaciones'] && !$sabores_info): ?>
        <div class="seccion">
            <div class="seccion-titulo">OBSERVACIONES:</div>
            <div class="seccion-contenido">
                <?= nl2br(htmlspecialchars($pedido['observaciones'])) ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- FOOTER -->
    <div class="footer">
        <p><strong>Hora del pedido:</strong> <?= date('d/m/Y H:i', strtotime($pedido['fecha_pedido'])) ?></p>
        <?php if ($minutos_transcurridos > 0): ?>
            <p><strong>Tiempo transcurrido:</strong> <?= $minutos_transcurridos ?> minutos</p>
        <?php endif; ?>
        <p style="margin-top: 6px;">¡Gracias por elegirnos!</p>
        <?php if ($es_admin): ?>
            <p style="font-size: 8px; color: #666; margin-top: 4px;">Impreso desde Admin Panel</p>
        <?php endif; ?>
    </div>

    <script>
        function imprimirYCerrar() {
            // Ocultar botones de control
            const controles = document.querySelector('.no-print');
            if (controles) {
                controles.style.display = 'none';
            }
            
            // Imprimir
            window.print();
            
            // Mostrar mensaje de confirmación
            setTimeout(() => {
                if (confirm('¿Se imprimió correctamente la comanda?')) {
                    // Marcar como impreso si viene del admin
                    <?php if ($es_admin): ?>
                    if (window.opener && typeof window.opener.marcarComoImpreso === 'function') {
                        window.opener.marcarComoImpreso(<?= $pedido_id ?>);
                    }
                    <?php endif; ?>
                    
                    window.close();
                } else {
                    // Mostrar controles nuevamente si no se imprimió
                    if (controles) {
                        controles.style.display = 'block';
                    }
                }
            }, 1000);
        }

        // Auto-imprimir si se especifica
        <?php if ($auto_print): ?>
        setTimeout(() => {
            imprimirYCerrar();
        }, 800);
        <?php endif; ?>

        // Atajos de teclado
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                imprimirYCerrar();
            } else if (e.key === 'Escape') {
                window.close();
            }
        });

        // Log de información
        console.log('🖨️ Comanda cargada correctamente');
        console.log('📋 Pedido #<?= $pedido_id ?>');
        console.log('👤 Cliente: <?= addslashes($nombre_completo) ?>');
        console.log('🏪 Modalidad: <?= $pedido['modalidad'] ?>');
        <?php if ($es_admin): ?>
        console.log('👨‍💼 Origen: Admin Panel');
        <?php endif; ?>
        console.log('⏱️ Tiempo transcurrido: <?= $minutos_transcurridos ?> minutos');
    </script>
</body>
</html>