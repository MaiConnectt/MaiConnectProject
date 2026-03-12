<?php
require_once __DIR__ . '/Front/conexion.php';

try {
    $pdo->beginTransaction();

    $sql = "
    CREATE TABLE IF NOT EXISTS tbl_estado_transicion (
        id_transicion SERIAL PRIMARY KEY,
        estado_actual INTEGER NOT NULL,
        estado_siguiente INTEGER NOT NULL,
        
        FOREIGN KEY (estado_actual) REFERENCES tbl_estado_pedido(id_estado_pedido) ON DELETE CASCADE,
        FOREIGN KEY (estado_siguiente) REFERENCES tbl_estado_pedido(id_estado_pedido) ON DELETE CASCADE,
        UNIQUE (estado_actual, estado_siguiente)
    );

    -- Clear existing rules if any to avoid duplicates on re-run
    TRUNCATE TABLE tbl_estado_transicion RESTART IDENTITY CASCADE;

    -- Insert allowed transitions
    INSERT INTO tbl_estado_transicion (estado_actual, estado_siguiente) VALUES 
    (0, 1), -- Pendiente -> En proceso
    (0, 3), -- Pendiente -> Cancelado
    (1, 2), -- En proceso -> Completado
    (1, 3); -- En proceso -> Cancelado
    ";

    $pdo->exec($sql);
    $pdo->commit();
    echo "Tabla 'tbl_estado_transicion' creada y poblada exitosamente.\n";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "ERROR: " . $e->getMessage() . "\n";
}
