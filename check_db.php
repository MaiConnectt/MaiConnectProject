<?php
require_once __DIR__ . '/Front/conexion.php';

try {
    $stmt = $pdo->query("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'tbl_historial_pedido'");
    $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
    print_r($cols);
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
