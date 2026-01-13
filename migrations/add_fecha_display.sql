-- Agregar columna fecha_display a tabla pedidos
-- Esta columna almacena la fecha ya formateada en timezone de Argentina
-- Formato: "DD/MM HH:mm" (ej: "13/01 23:35")

ALTER TABLE pedidos
ADD COLUMN fecha_display VARCHAR(20) DEFAULT NULL
COMMENT 'Fecha formateada para mostrar (timezone Argentina)';

-- Llenar datos existentes con fecha formateada
UPDATE pedidos
SET fecha_display = DATE_FORMAT(
    CONVERT_TZ(created_at, '+00:00', '-03:00'),
    '%d/%m %H:%i'
)
WHERE fecha_display IS NULL;

-- Verificar resultado
SELECT id, created_at, fecha_display
FROM pedidos
ORDER BY id DESC
LIMIT 10;
