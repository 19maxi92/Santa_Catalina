<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Santa Catalina - Sándwiches de Miga</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50 font-sans">
<!-- Header -->
<header class="hero-pattern bg-gray-900 text-white py-16 md:py-24 relative">
        <div class="absolute inset-0 bg-black bg-opacity-50"></div>
        <div class="absolute top-4 right-4 z-20 space-x-2">
            <a href="empleados/login.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-full text-sm">
                <i class="fas fa-users mr-1"></i> Empleados
            </a>
            <a href="admin/" class="bg-gray-800 hover:bg-gray-700 text-white px-4 py-2 rounded-full text-sm">
                <i class="fas fa-lock mr-1"></i> Admin
            </a>
        </div>
        <div class="container mx-auto px-4 relative z-10">
            <div class="max-w-3xl mx-auto text-center">
                <h1 class="text-4xl md:text-5xl font-bold mb-4">Santa Catalina</h1>
                <p class="text-xl md:text-2xl mb-8">Los mejores sándwiches de miga de la ciudad</p>
                <div class="bg-white text-gray-800 inline-block px-6 py-3 rounded-full shadow-lg">
                    <i class="fas fa-phone-alt mr-2 text-orange-500"></i>
                    <span class="font-semibold">Pedidos: 11 5981-3546</span>
                </div>
            </div>
        </div>
    </header>

<!-- WhatsApp Float Button -->
<div class="fixed bottom-6 right-6 z-50">
    <a href="https://wa.me/541159813546?text=Hola%20quiero%20hacer%20un%20pedido" target="_blank" 
       class="bg-green-500 hover:bg-green-600 text-white rounded-full p-4 shadow-lg transition-all hover:scale-110">
        <i class="fab fa-whatsapp text-2xl"></i>
    </a>
</div>

