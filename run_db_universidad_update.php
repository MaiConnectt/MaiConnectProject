<?php
require_once __DIR__ . '/Front/conexion.php';

try {
    $pdo->beginTransaction();

    // 1. Create table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS tbl_universidad (
            id_universidad SERIAL PRIMARY KEY,
            nombre_universidad VARCHAR(150) UNIQUE NOT NULL
        )
    ");

    // 2. Migrate existing universities
    $pdo->exec("
        INSERT INTO tbl_universidad (nombre_universidad)
        SELECT DISTINCT universidad
        FROM tbl_miembro
        WHERE universidad IS NOT NULL AND TRIM(universidad) != ''
        ON CONFLICT (nombre_universidad) DO NOTHING
    ");

    // 3. Add column to tbl_miembro
    $pdo->exec("ALTER TABLE tbl_miembro ADD COLUMN IF NOT EXISTS id_universidad INTEGER");

    // Add Foreign Key
    $stmt = $pdo->prepare("
        SELECT constraint_name 
        FROM information_schema.table_constraints 
        WHERE table_name = 'tbl_miembro' AND constraint_name = 'fk_miembro_universidad'
    ");
    $stmt->execute();
    if (!$stmt->fetch()) {
        $pdo->exec("
            ALTER TABLE tbl_miembro
            ADD CONSTRAINT fk_miembro_universidad
            FOREIGN KEY (id_universidad)
            REFERENCES tbl_universidad(id_universidad)
        ");
    }

    // 4. Update data
    $pdo->exec("
        UPDATE tbl_miembro m
        SET id_universidad = u.id_universidad
        FROM tbl_universidad u
        WHERE m.universidad = u.nombre_universidad
    ");

    // 5. Create View
    $pdo->exec("
        CREATE OR REPLACE VIEW vw_vendedores_por_universidad AS
        SELECT
            u.nombre_universidad,
            COUNT(m.id_miembro) AS total_vendedores
        FROM tbl_universidad u
        LEFT JOIN tbl_miembro m ON m.id_universidad = u.id_universidad
        WHERE m.estado != 'eliminado'
        GROUP BY u.nombre_universidad
        ORDER BY total_vendedores DESC
    ");

    $pdo->commit();
    echo "¡Base de datos y vista actualizadas con éxito!\n";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "Error: " . $e->getMessage() . "\n";
}
?>