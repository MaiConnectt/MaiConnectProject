-- =====================================================
-- MAI CONNECT - SCHEMA INTEGRAL (3FN + COMPATIBILIDAD PHP)
-- =====================================================

-- 1. DROP EN ORDEN SEGURO (MANTENIENDO INTEGRIDAD)
DROP VIEW IF EXISTS vw_comisiones_vendedor;
DROP VIEW IF EXISTS vw_totales_pedido;

-- Tablas de relación y logs (hojas finales)
DROP TABLE IF EXISTS tbl_sesion_usuario;
DROP TABLE IF EXISTS tbl_log_sistema;
DROP TABLE IF EXISTS tbl_auditoria;
DROP TABLE IF EXISTS tbl_configuracion_general;
DROP TABLE IF EXISTS tbl_movimiento_stock;
DROP TABLE IF EXISTS tbl_rol_permiso;
DROP TABLE IF EXISTS tbl_historial_pedido;
DROP TABLE IF EXISTS tbl_comprobante_pago;
DROP TABLE IF EXISTS tbl_detalle_pedido;

-- Tablas con FKs (nivel intermedio)
DROP TABLE IF EXISTS tbl_pedido;
DROP TABLE IF EXISTS tbl_pago_comision;
DROP TABLE IF EXISTS tbl_miembro;
DROP TABLE IF EXISTS tbl_usuario;

-- Tablas maestras (raíces)
DROP TABLE IF EXISTS tbl_rol;
DROP TABLE IF EXISTS tbl_permiso;
DROP TABLE IF EXISTS tbl_tipo_movimiento_stock;
DROP TABLE IF EXISTS tbl_metodo_pago;
DROP TABLE IF EXISTS tbl_tipo_cancelacion;
DROP TABLE IF EXISTS tbl_estado_miembro;
DROP TABLE IF EXISTS tbl_estado_pago;
DROP TABLE IF EXISTS tbl_estado_pedido;
DROP TABLE IF EXISTS tbl_producto;

-- 2. TABLAS MAESTRAS (ESTADOS Y DEFINICIONES)

CREATE TABLE tbl_rol (
    id_role SMALLINT PRIMARY KEY,
    nombre_rol VARCHAR(50) NOT NULL UNIQUE,
    descripcion TEXT
);

CREATE TABLE tbl_estado_pedido (
    id_estado_pedido SMALLINT PRIMARY KEY,
    nombre_estado VARCHAR(50) NOT NULL UNIQUE
);

CREATE TABLE tbl_estado_pago (
    id_estado_pago SMALLINT PRIMARY KEY,
    nombre_estado VARCHAR(50) NOT NULL UNIQUE
);

CREATE TABLE tbl_estado_miembro (
    id_estado_miembro SMALLINT PRIMARY KEY,
    nombre_estado VARCHAR(50) NOT NULL UNIQUE
);

CREATE TABLE tbl_permiso (
    id_permiso SERIAL PRIMARY KEY,
    nombre_permiso VARCHAR(100) NOT NULL UNIQUE
);

CREATE TABLE tbl_tipo_movimiento_stock (
    id_tipo_movimiento SERIAL PRIMARY KEY,
    nombre_tipo VARCHAR(100) NOT NULL UNIQUE
);

CREATE TABLE tbl_metodo_pago (
    id_metodo_pago SERIAL PRIMARY KEY,
    nombre_metodo VARCHAR(100) NOT NULL UNIQUE
);

-- 3. TABLAS PRINCIPALES (ENTIDADES)

