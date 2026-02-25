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

-- Definición de roles del sistema (Admin, Vendedor, etc.)
CREATE TABLE tbl_rol (
    id_role         SMALLINT           PRIMARY KEY,          -- Identificador único del rol
    nombre_rol      VARCHAR(50)        NOT NULL UNIQUE,      -- Nombre descriptivo (ej. ADMIN)
    descripcion     TEXT                                     -- Detalles adicionales del rol
);

-- Estados posibles de un pedido (0=Pendiente, 1=Proceso, etc.)
CREATE TABLE tbl_estado_pedido (
    id_estado_pedido SMALLINT          PRIMARY KEY,          -- ID del estado (numérico)
    nombre_estado    VARCHAR(50)       NOT NULL UNIQUE       -- Nombre del estado (ej. Pendiente)
);

-- Estados del comprobante de pago subido por el cliente
CREATE TABLE tbl_estado_pago (
    id_estado_pago   SMALLINT          PRIMARY KEY,          -- ID del estado de pago
    nombre_estado    VARCHAR(50)       NOT NULL UNIQUE       -- Descripcion (ej. Aprobado)
);

-- Estados de membresía para vendedores
CREATE TABLE tbl_estado_miembro (
    id_estado_miembro SMALLINT         PRIMARY KEY,          -- ID del estado de miembro
    nombre_estado     VARCHAR(50)      NOT NULL UNIQUE       -- Descripcion (ej. Activo)
);

-- Permisos específicos para granularidad de acceso (RBAC)
CREATE TABLE tbl_permiso (
    id_permiso      INTEGER            PRIMARY KEY,          -- ID manual del permiso
    nombre_permiso  VARCHAR(100)       NOT NULL UNIQUE       -- Nombre técnico del permiso
);

-- Tipos de movimientos en inventario (Entrada/Salida)
CREATE TABLE tbl_tipo_movimiento_stock (
    id_tipo_movimiento INTEGER         PRIMARY KEY,          -- ID del tipo de movimiento
    nombre_tipo     VARCHAR(100)       NOT NULL UNIQUE       -- Nombre (ej. Venta, Ajuste)
);

-- Métodos de pago permitidos
CREATE TABLE tbl_metodo_pago (
    id_metodo_pago  INTEGER            PRIMARY KEY,          -- 1=Nequi, 2=Daviplata, etc.
    nombre_metodo   VARCHAR(100)       NOT NULL UNIQUE       -- Nombre del método de pago
);

-- 3. TABLAS PRINCIPALES (ENTIDADES)

-- Usuarios del sistema (Credenciales y datos básicos)
CREATE TABLE tbl_usuario (
    id_usuario      INTEGER            PRIMARY KEY,          -- Identificador único (Manual)
    nombre          VARCHAR(100)       NOT NULL,             -- Nombres del usuario
    apellido        VARCHAR(100)       NOT NULL,             -- Apellidos del usuario
    email           VARCHAR(100)       NOT NULL UNIQUE,      -- Correo (usado para login)
    contrasena      VARCHAR(255)       NOT NULL,             -- Hash BCrypt de la contraseña
    id_rol          SMALLINT           REFERENCES tbl_rol(id_role), -- FK a roles
    fecha_creacion  TIMESTAMP          DEFAULT CURRENT_TIMESTAMP, -- Registro de creación
    fecha_actualizacion TIMESTAMP     DEFAULT CURRENT_TIMESTAMP  -- Última edición
);

