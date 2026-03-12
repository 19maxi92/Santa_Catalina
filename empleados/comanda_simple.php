<?php
// empleados/comanda_simple.php - VERSIÓN FINAL
require_once '../admin/config.php';

$pedido_id = isset($_GET['pedido']) ? (int)$_GET['pedido'] : 0;

if (!$pedido_id) {
    die('ID de pedido requerido');
}

$pdo = getConnection();

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

$es_cliente_fijo = !empty($pedido['cliente_fijo_nombre']);
$nombre_completo = $es_cliente_fijo 
    ? $pedido['cliente_fijo_nombre'] . ' ' . $pedido['cliente_fijo_apellido']
    : $pedido['nombre'] . ' ' . $pedido['apellido'];

// Extraer turno de las observaciones
$turno = 'M'; // Default
if (preg_match('/Turno:\s*([MST]|Mañana|Siesta|Tarde)/i', $pedido['observaciones'], $match)) {
    $turno_text = $match[1];
    if ($turno_text === 'Mañana' || $turno_text === 'M') $turno = 'M';
    elseif ($turno_text === 'Siesta' || $turno_text === 'S') $turno = 'S';
    elseif ($turno_text === 'Tarde' || $turno_text === 'T') $turno = 'T';
}

// Usar fecha_entrega si existe, sino created_at
$fecha_para_mostrar = !empty($pedido['fecha_entrega']) ? $pedido['fecha_entrega'] : $pedido['created_at'];
$fecha_formatted = date('d-M', strtotime($fecha_para_mostrar));
$meses = [
    'Jan' => 'ene', 'Feb' => 'feb', 'Mar' => 'mar', 'Apr' => 'abr',
    'May' => 'may', 'Jun' => 'jun', 'Jul' => 'jul', 'Aug' => 'ago',
    'Sep' => 'sep', 'Oct' => 'oct', 'Nov' => 'nov', 'Dec' => 'dic'
];
foreach ($meses as $eng => $esp) {
    $fecha_formatted = str_replace($eng, $esp, $fecha_formatted);
}

