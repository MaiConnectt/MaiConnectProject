<?php
require_once __DIR__ . '/conexion.php';
try {
    $tables = ['tbl_pedido', 'tbl_usuario'];
    foreach ($tables as $table) {
        $stmt = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = '$table'");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo "Columns in $table:\n";
        print_r($columns);
        echo "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>