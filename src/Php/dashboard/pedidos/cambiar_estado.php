<?php
/**
 * Endpoint para cambiar el estado de un pedido
 * Requiere nota de cancelación cuando estado = 3
 */
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../config/conexion.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$id_pedido = isset($data['id_pedido']) ? (int) $data['id_pedido'] : (isset($data['order_id']) ? (int) $data['order_id'] : 0);
$estado_nuevo = isset($data['estado']) ? (int) $data['estado'] : (isset($data['status']) ? (int) $data['status'] : -1);
$nota_cancelacion = isset($data['nota_cancelacion']) ? trim($data['nota_cancelacion']) : '';

// ── BLINDAJE DE ROL ──────────────────────────────────────────────────────────
$rol_sesion = $_SESSION['id_rol'] ?? $_SESSION['role_id'] ?? null;
if ((int) $rol_sesion === 1 && $estado_nuevo === 2) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Acción no permitida: solo el vendedor puede completar pedidos.'
    ]);
    exit;
}

// El Admin solo puede cambiar entre: Pendiente (0), En Proceso (1), Cancelado (3)
if ((int) $rol_sesion === 1 && !in_array($estado_nuevo, [0, 1, 3])) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Estado no permitido para el administrador.'
    ]);
    exit;
}
// ──────────────────────────────────────────────────────────────────────────────

// Cuando se cancela, la nota es OBLIGATORIA
if ($estado_nuevo === 3 && $nota_cancelacion === '') {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Debes ingresar el motivo de cancelación.'
    ]);
    exit;
}

// Validar datos básicos
if ($id_pedido <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID de pedido inválido']);
    exit;
}

if (!in_array($estado_nuevo, [0, 1, 2, 3])) {
    echo json_encode(['success' => false, 'message' => 'Estado inválido']);
    exit;
}

try {
    // Llamada directa a función SQL para gestionar flujos y estados (modo: cambio_directo)
    $stmt_sql = $pdo->prepare("SELECT fun_gestionar_estado_pedido(?, ?, ?, ?, ?)");
    $stmt_sql->execute([
        $id_pedido,
        $_SESSION['user_id'] ?? 1,
        'cambio_directo',
        $estado_nuevo,
        $nota_cancelacion
    ]);

    $resultado_json = $stmt_sql->fetchColumn();
    $resultado = json_decode($resultado_json, true);

    if (!$resultado || empty($resultado['success'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => $resultado['message'] ?? 'Error al actualizar el estado']);
        exit;
    }

    $status_names = [
        0 => 'Pendiente',
        1 => 'En Proceso',
        2 => 'Completado',
        3 => 'Cancelado'
    ];

    echo json_encode([
        'success' => true,
        'message' => 'Estado actualizado a: ' . $status_names[$estado_nuevo]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al actualizar: ' . $e->getMessage()]);
}
