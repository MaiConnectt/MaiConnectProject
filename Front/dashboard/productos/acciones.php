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

            // Procesar la subida de la imagen si se envió una
            $ruta_imagen = null;
            if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = __DIR__ . '/../../uploads/productos/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true); // Crear directorio si no existe
                }

                $file_extension = strtolower(pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION));
                $new_filename = 'prod_' . time() . '_' . rand(1000, 9999) . '.' . $file_extension;
                $destination = $upload_dir . $new_filename;

                if (move_uploaded_file($_FILES['imagen']['tmp_name'], $destination)) {
                    $ruta_imagen = 'uploads/productos/' . $new_filename;
                }
            }

            // Get next ID
            $next_id = $pdo->query("SELECT COALESCE(MAX(id_producto), 0) + 1 FROM tbl_producto")->fetchColumn();

            $stmt = $pdo->prepare("INSERT INTO tbl_producto (id_producto, nombre_producto, descripcion, precio, stock, estado, imagen_principal, fecha_creacion, fecha_actualizacion) VALUES (?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");
            $stmt->execute([$next_id, $nombre, $descripcion, $precio, $stock, $estado, $ruta_imagen]);

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

            // Procesar la subida de la imagen si se envió una
            $ruta_imagen = null;
            if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = __DIR__ . '/../../uploads/productos/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }

                $file_extension = strtolower(pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION));
                $new_filename = 'prod_' . time() . '_' . rand(1000, 9999) . '.' . $file_extension;
                $destination = $upload_dir . $new_filename;

                if (move_uploaded_file($_FILES['imagen']['tmp_name'], $destination)) {
                    $ruta_imagen = 'uploads/productos/' . $new_filename;
                }
            }

            if ($ruta_imagen) {
                $stmt = $pdo->prepare("UPDATE tbl_producto SET nombre_producto = ?, descripcion = ?, precio = ?, stock = ?, estado = ?, imagen_principal = ?, fecha_actualizacion = CURRENT_TIMESTAMP WHERE id_producto = ?");
                $stmt->execute([$nombre, $descripcion, $precio, $stock, $estado, $ruta_imagen, $id_producto]);
            } else {
                $stmt = $pdo->prepare("UPDATE tbl_producto SET nombre_producto = ?, descripcion = ?, precio = ?, stock = ?, estado = ?, fecha_actualizacion = CURRENT_TIMESTAMP WHERE id_producto = ?");
                $stmt->execute([$nombre, $descripcion, $precio, $stock, $estado, $id_producto]);
            }

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