<main class="container mx-auto px-4 py-12">
    <h2 class="text-3xl font-bold text-center mb-12 text-gray-800">Promos de Triples</h2>

    <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8 mb-16">
        <!-- PROMO 24 JAMÓN Y QUESO -->
        <div class="sandwich-card bg-white rounded-lg overflow-hidden shadow-md transition-all duration-300">
            <div class="bg-orange-100 p-6">
                <h3 class="text-xl font-bold text-gray-800">24 Jamón y Queso</h3>
            </div>
            <div class="p-6">
                <p class="text-gray-600 mb-4">Clásico triple de jamón y queso.</p>
                <div class="flex items-center justify-between">
                    <div>
                        <span class="text-lg text-gray-400 line-through">$12.000</span>
                        <span class="text-2xl font-bold text-green-600 ml-2">$11.000</span>
                        <div class="text-sm text-green-600 font-medium">Efectivo: $1.000 menos</div>
                    </div>
                    <a href="https://wa.me/541159813546?text=Hola%20quiero%20pedir%2024%20sándwiches%20de%20jamón%20y%20queso" target="_blank" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-full transition">Pedir</a>
                </div>
            </div>
        </div>

        <!-- PROMO 24 SURTIDOS -->
        <div class="sandwich-card bg-white rounded-lg overflow-hidden shadow-md transition-all duration-300">
            <div class="bg-orange-100 p-6">
                <h3 class="text-xl font-bold text-gray-800">24 Surtidos</h3>
            </div>
            <div class="p-6">
                <p class="text-gray-600 mb-4">3 sabores a elección: jamón y queso, lechuga, tomate, huevo, choclo, aceitunas.</p>
                <div class="flex items-center justify-between">
                    <div>
                        <span class="text-lg text-gray-400 line-through">$12.000</span>
                        <span class="text-2xl font-bold text-green-600 ml-2">$11.000</span>
                        <div class="text-sm text-green-600 font-medium">Efectivo: $1.000 menos</div>
                    </div>
                    <a href="https://wa.me/541159813546?text=Hola%20quiero%20pedir%2024%20sándwiches%20surtidos" target="_blank" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-full transition">Pedir</a>
                </div>
            </div>
        </div>

        <!-- PROMO 24 PREMIUM -->
        <div class="sandwich-card bg-white rounded-lg overflow-hidden shadow-md transition-all duration-300">
            <div class="bg-orange-100 p-6">
                <h3 class="text-xl font-bold text-gray-800">24 Premium</h3>
            </div>
            <div class="p-6">
                <p class="text-gray-600 mb-4">3 sabores a elección entre: ananá, atún, berenjena, durazno, jamón crudo, morrón, palmito, panceta, pollo, roquefort, salame.</p>
                <div class="flex items-center justify-between">
                    <div>
                        <span class="text-lg text-gray-400 line-through">$22.000</span>
                        <span class="text-2xl font-bold text-green-600 ml-2">$21.000</span>
                        <div class="text-sm text-green-600 font-medium">Efectivo: $1.000 menos</div>
                    </div>
                    <a href="https://wa.me/541159813546?text=Hola%20quiero%20pedir%2024%20sándwiches%20premium" target="_blank" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-full transition">Pedir</a>
                </div>
            </div>
        </div>

        <!-- PROMO 48 JAMÓN Y QUESO -->
        <div class="sandwich-card bg-white rounded-lg overflow-hidden shadow-md transition-all duration-300">
            <div class="bg-orange-100 p-6">
                <h3 class="text-xl font-bold text-gray-800">48 Jamón y Queso</h3>
            </div>
            <div class="p-6">
                <p class="text-gray-600 mb-4">Clásicos triples de jamón y queso.</p>
                <div class="flex items-center justify-between">
                    <div>
                        <span class="text-lg text-gray-400 line-through">$24.000</span>
                        <span class="text-2xl font-bold text-green-600 ml-2">$22.000</span>
                        <div class="text-sm text-green-600 font-medium">Efectivo: $2.000 menos</div>
                    </div>
                    <a href="https://wa.me/541159813546?text=Hola%20quiero%20pedir%2048%20sándwiches%20de%20jamón%20y%20queso" target="_blank" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-full transition">Pedir</a>
                </div>
            </div>
        </div>

        <!-- PROMO 48 SURTIDOS CLÁSICOS -->
        <div class="sandwich-card bg-white rounded-lg overflow-hidden shadow-md transition-all duration-300">
            <div class="bg-orange-100 p-6">
                <h3 class="text-xl font-bold text-gray-800">48 Surtidos Clásicos</h3>
            </div>
            <div class="p-6">
                <p class="text-gray-600 mb-4">Jamón y queso, lechuga, tomate, huevo.</p>
                <div class="flex items-center justify-between">
                    <div>
                        <span class="text-lg text-gray-400 line-through">$22.000</span>
                        <span class="text-2xl font-bold text-green-600 ml-2">$20.000</span>
                        <div class="text-sm text-green-600 font-medium">Efectivo: $2.000 menos</div>
                    </div>
                    <a href="https://wa.me/541159813546?text=Hola%20quiero%20pedir%2048%20sándwiches%20surtidos%20clásicos" target="_blank" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-full transition">Pedir</a>
                </div>
            </div>
        </div>

        <!-- PROMO 48 SURTIDOS ESPECIALES -->
        <div class="sandwich-card bg-white rounded-lg overflow-hidden shadow-md transition-all duration-300">
            <div class="bg-orange-100 p-6">
                <h3 class="text-xl font-bold text-gray-800">48 Surtidos Especiales</h3>
            </div>
            <div class="p-6">
                <p class="text-gray-600 mb-4">Jamón y queso, lechuga, tomate, huevo, choclo, aceitunas.</p>
                <div class="flex items-center justify-between">
                    <div>
                        <span class="text-lg text-gray-400 line-through">$24.000</span>
                        <span class="text-2xl font-bold text-green-600 ml-2">$22.000</span>
                        <div class="text-sm text-green-600 font-medium">Efectivo: $2.000 menos</div>
                    </div>
                    <a href="https://wa.me/541159813546?text=Hola%20quiero%20pedir%2048%20sándwiches%20surtidos%20especiales" target="_blank" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-full transition">Pedir</a>
                </div>
            </div>
        </div>

        <!-- PROMO 48 SURTIDOS PREMIUM -->
        <div class="sandwich-card bg-white rounded-lg overflow-hidden shadow-md transition-all duration-300">
            <div class="bg-orange-100 p-6">
                <h3 class="text-xl font-bold text-gray-800">48 Premium</h3>
            </div>
            <div class="p-6">
                <p class="text-gray-600 mb-4">6 sabores a elección entre: ananá, atún, berenjena, durazno, jamón crudo, morrón, palmito, panceta, pollo, roquefort, salame.</p>
                <div class="flex items-center justify-between">
                    <div>
                        <span class="text-lg text-gray-400 line-through">$44.000</span>
                        <span class="text-2xl font-bold text-green-600 ml-2">$42.000</span>
                        <div class="text-sm text-green-600 font-medium">Efectivo: $2.000 menos</div>
                    </div>
                    <a href="https://wa.me/541159813546?text=Hola%20quiero%20pedir%2048%20sándwiches%20premium" target="_blank" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-full transition">Pedir</a>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Footer actualizado -->
