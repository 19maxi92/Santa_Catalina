<?php
// admin/modules/impresion/comanda_simple.php - VERSIÓN CON FUENTES MÁS GRANDES
require_once '../../config.php';
requireLogin();

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
    WHERE p.id = ?
");
$stmt->execute([$pedido_id]);
$pedido = $stmt->fetch();

if (!$pedido) {
    die('Pedido no encontrado');
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
    $turno = 'M';
} elseif ($hora_pedido >= 14 && $hora_pedido < 18) {
    $turno = 'S';
} else {
    $turno = 'T';
}

// Formatear fecha
$fecha_formatted = date('d-M', strtotime($pedido['created_at']));
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
    <title>Comanda #<?= $pedido['id'] ?></title>
    <style>
        @page { 
            size: 80mm auto; 
            margin: 0; 
        }
        
        body {
            font-family: 'Courier New', monospace;
            font-size: 14px;  /* Aumentado de 12px */
            line-height: 1.4;
            width: 80mm;
            margin: 0;
            padding: 5mm;
            background: white;
            color: black;
        }
        
        .comanda-ticket {
            border: 2px solid #000;  /* Aumentado de 1px */
            padding: 10px;  /* Aumentado de 8px */
            text-align: center;
            background: white;
        }
        
        .fecha-turno {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #000;  /* Aumentado */
            padding-bottom: 5px;  /* Aumentado */
            margin-bottom: 8px;  /* Aumentado */
            font-weight: bold;
            font-size: 14px;
        }
        
        .cliente-nombre {
            font-size: 18px;  /* Aumentado de 14px */
            font-weight: bold;
            margin: 10px 0;  /* Aumentado */
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .producto-info {
            font-size: 16px;  /* Aumentado de 11px */
            margin: 8px 0;  /* Aumentado */
            font-weight: bold;
            min-height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .precio-box {
            border: 2px solid #000;  /* Aumentado */
            padding: 8px;  /* Aumentado */
            margin-top: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .precio-final {
            font-size: 18px;  /* Aumentado de 14px */
            font-weight: bold;
        }
        
        .numero-pedido {
            font-size: 13px;  /* Aumentado de 10px */
            color: #666;
            margin-top: 6px;
        }
        
        .ubicacion-badge {
            background: #007bff;
            color: white;
            padding: 3px 8px;  /* Aumentado */
            border-radius: 4px;
            font-size: 11px;  /* Aumentado de 9px */
            font-weight: bold;
        }
        
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
        <h3 style="margin: 0 0 10px 0;">🎫 Comanda - <?= $pedido['ubicacion'] ?></h3>
        <button onclick="imprimirYCerrar()" style="background: #28a745; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; margin-right: 10px; font-size: 14px;">
            🖨️ IMPRIMIR
        </button>
        <button onclick="window.close()" style="background: #6c757d; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; font-size: 14px;">
            ❌ Cancelar
        </button>
        <div style="margin-top: 10px; font-size: 12px; color: #666;">
            <strong>Pedido:</strong> #<?= $pedido['id'] ?> | 
            <strong>Ubicación:</strong> <?= $pedido['ubicacion'] ?> | 
            <strong>Turno:</strong> <?= $turno ?>
        </div>
    </div>

    <!-- COMANDA SIMPLE -->
    <div class="comanda-ticket">
        <!-- Fecha y turno -->
        <div class="fecha-turno">
            <span><?= $fecha_formatted ?></span>
            <span class="ubicacion-badge"><?= $turno ?></span>
        </div>
        
        <!-- Nombre del cliente -->
        <div class="cliente-nombre">
            <?= htmlspecialchars($nombre_completo) ?>
            <?php if ($es_cliente_fijo): ?>
                <div style="font-size: 12px; color: #666; margin-top: 3px;">(CLIENTE FIJO)</div>
            <?php endif; ?>
        </div>
        
        <!-- Producto -->
        <div class="producto-info">
            <?php 
            // Si es personalizado, mostrar solo la cantidad total
            if (strpos($pedido['producto'], 'Personalizado') !== false): 
                preg_match('/Personalizado x(\d+)/', $pedido['producto'], $match);
                $cantidad_total = $match[1] ?? '?';
                echo "Personalizado x{$cantidad_total}";
            else:
                echo htmlspecialchars($pedido['producto']);
            endif;
            ?>
            
            <?php if (strpos($pedido['producto'], 'Personalizado') !== false && !empty($pedido['observaciones'])): ?>
                <div style="font-size: 15px; margin-top: 8px; text-align: center; line-height: 1.8; font-weight: bold;">
                <?php
                $obs = $pedido['observaciones'];
                
                // NUEVO FORMATO (con JSON y === SABORES PERSONALIZADOS ===)
                if (preg_match('/===\s*SABORES PERSONALIZADOS\s*===\n(.*?)(?=\n\n|Turno:|$)/s', $obs, $matches)) {
                    $sabores_texto = trim($matches[1]);
                    $lineas = explode("\n", $sabores_texto);
                    
                    foreach ($lineas as $linea) {
                        if (preg_match('/•\s*([^:]+):\s*(\d+)\s*plancha/i', $linea, $m)) {
                            $sabor = trim($m[1]);
                            $cant = trim($m[2]);
                            
                            // Abreviar
                            $abrev = $sabor;
                            if (stripos($sabor, 'jamón y queso') !== false || stripos($sabor, 'jamon y queso') !== false) $abrev = 'J y Q';
                            elseif (stripos($sabor, 'zanahoria y queso') !== false) $abrev = 'Z y Q';
                            elseif (stripos($sabor, 'zanahoria y huevo') !== false) $abrev = 'Z y H';
                            elseif (stripos($sabor, 'huevo') !== false) $abrev = 'Huevo';
                            elseif (stripos($sabor, 'lechuga') !== false) $abrev = 'Lechuga';
                            elseif (stripos($sabor, 'tomate') !== false) $abrev = 'Tomate';
                            elseif (stripos($sabor, 'choclo') !== false) $abrev = 'Choclo';
                            elseif (stripos($sabor, 'aceitunas') !== false) $abrev = 'Aceitunas';
                            
                            echo htmlspecialchars($abrev) . ' x' . $cant . '<br>';
                        }
                    }
                }
                // FORMATO VIEJO (Sabores: 8jyg | 8lechuga)
                elseif (preg_match('/Sabores:\s*(.+?)(?=\n|$)/i', $obs, $matches)) {
                    $sabores_raw = trim($matches[1]);
                    $sabores_array = explode('|', $sabores_raw);
                    
                    foreach ($sabores_array as $sabor_item) {
                        $sabor_item = trim($sabor_item);
                        // Formato: 8jyg, 8lechuga, etc
                        if (preg_match('/(\d+)(.+)/', $sabor_item, $m)) {
                            $cant_sandwiches = (int)$m[1];
                            $sabor_codigo = trim($m[2]);
                            $planchas = ceil($cant_sandwiches / 8);
                            
                            // Decodificar códigos comunes
                            $sabor_texto = $sabor_codigo;
                            if (stripos($sabor_codigo, 'jyg') !== false || stripos($sabor_codigo, 'jyq') !== false) $sabor_texto = 'J y Q';
                            elseif (stripos($sabor_codigo, 'lechuga') !== false) $sabor_texto = 'Lechuga';
                            elseif (stripos($sabor_codigo, 'tomate') !== false) $sabor_texto = 'Tomate';
                            elseif (stripos($sabor_codigo, 'huevo') !== false) $sabor_texto = 'Huevo';
                            elseif (stripos($sabor_codigo, 'choclo') !== false) $sabor_texto = 'Choclo';
                            elseif (stripos($sabor_codigo, 'aceitunas') !== false) $sabor_texto = 'Aceitunas';
                            
                            echo htmlspecialchars($sabor_texto) . ' x' . $planchas . '<br>';
                        }
                    }
                }
                // FORMATO MUY VIEJO (Detalle de planchas: ...)
                elseif (preg_match('/Detalle de planchas:(.+?)(?=Sabores:|$)/is', $obs, $matches)) {
                    $detalle = $matches[1];
                    preg_match_all('/Plancha\s+\d+:.*?-\s*([^\n]+)/i', $detalle, $planchas_matches);
                    
                    $sabores_count = [];
                    foreach ($planchas_matches[1] as $sabor_raw) {
                        $sabor_raw = trim($sabor_raw);
                        // Extraer sabor limpio
                        if (preg_match('/(\d+)(.+)/', $sabor_raw, $m)) {
                            $sabor_codigo = trim($m[2]);
                            
                            // Decodificar
                            $sabor_texto = $sabor_codigo;
                            if (stripos($sabor_codigo, 'jyg') !== false || stripos($sabor_codigo, 'jyq') !== false) $sabor_texto = 'J y Q';
                            elseif (stripos($sabor_codigo, 'lechuga') !== false) $sabor_texto = 'Lechuga';
                            elseif (stripos($sabor_codigo, 'tomate') !== false) $sabor_texto = 'Tomate';
                            
                            if (!isset($sabores_count[$sabor_texto])) $sabores_count[$sabor_texto] = 0;
                            $sabores_count[$sabor_texto]++;
                        }
                    }
                    
                    foreach ($sabores_count as $sabor => $cant) {
                        echo htmlspecialchars($sabor) . ' x' . $cant . '<br>';
                    }
                }
                ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Precio -->
        <div class="precio-box">
            <span class="precio-final">TOTAL:</span>
            <span class="precio-final"><?= $precio_formatted ?></span>
        </div>
        
        <!-- Número de pedido -->
        <div class="numero-pedido">
            Pedido #<?= $pedido['id'] ?>
        </div>
    </div>

    <script>
        function imprimirYCerrar() {
            window.focus();
            window.print();
            setTimeout(() => {
                window.close();
            }, 1000);
        }
        
        // Auto-imprimir si viene el parámetro
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('auto') === '1') {
            setTimeout(imprimirYCerrar, 500);
        }
        
        // Atajos de teclado
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                imprimirYCerrar();
            } else if (e.key === 'Escape') {
                window.close();
            }
        });
        
        // Confirmar impresión
        setTimeout(() => {
            if (confirm('¿Imprimir comanda ahora?')) {
                imprimirYCerrar();
            }
        }, 300);
    </script>
</body>
</html>