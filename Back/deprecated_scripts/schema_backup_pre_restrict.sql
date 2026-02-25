-- MAI SHOP - SCHEMA BACKUP (BEFORE CASCADE REMOVAL)
-- Generation Date: 2026-02-23

-- Table structures based on MaiShop_Spanish_Init.sql

-- tbl_rol
-- tbl_usuario
-- tbl_miembro (FK: id_usuario -> tbl_usuario CASCADE)
-- tbl_producto
-- tbl_pago_comision (FK: id_vendedor -> tbl_miembro CASCADE)
-- tbl_pedido (FK: id_vendedor -> tbl_miembro SET NULL, id_pago_comision -> tbl_pago_comision SET NULL)
-- tbl_detalle_pedido (FK: id_pedido -> tbl_pedido CASCADE, id_producto -> tbl_producto RESTRICT)
-- tbl_comprobante_pago (FK: id_pedido -> tbl_pedido CASCADE)
-- tbl_historial_pedido (FK: id_pedido -> tbl_pedido CASCADE, usuario_cambio -> tbl_usuario SET NULL)

-- Note: This is an architectural backup of constraints to allow rollback.

-- Current constraints with CASCADE:
-- tbl_miembro: fk_miembro_usuario (ON DELETE CASCADE)
-- tbl_pago_comision: fk_pago_vendedor (ON DELETE CASCADE)
-- tbl_detalle_pedido: fk_detalle_pedido (ON DELETE CASCADE)
-- tbl_comprobante_pago: fk_comprobante_pedido (ON DELETE CASCADE)
-- tbl_historial_pedido: fk_historial_pedido (ON DELETE CASCADE)