-- Perfil detallado de los vendedores (miembros del equipo)
CREATE TABLE tbl_miembro (
    id_miembro      INTEGER            PRIMARY KEY,          -- Identificador único de miembro (Manual)
    id_usuario      INTEGER            NOT NULL UNIQUE REFERENCES tbl_usuario(id_usuario) ON DELETE RESTRICT,
    porcentaje_comision DECIMAL(5, 2) NOT NULL DEFAULT 10.00, -- % que gana por venta
    universidad     VARCHAR(150),                            -- Institución educativa
    telefono        VARCHAR(15),                             -- Contacto directoizado (10 digitos)
    estado          VARCHAR(20)        DEFAULT 'activo',     -- Estado lógico (activo/inactivo)
    id_estado_miembro SMALLINT         REFERENCES tbl_estado_miembro(id_estado_miembro),
    fecha_contratacion DATE            DEFAULT CURRENT_DATE, -- Fecha de vinculación
    notas           TEXT                                     -- Observaciones internas
);

-- Catálogo de productos disponibles para la venta
CREATE TABLE tbl_producto (
    id_producto     INTEGER            PRIMARY KEY,          -- Identificador único (Manual)
    nombre_producto VARCHAR(150)       NOT NULL,             -- Nombre del producto (ej. Torta)
    descripcion     TEXT,                                    -- Descripción o ingredientes
    precio          DECIMAL(10, 2)     NOT NULL,             -- Precio de venta al público
    stock           INTEGER            NOT NULL DEFAULT 0,   -- Cantidad disponible actual
    imagen_principal VARCHAR(255),                            -- Ruta del archivo de imagen
    estado          VARCHAR(20)        DEFAULT 'activo',     -- Para soft delete de productos
    fecha_creacion  TIMESTAMP          DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP      DEFAULT CURRENT_TIMESTAMP
);

-- Registro de pagos de comisiones realizados a los vendedores
CREATE TABLE tbl_pago_comision (
    id_pago_comision INTEGER           PRIMARY KEY,          -- ID del comprobante de pago
    id_vendedor     INTEGER            NOT NULL REFERENCES tbl_miembro(id_miembro) ON DELETE RESTRICT,
    monto           DECIMAL(10, 2)     NOT NULL,             -- Cantidad pagada al vendedor
    ruta_archivo    VARCHAR(255),                            -- Evidencia del pago (imagen)
    fecha_pago      TIMESTAMP          DEFAULT CURRENT_TIMESTAMP,
    estado          VARCHAR(20)        DEFAULT 'completado', -- Estado del pago
    notas           TEXT                                     -- Notas del administrador
);

-- Encabezado de los pedidos realizados
CREATE TABLE tbl_pedido (
    id_pedido       INTEGER            PRIMARY KEY,          -- Código único de orden (Manual)
    id_vendedor     INTEGER            REFERENCES tbl_miembro(id_miembro) ON DELETE SET NULL,
    telefono_contacto VARCHAR(20)      NOT NULL,             -- Teléfono del cliente final
    direccion_entrega TEXT             NOT NULL,             -- Ubicación de entrega
    fecha_entrega   DATE               NOT NULL,             -- Fecha programada de entrega
    notas           TEXT,                                    -- Requerimientos especiales
    estado          SMALLINT           NOT NULL DEFAULT 0,   -- 0:Pendiente, 2:Completado, 3:Canc
    estado_pago     SMALLINT           DEFAULT 0,            -- 0:Sin, 1:Subido, 2:Ap, 3:Rech
    id_estado_pedido SMALLINT          REFERENCES tbl_estado_pedido(id_estado_pedido),
    id_estado_pago  SMALLINT           REFERENCES tbl_estado_pago(id_estado_pago),
    monto_comision  DECIMAL(10, 2),                          -- Comisión calculada para esta venta
    id_pago_comision INTEGER           REFERENCES tbl_pago_comision(id_pago_comision) ON DELETE SET NULL,
    nota_cancelacion TEXT,                                   -- Por qué se canceló el pedido
    estado_logico   VARCHAR(20)        DEFAULT 'activo',     -- Control de borrado lógico
    fecha_creacion  TIMESTAMP          DEFAULT CURRENT_TIMESTAMP
);

