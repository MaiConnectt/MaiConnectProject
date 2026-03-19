<?php
require_once __DIR__ . '/seller_auth.php';
require_once __DIR__ . '/../config/conexion.php';

// Auth check already handled by seller_auth.php which sets session

$user_id = $_SESSION['user_id'];
$member_id = $_SESSION['member_id'];
$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'crear_pedido':
            require_once __DIR__ . '/../config/pedidos_helper.php';

            $resultado = crearPedido([
                'telefono'  => trim($_POST['client_phone'] ?? ''),
                'direccion' => trim($_POST['client_address'] ?? ''),
                'fecha'     => $_POST['delivery_date'] ?? null,
                'notas'     => trim($_POST['notes'] ?? ''),
                'productos' => $_POST['products'] ?? [],
                'user_id'   => $user_id,
                'commission_percentage' => $_SESSION['commission_percentage'] ?? 10,
            ], $pdo, $member_id);

            if ($resultado['success']) {
                $_SESSION['success'] = "¡Pedido #" . str_pad($resultado['id_pedido'], 4, '0', STR_PAD_LEFT) . " creado exitosamente!";
                header("Location: mis_pedidos.php");
                exit;
            } else {
                throw new Exception($resultado['message']);
            }

        case 'subir_pago':
            $id_pedido = (int) ($_POST['id_pedido'] ?? 0);
            if (!$id_pedido)
                throw new Exception("ID de pedido no proporcionado");

            $stmt = $pdo->prepare("SELECT id_vendedor, estado_pago FROM tbl_pedido WHERE id_pedido = ?");
            $stmt->execute([$id_pedido]);
            $order = $stmt->fetch();

            if (!$order)
                throw new Exception("Pedido no encontrado");
            if ($order['id_vendedor'] != $member_id)
                throw new Exception("No tienes permiso sobre este pedido");
            if ($order['estado_pago'] != 0 && $order['estado_pago'] != 3)
                throw new Exception("El pago ya está en proceso o aprobado");

            if (!isset($_FILES['comprobante']) || $_FILES['comprobante']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception("Error al recibir el archivo del comprobante");
            }

            $upload_dir = __DIR__ . '/../uploads/orders/';
            if (!is_dir($upload_dir))
                mkdir($upload_dir, 0777, true);

            $ext = pathinfo($_FILES['comprobante']['name'], PATHINFO_EXTENSION);
            $filename = 'proof_' . $id_pedido . '_' . time() . '.' . $ext;

            if (move_uploaded_file($_FILES['comprobante']['tmp_name'], $upload_dir . $filename)) {
                $ruta = 'uploads/orders/' . $filename;
                $pdo->beginTransaction();
                $pdo->prepare("UPDATE tbl_comprobante_pago SET estado_registro = 'inactivo' WHERE id_pedido = ?")->execute([$id_pedido]);
                $next_comprobante_id = $pdo->query("SELECT COALESCE(MAX(id_comprobante_pago), 0) + 1 as next_id FROM tbl_comprobante_pago")->fetch()['next_id'];
                $pdo->prepare("INSERT INTO tbl_comprobante_pago (id_comprobante_pago, id_pedido, ruta_archivo, estado, notas) VALUES (?, ?, ?, 'pendiente', NULL)")->execute([$next_comprobante_id, $id_pedido, $ruta]);
                $pdo->prepare("UPDATE tbl_pedido SET estado_pago = 1 WHERE id_pedido = ?")->execute([$id_pedido]);
                $pdo->commit();
                $_SESSION['success'] = "Comprobante de pago subido correctamente.";
            } else {
                throw new Exception("No se pudo guardar el archivo");
            }
            header("Location: mis_pedidos.php");
            exit;

        case 'completar_pedido':
            $id_pedido = (int) ($_POST['id_pedido'] ?? 0);
            if (!$id_pedido)
                throw new Exception("ID de pedido no proporcionado");

            $stmt = $pdo->prepare("SELECT id_vendedor, estado, estado_pago FROM tbl_pedido WHERE id_pedido = ?");
            $stmt->execute([$id_pedido]);
            $order = $stmt->fetch();

            if (!$order)
                throw new Exception("Pedido no encontrado");
            if ($order['id_vendedor'] != $member_id)
                throw new Exception("No tienes permiso sobre este pedido");
            if ($order['estado'] != 1)
                throw new Exception("Solo se pueden completar pedidos en producción");
            if ($order['estado_pago'] != 2)
                throw new Exception("El pago debe estar aprobado antes de completar");

            $pdo->beginTransaction();
            $pdo->prepare("UPDATE tbl_pedido SET estado = 2 WHERE id_pedido = ?")->execute([$id_pedido]);
            $pdo->prepare("INSERT INTO tbl_historial_pedido (id_pedido, usuario_cambio, estado_anterior, estado_nuevo, motivo) VALUES (?, ?, 1, 2, 'Marcado como completado por el vendedor')")
                ->execute([$id_pedido, $user_id]);
            $pdo->commit();

            $_SESSION['success'] = "¡Pedido #" . str_pad($id_pedido, 4, '0', STR_PAD_LEFT) . " marcado como completado!";
            header("Location: mis_pedidos.php");
            exit;

        default:
            throw new Exception("Acción no reconocida");
    }
} catch (Exception $e) {
    if ($pdo->inTransaction())
        $pdo->rollBack();
    $_SESSION['error'] = $e->getMessage();
    header("Location: " . ($_POST['action'] == 'crear_pedido' ? 'nuevo_pedido.php' : 'mis_pedidos.php'));
    exit;
}
