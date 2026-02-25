<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../conexion.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'create':
            $nombre = trim($_POST['nombre'] ?? '');
            $precio = floatval($_POST['precio'] ?? 0);
            $stock = intval($_POST['stock'] ?? 0);
            $estado = $_POST['estado'] ?? 'activo';
            $descripcion = trim($_POST['descripcion'] ?? '');

            if (empty($nombre) || $precio <= 0) {
                throw new Exception("Nombre y Precio son obligatorios");
            }

            // Get next ID
            $next_id = $pdo->query("SELECT COALESCE(MAX(id_producto), 0) + 1 FROM tbl_producto")->fetchColumn();

            $stmt = $pdo->prepare("INSERT INTO tbl_producto (id_producto, nombre_producto, descripcion, precio, stock, estado, fecha_creacion, fecha_actualizacion) VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");
            $stmt->execute([$next_id, $nombre, $descripcion, $precio, $stock, $estado]);

            echo json_encode(['success' => true, 'message' => 'Producto creado exitosamente']);
            break;

        case 'edit':
            $id_producto = intval($_POST['id_producto'] ?? 0);
            $nombre = trim($_POST['nombre'] ?? '');
            $precio = floatval($_POST['precio'] ?? 0);
            $stock = intval($_POST['stock'] ?? 0);
            $estado = $_POST['estado'] ?? 'activo';
            $descripcion = trim($_POST['descripcion'] ?? '');

            if (!$id_producto || empty($nombre) || $precio <= 0) {
                throw new Exception("Datos incompletos o inválidos");
            }

            $stmt = $pdo->prepare("UPDATE tbl_producto SET nombre_producto = ?, descripcion = ?, precio = ?, stock = ?, estado = ?, fecha_actualizacion = CURRENT_TIMESTAMP WHERE id_producto = ?");
            $stmt->execute([$nombre, $descripcion, $precio, $stock, $estado, $id_producto]);

            echo json_encode(['success' => true, 'message' => 'Producto actualizado exitosamente']);
            break;

        case 'delete':
            $id_producto = intval($_POST['id_producto'] ?? 0);
            if (!$id_producto)
                throw new Exception("ID inválido");

            // 1. Validar si el producto tiene pedidos asociados
            $check_orders = $pdo->prepare("SELECT COUNT(*) FROM tbl_detalle_pedido WHERE id_producto = ?");
            $check_orders->execute([$id_producto]);
            if ($check_orders->fetchColumn() > 0) {
                throw new Exception("No se puede eliminar porque tiene pedidos asociados. El producto será desactivado.");
            }

            // 2. Aplicar Eliminación Lógica (Soft Delete)
            $stmt = $pdo->prepare("UPDATE tbl_producto SET estado = 'inactivo' WHERE id_producto = ?");
            $stmt->execute([$id_producto]);

            echo json_encode(['success' => true, 'message' => 'Producto desactivado exitosamente (Eliminación lógica)']);
            break;

        default:
            throw new Exception("Acción no válida");
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
