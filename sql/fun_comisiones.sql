-- ==============================================================================
-- Mai Shop - Funciones de Base de Datos para Pago de Comisiones
-- ==============================================================================

-- 1. Función: Pagar Comisiones (Generar Comprobante y Ligar Pedidos)
CREATE OR REPLACE FUNCTION fun_pagar_comisiones(
    p_id_miembro INTEGER,
    p_monto DECIMAL,
    p_ruta_archivo VARCHAR,
    p_ids_pedidos JSON -- Espera un arreglo JSON de enteros: [101, 102, 105]
) RETURNS JSON AS
$$
DECLARE
    v_id_pago_comision INTEGER;
    v_id_pedido INTEGER;
BEGIN
    -- 1. Validaciones básicas
    IF p_monto <= 0 THEN
        RETURN json_build_object('success', false, 'message', 'El monto a pagar debe ser mayor a cero', 'error_code', 'INVALID_AMOUNT');
    END IF;

    IF json_array_length(p_ids_pedidos) = 0 THEN
        RETURN json_build_object('success', false, 'message', 'No hay pedidos seleccionados para pagar', 'error_code', 'EMPTY_ORDERS');
    END IF;

    -- 2. Verificar que el miembro exista
    IF NOT EXISTS (SELECT 1 FROM tbl_miembro WHERE id_miembro = p_id_miembro) THEN
        RETURN json_build_object('success', false, 'message', 'El vendedor no existe', 'error_code', 'SELLER_NOT_FOUND');
    END IF;

    -- 3. Generar ID manual para el Comprobante de Pago
    SELECT COALESCE(MAX(id_pago_comision), 0) + 1 INTO v_id_pago_comision FROM tbl_pago_comision;

    -- 4. Insertar el Comprobante Maestro en tbl_pago_comision
    INSERT INTO tbl_pago_comision (
        id_pago_comision, id_vendedor, monto, ruta_archivo, estado, fecha_pago
    )
    VALUES (
        v_id_pago_comision, p_id_miembro, p_monto, p_ruta_archivo, 'completado', CURRENT_TIMESTAMP
    );

    -- 5. Actualizar los pedidos vinculándolos a este comprobante
    -- Extraer cada ID del arreglo JSON y actualizar el pedido
    FOR v_id_pedido IN SELECT (value)::INTEGER FROM json_array_elements_text(p_ids_pedidos) AS value
    LOOP
        IF v_id_pedido IS NOT NULL THEN
            UPDATE tbl_pedido 
            SET id_pago_comision = v_id_pago_comision 
            WHERE id_pedido = v_id_pedido 
            AND id_vendedor = p_id_miembro 
            AND estado = 2
            AND (id_pago_comision IS NULL OR id_pago_comision = 0); -- Seguridad anti-duplicados
        END IF;
    END LOOP;

    -- 6. Respuesta exitosa a PHP
    RETURN json_build_object('success', true, 'message', 'Comisiones pagadas y registradas exitosamente', 'id_pago_comision', v_id_pago_comision);

EXCEPTION WHEN OTHERS THEN
    -- Rollback Automático (PL/pgSQL revierte la transacción)
    RETURN json_build_object('success', false, 'message', 'Error interno: ' || SQLERRM, 'error_code', 'INTERNAL_ERROR');
END;
$$ LANGUAGE plpgsql;
