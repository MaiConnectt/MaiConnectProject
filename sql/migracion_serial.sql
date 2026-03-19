-- ==============================================================================
-- Migración: Convertir id_pedido e id_detalle_pedido a SERIAL (auto-incremento)
-- Ejecutar UNA SOLA VEZ en PostgreSQL
-- ==============================================================================

-- 1. Crear secuencia para tbl_pedido
CREATE SEQUENCE IF NOT EXISTS tbl_pedido_id_pedido_seq;

-- Ajustar la secuencia al valor máximo actual (mínimo 1)
SELECT setval('tbl_pedido_id_pedido_seq', GREATEST(COALESCE((SELECT MAX(id_pedido) FROM tbl_pedido), 0), 1));

-- Asignar la secuencia como DEFAULT
ALTER TABLE tbl_pedido ALTER COLUMN id_pedido SET DEFAULT nextval('tbl_pedido_id_pedido_seq');

-- Vincular la secuencia a la columna
ALTER SEQUENCE tbl_pedido_id_pedido_seq OWNED BY tbl_pedido.id_pedido;

-- 2. Crear secuencia para tbl_detalle_pedido
CREATE SEQUENCE IF NOT EXISTS tbl_detalle_pedido_id_seq;

SELECT setval('tbl_detalle_pedido_id_seq', GREATEST(COALESCE((SELECT MAX(id_detalle_pedido) FROM tbl_detalle_pedido), 0), 1));

ALTER TABLE tbl_detalle_pedido ALTER COLUMN id_detalle_pedido SET DEFAULT nextval('tbl_detalle_pedido_id_seq');

ALTER SEQUENCE tbl_detalle_pedido_id_seq OWNED BY tbl_detalle_pedido.id_detalle_pedido;
