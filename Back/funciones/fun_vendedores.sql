-- ==============================================================================
-- Mai Shop - Funciones de Base de Datos para Gestión de Vendedores (Equipo)
-- ==============================================================================

-- 1. Función: Crear Vendedor (Usuario + Miembro)
CREATE OR REPLACE FUNCTION fun_crear_vendedor(
    p_nombre VARCHAR,
    p_apellido VARCHAR,
    p_email VARCHAR,
    p_contrasena_hash VARCHAR,
    p_telefono VARCHAR,
    p_universidad VARCHAR,
    p_estado VARCHAR
) RETURNS JSON AS
$$
DECLARE
    v_id_usuario INTEGER;
    v_id_miembro INTEGER;
    v_id_estado_miembro DECIMAL(1,0);
    v_email_existe BOOLEAN;
BEGIN
    -- 1. Validar formato de teléfono numérico de 10 dígitos
    IF p_telefono !~ '^[0-9]{10}$' THEN
        RETURN json_build_object('success', false, 'message', 'El teléfono debe tener exactamente 10 dígitos numéricos', 'error_code', 'INVALID_PHONE');
    END IF;

    -- 2. Validar email único antes de insertar
    SELECT EXISTS(SELECT 1 FROM tbl_usuario WHERE email = p_email) INTO v_email_existe;
    IF v_email_existe THEN
        RETURN json_build_object('success', false, 'message', 'El email ya está registrado', 'error_code', 'EMAIL_EXISTS');
    END IF;

    -- 3. Mapear el estado a su ID correspondiente en tbl_estado_miembro
    v_id_estado_miembro := CASE WHEN p_estado = 'activo' THEN 1 ELSE 2 END;

    -- 4. Autogeneración de IDs (reemplaza uso de MAX en PHP y evita concurrencia)
    SELECT COALESCE(MAX(id_usuario), 0) + 1 INTO v_id_usuario FROM tbl_usuario;
    SELECT COALESCE(MAX(id_miembro), 0) + 1 INTO v_id_miembro FROM tbl_miembro;

    -- 5. Insertar datos en la tabla maestra de usuarios (con rol 2 predeterminado para vendedores)
    INSERT INTO tbl_usuario (id_usuario, nombre, apellido, email, contrasena, id_rol)
    VALUES (v_id_usuario, p_nombre, p_apellido, p_email, p_contrasena_hash, 2);

    -- 6. Insertar datos detallados en la tabla de miembros
    INSERT INTO tbl_miembro (id_miembro, id_usuario, estado, telefono, id_estado_miembro, universidad, fecha_contratacion)
    VALUES (v_id_miembro, v_id_usuario, p_estado, p_telefono, v_id_estado_miembro, p_universidad, CURRENT_DATE);

    -- 7. Retornar éxito estructurado en formato JSON para PHP
    RETURN json_build_object('success', true, 'message', 'Vendedor creado exitosamente', 'id_miembro', v_id_miembro, 'id_usuario', v_id_usuario);

EXCEPTION WHEN OTHERS THEN
    -- Manejo maestro de excepciones (por si se viola alguna restricción de MaiConnect.sql)
    RETURN json_build_object('success', false, 'message', 'Error interno: ' || SQLERRM, 'error_code', 'INTERNAL_ERROR');
END;
$$ LANGUAGE plpgsql;


CREATE OR REPLACE FUNCTION fun_editar_vendedor(
    p_id_miembro INTEGER,
    p_nombre VARCHAR,
    p_apellido VARCHAR,
    p_email VARCHAR,
    p_telefono VARCHAR,
    p_universidad VARCHAR,
    p_estado VARCHAR
) RETURNS JSON AS
$$
DECLARE
    v_id_usuario INTEGER;
    v_id_estado_miembro DECIMAL(1,0);
    v_email_existe BOOLEAN;
BEGIN
    -- 1. Validar formato de teléfono numérico
    IF p_telefono !~ '^[0-9]{10}$' THEN
        RETURN json_build_object('success', false, 'message', 'El teléfono debe tener exactamente 10 dígitos numéricos', 'error_code', 'INVALID_PHONE');
    END IF;

    -- 2. Verificar que el miembro efectivamente exista y recuperar su id_usuario de la relación
    SELECT id_usuario INTO v_id_usuario FROM tbl_miembro WHERE id_miembro = p_id_miembro;
    IF NOT FOUND THEN
        RETURN json_build_object('success', false, 'message', 'El vendedor no existe o fue eliminado', 'error_code', 'SELLER_NOT_FOUND');
    END IF;

    -- 3. Validar choque de email (evitar que robe el correo de OTRO usuario ya existente)
    SELECT EXISTS(SELECT 1 FROM tbl_usuario WHERE email = p_email AND id_usuario != v_id_usuario) INTO v_email_existe;
    IF v_email_existe THEN
        RETURN json_build_object('success', false, 'message', 'Este email ya pertenece a otro usuario', 'error_code', 'EMAIL_EXISTS');
    END IF;

    -- 4. Mapear estado
    v_id_estado_miembro := CASE WHEN p_estado = 'activo' THEN 1 ELSE 2 END;

    -- 5. Actualizar los datos maestros en tbl_usuario
    UPDATE tbl_usuario 
    SET nombre = p_nombre, 
        apellido = p_apellido, 
        email = p_email, 
        fecha_actualizacion = CURRENT_TIMESTAMP
    WHERE id_usuario = v_id_usuario;

    -- 6. Actualizar las condiciones de venta en tbl_miembro
    UPDATE tbl_miembro
    SET estado = p_estado, 
        telefono = p_telefono, 
        universidad = p_universidad,
        id_estado_miembro = v_id_estado_miembro
    WHERE id_miembro = p_id_miembro;

    -- 7. Retornar confirmación
    RETURN json_build_object('success', true, 'message', 'Vendedor actualizado exitosamente', 'id_miembro', p_id_miembro);

EXCEPTION WHEN OTHERS THEN
    RETURN json_build_object('success', false, 'message', 'Error interno: ' || SQLERRM, 'error_code', 'INTERNAL_ERROR');
END;
$$ LANGUAGE plpgsql;
