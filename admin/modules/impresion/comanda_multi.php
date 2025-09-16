<?php
// admin/modules/impresion/comanda_multi.php
require_once '../../config.php';
requireLogin();

$pedido_id = isset($_GET['pedido']) ? (int)$_GET['pedido'] : 0;
$ubicacion = isset($_GET['ubicacion']) ? $_GET['ubicacion'] : null;

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

// Determinar ubicación si no se especificó
if (!$ubicacion) {
    $ubicacion = $pedido['ubicacion'];
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

// Función para generar el contenido según la ubicación
function generarComandaPorUbicacion($pedido, $nombre_completo, $es_cliente_fijo, $urgencia, $minutos_transcurridos, $ubicacion) {
    if ($ubicacion === 'Local 1') {
        return generarComandaLocalModerna($pedido, $nombre_completo, $es_cliente_fijo, $urgencia, $minutos_transcurridos);
    } else {
        return generarComandaFabricaClassica($pedido, $nombre_completo, $es_cliente_fijo, $urgencia, $minutos_transcurridos);
    }
}

// Comanda para Local 1 (impresora moderna con ticket)
function generarComandaLocalModerna($pedido, $nombre_completo, $es_cliente_fijo, $urgencia, $minutos_transcurridos) {
    ob_start();
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <title>Comanda Local 1 #<?= $pedido['id'] ?></title>
        <style>
            @page { size: 80mm auto; margin: 0; }
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
            .header {
                text-align: center;
                border-bottom: 2px solid #000;
                padding-bottom: 5px;
                margin-bottom: 8px;
            }
            .header h1 {
                font-size: 16px;
                font-weight: bold;
                margin: 0;
                letter-spacing: 2px;
            }
            .local-badge {
                background: #000;
                color: white;
                padding: 2px 6px;
                font-size: 10px;
                font-weight: bold;
                margin: 5px 0;
            }
            .pedido-numero {
                font-size: 14px;
                font-weight: bold;
                text-align: center;
                margin: 8px 0;
                padding: 4px;
                border: 2px solid #000;
                background: #f0f0f0;
            }
            .urgencia {
                background: #000;
                color: white;
                text-align: center;
                padding: 3px;
                font-weight: bold;
                margin: 5px 0;
                font-size: 12px;
            }
            .seccion {
                margin: 8px 0;
                padding: 4px 0;
            }
            .seccion-titulo {
                font-weight: bold;
                font-size: 11px;
                margin-bottom: 3px;
            }
            .producto-principal {
                background: #f8f8f8;
                padding: 6px;
                margin: 8px 0;
                border: 2px solid #000;
                text-align: center;
            }
            .separador {
                text-align: center;
                font-weight: bold;
                margin: 8px 0;
                letter-spacing: 1px;
            }
            .cliente-fijo {
                font-size: 9px;
                background: #000;
                color: white;
                padding: 2px 4px;
                margin-left: 5px;
            }
            .footer {
                border-top: 2px solid #000;
                padding-top: 8px;
                margin-top: 10px;
                text-align: center;
                font-size: 9px;
            }
            .delivery-box {
                border: 3px solid #ff0000;
                padding: 5px;
                margin: 5px 0;
                background: #ffe6e6;
                font-weight: bold;
            }
            .ticket-section {
                margin-top: 15px;
                border-top: 3px dashed #000;
                padding-top: 10px;
            }
        </style>
    </head>
    <body>
        <!-- COMANDA PRINCIPAL -->
        <div class="header">
            <h1>SANTA CATALINA</h1>
            <div class="local-badge">🏪 LOCAL 1</div>
            <p style="margin: 2px 0; font-size: 10px;">Tel: 11 5981-3546</p>
            <p style="margin: 2px 0; font-size: 10px;">Camino Gral. Belgrano 7241</p>
        </div>

        <div class="pedido-numero">
            COMANDA #<?= $pedido['id'] ?>
            <?php if ($urgencia): ?>
                <div class="urgencia"><?= $urgencia ?></div>
            <?php endif; ?>
        </div>

        <div class="seccion">
            <div class="seccion-titulo">👤 CLIENTE:</div>
            <div style="font-size: 12px; font-weight: bold;">
                <?= htmlspecialchars($nombre_completo) ?>
                <?php if ($es_cliente_fijo): ?>
                    <span class="cliente-fijo">FIJO</span>
                <?php endif; ?>
            </div>
        </div>

        <div class="seccion">
            <div class="seccion-titulo">📞 TELÉFONO:</div>
            <div style="font-size: 12px; font-weight: bold;">
                <?= htmlspecialchars($pedido['telefono']) ?>
            </div>
        </div>

        <?php if ($pedido['modalidad'] === 'Delivery'): ?>
            <div class="delivery-box">
                <div class="seccion-titulo">🚚 DELIVERY - ATENCIÓN:</div>
                <div style="font-size: 11px;">
                    <?= htmlspecialchars($pedido['direccion'] ?: 'SIN DIRECCIÓN - CONFIRMAR CON CLIENTE') ?>
                </div>
            </div>
        <?php else: ?>
            <div class="seccion">
                <div class="seccion-titulo">🏪 MODALIDAD:</div>
                <div style="font-weight: bold; color: blue;">RETIRA EN LOCAL</div>
            </div>
        <?php endif; ?>

        <div class="separador">═══════════════════════════════════════</div>

        <div class="producto-principal">
            <div style="font-weight: bold; font-size: 13px; margin-bottom: 4px;">
                <?= htmlspecialchars($pedido['producto']) ?>
            </div>
            <div style="font-size: 12px; margin: 2px 0;">
                📦 CANTIDAD: <?= $pedido['cantidad'] ?> unidades
            </div>
            <div style="font-size: 12px; margin: 2px 0;">
                💰 PRECIO: <?= formatPrice($pedido['precio']) ?>
            </div>
            <div style="font-size: 11px; margin: 2px 0;">
                💳 PAGO: <?= htmlspecialchars($pedido['forma_pago']) ?>
            </div>
        </div>

        <?php if ($pedido['observaciones']): ?>
            <div class="separador">───────────────────────────────────────</div>
            <div class="seccion">
                <div class="seccion-titulo">📝 OBSERVACIONES:</div>
                <div style="font-weight: bold; background: #ffffcc; padding: 3px;">
                    <?= htmlspecialchars($pedido['observaciones']) ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($pedido['fecha_entrega'] || $pedido['hora_entrega'] || $pedido['notas_horario']): ?>
            <div class="separador">───────────────────────────────────────</div>
            <div class="seccion">
                <div class="seccion-titulo">⏰ HORARIO DE ENTREGA:</div>
                <div style="background: #fff3cd; padding: 3px; font-weight: bold;">
                    <?php if ($pedido['fecha_entrega']): ?>
                        📅 Fecha: <?= date('d/m/Y', strtotime($pedido['fecha_entrega'])) ?><br>
                    <?php endif; ?>
                    <?php if ($pedido['hora_entrega']): ?>
                        🕐 Hora: <?= substr($pedido['hora_entrega'], 0, 5) ?><br>
                    <?php endif; ?>
                    <?php if ($pedido['notas_horario']): ?>
                        📌 Notas: <?= htmlspecialchars($pedido['notas_horario']) ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="footer">
            <div class="separador">═══════════════════════════════════════</div>
            <p><strong>Pedido tomado:</strong> <?= date('d/m/Y H:i', strtotime($pedido['created_at'])) ?></p>
            <p><strong>Por:</strong> <?= htmlspecialchars($_SESSION['admin_name'] ?? 'Sistema') ?></p>
            <p><strong>Estado:</strong> <?= $pedido['estado'] ?></p>
            <?php if ($minutos_transcurridos > 0): ?>
                <p><strong>Tiempo:</strong> Hace <?= $minutos_transcurridos ?> minutos</p>
            <?php endif; ?>
            <div class="separador">═══════════════════════════════════════</div>
            <p style="font-size: 8px; margin-top: 5px;">
                🏪 LOCAL 1 - Sistema Santa Catalina v2.0<br>
                Comanda generada automáticamente
            </p>
        </div>

        <!-- SECCIÓN DE TICKET PARA EL CLIENTE -->
        <div class="ticket-section">
            <div class="header">
                <h2 style="font-size: 14px; margin: 0;">SANTA CATALINA</h2>
                <div style="font-size: 10px;">🏪 Local 1 - Ticket Cliente</div>
            </div>
            
            <div style="margin: 10px 0; text-align: center;">
                <div style="font-size: 16px; font-weight: bold; border: 1px solid #000; padding: 5px;">
                    PEDIDO #<?= $pedido['id'] ?>
                </div>
            </div>
            
            <div style="margin: 8px 0;">
                <strong>Cliente:</strong> <?= htmlspecialchars($nombre_completo) ?><br>
                <strong>Teléfono:</strong> <?= htmlspecialchars($pedido['telefono']) ?><br>
                <strong>Modalidad:</strong> <?= $pedido['modalidad'] ?>
            </div>
            
            <div style="margin: 8px 0; background: #f0f0f0; padding: 4px;">
                <strong><?= htmlspecialchars($pedido['producto']) ?></strong><br>
                Cantidad: <?= $pedido['cantidad'] ?> | <?= formatPrice($pedido['precio']) ?>
            </div>
            
            <div style="margin: 8px 0; text-align: center; font-size: 10px;">
                <p><strong>Fecha:</strong> <?= date('d/m/Y H:i', strtotime($pedido['created_at'])) ?></p>
                <p>Estado: <strong><?= $pedido['estado'] ?></strong></p>
            </div>
            
            <div style="text-align: center; border-top: 1px solid #000; padding-top: 5px; font-size: 9px;">
                <p>¡Gracias por elegirnos!</p>
                <p>Tel: 11 5981-3546</p>
            </div>
        </div>
    </body>
    </html>
    <?php
    return ob_get_clean();
}

// Comanda para Fábrica (3nstar clásica 80mm)
function generarComandaFabricaClassica($pedido, $nombre_completo, $es_cliente_fijo, $urgencia, $minutos_transcurridos) {
    ob_start();
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <title>Comanda Fábrica #<?= $pedido['id'] ?></title>
        <style>
            @page { size: 80mm auto; margin: 0; }
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
            .fabrica-badge {
                background: #000;
                color: white;
                padding: 1px 4px;
                font-size: 9px;
                font-weight: bold;
                margin: 3px 0;
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
            .delivery-importante {
                border: 2px solid #000;
                padding: 3px;
                margin: 5px 0;
                font-weight: bold;
                background: #f8f8f8;
            }
        </style>
    </head>
    <body>
        <div class="comanda-header">
            <h1>SANTA CATALINA</h1>
            <div class="fabrica-badge">🏭 FÁBRICA</div>
            <p style="margin: 2px 0; font-size: 9px;">Tel: 11 5981-3546</p>
            <p style="margin: 2px 0; font-size: 9px;">Camino Gral. Belgrano 7241</p>
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
            <div class="delivery-importante">
                <div class="seccion-titulo">🚚 DELIVERY - ENVIAR A:</div>
                <div class="seccion-contenido">
                    <?= htmlspecialchars($pedido['direccion'] ?: '*** SIN DIRECCION - CONFIRMAR ***') ?>
                </div>
            </div>
        <?php else: ?>
            <div class="seccion">
                <div class="seccion-titulo">MODALIDAD:</div>
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
                <div class="seccion-titulo">HORARIO ENTREGA:</div>
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
                🏭 FÁBRICA - Sistema Santa Catalina v2.0<br>
                Comanda generada para 3nstar RPT006S
            </p>
        </div>
    </body>
    </html>
    <?php
    return ob_get_clean();
}

// Generar el contenido según la ubicación
echo generarComandaPorUbicacion($pedido, $nombre_completo, $es_cliente_fijo, $urgencia, $minutos_transcurridos, $ubicacion);
?>

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
console.log('🖨️ Módulo de Impresión Multi-Ubicación');
console.log('Pedido ID:', <?= $pedido['id'] ?>);
console.log('Ubicación:', '<?= $ubicacion ?>');
console.log('Impresora:', '<?= $ubicacion === "Local 1" ? "Moderna con ticket" : "3nstar RPT006S 80mm" ?>');
</script>