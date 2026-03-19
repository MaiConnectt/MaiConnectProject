<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../config/conexion.php';
require_once __DIR__ . '/../../config/helpers.php';

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
            $estado = $_POST['estado'] ?? 'activo';
            $descripcion = trim($_POST['descripcion'] ?? '');

            if (empty($nombre) || $precio <= 0) {
                throw new Exception("Nombre y Precio son obligatorios");
            }

            // Procesar la subida de la imagen si se envió una
            $ruta_imagen = null;
            if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
                validarImagen($_FILES['imagen']);
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

            $stmt = $pdo->prepare("INSERT INTO tbl_producto (id_producto, nombre_producto, descripcion, precio, estado, imagen_principal, fecha_creacion, fecha_actualizacion) VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");
            $stmt->execute([$next_id, $nombre, $descripcion, $precio, $estado, $ruta_imagen]);

            echo json_encode(['success' => true, 'message' => 'Producto creado exitosamente']);
            break;

        case 'edit':
            $id_producto = intval($_POST['id_producto'] ?? 0);
            $nombre = trim($_POST['nombre'] ?? '');
            $precio = floatval($_POST['precio'] ?? 0);
            $estado = $_POST['estado'] ?? 'activo';
            $descripcion = trim($_POST['descripcion'] ?? '');

            if (!$id_producto || empty($nombre) || $precio <= 0) {
                throw new Exception("Datos incompletos o inválidos");
            }

            // Procesar la subida de la imagen si se envió una
            $ruta_imagen = null;
            if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
                validarImagen($_FILES['imagen']);
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
                $stmt = $pdo->prepare("UPDATE tbl_producto SET nombre_producto = ?, descripcion = ?, precio = ?, estado = ?, imagen_principal = ?, fecha_actualizacion = CURRENT_TIMESTAMP WHERE id_producto = ?");
                $stmt->execute([$nombre, $descripcion, $precio, $estado, $ruta_imagen, $id_producto]);
            } else {
                $stmt = $pdo->prepare("UPDATE tbl_producto SET nombre_producto = ?, descripcion = ?, precio = ?, estado = ?, fecha_actualizacion = CURRENT_TIMESTAMP WHERE id_producto = ?");
                $stmt->execute([$nombre, $descripcion, $precio, $estado, $id_producto]);
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
                throw new Exception("No se puede eliminar completamente este producto porque ya está vinculado a pedidos existentes.");
            }

            // 2. Obtener la ruta de la imagen antes de eliminar
            $stmt_img = $pdo->prepare("SELECT imagen_principal FROM tbl_producto WHERE id_producto = ?");
            $stmt_img->execute([$id_producto]);
            $prod_info = $stmt_img->fetch();

            // 3. Aplicar Eliminación Física (Hard Delete)
            $stmt = $pdo->prepare("DELETE FROM tbl_producto WHERE id_producto = ?");
            if ($stmt->execute([$id_producto])) {
                // Si la eliminación en BD fue exitosa, eliminamos la imagen del servidor para liberar espacio
                if ($prod_info && !empty($prod_info['imagen_principal'])) {
                    $img_path = __DIR__ . '/../../' . $prod_info['imagen_principal'];
                    if (file_exists($img_path)) {
                        unlink($img_path);
                    }
                }
            }

            echo json_encode(['success' => true, 'message' => 'Producto eliminado permanentemente de la base de datos']);
            break;

        default:
            throw new Exception("Acción no válida");
    }
} catch (PDOException $e) {
    if ($e->getCode() == '23505') {
        echo json_encode(['success' => false, 'message' => 'Ya existe un producto con ese nombre. Por favor usa un nombre diferente.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error de base de datos: ' . $e->getMessage()]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
