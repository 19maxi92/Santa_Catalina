<?php
// empleados/comanda_simple.php - VERSIÓN OPTIMIZADA 80mm
require_once '../admin/config.php';

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
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Courier New', monospace;
            background: #f5f5f5;
            padding: 10px;
        }
        
        /* CONTENEDOR OPTIMIZADO 80mm */
        .comanda-container {
            width: 302px; /* 80mm @ 96dpi */
            max-width: 80mm;
            margin: 0 auto;
            background: white;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        /* TICKET OPTIMIZADO */
        .comanda-ticket {
            padding: 5px 8px; /* Márgenes mínimos como el Excel */
            background: white;
        }
        
        /* FECHA Y TURNO - COMPACTO */
        .fecha-turno {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #000;
            padding-bottom: 4px;
            margin-bottom: 6px;
            font-size: 14px;
            font-weight: bold;
        }
        
        .ubicacion-badge {
            background: #000;
            color: white;
            padding: 2px 8px;
            border-radius: 3px;
            font-weight: bold;
            font-size: 14px;
        }
        
        /* NOMBRE CLIENTE - DESTACADO */
        .cliente-nombre {
            text-align: center;
            font-size: 18px; /* Reducido de 22px */
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 6px;
            padding: 4px 0;
            border-bottom: 1px dashed #000;
        }
        
        /* PRODUCTO - COMPACTO */
        .producto-info {
            text-align: center;
            font-size: 16px; /* Reducido de 20px */
            font-weight: bold;
            margin-bottom: 6px;
            padding: 4px 0;
        }
        
        /* SABORES PERSONALIZADOS - MÁS COMPACTO */
        .sabores-detalle {
            font-size: 14px; /* Reducido de 15px */
            margin-top: 6px;
            text-align: center;
            line-height: 1.4; /* Reducido de 1.8 */
            font-weight: bold;
            padding: 4px 0;
        }
        
        /* PRECIO - DESTACADO PERO COMPACTO */
        .precio-total {
            text-align: center;
            font-size: 20px; /* Reducido de 24px */
            font-weight: bold;
            margin-top: 8px;
            padding-top: 6px;
            border-top: 2px solid #000;
        }
        
        /* BOTONES COMPACTOS - NO IMPRIMIR */
        .controles {
            text-align: center;
            padding: 8px;
            background: #f8f9fa;
            border-top: 1px solid #ddd;
        }
        
        .btn {
            padding: 8px 16px;
            margin: 0 4px;
            border: none;
            border-radius: 4px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn-print {
            background: #28a745;
            color: white;
        }
        
        .btn-print:hover {
            background: #218838;
        }
        
        .btn-cancel {
            background: #6c757d;
            color: white;
        }
        
        .btn-cancel:hover {
            background: #5a6268;
        }
        
        /* OCULTAR EN IMPRESIÓN */
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
                padding: 2mm 3mm; /* Márgenes ultra mínimos para impresión */
            }
            
            /* Optimizar para papel de 80mm */
            @page {
                size: 80mm auto;
                margin: 0;
            }
        }
    </style>
