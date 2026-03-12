<?php
require_once __DIR__ . '/Front/conexion.php';

$sql = file_get_contents(__DIR__ . '/Back/funciones/fun_desactivar_vendedor.sql');
try {
    $pdo->exec($sql);
    echo "SQL functions reloaded successfully.\n";
} catch (PDOException $e) {
    echo "ERROR reloading SQL functions: " . $e->getMessage() . "\n";
}