-- Detalle línea por línea de los productos en un pedido
CREATE TABLE tbl_detalle_pedido (
    id_detalle_pedido INTEGER          PRIMARY KEY,          -- ID de la fila de detalle
    id_pedido       INTEGER            NOT NULL REFERENCES tbl_pedido(id_pedido) ON DELETE RESTRICT,
    id_producto     INTEGER            NOT NULL REFERENCES tbl_producto(id_producto) ON DELETE RESTRICT,
    cantidad        DECIMAL(10, 2)     NOT NULL CHECK (cantidad >= 1),
    precio_unitario DECIMAL(10, 2)     NOT NULL,             -- Precio al que se vendió (histórico)
    estado          VARCHAR(20)        DEFAULT 'activo'      -- Estado lógico de la línea
);

-- Evidencias de pago subidas para cada pedido
CREATE TABLE tbl_comprobante_pago (
    id_comprobante_pago INTEGER        PRIMARY KEY,          -- ID del comprobante
    id_pedido       INTEGER            NOT NULL REFERENCES tbl_pedido(id_pedido) ON DELETE RESTRICT,
    ruta_archivo    VARCHAR(255)       NOT NULL,             -- Ruta física de la imagen
    fecha_subida    TIMESTAMP          DEFAULT CURRENT_TIMESTAMP,
    estado          VARCHAR(20)        DEFAULT 'pendiente',  -- Aprobado/Rechazado
    notas           TEXT,                                    -- Notas del revisor
    estado_registro VARCHAR(20)        DEFAULT 'activo'      -- Control de borrado
);

-- Trazabilidad de los estados de un pedido
CREATE TABLE tbl_historial_pedido (
    id_historial    INTEGER            PRIMARY KEY,          -- ID del registro histórico
    id_pedido       INTEGER            NOT NULL REFERENCES tbl_pedido(id_pedido) ON DELETE RESTRICT,
    estado_anterior SMALLINT,                                 -- Estado antes del cambio
    estado_nuevo    SMALLINT           NOT NULL,             -- Estado al que pasó
    usuario_cambio  INTEGER            REFERENCES tbl_usuario(id_usuario) ON DELETE SET NULL,
    fecha_cambio    TIMESTAMP          DEFAULT CURRENT_TIMESTAMP,
    motivo          TEXT,                                    -- Por qué se hizo el cambio
    estado_registro VARCHAR(20)        DEFAULT 'activo'
);

-- 4. OTRAS TABLAS ESTRUCTURALES

-- Relación de muchos a muchos entre Roles y Permisos
CREATE TABLE tbl_rol_permiso (
    id_rol          SMALLINT           REFERENCES tbl_rol(id_role), -- FK a rol
    id_permiso      INTEGER            REFERENCES tbl_permiso(id_permiso), -- FK a permiso
    PRIMARY KEY (id_rol, id_permiso)                               -- Llave compuesta
);

-- Catálogo de razones por las cuales se cancela un pedido
CREATE TABLE tbl_tipo_cancelacion (
    id_tipo_cancelacion INTEGER        PRIMARY KEY,          -- Identificador único
    descripcion     VARCHAR(150)       NOT NULL              -- Descripción (ej. No había stock)
);

-- Kardex / Control de inventario histórico
CREATE TABLE tbl_movimiento_stock (
    id_movimiento   INTEGER            PRIMARY KEY,          -- Registro de movimiento
    id_producto     INTEGER            NOT NULL REFERENCES tbl_producto(id_producto),
    id_tipo_movimiento INTEGER         REFERENCES tbl_tipo_movimiento_stock(id_tipo_movimiento),
    cantidad        INTEGER            NOT NULL,             -- Unidades (+ entrada, - salida)
    fecha           TIMESTAMP          DEFAULT CURRENT_TIMESTAMP,
    usuario_id      INTEGER            REFERENCES tbl_usuario(id_usuario) -- Quien hizo el ajuste
);

