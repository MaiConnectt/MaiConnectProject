<?php
require_once __DIR__ . '/Front/conexion.php';

try {
    $stmt = $pdo->query("SELECT * FROM tbl_historial_pedido LIMIT 10");
    $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    print_r($filas);
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
