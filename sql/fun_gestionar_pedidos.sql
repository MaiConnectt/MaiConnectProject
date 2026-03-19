-- ==============================================================================
-- Mai Shop - Funciones de Base de Datos para Gestión de Estados y Eliminación de Pedidos
-- ==============================================================================

-- 1. Función: Gestionar Estado del Pedido (Cambios de estado, pagos y producción)
CREATE OR REPLACE FUNCTION fun_gestionar_estado_pedido(
    p_id_pedido INTEGER,
    p_id_usuario_cambio INTEGER,
    p_accion VARCHAR, -- Valores esperados: 'aprobar_pago', 'rechazar_pago', 'mandar_produccion', 'cancelar_pedido', 'cambio_directo'
    p_estado_nuevo INTEGER, -- Se utiliza fuertemente para 'cambio_directo'. En otros casos se autocalcula.
    p_notas TEXT
) RETURNS JSON AS
$$
DECLARE
    v_pedido tbl_pedido%ROWTYPE;
    v_estado_final INTEGER;
    v_pago_final INTEGER;
    v_id_historial INTEGER;
    v_motivo_historial VARCHAR;
BEGIN
    SELECT estado, estado_pago INTO v_pedido.estado, v_pedido.estado_pago FROM tbl_pedido WHERE id_pedido = p_id_pedido;
    IF NOT FOUND THEN
        RETURN json_build_object('success', false, 'message', 'Pedido no encontrado', 'error_code', 'NOT_FOUND');
    END IF;

    -- Pre-asignar estado y pago de cómo están actualmente (para no alterarlos si no toca)
    v_estado_final := v_pedido.estado;
    v_pago_final := v_pedido.estado_pago;

    -- Determinar lógica de negocio según la acción
    IF p_accion = 'aprobar_pago' THEN
        IF v_pago_final != 1 THEN
            RETURN json_build_object('success', false, 'message', 'No hay un comprobante pendiente de validar', 'error_code', 'NO_PENDING_PAYMENT');
        END IF;
        
        UPDATE tbl_comprobante_pago SET estado = 'aprobado' WHERE id_pedido = p_id_pedido AND estado = 'pendiente';
        v_pago_final := 2;
        v_motivo_historial := 'Pago aprobado exitosamente.';

    ELSIF p_accion = 'rechazar_pago' THEN
        IF COALESCE(p_notas, '') = '' THEN
            RETURN json_build_object('success', false, 'message', 'Debes indicar el motivo del rechazo', 'error_code', 'MISSING_NOTES');
        END IF;

        UPDATE tbl_comprobante_pago SET estado = 'rechazado', notas = p_notas WHERE id_pedido = p_id_pedido AND estado = 'pendiente';
        v_pago_final := 3;
        v_motivo_historial := 'Pago rechazado: ' || p_notas;

    ELSIF p_accion = 'mandar_produccion' THEN
        IF v_estado_final != 0 THEN
            RETURN json_build_object('success', false, 'message', 'El pedido ya no está pendiente', 'error_code', 'NOT_PENDING');
        END IF;
        IF v_pago_final != 2 THEN
            RETURN json_build_object('success', false, 'message', 'No se puede mandar a producción sin pago aprobado', 'error_code', 'NOT_PAID');
        END IF;

        v_estado_final := 1;
        v_motivo_historial := 'Pedido enviado a producción.';

    ELSIF p_accion = 'cancelar_pedido' THEN
        IF COALESCE(p_notas, '') = '' THEN
            RETURN json_build_object('success', false, 'message', 'Debes indicar el motivo de la cancelación', 'error_code', 'MISSING_NOTES');
        END IF;
        IF v_estado_final = 1 AND v_pago_final = 2 THEN
            RETURN json_build_object('success', false, 'message', 'No se puede cancelar en producción con pago aprobado', 'error_code', 'CANNOT_CANCEL');
        END IF;

        v_estado_final := 3;
        v_motivo_historial := 'Cancelado por administrador: ' || p_notas;

    ELSIF p_accion = 'cambio_directo' THEN
        -- Viene del archivo cambiar_estado.php
        IF p_estado_nuevo = 3 AND COALESCE(p_notas, '') = '' THEN
            RETURN json_build_object('success', false, 'message', 'Debes ingresar el motivo de cancelación', 'error_code', 'MISSING_NOTES');
        END IF;
        IF p_estado_nuevo = 3 AND v_estado_final = 1 AND v_pago_final = 2 THEN
            RETURN json_build_object('success', false, 'message', 'No se puede cancelar: el pago ya fue aprobado y el pedido está en producción', 'error_code', 'CANNOT_CANCEL');
        END IF;
        
        v_estado_final := p_estado_nuevo;
        IF p_estado_nuevo = 3 THEN
             v_motivo_historial := 'Cancelado por administrador: ' || p_notas;
        ELSE
             v_motivo_historial := 'Cambio de estado desde el panel de administración';
        END IF;
    ELSE
        RETURN json_build_object('success', false, 'message', 'Acción desconocida', 'error_code', 'UNKNOWN_ACTION');
    END IF;

    -- VALIDAR TRANSICIÓN DE ESTADO (Nueva Lógica Workflow)
    IF v_pedido.estado != v_estado_final THEN
        IF NOT EXISTS (
            SELECT 1 FROM tbl_estado_transicion
            WHERE estado_actual = v_pedido.estado
            AND estado_siguiente = v_estado_final
        ) THEN
            RETURN json_build_object(
                'success', false, 
                'message', 'Cambio de estado no permitido. Secuencia inválida.', 
                'error_code', 'INVALID_TRANSITION'
            );
        END IF;
    END IF;

    -- Actualizar Tabla Pedido Oficialmente
    IF p_accion = 'cancelar_pedido' OR (p_accion = 'cambio_directo' AND p_estado_nuevo = 3) THEN
        UPDATE tbl_pedido SET estado = v_estado_final, estado_pago = v_pago_final, nota_cancelacion = p_notas WHERE id_pedido = p_id_pedido;
    ELSE
        UPDATE tbl_pedido SET estado = v_estado_final, estado_pago = v_pago_final WHERE id_pedido = p_id_pedido;
    END IF;

    -- Insertar en el Historial Triggers (Solo si hubo un cambio real o una cancelación oficial)
    INSERT INTO tbl_historial_pedido (id_pedido, usuario_cambio, estado_anterior, estado_nuevo, motivo) 
    VALUES (p_id_pedido, p_id_usuario_cambio, v_pedido.estado, v_estado_final, COALESCE(p_notas, v_motivo_historial));

    RETURN json_build_object('success', true, 'message', v_motivo_historial, 'estado_nuevo', v_estado_final);

