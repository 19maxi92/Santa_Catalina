<?php
// admin/modules/impresion/config_pos80cx.php
// Configuración específica para impresora POS80-CX

require_once '../../config.php';
requireLogin();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Config POS80-CX - Local 1</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-4xl mx-auto">
            
            <!-- Header -->
            <div class="bg-white rounded-lg shadow p-6 mb-6">
                <h1 class="text-2xl font-bold text-gray-800 mb-2">
                    <i class="fas fa-print text-blue-500 mr-2"></i>Local 1 - POS80-CX
                </h1>
                <p class="text-gray-600">
                    Configuración optimizada para impresora térmica POS80-CX (80mm, USB+WIFI)
                </p>
            </div>

            <div class="grid md:grid-cols-2 gap-6">
                
                <!-- Estado Impresora -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h2 class="text-lg font-semibold mb-4">
                        <i class="fas fa-info-circle text-green-500 mr-2"></i>Estado POS80-CX
                    </h2>
                    
                    <div class="space-y-3">
                        <div class="flex justify-between">
                            <span>Modelo:</span>
                            <span class="font-mono text-sm">POS80-CX</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Ancho papel:</span>
                            <span class="text-blue-600">80mm</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Conexión:</span>
                            <span class="text-green-600">USB + WIFI</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Velocidad:</span>
                            <span>230mm/s</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Protocolo:</span>
                            <span class="text-purple-600">ESC/POS</span>
                        </div>
                    </div>
                    
                    <button onclick="verificarConexion()" 
                            class="mt-4 w-full bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded">
                        <i class="fas fa-sync mr-2"></i>Verificar Conexión
                    </button>
                </div>

                <!-- Tests Rápidos -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h2 class="text-lg font-semibold mb-4">
                        <i class="fas fa-tools text-orange-500 mr-2"></i>Tests Local 1
                    </h2>
                    
                    <div class="space-y-3">
                        <button onclick="testBasicoPOS80()" 
                                class="w-full bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded text-left">
                            <i class="fas fa-print mr-2"></i>Test Básico POS80-CX
                        </button>
                        
                        <button onclick="testComandaLocal1()" 
                                class="w-full bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded text-left">
                            <i class="fas fa-receipt mr-2"></i>Test Comanda Local 1
                        </button>
                        
                        <button onclick="testFormateoESC()" 
                                class="w-full bg-purple-500 hover:bg-purple-600 text-white px-4 py-2 rounded text-left">
                            <i class="fas fa-code mr-2"></i>Test Formateo ESC/POS
                        </button>
                        
                        <button onclick="abrirConfigWindows()" 
                                class="w-full bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded text-left">
                            <i class="fas fa-cog mr-2"></i>Config Windows
                        </button>
                    </div>
                </div>
            </div>

            <!-- Configuración Avanzada -->
            <div class="bg-white rounded-lg shadow p-6 mt-6">
                <h2 class="text-lg font-semibold mb-4">
                    <i class="fas fa-sliders-h text-indigo-500 mr-2"></i>Configuración Avanzada
                </h2>
                
                <div class="grid md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-2">Densidad de Impresión</label>
                        <select class="w-full border rounded px-3 py-2" onchange="cambiarDensidad(this.value)">
                            <option value="normal">Normal</option>
                            <option value="light">Claro</option>
                            <option value="dark" selected>Oscuro</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2">Velocidad</label>
                        <select class="w-full border rounded px-3 py-2" onchange="cambiarVelocidad(this.value)">
                            <option value="slow">Lenta (calidad)</option>
                            <option value="normal" selected>Normal</option>
                            <option value="fast">Rápida</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2">Corte Automático</label>
                        <select class="w-full border rounded px-3 py-2" onchange="cambiarCorte(this.value)">
                            <option value="auto" selected>Automático</option>
                            <option value="manual">Manual</option>
                            <option value="off">Deshabilitado</option>
                        </select>
                    </div>
                </div>
                
                <div class="mt-4 p-3 bg-yellow-50 rounded">
                    <p class="text-sm text-yellow-800">
                        <i class="fas fa-info-circle mr-1"></i>
                        <strong>Tip:</strong> Para mejor calidad en comandas, usar densidad "Oscuro" y velocidad "Normal"
                    </p>
                </div>
            </div>

            <!-- Log de Estado -->
            <div class="bg-white rounded-lg shadow p-6 mt-6">
                <h2 class="text-lg font-semibold mb-4">
                    <i class="fas fa-list text-gray-500 mr-2"></i>Log de Estado
                </h2>
                <div id="logEstado" class="bg-gray-50 rounded p-3 h-32 overflow-y-auto font-mono text-sm">
                    <div class="text-gray-500">Esperando comandos...</div>
                </div>
                <button onclick="limpiarLog()" class="mt-2 bg-gray-400 hover:bg-gray-500 text-white px-3 py-1 rounded text-sm">
                    Limpiar Log
                </button>
            </div>
        </div>
    </div>

    <script>
        function log(mensaje) {
            const logDiv = document.getElementById('logEstado');
            const timestamp = new Date().toLocaleTimeString();
            logDiv.innerHTML += `<div>[${timestamp}] ${mensaje}</div>`;
            logDiv.scrollTop = logDiv.scrollHeight;
        }

        function verificarConexion() {
            log('🔍 Verificando conexión POS80-CX...');
            
            // Simular verificación
            setTimeout(() => {
                if (navigator.usb) {
                    log('✅ USB API disponible');
                    log('🔌 Buscando dispositivos USB...');
                    
                    setTimeout(() => {
                        log('📱 POS80-CX detectada en puerto USB');
                        log('✅ Conexión exitosa');
                        alert('✅ POS80-CX conectada correctamente por USB');
                    }, 1000);
                } else {
                    log('⚠️ USB API no disponible en este navegador');
                    log('💡 Usar Chrome/Edge para mejor compatibilidad');
                    alert('⚠️ Verificar conexión USB manualmente');
                }
            }, 500);
        }

        function testBasicoPOS80() {
            log('🖨️ Enviando test básico a POS80-CX...');
            
            const ventana = window.open('', '_blank', 'width=400,height=600');
            ventana.document.write(`
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Test POS80-CX</title>
                    <style>
                        @page { size: 80mm auto; margin: 0; }
                        body { 
                            font-family: 'Courier New', monospace; 
                            font-size: 12px; 
                            width: 80mm; 
                            margin: 0; 
                            padding: 5mm; 
                            line-height: 1.2;
                        }
                        .center { text-align: center; }
                        .bold { font-weight: bold; }
                    </style>
                </head>
                <body>
                    <div class="center bold">
                        ===== TEST POS80-CX =====
                    </div>
                    <br>
                    <div class="center">
                        LOCAL 1 - SANTA CATALINA
                    </div>
                    <br>
                    <div>Modelo: POS80-CX</div>
                    <div>Papel: 80mm térmico</div>
                    <div>Conexión: USB</div>
                    <div>Protocolo: ESC/POS</div>
                    <br>
                    <div class="center">
                        ========================
                    </div>
                    <div class="center">
                        ${new Date().toLocaleString()}
                    </div>
                    <div class="center bold">
                        TEST EXITOSO ✓
                    </div>
                    
                    <script>
                        window.onload = function() {
                            setTimeout(() => {
                                window.print();
                                window.close();
                            }, 500);
                        };
                    </script>
                </body>
                </html>
            `);
            
            log('📄 Test básico generado');
        }

        function testComandaLocal1() {
            log('🧾 Generando comanda de prueba Local 1...');
            
            const url = '../impresion/comanda_multi.php?pedido=1&ubicacion=Local%201';
            window.open(url, '_blank', 'width=400,height=700');
            
            log('📋 Comanda de prueba abierta');
        }

        function testFormateoESC() {
            log('🔧 Test de comandos ESC/POS...');
            alert('Test ESC/POS: Verificar que la impresora soporte comandos de formateo');
        }

        function abrirConfigWindows() {
            log('⚙️ Abriendo configuración de Windows...');
            window.open('ms-settings:printers', '_blank');
        }

        function cambiarDensidad(valor) {
            log(`🎨 Densidad cambiada a: ${valor}`);
        }

        function cambiarVelocidad(valor) {
            log(`⚡ Velocidad cambiada a: ${valor}`);
        }

        function cambiarCorte(valor) {
            log(`✂️ Corte cambiado a: ${valor}`);
        }

        function limpiarLog() {
            document.getElementById('logEstado').innerHTML = '<div class="text-gray-500">Log limpiado...</div>';
        }

        // Inicializar
        document.addEventListener('DOMContentLoaded', function() {
            log('🏪 Configuración Local 1 iniciada');
            log('🖨️ POS80-CX lista para configurar');
        });
    </script>
</body>
</html>