<?php
require_once '../config.php';
session_start();

// Verificar acceso de empleado
if (!isset($_SESSION['empleado_logged']) || $_SESSION['empleado_logged'] !== true) {
    header('Location: login.php');
    exit;
}

$pedido_id = isset($_GET['pedido']) ? (int)$_GET['pedido'] : 0;

if (!$pedido_id) {
    die('ID de pedido requerido');
}

$pdo = getConnection();

// Obtener datos del pedido
$stmt = $pdo->prepare("
    SELECT p.*, cf.nombre as cliente_fijo_nombre, cf.apellido as cliente_fijo_apellido 
    FROM pedidos p 
    LEFT JOIN clientes_fijos cf ON p.cliente_fijo_id = cf.id 
    WHERE p.id = ? AND p.ubicacion = 'Local 1'
");
$stmt->execute([$pedido_id]);
$pedido = $stmt->fetch();

if (!$pedido) {
    die('Pedido no encontrado o no corresponde al Local 1');
}

// Determinar nombre completo
$es_cliente_fijo = !empty($pedido['cliente_fijo_nombre']);
$nombre_completo = $es_cliente_fijo 
    ? $pedido['cliente_fijo_nombre'] . ' ' . $pedido['cliente_fijo_apellido']
    : $pedido['nombre'] . ' ' . $pedido['apellido'];

// Determinar turno basado en la hora
$hora_pedido = date('H', strtotime($pedido['created_at']));
$turno = '';
if ($hora_pedido >= 6 && $hora_pedido < 14) {
    $turno = 'M'; // Mañana
} elseif ($hora_pedido >= 14 && $hora_pedido < 18) {
    $turno = 'S'; // Siesta
} else {
    $turno = 'T'; // Tarde/Noche
}

// Formatear fecha
$fecha_formatted = date('d-M', strtotime($pedido['created_at']));
// Convertir mes a español
$meses = [
    'Jan' => 'ene', 'Feb' => 'feb', 'Mar' => 'mar', 'Apr' => 'abr',
    'May' => 'may', 'Jun' => 'jun', 'Jul' => 'jul', 'Aug' => 'ago',
    'Sep' => 'sep', 'Oct' => 'oct', 'Nov' => 'nov', 'Dec' => 'dic'
];
foreach ($meses as $eng => $esp) {
    $fecha_formatted = str_replace($eng, $esp, $fecha_formatted);
}

// Formatear precio
$precio_formatted = '$' . number_format($pedido['precio'], 0, ',', '.');
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comanda Simple #<?= $pedido['id'] ?></title>
    <style>
        @page { 
            size: 80mm auto; 
            margin: 0; 
        }
        
        body {
            font-family: 'Courier New', monospace;
            font-size: 12px;
            line-height: 1.3;
            width: 80mm;
            margin: 0;
            padding: 5mm;
            background: white;
            color: black;
        }
        
        .comanda-ticket {
            border: 2px solid #000;
            padding: 8px;
            text-align: center;
            background: white;
        }
        
        .fecha-turno {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #000;
            padding-bottom: 3px;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        .cliente-nombre {
            font-size: 14px;
            font-weight: bold;
            margin: 8px 0;
            text-transform: uppercase;
        }
        
        .producto-info {
            font-size: 11px;
            margin: 6px 0;
            font-weight: bold;
        }
        
        .precio-box {
            border: 1px solid #000;
            padding: 4px;
            margin-top: 5px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .precio-final {
            font-size: 14px;
            font-weight: bold;
        }
        
        .numero-pedido {
            font-size: 10px;
            color: #666;
            margin-top: 5px;
        }
        
        /* Estilos para pantalla */
        @media screen {
            body {
                margin: 20px auto;
                border: 2px solid #333;
                box-shadow: 0 0 10px rgba(0,0,0,0.3);
            }
            
            .no-print {
                text-align: center;
                margin-bottom: 15px;
                padding: 10px;
                background: #f8f9fa;
                border-radius: 5px;
            }
        }
        
        @media print {
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>
    <!-- Botones de control (solo en pantalla) -->
    <div class="no-print">
        <h3 style="margin: 0 0 10px 0;">🎫 Comanda Simple Local 1</h3>
        <button onclick="imprimirYCerrar()" style="background: #28a745; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; margin-right: 10px; font-size: 14px;">
            🖨️ IMPRIMIR
        </button>
        <button onclick="window.close()" style="background: #6c757d; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; font-size: 14px;">
            ❌ Cancelar
        </button>
        <div style="margin-top: 10px; font-size: 12px; color: #666;">
            <strong>Pedido:</strong> #<?= $pedido['id'] ?> | <strong>Local:</strong> 1 | <strong>Turno:</strong> <?= $turno ?>
        </div>
    </div>

    <!-- COMANDA SIMPLE -->
    <div class="comanda-ticket">
        <!-- Fecha y turno -->
        <div class="fecha-turno">
            <span><?= $fecha_formatted ?></span>
            <span><?= $turno ?></span>
        </div>
        
        <!-- Nombre del cliente -->
        <div class="cliente-nombre">
            <?= htmlspecialchars($nombre_completo) ?>
        </div>
        
        <!-- Producto -->
        <div class="producto-info">
            <?= htmlspecialchars($pedido['producto']) ?>
        </div>
        
        <!-- Precio -->
        <div class="precio-box">
            <span><?= $precio_formatted ?></span>
            <span style="font-size: 10px;"><?= number_format($pedido['precio'], 0, ',', '.') ?></span>
        </div>
        
        <!-- Número de pedido (pequeño) -->
        <div class="numero-pedido">
            Pedido #<?= $pedido['id'] ?> - Local 1
        </div>
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
        
        // Log de información
        console.log('🎫 Comanda Simple para Local 1');
        console.log('Pedido ID:', <?= $pedido['id'] ?>);
        console.log('Cliente:', '<?= addslashes($nombre_completo) ?>');
        console.log('Turno:', '<?= $turno ?>');
        console.log('Fecha:', '<?= $fecha_formatted ?>');
    </script>
</body>
</html>