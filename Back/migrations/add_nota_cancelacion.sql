-- =====================================================
-- MIGRACIÓN: Agregar nota de cancelación a tbl_pedido
-- Fecha: 2026-02-20
-- Descripción: Agrega columna nota_cancelacion para que
--              el admin registre el motivo al cancelar
--              un pedido y el vendedor lo pueda ver.
-- =====================================================

ALTER TABLE tbl_pedido
    ADD COLUMN IF NOT EXISTS nota_cancelacion TEXT DEFAULT NULL;

COMMENT ON COLUMN tbl_pedido.nota_cancelacion IS 'Motivo de cancelación ingresado por el administrador';