</head>
<body>

    <!-- CONTROLES (NO SE IMPRIMEN) -->
    <div class="controles">
        <div style="margin-bottom: 8px; font-size: 12px; color: #666;">
            <strong>Pedido:</strong> #<?= $pedido['id'] ?> | 
            <strong>Turno:</strong> <?= $turno ?> |
            <strong>Papel:</strong> 80mm
        </div>
        <button onclick="imprimirYCerrar()" class="btn btn-print">
            🖨️ IMPRIMIR
        </button>
        <button onclick="window.close()" class="btn btn-cancel">
            ❌ Cancelar
        </button>
    </div>

    <!-- COMANDA OPTIMIZADA 80mm -->
    <div class="comanda-container">
        <div class="comanda-ticket">
            
            <!-- FECHA Y TURNO -->
            <div class="fecha-turno">
                <span><?= $fecha_formatted ?></span>
                <span class="ubicacion-badge"><?= $turno ?></span>
            </div>
            
            <!-- NOMBRE DEL CLIENTE -->
            <div class="cliente-nombre">
                <?= htmlspecialchars($nombre_completo) ?>
                <?php if ($es_cliente_fijo): ?>
                    <div style="font-size: 11px; color: #666; margin-top: 2px; font-weight: normal;">(CLIENTE FIJO)</div>
                <?php endif; ?>
            </div>
            
            <!-- PRODUCTO -->
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
            </div>
            
            <!-- SABORES PERSONALIZADOS (SI APLICA) -->
            <?php if (strpos($pedido['producto'], 'Personalizado') !== false && !empty($pedido['observaciones'])): ?>
                <div class="sabores-detalle">
                <?php
                $obs = $pedido['observaciones'];
                
                // NUEVO FORMATO (con JSON y === SABORES PERSONALIZADOS ===)
                if (preg_match('/===\s*SABORES PERSONALIZADOS\s*===\n(.*?)(?:\n---|$)/s', $obs, $matches)) {
                    $sabores_texto = trim($matches[1]);
                    $lineas = explode("\n", $sabores_texto);
                    
                    foreach ($lineas as $linea) {
                        if (preg_match('/•\s*(.+?):\s*(\d+)\s*plancha/i', $linea, $match)) {
                            $sabor = trim($match[1]);
                            $planchas = (int)$match[2];
                            $sandwiches = $planchas * 8;
                            
                            // Abreviar nombres largos
                            $sabor_abrev = str_replace(
                                ['Jamón y Queso', 'Clásico', 'Zanahoria y Queso', 'Zanahoria y Huevo'],
                                ['jyq', 'cl', 'zq', 'zh'],
                                $sabor
                            );
                            $sabor_abrev = strtolower($sabor_abrev);
                            
                            echo "{$sandwiches}{$sabor_abrev}<br>";
                        }
                    }
                } 
                // FORMATO ANTIGUO (sin JSON)
                elseif (preg_match_all('/(\d+)\s*-\s*(.+?)(?=\d+\s*-|\$|$)/s', $obs, $matches, PREG_SET_ORDER)) {
                    foreach ($matches as $match) {
                        $cantidad = trim($match[1]);
                        $sabor = trim($match[2]);
                        
                        // Abreviar nombres
                        $sabor_abrev = str_replace(
                            ['Jamón y Queso', 'Clásico', 'Zanahoria y Queso', 'Zanahoria y Huevo', 'Jamón Crudo'],
                            ['jyq', 'cl', 'zq', 'zh', 'jc'],
                            $sabor
                        );
                        $sabor_abrev = strtolower($sabor_abrev);
                        
                        echo "{$cantidad}{$sabor_abrev}<br>";
                    }
                }
                ?>
                </div>
            <?php endif; ?>
            
            <!-- PRECIO TOTAL -->
            <div class="precio-total">
                <?= $precio_formatted ?>
            </div>
            
        </div>
    </div>

    <script>
    function imprimirYCerrar() {
        // Ocultar controles antes de imprimir
        document.querySelector('.controles').style.display = 'none';
        
        // Pequeña pausa para que se aplique el cambio
        setTimeout(() => {
            window.print();
            
            // Cerrar automáticamente después de imprimir
            setTimeout(() => {
                window.close();
            }, 500);
        }, 200);
    }

    // Atajos de teclado
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            imprimirYCerrar();
        } else if (e.key === 'Escape') {
            window.close();
        }
    });

    // Auto-focus para imprimir rápido con Enter
    window.focus();
    
    console.log('🎫 Comanda optimizada 80mm cargada');
    console.log('📋 Pedido #<?= $pedido_id ?>');
    console.log('📐 Dimensiones: 302px (80mm) optimizado');
    </script>

</body>
</html>