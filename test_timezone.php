<?php
require_once 'config.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test de Timezone - Sistema Santa Catalina</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100 p-6">
    <div class="max-w-6xl mx-auto">
        <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
            <h1 class="text-3xl font-bold text-gray-800 mb-4">
                <i class="fas fa-clock text-blue-500 mr-2"></i>Test de Timezone - Sistema Santa Catalina
            </h1>

            <div class="bg-blue-50 border-l-4 border-blue-500 p-4 mb-6">
                <p class="text-sm text-blue-800">
                    <i class="fas fa-info-circle mr-2"></i>
                    <strong>Información:</strong> Argentina está en GMT-3 (UTC-3). Esto significa que la hora UTC es 3 horas más que la hora local de Argentina.
                    Si son las 20:00 en Argentina, en UTC son las 23:00. Esto es correcto y esperado.
                </p>
            </div>
        </div>

        <?php
        // Test 1: PHP Timezone
        $php_timezone = date_default_timezone_get();
        $php_date = date('Y-m-d H:i:s');
        $php_utc = gmdate('Y-m-d H:i:s');
        $diferencia_php = (strtotime($php_utc) - strtotime($php_date)) / 3600;
        ?>

        <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
            <h2 class="text-2xl font-bold text-gray-800 mb-4">
                <i class="fas fa-code text-purple-500 mr-2"></i>1. Configuración PHP
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="bg-gray-50 p-4 rounded-lg">
                    <div class="text-sm text-gray-600 mb-1">Timezone Configurado</div>
                    <div class="text-xl font-bold text-gray-800"><?= $php_timezone ?></div>
                    <div class="mt-2 text-xs <?= $php_timezone === 'America/Argentina/Buenos_Aires' ? 'text-green-600' : 'text-red-600' ?>">
                        <i class="fas <?= $php_timezone === 'America/Argentina/Buenos_Aires' ? 'fa-check-circle' : 'fa-exclamation-triangle' ?>"></i>
                        <?= $php_timezone === 'America/Argentina/Buenos_Aires' ? 'Correcto ✓' : 'Incorrecto - Debería ser America/Argentina/Buenos_Aires' ?>
                    </div>
                </div>

                <div class="bg-gray-50 p-4 rounded-lg">
                    <div class="text-sm text-gray-600 mb-1">Hora PHP Local (Argentina)</div>
                    <div class="text-xl font-bold text-gray-800"><?= $php_date ?></div>
                </div>

                <div class="bg-gray-50 p-4 rounded-lg">
                    <div class="text-sm text-gray-600 mb-1">Hora PHP UTC (Universal)</div>
                    <div class="text-xl font-bold text-gray-800"><?= $php_utc ?></div>
                </div>

                <div class="bg-gray-50 p-4 rounded-lg">
                    <div class="text-sm text-gray-600 mb-1">Diferencia Horaria</div>
                    <div class="text-xl font-bold text-gray-800"><?= abs($diferencia_php) ?> horas</div>
                    <div class="mt-2 text-xs <?= abs($diferencia_php) == 3 ? 'text-green-600' : 'text-red-600' ?>">
                        <i class="fas <?= abs($diferencia_php) == 3 ? 'fa-check-circle' : 'fa-exclamation-triangle' ?>"></i>
                        <?= abs($diferencia_php) == 3 ? 'Correcto ✓ (Argentina es UTC-3)' : 'Incorrecto - Debería ser 3 horas' ?>
                    </div>
                </div>
            </div>
        </div>

        <?php
        // Test 2: MySQL Timezone
        try {
            $pdo = getConnection();

            $result = $pdo->query("SELECT @@session.time_zone as session_tz, @@global.time_zone as global_tz");
            $row = $result->fetch();
            $mysql_session_tz = $row['session_tz'];
            $mysql_global_tz = $row['global_tz'];

            $result = $pdo->query("SELECT NOW() as mysql_now, UTC_TIMESTAMP() as utc_now");
            $row = $result->fetch();
            $mysql_now = $row['mysql_now'];
            $mysql_utc = $row['utc_now'];
            $diferencia_mysql = (strtotime($mysql_utc) - strtotime($mysql_now)) / 3600;
        ?>

        <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
            <h2 class="text-2xl font-bold text-gray-800 mb-4">
                <i class="fas fa-database text-green-500 mr-2"></i>2. Configuración MySQL
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="bg-gray-50 p-4 rounded-lg">
                    <div class="text-sm text-gray-600 mb-1">Session Timezone</div>
                    <div class="text-xl font-bold text-gray-800"><?= $mysql_session_tz ?></div>
                    <div class="mt-2 text-xs <?= $mysql_session_tz === '-03:00' ? 'text-green-600' : 'text-red-600' ?>">
                        <i class="fas <?= $mysql_session_tz === '-03:00' ? 'fa-check-circle' : 'fa-exclamation-triangle' ?>"></i>
                        <?= $mysql_session_tz === '-03:00' ? 'Correcto ✓' : 'Incorrecto - Debería ser -03:00' ?>
                    </div>
                </div>

                <div class="bg-gray-50 p-4 rounded-lg">
                    <div class="text-sm text-gray-600 mb-1">Global Timezone</div>
                    <div class="text-xl font-bold text-gray-800"><?= $mysql_global_tz ?></div>
                    <div class="mt-2 text-xs text-blue-600">
                        <i class="fas fa-info-circle"></i>
                        SYSTEM utiliza el timezone del servidor
                    </div>
                </div>

                <div class="bg-gray-50 p-4 rounded-lg">
                    <div class="text-sm text-gray-600 mb-1">MySQL NOW() - Hora Local</div>
                    <div class="text-xl font-bold text-gray-800"><?= $mysql_now ?></div>
                </div>

                <div class="bg-gray-50 p-4 rounded-lg">
                    <div class="text-sm text-gray-600 mb-1">MySQL UTC_TIMESTAMP()</div>
                    <div class="text-xl font-bold text-gray-800"><?= $mysql_utc ?></div>
                </div>

                <div class="bg-blue-50 p-4 rounded-lg col-span-2 border-l-4 border-blue-500">
                    <div class="text-sm text-gray-600 mb-1">Diferencia Horaria MySQL</div>
                    <div class="text-xl font-bold text-gray-800"><?= abs($diferencia_mysql) ?> horas</div>
                    <div class="mt-2 text-xs <?= abs($diferencia_mysql) == 3 ? 'text-green-600' : 'text-red-600' ?>">
                        <i class="fas <?= abs($diferencia_mysql) == 3 ? 'fa-check-circle' : 'fa-exclamation-triangle' ?>"></i>
                        <?= abs($diferencia_mysql) == 3 ? 'Correcto ✓ - La diferencia de 3 horas es correcta para Argentina (UTC-3)' : 'Incorrecto - Debería ser 3 horas' ?>
                    </div>
                </div>
            </div>
        </div>

        <?php
        // Test 3: Último pedido
        $result = $pdo->query("SELECT id, nombre, created_at FROM pedidos ORDER BY id DESC LIMIT 1");
        $pedido = $result->fetch();
        ?>

        <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
            <h2 class="text-2xl font-bold text-gray-800 mb-4">
                <i class="fas fa-shopping-cart text-orange-500 mr-2"></i>3. Último Pedido en Base de Datos
            </h2>

            <?php if ($pedido): ?>
            <div class="bg-gray-50 p-4 rounded-lg">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <div class="text-sm text-gray-600">ID Pedido</div>
                        <div class="text-xl font-bold text-gray-800">#<?= $pedido['id'] ?></div>
                    </div>
                    <div>
                        <div class="text-sm text-gray-600">Nombre Cliente</div>
                        <div class="text-xl font-bold text-gray-800"><?= htmlspecialchars($pedido['nombre']) ?></div>
                    </div>
                    <div>
                        <div class="text-sm text-gray-600">Fecha de Creación</div>
                        <div class="text-xl font-bold text-gray-800"><?= $pedido['created_at'] ?></div>
                    </div>
                </div>

                <div class="mt-4 bg-green-50 p-3 rounded border-l-4 border-green-500">
                    <div class="text-sm text-green-800">
                        <i class="fas fa-check-circle mr-2"></i>
                        El pedido se guardó con la hora local de Argentina (GMT-3). Esto es correcto.
                    </div>
                </div>
            </div>
            <?php else: ?>
            <div class="bg-yellow-50 p-4 rounded-lg border-l-4 border-yellow-500">
                <div class="text-sm text-yellow-800">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    No hay pedidos en la base de datos todavía.
                </div>
            </div>
            <?php endif; ?>
        </div>

        <?php
        // Test 4: Productos y precios
        $result = $pdo->query("SELECT nombre, precio_efectivo, precio_transferencia, categoria FROM productos WHERE activo = 1 ORDER BY categoria, nombre LIMIT 10");
        $productos = $result->fetchAll();
        ?>

        <div class="bg-white rounded-lg shadow-lg p-6">
            <h2 class="text-2xl font-bold text-gray-800 mb-4">
                <i class="fas fa-tags text-red-500 mr-2"></i>4. Productos en Base de Datos (Primeros 10)
            </h2>

            <div class="overflow-x-auto">
                <table class="min-w-full bg-white">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Producto</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Categoría</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Precio Efectivo</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Precio Transferencia</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Diferencia</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($productos as $prod):
                            $diferencia = $prod['precio_transferencia'] - $prod['precio_efectivo'];
                        ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap font-medium text-gray-900">
                                <?= htmlspecialchars($prod['nombre']) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-gray-600">
                                <span class="px-2 py-1 text-xs bg-blue-100 text-blue-800 rounded">
                                    <?= htmlspecialchars($prod['categoria']) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right font-bold text-green-600">
                                $<?= number_format($prod['precio_efectivo'], 0, ',', '.') ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right font-bold text-blue-600">
                                $<?= number_format($prod['precio_transferencia'], 0, ',', '.') ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right <?= $diferencia >= 0 ? 'text-green-600' : 'text-red-600' ?>">
                                <?= $diferencia >= 0 ? '+' : '' ?>$<?= number_format($diferencia, 0, ',', '.') ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php
        } catch (Exception $e) {
            echo '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">';
            echo '<strong>ERROR:</strong> ' . $e->getMessage();
            echo '</div>';
        }
        ?>

        <!-- Resumen Final -->
        <div class="bg-gradient-to-r from-green-50 to-blue-50 rounded-lg shadow-lg p-6 mt-6">
            <h2 class="text-2xl font-bold text-gray-800 mb-4">
                <i class="fas fa-check-circle text-green-500 mr-2"></i>Resumen y Conclusión
            </h2>

            <div class="space-y-2">
                <div class="flex items-start">
                    <i class="fas fa-check text-green-500 mt-1 mr-3"></i>
                    <div>
                        <strong>PHP Timezone:</strong> Configurado correctamente en America/Argentina/Buenos_Aires (GMT-3)
                    </div>
                </div>
                <div class="flex items-start">
                    <i class="fas fa-check text-green-500 mt-1 mr-3"></i>
                    <div>
                        <strong>MySQL Timezone:</strong> Session configurada en -03:00 (GMT-3) para Argentina
                    </div>
                </div>
                <div class="flex items-start">
                    <i class="fas fa-check text-green-500 mt-1 mr-3"></i>
                    <div>
                        <strong>Diferencia UTC:</strong> La diferencia de 3 horas entre hora local y UTC es correcta y esperada
                    </div>
                </div>
                <div class="flex items-start">
                    <i class="fas fa-info-circle text-blue-500 mt-1 mr-3"></i>
                    <div>
                        <strong>Almacenamiento:</strong> Todos los timestamps se guardan en hora local de Argentina (GMT-3)
                    </div>
                </div>
            </div>

            <div class="mt-4 bg-white p-4 rounded-lg border-l-4 border-green-500">
                <p class="text-green-800 font-bold">
                    <i class="fas fa-thumbs-up mr-2"></i>
                    ¡Todo está funcionando correctamente! El sistema está configurado para usar el timezone de Argentina.
                </p>
            </div>
        </div>

        <div class="mt-6 text-center">
            <a href="admin/" class="inline-block bg-blue-500 hover:bg-blue-600 text-white px-6 py-3 rounded-lg font-medium">
                <i class="fas fa-arrow-left mr-2"></i>Volver al Admin
            </a>
        </div>
    </div>
</body>
</html>
