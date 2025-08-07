-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1:3306
-- Tiempo de generación: 07-08-2025 a las 12:21:22
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
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `pedidos`
--

INSERT INTO `pedidos` (`id`, `nombre`, `apellido`, `telefono`, `direccion`, `producto`, `cantidad`, `precio`, `forma_pago`, `modalidad`, `estado`, `observaciones`, `cliente_fijo_id`, `impreso`, `created_at`, `updated_at`) VALUES
(1, 's', 'd', 'f', 'f', '24 Jamón y Queso', 24, 11000.00, 'Efectivo', 'Retira', 'Pendiente', '', NULL, 0, '2025-08-07 12:04:11', '2025-08-07 12:04:11'),
(2, 'maxi', 's', 'd', 'f', '48 Surtidos Clásicos', 48, 22000.00, 'Transferencia', 'Retira', 'Pendiente', '', NULL, 0, '2025-08-07 12:14:06', '2025-08-07 12:14:06'),
(3, 'd', 'f', 's', 'a', '48 Surtidos Premium', 48, 42000.00, 'Efectivo', 'Retira', 'Pendiente', '', NULL, 0, '2025-08-07 12:14:18', '2025-08-07 12:14:18');

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

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
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
