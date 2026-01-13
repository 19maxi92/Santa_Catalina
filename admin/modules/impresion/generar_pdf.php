<?php
// admin/modules/impresion/generar_pdf.php
require_once '../../config.php';
requireLogin();

// Verificar parámetros
$pedido_id = isset($_GET['pedido']) ? (int)$_GET['pedido'] : 0;
$ubicacion = isset($_GET['ubicacion']) ? $_GET['ubicacion'] : 'Local 1';
$tipo = isset($_GET['tipo']) ? $_GET['tipo'] : 'html'; // Cambiado a HTML por defecto

if (!$pedido_id) {
    die('ID de pedido requerido');
}

$pdo = getConnection();

// Obtener datos del pedido
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

// Calcular datos adicionales
$minutos_transcurridos = round((time() - strtotime($pedido['created_at'])) / 60);
$es_cliente_fijo = !empty($pedido['cliente_fijo_nombre']);
$nombre_completo = $es_cliente_fijo 
    ? $pedido['cliente_fijo_nombre'] . ' ' . $pedido['cliente_fijo_apellido']
    : $pedido['nombre'] . ' ' . $pedido['apellido'];

// Determinar urgencia
$urgencia = '';
if ($minutos_transcurridos > 60) {
    $urgencia = '🚨 URGENTE';
} elseif ($minutos_transcurridos > 30) {
    $urgencia = '⚠️ PRIORIDAD';
}

