<?php
require_once __DIR__ . '/Front/conexion.php';

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("UPDATE tbl_miembro SET porcentaje_comision = 10.00 WHERE porcentaje_comision = 15.00;");
    $stmt->execute();

    $pdo->commit();
    echo "¡Comisión actualizada con éxito!" . PHP_EOL;

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "Error: " . $e->getMessage() . PHP_EOL;
}
?>