<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sandwichería Santa Catalina - Los mejores triples de Buenos Aires</title>
    <meta name="description" content="Sandwichería Santa Catalina - Los mejores sándwiches triples de La Plata. Delivery y takeaway. Jamón y queso, surtidos clásicos, especiales y premium.">
    <meta name="keywords" content="sandwiches, triples, La Plata, delivery, jamón y queso, surtidos, premium">
    
    <!-- Open Graph para redes sociales -->
    <meta property="og:title" content="Sandwichería Santa Catalina">
    <meta property="og:description" content="Los mejores sándwiches triples de La Plata">
    <meta property="og:image" content="https://tu-dominio.com/logo-santa-catalina.jpg">
    <meta property="og:url" content="https://tu-dominio.com">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Poppins', sans-serif;
        }
        
        .sandwich-card {
            transition: all 0.3s ease;
        }
        
        .sandwich-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
        }
        
        .hero-bg {
            background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
        }
        
        .pulse-button {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.05);
            }
        }
        
        .gradient-text {
            background: linear-gradient(135deg, #ff6b35, #f7931e);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .floating {
            animation: floating 3s ease-in-out infinite;
        }
        
        @keyframes floating {
            0%, 100% {
                transform: translateY(0px);
            }
            50% {
                transform: translateY(-10px);
            }
        }
        
        /* Estilos para el botón de WhatsApp fijo */
        .whatsapp-fixed {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
            animation: bounce 2s infinite;
        }
        
        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% {
                transform: translateY(0);
            }
            40% {
                transform: translateY(-10px);
            }
            60% {
                transform: translateY(-5px);
            }
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .hero-title {
                font-size: 2.5rem;
            }
            
            .sandwich-card {
                margin-bottom: 1.5rem;
            }
        }
    </style>
</head>

