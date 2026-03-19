-- ==============================================================================
-- Mai Shop - Funciones de Base de Datos para Edición de Pedidos
-- ==============================================================================

-- Función: Editar Pedido Completo (Actualiza datos, productos y registra historial)
CREATE OR REPLACE FUNCTION fun_editar_pedido(
    p_id_pedido INTEGER,
    p_id_usuario_cambio INTEGER,
    p_nombre_cliente VARCHAR,
    p_telefono_cliente VARCHAR,
    p_direccion_entrega VARCHAR,
    p_fecha_entrega DATE,
    p_estado_nuevo INTEGER,
    p_notas TEXT,
    p_productos JSON -- Arreglo de objetos JSON: [{"name":"Torta M", "quantity":2, "price":30000}, ...]
) RETURNS JSON AS
$$
DECLARE
    v_pedido tbl_pedido%ROWTYPE;
    v_producto JSON;
    v_id_producto INTEGER;
    v_id_detalle_pedido INTEGER;
    v_id_historial INTEGER;
BEGIN
    -- 1. Verificar si el pedido existe y obtener sus datos base
    SELECT id_cliente, estado INTO v_pedido.id_cliente, v_pedido.estado 
    FROM tbl_pedido 
    WHERE id_pedido = p_id_pedido AND estado_logico = 'activo';
    
    IF NOT FOUND THEN
        RETURN json_build_object('success', false, 'message', 'El pedido no existe o está inactivo', 'error_code', 'ORDER_NOT_FOUND');
    END IF;

    -- 2. Actualizar el pedido
    UPDATE tbl_pedido 
    SET estado = p_estado_nuevo, 
        notas = p_notas, 
        fecha_actualizacion = CURRENT_TIMESTAMP, 
        telefono_contacto = p_telefono_cliente, 
        direccion_entrega = p_direccion_entrega, 
        fecha_entrega = COALESCE(p_fecha_entrega, fecha_entrega)
    WHERE id_pedido = p_id_pedido;

    -- Actualizar el nombre del cliente en su tabla (opcional según el form, pero recomendado para mantener sincronía)
    -- Asumimos que queremos cambiar el nombre del cliente (ref) también si fue modificado
    UPDATE tbl_cliente SET nombre = p_nombre_cliente, telefono = p_telefono_cliente WHERE id_cliente = v_pedido.id_cliente;

    -- 3. Marcar detalles anteriores como 'inactivos' (Soft Delete en los detalles)
    UPDATE tbl_detalle_pedido SET estado = 'inactivo' WHERE id_pedido = p_id_pedido;

    -- 4. Iterar sobre los productos enviados en el JSON
    FOR v_producto IN SELECT value FROM json_array_elements(p_productos)
    LOOP
        -- Buscar si el producto existe por nombre exacto
        SELECT id_producto INTO v_id_producto FROM tbl_producto WHERE nombre_producto = v_producto->>'name' LIMIT 1;
        
        -- Si no existe, crearlo al vuelo
        IF v_id_producto IS NULL THEN
            SELECT COALESCE(MAX(id_producto), 0) + 1 INTO v_id_producto FROM tbl_producto;
            INSERT INTO tbl_producto (id_producto, nombre_producto, precio, stock, estado) 
            VALUES (v_id_producto, v_producto->>'name', (v_producto->>'price')::DECIMAL, 0, 'activo');
        END IF;

        -- Insertar el nuevo detalle del pedido
        IF v_id_producto IS NOT NULL AND (v_producto->>'quantity')::INTEGER > 0 THEN
            SELECT COALESCE(MAX(id_detalle_pedido), 0) + 1 INTO v_id_detalle_pedido FROM tbl_detalle_pedido;
            INSERT INTO tbl_detalle_pedido (id_detalle_pedido, id_pedido, id_producto, cantidad, precio_unitario)
            VALUES (v_id_detalle_pedido, p_id_pedido, v_id_producto, (v_producto->>'quantity')::INTEGER, (v_producto->>'price')::DECIMAL);
        END IF;
    END LOOP;

    -- 5. Registrar en el Historial de Pedidos
    INSERT INTO tbl_historial_pedido (id_pedido, usuario_cambio, estado_anterior, estado_nuevo, motivo) 
    VALUES (p_id_pedido, p_id_usuario_cambio, v_pedido.estado, p_estado_nuevo, 'Detalles del pedido editados o actualizados por administrador vía función');

    -- Retornar éxito
    RETURN json_build_object('success', true, 'message', 'Pedido actualizado correctamente', 'id_pedido', p_id_pedido);

EXCEPTION WHEN OTHERS THEN
    RETURN json_build_object('success', false, 'message', 'Error interno: ' || SQLERRM, 'error_code', 'INTERNAL_ERROR');
END;
$$ LANGUAGE plpgsql;