<footer class="bg-gray-900 text-white py-12">
    <div class="container mx-auto px-4">
        <div class="grid md:grid-cols-3 gap-8">
            <div>
                <h3 class="text-xl font-bold mb-4">Santa Catalina</h3>
                <p class="text-gray-400">Los auténticos sándwiches de miga, elaborados con los mejores ingredientes.</p>
            </div>
            <div>
                <h3 class="text-xl font-bold mb-4">Contacto</h3>
                <ul class="space-y-2">
                    <li class="flex items-center">
                        <i class="fas fa-phone-alt mr-3 text-orange-500"></i>
                        <span>📲 1159813546</span>
                    </li>
                    <li class="flex items-center">
                        <i class="fas fa-map-marker-alt mr-3 text-orange-500"></i>
                        <span>Camino General Manuel Belgrano 7241, J.M. Gutierrez, Buenos Aires, Argentina</span>
                    </li>
                    <li class="flex items-center">
                        <i class="fas fa-clock mr-3 text-orange-500"></i>
                        <span>Lunes a Domingo 9:00 - 20:00</span>
                    </li>
                </ul>
            </div>
            <div>
                <h3 class="text-xl font-bold mb-4">Redes Sociales</h3>
                <div class="flex space-x-4">
                    <a href="https://web.facebook.com/sandwicheria.santacatalina.1" target="_blank" class="bg-gray-700 hover:bg-orange-500 w-10 h-10 rounded-full flex items-center justify-center transition">
                        <i class="fab fa-facebook-f"></i>
                    </a>
                    <a href="https://www.instagram.com/sandwicheriasantacatalina" target="_blank" class="bg-gray-700 hover:bg-orange-500 w-10 h-10 rounded-full flex items-center justify-center transition">
                        <i class="fab fa-instagram"></i>
                    </a>
                    <a href="https://wa.me/541159813546" target="_blank" class="bg-gray-700 hover:bg-orange-500 w-10 h-10 rounded-full flex items-center justify-center transition">
                        <i class="fab fa-whatsapp"></i>
                    </a>
                </div>
            </div>
        </div>
        <div class="border-t border-gray-800 mt-8 pt-8 text-center text-gray-400">
            <p>© 2025 Santa Catalina - Todos los derechos reservados</p>
        </div>
    </div>
</footer>

<!-- Admin Login Modal -->
<div id="adminModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-lg p-8 max-w-md w-full">
        <h3 class="text-2xl font-bold mb-6 text-gray-800">Admin Login</h3>
        <form id="loginForm" class="space-y-4">
            <div>
                <label class="block text-gray-700 mb-2">Usuario</label>
                <input type="text" name="username" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500">
            </div>
            <div>
                <label class="block text-gray-700 mb-2">Contraseña</label>
                <input type="password" name="password" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500">
            </div>
            <button type="submit" class="w-full bg-orange-500 hover:bg-orange-600 text-white py-2 px-4 rounded-lg transition">
                Ingresar
            </button>
        </form>
    </div>
</div>

<script>
// Admin login functionality
const adminLoginBtn = document.getElementById('adminLoginBtn');
const adminModal = document.getElementById('adminModal');
const loginForm = document.getElementById('loginForm');

// Toggle login modal
if (adminLoginBtn) {
    adminLoginBtn.addEventListener('click', () => {
        adminModal.classList.toggle('hidden');
    });
}

// Close modal when clicking outside
if (adminModal) {
    adminModal.addEventListener('click', (e) => {
        if (e.target === adminModal) {
            adminModal.classList.add('hidden');
        }
    });
}

// Handle login form submission
if (loginForm) {
    loginForm.addEventListener('submit', (e) => {
        e.preventDefault();
        const username = e.target.username.value;
        const password = e.target.password.value;

        if(username === 'admin' && password === 'Sangu2186') {
            // Redirect to admin panel
            window.location.href = 'admin/';
        } else {
            alert('Credenciales incorrectas');
        }
    });
}

// Add hover effects to sandwich cards
document.querySelectorAll('.sandwich-card').forEach(card => {
    card.addEventListener('mouseenter', function() {
        this.style.transform = 'translateY(-5px)';
        this.style.boxShadow = '0 10px 25px rgba(0,0,0,0.1)';
    });
    
    card.addEventListener('mouseleave', function() {
        this.style.transform = 'translateY(0)';
        this.style.boxShadow = '0 4px 6px rgba(0,0,0,0.1)';
    });
});
</script>
</body>
</html>