EXCEPTION WHEN OTHERS THEN
    RETURN json_build_object('success', false, 'message', 'Error interno: ' || SQLERRM, 'error_code', 'INTERNAL_ERROR');
END;
$$ LANGUAGE plpgsql;


-- ==============================================================================

-- 2. Función: Eliminar Pedido (Soft Delete)
CREATE OR REPLACE FUNCTION fun_eliminar_pedido(
    p_id_pedido INTEGER,
    p_id_usuario_cambio INTEGER
) RETURNS JSON AS
$$
DECLARE
    v_estado_actual INTEGER;
    v_id_historial INTEGER;
BEGIN
    SELECT estado INTO v_estado_actual FROM tbl_pedido WHERE id_pedido = p_id_pedido AND estado_logico = 'activo';
    
    IF NOT FOUND THEN
        RETURN json_build_object('success', false, 'message', 'Pedido no encontrado o ya eliminado', 'error_code', 'NOT_FOUND');
    END IF;

    -- 1. Aplicar Soft Delete
    UPDATE tbl_pedido SET estado_logico = 'inactivo' WHERE id_pedido = p_id_pedido;

    -- 2. Registrar en historial
    INSERT INTO tbl_historial_pedido (id_pedido, usuario_cambio, estado_anterior, estado_nuevo, motivo) 
    VALUES (p_id_pedido, p_id_usuario_cambio, v_estado_actual, v_estado_actual, 'Pedido eliminado por el administrador (Soft Delete)');

    RETURN json_build_object('success', true, 'message', 'Pedido eliminado correctamente');

EXCEPTION WHEN OTHERS THEN
    RETURN json_build_object('success', false, 'message', 'Error interno: ' || SQLERRM, 'error_code', 'INTERNAL_ERROR');
END;
$$ LANGUAGE plpgsql;
