<?php
require_once '../config.php';
session_start();

// Verificar acceso de empleado
if (!isset($_SESSION['empleado_logged']) || $_SESSION['empleado_logged'] !== true) {
    http_response_code(403);
    exit('Acceso denegado');
}

$pdo = getConnection();
$pedido_id = (int)($_GET['pedido'] ?? 0);

if (!$pedido_id) {
    http_response_code(400);
    exit('ID de pedido requerido');
}

// Obtener datos del pedido
try {
    $stmt = $pdo->prepare("
        SELECT p.*, cf.nombre as cliente_nombre, cf.apellido as cliente_apellido 
        FROM pedidos p 
        LEFT JOIN clientes_fijos cf ON p.cliente_fijo_id = cf.id 
        WHERE p.id = ?
    ");
    $stmt->execute([$pedido_id]);
    $pedido = $stmt->fetch();
    
    if (!$pedido) {
        http_response_code(404);
        exit('Pedido no encontrado');
    }
} catch (Exception $e) {
    http_response_code(500);
    exit('Error al cargar pedido: ' . $e->getMessage());
}

// Determinar nombre completo
$nombre_completo = trim($pedido['nombre'] . ' ' . $pedido['apellido']);
if ($pedido['cliente_nombre']) {
    $nombre_completo = trim($pedido['cliente_nombre'] . ' ' . $pedido['cliente_apellido']);
}

// Determinar turno basado en observaciones o hora actual
$turno = 'T'; // Por defecto Tarde

// Buscar turno en observaciones
if ($pedido['observaciones'] && strpos($pedido['observaciones'], 'Turno delivery:') !== false) {
    if (strpos(strtolower($pedido['observaciones']), 'mañana') !== false) {
        $turno = 'M';
    } elseif (strpos(strtolower($pedido['observaciones']), 'siesta') !== false) {
        $turno = 'S';
    } elseif (strpos(strtolower($pedido['observaciones']), 'tarde') !== false) {
        $turno = 'T';
    }
} else {
    // Determinar por hora actual
    $hora = (int)date('H');
    if ($hora >= 8 && $hora < 12) {
        $turno = 'M';
    } elseif ($hora >= 12 && $hora < 16) {
        $turno = 'S';
    } else {
        $turno = 'T';
    }
}

// Formatear fecha
$fecha = date('j-M');

// Formatear producto (quitar espacios en números)
$producto_formateado = preg_replace('/(\d+)\s+/', '$1', $pedido['producto']);

// Formatear precio
$precio_formateado = '$' . number_format($pedido['precio'], 0, ',', '.');
$precio_numerico = number_format($pedido['precio'], 0, '', '');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Comanda #<?= $pedido['id'] ?></title>
    <meta charset="UTF-8">
    <style>
        @page { 
            size: 80mm auto; 
            margin: 2mm; 
        }
        body { 
            font-family: 'Courier New', monospace; 
            font-size: 12px; 
            margin: 0; 
            padding: 5px;
            width: 75mm;
            line-height: 1.1;
            color: #000;
            background: white;
        }
        .header {
            border: 2px solid #000;
            padding: 5px 8px;
            margin-bottom: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .fecha {
            font-weight: bold;
            font-size: 11px;
        }
        .turno {
            font-size: 18px;
            font-weight: bold;
        }
        .nombre {
            font-size: 14px;
            font-weight: bold;
            text-align: center;
            margin: 8px 0;
            word-wrap: break-word;
            max-width: 100%;
        }
        .producto {
            font-size: 16px;
            font-weight: bold;
            text-align: center;
            margin: 12px 0;
            word-wrap: break-word;
            max-width: 100%;
        }
        .precio-box {
            border: 2px solid #000;
            padding: 4px 8px;
            margin-top: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .precio-principal {
            font-size: 14px;
            font-weight: bold;
        }
        .precio-numerico {
            font-size: 11px;
            color: #666;
        }
        @media print {
            body { 
                width: auto; 
                -webkit-print-color-adjust: exact;
                color-adjust: exact;
            }
            .no-print {
                display: none !important;
            }
        }
        .instrucciones {
            text-align: center;
            margin: 10px 0;
            padding: 5px;
            background: #f0f0f0;
            font-size: 10px;
            border-radius: 3px;
        }
    </style>
</head>
<body>
    <!-- Instrucciones para el usuario (no se imprimen) -->
    <div class="instrucciones no-print">
        <strong>Comanda lista para imprimir</strong><br>
        Presiona Ctrl+P o usa el botón imprimir de tu navegador
    </div>

    <!-- Header con fecha y turno -->
    <div class="header">
        <span class="fecha"><?= $fecha ?></span>
        <span class="turno"><?= $turno ?></span>
    </div>
    
    <!-- Nombre del cliente -->
    <div class="nombre"><?= htmlspecialchars($nombre_completo) ?></div>
    
    <!-- Producto -->
    <div class="producto"><?= htmlspecialchars($producto_formateado) ?></div>
    
    <!-- Precio -->
    <div class="precio-box">
        <span class="precio-principal"><?= $precio_formateado ?></span>
        <span class="precio-numerico"><?= $precio_numerico ?></span>
    </div>

    <script>
        function imprimirYCerrar() {
            // Configurar para impresión
            window.focus();
            
            // Imprimir
            window.print();
            
            // Cerrar ventana después de un delay
            setTimeout(() => {
                window.close();
            }, 1000);
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
        console.log('🖨️ Comanda Comandera - Pedido #<?= $pedido['id'] ?>');
        console.log('Cliente: <?= addslashes($nombre_completo) ?>');
        console.log('Producto: <?= addslashes($producto_formateado) ?>');
        console.log('Turno: <?= $turno ?>');
        console.log('Precio: <?= $precio_formateado ?>');
        
        // Mostrar mensaje en pantalla
        setTimeout(() => {
            if (confirm('¿Imprimir comanda ahora?')) {
                imprimirYCerrar();
            }
        }, 200);
    </script>
</body>
</html>