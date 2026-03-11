-- ==============================================================================
-- Mai Shop - Funciones de Base de Datos para Eliminación Lógica de Vendedores
-- ==============================================================================

-- 4. Función: Desactivar Vendedor (Soft Delete Puro)
CREATE OR REPLACE FUNCTION fun_desactivar_vendedor(
    p_id_miembro INTEGER
) RETURNS JSON AS
$$
BEGIN
    -- 1. Aplicar Eliminación Lógica (Soft Delete) en tabla miembro
    UPDATE tbl_miembro 
    SET estado = 'eliminado', 
        id_estado_miembro = 0, 
        fecha_eliminacion = CURRENT_DATE 
    WHERE id_miembro = p_id_miembro;

    IF NOT FOUND THEN
        RETURN json_build_object('success', false, 'message', 'El vendedor no existe o ya fue eliminado', 'error_code', 'NOT_FOUND');
    END IF;

    -- Retornar confirmación estructurada
    RETURN json_build_object('success', true, 'message', 'Vendedor eliminado lógicamente del sistema', 'id_miembro', p_id_miembro);

EXCEPTION WHEN OTHERS THEN
    RETURN json_build_object('success', false, 'message', 'Error interno: ' || SQLERRM, 'error_code', 'INTERNAL_ERROR');
END;
$$ LANGUAGE plpgsql;

-- 5. Función: Restaurar Vendedor Eliminado
CREATE OR REPLACE FUNCTION fun_restaurar_vendedor(
    p_id_miembro INTEGER
) RETURNS JSON AS
$$
BEGIN
    -- 1. Restaurar vendedor a estado inactivo (para que el Admin decida reactivarlo)
    UPDATE tbl_miembro 
    SET estado = 'inactivo', 
        id_estado_miembro = 2, 
        fecha_eliminacion = NULL 
    WHERE id_miembro = p_id_miembro AND estado = 'eliminado';

    IF NOT FOUND THEN
        RETURN json_build_object('success', false, 'message', 'El vendedor no pudo ser restaurado (no existe o no está en estado eliminado)', 'error_code', 'NOT_FOUND');
    END IF;

    -- Retornar confirmación
    RETURN json_build_object('success', true, 'message', 'Vendedor restaurado correctamente a estado inactivo', 'id_miembro', p_id_miembro);

EXCEPTION WHEN OTHERS THEN
    RETURN json_build_object('success', false, 'message', 'Error interno: ' || SQLERRM, 'error_code', 'INTERNAL_ERROR');
END;
$$ LANGUAGE plpgsql;
