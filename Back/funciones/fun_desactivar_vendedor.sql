-- ==============================================================================
-- Mai Shop - Funciones de Base de Datos para Eliminación Lógica de Vendedores
-- ==============================================================================

-- 4. Función: Desactivar Vendedor (Validar Integridad Lógica)
CREATE OR REPLACE FUNCTION fun_desactivar_vendedor(
    p_id_miembro INTEGER
) RETURNS JSON AS
$$
DECLARE
    v_conteo INTEGER;
    v_id_estado_inactivo DECIMAL(1,0) := 2; -- Asumiendo que 2 es inactivo puro en tbl_estado_miembro
    v_id_usuario INTEGER;
BEGIN
    -- 1. Verificar si el miembro existe y obtener su id_usuario de paso
    SELECT id_usuario INTO v_id_usuario FROM tbl_miembro WHERE id_miembro = p_id_miembro;
    IF NOT FOUND THEN
        RETURN json_build_object('success', false, 'message', 'El vendedor no existe o ya fue eliminado', 'error_code', 'NOT_FOUND');
    END IF;

    -- 2. Validar si el vendedor tiene pedidos asociados activos o en general
    SELECT COUNT(id_pedido) INTO v_conteo FROM tbl_pedido WHERE id_vendedor = p_id_miembro;
    IF v_conteo > 0 THEN
        RETURN json_build_object('success', false, 'message', 'No se puede eliminar porque tiene pedidos asociados. El vendedor está blindado.', 'error_code', 'HAS_ORDERS');
    END IF;

    -- 3. Validar si ya se le han registrado pagos de comisión (historial contable)
    SELECT COUNT(id_pago_comision) INTO v_conteo FROM tbl_pago_comision WHERE id_vendedor = p_id_miembro;
    IF v_conteo > 0 THEN
        RETURN json_build_object('success', false, 'message', 'No se puede eliminar porque tiene pagos de comisión registrados contablemente.', 'error_code', 'HAS_PAYMENTS');
    END IF;

    -- 4. Aplicar Eliminación Lógica (Soft Delete) en tabla miembro
    UPDATE tbl_miembro 
    SET estado = 'inactivo', 
        id_estado_miembro = v_id_estado_inactivo 
    WHERE id_miembro = p_id_miembro;

    -- Retornar confirmación estructurada
    RETURN json_build_object('success', true, 'message', 'Vendedor desactivado exitosamente (Eliminación lógica)', 'id_miembro', p_id_miembro);

EXCEPTION WHEN OTHERS THEN
    RETURN json_build_object('success', false, 'message', 'Error interno: ' || SQLERRM, 'error_code', 'INTERNAL_ERROR');
END;
$$ LANGUAGE plpgsql;
