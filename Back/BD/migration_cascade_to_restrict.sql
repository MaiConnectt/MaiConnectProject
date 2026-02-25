-- MIGRACIÓN: ELIMINACIÓN DE CASCADAS Y PREPARACIÓN PARA SOFT DELETE
-- Ejecutar este script para actualizar la base de datos existente

BEGIN;

-- 1. Agregar columnas de estado para soft delete
ALTER TABLE tbl_producto ADD COLUMN IF NOT EXISTS estado VARCHAR(20) DEFAULT 'activo';
ALTER TABLE tbl_pedido ADD COLUMN IF NOT EXISTS estado_logico VARCHAR(20) DEFAULT 'activo';
ALTER TABLE tbl_detalle_pedido ADD COLUMN IF NOT EXISTS estado VARCHAR(20) DEFAULT 'activo';
ALTER TABLE tbl_comprobante_pago ADD COLUMN IF NOT EXISTS estado_registro VARCHAR(20) DEFAULT 'activo';
ALTER TABLE tbl_historial_pedido ADD COLUMN IF NOT EXISTS estado_registro VARCHAR(20) DEFAULT 'activo';

-- 2. Modificar FOREIGN KEYS para usar RESTRICT exclusivamente

-- tbl_miembro
ALTER TABLE tbl_miembro DROP CONSTRAINT IF EXISTS fk_miembro_usuario;
ALTER TABLE tbl_miembro ADD CONSTRAINT fk_miembro_usuario 
    FOREIGN KEY (id_usuario) REFERENCES tbl_usuario (id_usuario) ON DELETE RESTRICT;

-- tbl_pago_comision
ALTER TABLE tbl_pago_comision DROP CONSTRAINT IF EXISTS fk_pago_vendedor;
ALTER TABLE tbl_pago_comision ADD CONSTRAINT fk_pago_vendedor 
    FOREIGN KEY (id_vendedor) REFERENCES tbl_miembro (id_miembro) ON DELETE RESTRICT;

-- tbl_detalle_pedido
ALTER TABLE tbl_detalle_pedido DROP CONSTRAINT IF EXISTS fk_detalle_pedido;
ALTER TABLE tbl_detalle_pedido ADD CONSTRAINT fk_detalle_pedido 
    FOREIGN KEY (id_pedido) REFERENCES tbl_pedido (id_pedido) ON DELETE RESTRICT;

-- tbl_comprobante_pago
ALTER TABLE tbl_comprobante_pago DROP CONSTRAINT IF EXISTS fk_comprobante_pedido;
ALTER TABLE tbl_comprobante_pago ADD CONSTRAINT fk_comprobante_pedido 
    FOREIGN KEY (id_pedido) REFERENCES tbl_pedido (id_pedido) ON DELETE RESTRICT;

-- tbl_historial_pedido
ALTER TABLE tbl_historial_pedido DROP CONSTRAINT IF EXISTS fk_historial_pedido;
ALTER TABLE tbl_historial_pedido ADD CONSTRAINT fk_historial_pedido 
    FOREIGN KEY (id_pedido) REFERENCES tbl_pedido (id_pedido) ON DELETE RESTRICT;

COMMIT;
