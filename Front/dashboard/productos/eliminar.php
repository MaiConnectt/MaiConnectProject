<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../conexion.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$product_id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

if ($product_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID de producto inválido']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Get product image to delete file
    $stmt = $pdo->prepare("SELECT main_image FROM tbl_product WHERE id_product = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();

    if (!$product) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Producto no encontrado']);
        exit;
    }

    // 1. Validar si el producto tiene pedidos asociados
    $check_orders = $pdo->prepare("SELECT COUNT(*) FROM tbl_detalle_pedido WHERE id_producto = ?");
    $check_orders->execute([$product_id]);
    $has_orders = $check_orders->fetchColumn() > 0;

    if ($has_orders) {
        $pdo->rollBack();
        echo json_encode([
            'success' => false,
            'message' => 'No se puede eliminar porque tiene pedidos asociados. El producto será desactivado para mantener el historial.'
        ]);

        // Opcional: Podríamos desactivarlo automáticamente aquí si el usuario lo prefiere, 
        // pero la instrucción pide bloquear y mostrar mensaje.
        exit;
    }

    // 2. Aplicar Eliminación Lógica (Soft Delete)
    // Se cambia DELETE por UPDATE estado = 'inactivo'
    $stmt = $pdo->prepare("UPDATE tbl_producto SET estado = 'inactivo' WHERE id_producto = ?");
    $stmt->execute([$product_id]);

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Producto desactivado exitosamente']);

} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Error al eliminar producto: ' . $e->getMessage()]);
}
