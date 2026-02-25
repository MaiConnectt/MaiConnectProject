<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../conexion.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$seller_id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

if ($seller_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID de vendedor inválido']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Verificar que el vendedor existe
    $check_stmt = $pdo->prepare("SELECT id_miembro FROM tbl_miembro WHERE id_miembro = ?");
    $check_stmt->execute([$seller_id]);

    if (!$check_stmt->fetch()) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Vendedor no encontrado']);
        exit;
    }

    // 1. Validar si el vendedor tiene pedidos asociados
    $check_orders = $pdo->prepare("SELECT COUNT(*) FROM tbl_pedido WHERE id_vendedor = ?");
    $check_orders->execute([$seller_id]);
    if ($check_orders->fetchColumn() > 0) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'No se puede eliminar porque tiene pedidos asociados.']);
        exit;
    }

    // 2. Validar si tiene pagos de comisión registrados
    $check_payments = $pdo->prepare("SELECT COUNT(*) FROM tbl_pago_comision WHERE id_vendedor = ?");
    $check_payments->execute([$seller_id]);
    if ($check_payments->fetchColumn() > 0) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'No se puede eliminar porque tiene pagos de comisión registrados.']);
        exit;
    }

    // 3. En lugar de eliminar, marcar como inactivo
    $stmt = $pdo->prepare("UPDATE tbl_miembro SET estado = 'inactivo' WHERE id_miembro = ?");
    $stmt->execute([$seller_id]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Vendedor desactivado exitosamente'
    ]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    echo json_encode([
        'success' => false,
        'message' => 'Error al eliminar vendedor: ' . $e->getMessage()
    ]);
}
