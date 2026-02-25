<?php
/**
 * Endpoint para eliminar un pedido
 */
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../conexion.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$id_pedido = isset($data['id_pedido']) ? (int) $data['id_pedido'] : (isset($data['order_id']) ? (int) $data['order_id'] : 0);

// Validar datos
if ($id_pedido <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID de pedido inválido']);
    exit;
}

try {
    // Iniciar transacción
    $pdo->beginTransaction();

    // 1. Validar si tiene pagos de comisión vinculados
    $stmt_check = $pdo->prepare("SELECT id_pago_comision FROM tbl_pedido WHERE id_pedido = ?");
    $stmt_check->execute([$id_pedido]);
    $pago_comision = $stmt_check->fetchColumn();

    if ($pago_comision) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'No se puede eliminar porque ya tiene un pago de comisión asociado.']);
        exit;
    }

    // 2. Aplicar Eliminación Lógica (Soft Delete)
    // Se cambia DELETE por UPDATE estado_logico = 'inactivo'
    $stmt = $pdo->prepare("UPDATE tbl_pedido SET estado_logico = 'inactivo' WHERE id_pedido = ?");
    $stmt->execute([$id_pedido]);

    if ($stmt->rowCount() > 0) {
        $pdo->commit();
        echo json_encode([
            'success' => true,
            'message' => 'Pedido desactivado correctamente (Eliminación lógica)'
        ]);
    } else {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'No se encontró el pedido']);
    }
} catch (PDOException $e) {
    if ($pdo->inTransaction())
        $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al eliminar: ' . $e->getMessage()]);
}