<body class="bg-gray-50">
    <!-- Header Hero Section -->
    <header class="hero-bg text-white relative overflow-hidden">
        <!-- Sección de accesos en la parte superior -->
        <div class="bg-gray-800 bg-opacity-90 py-3 border-b border-gray-700" style="position: relative; z-index: 100;">
            <div class="container mx-auto px-4">
                <div class="flex justify-end space-x-3">
                    <a href="empleados/login.php" 
                       class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-all duration-300 flex items-center shadow-lg"
                       style="pointer-events: auto; position: relative; z-index: 101;">
                        <i class="fas fa-users mr-2"></i>
                        Empleados
                    </a>
                    <a href="admin/login.php" 
                       class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-all duration-300 flex items-center shadow-lg"
                       style="pointer-events: auto; position: relative; z-index: 101;">
                        <i class="fas fa-cog mr-2"></i>
                        Admin
                    </a>
                </div>
            </div>
        </div>
        
        <div class="absolute inset-0 bg-black opacity-10"></div>
        <div class="container mx-auto px-4 py-16 relative z-10">
            <div class="text-center">
                <!-- Logo placeholder -->
                <div class="floating mb-8">
                    <div class="w-32 h-32 mx-auto bg-white rounded-full flex items-center justify-center shadow-2xl">
                        <i class="fas fa-hamburger text-6xl text-orange-500"></i>
                    </div>
                </div>
                
                <h1 class="hero-title text-5xl md:text-6xl font-bold mb-4">
                    Sandwichería<br>
                    <span class="text-yellow-300">Santa Catalina</span>
                </h1>
                
                <p class="text-xl md:text-2xl mb-8 font-light">
                    Los mejores sándwiches triples de Buenos Aires
                </p>
                
                <div class="flex flex-col md:flex-row items-center justify-center space-y-4 md:space-y-0 md:space-x-6">
                    <div class="flex items-center text-lg">
                        <i class="fas fa-map-marker-alt mr-3 text-yellow-300"></i>
                        <span>Camino General Manuel Belgrano 7241, J.M. Gutierrez, Buenos Aires, Argentina</span>
                    </div>
                    <div class="flex items-center text-lg">
                        <i class="fas fa-phone mr-3 text-yellow-300"></i>
                        <span>11 5981-3546</span>
                    </div>
                </div>
                
                <!-- Call to Action -->
                <div class="mt-8">
                    <a href="https://wa.me/541159813546?text=Hola%20quiero%20hacer%20un%20pedido" 
                       target="_blank" 
                       class="pulse-button inline-flex items-center bg-green-500 hover:bg-green-600 text-white text-xl font-semibold px-8 py-4 rounded-full transition-all duration-300 shadow-lg">
                        <i class="fab fa-whatsapp mr-3 text-2xl"></i>
                        Hacer Pedido por WhatsApp
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Productos Principales -->
    <main class="container mx-auto px-4 py-16">
        <!-- Título de sección -->
        <div class="text-center mb-16">
            <h2 class="text-4xl font-bold gradient-text mb-4">Nuestros Sándwiches</h2>
            <p class="text-xl text-gray-600">Triples frescos hechos al momento con los mejores ingredientes</p>
        </div>

        <!-- Grid de productos principales -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8 mb-20">
            
            <!-- 24 JAMÓN Y QUESO -->
            <div class="sandwich-card bg-white rounded-2xl overflow-hidden shadow-lg">
                <div class="bg-gradient-to-r from-orange-400 to-orange-500 p-6">
                    <h3 class="text-2xl font-bold text-white">24 Jamón y Queso</h3>
                    <p class="text-orange-100">El clásico que nunca falla</p>
                </div>
                <div class="p-6">
                    <p class="text-gray-600 mb-6">Clásico triple de jamón y queso. Pan fresco, jamón cocido y queso cremoso. Perfectos para cualquier ocasión.</p>
                    <div class="flex items-end justify-between mb-6">
                        <div>
                            <div class="text-3xl font-bold text-orange-600">$12.500</div>
                        </div>
                        <div class="text-right">
                            <div class="text-sm text-gray-500">24 unidades</div>
                            <div class="text-xs text-gray-400">Para 8-12 personas</div>
                        </div>
                    </div>
                    <a href="https://wa.me/541159813546?text=Hola%20quiero%20pedir%2024%20sándwiches%20de%20jamón%20y%20queso%20por%20%2412.500" 
                       target="_blank" 
                       class="w-full bg-orange-500 hover:bg-orange-600 text-white py-3 rounded-xl font-semibold transition-all duration-300 flex items-center justify-center">
                        <i class="fab fa-whatsapp mr-2"></i>
                        Pedir Ahora
                    </a>
                </div>
            </div>

            <!-- 48 JAMÓN Y QUESO -->
            <div class="sandwich-card bg-white rounded-2xl overflow-hidden shadow-lg border-2 border-red-200">
                <div class="bg-gradient-to-r from-red-500 to-red-600 p-6 relative">
                    <div class="absolute top-2 right-2 bg-yellow-400 text-red-800 px-3 py-1 rounded-full text-xs font-bold">
                        ¡OFERTA!
                    </div>
                    <h3 class="text-2xl font-bold text-white">48 Jamón y Queso</h3>
                    <p class="text-red-100">Pack grande con descuento</p>
                </div>
                <div class="p-6">
                    <p class="text-gray-600 mb-6">Pack grande de clásicos jamón y queso. Ideal para eventos, oficinas y reuniones familiares.</p>
                    <div class="flex items-end justify-between mb-6">
                        <div>
                            <div class="text-3xl font-bold text-red-600">$24.000</div>
                        </div>
                        <div class="text-right">
                            <div class="text-sm text-gray-500">48 unidades</div>
                            <div class="text-xs text-gray-400">Para 15-20 personas</div>
                        </div>
                    </div>
                    <a href="https://wa.me/541159813546?text=Hola%20quiero%20pedir%2048%20sándwiches%20de%20jamón%20y%20queso%20por%20%2424.000" 
                       target="_blank" 
                       class="w-full bg-red-500 hover:bg-red-600 text-white py-3 rounded-xl font-semibold transition-all duration-300 flex items-center justify-center">
                        <i class="fab fa-whatsapp mr-2"></i>
                        Pedir Ahora
                    </a>
                </div>
            </div>

            <!-- 24 SURTIDOS CLÁSICOS -->
            <div class="sandwich-card bg-white rounded-2xl overflow-hidden shadow-lg">
                <div class="bg-gradient-to-r from-blue-500 to-blue-600 p-6">
                    <h3 class="text-2xl font-bold text-white">24 Surtidos Clásicos</h3>
                    <p class="text-blue-100">Variedad tradicional</p>
                </div>
                <div class="p-6">
                    <p class="text-gray-600 mb-4">Jamón y queso, lechuga, tomate, huevo. Los sabores de siempre que nunca pasan de moda.</p>
                    <div class="mb-4">
                        <h5 class="font-semibold text-gray-700 mb-2">Sabores incluidos:</h5>
                        <div class="flex flex-wrap gap-1">
                            <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded text-xs">Jamón y Queso</span>
                            <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded text-xs">Lechuga</span>
                            <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded text-xs">Tomate</span>
                            <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded text-xs">Huevo</span>
                        </div>
                    </div>
                    <div class="flex items-end justify-between mb-6">
                        <div>
                            <div class="text-3xl font-bold text-blue-600">$12.500</div>
                        </div>
                    </div>
                    <a href="https://wa.me/541159813546?text=Hola%20quiero%20pedir%2024%20sándwiches%20surtidos%20clásicos%20por%20%2412.500" 
                       target="_blank" 
                       class="w-full bg-blue-500 hover:bg-blue-600 text-white py-3 rounded-xl font-semibold transition-all duration-300 flex items-center justify-center">
                        <i class="fab fa-whatsapp mr-2"></i>
                        Pedir Ahora
                    </a>
                </div>
            </div>

            <!-- 48 SURTIDOS CLÁSICOS -->
            <div class="sandwich-card bg-white rounded-2xl overflow-hidden shadow-lg">
                <div class="bg-gradient-to-r from-blue-600 to-blue-700 p-6">
                    <h3 class="text-2xl font-bold text-white">48 Surtidos Clásicos</h3>
                    <p class="text-blue-100">Pack grande clásico</p>
                </div>
                <div class="p-6">
                    <p class="text-gray-600 mb-6">Jamón y queso, lechuga, tomate, huevo. Pack grande con los sabores tradicionales.</p>
                    <div class="flex items-end justify-between mb-6">
                        <div>
                            <div class="text-3xl font-bold text-blue-600">$22.000</div>
                        </div>
                    </div>
                    <a href="https://wa.me/541159813546?text=Hola%20quiero%20pedir%2048%20sándwiches%20surtidos%20clásicos%20por%20%2422.000" 
                       target="_blank" 
                       class="w-full bg-blue-600 hover:bg-blue-700 text-white py-3 rounded-xl font-semibold transition-all duration-300 flex items-center justify-center">
                        <i class="fab fa-whatsapp mr-2"></i>
                        Pedir Ahora
                    </a>
                </div>
            </div>

            <!-- 24 SURTIDOS ESPECIALES -->
            <div class="sandwich-card bg-white rounded-2xl overflow-hidden shadow-lg">
                <div class="bg-gradient-to-r from-purple-500 to-purple-600 p-6">
                    <h3 class="text-2xl font-bold text-white">24 Surtidos Especiales</h3>
                    <p class="text-purple-100">Con choclo y aceitunas</p>
                </div>
                <div class="p-6">
                    <p class="text-gray-600 mb-4">Clásicos + choclo y aceitunas. Más variedad de sabores para los que buscan algo diferente.</p>
                    <div class="mb-4">
                        <h5 class="font-semibold text-gray-700 mb-2">Sabores incluidos:</h5>
                        <div class="flex flex-wrap gap-1">
                            <span class="bg-purple-100 text-purple-800 px-2 py-1 rounded text-xs">Jamón y Queso</span>
                            <span class="bg-purple-100 text-purple-800 px-2 py-1 rounded text-xs">Lechuga</span>
                            <span class="bg-purple-100 text-purple-800 px-2 py-1 rounded text-xs">Tomate</span>
                            <span class="bg-purple-100 text-purple-800 px-2 py-1 rounded text-xs">Huevo</span>
                            <span class="bg-purple-100 text-purple-800 px-2 py-1 rounded text-xs">Choclo</span>
                            <span class="bg-purple-100 text-purple-800 px-2 py-1 rounded text-xs">Aceitunas</span>
                        </div>
                    </div>
                    <div class="flex items-end justify-between mb-6">
                        <div>
                            <div class="text-3xl font-bold text-purple-600">$12.500</div>
                        </div>
                    </div>
                    <a href="https://wa.me/541159813546?text=Hola%20quiero%20pedir%2024%20sándwiches%20surtidos%20especiales%20por%20%2412.500" 
                       target="_blank" 
                       class="w-full bg-purple-500 hover:bg-purple-600 text-white py-3 rounded-xl font-semibold transition-all duration-300 flex items-center justify-center">
                        <i class="fab fa-whatsapp mr-2"></i>
                        Pedir Ahora
                    </a>
                </div>
            </div>

            <!-- 48 SURTIDOS ESPECIALES -->
            <div class="sandwich-card bg-white rounded-2xl overflow-hidden shadow-lg">
                <div class="bg-gradient-to-r from-purple-600 to-purple-700 p-6">
                    <h3 class="text-2xl font-bold text-white">48 Surtidos Especiales</h3>
                    <p class="text-purple-100">Pack completo especial</p>
                </div>
                <div class="p-6">
                    <p class="text-gray-600 mb-6">Clásicos + choclo y aceitunas. Pack grande con mayor variedad de sabores.</p>
                    <div class="flex items-end justify-between mb-6">
                        <div>
                            <div class="text-3xl font-bold text-purple-600">$24.000</div>
                        </div>
                    </div>
                    <a href="https://wa.me/541159813546?text=Hola%20quiero%20pedir%2048%20sándwiches%20surtidos%20especiales%20por%20%2422.000%20(efectivo)" 
                       target="_blank" 
                       class="w-full bg-purple-600 hover:bg-purple-700 text-white py-3 rounded-xl font-semibold transition-all duration-300 flex items-center justify-center">
                        <i class="fab fa-whatsapp mr-2"></i>
                        Pedir Ahora
                    </a>
                </div>
            </div>

        </div>

        <!-- Sección Premium -->
        <div class="bg-gradient-to-r from-yellow-100 to-orange-100 rounded-3xl p-8 mb-20">
            <div class="text-center mb-12">
                <h2 class="text-4xl font-bold text-gray-800 mb-4">🌟 Premium Gourmet</h2>
                <p class="text-xl text-gray-600">Sabores únicos y sofisticados para paladares exigentes</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <!-- 24 PREMIUM -->
                <div class="sandwich-card bg-white rounded-2xl overflow-hidden shadow-lg">
                    <div class="bg-gradient-to-r from-yellow-500 to-yellow-600 p-6">
                        <h3 class="text-2xl font-bold text-white">24 Premium</h3>
                        <p class="text-yellow-100">Sabores gourmet selectos</p>
                    </div>
                    <div class="p-6">
                        <p class="text-gray-600 mb-4">Sabores gourmet únicos que no encontrarás en otro lado. Ingredientes premium cuidadosamente seleccionados.</p>
                        <div class="mb-4">
                            <h5 class="font-semibold text-gray-700 mb-2">Sabores premium disponibles:</h5>
                            <div class="grid grid-cols-2 gap-1 text-xs">
                                <span class="bg-yellow-100 text-yellow-800 px-2 py-1 rounded">Ananá</span>
                                <span class="bg-yellow-100 text-yellow-800 px-2 py-1 rounded">Atún</span>
                                <span class="bg-yellow-100 text-yellow-800 px-2 py-1 rounded">Berenjena</span>
                                <span class="bg-yellow-100 text-yellow-800 px-2 py-1 rounded">Durazno</span>
                                <span class="bg-yellow-100 text-yellow-800 px-2 py-1 rounded">Jamón Crudo</span>
                                <span class="bg-yellow-100 text-yellow-800 px-2 py-1 rounded">Morrón</span>
                                <span class="bg-yellow-100 text-yellow-800 px-2 py-1 rounded">Palmito</span>
                                <span class="bg-yellow-100 text-yellow-800 px-2 py-1 rounded">Panceta</span>
                                <span class="bg-yellow-100 text-yellow-800 px-2 py-1 rounded">Pollo</span>
                                <span class="bg-yellow-100 text-yellow-800 px-2 py-1 rounded">Roquefort</span>
                                <span class="bg-yellow-100 text-yellow-800 px-2 py-1 rounded">Salame</span>
                            </div>
                        </div>
                        <div class="flex items-end justify-between mb-6">
                            <div>
                                <div class="text-3xl font-bold text-yellow-600">$22.500</div>
                            </div>
                        </div>
                        <a href="https://wa.me/541159813546?text=Hola%20quiero%20pedir%2024%20sándwiches%20premium%20por%20%2422.500%20-%20Sabores:" 
                           target="_blank" 
                           class="w-full bg-yellow-500 hover:bg-yellow-600 text-white py-3 rounded-xl font-semibold transition-all duration-300 flex items-center justify-center">
                            <i class="fab fa-whatsapp mr-2"></i>
                            Elegir Sabores
                        </a>
                    </div>
                </div>

                <!-- 48 PREMIUM -->
                <div class="sandwich-card bg-white rounded-2xl overflow-hidden shadow-lg border-2 border-yellow-200">
                    <div class="bg-gradient-to-r from-yellow-600 to-orange-500 p-6 relative">
                        <div class="absolute top-2 right-2 bg-white text-orange-800 px-3 py-1 rounded-full text-xs font-bold">
                            PREMIUM
                        </div>
                        <h3 class="text-2xl font-bold text-white">48 Premium</h3>
                        <p class="text-yellow-100">Pack grande gourmet</p>
                    </div>
                    <div class="p-6">
                        <p class="text-gray-600 mb-6">Pack grande de sabores gourmet. Perfecto para eventos especiales. Podés elegir hasta 6 sabores premium diferentes.</p>
                        <div class="flex items-end justify-between mb-6">
                            <div>
                                <div class="text-3xl font-bold text-orange-600">$44.000</div>
                            </div>
                            <div class="text-right">
                                <div class="text-sm text-gray-500">Hasta 6 sabores</div>
                                <div class="text-xs text-gray-400">8 de cada sabor</div>
                            </div>
                        </div>
                        <a href="https://wa.me/541159813546?text=Hola%20quiero%20pedir%2048%20sándwiches%20premium%20por%20%2444.000%20-%20Sabores:" 
                           target="_blank" 
                           class="w-full bg-gradient-to-r from-yellow-500 to-orange-500 hover:from-yellow-600 hover:to-orange-600 text-white py-3 rounded-xl font-semibold transition-all duration-300 flex items-center justify-center">
                            <i class="fab fa-whatsapp mr-2"></i>
                            Elegir Sabores Premium
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- NUEVA SECCIÓN: SURTIDOS ELEGIDOS -->
        <div class="bg-gradient-to-r from-red-50 via-pink-50 to-red-100 rounded-3xl p-8 mb-20">
            <div class="text-center mb-12">
                <h2 class="text-4xl font-bold text-gray-800 mb-4">
                    🎯 Surtidos Elegidos
                </h2>
                <p class="text-xl text-gray-600 mb-4">¡Vos elegís exactamente lo que querés!</p>
                <p class="text-lg text-red-600 font-medium">Personalizá tu pedido con los sabores que más te gustan</p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
                
                <!-- 48 ELEGIDOS -->
                <div class="bg-white rounded-2xl p-6 shadow-md hover:shadow-xl transition-all duration-300 text-center border-2 border-red-200">
                    <div class="text-4xl mb-3">🥪</div>
                    <h3 class="text-2xl font-bold text-red-600 mb-2">48 Elegidos</h3>
                    <div class="text-3xl font-bold text-red-600 mb-3">$25.000</div>
                    <div class="text-sm text-gray-500 mb-4">Para 15-20 personas</div>
                    <a href="https://wa.me/541159813546?text=Hola%20quiero%20pedir%2048%20sándwiches%20elegidos%20por%20%2425.000%20-%20Sabores:" 
                       target="_blank" 
                       class="w-full bg-red-500 hover:bg-red-600 text-white px-4 py-3 rounded-xl font-semibold transition-all duration-300 flex items-center justify-center">
                        <i class="fab fa-whatsapp mr-2"></i>
                        Personalizar
                    </a>
                </div>

                <!-- 40 ELEGIDOS -->
                <div class="bg-white rounded-2xl p-6 shadow-md hover:shadow-xl transition-all duration-300 text-center">
                    <div class="text-4xl mb-3">🥪</div>
                    <h3 class="text-2xl font-bold text-red-600 mb-2">40 Elegidos</h3>
                    <div class="text-3xl font-bold text-red-600 mb-3">$20.900</div>
                    <div class="text-sm text-gray-500 mb-4">Para 12-15 personas</div>
                    <a href="https://wa.me/541159813546?text=Hola%20quiero%20pedir%2040%20sándwiches%20elegidos%20por%20%2420.900%20-%20Sabores:" 
                       target="_blank" 
                       class="w-full bg-red-500 hover:bg-red-600 text-white px-4 py-3 rounded-xl font-semibold transition-all duration-300 flex items-center justify-center">
                        <i class="fab fa-whatsapp mr-2"></i>
                        Personalizar
                    </a>
                </div>

                <!-- 32 ELEGIDOS -->
                <div class="bg-white rounded-2xl p-6 shadow-md hover:shadow-xl transition-all duration-300 text-center">
                    <div class="text-4xl mb-3">🥪</div>
                    <h3 class="text-2xl font-bold text-red-600 mb-2">32 Elegidos</h3>
                    <div class="text-3xl font-bold text-red-600 mb-3">$16.700</div>
                    <div class="text-sm text-gray-500 mb-4">Para 10-12 personas</div>
                    <a href="https://wa.me/541159813546?text=Hola%20quiero%20pedir%2032%20sándwiches%20elegidos%20por%20%2416.700%20-%20Sabores:" 
                       target="_blank" 
                       class="w-full bg-red-500 hover:bg-red-600 text-white px-4 py-3 rounded-xl font-semibold transition-all duration-300 flex items-center justify-center">
                        <i class="fab fa-whatsapp mr-2"></i>
                        Personalizar
                    </a>
                </div>

                <!-- 24 ELEGIDOS -->
                <div class="bg-white rounded-2xl p-6 shadow-md hover:shadow-xl transition-all duration-300 text-center">
                    <div class="text-4xl mb-3">🥪</div>
                    <h3 class="text-2xl font-bold text-red-600 mb-2">24 Elegidos</h3>
                    <div class="text-3xl font-bold text-red-600 mb-3">$12.500</div>
                    <div class="text-sm text-gray-500 mb-4">Para 8-10 personas</div>
                    <a href="https://wa.me/541159813546?text=Hola%20quiero%20pedir%2024%20sándwiches%20elegidos%20por%20%2412.500%20-%20Sabores:" 
                       target="_blank" 
                       class="w-full bg-red-500 hover:bg-red-600 text-white px-4 py-3 rounded-xl font-semibold transition-all duration-300 flex items-center justify-center">
                        <i class="fab fa-whatsapp mr-2"></i>
                        Personalizar
                    </a>
                </div>

                <!-- 16 ELEGIDOS -->
                <div class="bg-white rounded-2xl p-6 shadow-md hover:shadow-xl transition-all duration-300 text-center">
                    <div class="text-4xl mb-3">🥪</div>
                    <h3 class="text-2xl font-bold text-red-600 mb-2">16 Elegidos</h3>
                    <div class="text-3xl font-bold text-red-600 mb-3">$8.400</div>
                    <div class="text-sm text-gray-500 mb-4">Para 5-6 personas</div>
                    <a href="https://wa.me/541159813546?text=Hola%20quiero%20pedir%2016%20sándwiches%20elegidos%20por%20%248.400%20-%20Sabores:" 
                       target="_blank" 
                       class="w-full bg-red-500 hover:bg-red-600 text-white px-4 py-3 rounded-xl font-semibold transition-all duration-300 flex items-center justify-center">
                        <i class="fab fa-whatsapp mr-2"></i>
                        Personalizar
                    </a>
                </div>

                <!-- 8 ELEGIDOS -->
                <div class="bg-white rounded-2xl p-6 shadow-md hover:shadow-xl transition-all duration-300 text-center">
                    <div class="text-4xl mb-3">🥪</div>
                    <h3 class="text-2xl font-bold text-red-600 mb-2">8 Elegidos</h3>
                    <div class="text-3xl font-bold text-red-600 mb-3">$4.200</div>
                    <div class="text-sm text-gray-500 mb-4">Para 2-3 personas</div>
                    <a href="https://wa.me/541159813546?text=Hola%20quiero%20pedir%208%20sándwiches%20elegidos%20por%20%244.200%20-%20Sabores:" 
                       target="_blank" 
                       class="w-full bg-red-500 hover:bg-red-600 text-white px-4 py-3 rounded-xl font-semibold transition-all duration-300 flex items-center justify-center">
                        <i class="fab fa-whatsapp mr-2"></i>
                        Personalizar
                    </a>
                </div>

            </div>

            <!-- Lista de sabores disponibles -->
            <div class="bg-white rounded-2xl p-8 shadow-lg">
                <h4 class="text-2xl font-bold text-gray-800 mb-6 text-center">🥪 Todos los sabores disponibles</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <div>
                        <h5 class="text-xl font-semibold text-blue-600 mb-4 flex items-center">
                            <i class="fas fa-star mr-2"></i>
                            Sabores Clásicos
                        </h5>
                        <div class="space-y-2">
                            <div class="flex items-center p-2 bg-blue-50 rounded-lg">
                                <i class="fas fa-check-circle text-blue-500 mr-3"></i>
                                <span class="font-medium">Jamón y Queso</span>
                            </div>
                            <div class="flex items-center p-2 bg-blue-50 rounded-lg">
                                <i class="fas fa-check-circle text-blue-500 mr-3"></i>
                                <span class="font-medium">Lechuga y Tomate</span>
                            </div>
                            <div class="flex items-center p-2 bg-blue-50 rounded-lg">
                                <i class="fas fa-check-circle text-blue-500 mr-3"></i>
                                <span class="font-medium">Huevo</span>
                            </div>
                            <div class="flex items-center p-2 bg-blue-50 rounded-lg">
                                <i class="fas fa-check-circle text-blue-500 mr-3"></i>
                                <span class="font-medium">Choclo</span>
                            </div>
                            <div class="flex items-center p-2 bg-blue-50 rounded-lg">
                                <i class="fas fa-check-circle text-blue-500 mr-3"></i>
                                <span class="font-medium">Aceitunas</span>
                            </div>
                        </div>
                    </div>
                    <div>
                        <h5 class="text-xl font-semibold text-yellow-600 mb-4 flex items-center">
                            <i class="fas fa-crown mr-2"></i>
                            Sabores Premium
                        </h5>
                        <div class="grid grid-cols-2 gap-2">
                            <div class="flex items-center p-2 bg-yellow-50 rounded-lg">
                                <i class="fas fa-check-circle text-yellow-500 mr-2"></i>
                                <span class="font-medium text-sm">Ananá</span>
                            </div>
                            <div class="flex items-center p-2 bg-yellow-50 rounded-lg">
                                <i class="fas fa-check-circle text-yellow-500 mr-2"></i>
                                <span class="font-medium text-sm">Atún</span>
                            </div>
                            <div class="flex items-center p-2 bg-yellow-50 rounded-lg">
                                <i class="fas fa-check-circle text-yellow-500 mr-2"></i>
                                <span class="font-medium text-sm">Berenjena</span>
                            </div>
                            <div class="flex items-center p-2 bg-yellow-50 rounded-lg">
                                <i class="fas fa-check-circle text-yellow-500 mr-2"></i>
                                <span class="font-medium text-sm">Durazno</span>
                            </div>
                            <div class="flex items-center p-2 bg-yellow-50 rounded-lg">
                                <i class="fas fa-check-circle text-yellow-500 mr-2"></i>
                                <span class="font-medium text-sm">Jamón Crudo</span>
                            </div>
                            <div class="flex items-center p-2 bg-yellow-50 rounded-lg">
                                <i class="fas fa-check-circle text-yellow-500 mr-2"></i>
                                <span class="font-medium text-sm">Morrón</span>
                            </div>
                            <div class="flex items-center p-2 bg-yellow-50 rounded-lg">
                                <i class="fas fa-check-circle text-yellow-500 mr-2"></i>
                                <span class="font-medium text-sm">Palmito</span>
                            </div>
                            <div class="flex items-center p-2 bg-yellow-50 rounded-lg">
                                <i class="fas fa-check-circle text-yellow-500 mr-2"></i>
                                <span class="font-medium text-sm">Panceta</span>
                            </div>
                            <div class="flex items-center p-2 bg-yellow-50 rounded-lg">
                                <i class="fas fa-check-circle text-yellow-500 mr-2"></i>
                                <span class="font-medium text-sm">Pollo</span>
                            </div>
                            <div class="flex items-center p-2 bg-yellow-50 rounded-lg">
                                <i class="fas fa-check-circle text-yellow-500 mr-2"></i>
                                <span class="font-medium text-sm">Roquefort</span>
                            </div>
                            <div class="flex items-center p-2 bg-yellow-50 rounded-lg">
                                <i class="fas fa-check-circle text-yellow-500 mr-2"></i>
                                <span class="font-medium text-sm">Salame</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="text-center mt-6 p-4 bg-gray-100 rounded-lg">
                    <p class="text-gray-600 font-medium">
                        💡 <strong>¿Cómo funciona?</strong><br>
                        Elegí la cantidad que querés y después nos decís exactamente qué sabores preferís. 
                        Podés mezclar clásicos y premium como más te guste.
                    </p>
                </div>
            </div>
        </div>

        <!-- Información de delivery actualizada -->
        <div class="bg-gradient-to-r from-green-100 to-blue-100 rounded-3xl p-8 mb-20">
            <div class="text-center mb-12">
                <h2 class="text-4xl font-bold text-gray-800 mb-4">
                    🚚 Delivery - Turnos Disponibles
                </h2>
                <p class="text-xl text-gray-600">Elegí el horario que mejor te convenga</p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="bg-white rounded-2xl p-8 text-center shadow-lg hover:shadow-xl transition-all duration-300">
                    <div class="text-6xl mb-4">🌅</div>
                    <h3 class="text-2xl font-bold text-blue-600 mb-2">Mañana</h3>
                    <p class="text-lg text-gray-600 mb-4">9:00 - 11:30</p>
                    <p class="text-sm text-gray-500 mb-6">Perfecto para el desayuno o almuerzo temprano</p>
                    <a href="https://wa.me/541159813546?text=Hola%20quiero%20hacer%20un%20pedido%20para%20el%20turno%20MAÑANA%20(9:00-11:30)" 
                       target="_blank" 
                       class="w-full bg-blue-500 hover:bg-blue-600 text-white py-3 rounded-xl font-semibold transition-all duration-300 flex items-center justify-center">
                        <i class="fab fa-whatsapp mr-2"></i>
                        Pedir para Mañana
                    </a>
                </div>
                
                <div class="bg-white rounded-2xl p-8 text-center shadow-lg hover:shadow-xl transition-all duration-300 border-2 border-orange-200">
                    <div class="text-6xl mb-4">☕</div>
                    <h3 class="text-2xl font-bold text-orange-600 mb-2">Merienda</h3>
                    <p class="text-lg text-gray-600 mb-4">15:00 - 17:00</p>
                    <p class="text-sm text-gray-500 mb-6">Ideal para la merienda o reuniones de tarde</p>
                    <a href="https://wa.me/541159813546?text=Hola%20quiero%20hacer%20un%20pedido%20para%20el%20turno%20MERIENDA%20(15:00-17:00)" 
                       target="_blank" 
                       class="w-full bg-orange-500 hover:bg-orange-600 text-white py-3 rounded-xl font-semibold transition-all duration-300 flex items-center justify-center">
                        <i class="fab fa-whatsapp mr-2"></i>
                        Pedir para Merienda
                    </a>
                </div>
                
                <div class="bg-white rounded-2xl p-8 text-center shadow-lg hover:shadow-xl transition-all duration-300">
                    <div class="text-6xl mb-4">🌆</div>
                    <h3 class="text-2xl font-bold text-purple-600 mb-2">Tarde</h3>
                    <p class="text-lg text-gray-600 mb-4">18:00 - 20:00</p>
                    <p class="text-sm text-gray-500 mb-6">Perfecto para la cena o eventos nocturnos</p>
                    <a href="https://wa.me/541159813546?text=Hola%20quiero%20hacer%20un%20pedido%20para%20el%20turno%20TARDE%20(18:00-20:00)" 
                       target="_blank" 
                       class="w-full bg-purple-500 hover:bg-purple-600 text-white py-3 rounded-xl font-semibold transition-all duration-300 flex items-center justify-center">
                        <i class="fab fa-whatsapp mr-2"></i>
                        Pedir para Tarde
                    </a>
                </div>
            </div>
            
            <div class="text-center mt-8">
                <div class="bg-white rounded-xl p-6 shadow-md">
                    <p class="text-lg text-gray-600 mb-2">
                        <i class="fas fa-map-marker-alt text-green-500 mr-2"></i>
                        <strong>Zona de delivery:</strong> La Plata y alrededores
                    </p>
                    <p class="text-sm text-gray-500">
                        Coordinamos el horario exacto por WhatsApp según tu ubicación y disponibilidad
                    </p>
                </div>
            </div>
        </div>

        <!-- Información adicional -->
        <div class="bg-gray-800 text-white rounded-3xl p-8 mb-20">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="text-center">
                    <div class="text-5xl mb-4">💳</div>
                    <h3 class="text-xl font-bold mb-4">Formas de Pago</h3>
                    <ul class="space-y-2 text-gray-300">
                        <li class="flex items-center justify-center">
                            <i class="fas fa-money-bill-wave text-green-400 mr-2"></i>
                            <span><strong>Efectivo:</strong> Con descuentos especiales</span>
                        </li>
                        <li class="flex items-center justify-center">
                            <i class="fas fa-university text-blue-400 mr-2"></i>
                            <span><strong>Transferencia:</strong> Todos los bancos</span>
                        </li>
                        <li class="flex items-center justify-center">
                            <i class="fas fa-credit-card text-purple-400 mr-2"></i>
                            <span><strong>MercadoPago:</strong> Disponible</span>
                        </li>
                    </ul>
                </div>
                
                <div class="text-center">
                    <div class="text-5xl mb-4">🏪</div>
                    <h3 class="text-xl font-bold mb-4">Retiro en Local</h3>
                    <div class="text-gray-300 space-y-2">
                        <p><strong>Dirección:</strong><br>Camino General Manuel Belgrano 7241, J.M. Gutierrez, Buenos Aires, Argentina</p>
                        <p><strong>Horarios:</strong><br>Lunes a Domingo<br>9:00 a 21:00hs</p>
                        <p class="text-sm bg-gray-700 p-2 rounded">
                            💡 Coordiná tu horario de retiro por WhatsApp
                        </p>
                    </div>
                </div>
                
                <div class="text-center">
                    <div class="text-5xl mb-4">📞</div>
                    <h3 class="text-xl font-bold mb-4">Contacto</h3>
                    <ul class="space-y-2 text-gray-300">
                        <li class="flex items-center justify-center">
                            <i class="fab fa-whatsapp text-green-400 mr-2"></i>
                            <span><strong>WhatsApp:</strong> 11 5981-3546</span>
                        </li>
                        <li class="flex items-center justify-center">
                            <i class="fab fa-instagram text-pink-400 mr-2"></i>
                            <span><strong>Instagram:</strong> @sandwicheriasantacatalina</span>
                        </li>
                        <li class="flex items-center justify-center">
                            <i class="fas fa-envelope text-yellow-400 mr-2"></i>
                            <span><strong>Email:</strong> info@santacatalina.com.ar</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Testimonios -->
        <div class="text-center mb-16">
            <h2 class="text-4xl font-bold gradient-text mb-12">Lo que dicen nuestros clientes</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="bg-white p-6 rounded-2xl shadow-lg">
                    <div class="text-yellow-400 text-3xl mb-4">⭐⭐⭐⭐⭐</div>
                    <p class="text-gray-600 mb-4 italic">"Los mejores triples de La Plata! Siempre frescos y con ingredientes de primera calidad."</p>
                    <p class="font-semibold text-gray-800">- María González</p>
                </div>
                <div class="bg-white p-6 rounded-2xl shadow-lg">
                    <div class="text-yellow-400 text-3xl mb-4">⭐⭐⭐⭐⭐</div>
                    <p class="text-gray-600 mb-4 italic">"El delivery siempre puntual y los sabores premium son increíbles. Recomendadísimos!"</p>
                    <p class="font-semibold text-gray-800">- Carlos Pérez</p>
                </div>
                <div class="bg-white p-6 rounded-2xl shadow-lg">
                    <div class="text-yellow-400 text-3xl mb-4">⭐⭐⭐⭐⭐</div>
                    <p class="text-gray-600 mb-4 italic">"Para eventos son perfectos. Gran variedad y precios accesibles. Los elegidos son geniales!"</p>
                    <p class="font-semibold text-gray-800">- Ana Martínez</p>
                </div>
            </div>
        </div>

    </main>

    <!-- Sección de accesos para empleados y admin - ELIMINADA PORQUE SE MOVIÓ ARRIBA -->

    <!-- Footer -->
    <footer class="bg-gray-900 text-white py-12">
        <div class="container mx-auto px-4">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                <div>
                    <h3 class="text-xl font-bold mb-4">Sandwichería Santa Catalina</h3>
                    <p class="text-gray-300 mb-4">Los mejores sándwiches triples de La Plata desde 2020. Ingredientes frescos, sabor casero.</p>
                    <div class="flex space-x-4">
                        <a href="https://wa.me/541159813546" target="_blank" class="text-green-400 hover:text-green-300 text-2xl">
                            <i class="fab fa-whatsapp"></i>
                        </a>
                        <a href="https://instagram.com/sandwicheriasantacatalina" target="_blank" class="text-pink-400 hover:text-pink-300 text-2xl">
                            <i class="fab fa-instagram"></i>
                        </a>
                        <a href="https://facebook.com/sandwicheriasantacatalina" target="_blank" class="text-blue-400 hover:text-blue-300 text-2xl">
                            <i class="fab fa-facebook"></i>
                        </a>
                    </div>
                </div>
                
                <div>
                    <h4 class="text-lg font-semibold mb-4">Productos</h4>
                    <ul class="space-y-2 text-gray-300">
                        <li><a href="#" class="hover:text-white transition">Jamón y Queso</a></li>
                        <li><a href="#" class="hover:text-white transition">Surtidos Clásicos</a></li>
                        <li><a href="#" class="hover:text-white transition">Surtidos Especiales</a></li>
                        <li><a href="#" class="hover:text-white transition">Premium Gourmet</a></li>
                        <li><a href="#" class="hover:text-white transition">Surtidos Elegidos</a></li>
                    </ul>
                </div>
                
                <div>
                    <h4 class="text-lg font-semibold mb-4">Servicios</h4>
                    <ul class="space-y-2 text-gray-300">
                        <li><a href="#" class="hover:text-white transition">Delivery</a></li>
                        <li><a href="#" class="hover:text-white transition">Retiro en Local</a></li>
                        <li><a href="#" class="hover:text-white transition">Eventos Corporativos</a></li>
                        <li><a href="#" class="hover:text-white transition">Cumpleaños</a></li>
                        <li><a href="#" class="hover:text-white transition">Catering</a></li>
                    </ul>
                </div>
                
                <div>
                    <h4 class="text-lg font-semibold mb-4">Contacto</h4>
                    <ul class="space-y-2 text-gray-300">
                        <li><i class="fas fa-map-marker-alt mr-2"></i>Camino General Manuel Belgrano 7241, J.M. Gutierrez, Buenos Aires, Argentina</li>
                        <li><i class="fab fa-whatsapp mr-2"></i>11 5981-3546</li>
                        <li><i class="fas fa-envelope mr-2"></i>info@santacatalina.com.ar</li>
                        <li><i class="fas fa-clock mr-2"></i>Lun-Dom: 9:00-21:00hs</li>
                    </ul>
                </div>
            </div>
            
            <div class="border-t border-gray-700 mt-8 pt-8 text-center text-gray-400">
                <p>&copy; 2024 Sandwichería Santa Catalina. Todos los derechos reservados.</p>
                <p class="text-sm mt-2">Hecho con ❤️ en Gutiérrez Buenos Aires</p>
            </div>
        </div>
    </footer>

    <!-- Botón flotante de WhatsApp -->
    <a href="https://wa.me/541159813546?text=Hola%20quiero%20hacer%20un%20pedido" 
       target="_blank" 
       class="whatsapp-fixed bg-green-500 hover:bg-green-600 text-white rounded-full p-4 shadow-2xl">
        <i class="fab fa-whatsapp text-3xl"></i>
    </a>

    <!-- JavaScript para interactividad -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Asegurar que los botones de admin y empleados funcionen
            const empleadosBtn = document.querySelector('a[href="empleados/login.php"]');
            const adminBtn = document.querySelector('a[href="admin/login.php"]');
            
            if (empleadosBtn) {
                empleadosBtn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    window.location.href = 'empleados/login.php';
                });
            }
            
            if (adminBtn) {
                adminBtn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    window.location.href = 'admin/login.php';
                });
            }
            
            // Destacar turnos según la hora actual
            const hora = new Date().getHours();
            
            if (hora >= 6 && hora < 11) {
                // Destacar turno mañana
                const turnoMañana = document.querySelector('a[href*="MAÑANA"]').closest('div');
                turnoMañana.classList.add('ring-4', 'ring-green-300', 'bg-green-50');
                turnoMañana.querySelector('a').innerHTML = '<i class="fab fa-whatsapp mr-2"></i>Disponible Ahora 🟢';
            } else if (hora >= 11 && hora < 15) {
                // Próximo turno merienda  
                const turnoMerienda = document.querySelector('a[href*="MERIENDA"]').closest('div');
                turnoMerienda.classList.add('ring-4', 'ring-orange-300', 'bg-orange-50');
                turnoMerienda.querySelector('a').innerHTML = '<i class="fab fa-whatsapp mr-2"></i>Próximo Turno ⏳';
            } else if (hora >= 15 && hora < 20) {
                // Destacar turno tarde
                const turnoTarde = document.querySelector('a[href*="TARDE"]').closest('div');
                turnoTarde.classList.add('ring-4', 'ring-green-300', 'bg-green-50');
                turnoTarde.querySelector('a').innerHTML = '<i class="fab fa-whatsapp mr-2"></i>Disponible Ahora 🟢';
            }
            
            // Smooth scroll para anclas
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function (e) {
                    e.preventDefault();
                    const target = document.querySelector(this.getAttribute('href'));
                    if (target) {
                        target.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                });
            });
            
            // Animación de cards al hacer hover
            const cards = document.querySelectorAll('.sandwich-card');
            cards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-8px) scale(1.02)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0) scale(1)';
                });
            });
            
            // Mostrar ofertas especiales según el día
            const dia = new Date().getDay();
            if (dia === 5 || dia === 6) { // Viernes o Sábado
                // Destacar ofertas de fin de semana
                document.querySelectorAll('.line-through').forEach(oferta => {
                    oferta.parentElement.parentElement.classList.add('ring-2', 'ring-yellow-300');
                });
            }
            
            // Contador de productos más populares
            const popularProducts = ['48 Jamón y Queso', '24 Surtidos Clásicos', '48 Surtidos Premium'];
            popularProducts.forEach(product => {
                const cards = document.querySelectorAll('.sandwich-card h3');
                cards.forEach(title => {
                    if (title.textContent.includes(product.split(' ').pop())) {
                        const popularBadge = document.createElement('span');
                        popularBadge.className = 'absolute top-2 left-2 bg-red-500 text-white px-2 py-1 rounded-full text-xs font-bold';
                        popularBadge.textContent = '🔥 Popular';
                        title.parentElement.style.position = 'relative';
                        title.parentElement.appendChild(popularBadge);
                    }
                });
            });
            
        });
        
        // Función para tracking de clics (opcional para analytics)
        function trackOrder(product, price) {
            console.log(`Pedido iniciado: ${product} - ${price}`);
            // Aquí puedes agregar Google Analytics o similar
            // gtag('event', 'begin_checkout', { value: price, currency: 'ARS' });
        }
        
        // Agregar tracking a todos los botones de pedido
        document.querySelectorAll('a[href*="wa.me"]').forEach(button => {
            button.addEventListener('click', function() {
                const productName = this.closest('.sandwich-card')?.querySelector('h3')?.textContent || 'Producto desconocido';
                const price = this.href.match(/\$(\d+\.?\d*)/)?.[1] || '0';
                trackOrder(productName, price);
            });
        });
        
        // Efecto parallax suave en el hero - DESACTIVADO para evitar que tape contenido
        /*
        window.addEventListener('scroll', function() {
            const scrolled = window.pageYOffset;
            const hero = document.querySelector('.hero-bg');
            if (hero) {
                hero.style.transform = `translateY(${scrolled * 0.5}px)`;
            }
        });
        */
        
        // Lazy loading para imágenes (si se agregan después)
        if ('IntersectionObserver' in window) {
            const imageObserver = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        img.src = img.dataset.src;
                        img.classList.remove('lazy');
                        imageObserver.unobserve(img);
                    }
                });
            });
            
            document.querySelectorAll('img[data-src]').forEach(img => {
                imageObserver.observe(img);
            });
        }
    </script>

    <!-- Schema.org para SEO -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "Restaurant",
        "name": "Sandwichería Santa Catalina",
        "description": "Los mejores sándwiches triples de Buenos Aires",
        "address": {
            "@type": "PostalAddress",
            "streetAddress": "Camino General Manuel Belgrano 7241, J.M. Gutierrez, Buenos Aires, Argentina",
            "addressLocality": "Gutierrez",
            "addressRegion": "Buenos Aires",
            "addressCountry": "AR"
        },
        "telephone": "+541159813546",
        "openingHours": "Mo-Su 09:00-21:00",
        "servesCuisine": "Sandwiches",
        "priceRange": "$",
        "acceptsReservations": false,
        "hasDeliveryService": true,
        "menu": "https://wa.me/541159813546",
        "paymentAccepted": ["Cash", "Credit Card", "Bank Transfer"],
        "currenciesAccepted": "ARS"
    }
    </script>

    <!-- Google Analytics (opcional - reemplazar con tu ID) -->
    <!-- 
    <script async src="https://www.googletagmanager.com/gtag/js?id=GA_MEASUREMENT_ID"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', 'GA_MEASUREMENT_ID');
    </script>
    -->

    <!-- Facebook Pixel (opcional) -->
    <!--
    <script>
        !function(f,b,e,v,n,t,s)
        {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
        n.callMethod.apply(n,arguments):n.queue.push(arguments)};
        if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
        n.queue=[];t=b.createElement(e);t.async=!0;
        t.src=v;s=b.getElementsByTagName(e)[0];
        s.parentNode.insertBefore(t,s)}(window, document,'script',
        'https://connect.facebook.net/en_US/fbevents.js');
        fbq('init', 'YOUR_PIXEL_ID');
        fbq('track', 'PageView');
    </script>
    -->

</body>
</html>