$precio_formatted = '$' . number_format($pedido['precio'], 0, ',', '.');
$es_personalizado = strpos($pedido['producto'], 'Personalizado') !== false;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comanda #<?= $pedido_id ?></title>
    <style>
        @page { size: 90mm auto; margin: 0; }

        body {
            font-family: 'Courier New', monospace;
            font-size: 10px;
            line-height: 1.3;
            width: 340px;
            margin: 0;
            padding: 0;
            background: white;
            color: black;
        }
        
        .controles {
            background: #f0f0f0;
            padding: 10px;
            text-align: center;
            margin-bottom: 10px;
        }
        
        .btn {
            padding: 8px 16px;
            margin: 0 4px;
            border: none;
            border-radius: 4px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
        }
        
        .btn-print { background: #28a745; color: white; }
        .btn-cancel { background: #6c757d; color: white; }
        
        .comanda-container {
            background: white;
            margin: 10px auto;
            padding: 5px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            width: 340px;
        }
        
        .comanda-ticket {
            padding: 5px 8px;
            font-family: 'Courier New', monospace;
        }
        
        .fecha-turno {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 11px;
            font-weight: bold;
            margin-bottom: 6px;
            padding-bottom: 4px;
            border-bottom: 1px solid #000;
        }
        
        .turno-badge {
            font-size: 18px;
            font-weight: 900;
            color: #000;
        }
        
        .cliente-nombre {
            font-size: 16px;
            font-weight: bold;
            text-align: center;
            margin: 8px 0;
            text-transform: uppercase;
        }
        
        .producto-info {
            font-size: 18px;
            font-weight: bold;
            text-align: center;
            margin: 10px 0;
            padding: 8px;
            background: #f5f5f5;
            border: 2px solid #000;
        }
        
        /* SABORES GRANDES PARA PERSONALIZADO */
        .sabores-detalle-grande {
            font-size: 14px;
            line-height: 1.6;
            margin: 8px 0;
            padding: 8px;
            background: #fafafa;
            border: 2px solid #000;
            font-weight: bold;
        }
        
        .total-sandwiches {
            font-size: 16px;
            font-weight: bold;
            text-align: center;
            margin: 8px 0;
            padding: 6px;
            border-top: 2px solid #000;
            border-bottom: 2px solid #000;
        }
        
        .precio-total {
            font-size: 24px;
            font-weight: bold;
            text-align: center;
            margin: 12px 0;
            padding: 8px;
            border: 2px solid #000;
        }
        
        .observaciones-container {
            border-top: 2px solid #000;
            margin: 8px 0;
            padding-top: 8px;
        }
        
        .observaciones-titulo {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 6px;
            text-align: center;
            text-transform: uppercase;
        }

        .observaciones-texto {
            font-size: 20px;
            line-height: 1.6;
            font-weight: 900;
            padding: 10px;
            background: #fff3cd;
            border: 3px solid #000;
            text-transform: uppercase;
        }
        
        .info-admin {
            font-size: 8px;
            text-align: center;
            color: #666;
            margin-top: 10px;
            padding-top: 8px;
            border-top: 1px dashed #999;
        }
        
        @media print {
            body {
                background: white;
                padding: 0;
            }
            
            .controles {
                display: none !important;
            }
            
            .comanda-container {
                box-shadow: none;
                margin: 0;
                padding: 0;
            }
            
            .comanda-ticket {
                padding: 2mm 3mm;
            }
            
            @page {
                size: 90mm auto;
                margin: 0;
            }
        }
    </style>
</head>
<body>

    <div class="controles">
        <div style="margin-bottom: 8px; font-size: 12px; color: #666;">
            <strong>Pedido:</strong> #<?= $pedido_id ?> | <strong>Turno:</strong> <?= $turno ?>
        </div>
        <button onclick="imprimirConQZTray()" class="btn btn-print">
            🖨️ IMPRIMIR
        </button>
        <button onclick="window.close()" class="btn btn-cancel">
            ❌ Cancelar
        </button>
    </div>

    <div class="comanda-container">
        <div class="comanda-ticket">
            
            <!-- FECHA Y TURNO -->
            <div class="fecha-turno">
                <?php if (!empty($pedido['fecha_entrega']) && $pedido['fecha_entrega'] != date('Y-m-d')): ?>
                    <span style="background:#9c27b0;color:white;padding:2px 6px;border-radius:3px;">
                        ENTREGA: <?= $fecha_formatted ?>
                    </span>
                <?php else: ?>
                    <span><?= $fecha_formatted ?></span>
                <?php endif; ?>
                <span class="turno-badge"><?= $turno ?></span>
            </div>
            
            <!-- NOMBRE CLIENTE -->
            <div class="cliente-nombre">
                <?= htmlspecialchars($nombre_completo) ?>
            </div>
            
            <?php if ($es_personalizado): ?>
                <!-- PEDIDO PERSONALIZADO: SABORES GRANDES -->
                <div class="sabores-detalle-grande">
                <?php
                $obs = $pedido['observaciones'];
                $sabores_encontrados = false;

                // Intentar formato con planchas (con o sin saltos de línea)
                if (preg_match('/===\s*SABORES PERSONALIZADOS\s*===[\s\n]*(.*?)(?:---|$)/s', $obs, $matches)) {
                    $sabores_texto = trim($matches[1]);

                    // Buscar formato: • Sabor: X plancha(s) (Y sándwiches)
                    if (preg_match_all('/•\s*([^:]+):\s*(\d+)\s*plancha/i', $sabores_texto, $planchas_matches, PREG_SET_ORDER)) {
                        foreach ($planchas_matches as $match) {
                            $sabor = trim($match[1]);
                            $planchas = (int)$match[2];
                            $sandwiches = $planchas * 8;
                            echo "<strong>{$planchas}pl</strong> {$sabor} ({$sandwiches})<br>";
                            $sabores_encontrados = true;
                        }
                    }

                    // Si no hay formato planchas, buscar formato directo: • Sabor: X (número de sándwiches)
                    if (!$sabores_encontrados) {
                        if (preg_match_all('/•\s*([^:•]+):\s*(\d+)(?!\s*plancha)/i', $sabores_texto, $direct_matches, PREG_SET_ORDER)) {
                            foreach ($direct_matches as $match) {
                                $sabor = trim($match[1]);
                                $sandwiches = (int)$match[2];
                                // Calcular planchas (8 sándwiches = 1 plancha)
                                $planchas = ceil($sandwiches / 8);
                                echo "<strong>{$sandwiches}</strong> {$sabor}<br>";
                                $sabores_encontrados = true;
                            }
                        }
                    }
                }
                ?>
                </div>
                
                <!-- TOTAL SÁNDWICHES -->
                <?php
                preg_match('/x(\d+)/', $pedido['producto'], $match_total);
                $total_sandwiches = $match_total[1] ?? '?';
                ?>
                <div class="total-sandwiches">
                    TOTAL: <?= $total_sandwiches ?> sándwiches
                </div>
                
            <?php else: ?>
                <!-- PEDIDO COMÚN: NOMBRE DEL COMBO -->
                <div class="producto-info">
                    <?= htmlspecialchars($pedido['producto']) ?>
                </div>
            <?php endif; ?>
            
            <!-- PRECIO -->
            <div class="precio-total">
                <?= $precio_formatted ?>
            </div>
            
            <!-- OBSERVACIONES (si existen) -->
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
                    <div class="observaciones-container">
                        <div class="observaciones-titulo">📝 OBSERVACIONES</div>
                        <div class="observaciones-texto">
                            <?= nl2br(htmlspecialchars($obs_limpia)) ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            
            <!-- INFO ADMINISTRATIVA -->
            <div class="info-admin">
                Modalidad: <?= $pedido['modalidad'] ?> | Pago: <?= $pedido['forma_pago'] ?>
                <br>
                <?php if (!empty($pedido['fecha_entrega']) && $pedido['fecha_entrega'] != date('Y-m-d')): ?>
                    <strong>ENTREGA: <?= date('d/m/Y', strtotime($pedido['fecha_entrega'])) ?></strong>
                <?php else: ?>
                    <?= $pedido['fecha_display'] ?? formatDateTime($pedido['created_at'], 'd/m/Y H:i') ?>
                <?php endif; ?>
            </div>
            
        </div>
    </div>

    <!-- QZ Tray para impresión silenciosa sin diálogo del navegador -->
    <script src="https://cdn.jsdelivr.net/npm/qz-tray/qz-tray.js"></script>
    <script>
    // ============================================================
    // CONFIGURACIÓN DE IMPRESORAS
    // Cambiar los valores por el nombre exacto de cada impresora
    // tal como aparece en Windows > Dispositivos e impresoras
    // ============================================================
    const IMPRESORAS = {
        'Local 1': 'IMPRESORA_LOCAL_1',   // ← Poner nombre real de la impresora en Local 1
        'Fábrica':  'IMPRESORA_FABRICA'   // ← Poner nombre real de la impresora en Fábrica
    };

    const UBICACION_PEDIDO  = '<?= addslashes($pedido['ubicacion'] ?? 'Local 1') ?>';
    const IMPRESORA_DESTINO = IMPRESORAS[UBICACION_PEDIDO] || null;

    // ============================================================
    // IMPRESIÓN CLÁSICA (fallback con diálogo del navegador)
    // ============================================================
    function imprimirYCerrar() {
        document.querySelector('.controles').style.display = 'none';
        setTimeout(() => {
            window.print();
            setTimeout(() => window.close(), 500);
        }, 200);
    }

    // ============================================================
    // IMPRESIÓN SILENCIOSA CON QZ TRAY
    // Si QZ Tray no está instalado/corriendo cae a window.print()
    // ============================================================
    async function imprimirConQZTray() {
        if (typeof qz === 'undefined' || !IMPRESORA_DESTINO) {
            console.warn('QZ Tray no disponible o impresora no configurada. Usando window.print().');
            imprimirYCerrar();
            return;
        }
        try {
            // Sin certificado: QZ Tray debe tener habilitado "Allow unsigned"
            qz.security.setCertificatePromise(function(resolve) { resolve(); });
            qz.security.setSignaturePromise(function() {
                return function(resolve) { resolve(); };
            });

            if (!qz.websocket.isActive()) {
                await qz.websocket.connect();
            }

            // Capturar HTML de la comanda (sin los controles)
            document.querySelector('.controles').style.display = 'none';
            const htmlContent = document.documentElement.outerHTML;
            document.querySelector('.controles').style.display = '';

            const config = qz.configs.create(IMPRESORA_DESTINO, {
                colorType: 'blackwhite',
                units: 'mm',
                size: { width: 90, height: null }
            });

            await qz.print(config, [{ type: 'html', format: 'plain', data: htmlContent }]);

            if (qz.websocket.isActive()) await qz.websocket.disconnect();
            setTimeout(() => window.close(), 1500);

        } catch (e) {
            console.error('Error QZ Tray:', e.message || e);
            imprimirYCerrar();
        }
    }

    // ============================================================
    // AUTO-IMPRIMIR cuando se abre con ?auto=1
    // ============================================================
    if (new URLSearchParams(window.location.search).get('auto') === '1') {
        window.addEventListener('load', function() {
            setTimeout(imprimirConQZTray, 400);
        });
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            imprimirConQZTray();
        } else if (e.key === 'Escape') {
            window.close();
        }
    });

    window.focus();
    console.log('🎫 Comanda - Pedido #<?= $pedido_id ?> | Ubicación: ' + UBICACION_PEDIDO + ' | Impresora: ' + (IMPRESORA_DESTINO || 'no configurada'));
    </script>

</body>
</html>