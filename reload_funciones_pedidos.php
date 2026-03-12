<?php
require_once __DIR__ . '/Front/conexion.php';

try {
    $sql = file_get_contents(__DIR__ . '/Back/funciones/fun_gestionar_pedidos.sql');
    $pdo->exec($sql);
    echo "¡Funciones de pedidos recargadas exitosamente!";
} catch (Exception $e) {
    echo "Error recargando SQL: " . $e->getMessage();
}
