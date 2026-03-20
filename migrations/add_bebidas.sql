-- Migración: sistema de bebidas por pedido
-- Ejecutar una sola vez

CREATE TABLE IF NOT EXISTS `bebidas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `orden` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Columnas en pedidos para almacenar bebidas seleccionadas
ALTER TABLE `pedidos`
  ADD COLUMN IF NOT EXISTS `bebidas_json` TEXT DEFAULT NULL AFTER `pagado`,
  ADD COLUMN IF NOT EXISTS `bebidas_precio` INT DEFAULT NULL AFTER `bebidas_json`;

-- Bebidas iniciales (de la heladera)
INSERT IGNORE INTO `bebidas` (`nombre`, `orden`) VALUES
  ('Monster Ultra',           1),
  ('Monster Azul',            2),
  ('Monster Verde',           3),
  ('Monster Negro',           4),
  ('Monster Reserva Ananás',  5),
  ('Coca-Cola 500ml',         6),
  ('Coca-Cola 2.25L',         7),
  ('Sprite 500ml',            8),
  ('Sprite 2L',               9),
  ('Fanta 500ml',            10),
  ('Fanta 2L',               11),
  ('Baggio Pronto Naranja',  12),
  ('Baggio Pronto Multifrutas', 13),
  ('Baggio Fresh Manzana',   14),
  ('Baggio Fresh',           15);
