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

// Comanda para Local 1 (impresora POS80-CX)
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
                letter-spacing: 1px;
            }
            .header p {
                font-size: 9px;
                margin: 1px 0;
            }
            .local-badge {
                background: #0066cc;
                color: white;
                padding: 2px 6px;
                font-size: 9px;
                font-weight: bold;
                margin: 3px 0;
                border-radius: 3px;
            }
            .pedido-numero {
                font-size: 14px;
                font-weight: bold;
                text-align: center;
                margin: 8px 0;
                padding: 5px;
                border: 2px solid #000;
                background: #f0f0f0;
            }
            .urgencia {
                background: #ff0000;
                color: white;
                padding: 2px 4px;
                font-size: 10px;
                font-weight: bold;
                text-align: center;
                margin: 3px 0;
            }
            .seccion {
                margin: 5px 0;
                border-bottom: 1px dashed #ccc;
                padding-bottom: 3px;
            }
            .seccion-titulo {
                font-weight: bold;
                font-size: 10px;
                text-transform: uppercase;
                color: #333;
            }
            .seccion-contenido {
                font-size: 11px;
                margin-left: 2px;
                font-weight: bold;
            }
            .producto-principal {
                background: #e6f3ff;
                border: 1px solid #0066cc;
                padding: 5px;
                margin: 8px 0;
                font-weight: bold;
                text-align: center;
            }
            .modalidad {
                text-align: center;
                background: #eeeeee;
                padding: 3px;
                margin: 5px 0;
                font-weight: bold;
                border: 1px solid #999;
            }
            .cliente-fijo {
                background: #00aa00;
                color: white;
                padding: 1px 4px;
                font-size: 8px;
                border-radius: 2px;
                margin-left: 5px;
            }
            .footer {
                text-align: center;
                margin-top: 8px;
                border-top: 1px solid #000;
                padding-top: 5px;
                font-size: 8px;
            }
            .separador {
                text-align: center;
                margin: 5px 0;
                font-weight: bold;
                color: #666;
            }
            .delivery-box {
                background: #fff3cd;
                border: 1px solid #ffc107;
                padding: 4px;
                margin: 5px 0;
            }
            
            /* Ocultar botones al imprimir */
            @media print {
                .no-print { display: none !important; }
                body { width: 80mm; }
            }
            
            /* Vista previa en pantalla */
            @media screen {
                body {
                    width: 80mm;
                    margin: 20px auto;
                    border: 2px solid #0066cc;
                    padding: 10px;
                    box-shadow: 0 0 15px rgba(0,102,204,0.3);
                }
            }
        </style>
    </head>
    <body>
        <!-- Controles de impresión (solo en pantalla) -->
        <div class="no-print" style="text-align: center; margin-bottom: 15px; padding: 10px; background: #e6f3ff; border-radius: 5px; border: 1px solid #0066cc;">
            <h3 style="margin: 0 0 10px 0; color: #0066cc;">🏪 LOCAL 1 - POS80-CX</h3>
            <button onclick="imprimirYCerrar()" 
                    style="background: #0066cc; color: white; border: none; padding: 12px 20px; border-radius: 5px; cursor: pointer; margin-right: 10px; font-size: 14px; font-weight: bold;">
                🖨️ IMPRIMIR COMANDA
            </button>
            <button onclick="window.close()" 
                    style="background: #6c757d; color: white; border: none; padding: 12px 20px; border-radius: 5px; cursor: pointer; font-size: 14px;">
                ❌ Cancelar
            </button>
            <div style="margin-top: 10px; font-size: 12px; color: #666;">
                <strong>Impresora:</strong> POS80-CX (USB) | <strong>Pedido:</strong> #<?= $pedido['id'] ?> | <strong>Local:</strong> 1
            </div>
        </div>

        <!-- === COMANDA LOCAL 1 === -->
        
        <!-- Header -->
        <div class="header">
            <h1>SANTA CATALINA</h1>
            <p>Sándwiches de Miga Artesanales</p>
            <p>Tel: 11 5981-3546</p>
            <p>Camino Gral. Belgrano 7241</p>
            <div class="local-badge">🏪 LOCAL 1</div>
        </div>

        <!-- Número de pedido -->
        <div class="pedido-numero">
            COMANDA #<?= $pedido['id'] ?>
            <?php if ($urgencia): ?>
                <div class="urgencia"><?= $urgencia ?></div>
            <?php endif; ?>
        </div>

        <!-- Cliente -->
        <div class="seccion">
            <div class="seccion-titulo">👤 CLIENTE:</div>
            <div class="seccion-contenido">
                <?= htmlspecialchars($nombre_completo) ?>
                <?php if ($es_cliente_fijo): ?>
                    <span class="cliente-fijo">CLIENTE FIJO</span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Teléfono -->
        <div class="seccion">
            <div class="seccion-titulo">📞 TELÉFONO:</div>
            <div class="seccion-contenido"><?= htmlspecialchars($pedido['telefono']) ?></div>
        </div>

        <!-- Modalidad -->
        <div class="modalidad">
            <?= $pedido['modalidad'] === 'Delivery' ? '🚚 DELIVERY' : '🏪 RETIRO EN LOCAL 1' ?>
        </div>

        <!-- Dirección (solo si es delivery) -->
        <?php if ($pedido['modalidad'] === 'Delivery'): ?>
            <div class="delivery-box">
                <div class="seccion-titulo">🚚 DIRECCIÓN DELIVERY:</div>
                <div class="seccion-contenido">
                    <?= htmlspecialchars($pedido['direccion'] ?: '*** SIN DIRECCIÓN - CONFIRMAR ***') ?>
                    <?php if ($pedido['entre_calles']): ?>
                        <br>Entre: <?= htmlspecialchars($pedido['entre_calles']) ?>
                    <?php endif; ?>
                    <?php if ($pedido['localidad']): ?>
                        <br><?= htmlspecialchars($pedido['localidad']) ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Producto principal -->
        <div class="producto-principal">
            <div style="font-size: 13px; margin-bottom: 3px;">
                <?= htmlspecialchars($pedido['producto']) ?>
            </div>
            <?php if ($pedido['sabores']): ?>
                <div style="font-size: 10px; color: #0066cc;">
                    Sabores: <?= htmlspecialchars($pedido['sabores']) ?>
                </div>
            <?php endif; ?>
            <div style="font-size: 11px; margin-top: 3px;">
                CANTIDAD: <?= $pedido['cantidad'] ?> | PRECIO: $<?= number_format($pedido['precio'], 0, ',', '.') ?>
            </div>
        </div>

        <!-- Forma de Pago -->
        <div class="seccion">
            <div class="seccion-titulo">💳 FORMA DE PAGO:</div>
            <div class="seccion-contenido"><?= htmlspecialchars($pedido['forma_pago']) ?></div>
        </div>

        <!-- Observaciones -->
        <?php if ($pedido['observaciones']): ?>
            <div class="separador">--------------------------------</div>
            <div class="seccion">
                <div class="seccion-titulo">📝 OBSERVACIONES:</div>
                <div class="seccion-contenido"><?= htmlspecialchars($pedido['observaciones']) ?></div>
            </div>
        <?php endif; ?>

        <!-- Horario de entrega -->
        <?php if ($pedido['fecha_entrega'] || $pedido['hora_entrega'] || $pedido['notas_horario']): ?>
            <div class="separador">--------------------------------</div>
            <div class="seccion">
                <div class="seccion-titulo">⏰ HORARIO ENTREGA:</div>
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

        <!-- Footer -->
        <div class="footer">
            <div class="separador">════════════════════════════════</div>
            <p><strong>Pedido tomado:</strong> <?= $pedido['fecha_display'] ?? formatDateTime($pedido['created_at'], 'd/m/Y H:i') ?></p>
            <p><strong>Estado:</strong> <?= $pedido['estado'] ?></p>
            <?php if ($minutos_transcurridos > 0): ?>
                <p><strong>Tiempo:</strong> Hace <?= $minutos_transcurridos ?> minutos</p>
            <?php endif; ?>
            <div class="separador">════════════════════════════════</div>
            <p style="font-size: 7px; margin-top: 5px;">
                🏪 LOCAL 1 - Sistema Santa Catalina v2.0<br>
                Comanda generada para POS80-CX
            </p>
        </div>

        <script>
        function imprimirYCerrar() {
            console.log('🖨️ Iniciando impresión Local 1 - POS80-CX');
            
            // Configurar para impresión
            window.focus();
            
            // Imprimir
            window.print();
            
            // Marcar como impreso
            marcarComoImpreso();
            
            // Mostrar confirmación y cerrar
            setTimeout(() => {
                alert('✅ Comanda enviada a POS80-CX\n\nPedido #<?= $pedido['id'] ?> impreso en Local 1');
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
                console.log('✅ Pedido #<?= $pedido['id'] ?> marcado como impreso');
            }).catch(error => {
                console.error('Error marcando como impreso:', error);
            });
        }

        // Auto-imprimir si se especifica
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('auto') === '1') {
            setTimeout(imprimirYCerrar, 800);
        }

        // Eventos de teclado
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                imprimirYCerrar();
            } else if (e.key === 'Escape') {
                window.close();
            }
        });

        // Log de información
        console.log('🏭 Fábrica - Comanda cargada correctamente');
        console.log('🖨️ 3nstar RPT006S - Pedido #<?= $pedido['id'] ?>');
        console.log('📋 Cliente: <?= addslashes($nombre_completo) ?>');
        console.log('🎯 Modalidad: <?= $pedido['modalidad'] ?>');
        </script>
    </body>
    </html>
    <?php
    return ob_get_clean();
}

