<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Santa Catalina - Los Mejores Sándwiches de La Plata</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            overflow-x: hidden;
            background: linear-gradient(135deg, #FDBA74 0%, #FB923C 50%, #F97316 100%);
        }
        
        .hero-gradient {
            background: linear-gradient(135deg, #FF6B35 0%, #F7931E 50%, #FDC830 100%);
            animation: gradientShift 10s ease infinite;
            background-size: 400% 400%;
            position: relative;
        }
        
        @keyframes gradientShift {
            0%, 100% { background-position: 0% 50%; }
            25% { background-position: 100% 50%; }
            50% { background-position: 100% 100%; }
            75% { background-position: 0% 100%; }
        }
        
        .hero-pattern {
            background-image: 
                radial-gradient(circle at 20% 50%, rgba(255, 255, 255, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(255, 255, 255, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 40% 20%, rgba(255, 255, 255, 0.08) 0%, transparent 50%);
            animation: patternMove 20s ease infinite;
        }
        
        @keyframes patternMove {
            0%, 100% { transform: translateY(0) scale(1); }
            50% { transform: translateY(-20px) scale(1.05); }
        }
        
        .card-epic {
            transition: all 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
            overflow: hidden;
        }
        
        .card-epic::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(
                45deg,
                transparent,
                rgba(255, 255, 255, 0.1),
                transparent
            );
            transform: rotate(45deg);
            transition: all 0.5s;
        }
        
        .card-epic:hover::before {
            animation: shine 1.5s ease;
        }
        
        @keyframes shine {
            0% { transform: translateX(-100%) rotate(45deg); }
            100% { transform: translateX(100%) rotate(45deg); }
        }
        
        .card-epic:hover {
            transform: translateY(-10px) scale(1.02);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }
        
        .pulse-icon {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        
        @keyframes pulse {
            0%, 100% { 
                opacity: 1;
                transform: scale(1);
            }
            50% { 
                opacity: .8;
                transform: scale(1.05);
            }
        }
        
        .float-animation {
            animation: float 4s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            25% { transform: translateY(-15px) rotate(-5deg); }
            50% { transform: translateY(-10px) rotate(0deg); }
            75% { transform: translateY(-15px) rotate(5deg); }
        }
        
        .sandwich-icon {
            font-size: 4rem;
            filter: drop-shadow(0 10px 20px rgba(0,0,0,0.3));
            animation: bounce 2s ease-in-out infinite;
        }
        
        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-20px); }
        }
        
        .whatsapp-btn {
            background: linear-gradient(135deg, #25D366 0%, #128C7E 100%);
            transition: all 0.4s ease;
            position: relative;
            overflow: hidden;
        }
        
        .whatsapp-btn::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }
        
        .whatsapp-btn:hover::before {
            width: 300px;
            height: 300px;
        }
        
        .whatsapp-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 15px 35px rgba(37, 211, 102, 0.5);
        }
        
        .badge-premium {
            background: linear-gradient(135deg, #FFD700, #FFA500);
            animation: shimmer 3s infinite;
            box-shadow: 0 5px 15px rgba(255, 215, 0, 0.5);
        }
        
        @keyframes shimmer {
            0%, 100% { 
                opacity: 1;
                box-shadow: 0 5px 15px rgba(255, 215, 0, 0.5);
            }
            50% { 
                opacity: 0.85;
                box-shadow: 0 8px 25px rgba(255, 215, 0, 0.7);
            }
        }
        
        .sabor-tag {
            transition: all 0.3s ease;
            cursor: default;
        }
        
        .sabor-tag:hover {
            transform: scale(1.08) rotate(-2deg);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }
        
        .text-glow {
            text-shadow: 0 0 20px rgba(255, 255, 255, 0.5),
                         0 0 40px rgba(255, 255, 255, 0.3);
        }
        
        .floating-emoji {
            position: absolute;
            animation: floatRandom 8s ease-in-out infinite;
            opacity: 0.12;
            font-size: 3rem;
        }
        
        @keyframes floatRandom {
            0%, 100% { 
                transform: translate(0, 0) rotate(0deg);
            }
            25% { 
                transform: translate(30px, -30px) rotate(10deg);
            }
            50% { 
                transform: translate(-20px, -50px) rotate(-10deg);
            }
            75% { 
                transform: translate(40px, -70px) rotate(5deg);
            }
        }
        
        .admin-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            z-index: 50;
            transition: all 0.3s ease;
        }
        
        .admin-btn:hover {
            transform: scale(1.1) rotate(5deg);
        }
        
        .empleado-btn {
            position: fixed;
            bottom: 100px;
            right: 30px;
            z-index: 50;
            transition: all 0.3s ease;
        }
        
        .empleado-btn:hover {
            transform: scale(1.1) rotate(-5deg);
        }
        
        /* Ocultar botones en móviles */
        @media (max-width: 768px) {
            .admin-btn,
            .empleado-btn {
                display: none;
            }
        }
        
        .fire-effect {
            animation: fire 2s ease-in-out infinite;
        }
        
        @keyframes fire {
            0%, 100% { 
                filter: hue-rotate(0deg) brightness(1);
            }
            50% { 
                filter: hue-rotate(30deg) brightness(1.2);
            }
        }
        
        .section-divider {
            height: 3px;
            background: linear-gradient(90deg, transparent, #FF6B35, #F7931E, #FDC830, transparent);
            animation: dividerMove 3s ease infinite;
            background-size: 200% 100%;
        }
        
        @keyframes dividerMove {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }
    </style>
</head>
<body>
    
    <!-- Botones de Acceso Admin y Empleado (solo desktop) -->
    <a href="admin/login.php" class="admin-btn bg-gradient-to-r from-purple-600 to-purple-800 text-white px-5 py-2.5 rounded-full shadow-2xl hover:shadow-purple-500/50 flex items-center gap-2 font-bold text-sm">
        <i class="fas fa-user-shield text-lg"></i>
        <span>Admin</span>
    </a>
    
    <a href="empleados/login.php" class="empleado-btn bg-gradient-to-r from-blue-600 to-blue-800 text-white px-5 py-2.5 rounded-full shadow-2xl hover:shadow-blue-500/50 flex items-center gap-2 font-bold text-sm">
        <i class="fas fa-user-tie text-lg"></i>
        <span>Empleado</span>
    </a>

    <!-- Header Hero -->
    <header class="hero-gradient hero-pattern text-white py-16 md:py-20 px-4 relative overflow-hidden">
        <!-- Emojis flotantes decorativos -->
        <div class="floating-emoji" style="top: 10%; left: 10%; animation-delay: 0s;">🥪</div>
        <div class="floating-emoji" style="top: 60%; left: 5%; animation-delay: 1s;">🧀</div>
        <div class="floating-emoji" style="top: 20%; right: 15%; animation-delay: 2s;">🥖</div>
        <div class="floating-emoji" style="top: 70%; right: 10%; animation-delay: 3s;">🥓</div>
        <div class="floating-emoji" style="top: 40%; left: 80%; animation-delay: 1.5s;">🍅</div>
        
        <div class="container mx-auto text-center relative z-10">
            <div class="float-animation inline-block mb-6">
                <div class="sandwich-icon fire-effect">🥪</div>
            </div>
            
            <h1 class="text-4xl md:text-6xl lg:text-7xl font-black mb-4 drop-shadow-2xl" style="color: white; text-shadow: 0 4px 20px rgba(0,0,0,0.4), 0 0 40px rgba(255,255,255,0.5);">
                Santa Catalina
            </h1>
            <div class="h-2 w-32 md:w-48 mx-auto mb-6 section-divider rounded-full"></div>
            <p class="text-xl md:text-2xl lg:text-3xl font-bold mb-3 drop-shadow-lg" style="color: white; text-shadow: 0 3px 15px rgba(0,0,0,0.5);">
                🔥 Los Mejores Sándwiches de La Plata 🔥
            </p>
            <p class="text-base md:text-lg mb-8 max-w-3xl mx-auto drop-shadow px-4" style="color: white; text-shadow: 0 2px 10px rgba(0,0,0,0.4);">
                Triples frescos y artesanales hechos al momento con ingredientes de <span class="font-black" style="color: #FEF08A; text-shadow: 0 2px 10px rgba(0,0,0,0.6);">PRIMERA CALIDAD</span>
            </p>
            
            <div class="flex flex-col md:flex-row gap-3 md:gap-4 justify-center items-center mb-8 px-4">
                <div class="flex items-center bg-white/20 backdrop-blur-md px-5 md:px-6 py-2.5 md:py-3 rounded-full shadow-xl border-2 border-white/30">
                    <i class="fas fa-map-marker-alt mr-2 text-lg md:text-xl" style="color: white;"></i>
                    <span class="text-sm md:text-base font-bold" style="color: white; text-shadow: 0 2px 8px rgba(0,0,0,0.4);">Gutierrez, La Plata</span>
                </div>
                <div class="flex items-center bg-white/20 backdrop-blur-md px-5 md:px-6 py-2.5 md:py-3 rounded-full shadow-xl border-2 border-white/30">
                    <i class="fas fa-phone mr-2 text-lg md:text-xl" style="color: white;"></i>
                    <span class="text-sm md:text-base font-bold" style="color: white; text-shadow: 0 2px 8px rgba(0,0,0,0.4);">11 5981-3546</span>
                </div>
            </div>
            
            <div class="relative inline-block px-4">
                <div class="absolute inset-0 bg-green-400 blur-2xl opacity-50 animate-pulse"></div>
                <a href="https://wa.me/541159813546?text=Hola! Quiero hacer un pedido de sándwiches 🥪" 
                   target="_blank" 
                   class="whatsapp-btn relative inline-flex items-center px-6 md:px-10 py-3 md:py-4 text-lg md:text-xl font-black rounded-full shadow-2xl pulse-icon">
                    <i class="fab fa-whatsapp mr-2 md:mr-3 text-2xl"></i>
                    ¡PEDÍ POR WHATSAPP!
                </a>
            </div>
        </div>
    </header>

    <!-- Productos Principales -->
    <main class="container mx-auto px-4 py-12 md:py-16">
        
        <!-- Título Principal -->
        <div class="text-center mb-16 px-4">
            <div class="text-5xl md:text-6xl mb-4 inline-block fire-effect">🍽️</div>
            <h2 class="text-3xl md:text-4xl lg:text-5xl font-black mb-4" style="background: linear-gradient(135deg, #FF6B35 0%, #F7931E 50%, #FDC830 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;">
                NUESTROS SÁNDWICHES
            </h2>
            <div class="h-2 w-32 md:w-48 mx-auto mb-4 section-divider rounded-full"></div>
            <p class="text-base md:text-lg max-w-3xl mx-auto font-semibold" style="color: #374151;">
                Elegí tu favorito y disfrutá de la <span class="font-black" style="color: #EA580C;">MEJOR CALIDAD</span> en cada bocado
            </p>
        </div>

        <!-- Grid de Productos -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 md:gap-8 mb-16">
            
            <!-- 24 Jamón y Queso -->
            <div class="card-epic bg-white rounded-3xl overflow-hidden shadow-xl">
                <div class="bg-gradient-to-br from-orange-400 via-orange-500 to-orange-600 p-6 md:p-8 text-white relative">
                    <div class="text-4xl md:text-5xl mb-3 float-animation">🧀</div>
                    <h3 class="text-xl md:text-2xl font-black mb-2">24 Jamón y Queso</h3>
                    <p class="text-orange-100 text-base md:text-lg font-semibold">El clásico de siempre</p>
                    <div class="absolute top-3 right-3 bg-white text-orange-600 px-3 py-1.5 rounded-full font-black text-xs shadow-lg pulse-icon">
                        ⭐ POPULAR
                    </div>
                </div>
                <div class="p-5 md:p-6">
                    <p class="text-gray-700 text-sm md:text-base mb-5 leading-relaxed">
                        Triple de <span class="font-bold text-orange-600">jamón cocido</span> y <span class="font-bold text-orange-600">queso cremoso</span>. Simple, delicioso e infalible.
                    </p>
                    <a href="https://wa.me/541159813546?text=Hola! Quiero pedir 24 sándwiches de Jamón y Queso 🧀" 
                       target="_blank" 
                       class="whatsapp-btn w-full py-3 md:py-3.5 rounded-2xl text-white font-black text-base md:text-lg flex items-center justify-center relative z-10">
                        <i class="fab fa-whatsapp mr-2 text-xl"></i>
                        PEDIR AHORA
                    </a>
                </div>
            </div>

            <!-- 48 Jamón y Queso -->
            <div class="card-epic bg-white rounded-3xl overflow-hidden shadow-xl">
                <div class="bg-gradient-to-br from-orange-500 via-red-500 to-red-600 p-6 md:p-8 text-white relative">
                    <div class="text-4xl md:text-5xl mb-3 float-animation">🥪</div>
                    <h3 class="text-xl md:text-2xl font-black mb-2">48 Jamón y Queso</h3>
                    <p class="text-orange-100 text-base md:text-lg font-semibold">Pack completo</p>
                    <div class="absolute top-3 right-3 bg-white text-red-600 px-3 py-1.5 rounded-full font-black text-xs shadow-lg">
                        📦 PACK XL
                    </div>
                </div>
                <div class="p-5 md:p-6">
                    <p class="text-gray-700 text-sm md:text-base mb-5 leading-relaxed">
                        Para eventos grandes o para guardar. <span class="font-bold text-red-600">6 planchas</span> de puro sabor.
                    </p>
                    <a href="https://wa.me/541159813546?text=Hola! Quiero pedir 48 sándwiches de Jamón y Queso 🥪" 
                       target="_blank" 
                       class="whatsapp-btn w-full py-3 md:py-3.5 rounded-2xl text-white font-black text-base md:text-lg flex items-center justify-center relative z-10">
                        <i class="fab fa-whatsapp mr-2 text-xl"></i>
                        PEDIR AHORA
                    </a>
                </div>
            </div>

            <!-- 24 Surtidos Clásicos -->
            <div class="card-epic bg-white rounded-3xl overflow-hidden shadow-xl">
                <div class="bg-gradient-to-br from-blue-500 via-blue-600 to-blue-700 p-6 md:p-8 text-white">
                    <div class="text-4xl md:text-5xl mb-3 float-animation">🎯</div>
                    <h3 class="text-xl md:text-2xl font-black mb-2">24 Surtidos Clásicos</h3>
                    <p class="text-blue-100 text-base md:text-lg font-semibold">Variedad tradicional</p>
                </div>
                <div class="p-5 md:p-6">
                    <div class="mb-4">
                        <p class="font-bold text-gray-900 mb-2 text-sm md:text-base">Sabores incluidos:</p>
                        <div class="flex flex-wrap gap-1.5">
                            <span class="sabor-tag bg-gradient-to-r from-blue-100 to-blue-200 text-blue-900 px-2.5 py-1 rounded-lg font-bold shadow-md text-xs">🧀 J&Q</span>
                            <span class="sabor-tag bg-gradient-to-r from-blue-100 to-blue-200 text-blue-900 px-2.5 py-1 rounded-lg font-bold shadow-md text-xs">🥬 Lechuga</span>
                            <span class="sabor-tag bg-gradient-to-r from-blue-100 to-blue-200 text-blue-900 px-2.5 py-1 rounded-lg font-bold shadow-md text-xs">🍅 Tomate</span>
                            <span class="sabor-tag bg-gradient-to-r from-blue-100 to-blue-200 text-blue-900 px-2.5 py-1 rounded-lg font-bold shadow-md text-xs">🥚 Huevo</span>
                        </div>
                    </div>
                    <a href="https://wa.me/541159813546?text=Hola! Quiero pedir 24 Surtidos Clásicos 🎯" 
                       target="_blank" 
                       class="whatsapp-btn w-full py-3 md:py-3.5 rounded-2xl text-white font-black text-base md:text-lg flex items-center justify-center relative z-10">
                        <i class="fab fa-whatsapp mr-2 text-xl"></i>
                        PEDIR AHORA
                    </a>
                </div>
            </div>

            <!-- 48 Surtidos Clásicos -->
            <div class="card-epic bg-white rounded-3xl overflow-hidden shadow-xl">
                <div class="bg-gradient-to-br from-blue-600 via-indigo-600 to-indigo-700 p-6 md:p-8 text-white">
                    <div class="text-4xl md:text-5xl mb-3 float-animation">🥙</div>
                    <h3 class="text-xl md:text-2xl font-black mb-2">48 Surtidos Clásicos</h3>
                    <p class="text-blue-100 text-base md:text-lg font-semibold">Pack grande clásico</p>
                </div>
                <div class="p-5 md:p-6">
                    <p class="text-gray-700 text-sm md:text-base mb-5 leading-relaxed">
                        Jamón y queso, lechuga, tomate y huevo. Los <span class="font-bold text-blue-600">4 sabores</span> que nunca fallan.
                    </p>
                    <a href="https://wa.me/541159813546?text=Hola! Quiero pedir 48 Surtidos Clásicos 🥙" 
                       target="_blank" 
                       class="whatsapp-btn w-full py-3 md:py-3.5 rounded-2xl text-white font-black text-base md:text-lg flex items-center justify-center relative z-10">
                        <i class="fab fa-whatsapp mr-2 text-xl"></i>
                        PEDIR AHORA
                    </a>
                </div>
            </div>

            <!-- 24 Surtidos Especiales -->
            <div class="card-epic bg-white rounded-3xl overflow-hidden shadow-xl">
                <div class="bg-gradient-to-br from-purple-500 via-purple-600 to-purple-700 p-6 md:p-8 text-white">
                    <div class="text-4xl md:text-5xl mb-3 float-animation">⭐</div>
                    <h3 class="text-xl md:text-2xl font-black mb-2">24 Surtidos Especiales</h3>
                    <p class="text-purple-100 text-base md:text-lg font-semibold">Más variedad</p>
                </div>
                <div class="p-5 md:p-6">
                    <div class="mb-4">
                        <p class="font-bold text-gray-900 mb-2 text-sm md:text-base">Sabores incluidos:</p>
                        <div class="flex flex-wrap gap-1.5">
                            <span class="sabor-tag bg-gradient-to-r from-purple-100 to-purple-200 text-purple-900 px-2 py-1 rounded-lg font-bold shadow-md text-xs">J&Q</span>
                            <span class="sabor-tag bg-gradient-to-r from-purple-100 to-purple-200 text-purple-900 px-2 py-1 rounded-lg font-bold shadow-md text-xs">Lechuga</span>
                            <span class="sabor-tag bg-gradient-to-r from-purple-100 to-purple-200 text-purple-900 px-2 py-1 rounded-lg font-bold shadow-md text-xs">Tomate</span>
                            <span class="sabor-tag bg-gradient-to-r from-purple-100 to-purple-200 text-purple-900 px-2 py-1 rounded-lg font-bold shadow-md text-xs">Huevo</span>
                            <span class="sabor-tag bg-gradient-to-r from-purple-100 to-purple-200 text-purple-900 px-2 py-1 rounded-lg font-bold shadow-md text-xs">Choclo</span>
                            <span class="sabor-tag bg-gradient-to-r from-purple-100 to-purple-200 text-purple-900 px-2 py-1 rounded-lg font-bold shadow-md text-xs">Aceitunas</span>
                        </div>
                    </div>
                    <a href="https://wa.me/541159813546?text=Hola! Quiero pedir 24 Surtidos Especiales ⭐" 
                       target="_blank" 
                       class="whatsapp-btn w-full py-3 md:py-3.5 rounded-2xl text-white font-black text-base md:text-lg flex items-center justify-center relative z-10">
                        <i class="fab fa-whatsapp mr-2 text-xl"></i>
                        PEDIR AHORA
                    </a>
                </div>
            </div>

            <!-- 48 Surtidos Especiales -->
            <div class="card-epic bg-white rounded-3xl overflow-hidden shadow-xl">
                <div class="bg-gradient-to-br from-purple-600 via-pink-500 to-pink-600 p-6 md:p-8 text-white">
                    <div class="text-4xl md:text-5xl mb-3 float-animation">🌟</div>
                    <h3 class="text-xl md:text-2xl font-black mb-2">48 Surtidos Especiales</h3>
                    <p class="text-purple-100 text-base md:text-lg font-semibold">Pack completo especial</p>
                </div>
                <div class="p-5 md:p-6">
                    <p class="text-gray-700 text-sm md:text-base mb-5 leading-relaxed">
                        Clásicos + <span class="font-bold text-purple-600">choclo y aceitunas</span>. Ideal para quienes buscan más variedad.
                    </p>
                    <a href="https://wa.me/541159813546?text=Hola! Quiero pedir 48 Surtidos Especiales 🌟" 
                       target="_blank" 
                       class="whatsapp-btn w-full py-3 md:py-3.5 rounded-2xl text-white font-black text-base md:text-lg flex items-center justify-center relative z-10">
                        <i class="fab fa-whatsapp mr-2 text-xl"></i>
                        PEDIR AHORA
                    </a>
                </div>
            </div>

            <!-- 48 Surtidos Premium -->
            <div class="card-epic bg-white rounded-3xl overflow-hidden shadow-xl relative border-4 border-yellow-400">
                <div class="badge-premium absolute top-4 right-4 z-20 px-4 py-2 rounded-full font-black text-xs text-white shadow-2xl">
                    ✨ PREMIUM
                </div>
                <div class="bg-gradient-to-br from-amber-500 via-yellow-500 to-yellow-600 p-6 md:p-8 text-white">
                    <div class="text-4xl md:text-5xl mb-3 float-animation">👑</div>
                    <h3 class="text-xl md:text-2xl font-black mb-2">48 Surtidos Premium</h3>
                    <p class="text-amber-100 text-base md:text-lg font-semibold">Sabores exclusivos</p>
                </div>
                <div class="p-5 md:p-6">
                    <div class="mb-4">
                        <p class="font-bold text-gray-900 mb-2 text-sm md:text-base">Sabores premium:</p>
                        <div class="flex flex-wrap gap-1.5">
                            <span class="sabor-tag bg-gradient-to-r from-amber-100 to-yellow-200 text-amber-900 px-2 py-1 rounded-lg font-bold shadow-md text-xs">🍍 Ananá</span>
                            <span class="sabor-tag bg-gradient-to-r from-amber-100 to-yellow-200 text-amber-900 px-2 py-1 rounded-lg font-bold shadow-md text-xs">🐟 Atún</span>
                            <span class="sabor-tag bg-gradient-to-r from-amber-100 to-yellow-200 text-amber-900 px-2 py-1 rounded-lg font-bold shadow-md text-xs">🍆 Berenjena</span>
                            <span class="sabor-tag bg-gradient-to-r from-amber-100 to-yellow-200 text-amber-900 px-2 py-1 rounded-lg font-bold shadow-md text-xs">🥓 J.Crudo</span>
                            <span class="sabor-tag bg-gradient-to-r from-amber-100 to-yellow-200 text-amber-900 px-2 py-1 rounded-lg font-bold shadow-md text-xs">🫑 Morrón</span>
                            <span class="sabor-tag bg-gradient-to-r from-amber-100 to-yellow-200 text-amber-900 px-2 py-1 rounded-lg font-bold shadow-md text-xs">🌴 Palmito</span>
                            <span class="sabor-tag bg-gradient-to-r from-amber-100 to-yellow-200 text-amber-900 px-2 py-1 rounded-lg font-bold shadow-md text-xs">🥓 Panceta</span>
                            <span class="sabor-tag bg-gradient-to-r from-amber-100 to-yellow-200 text-amber-900 px-2 py-1 rounded-lg font-bold shadow-md text-xs">🍗 Pollo</span>
                            <span class="sabor-tag bg-gradient-to-r from-amber-100 to-yellow-200 text-amber-900 px-2 py-1 rounded-lg font-bold shadow-md text-xs">🧀 Roquefort</span>
                            <span class="sabor-tag bg-gradient-to-r from-amber-100 to-yellow-200 text-amber-900 px-2 py-1 rounded-lg font-bold shadow-md text-xs">🥩 Salame</span>
                        </div>
                    </div>
                    <a href="https://wa.me/541159813546?text=Hola! Quiero pedir 48 Surtidos Premium 👑 - Quiero estos sabores:" 
                       target="_blank" 
                       class="whatsapp-btn w-full py-3 md:py-3.5 rounded-2xl text-white font-black text-base md:text-lg flex items-center justify-center relative z-10">
                        <i class="fab fa-whatsapp mr-2 text-xl"></i>
                        ELEGIR SABORES
                    </a>
                </div>
            </div>

        </div>

        <!-- Sección: Surtidos Elegidos -->
        <div class="bg-gradient-to-br from-red-100 via-pink-100 to-orange-100 rounded-3xl p-8 md:p-12 mb-16 shadow-2xl border-4 border-red-300">
            <div class="text-center mb-12">
                <div class="text-5xl md:text-6xl mb-4 fire-effect">🎨</div>
                <h2 class="text-3xl md:text-4xl lg:text-5xl font-black mb-4" style="background: linear-gradient(135deg, #DC2626 0%, #EC4899 50%, #F97316 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;">
                    SURTIDOS ELEGIDOS
                </h2>
                <div class="h-2 w-32 md:w-48 mx-auto mb-4 section-divider rounded-full"></div>
                <p class="text-xl md:text-2xl font-bold mb-2" style="color: #1F2937;">¡Vos elegís lo que querés!</p>
                <p class="text-lg md:text-xl font-semibold" style="color: #DC2626;">Personalizá tu pedido con tus sabores favoritos</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 md:gap-8 mb-12">
                
                <!-- 8 Elegidos -->
                <div class="card-epic bg-white rounded-3xl p-6 md:p-8 shadow-2xl text-center border-2 border-red-300 relative">
                    <div class="text-5xl md:text-6xl mb-4 float-animation">🥪</div>
                    <h3 class="text-2xl md:text-3xl font-black mb-3" style="color: #DC2626;">8 Elegidos</h3>
                    <p class="text-base md:text-lg mb-6 font-semibold" style="color: #6B7280;">1 plancha personalizada</p>
                    <a href="https://wa.me/541159813546?text=Hola! Quiero pedir 8 sándwiches elegidos 🥪 - Mis sabores:" 
                       target="_blank" 
                       class="whatsapp-btn w-full py-3 md:py-3.5 rounded-2xl text-white font-black text-base md:text-lg flex items-center justify-center relative z-10">
                        <i class="fab fa-whatsapp mr-2 text-xl"></i>
                        PERSONALIZAR
                    </a>
                </div>

                <!-- 24 Elegidos -->
                <div class="card-epic bg-white rounded-3xl p-6 md:p-8 shadow-2xl text-center border-4 border-red-500 relative">
                    <div class="absolute top-3 right-3 bg-red-500 text-white px-3 py-1.5 rounded-full font-black text-xs shadow-lg pulse-icon">
                        🔥 POPULAR
                    </div>
                    <div class="text-5xl md:text-6xl mb-4 float-animation">🎯</div>
                    <h3 class="text-2xl md:text-3xl font-black mb-3" style="color: #DC2626;">24 Elegidos</h3>
                    <p class="text-base md:text-lg mb-6 font-semibold" style="color: #6B7280;">3 planchas a tu gusto</p>
                    <a href="https://wa.me/541159813546?text=Hola! Quiero pedir 24 sándwiches elegidos 🎯 - Mis sabores:" 
                       target="_blank" 
                       class="whatsapp-btn w-full py-3 md:py-3.5 rounded-2xl text-white font-black text-base md:text-lg flex items-center justify-center relative z-10">
                        <i class="fab fa-whatsapp mr-2 text-xl"></i>
                        PERSONALIZAR
                    </a>
                </div>

                <!-- 48 Elegidos -->
                <div class="card-epic bg-white rounded-3xl p-6 md:p-8 shadow-2xl text-center border-2 border-red-300 relative">
                    <div class="text-5xl md:text-6xl mb-4 float-animation">🎉</div>
                    <h3 class="text-2xl md:text-3xl font-black mb-3" style="color: #DC2626;">48 Elegidos</h3>
                    <p class="text-base md:text-lg mb-6 font-semibold" style="color: #6B7280;">6 planchas personalizadas</p>
                    <a href="https://wa.me/541159813546?text=Hola! Quiero pedir 48 sándwiches elegidos 🎉 - Mis sabores:" 
                       target="_blank" 
                       class="whatsapp-btn w-full py-3 md:py-3.5 rounded-2xl text-white font-black text-base md:text-lg flex items-center justify-center relative z-10">
                        <i class="fab fa-whatsapp mr-2 text-xl"></i>
                        PERSONALIZAR
                    </a>
                </div>

            </div>

            <!-- Lista de todos los sabores -->
            <div class="bg-white rounded-3xl p-6 md:p-10 shadow-2xl border-2 border-orange-200">
                <h4 class="text-2xl md:text-3xl font-black mb-8 text-center" style="color: #1F2937;">
                    🌈 TODOS LOS SABORES DISPONIBLES
                </h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8 md:gap-10">
                    
                    <!-- Sabores Clásicos -->
                    <div>
                        <h5 class="text-xl md:text-2xl font-black mb-6 flex items-center" style="color: #2563EB;">
                            <i class="fas fa-star mr-3"></i>
                            Sabores Clásicos
                        </h5>
                        <div class="space-y-3">
                            <div class="flex items-center p-3 md:p-4 bg-gradient-to-r from-blue-50 to-blue-100 rounded-2xl shadow-md">
                                <i class="fas fa-check-circle mr-3 md:mr-4 text-lg md:text-xl" style="color: #2563EB;"></i>
                                <span class="font-bold text-sm md:text-base" style="color: #1F2937;">Jamón y Queso</span>
                            </div>
                            <div class="flex items-center p-3 md:p-4 bg-gradient-to-r from-blue-50 to-blue-100 rounded-2xl shadow-md">
                                <i class="fas fa-check-circle mr-3 md:mr-4 text-lg md:text-xl" style="color: #2563EB;"></i>
                                <span class="font-bold text-sm md:text-base" style="color: #1F2937;">Lechuga y Tomate</span>
                            </div>
                            <div class="flex items-center p-3 md:p-4 bg-gradient-to-r from-blue-50 to-blue-100 rounded-2xl shadow-md">
                                <i class="fas fa-check-circle mr-3 md:mr-4 text-lg md:text-xl" style="color: #2563EB;"></i>
                                <span class="font-bold text-sm md:text-base" style="color: #1F2937;">Huevo</span>
                            </div>
                            <div class="flex items-center p-3 md:p-4 bg-gradient-to-r from-blue-50 to-blue-100 rounded-2xl shadow-md">
                                <i class="fas fa-check-circle mr-3 md:mr-4 text-lg md:text-xl" style="color: #2563EB;"></i>
                                <span class="font-bold text-sm md:text-base" style="color: #1F2937;">Choclo</span>
                            </div>
                            <div class="flex items-center p-3 md:p-4 bg-gradient-to-r from-blue-50 to-blue-100 rounded-2xl shadow-md">
                                <i class="fas fa-check-circle mr-3 md:mr-4 text-lg md:text-xl" style="color: #2563EB;"></i>
                                <span class="font-bold text-sm md:text-base" style="color: #1F2937;">Aceitunas</span>
                            </div>
                            <div class="flex items-center p-3 md:p-4 bg-gradient-to-r from-blue-50 to-blue-100 rounded-2xl shadow-md">
                                <i class="fas fa-check-circle mr-3 md:mr-4 text-lg md:text-xl" style="color: #2563EB;"></i>
                                <span class="font-bold text-sm md:text-base" style="color: #1F2937;">Zanahoria y Queso</span>
                            </div>
                            <div class="flex items-center p-3 md:p-4 bg-gradient-to-r from-blue-50 to-blue-100 rounded-2xl shadow-md">
                                <i class="fas fa-check-circle mr-3 md:mr-4 text-lg md:text-xl" style="color: #2563EB;"></i>
                                <span class="font-bold text-sm md:text-base" style="color: #1F2937;">Zanahoria y Huevo</span>
                            </div>
                        </div>
                    </div>

                    <!-- Sabores Premium -->
                    <div>
                        <h5 class="text-xl md:text-2xl font-black mb-6 flex items-center" style="color: #F59E0B;">
                            <i class="fas fa-crown mr-3"></i>
                            Sabores Premium
                        </h5>
                        <div class="space-y-3">
                            <div class="flex items-center p-3 md:p-4 bg-gradient-to-r from-amber-50 to-yellow-100 rounded-2xl shadow-md">
                                <i class="fas fa-check-circle mr-3 md:mr-4 text-lg md:text-xl" style="color: #F59E0B;"></i>
                                <span class="font-bold text-sm md:text-base" style="color: #1F2937;">Ananá • Atún • Berenjena</span>
                            </div>
                            <div class="flex items-center p-3 md:p-4 bg-gradient-to-r from-amber-50 to-yellow-100 rounded-2xl shadow-md">
                                <i class="fas fa-check-circle mr-3 md:mr-4 text-lg md:text-xl" style="color: #F59E0B;"></i>
                                <span class="font-bold text-sm md:text-base" style="color: #1F2937;">Jamón Crudo • Morrón</span>
                            </div>
                            <div class="flex items-center p-3 md:p-4 bg-gradient-to-r from-amber-50 to-yellow-100 rounded-2xl shadow-md">
                                <i class="fas fa-check-circle mr-3 md:mr-4 text-lg md:text-xl" style="color: #F59E0B;"></i>
                                <span class="font-bold text-sm md:text-base" style="color: #1F2937;">Palmito • Panceta • Pollo</span>
                            </div>
                            <div class="flex items-center p-3 md:p-4 bg-gradient-to-r from-amber-50 to-yellow-100 rounded-2xl shadow-md">
                                <i class="fas fa-check-circle mr-3 md:mr-4 text-lg md:text-xl" style="color: #F59E0B;"></i>
                                <span class="font-bold text-sm md:text-base" style="color: #1F2937;">Roquefort • Salame</span>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <!-- Beneficios -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 md:gap-8 mb-16">
            
            <div class="card-epic bg-white rounded-3xl p-6 md:p-8 shadow-2xl text-center border-2 border-orange-200">
                <div class="text-5xl md:text-6xl mb-4 fire-effect">🚀</div>
                <h3 class="text-xl md:text-2xl font-black mb-3" style="color: #1F2937;">Entrega Rápida</h3>
                <p class="text-sm md:text-base font-medium" style="color: #6B7280;">
                    Hacemos tu pedido al momento y lo entregamos caliente y fresco
                </p>
            </div>

            <div class="card-epic bg-white rounded-3xl p-6 md:p-8 shadow-2xl text-center border-2 border-orange-200">
                <div class="text-5xl md:text-6xl mb-4 fire-effect">✨</div>
                <h3 class="text-xl md:text-2xl font-black mb-3" style="color: #1F2937;">Ingredientes Premium</h3>
                <p class="text-sm md:text-base font-medium" style="color: #6B7280;">
                    Usamos solo productos de primera calidad para garantizar el mejor sabor
                </p>
            </div>

            <div class="card-epic bg-white rounded-3xl p-6 md:p-8 shadow-2xl text-center border-2 border-orange-200">
                <div class="text-5xl md:text-6xl mb-4 fire-effect">💯</div>
                <h3 class="text-xl md:text-2xl font-black mb-3" style="color: #1F2937;">Satisfacción Garantizada</h3>
                <p class="text-sm md:text-base font-medium" style="color: #6B7280;">
                    Más de 10 años preparando los mejores sándwiches de La Plata
                </p>
            </div>

        </div>

        <!-- Llamada a la acción final -->
        <div class="hero-gradient rounded-3xl p-10 md:p-16 text-center text-white shadow-2xl border-4 border-orange-400">
            <div class="max-w-3xl mx-auto">
                <div class="text-6xl md:text-7xl mb-6 float-animation">🥪</div>
                <h2 class="text-3xl md:text-4xl lg:text-5xl font-black mb-6" style="color: white; text-shadow: 0 4px 20px rgba(0,0,0,0.4);">
                    ¿TENÉS HAMBRE?
                </h2>
                <p class="text-lg md:text-xl lg:text-2xl mb-8 font-bold" style="color: white; text-shadow: 0 3px 15px rgba(0,0,0,0.4);">
                    Pedí ahora y disfrutá de los mejores sándwiches de La Plata en minutos
                </p>
                <a href="https://wa.me/541159813546?text=Hola! Quiero hacer un pedido 🥪" 
                   target="_blank" 
                   class="inline-flex items-center bg-white px-8 md:px-12 py-4 md:py-5 text-xl md:text-2xl font-black rounded-full shadow-2xl hover:scale-105 transition-all duration-300" style="color: #EA580C;">
                    <i class="fab fa-whatsapp mr-3 text-3xl" style="color: #25D366;"></i>
                    HACER MI PEDIDO
                </a>
            </div>
        </div>

    </main>

    <!-- Footer -->
    <footer class="bg-gradient-to-r from-gray-900 via-gray-800 to-gray-900 text-white py-12 md:py-14 px-4 mt-20">
        <div class="container mx-auto">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-8">
                
                <div class="text-center md:text-left">
                    <h4 class="text-xl md:text-2xl font-black mb-4 flex items-center justify-center md:justify-start" style="color: #FFA500;">
                        <i class="fas fa-map-marker-alt mr-2"></i>
                        Ubicación
                    </h4>
                    <p class="text-base md:text-lg font-semibold" style="color: #D1D5DB;">Gutierrez</p>
                    <p class="text-base md:text-lg font-semibold" style="color: #D1D5DB;">La Plata, Buenos Aires</p>
                </div>

                <div class="text-center">
                    <h4 class="text-xl md:text-2xl font-black mb-4 flex items-center justify-center" style="color: #FFA500;">
                        <i class="fas fa-phone mr-2"></i>
                        Contacto
                    </h4>
                    <p class="text-base md:text-lg font-bold mb-3" style="color: white;">11 5981-3546</p>
                    <a href="https://wa.me/541159813546" target="_blank" 
                       class="inline-flex items-center px-5 py-2.5 bg-green-500 hover:bg-green-600 rounded-full font-bold text-sm md:text-base transition-all duration-300 shadow-lg">
                        <i class="fab fa-whatsapp mr-2 text-lg"></i>
                        WhatsApp
                    </a>
                </div>

                <div class="text-center md:text-right">
                    <h4 class="text-xl md:text-2xl font-black mb-4 flex items-center justify-center md:justify-end" style="color: #FFA500;">
                        <i class="fas fa-clock mr-2"></i>
                        Horarios
                    </h4>
                    <p class="text-base md:text-lg font-semibold" style="color: #D1D5DB;">Consultá por WhatsApp</p>
                    <p class="text-base md:text-lg font-semibold" style="color: #D1D5DB;">disponibilidad y tiempos</p>
                </div>

            </div>

            <div class="border-t border-gray-700 pt-8 text-center">
                <p class="text-base md:text-lg font-semibold mb-2" style="color: #9CA3AF;">
                    © 2025 Santa Catalina - Los mejores sándwiches de La Plata
                </p>
                <p class="text-sm md:text-base font-medium" style="color: #6B7280;">
                    Hecho con ❤️ y mucho jamón y queso
                </p>
            </div>
        </div>
    </footer>

</body>
</html>