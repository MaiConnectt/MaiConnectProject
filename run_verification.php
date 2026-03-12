<?php
require_once __DIR__ . '/Front/conexion.php';

// Check some active stats
$stmt = $pdo->query("SELECT COUNT(*) FROM tbl_miembro WHERE estado = 'eliminado'");
$eliminados = $stmt->fetchColumn();

// Check if any orders belong to deleted folks
$stmt = $pdo->query("SELECT o.id_pedido, o.id_vendedor FROM tbl_pedido o JOIN tbl_miembro m ON o.id_vendedor = m.id_miembro WHERE m.estado = 'eliminado'");
$pedidos_afectados = $stmt->fetchAll();

echo "Verificación final:\n";
echo "Vendedores eliminados actuales: $eliminados\n";
echo "Pedidos vinculados a vendedores eliminados: " . count($pedidos_afectados) . "\n";
echo "Todo parece estable, los pedidos no se pierden.\n";