CREATE TABLE tbl_usuario (
    id_usuario SERIAL PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    apellido VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    contrasena VARCHAR(255) NOT NULL,
    id_rol SMALLINT REFERENCES tbl_rol(id_role),
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE tbl_miembro (
    id_miembro SERIAL PRIMARY KEY,
    id_usuario INTEGER NOT NULL UNIQUE REFERENCES tbl_usuario(id_usuario) ON DELETE RESTRICT,
    porcentaje_comision DECIMAL(5, 2) NOT NULL DEFAULT 10.00,
    universidad VARCHAR(150),
    telefono VARCHAR(15),
    estado VARCHAR(20) DEFAULT 'activo', -- Para compatibilidad PHP inmediata
    id_estado_miembro SMALLINT REFERENCES tbl_estado_miembro(id_estado_miembro), -- Para 3FN futura
    fecha_contratacion DATE DEFAULT CURRENT_DATE,
    notas TEXT
);

CREATE TABLE tbl_producto (
    id_producto SERIAL PRIMARY KEY,
    nombre_producto VARCHAR(150) NOT NULL,
    descripcion TEXT,
    precio DECIMAL(10, 2) NOT NULL,
    stock INTEGER NOT NULL DEFAULT 0,
    imagen_principal VARCHAR(255),
    estado VARCHAR(20) DEFAULT 'activo',
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE tbl_pago_comision (
    id_pago_comision SERIAL PRIMARY KEY,
    id_vendedor INTEGER NOT NULL REFERENCES tbl_miembro(id_miembro) ON DELETE RESTRICT,
    monto DECIMAL(10, 2) NOT NULL,
    ruta_archivo VARCHAR(255),
    fecha_pago TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    estado VARCHAR(20) DEFAULT 'completado',
    notas TEXT
);

CREATE TABLE tbl_pedido (
    id_pedido SERIAL PRIMARY KEY,
    id_vendedor INTEGER REFERENCES tbl_miembro(id_miembro) ON DELETE SET NULL,
    telefono_contacto VARCHAR(20) NOT NULL,
    direccion_entrega TEXT NOT NULL,
    fecha_entrega DATE NOT NULL,
    notas TEXT,
    estado SMALLINT NOT NULL DEFAULT 0, -- 0=Pendiente, 1=En Proceso, 2=Completado, 3=Cancelado
    estado_pago SMALLINT DEFAULT 0,    -- 0=Sin Comprobante, 1=Subido, 2=Aprobado, 3=Rechazado
    id_estado_pedido SMALLINT REFERENCES tbl_estado_pedido(id_estado_pedido),
    id_estado_pago SMALLINT REFERENCES tbl_estado_pago(id_estado_pago),
    monto_comision DECIMAL(10, 2),
    id_pago_comision INTEGER REFERENCES tbl_pago_comision(id_pago_comision) ON DELETE SET NULL,
    nota_cancelacion TEXT,
    estado_logico VARCHAR(20) DEFAULT 'activo',
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE tbl_detalle_pedido (
    id_detalle_pedido SERIAL PRIMARY KEY,
    id_pedido INTEGER NOT NULL REFERENCES tbl_pedido(id_pedido) ON DELETE RESTRICT,
    id_producto INTEGER NOT NULL REFERENCES tbl_producto(id_producto) ON DELETE RESTRICT,
    cantidad DECIMAL(10, 2) NOT NULL CHECK (cantidad >= 1),
    precio_unitario DECIMAL(10, 2) NOT NULL,
    estado VARCHAR(20) DEFAULT 'activo'
);

CREATE TABLE tbl_comprobante_pago (
    id_comprobante_pago SERIAL PRIMARY KEY,
    id_pedido INTEGER NOT NULL REFERENCES tbl_pedido(id_pedido) ON DELETE RESTRICT,
    ruta_archivo VARCHAR(255) NOT NULL,
    fecha_subida TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    estado VARCHAR(20) DEFAULT 'pendiente',
    notas TEXT,
    estado_registro VARCHAR(20) DEFAULT 'activo'
);

CREATE TABLE tbl_historial_pedido (
    id_historial SERIAL PRIMARY KEY,
    id_pedido INTEGER NOT NULL REFERENCES tbl_pedido(id_pedido) ON DELETE RESTRICT,
    estado_anterior SMALLINT,
    estado_nuevo SMALLINT NOT NULL,
    usuario_cambio INTEGER REFERENCES tbl_usuario(id_usuario) ON DELETE SET NULL,
    fecha_cambio TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    motivo TEXT,
    estado_registro VARCHAR(20) DEFAULT 'activo'
);

-- 4. OTRAS TABLAS ESTRUCTURALES

CREATE TABLE tbl_rol_permiso (
    id_rol SMALLINT REFERENCES tbl_rol(id_role),
    id_permiso INTEGER REFERENCES tbl_permiso(id_permiso),
    PRIMARY KEY (id_rol, id_permiso)
);

CREATE TABLE tbl_tipo_cancelacion (
    id_tipo_cancelacion SERIAL PRIMARY KEY,
    descripcion VARCHAR(150) NOT NULL
);

CREATE TABLE tbl_movimiento_stock (
    id_movimiento SERIAL PRIMARY KEY,
    id_producto INTEGER NOT NULL REFERENCES tbl_producto(id_producto),
    id_tipo_movimiento INTEGER REFERENCES tbl_tipo_movimiento_stock(id_tipo_movimiento),
    cantidad INTEGER NOT NULL,
    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    usuario_id INTEGER REFERENCES tbl_usuario(id_usuario)
);

CREATE TABLE tbl_auditoria (
    id_auditoria SERIAL PRIMARY KEY,
    id_usuario INTEGER REFERENCES tbl_usuario(id_usuario),
    tabla_afectada VARCHAR(100) NOT NULL,
    accion VARCHAR(50) NOT NULL,
    valor_anterior TEXT,
    valor_nuevo TEXT,
    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE tbl_log_sistema (
    id_log SERIAL PRIMARY KEY,
    nivel VARCHAR(20) NOT NULL,
    mensaje TEXT NOT NULL,
    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE tbl_configuracion_general (
    id_configuracion SERIAL PRIMARY KEY,
    clave VARCHAR(100) NOT NULL UNIQUE,
    valor TEXT
);

CREATE TABLE tbl_sesion_usuario (
    id_sesion SERIAL PRIMARY KEY,
    id_usuario INTEGER NOT NULL REFERENCES tbl_usuario(id_usuario),
    token VARCHAR(255),
    fecha_inicio TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_fin TIMESTAMP,
    activa BOOLEAN DEFAULT TRUE
);

-- 5. DATOS MAESTROS E INICIALES

INSERT INTO tbl_rol (id_role, nombre_rol, descripcion) VALUES
(1, 'ADMIN', 'Administrador del sistema'),
(2, 'VENDEDOR', 'Miembro del equipo de ventas');

INSERT INTO tbl_estado_pedido (id_estado_pedido, nombre_estado) VALUES
(0, 'Pendiente'), (1, 'En Proceso'), (2, 'Completado'), (3, 'Cancelado');

INSERT INTO tbl_estado_pago (id_estado_pago, nombre_estado) VALUES
(0, 'Sin Comprobante'), (1, 'Subido'), (2, 'Aprobado'), (3, 'Rechazado');

INSERT INTO tbl_estado_miembro (id_estado_miembro, nombre_estado) VALUES
(1, 'Activo'), (2, 'Inactivo'), (3, 'Suspendido');

INSERT INTO tbl_usuario (nombre, apellido, email, contrasena, id_rol) VALUES
('Admin', 'Sistema', 'admin@maishop.com', '$2y$10$cnwQTD8nHIx2Z1qIUrCaouWcDtyyoVkGzE4TNfXlrByIgLUSV5/0S', 1),
('Juan', 'Pérez', 'vendedor@maishop.com', '$2y$10$mXYW56m2us6UIU/d7l36Supd193Puln2wsHbk8Jzqpbq.xb25L2lK', 2);

INSERT INTO tbl_miembro (id_usuario, porcentaje_comision, universidad, telefono, estado) 
SELECT id_usuario, 15.00, 'Universidad Central', '3001234567', 'activo' FROM tbl_usuario WHERE email = 'vendedor@maishop.com';

INSERT INTO tbl_producto (nombre_producto, descripcion, precio, stock) VALUES
('Torta de Chocolate', 'Deliciosa torta con ganache', 35000.00, 20),
('Cupcakes de Fresa', 'Decorados con crema natural', 5000.00, 40);

-- 6. VISTAS

CREATE OR REPLACE VIEW vw_totales_pedido AS
SELECT 
    p.id_pedido, p.id_vendedor, p.telefono_contacto, p.direccion_entrega, p.fecha_entrega, 
    p.fecha_creacion, p.estado, p.estado_pago, p.notas,
    COALESCE(SUM(dp.cantidad * dp.precio_unitario), 0) AS total
FROM tbl_pedido p
LEFT JOIN tbl_detalle_pedido dp ON p.id_pedido = dp.id_pedido AND dp.estado = 'activo'
WHERE p.estado_logico = 'activo'
GROUP BY p.id_pedido, p.id_vendedor, p.telefono_contacto, p.direccion_entrega, p.fecha_entrega, 
         p.fecha_creacion, p.estado, p.estado_pago, p.notas;

CREATE OR REPLACE VIEW vw_comisiones_vendedor AS
SELECT 
    m.id_miembro, u.nombre, u.apellido, u.email, m.universidad, m.telefono, m.porcentaje_comision, m.estado, m.fecha_contratacion,
    COUNT(DISTINCT CASE WHEN o.estado != 3 THEN o.id_pedido END) AS total_pedidos,
    COALESCE(SUM(CASE WHEN o.estado = 2 THEN ot.total ELSE 0 END), 0) AS total_ventas,
    COALESCE(SUM(CASE WHEN o.estado = 2 THEN ot.total * m.porcentaje_comision / 100 ELSE 0 END), 0) AS total_comisiones_ganadas,
    COALESCE(SUM(CASE WHEN o.estado = 2 AND o.id_pago_comision IS NOT NULL THEN o.monto_comision ELSE 0 END), 0) AS total_pagado,
    COALESCE(SUM(CASE WHEN o.estado = 2 AND o.id_pago_comision IS NULL THEN o.monto_comision ELSE 0 END), 0) AS saldo_pendiente
FROM tbl_miembro m
JOIN tbl_usuario u ON m.id_usuario = u.id_usuario
LEFT JOIN tbl_pedido o ON m.id_miembro = o.id_vendedor AND o.estado_logico = 'activo'
LEFT JOIN vw_totales_pedido ot ON o.id_pedido = ot.id_pedido
WHERE m.estado = 'activo'
GROUP BY m.id_miembro, u.nombre, u.apellido, u.email, m.universidad, m.telefono, m.porcentaje_comision, m.estado, m.fecha_contratacion;

-- 7. ÍNDICES Y TRIGGERS (RESTAURADOS)

CREATE INDEX idx_usuario_email ON tbl_usuario(email);
CREATE INDEX idx_pedido_vendedor ON tbl_pedido(id_vendedor);
CREATE INDEX idx_detalle_pedido ON tbl_detalle_pedido(id_pedido);

CREATE OR REPLACE FUNCTION actualizar_fecha_modificacion() RETURNS TRIGGER AS $$
BEGIN NEW.fecha_actualizacion = CURRENT_TIMESTAMP; RETURN NEW; END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_usuario_actualizado BEFORE UPDATE ON tbl_usuario FOR EACH ROW EXECUTE FUNCTION actualizar_fecha_modificacion();
CREATE TRIGGER trg_producto_actualizado BEFORE UPDATE ON tbl_producto FOR EACH ROW EXECUTE FUNCTION actualizar_fecha_modificacion();

CREATE OR REPLACE FUNCTION registrar_cambio_estado_pedido() RETURNS TRIGGER AS $$
BEGIN
    IF OLD.estado IS DISTINCT FROM NEW.estado THEN
        INSERT INTO tbl_historial_pedido (id_pedido, estado_anterior, estado_nuevo, motivo)
        VALUES (NEW.id_pedido, OLD.estado, NEW.estado, 'Cambio automático');
    END IF;
    RETURN NEW;
END; $$ LANGUAGE plpgsql;

CREATE TRIGGER trg_pedido_cambio_estado AFTER UPDATE ON tbl_pedido FOR EACH ROW EXECUTE FUNCTION registrar_cambio_estado_pedido();

CREATE OR REPLACE FUNCTION calcular_comision_pedido() RETURNS TRIGGER AS $$
DECLARE v_total DECIMAL(10,2); v_porc DECIMAL(5,2);
BEGIN
    IF OLD.estado IS DISTINCT FROM NEW.estado AND NEW.estado = 2 AND (NEW.monto_comision IS NULL OR NEW.monto_comision = 0) THEN
        SELECT COALESCE(SUM(cantidad * precio_unitario), 0) INTO v_total FROM tbl_detalle_pedido WHERE id_pedido = NEW.id_pedido;
        IF v_total > 0 AND NEW.id_vendedor IS NOT NULL THEN
            SELECT porcentaje_comision INTO v_porc FROM tbl_miembro WHERE id_miembro = NEW.id_vendedor;
            IF v_porc IS NOT NULL THEN NEW.monto_comision := ROUND(v_total * v_porc / 100, 2); END IF;
        END IF;
    END IF;
    RETURN NEW;
END; $$ LANGUAGE plpgsql;

CREATE TRIGGER trg_pedido_calcular_comision BEFORE UPDATE OF estado ON tbl_pedido FOR EACH ROW EXECUTE FUNCTION calcular_comision_pedido();