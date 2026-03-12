<?php
require_once __DIR__ . '/Front/conexion.php';

try {
    $pdo->beginTransaction();

    // Crear la tabla tbl_historial_pedido si no existe
    $sql = "
    CREATE TABLE IF NOT EXISTS tbl_historial_pedido (
        id_historial SERIAL PRIMARY KEY,
        id_pedido INTEGER NOT NULL,
        usuario_cambio INTEGER NULL,
        estado_anterior INTEGER NULL,
        estado_nuevo INTEGER NOT NULL,
        motivo TEXT,
        fecha_historial TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        
        FOREIGN KEY (id_pedido) REFERENCES tbl_pedido(id_pedido) ON DELETE CASCADE,
        FOREIGN KEY (usuario_cambio) REFERENCES tbl_usuario(id_usuario) ON DELETE SET NULL
    );
    ";

    $pdo->exec($sql);

    $pdo->commit();
    echo "Tabla 'tbl_historial_pedido' creada con éxito.\n";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "ERROR durante la migración: " . $e->getMessage() . "\n";
}
