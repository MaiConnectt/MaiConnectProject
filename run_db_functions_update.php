<?php
require_once __DIR__ . '/Front/conexion.php';

try {
    $sql = file_get_contents(__DIR__ . '/Back/funciones/fun_vendedores.sql');
    $pdo->exec($sql);
    echo "Funciones SQL actualizadas con éxito." . PHP_EOL;
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . PHP_EOL;
}
?>