// Generar el contenido según la ubicación
echo generarComandaPorUbicacion($pedido, $nombre_completo, $es_cliente_fijo, $urgencia, $minutos_transcurridos, $ubicacion);
?>.error('Error marcando como impreso:', error);
            });
        }

        // Auto-imprimir si se especifica
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('auto') === '1') {
            setTimeout(imprimirYCerrar, 800);
        }

        // Eventos de teclado
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                imprimirYCerrar();
            } else if (e.key === 'Escape') {
                window.close();
            }
        });

        // Log de información
        console.log('🏪 Local 1 - Comanda cargada correctamente');
        console.log('🖨️ POS80-CX - Pedido #<?= $pedido['id'] ?>');
        console.log('📋 Cliente: <?= addslashes($nombre_completo) ?>');
        console.log('🎯 Modalidad: <?= $pedido['modalidad'] ?>');
        </script>
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
                background: #ff6600;
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
                margin: 3px 0;
                border-bottom: 1px dashed #ccc;
                padding-bottom: 2px;
            }
            .seccion-titulo {
                font-weight: bold;
                font-size: 9px;
                text-transform: uppercase;
            }
            .seccion-contenido {
                font-size: 10px;
                margin-left: 2px;
            }
            .producto-principal {
                background: #f0f0f0;
                padding: 3px;
                margin: 5px 0;
                font-weight: bold;
                text-align: center;
                border: 1px solid #000;
            }
            .urgencia {
                background: #ff0000;
                color: white;
                padding: 1px 3px;
                font-size: 9px;
                font-weight: bold;
                text-align: center;
            }
            .cliente-fijo {
                background: #00aa00;
                color: white;
                padding: 1px 3px;
                font-size: 8px;
                border-radius: 2px;
            }
            .delivery-box {
                background: #fff3cd;
                border: 1px solid #ffc107;
                padding: 3px;
                margin: 3px 0;
            }
            .footer {
                text-align: center;
                margin-top: 5px;
                border-top: 1px solid #000;
                padding-top: 3px;
                font-size: 8px;
            }
            .separador {
                text-align: center;
                margin: 3px 0;
                font-weight: bold;
            }
            
            /* Ocultar botones al imprimir */
            @media print {
                .no-print { display: none !important; }
                body { width: 80mm; }
            }
            
            /* Vista previa en pantalla */
            @media screen {
                body {
                    width: 80mm;
                    margin: 20px auto;
                    border: 2px solid #ff6600;
                    padding: 10px;
                    box-shadow: 0 0 15px rgba(255,102,0,0.3);
                }
            }
        </style>
    </head>
    <body>
        <!-- Controles de impresión (solo en pantalla) -->
        <div class="no-print" style="text-align: center; margin-bottom: 15px; padding: 10px; background: #fff3cd; border-radius: 5px; border: 1px solid #ff6600;">
            <h3 style="margin: 0 0 10px 0; color: #ff6600;">🏭 FÁBRICA - 3nstar RPT006S</h3>
            <button onclick="imprimirYCerrar()" 
                    style="background: #ff6600; color: white; border: none; padding: 12px 20px; border-radius: 5px; cursor: pointer; margin-right: 10px; font-size: 14px; font-weight: bold;">
                🖨️ IMPRIMIR COMANDA
            </button>
            <button onclick="window.close()" 
                    style="background: #6c757d; color: white; border: none; padding: 12px 20px; border-radius: 5px; cursor: pointer; font-size: 14px;">
                ❌ Cancelar
            </button>
            <div style="margin-top: 10px; font-size: 12px; color: #666;">
                <strong>Impresora:</strong> 3nstar RPT006S 80mm | <strong>Pedido:</strong> #<?= $pedido['id'] ?> | <strong>Fábrica</strong>
            </div>
        </div>

        <!-- === COMANDA FÁBRICA === -->
        
        <!-- Header -->
        <div class="comanda-header">
            <h1>SANTA CATALINA</h1>
            <p style="font-size: 9px; margin: 1px 0;">Sándwiches de Miga</p>
            <p style="font-size: 9px; margin: 1px 0;">Tel: 11 5981-3546</p>
            <p style="font-size: 9px; margin: 1px 0;">Camino Gral. Belgrano 7241</p>
            <div class="fabrica-badge">🏭 FÁBRICA</div>
        </div>

        <div class="pedido-numero">
            COMANDA #<?= $pedido['id'] ?>
            <?php if ($urgencia): ?>
                <div class="urgencia"><?= $urgencia ?></div>
            <?php endif; ?>
        </div>

        <div class="seccion">
            <div class="seccion-titulo">👤 CLIENTE:</div>
            <div style="font-size: 11px; font-weight: bold;">
                <?= htmlspecialchars($nombre_completo) ?>
                <?php if ($es_cliente_fijo): ?>
                    <span class="cliente-fijo">FIJO</span>
                <?php endif; ?>
            </div>
        </div>

        <div class="seccion">
            <div class="seccion-titulo">📞 TELÉFONO:</div>
            <div style="font-size: 11px; font-weight: bold;">
                <?= htmlspecialchars($pedido['telefono']) ?>
            </div>
        </div>

        <?php if ($pedido['modalidad'] === 'Delivery'): ?>
            <div class="delivery-box">
                <div class="seccion-titulo">🚚 DELIVERY - ATENCIÓN:</div>
                <div style="font-size: 10px;">
                    <?= htmlspecialchars($pedido['direccion'] ?: '*** SIN DIRECCIÓN - CONFIRMAR ***') ?>
                    <?php if ($pedido['entre_calles']): ?>
                        <br>Entre: <?= htmlspecialchars($pedido['entre_calles']) ?>
                    <?php endif; ?>
                    <?php if ($pedido['localidad']): ?>
                        <br><?= htmlspecialchars($pedido['localidad']) ?>
                    <?php endif; ?>
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
            <?php if ($pedido['sabores']): ?>
                <div style="font-size: 9px;">
                    Sabores: <?= htmlspecialchars($pedido['sabores']) ?>
                </div>
            <?php endif; ?>
            <div style="font-size: 10px;">
                CANTIDAD: <?= $pedido['cantidad'] ?> unidades
            </div>
            <div style="font-size: 10px; margin-top: 2px;">
                PRECIO: $<?= number_format($pedido['precio'], 0, ',', '.') ?>
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
            <p>Pedido tomado: <?= formatDateTime($pedido['created_at'], 'd/m/Y H:i') ?></p>
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

        <script>
        function imprimirYCerrar() {
            console.log('🖨️ Iniciando impresión Fábrica - 3nstar RPT006S');
            
            // Configurar para impresión
            window.focus();
            
            // Imprimir
            window.print();
            
            // Marcar como impreso
            marcarComoImpreso();
            
            // Mostrar confirmación y cerrar
            setTimeout(() => {
                alert('✅ Comanda enviada a 3nstar RPT006S\n\nPedido #<?= $pedido['id'] ?> impreso en Fábrica');
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
                console.log('✅ Pedido #<?= $pedido['id'] ?> marcado como impreso');
            }).catch(error => {
                console