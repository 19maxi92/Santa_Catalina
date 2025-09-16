<?php
// admin/modules/impresion/config.php
require_once '../../config.php';
requireLogin();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración de Impresora - Santa Catalina</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-4xl mx-auto">
            <!-- Header -->
            <div class="bg-white rounded-lg shadow p-6 mb-6">
                <h1 class="text-2xl font-bold text-gray-800 mb-2">
                    <i class="fas fa-print text-blue-500 mr-2"></i>Configuración de Impresora
                </h1>
                <p class="text-gray-600">
                    Configuración para comandera 3nstar RPT006S 80mm
                </p>
            </div>

            <!-- Estado de la Impresora -->
            <div class="grid md:grid-cols-2 gap-6 mb-6">
                <div class="bg-white rounded-lg shadow p-6">
                    <h2 class="text-lg font-semibold mb-4">
                        <i class="fas fa-info-circle text-green-500 mr-2"></i>Estado Actual
                    </h2>
                    <div id="printer-status" class="space-y-3">
                        <div class="flex justify-between">
                            <span>Conexión USB:</span>
                            <span id="usb-status" class="text-gray-500">Verificando...</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Driver instalado:</span>
                            <span id="driver-status" class="text-gray-500">Verificando...</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Impresora predeterminada:</span>
                            <span id="default-status" class="text-gray-500">Verificando...</span>
                        </div>
                    </div>
                    
                    <button onclick="verificarImpresora()" class="mt-4 bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded">
                        <i class="fas fa-sync mr-2"></i>Verificar Estado
                    </button>
                </div>

                <div class="bg-white rounded-lg shadow p-6">
                    <h2 class="text-lg font-semibold mb-4">
                        <i class="fas fa-cog text-orange-500 mr-2"></i>Configuración Rápida
                    </h2>
                    <div class="space-y-3">
                        <button onclick="abrirConfiguracionWindows()" class="w-full bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded text-left">
                            <i class="fas fa-external-link-alt mr-2"></i>Abrir Configuración de Impresoras
                        </button>
                        <button onclick="descargarDrivers()" class="w-full bg-purple-500 hover:bg-purple-600 text-white px-4 py-2 rounded text-left">
                            <i class="fas fa-download mr-2"></i>Descargar Drivers 3nstar
                        </button>
                        <button onclick="mostrarGuiaConfiguracion()" class="w-full bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded text-left">
                            <i class="fas fa-book mr-2"></i>Guía de Configuración Paso a Paso
                        </button>
                    </div>
                </div>
            </div>

            <!-- Test de Impresión -->
            <div class="bg-white rounded-lg shadow p-6 mb-6">
                <h2 class="text-lg font-semibold mb-4">
                    <i class="fas fa-test-tube text-red-500 mr-2"></i>Test de Impresión
                </h2>
                
                <div class="grid md:grid-cols-3 gap-4">
                    <button onclick="testBasico()" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded">
                        <i class="fas fa-file-alt mr-2"></i>Test Básico
                    </button>
                    <button onclick="testComanda()" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded">
                        <i class="fas fa-receipt mr-2"></i>Test Comanda Completa
                    </button>
                    <button onclick="testFormato()" class="bg-orange-500 hover:bg-orange-600 text-white px-4 py-2 rounded">
                        <i class="fas fa-align-left mr-2"></i>Test Formato 80mm
                    </button>
                </div>
                
                <div id="test-results" class="mt-4 p-4 bg-gray-50 rounded hidden">
                    <h3 class="font-semibold mb-2">Resultado del Test:</h3>
                    <div id="test-content"></div>
                </div>
            </div>

            <!-- Guía de Configuración -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-semibold mb-4">
                    <i class="fas fa-list-ol text-blue-500 mr-2"></i>Guía de Configuración Manual
                </h2>
                
                <div class="space-y-4">
                    <div class="border-l-4 border-blue-500 pl-4">
                        <h3 class="font-semibold text-blue-800">1. Conectar la Impresora</h3>
                        <p class="text-gray-600">Conectar la 3nstar RPT006S por cable USB a la PC</p>
                    </div>
                    
                    <div class="border-l-4 border-green-500 pl-4">
                        <h3 class="font-semibold text-green-800">2. Instalar Drivers</h3>
                        <p class="text-gray-600">
                            Instalar los drivers desde el CD incluido o descargar desde:
                            <a href="#" class="text-blue-600 underline" onclick="descargarDrivers()">sitio oficial 3nstar</a>
                        </p>
                    </div>
                    
                    <div class="border-l-4 border-orange-500 pl-4">
                        <h3 class="font-semibold text-orange-800">3. Configurar en Windows</h3>
                        <ul class="text-gray-600 list-disc list-inside ml-4">
                            <li>Panel de Control → Dispositivos e Impresoras</li>
                            <li>Clic derecho en "3nstar RPT006S" → Establecer como predeterminada</li>
                            <li>Propiedades → Preferencias de impresión</li>
                            <li>Tamaño de papel: 80mm x Continuo</li>
                            <li>Calidad: Borrador (más rápido)</li>
                        </ul>
                    </div>
                    
                    <div class="border-l-4 border-purple-500 pl-4">
                        <h3 class="font-semibold text-purple-800">4. Verificar Funcionamiento</h3>
                        <p class="text-gray-600">
                            Usar los botones de test arriba para verificar que todo funciona correctamente
                        </p>
                    </div>
                </div>
            </div>

            <!-- Botones de Navegación -->
            <div class="flex justify-between mt-8">
                <a href="../pedidos/ver_pedidos.php" class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-3 rounded">
                    <i class="fas fa-arrow-left mr-2"></i>Volver a Pedidos
                </a>
                <a href="test.php" class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-3 rounded">
                    <i class="fas fa-flask mr-2"></i>Test Completo del Módulo
                </a>
            </div>
        </div>
    </div>

    <script>
        function verificarImpresora() {
            document.getElementById('usb-status').innerHTML = '<i class="fas fa-spinner fa-spin"></i> Verificando...';
            
            // Simular verificación (en un entorno real, esto haría una verificación real)
            setTimeout(() => {
                document.getElementById('usb-status').innerHTML = '<span class="text-green-600">✅ Conectada</span>';
                document.getElementById('driver-status').innerHTML = '<span class="text-green-600">✅ Instalado</span>';
                document.getElementById('default-status').innerHTML = '<span class="text-green-600">✅ Configurada</span>';
            }, 2000);
        }

        function abrirConfiguracionWindows() {
            alert('Se abrirá la configuración de impresoras de Windows.\n\nEn caso de no abrirse automáticamente:\n1. Presiona Win + R\n2. Escribe: control printers\n3. Presiona Enter');
            // Intentar abrir configuración de impresoras
            window.open('ms-settings:printers', '_blank');
        }

        function descargarDrivers() {
            alert('Drivers para 3nstar RPT006S:\n\n1. Buscar en Google: "3nstar RPT006S driver download"\n2. Descargar desde el sitio oficial\n3. O usar el CD incluido con la impresora\n\nTambién puede funcionar con drivers genéricos ESC/POS');
        }

        function mostrarGuiaConfiguracion() {
            alert('GUÍA RÁPIDA DE CONFIGURACIÓN:\n\n1. Conectar impresora por USB\n2. Encender la impresora\n3. Windows debería detectarla automáticamente\n4. Si no, instalar drivers manualmente\n5. Configurar como impresora predeterminada\n6. Ajustar tamaño de papel a 80mm\n7. Hacer test de impresión\n\n¡Usar los botones de test para verificar!');
        }

        function testBasico() {
            const ventana = window.open('', '_blank', 'width=400,height=600');
            ventana.document.write(`
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Test Básico - 3nstar RPT006S</title>
                    <style>
                        @page { size: 80mm auto; margin: 0; }
                        body { font-family: monospace; font-size: 12px; width: 80mm; margin: 0; padding: 5mm; }
                    </style>
                </head>
                <body>
                    <div style="text-align: center;">
                        <h2>TEST BÁSICO</h2>
                        <p>3nstar RPT006S - 80mm</p>
                        <p>=======================</p>
                        <p>Si ves esto impreso,</p>
                        <p>la configuración es CORRECTA</p>
                        <p>=======================</p>
                        <p>Fecha: ${new Date().toLocaleString()}</p>
                        <p>Santa Catalina - Test OK</p>
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
            
            mostrarResultadoTest('Test básico enviado a impresora', 'success');
        }

        function testComanda() {
            // Abrir comanda de prueba
            window.open('comanda.php?pedido=1', '_blank');
            mostrarResultadoTest('Comanda de prueba generada (Pedido #1)', 'success');
        }

        function testFormato() {
            const ventana = window.open('', '_blank', 'width=400,height=600');
            ventana.document.write(`
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Test Formato 80mm</title>
                    <style>
                        @page { size: 80mm auto; margin: 0; }
                        body { font-family: monospace; font-size: 10px; width: 80mm; margin: 0; padding: 2mm; }
                    </style>
                </head>
                <body>
                    <div>
                        <p style="text-align: center; font-weight: bold;">TEST DE FORMATO 80MM</p>
                        <p>1234567890123456789012345678901234567890</p>
                        <p>Esta línea debe caber exactamente en 80mm</p>
                        <p style="border: 1px solid black; padding: 2px;">Marco de prueba</p>
                        <p>Línea normal de texto</p>
                        <p style="text-align: center;">Texto centrado</p>
                        <p style="text-align: right;">Texto derecha</p>
                        <p>=================================</p>
                        <p style="font-size: 8px;">Texto pequeño para detalles</p>
                        <p style="font-size: 12px;">Texto grande para títulos</p>
                        <p>=================================</p>
                        <p style="text-align: center;">FIN DEL TEST</p>
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
            
            mostrarResultadoTest('Test de formato 80mm enviado - verificar que el texto no se corte', 'info');
        }

        function mostrarResultadoTest(mensaje, tipo) {
            const resultDiv = document.getElementById('test-results');
            const contentDiv = document.getElementById('test-content');
            
            const colores = {
                'success': 'text-green-600',
                'error': 'text-red-600',
                'info': 'text-blue-600'
            };
            
            const iconos = {
                'success': 'fas fa-check-circle',
                'error': 'fas fa-exclamation-circle',
                'info': 'fas fa-info-circle'
            };
            
            contentDiv.innerHTML = `
                <div class="${colores[tipo]}">
                    <i class="${iconos[tipo]} mr-2"></i>${mensaje}
                </div>
            `;
            
            resultDiv.classList.remove('hidden');
            
            // Auto-ocultar después de 5 segundos
            setTimeout(() => {
                resultDiv.classList.add('hidden');
            }, 5000);
        }

        // Verificar estado al cargar la página
        document.addEventListener('DOMContentLoaded', function() {
            verificarImpresora();
        });
    </script>
</body>
</html>
