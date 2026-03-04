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

            // Llamada directa a función SQL para Soft Delete transaccional
            $stmt = $pdo->prepare("SELECT fun_eliminar_pedido(?, ?)");
            $stmt->execute([$id_pedido, $_SESSION['user_id']]);

            $resultado_json = $stmt->fetchColumn();
            $resultado = json_decode($resultado_json, true);

            if (!$resultado || empty($resultado['success'])) {
                throw new Exception($resultado['message'] ?? 'Error al eliminar el pedido');
            }

            $response = ['success' => true, 'message' => $resultado['message']];
            break;

        case 'aprobar_pago':
        case 'rechazar_pago':
        case 'mandar_produccion':
        case 'cancelar_pedido':
            $notas = $data['notas'] ?? '';

            // Llamada directa a función SQL para gestionar flujos y estados
            $stmt = $pdo->prepare("SELECT fun_gestionar_estado_pedido(?, ?, ?, ?, ?)");
            $stmt->execute([
                $id_pedido,
                $_SESSION['user_id'],
                $action,
                0, // Null o cero, porque en estos workflows se autocalcula en el db
                $notas
            ]);

            $resultado_json = $stmt->fetchColumn();
            $resultado = json_decode($resultado_json, true);

            if (!$resultado || empty($resultado['success'])) {
                throw new Exception($resultado['message'] ?? 'Error al procesar la acción del pedido');
            }

            $_SESSION['success'] = $resultado['message'];
            $response = ['success' => true, 'message' => $resultado['message']];
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
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
