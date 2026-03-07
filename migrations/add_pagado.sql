-- Agregar columna pagado a tabla pedidos
-- Permite marcar un pedido como pago confirmado
-- Util para pedidos por Transferencia que se deben verificar

ALTER TABLE pedidos
ADD COLUMN pagado TINYINT(1) NOT NULL DEFAULT 0
COMMENT '0 = Pendiente pago, 1 = Pago confirmado';

-- Marcar pedidos en Efectivo como pagados por defecto (se paga en el momento)
UPDATE pedidos
SET pagado = 1
WHERE forma_pago = 'Efectivo';

-- Verificar resultado
SELECT id, nombre, forma_pago, pagado
FROM pedidos
ORDER BY id DESC
LIMIT 10;
