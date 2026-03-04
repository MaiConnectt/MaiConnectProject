-- ==============================================================================
-- Mai Shop - Funciones de Base de Datos para Gestión de Pedidos
-- ==============================================================================

-- 1. Función: Crear Pedido (Maestro + Detalle + Historial + Productos Nuevos)
CREATE OR REPLACE FUNCTION fun_crear_pedido(
    p_id_usuario_ejecutor INTEGER,
    p_telefono_contacto VARCHAR,
    p_direccion_entrega TEXT,
    p_fecha_entrega DATE,
    p_estado INTEGER,
    p_notas TEXT,
    p_productos JSON -- Espera un arreglo JSON: [{"name":"Torta","quantity":1,"price":50000}]
) RETURNS JSON AS
$$
DECLARE
    v_id_pedido INTEGER;
    v_id_producto INTEGER;
    v_id_detalle_pedido INTEGER;
    v_id_historial INTEGER;
    v_monto_comision DECIMAL(10,2) := 0;
    
    -- Variables para iterar el JSON de productos
    v_producto JSON;
    v_prod_name VARCHAR;
    v_prod_qty INTEGER;
    v_prod_price DECIMAL(10,2);
BEGIN
    -- 1. Validaciones básicas
    IF p_telefono_contacto IS NULL OR p_telefono_contacto = '' THEN
        RETURN json_build_object('success', false, 'message', 'El teléfono del cliente es obligatorio', 'error_code', 'EMPTY_PHONE');
    END IF;

    IF json_array_length(p_productos) = 0 THEN
        RETURN json_build_object('success', false, 'message', 'Debe agregar al menos un producto', 'error_code', 'EMPTY_PRODUCTS');
    END IF;

    -- 2. Generar ID manual para el Pedido Maestro
    SELECT COALESCE(MAX(id_pedido), 0) + 1 INTO v_id_pedido FROM tbl_pedido;

    -- 3. Insertar el Pedido (id_vendedor es NULL inicialmente para administración)
    INSERT INTO tbl_pedido (
        id_pedido, id_vendedor, estado, notas, fecha_creacion, 
        monto_comision, telefono_contacto, direccion_entrega, fecha_entrega
    )
    VALUES (
        v_id_pedido, NULL, p_estado, p_notas, CURRENT_TIMESTAMP, 
        v_monto_comision, p_telefono_contacto, p_direccion_entrega, p_fecha_entrega
    );

    -- 4. Procesar Detalle de Pedidos y Productos Dinámicos (Loop JSON)
    FOR v_producto IN SELECT value FROM json_array_elements(p_productos)
    LOOP
        v_prod_name := v_producto->>'name';
        v_prod_qty := (v_producto->>'quantity')::INTEGER;
        v_prod_price := (v_producto->>'price')::DECIMAL;

        IF v_prod_name IS NOT NULL AND v_prod_qty > 0 AND v_prod_price >= 0 THEN
            
            -- 4.1. Buscar si el producto ya existe por su nombre
            SELECT id_producto INTO v_id_producto FROM tbl_producto WHERE nombre_producto = v_prod_name LIMIT 1;
            
            -- 4.2. Si no existe, crearlo al vuelo (almacenar en tbl_producto)
            IF NOT FOUND THEN
                SELECT COALESCE(MAX(id_producto), 0) + 1 INTO v_id_producto FROM tbl_producto;
                
                INSERT INTO tbl_producto (id_producto, nombre_producto, precio, stock, estado) 
                VALUES (v_id_producto, v_prod_name, v_prod_price, 0, 'activo');
            END IF;

            -- 4.3. Generar ID y registrar el detalle del pedido
            SELECT COALESCE(MAX(id_detalle_pedido), 0) + 1 INTO v_id_detalle_pedido FROM tbl_detalle_pedido;
            
            INSERT INTO tbl_detalle_pedido (id_detalle_pedido, id_pedido, id_producto, cantidad, precio_unitario)
            VALUES (v_id_detalle_pedido, v_id_pedido, v_id_producto, v_prod_qty, v_prod_price);
            
        END IF;
    END LOOP;

    -- 5. Registrar en el Historial de Cambios del Pedido
    INSERT INTO tbl_historial_pedido (id_pedido, usuario_cambio, estado_anterior, estado_nuevo, motivo) 
    VALUES (v_id_pedido, p_id_usuario_ejecutor, NULL, p_estado, 'Pedido creado desde el panel de administración');

    -- 6. Respuesta existosa a PHP
    RETURN json_build_object('success', true, 'message', 'Pedido creado exitosamente', 'id_pedido', v_id_pedido);

EXCEPTION WHEN OTHERS THEN
    -- Rollback Automático (PL/pgSQL revierte toda la transacción en caso de error)
    RETURN json_build_object('success', false, 'message', 'Error interno: ' || SQLERRM, 'error_code', 'INTERNAL_ERROR');
END;
$$ LANGUAGE plpgsql;
