<?php
// admin/modules/pedidos/impresion_automatica.php
// Ventana de progreso para impresión automática

require_once '../../config.php';
requireLogin();

$pedidos = isset($_GET['pedidos']) ? explode(',', $_GET['pedidos']) : [];
$total = count($pedidos);

if ($total === 0) {
    die('No hay pedidos para imprimir');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Impresión Automática</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', sans-serif;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }
        .container {
            background: rgba(255,255,255,0.95);
            color: #333;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
            text-align: center;
            min-width: 350px;
        }
        h2 { margin-bottom: 20px; color: #667eea; }
        .progreso {
            font-size: 48px;
            font-weight: bold;
            margin: 20px 0;
            color: #764ba2;
        }
        .barra-container {
            width: 100%;
            height: 30px;
            background: #e0e0e0;
            border-radius: 15px;
            overflow: hidden;
            margin: 20px 0;
        }
        .barra {
            height: 100%;
            background: linear-gradient(90deg, #667eea, #764ba2);
            transition: width 0.5s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            width: 0%;
        }
        .info {
            margin: 15px 0;
            padding: 15px;
            background: #f5f5f5;
            border-radius: 10px;
            font-size: 14px;
        }
        .pedido-actual {
            font-size: 16px;
            font-weight: bold;
            color: #667eea;
            margin: 10px 0;
        }
        .botones {
            margin-top: 20px;
            display: flex;
            gap: 10px;
            justify-content: center;
        }
        button {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.3s;
        }
        .btn-pausar {
            background: #ff9800;
            color: white;
        }
        .btn-pausar:hover { background: #f57c00; }
        .btn-cancelar {
            background: #f44336;
            color: white;
        }
        .btn-cancelar:hover { background: #d32f2f; }
        .estado {
            font-size: 14px;
            color: #666;
            margin-top: 10px;
        }
        .completado {
            color: #4caf50;
            font-size: 24px;
            font-weight: bold;
            animation: aparecer 0.5s;
        }
        @keyframes aparecer {
            from { opacity: 0; transform: scale(0.5); }
            to { opacity: 1; transform: scale(1); }
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>🖨️ Impresión Automática</h2>
        <div class="progreso" id="progreso">0/<?= $total ?></div>
        <div class="barra-container">
            <div class="barra" id="barra">0%</div>
        </div>
        <div class="info">
            <div class="pedido-actual" id="pedidoActual">Preparando...</div>
            <div class="estado" id="estado">⏳ Próximo en 3 segundos...</div>
        </div>
        <div class="botones">
            <button class="btn-pausar" onclick="togglePausa()" id="btnPausar">⏸️ Pausar</button>
            <button class="btn-cancelar" onclick="cancelar()">❌ Cancelar</button>
        </div>
    </div>
    
    <script>
        const pedidos = <?= json_encode($pedidos) ?>;
        const total = <?= $total ?>;
        let indiceActual = 0;
        let pausado = false;
        let cancelado = false;
        
        function togglePausa() {
            pausado = !pausado;
            const btn = document.getElementById('btnPausar');
            if (pausado) {
                btn.innerHTML = '▶️ Continuar';
                btn.style.background = '#4caf50';
            } else {
                btn.innerHTML = '⏸️ Pausar';
                btn.style.background = '#ff9800';
            }
        }
        
        function cancelar() {
            if (confirm('¿Cancelar la impresión automática?')) {
                cancelado = true;
                document.querySelector('.container').innerHTML = 
                    '<h2 style="color: #f44336;">❌ Impresión Cancelada</h2>' +
                    '<p>Se cerrará en 2 segundos...</p>';
                setTimeout(() => {
                    if (window.opener) {
                        window.opener.location.reload();
                    }
                    window.close();
                }, 2000);
            }
        }
        
        function actualizarUI(indice, pedidoId) {
            const porcentaje = Math.round(((indice + 1) / total) * 100);
            
            document.getElementById('progreso').textContent = `${indice + 1}/${total}`;
            document.getElementById('barra').style.width = porcentaje + '%';
            document.getElementById('barra').textContent = porcentaje + '%';
            document.getElementById('pedidoActual').textContent = `Pedido #${pedidoId}`;
            
            if (indice < total - 1) {
                document.getElementById('estado').textContent = '⏳ Próximo en 3 segundos...';
            } else {
                document.getElementById('estado').textContent = '✅ Última comanda';
            }
        }
        
        function finalizar() {
            document.querySelector('.container').innerHTML = `
                <h2 style="color: #4caf50;">✅ Impresión Completada</h2>
                <div class="completado">
                    ${total} comanda(s) impresa(s)
                </div>
                <p style="margin-top: 20px; color: #666;">Cerrando en 3 segundos...</p>
            `;
            
            setTimeout(() => {
                if (window.opener) {
                    // Marcar como impresas en la ventana padre
                    window.opener.postMessage({
                        action: 'marcar_impresas',
                        pedidos: pedidos
                    }, '*');
                }
                window.close();
            }, 3000);
        }
        
        function imprimirSiguiente() {
            // Si cancelado o terminado
            if (cancelado || indiceActual >= total) {
                if (!cancelado) {
                    finalizar();
                }
                return;
            }
            
            // Si pausado, esperar
            if (pausado) {
                setTimeout(imprimirSiguiente, 500);
                return;
            }
            
            const pedidoId = pedidos[indiceActual];
            
            console.log(`📋 Imprimiendo ${indiceActual + 1}/${total}: Pedido #${pedidoId}`);
            
            // Actualizar UI
            actualizarUI(indiceActual, pedidoId);
            
            // Abrir ventana de comanda
            const url = `../impresion/comanda_simple.php?pedido=${pedidoId}`;
            const ventanaComanda = window.open(url, `Comanda_${pedidoId}`, 
                'width=400,height=600,scrollbars=yes');
            
            if (!ventanaComanda) {
                console.warn('⚠️ No se pudo abrir ventana de comanda');
            }
            
            // Siguiente
            indiceActual++;
            setTimeout(imprimirSiguiente, 3000);
        }
        
        // Iniciar después de 1 segundo
        console.log('🚀 Iniciando impresión de', total, 'comandas');
        setTimeout(imprimirSiguiente, 1000);
    </script>
</body>
</html>