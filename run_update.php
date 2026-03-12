<?php
require_once __DIR__ . '/Front/conexion.php';

try {
    $pdo->beginTransaction();

    // 1. Insertar el estado 0 solo si no existe
    $pdo->exec("
        INSERT INTO tbl_estado_miembro (id_estado_miembro, nombre_estado)
        SELECT 0, 'Eliminado'
        WHERE NOT EXISTS (SELECT 1 FROM tbl_estado_miembro WHERE id_estado_miembro = 0)
    ");
    echo "Estado 'Eliminado' garantizado (ID 0).\n";

    // 2. Agregar columna fecha_eliminacion si no existe
    $stmt = $pdo->query("
        SELECT column_name 
        FROM information_schema.columns 
        WHERE table_name='tbl_miembro' AND column_name='fecha_eliminacion'
    ");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE tbl_miembro ADD COLUMN fecha_eliminacion DATE NULL");
        echo "Columna 'fecha_eliminacion' agregada.\n";
    } else {
        echo "Columna 'fecha_eliminacion' ya existía.\n";
    }

    $pdo->commit();
    echo "Migración de Base de Datos COMPLETADA con éxito.\n";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "ERROR durante la migración: " . $e->getMessage() . "\n";
}
