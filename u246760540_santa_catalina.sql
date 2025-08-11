-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1:3306
-- Tiempo de generación: 11-08-2025 a las 00:51:49
-- Versión del servidor: 10.11.10-MariaDB-log
-- Versión de PHP: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `u246760540_santa_catalina`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `clientes_fijos`
--

CREATE TABLE `clientes_fijos` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `apellido` varchar(100) NOT NULL,
  `telefono` varchar(20) NOT NULL,
  `direccion` text DEFAULT NULL,
  `producto_habitual` varchar(100) DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `clientes_fijos`
--

INSERT INTO `clientes_fijos` (`id`, `nombre`, `apellido`, `telefono`, `direccion`, `producto_habitual`, `observaciones`, `activo`, `created_at`) VALUES
(1, 'maxi', 'burgos', '2216267575', 'calle 10 n1564', NULL, '', 1, '2025-08-06 17:06:37'),
(2, 'a', 's', 'd', '2', NULL, '', 0, '2025-08-06 17:09:57'),
(3, 'María Elena', 'Fernández', '11-4567-8901', 'Av. San Martín 1234, Lanús', NULL, 'Siempre pide 24 jamón y queso. Paga en efectivo. Le gusta que sean bien tostaditos. Delivery martes y viernes a las 14:00hs. Muy puntual.', 1, '2025-08-08 17:42:27'),
(4, 'Sebastián', 'Rodriguez', '11-2345-6789', 'Camino Belgrano 5678, Temperley', NULL, 'Ejecutivo que trabaja desde casa. Pide 48 surtidos premium los domingos para la semana. Transfiere siempre. Avisar 1 hora antes por WhatsApp.', 1, '2025-08-08 17:42:27'),
(5, 'Claudia', 'Martinez', '11-8765-4321', 'Calle 12 de Octubre 987, Adrogué', NULL, 'Familia de 6 personas. Pide 48 surtidos clásicos cada 15 días. Mezcla: mitad jamón y queso, mitad con huevo y lechuga. Efectivo. Sábados a la mañana.', 1, '2025-08-08 17:42:27'),
(6, 'Roberto Carlos', 'Giménez', '11-5555-1234', 'Mitre 456, Banfield', NULL, 'Diabético - solo pan lactal, jamón magro sin grasa, queso light. Nada de manteca. 24 unidades cada martes. Muy específico con los ingredientes.', 1, '2025-08-08 17:42:27'),
(7, 'Valentina', 'López', '11-9876-5432', 'Brown 789, Llavallol', NULL, 'Estudiante universitaria. Pide para compartir con amigas. Le gustan los premium con ananá y jamón crudo. Siempre entre 18-20hs. Transferencia.', 1, '2025-08-08 17:42:27'),
(8, 'Estudio Contable', 'González & Asociados', '11-4000-1111', 'Av. Hipólito Yrigoyen 2500, Banfield', NULL, 'EMPRESA - 15 empleados. Piden todos los viernes 48 surtidos especiales para el almuerzo. Factura A necesaria. Delivery 12:30hs puntual. Transferencia siempre.', 1, '2025-08-08 17:42:27'),
(9, 'Taller Mecánico', 'El Tornillo', '11-4000-2222', 'Ruta 4 Km 23.5, Monte Grande', NULL, 'TALLER - 8 operarios. Pedido semanal miércoles: 48 jamón y queso + 24 surtidos. MUY IMPORTANTE: delivery antes de las 11:30 (arrancan a las 12). Efectivo.', 1, '2025-08-08 17:42:27'),
(10, 'Clínica Médica', 'San Rafael', '11-4000-3333', 'Alsina 1800, Temperley', NULL, 'CLÍNICA - Turnos rotativos. Pedidos variables: martes y jueves 48 premium (médicos), sábados 24 clásicos (enfermería). Coordinan con Dra. Pérez. Transfer.', 1, '2025-08-08 17:42:27'),
(11, 'Instituto Educativo', 'San José', '11-4000-4444', 'Belgrano 3200, Lomas de Zamora', NULL, 'COLEGIO - Eventos especiales y reuniones de padres. Pedidos grandes: 96-144 unidades. Avisar con 48hs de anticipación. Mezcla premium y clásicos. Factura.', 1, '2025-08-08 17:42:27'),
(12, 'Ferretería', 'Todo Hogar', '11-4000-5555', 'San Lorenzo 567, Adrogué', NULL, 'FERRETERÍA - Familia trabajadora, 5 personas. Sábados 24 surtidos para almorzar todos juntos después del trabajo. Les gusta probar sabores nuevos. Efectivo.', 1, '2025-08-08 17:42:27');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `historial_precios`
--

