<?php
require_once __DIR__ . '/conexion.php';
try {
    $stmt = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'tbl_miembro'");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Columns in tbl_miembro:\n";
    print_r($columns);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>