// NO configurar headers de PDF - generar HTML que se puede imprimir/guardar como PDF
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comanda <?= htmlspecialchars($ubicacion) ?> #<?= $pedido['id'] ?></title>
    <style>
        @page { 
            size: 80mm auto; 
            margin: 2mm;
        }
        
        body {
            font-family: 'Courier New', monospace;
            font-size: 12px; /* Aumentado de 10px a 12px */
            line-height: 1.3; /* Aumentado de 1.2 a 1.3 */
            width: 80mm;
            margin: 0;
            padding: 3mm; /* Aumentado de 2mm a 3mm */
            background: white;
            color: black;
        }
        
        .header {
            text-align: center;
            border-bottom: 2px solid #000;
            padding-bottom: 4px; /* Aumentado */
            margin-bottom: 6px; /* Aumentado */
        }
        
        .header h1 {
            font-size: 18px; /* Aumentado de 14px a 18px */
            font-weight: bold;
            margin: 0;
            letter-spacing: 1px;
        }
        
        .header p {
            font-size: 10px; /* Aumentado de 8px a 10px */
            margin: 2px 0; /* Aumentado */
        }
        
        .ubicacion-badge {
            text-align: center;
            padding: 3px 6px; /* Aumentado */
            font-size: 10px; /* Aumentado de 8px a 10px */
            font-weight: bold;
            margin: 4px 0; /* Aumentado */
            border-radius: 3px;
        }
        
        .local1 {
            background: #0066cc;
            color: white;
        }
        
        .fabrica {
            background: #ff6600;
            color: white;
        }
        
        .pedido-numero {
            font-size: 16px; /* Aumentado de 12px a 16px */
            font-weight: bold;
            text-align: center;
            margin: 6px 0; /* Aumentado */
            padding: 4px; /* Aumentado */
            border: 2px solid #000; /* Aumentado grosor */
        }
        
        .urgencia {
            background: #ff0000;
            color: white;
            padding: 2px 4px; /* Aumentado */
            font-size: 10px; /* Aumentado de 8px a 10px */
            font-weight: bold;
            text-align: center;
            margin: 3px 0; /* Aumentado */
        }
        
        .seccion {
            margin: 4px 0; /* Aumentado */
            border-bottom: 1px dashed #ccc;
            padding-bottom: 3px; /* Aumentado */
        }
        
        .seccion-titulo {
            font-weight: bold;
            font-size: 10px; /* Aumentado de 8px a 10px */
            text-transform: uppercase;
            margin-bottom: 1px;
        }
        
        .seccion-contenido {
            font-size: 11px; /* Aumentado de 9px a 11px */
            margin-left: 3px; /* Aumentado */
            font-weight: bold;
        }
        
        .producto-principal {
            background: #f0f0f0;
            border: 2px solid #000; /* Aumentado grosor */
            padding: 5px; /* Aumentado */
            margin: 6px 0; /* Aumentado */
            font-weight: bold;
            text-align: center;
        }
        
        .modalidad {
            text-align: center;
            background: #eeeeee;
            padding: 3px; /* Aumentado */
            margin: 4px 0; /* Aumentado */
            font-weight: bold;
            border: 1px solid #999;
            font-size: 11px; /* Especificado */
        }
        
        .cliente-fijo {
            background: #00aa00;
            color: white;
            padding: 2px 4px; /* Aumentado */
            font-size: 8px; /* Aumentado de 7px a 8px */
            border-radius: 3px;
        }
        
        .delivery-box {
            background: #fff3cd;
            border: 1px solid #ffc107;
            padding: 3px; /* Aumentado */
            margin: 4px 0; /* Aumentado */
        }
        
        .footer {
            text-align: center;
            margin-top: 6px; /* Aumentado */
            border-top: 2px solid #000; /* Aumentado grosor */
            padding-top: 4px; /* Aumentado */
            font-size: 9px; /* Aumentado de 7px a 9px */
        }
        
        .separador {
            text-align: center;
            margin: 4px 0; /* Aumentado */
            font-weight: bold;
            font-size: 10px; /* Aumentado de 8px a 10px */
        }
        
        /* Botones de control (solo en pantalla) */
        .controles {
            position: fixed;
            top: 10px;
            right: 10px;
            background: rgba(0,0,0,0.8);
            color: white;
            padding: 10px;
            border-radius: 5px;
            z-index: 1000;
        }
        
        .btn {
            background: #007bff;
            color: white;
            border: none;
            padding: 8px 15px;
            margin: 2px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 12px;
        }
        
        .btn:hover {
            background: #0056b3;
        }
        
        .btn-success {
            background: #28a745;
        }
        
        .btn-success:hover {
            background: #1e7e34;
        }
        
        /* Optimización para impresión */
        @media print {
            .controles { display: none !important; }
            body { 
                width: 80mm; 
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            @page {
                margin: 0;
                size: 80mm auto;
            }
        }
    </style>
</head>
<body>
    
    <!-- Controles (solo en pantalla) -->
    <div class="controles">
        <div style="margin-bottom: 5px; font-size: 11px; font-weight: bold;">
            📄 Comanda Lista
        </div>
        <button class="btn btn-success" onclick="imprimirComanda()">
            🖨️ Imprimir
        </button>
        <button class="btn" onclick="guardarPDF()">
            📁 Guardar PDF
        </button>
        <button class="btn" onclick="window.close()">
            ❌ Cerrar
        </button>
    </div>
    
    <!-- Header -->
    <div class="header">
        <h1>SANTA CATALINA</h1>
        <p>Sándwiches de Miga Artesanales</p>
        <p>Tel: 11 5981-3546</p>
        <p>Camino Gral. Belgrano 7241</p>
        
        <div class="ubicacion-badge <?= $ubicacion === 'Local 1' ? 'local1' : 'fabrica' ?>">
            <?= $ubicacion === 'Local 1' ? '🏪 LOCAL 1' : '🏭 FÁBRICA' ?>
        </div>
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
        <?= $pedido['modalidad'] === 'Delivery' ? '🚚 DELIVERY' : '🏪 RETIRO EN LOCAL' ?>
    </div>

    <!-- Dirección (solo si es delivery) -->
    <?php if ($pedido['modalidad'] === 'Delivery'): ?>
        <div class="delivery-box">
            <div class="seccion-titulo">🚚 DIRECCIÓN:</div>
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
        <div style="font-size: 14px; margin-bottom: 3px; font-weight: bold;"> <!-- Aumentado de 11px a 14px -->
            <?= htmlspecialchars($pedido['producto']) ?>
        </div>
        <?php if ($pedido['sabores']): ?>
            <div style="font-size: 10px; margin: 2px 0;"> <!-- Aumentado de 8px a 10px -->
                Sabores: <?= htmlspecialchars($pedido['sabores']) ?>
            </div>
        <?php endif; ?>
        <div style="font-size: 11px; margin-top: 3px; font-weight: bold;"> <!-- Aumentado de 9px a 11px -->
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
        <p style="font-size: 10px; margin: 2px 0;"><strong>Pedido tomado:</strong> <?= $pedido['fecha_display'] ?? formatDateTime($pedido['created_at'], 'd/m/Y H:i') ?></p> <!-- Aumentado -->
        <p style="font-size: 10px; margin: 2px 0;"><strong>Estado:</strong> <?= $pedido['estado'] ?></p> <!-- Aumentado -->
        <?php if ($minutos_transcurridos > 0): ?>
            <p style="font-size: 10px; margin: 2px 0;"><strong>Tiempo:</strong> Hace <?= $minutos_transcurridos ?> minutos</p> <!-- Aumentado -->
        <?php endif; ?>
        <div class="separador">════════════════════════════════</div>
        <p style="font-size: 8px; margin-top: 3px;"> <!-- Aumentado de 6px a 8px -->
            <?= $ubicacion === 'Local 1' ? '🏪 LOCAL 1' : '🏭 FÁBRICA' ?> - Sistema Santa Catalina v2.0<br>
            Comanda optimizada para impresión 80mm
        </p>
    </div>

    <script>
        function imprimirComanda() {
            console.log('🖨️ Enviando a impresora...');
            window.print();
        }
        
        function guardarPDF() {
            console.log('📁 Guardando como PDF...');
            // Abrir diálogo de impresión con opción "Guardar como PDF"
            window.print();
            
            // Mostrar instrucciones
            setTimeout(() => {
                alert('📄 Para guardar como PDF:\n\n' +
                      '1. En la ventana de impresión, buscar "Destino"\n' +
                      '2. Seleccionar "Guardar como PDF"\n' +
                      '3. Elegir ubicación y guardar\n\n' +
                      '✅ ¡Listo para imprimir en cualquier impresora 80mm!');
            }, 1000);
        }
        
        // Auto-focus para mejor experiencia
        window.onload = function() {
            console.log('📄 Comanda <?= $ubicacion ?> #<?= $pedido['id'] ?> cargada');
            console.log('🖨️ Lista para imprimir en formato 80mm');
            
            // Si viene con parámetro auto, mostrar diálogo de impresión automáticamente
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('auto') === '1') {
                setTimeout(imprimirComanda, 500);
            }
        };
        
        // Atajos de teclado
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'p') {
                e.preventDefault();
                imprimirComanda();
            } else if (e.key === 'Escape') {
                window.close();
            }
        });
    </script>
    
</body>
</html>