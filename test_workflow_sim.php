<?php
require_once __DIR__ . '/Front/conexion.php';

try {
    // Escenario 1: Tratar de pasar un pedido inventado o existente que no cumple la regla de transición.
    // Busquemos un pedido "Pendiente" (estado = 0)
    $stmt = $pdo->query("SELECT id_pedido FROM tbl_pedido WHERE estado = 0 LIMIT 1");
    $pedido_pendiente = $stmt->fetchColumn();

    if ($pedido_pendiente) {
        echo "Testeando Pedido Pendiente ID: $pedido_pendiente\n";

        // Tratar de cambiarlo directo a COMPLETADO (2), lo cual DEBE fallar según (0->1, 0->3)
        $stmt_test1 = $pdo->prepare("SELECT fun_gestionar_estado_pedido(?, ?, ?, ?, ?)");
        $stmt_test1->execute([$pedido_pendiente, 1, 'cambio_directo', 2, 'Prueba de salto']);
        $res1 = $stmt_test1->fetchColumn();
        echo "Resultado de salto inválido (0 -> 2): " . $res1 . "\n";

        // Tratar de cambiarlo a EN PROCESO (1), lo cual DEBE funcionar (0->1)
        $stmt_test2 = $pdo->prepare("SELECT fun_gestionar_estado_pedido(?, ?, ?, ?, ?)");
        $stmt_test2->execute([$pedido_pendiente, 1, 'cambio_directo', 1, 'Prueba de salto']);
        $res2 = $stmt_test2->fetchColumn();
        echo "Resultado de transición válida (0 -> 1): " . $res2 . "\n";

    } else {
        echo "No hay pedidos pendientes para testear esto.\n";
    }

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
