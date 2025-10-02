<?php
// admin/modules/pedidos/revision_manual.php
// Ventana para revisar comandas manualmente

require_once '../../config.php';
requireLogin();

$pedidos = isset($_GET['pedidos']) ? explode(',', $_GET['pedidos']) : [];
$total = count($pedidos);

if ($total === 0) {
    die('No hay pedidos para revisar');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Revisión Manual</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', sans-serif;
            background: #f5f5f5;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px;
            text-align: center;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }
        .contador {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .navegacion {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-top: 10px;
        }
        .nav-btn {
            padding: 8px 16px;
            background: rgba(255,255,255,0.2);
            border: 1px solid rgba(255,255,255,0.5);
            color: white;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.3s;
        }
        .nav-btn:hover {
            background: rgba(255,255,255,0.3);
            transform: translateY(-2px);
        }
        .nav-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        .contenido {
            padding: 20px;
        }
        .comanda-frame {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        iframe {
            width: 100%;
            height: 500px;
            border: none;
        }
        .acciones {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-top: 20px;
        }
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 14px;
        }
        .btn-imprimir {
            background: #4caf50;
            color: white;
        }
        .btn-imprimir:hover {
            background: #45a049;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(76, 175, 80, 0.4);
        }
        .btn-saltar {
            background: #ff9800;
            color: white;
        }
        .btn-saltar:hover { background: #f57c00; }
        .btn-cerrar {
            background: #f44336;
            color: white;
        }
        .btn-cerrar:hover { background: #d32f2f; }
        .progreso-visual {
            display: flex;
            gap: 5px;
            justify-content: center;
            margin-top: 10px;
            flex-wrap: wrap;
        }
        .punto {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: rgba(255,255,255,0.3);
            transition: all 0.3s;
        }
        .punto.actual {
            background: #ffd700;
            transform: scale(1.3);
        }
        .punto.impresa {
            background: #4caf50;
        }
        .stats {
            margin-top: 10px;
            font-size: 14px;
            opacity: 0.9;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="contador" id="contador">Comanda 1 de <?= $total ?></div>
        <div class="stats" id="stats">✅ Impresas: 0 | ⏭️ Saltadas: 0</div>
        <div class="progreso-visual" id="progresoVisual"></div>
        <div class="navegacion">
            <button class="nav-btn" onclick="anterior()" id="btnAnterior">← Anterior</button>
            <button class="nav-btn" onclick="siguiente()" id="btnSiguiente">Siguiente →</button>
        </div>
    </div>
    
    <div class="contenido">
        <div class="comanda-frame">
            <iframe id="comandaFrame" src=""></iframe>
        </div>
        
        <div class="acciones">
            <button class="btn btn-imprimir" onclick="imprimirEsta()">
                🖨️ Imprimir Esta
            </button>
            <button class="btn btn-saltar" onclick="saltar()">
                ⏭️ Saltar
            </button>
            <button class="btn btn-cerrar" onclick="cerrar()">
                ❌ Cerrar
            </button>
        </div>
    </div>
    
    <script>
        const pedidos = <?= json_encode($pedidos) ?>;
        const total = <?= $total ?>;
        let indiceActual = 0;
        let impresas = 0;
        let saltadas = 0;
        const estadoPedidos = new Array(total).fill('pendiente');
        
        function cargarComanda() {
            const pedidoId = pedidos[indiceActual];
            document.getElementById('comandaFrame').src = 
                '../impresion/comanda_simple.php?pedido=' + pedidoId;
            document.getElementById('contador').textContent = 
                'Comanda ' + (indiceActual + 1) + ' de ' + total;
            
            // Actualizar botones
            document.getElementById('btnAnterior').disabled = indiceActual === 0;
            document.getElementById('btnSiguiente').disabled = indiceActual === total - 1;
            
            actualizarProgresoVisual();
            actualizarStats();
        }
        
        function actualizarProgresoVisual() {
            const container = document.getElementById('progresoVisual');
            container.innerHTML = '';
            pedidos.forEach((_, i) => {
                const punto = document.createElement('div');
                punto.className = 'punto';
                if (i === indiceActual) punto.classList.add('actual');
                if (estadoPedidos[i] === 'impresa') punto.classList.add('impresa');
                container.appendChild(punto);
            });
        }
        
        function actualizarStats() {
            document.getElementById('stats').textContent = 
                '✅ Impresas: ' + impresas + ' | ⏭️ Saltadas: ' + saltadas;
        }
        
        function anterior() {
            if (indiceActual > 0) {
                indiceActual--;
                cargarComanda();
            }
        }
        
        function siguiente() {
            if (indiceActual < total - 1) {
                indiceActual++;
                cargarComanda();
            }
        }
        
        function imprimirEsta() {
            const frame = document.getElementById('comandaFrame');
            try {
                frame.contentWindow.focus();
                frame.contentWindow.print();
            } catch(e) {
                console.warn('No se pudo imprimir automáticamente:', e);
                alert('Usa Ctrl+P para imprimir la comanda');
            }
            
            estadoPedidos[indiceActual] = 'impresa';
            impresas++;
            actualizarStats();
            actualizarProgresoVisual();
            
            // Avanzar automáticamente
            setTimeout(() => {
                if (indiceActual < total - 1) {
                    siguiente();
                } else {
                    finalizar();
                }
            }, 1000);
        }
        
        function saltar() {
            saltadas++;
            actualizarStats();
            
            if (indiceActual < total - 1) {
                siguiente();
            } else {
                finalizar();
            }
        }
        
        function finalizar() {
            if (confirm('✅ Revisión completada\n\n' + 
                       'Impresas: ' + impresas + '\n' +
                       'Saltadas: ' + saltadas + '\n\n' +
                       '¿Cerrar ventana?')) {
                window.close();
            }
        }
        
        function cerrar() {
            if (confirm('¿Cerrar sin terminar la revisión?')) {
                window.close();
            }
        }
        
        // Atajos de teclado
        document.addEventListener('keydown', function(e) {
            if (e.key === 'ArrowLeft') anterior();
            if (e.key === 'ArrowRight') siguiente();
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                imprimirEsta();
            }
            if (e.key === 'Escape') saltar();
        });
        
        // Cargar primera comanda
        console.log('✅ Modo manual iniciado con', total, 'comandas');
        cargarComanda();
    </script>
</body>
</html>