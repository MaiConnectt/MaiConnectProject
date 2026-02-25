<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../conexion.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// Support both JSON (from JS) and form-data
$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    $data = $_POST;
}

$action = $data['action'] ?? '';
$id_pedido = isset($data['order_id']) ? (int) $data['order_id'] : (isset($data['id_pedido']) ? (int) $data['id_pedido'] : 0);

try {
    switch ($action) {
        case 'delete':
            if (!$id_pedido) {
                throw new Exception("ID de pedido inválido");
            }

            $pdo->beginTransaction();

            // 1. Verificar existencia del pedido
            $stmt = $pdo->prepare("SELECT estado FROM tbl_pedido WHERE id_pedido = ?");
            $stmt->execute([$id_pedido]);
            $pedido = $stmt->fetch();

            if (!$pedido) {
                throw new Exception("Pedido no encontrado");
            }

            // 2. Aplicar Soft Delete (Eliminación lógica)
            $stmt = $pdo->prepare("UPDATE tbl_pedido SET estado_logico = 'inactivo' WHERE id_pedido = ?");
            $stmt->execute([$id_pedido]);

            // 3. Registrar en historial
            $next_historial_id = $pdo->query("SELECT COALESCE(MAX(id_historial), 0) + 1 as next_id FROM tbl_historial_pedido")->fetch()['next_id'];
            $log = $pdo->prepare("INSERT INTO tbl_historial_pedido (id_historial, id_pedido, usuario_cambio, estado_anterior, estado_nuevo, motivo) VALUES (?, ?, ?, ?, ?, ?)");
            $log->execute([
                $next_historial_id,
                $id_pedido,
                $_SESSION['user_id'],
                $pedido['estado'],
                $pedido['estado'],
                'Pedido eliminado por el administrador (Soft Delete)'
            ]);

            $pdo->commit();
            $response = ['success' => true, 'message' => 'Pedido eliminado correctamente'];
            break;

        case 'aprobar_pago':
        case 'rechazar_pago':
        case 'mandar_produccion':
        case 'cancelar_pedido':
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("SELECT estado, estado_pago FROM tbl_pedido WHERE id_pedido = ?");
            $stmt->execute([$id_pedido]);
            $order = $stmt->fetch();

            if (!$order) {
                throw new Exception("Pedido no encontrado");
            }

            $estado_anterior = $order['estado'];
            $pago_anterior = $order['estado_pago'];
            $notas = $data['notas'] ?? '';
            $estado_nuevo = $estado_anterior;
            $pago_nuevo = $pago_anterior;

            if ($action === 'aprobar_pago') {
                if ($pago_anterior != 1)
                    throw new Exception("No hay un comprobante pendiente de validar");
                $pdo->prepare("UPDATE tbl_comprobante_pago SET estado = 'aprobado' WHERE id_pedido = ? AND estado = 'pendiente'")->execute([$id_pedido]);
                $pdo->prepare("UPDATE tbl_pedido SET estado_pago = 2 WHERE id_pedido = ?")->execute([$id_pedido]);
                $pago_nuevo = 2;
                $_SESSION['success'] = "Pago aprobado exitosamente.";
            } elseif ($action === 'rechazar_pago') {
                if (empty($notas))
                    throw new Exception("Debes indicar el motivo del rechazo");
                $pdo->prepare("UPDATE tbl_comprobante_pago SET estado = 'rechazado', notas = ? WHERE id_pedido = ? AND estado = 'pendiente'")->execute([$notas, $id_pedido]);
                $pdo->prepare("UPDATE tbl_pedido SET estado_pago = 3 WHERE id_pedido = ?")->execute([$id_pedido]);
                $pago_nuevo = 3;
                $_SESSION['success'] = "Pago rechazado.";
            } elseif ($action === 'mandar_produccion') {
                if ($estado_anterior != 0)
                    throw new Exception("El pedido ya no está pendiente");
                if ($pago_anterior != 2)
                    throw new Exception("No se puede mandar a producción sin pago aprobado");
                $estado_nuevo = 1;
                $_SESSION['success'] = "Pedido enviado a producción.";
            } elseif ($action === 'cancelar_pedido') {
                if (empty($notas))
                    throw new Exception("Debes indicar el motivo de la cancelación");
                if ($estado_anterior == 1 && $pago_anterior == 2)
                    throw new Exception("No se puede cancelar en producción con pago aprobado.");
                $estado_nuevo = 3;
                $_SESSION['success'] = "Pedido cancelado.";
            }

            // Update Pedido
            if ($action === 'cancelar_pedido') {
                $pdo->prepare("UPDATE tbl_pedido SET estado = ?, estado_pago = ?, nota_cancelacion = ? WHERE id_pedido = ?")->execute([$estado_nuevo, $pago_nuevo, $notas, $id_pedido]);
            } else {
                $pdo->prepare("UPDATE tbl_pedido SET estado = ?, estado_pago = ? WHERE id_pedido = ?")->execute([$estado_nuevo, $pago_nuevo, $id_pedido]);
            }

            // History
            $next_historial_id = $pdo->query("SELECT COALESCE(MAX(id_historial), 0) + 1 as next_id FROM tbl_historial_pedido")->fetch()['next_id'];
            $pdo->prepare("INSERT INTO tbl_historial_pedido (id_historial, id_pedido, usuario_cambio, estado_anterior, estado_nuevo, motivo) VALUES (?, ?, ?, ?, ?, ?)")
                ->execute([$next_historial_id, $id_pedido, $_SESSION['user_id'], $estado_anterior, $estado_nuevo, $notas]);

            $pdo->commit();
            $response = ['success' => true, 'message' => $_SESSION['success'] ?? 'Acción realizada'];
            break;

        default:
            throw new Exception("Acción no válida: " . $action);
    }

    // Response handling
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        echo json_encode($response);
    } else {
        // Form post - redirect
        $redirect = "ver.php?id=$id_pedido";
        header("Location: $redirect");
    }

} catch (Exception $e) {
    if ($pdo && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