CREATE TABLE `historial_precios` (
  `id` int(11) NOT NULL,
  `producto_id` int(11) DEFAULT NULL,
  `precio_anterior_efectivo` decimal(10,2) DEFAULT NULL,
  `precio_anterior_transferencia` decimal(10,2) DEFAULT NULL,
  `precio_nuevo_efectivo` decimal(10,2) DEFAULT NULL,
  `precio_nuevo_transferencia` decimal(10,2) DEFAULT NULL,
  `motivo` varchar(255) DEFAULT NULL,
  `usuario` varchar(100) DEFAULT NULL,
  `fecha_cambio` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pedidos`
--

CREATE TABLE `pedidos` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `apellido` varchar(100) NOT NULL,
  `telefono` varchar(20) NOT NULL,
  `direccion` text DEFAULT NULL,
  `producto` varchar(100) NOT NULL,
  `cantidad` int(11) NOT NULL,
  `precio` decimal(10,2) NOT NULL,
  `forma_pago` enum('Efectivo','Transferencia') DEFAULT 'Efectivo',
  `modalidad` enum('Retira','Delivery') DEFAULT 'Retira',
  `estado` enum('Pendiente','Preparando','Listo','Entregado') DEFAULT 'Pendiente',
  `observaciones` text DEFAULT NULL,
  `cliente_fijo_id` int(11) DEFAULT NULL,
  `impreso` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `fecha_entrega` date DEFAULT NULL COMMENT 'Fecha para cuando es el pedido',
  `hora_entrega` time DEFAULT NULL COMMENT 'Hora para cuando es el pedido',
  `notas_horario` text DEFAULT NULL COMMENT 'Observaciones sobre horario/entrega',
  `fecha_pedido` datetime DEFAULT current_timestamp() COMMENT 'Cuándo se tomó el pedido (puede ser diferente de created_at para casos especiales)',
  `prioridad` enum('normal','prioridad','urgente') DEFAULT 'normal' COMMENT 'Prioridad manual asignada por admin',
  `prioridad_notas` text DEFAULT NULL COMMENT 'Notas sobre por qué se asignó esta prioridad',
  `es_personalizado_complejo` tinyint(1) DEFAULT 0 COMMENT 'Si usa la tabla pedido_detalles para precios mixtos'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `pedidos`
--

INSERT INTO `pedidos` (`id`, `nombre`, `apellido`, `telefono`, `direccion`, `producto`, `cantidad`, `precio`, `forma_pago`, `modalidad`, `estado`, `observaciones`, `cliente_fijo_id`, `impreso`, `created_at`, `updated_at`, `fecha_entrega`, `hora_entrega`, `notas_horario`, `fecha_pedido`, `prioridad`, `prioridad_notas`, `es_personalizado_complejo`) VALUES
(1, 's', 'd', 'f', 'f', '24 Jamón y Queso', 24, 11000.00, 'Efectivo', 'Retira', 'Listo', '', NULL, 1, '2025-08-07 12:04:11', '2025-08-07 14:44:09', NULL, NULL, NULL, '2025-08-07 18:50:22', 'normal', NULL, 0),
(2, 'maxi', 's', 'd', 'f', '48 Surtidos Clásicos', 48, 22000.00, 'Transferencia', 'Retira', 'Preparando', '', NULL, 1, '2025-08-07 12:14:06', '2025-08-07 19:51:21', NULL, NULL, NULL, '2025-08-07 18:50:22', 'normal', NULL, 0),
(4, 'pedido nuevo', 'jasdj', 's1231', '', '24 Jamón y Queso', 24, 11000.00, 'Efectivo', 'Retira', 'Pendiente', '', NULL, 0, '2025-08-07 13:09:46', '2025-08-07 19:17:17', NULL, NULL, NULL, '2025-08-07 18:50:22', 'normal', NULL, 0),
(5, 'ejemplo', '1', '22123', '123123', '24 Jamón y Queso', 24, 11000.00, 'Efectivo', 'Retira', 'Pendiente', '', NULL, 0, '2025-08-07 19:45:03', '2025-08-07 19:45:03', '2025-08-14', '15:00:00', 'asd', '2025-08-07 19:45:03', 'normal', NULL, 0),
(6, 'María', 'González', '+5492214567890', 'Calle 50 N° 1234, La Plata', '48 Jamón y Queso + Paquete', 48, 24000.00, 'Efectivo', 'Delivery', 'Pendiente', 'Descuento efectivo aplicado', NULL, 0, '2025-08-08 17:28:10', '2025-08-08 17:28:10', NULL, NULL, NULL, '2025-08-08 17:28:10', 'normal', NULL, 0),
(7, 'Juan Carlos', 'Pérez', '+5492214567891', 'Av. 7 N° 890, La Plata', '24 Surtidos Premium (Atún, Roquefort, Panceta)', 24, 21000.00, 'Transferencia', 'Delivery', 'Preparando', '8 de cada sabor', NULL, 1, '2025-08-08 17:28:10', '2025-08-08 17:28:10', NULL, NULL, NULL, '2025-08-08 17:28:10', 'normal', NULL, 0),
(8, 'Ana', 'Martínez', '+5492214567892', '', '24 Triples Jamón y Queso + Paquete', 24, 12000.00, 'Efectivo', 'Retira', 'Listo', 'Descuento efectivo', NULL, 1, '2025-08-08 17:28:10', '2025-08-08 17:28:10', NULL, NULL, NULL, '2025-08-08 17:28:10', 'normal', NULL, 0),
(9, 'Carlos', 'López', '+5492214567893', 'Calle 12 N° 567, La Plata', '48 Surtidos Clásicos + Paquete', 48, 22000.00, '', 'Delivery', 'Entregado', 'Con lechuga, tomate, huevo', NULL, 1, '2025-08-08 17:28:10', '2025-08-08 17:28:10', NULL, NULL, NULL, '2025-08-08 17:28:10', 'normal', NULL, 0),
(10, 'Sofía', 'Rodríguez', '+5492214567894', '', '24 Triples Surtidos (J&Q, Lechuga-Tomate, Huevo-Choclo)', 24, 11000.00, 'Efectivo', 'Retira', 'Pendiente', 'Para las 15:00', NULL, 0, '2025-08-08 17:28:10', '2025-08-08 17:28:10', NULL, NULL, NULL, '2025-08-08 17:28:10', 'normal', NULL, 0),
(11, 'Roberto', 'Silva', '+5492214567895', 'Calle 1 N° 234, La Plata', '48 Surtidos Especiales + Paquete', 48, 24000.00, 'Efectivo', 'Delivery', 'Entregado', 'Con aceitunas y choclo', NULL, 1, '2025-08-07 17:28:10', '2025-08-07 17:28:10', NULL, NULL, NULL, '2025-08-08 17:28:10', 'normal', NULL, 0),
(12, 'Lucía', 'Fernández', '+5492214567896', 'Av. 13 N° 456, La Plata', '24 Surtidos Premium (Jamón Crudo, Palmito, Durazno)', 24, 21000.00, 'Transferencia', 'Delivery', 'Entregado', '8 de cada sabor', NULL, 1, '2025-08-07 17:28:10', '2025-08-07 17:28:10', NULL, NULL, NULL, '2025-08-08 17:28:10', 'normal', NULL, 0),
(13, 'Diego', 'Morales', '+5492214567897', '', '24 Triples Surtidos Premium (Anana, Morrón, Salame)', 24, 21000.00, 'Efectivo', 'Retira', 'Entregado', 'Descuento efectivo aplicado', NULL, 1, '2025-08-07 17:28:10', '2025-08-07 17:28:10', NULL, NULL, NULL, '2025-08-08 17:28:10', 'normal', NULL, 0),
(14, 'Valentina', 'Castro', '+5492214567898', 'Calle 60 N° 1890, La Plata', '48 Jamón y Queso', 48, 22000.00, '', 'Delivery', 'Entregado', 'Sin paquete', NULL, 1, '2025-08-07 17:28:10', '2025-08-07 17:28:10', NULL, NULL, NULL, '2025-08-08 17:28:10', 'normal', NULL, 0),
(15, 'Martín', 'Vega', '+5492214567899', 'Av. 44 N° 678, La Plata', '48 Surtidos Premium (Pollo, Berenjena, Atún, Roquefort, Panceta, Jamón Crudo)', 48, 42000.00, 'Efectivo', 'Delivery', 'Entregado', '6 sabores premium, 8 de cada', NULL, 1, '2025-08-05 17:28:10', '2025-08-05 17:28:10', NULL, NULL, NULL, '2025-08-08 17:28:10', 'normal', NULL, 0),
(16, 'Camila', 'Herrera', '+5492214567800', '', '24 Triples Jamón y Queso', 24, 11000.00, 'Transferencia', 'Retira', 'Entregado', 'Para oficina', NULL, 1, '2025-08-05 17:28:10', '2025-08-05 17:28:10', NULL, NULL, NULL, '2025-08-08 17:28:10', 'normal', NULL, 0),
(17, 'Fernando', 'Ruiz', '+5492214567801', 'Calle 25 N° 345, La Plata', '48 Surtidos Clásicos', 48, 20000.00, 'Efectivo', 'Delivery', 'Entregado', 'Sin paquete, descuento efectivo', NULL, 1, '2025-08-05 17:28:10', '2025-08-05 17:28:10', NULL, NULL, NULL, '2025-08-08 17:28:10', 'normal', NULL, 0),
(18, 'Agustina', 'Torres', '+5492214567802', 'Av. 32 N° 912, La Plata', '48 Surtidos Especiales', 48, 22000.00, '', 'Delivery', 'Entregado', 'Para cumpleaños, sin paquete', NULL, 1, '2025-08-01 17:28:10', '2025-08-01 17:28:10', NULL, NULL, NULL, '2025-08-08 17:28:10', 'normal', NULL, 0),
(19, 'Sebastián', 'Díaz', '+5492214567803', '', '24 Surtidos Premium (Palmito, Durazno, Morrón) + Paquete', 24, 22000.00, 'Efectivo', 'Retira', 'Entregado', 'Descuento efectivo', NULL, 1, '2025-08-01 17:28:10', '2025-08-01 17:28:10', NULL, NULL, NULL, '2025-08-08 17:28:10', 'normal', NULL, 0),
(20, 'Patricia', 'Mendoza', '+5492214567804', 'Calle 8 N° 567, La Plata', '48 Surtidos Premium (Anana, Atún, Jamón Crudo, Panceta, Pollo, Salame) + Paquete', 48, 44000.00, 'Transferencia', 'Delivery', 'Entregado', 'Evento familiar, 6 sabores premium', NULL, 1, '2025-07-24 17:28:10', '2025-07-24 17:28:10', NULL, NULL, NULL, '2025-08-08 17:28:10', 'normal', NULL, 0),
(21, 'Claudia', 'Martinez', '11-8765-4321', 'Calle 12 de Octubre 987, Adrogué', '24 Jamón y Queso', 24, 11000.00, 'Efectivo', 'Retira', 'Pendiente', 'Familia de 6 personas. Pide 48 surtidos clásicos cada 15 días. Mezcla: mitad jamón y queso, mitad con huevo y lechuga. Efectivo. Sábados a la mañana.', 5, 0, '2025-08-10 23:39:02', '2025-08-10 23:39:02', '2025-08-10', '00:00:00', '', '2025-08-10 23:39:02', 'normal', NULL, 0),
(22, 'a', 'fsdf', 'df', 'sdf', '24 Surtidos', 24, 12000.00, 'Transferencia', 'Retira', 'Pendiente', '', NULL, 0, '2025-08-11 00:19:27', '2025-08-11 00:19:27', '2025-08-11', '00:00:00', '', '2025-08-11 00:19:27', 'normal', NULL, 0),
(23, 'Clínica Médica', 'San Rafael', '11-4000-3333', 'Alsina 1800, Temperley', '24 Surtidos', 24, 11000.00, 'Efectivo', 'Retira', 'Pendiente', 'CLÍNICA - Turnos rotativos. Pedidos variables: martes y jueves 48 premium (médicos), sábados 24 clásicos (enfermería). Coordinan con Dra. Pérez. Transfer.', 10, 0, '2025-08-11 00:19:50', '2025-08-11 00:19:50', '2025-08-11', '00:00:00', '', '2025-08-11 00:19:50', 'normal', NULL, 0);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pedido_detalles`
--

CREATE TABLE `pedido_detalles` (
  `id` int(11) NOT NULL,
  `pedido_id` int(11) NOT NULL,
  `plancha_numero` int(11) NOT NULL COMMENT 'Número de plancha (1, 2, 3, etc)',
  `sabor` varchar(100) NOT NULL COMMENT 'Nombre del sabor',
  `tipo_sabor` enum('comun','premium') NOT NULL COMMENT 'Si es común o premium',
  `precio_plancha` decimal(10,2) NOT NULL COMMENT 'Precio de esta plancha específica',
  `cantidad_sandwiches` int(11) DEFAULT 8 COMMENT 'Sándwiches en esta plancha (normalmente 8)',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `productos`
--

CREATE TABLE `productos` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `precio_efectivo` decimal(10,2) NOT NULL,
  `precio_transferencia` decimal(10,2) NOT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `categoria` varchar(50) DEFAULT 'Standard',
  `descripcion` text DEFAULT NULL,
  `imagen_url` varchar(255) DEFAULT NULL,
  `orden_mostrar` int(11) DEFAULT 0,
  `updated_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `productos`
--

INSERT INTO `productos` (`id`, `nombre`, `precio_efectivo`, `precio_transferencia`, `activo`, `categoria`, `descripcion`, `imagen_url`, `orden_mostrar`, `updated_by`, `created_at`, `updated_at`) VALUES
(1, '24 Jamón y Queso', 11000.00, 12000.00, 1, 'Clásicos', 'Clásicos sándwiches de jamón y queso. Perfectos para cualquier ocasión.', NULL, 1, NULL, '2025-08-10 23:20:43', '2025-08-10 23:26:22'),
(2, '24 Surtidos', 11000.00, 12000.00, 1, 'Surtidos', 'Variedad de sabores: jamón y queso, lechuga, tomate, huevo, choclo, aceitunas.', NULL, 1, NULL, '2025-08-10 23:20:43', '2025-08-10 23:26:22'),
(3, '24 Surtidos Premium', 21000.00, 22000.00, 1, 'Premium', 'Sabores gourmet: ananá, atún, berenjena, durazno, jamón crudo, morrón, palmito, panceta, pollo, roquefort, salame.', NULL, 1, NULL, '2025-08-10 23:20:43', '2025-08-10 23:26:22'),
(4, '48 Jamón y Queso', 22000.00, 24000.00, 1, 'Clásicos', 'Pack grande de clásicos jamón y queso. Ideal para eventos y reuniones.', NULL, 2, NULL, '2025-08-10 23:20:43', '2025-08-10 23:26:22'),
(5, '48 Surtidos Clásicos', 20000.00, 22000.00, 1, 'Surtidos', 'Variedad clásica en pack grande: jamón y queso, lechuga, tomate, huevo.', NULL, 2, NULL, '2025-08-10 23:20:43', '2025-08-10 23:26:22'),
(6, '48 Surtidos Especiales', 22000.00, 24000.00, 1, 'Surtidos', 'Pack grande con todos los sabores clásicos: incluye choclo y aceitunas.', NULL, 2, NULL, '2025-08-10 23:20:43', '2025-08-10 23:26:22'),
(7, '48 Surtidos Premium', 42000.00, 44000.00, 1, 'Premium', 'Pack grande gourmet con 6 sabores premium a elección.', NULL, 2, NULL, '2025-08-10 23:20:43', '2025-08-10 23:26:22');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `promos`
--

CREATE TABLE `promos` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `precio_efectivo` decimal(10,2) DEFAULT NULL,
  `precio_transferencia` decimal(10,2) DEFAULT NULL,
  `fecha_inicio` date DEFAULT NULL,
  `fecha_fin` date DEFAULT NULL,
  `dias_semana` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`dias_semana`)),
  `condiciones` text DEFAULT NULL,
  `activa` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `hora_inicio` time DEFAULT NULL,
  `hora_fin` time DEFAULT NULL,
  `orden_mostrar` int(11) DEFAULT 100,
  `created_by` varchar(100) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `promos`
--

INSERT INTO `promos` (`id`, `nombre`, `descripcion`, `precio_efectivo`, `precio_transferencia`, `fecha_inicio`, `fecha_fin`, `dias_semana`, `condiciones`, `activa`, `created_at`, `hora_inicio`, `hora_fin`, `orden_mostrar`, `created_by`, `updated_at`) VALUES
(1, 'Promo Fin de Semana', '24 Surtidos Premium a precio especial', 19000.00, 20000.00, NULL, NULL, NULL, NULL, 1, '2025-08-11 00:25:58', NULL, NULL, 100, NULL, '2025-08-11 00:25:58'),
(2, 'Combo Oficina', '48 Jamón y Queso + 24 Surtidos', 32000.00, 34000.00, NULL, NULL, NULL, NULL, 1, '2025-08-11 00:25:58', NULL, NULL, 100, NULL, '2025-08-11 00:25:58');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `usuario` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `nombre` varchar(100) DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id`, `usuario`, `password`, `nombre`, `activo`, `created_at`) VALUES
(1, 'admin', 'Sangu2186', 'Administrador', 1, '2025-08-06 16:41:57'),
(2, 'empleado', 'Emple2186', 'Empleado', 1, '2025-08-06 16:56:30');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `clientes_fijos`
--
ALTER TABLE `clientes_fijos`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `historial_precios`
--
ALTER TABLE `historial_precios`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `pedidos`
--
ALTER TABLE `pedidos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `cliente_fijo_id` (`cliente_fijo_id`);

--
-- Indices de la tabla `pedido_detalles`
--
ALTER TABLE `pedido_detalles`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pedido_id` (`pedido_id`);

--
-- Indices de la tabla `productos`
--
ALTER TABLE `productos`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `promos`
--
ALTER TABLE `promos`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `usuario` (`usuario`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `clientes_fijos`
--
ALTER TABLE `clientes_fijos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT de la tabla `historial_precios`
--
ALTER TABLE `historial_precios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `pedidos`
--
ALTER TABLE `pedidos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT de la tabla `pedido_detalles`
--
ALTER TABLE `pedido_detalles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `productos`
--
ALTER TABLE `productos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT de la tabla `promos`
--
ALTER TABLE `promos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `pedidos`
--
ALTER TABLE `pedidos`
  ADD CONSTRAINT `pedidos_ibfk_1` FOREIGN KEY (`cliente_fijo_id`) REFERENCES `clientes_fijos` (`id`);

--
-- Filtros para la tabla `pedido_detalles`
--
ALTER TABLE `pedido_detalles`
  ADD CONSTRAINT `pedido_detalles_ibfk_1` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
