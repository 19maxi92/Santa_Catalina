<?php
// admin/modules/impresion/comanda.php
require_once '../../config.php';
requireLogin();

$pedido_id = isset($_GET['pedido']) ? (int)$_GET['pedido'] : 0;

if (!$pedido_id) {
    die('ID de pedido requerido');
}

$pdo = getConnection();

// Obtener datos completos del pedido
$stmt = $pdo->prepare("
    SELECT p.*, cf.nombre as cliente_fijo_nombre, cf.apellido as cliente_fijo_apellido 
    FROM pedidos p 
    LEFT JOIN clientes_fijos cf ON p.cliente_fijo_id = cf.id 
    WHERE p.id = ?
");
$stmt->execute([$pedido_id]);
$pedido = $stmt->fetch();

if (!$pedido) {
    die('Pedido no encontrado');
}

// Calcular urgencia
$minutos_transcurridos = round((time() - strtotime($pedido['created_at'])) / 60);
$urgencia = '';
$urgencia_class = '';

if ($minutos_transcurridos > 60) {
    $urgencia = '🚨 URGENTE';
    $urgencia_class = 'urgente';
} elseif ($minutos_transcurridos > 30) {
    $urgencia = '⚠️ PRIORIDAD';
    $urgencia_class = 'prioridad';
}

// Determinar si es cliente fijo
$es_cliente_fijo = !empty($pedido['cliente_fijo_nombre']);
$nombre_completo = $es_cliente_fijo 
    ? $pedido['cliente_fijo_nombre'] . ' ' . $pedido['cliente_fijo_apellido']
    : $pedido['nombre'] . ' ' . $pedido['apellido'];

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Comanda #<?= $pedido['id'] ?> - Santa Catalina</title>
    <style>
        /* Configuración para impresora térmica 80mm */
        @page {
            size: 80mm auto;
            margin: 0;
        }
        
        body {
            font-family: 'Courier New', monospace;
            font-size: 10px;
            line-height: 1.3;
            width: 80mm;
            margin: 0;
            padding: 2mm;
            background: white;
            color: black;
        }
        
        .comanda-header {
            text-align: center;
            border-bottom: 1px solid #000;
            padding-bottom: 5px;
            margin-bottom: 8px;
        }
        
        .comanda-header h1 {
            font-size: 14px;
            font-weight: bold;
            margin: 0;
            letter-spacing: 1px;
        }
        
        .comanda-header p {
            margin: 2px 0;
            font-size: 9px;
        }
        
        .pedido-numero {
            font-size: 12px;
            font-weight: bold;
            text-align: center;
            margin: 8px 0;
            padding: 3px;
            border: 1px solid #000;
        }
        
        .seccion {
            margin: 6px 0;
            padding: 3px 0;
        }
        
        .seccion-titulo {
            font-weight: bold;
            font-size: 10px;
            margin-bottom: 2px;
        }
        
        .seccion-contenido {
            font-size: 9px;
            margin-left: 2px;
        }
        
        .producto-principal {
            background: #f0f0f0;
            padding: 4px;
            margin: 6px 0;
            border: 1px solid #000;
            text-align: center;
        }
        
        .separador {
            text-align: center;
            font-weight: bold;
            margin: 6px 0;
            letter-spacing: 1px;
        }
        
        .urgencia {
            background: #000;
            color: white;
            text-align: center;
            padding: 3px;
            font-weight: bold;
            margin: 5px 0;
        }
        
        .footer {
            border-top: 1px solid #000;
            padding-top: 5px;
            margin-top: 8px;
            text-align: center;
            font-size: 8px;
        }
        
        .cliente-fijo {
            font-size: 8px;
            background: #000;
            color: white;
            padding: 1px 3px;
            margin-left: 5px;
        }
        
        /* Ocultar en pantalla */
        .no-print {
            display: block;
        }
        
        /* Solo mostrar en impresión */
        @media print {
            .no-print {
                display: none !important;
            }
            
            body {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
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
        }
    </style>
</head>
<body>
    <!-- Botones de control (solo en pantalla) -->
    <div class="no-print" style="text-align: center; margin-bottom: 15px; padding: 10px; background: #f8f9fa; border-radius: 5px;">
        <h3 style="margin: 0 0 10px 0;">🖨️ Comanda Lista para Imprimir</h3>
        <button onclick="imprimirYCerrar()" style="background: #28a745; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; margin-right: 10px; font-size: 14px;">
            🖨️ IMPRIMIR COMANDA
        </button>
        <button onclick="window.close()" style="background: #6c757d; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; font-size: 14px;">
            ❌ Cancelar
        </button>
        <div style="margin-top: 10px; font-size: 12px; color: #666;">
            <strong>Impresora:</strong> 3nstar RPT006S 80mm | <strong>Pedido:</strong> #<?= $pedido['id'] ?>
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

    <?php if ($pedido['modalidad'] === 'Delivery'): ?>
        <div class="seccion">
            <div class="seccion-titulo">🚚 DELIVERY:</div>
            <div class="seccion-contenido">
                <?= htmlspecialchars($pedido['direccion'] ?: 'SIN DIRECCION - CONFIRMAR') ?>
            </div>
        </div>
    <?php else: ?>
        <div class="seccion">
            <div class="seccion-titulo">🏪 MODALIDAD:</div>
            <div class="seccion-contenido">RETIRA EN LOCAL</div>
        </div>
    <?php endif; ?>

    <div class="separador">================================</div>

    <div class="producto-principal">
        <div style="font-weight: bold; font-size: 11px; margin-bottom: 3px;">
            <?= htmlspecialchars($pedido['producto']) ?>
        </div>
        <div style="font-size: 10px;">
            CANTIDAD: <?= $pedido['cantidad'] ?> unidades
        </div>
        <div style="font-size: 10px; margin-top: 2px;">
            PRECIO: <?= formatPrice($pedido['precio']) ?>
        </div>
    </div>

    <div class="seccion">
        <div class="seccion-titulo">FORMA DE PAGO:</div>
        <div class="seccion-contenido"><?= htmlspecialchars($pedido['forma_pago']) ?></div>
    </div>

    <?php if ($pedido['observaciones']): ?>
        <div class="separador">--------------------------------</div>
        <div class="seccion">
            <div class="seccion-titulo">OBSERVACIONES:</div>
            <div class="seccion-contenido"><?= htmlspecialchars($pedido['observaciones']) ?></div>
        </div>
    <?php endif; ?>

    <?php if ($pedido['fecha_entrega'] || $pedido['hora_entrega'] || $pedido['notas_horario']): ?>
        <div class="separador">--------------------------------</div>
        <div class="seccion">
            <div class="seccion-titulo">⏰ HORARIO DE ENTREGA:</div>
            <div class="seccion-contenido">
                <?php if ($pedido['fecha_entrega']): ?>
                    Fecha: <?= date('d/m/Y', strtotime($pedido['fecha_entrega'])) ?><br>
                <?php endif; ?>
                <?php if ($pedido['hora_entrega']): ?>
                    Hora: <?= substr($pedido['hora_entrega'], 0, 5) ?><br>
                <?php endif; ?>
                <?php if ($pedido['notas_horario']): ?>
                    Notas: <?= htmlspecialchars($pedido['notas_horario']) ?>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="footer">
        <div class="separador">================================</div>
        <p>Pedido tomado: <?= date('d/m/Y H:i', strtotime($pedido['created_at'])) ?></p>
        <p>Por: <?= htmlspecialchars($_SESSION['admin_name'] ?? 'Sistema') ?></p>
        <p>Estado: <?= $pedido['estado'] ?></p>
        <?php if ($minutos_transcurridos > 0): ?>
            <p>Hace: <?= $minutos_transcurridos ?> minutos</p>
        <?php endif; ?>
        <div class="separador">================================</div>
        <p style="font-size: 7px; margin-top: 5px;">
            Sistema Santa Catalina v1.0<br>
            Comanda generada automáticamente
        </p>
    </div>

    <script>
        function imprimirYCerrar() {
            // Configurar para impresión
            window.focus();
            
            // Imprimir
            window.print();
            
            // Marcar como impreso en el sistema
            marcarComoImpreso();
            
            // Cerrar ventana después de un delay
            setTimeout(() => {
                window.close();
            }, 1000);
        }
        
        function marcarComoImpreso() {
            fetch('../pedidos/ver_pedidos.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'accion=marcar_impreso&id=<?= $pedido['id'] ?>'
            }).then(response => {
                console.log('Pedido marcado como impreso');
            }).catch(error => {
                console.error('Error marcando como impreso:', error);
            });
        }
        
        // Auto-imprimir si se pasa el parámetro
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('auto') === '1') {
            setTimeout(imprimirYCerrar, 500);
        }
        
        // Manejar eventos de teclado
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                imprimirYCerrar();
            } else if (e.key === 'Escape') {
                window.close();
            }
        });
        
        // Información de debug
        console.log('🖨️ Módulo de Impresión de Comandas');
        console.log('Pedido ID:', <?= $pedido['id'] ?>);
        console.log('Impresora objetivo: 3nstar RPT006S 80mm');
        console.log('Urgencia: <?= $urgencia ?: "Normal" ?>');
        console.log('Minutos transcurridos:', <?= $minutos_transcurridos ?>);
    </script>
</body>
</html>