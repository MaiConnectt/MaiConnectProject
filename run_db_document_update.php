<?php
require_once __DIR__ . '/Front/conexion.php';

try {
    $pdo->beginTransaction();

    $pdo->exec("ALTER TABLE tbl_miembro ADD COLUMN IF NOT EXISTS tipo_documento VARCHAR(5)");
    $pdo->exec("ALTER TABLE tbl_miembro ADD COLUMN IF NOT EXISTS numero_documento VARCHAR(20)");

    // Try to add the unique constraint (ignore if already exists)
    $stmt = $pdo->prepare("
        SELECT constraint_name 
        FROM information_schema.table_constraints 
        WHERE table_name = 'tbl_miembro' AND constraint_name = 'unique_documento'
    ");
    $stmt->execute();
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE tbl_miembro ADD CONSTRAINT unique_documento UNIQUE (numero_documento)");
    }

    $pdo->commit();
    echo "¡Base de datos actualizada con éxito!" . PHP_EOL;

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "Error: " . $e->getMessage() . PHP_EOL;
}
?>