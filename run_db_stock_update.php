<?php
require_once __DIR__ . '/Front/conexion.php';

try {
    $pdo->beginTransaction();

    // 1. Eliminar referencias a stock en productos
    $pdo->exec("ALTER TABLE tbl_producto DROP COLUMN IF EXISTS stock CASCADE;");

    // 2. Agregar restricción UNIQUE al nombre del producto
    // Primero, si ya hay repetidos, esto fallaría. Asumimos que la BD está limpia de duplicados exactos,
    // o aplicamos el constraint. Si falla, el catch nos dirá.
    $pdo->exec("ALTER TABLE tbl_producto ADD CONSTRAINT unique_nombre_producto UNIQUE (nombre_producto);");

    // 3. Limpiar tablas obsoletas de inventario si existen (Opcional pero recomendado por el nuevo modelo)
    $pdo->exec("DROP TABLE IF EXISTS tbl_movimiento_stock CASCADE;");
    $pdo->exec("DROP TABLE IF EXISTS tbl_tipo_movimiento_stock CASCADE;");

    $pdo->commit();
    echo "Base de datos actualizada correctamente: Stock eliminado y restricción Unique agregada.\n";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "ERROR: " . $e->getMessage() . "\n";
}