-- Trazabilidad de cambios sensibles en tablas (CRUD audit)
CREATE TABLE tbl_auditoria (
    id_auditoria    INTEGER            PRIMARY KEY,          -- Registro de auditoría
    id_usuario      INTEGER            REFERENCES tbl_usuario(id_usuario),
    tabla_afectada  VARCHAR(100)       NOT NULL,             -- Nombre de la tabla
    accion          VARCHAR(50)        NOT NULL,             -- INSERT, UPDATE, DELETE
    valor_anterior  TEXT,                                    -- JSON con valores viejos
    valor_nuevo     TEXT,                                    -- JSON con valores nuevos
    fecha           TIMESTAMP          DEFAULT CURRENT_TIMESTAMP
);

-- Errores y eventos críticos detectados por el software
CREATE TABLE tbl_log_sistema (
    id_log          INTEGER            PRIMARY KEY,          -- Identificador de error
    nivel           VARCHAR(20)        NOT NULL,             -- INFO, WARNING, ERROR, CRITICAL
    mensaje         TEXT               NOT NULL,             -- Descripción técnica del log
    fecha           TIMESTAMP          DEFAULT CURRENT_TIMESTAMP
);

-- Ajustes globales de la aplicación (Nombre tienda, IVA, etc.)
CREATE TABLE tbl_configuracion_general (
    id_configuracion INTEGER           PRIMARY KEY,          -- Identificador de ajuste
    clave           VARCHAR(100)       NOT NULL UNIQUE,      -- Nombre clave (ej. IVA_PERCENT)
    valor           TEXT                                     -- Valor de la configuración
);

-- Gestión de tokens y tiempos de autenticación
CREATE TABLE tbl_sesion_usuario (
    id_sesion       INTEGER            PRIMARY KEY,          -- Identificador de sesión
    id_usuario      INTEGER            NOT NULL REFERENCES tbl_usuario(id_usuario),
    token           VARCHAR(255),                            -- Token JWT o ID de sesión
    fecha_inicio    TIMESTAMP          DEFAULT CURRENT_TIMESTAMP,
    fecha_fin       TIMESTAMP,                               -- Cuándo expiró o cerró
    activa          BOOLEAN            DEFAULT TRUE          -- ¿Sesión válida actualmente?
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

INSERT INTO tbl_usuario (id_usuario, nombre, apellido, email, contrasena, id_rol) VALUES
(1, 'Admin', 'Sistema', 'admin@maishop.com', '$2y$10$cnwQTD8nHIx2Z1qIUrCaouWcDtyyoVkGzE4TNfXlrByIgLUSV5/0S', 1),
(2, 'Juan', 'Pérez', 'vendedor@maishop.com', '$2y$10$mXYW56m2us6UIU/d7l36Supd193Puln2wsHbk8Jzqpbq.xb25L2lK', 2);

INSERT INTO tbl_miembro (id_miembro, id_usuario, porcentaje_comision, universidad, telefono, estado) 
SELECT 1, id_usuario, 15.00, 'Universidad Central', '3001234567', 'activo' FROM tbl_usuario WHERE email = 'vendedor@maishop.com';

INSERT INTO tbl_producto (id_producto, nombre_producto, descripcion, precio, stock) VALUES
(1, 'Torta de Chocolate', 'Deliciosa torta con ganache', 35000.00, 20),
(2, 'Cupcakes de Fresa', 'Decorados con crema natural', 5000.00, 40);

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
DECLARE v_next_id INTEGER;
BEGIN
    IF OLD.estado IS DISTINCT FROM NEW.estado THEN
        SELECT COALESCE(MAX(id_historial), 0) + 1 INTO v_next_id FROM tbl_historial_pedido;
        INSERT INTO tbl_historial_pedido (id_historial, id_pedido, estado_anterior, estado_nuevo, motivo)
        VALUES (v_next_id, NEW.id_pedido, OLD.estado, NEW.estado, 'Cambio automático');
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