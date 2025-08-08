-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1:3306
-- Tiempo de generación: 08-08-2025 a las 14:28:58
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
(2, 'a', 's', 'd', '2', NULL, '', 0, '2025-08-06 17:09:57');

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
(5, 'ejemplo', '1', '22123', '123123', '24 Jamón y Queso', 24, 11000.00, 'Efectivo', 'Retira', 'Pendiente', '', NULL, 0, '2025-08-07 19:45:03', '2025-08-07 19:45:03', '2025-08-14', '15:00:00', 'asd', '2025-08-07 19:45:03', 'normal', NULL, 0);

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
  `activo` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `productos`
--

INSERT INTO `productos` (`id`, `nombre`, `precio_efectivo`, `precio_transferencia`, `activo`) VALUES
(1, '24 Jamón y Queso', 11000.00, 12000.00, 1),
(2, '24 Surtidos', 11000.00, 12000.00, 1),
(3, '24 Surtidos Premium', 21000.00, 22000.00, 1),
(4, '48 Jamón y Queso', 22000.00, 24000.00, 1),
(5, '48 Surtidos Clásicos', 20000.00, 22000.00, 1),
(6, '48 Surtidos Especiales', 22000.00, 24000.00, 1),
(7, '48 Surtidos Premium', 42000.00, 44000.00, 1);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `pedidos`
--
ALTER TABLE `pedidos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

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
