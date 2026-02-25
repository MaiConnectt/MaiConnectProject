<?php
session_start();
require_once __DIR__ . '/../../conexion.php';

// Auth check
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header('Location: ../../login/login.php');
    exit;
}

$id_member = $_GET['id_member'] ?? null;
$id_pedido_single = $_GET['id_pedido'] ?? null;

if (!$id_member && !$id_pedido_single) {
    header('Location: index.php');
    exit;
}

// Case 1: Single Order Payment
if ($id_pedido_single) {
    // Determine member from order
    $stmt_mem = $pdo->prepare("
        SELECT m.id_miembro 
        FROM tbl_pedido p 
        JOIN tbl_miembro m ON p.id_vendedor = m.id_miembro 
        WHERE p.id_pedido = ?
    ");
    $stmt_mem->execute([$id_pedido_single]);
    $id_member = $stmt_mem->fetchColumn();
    if (!$id_member) die("Pedido o vendedor no encontrado.");
}

// Fetch Member Info
// FIX: Alias columns to match English keys expected by Frontend
$stmt = $pdo->prepare("
    SELECT 
        m.*, 
        u.nombre AS first_name, 
        u.apellido AS last_name, 
        u.email,
        m.porcentaje_comision AS commission_percentage
    FROM tbl_miembro m 
    JOIN tbl_usuario u ON m.id_usuario = u.id_usuario 
    WHERE m.id_miembro = ?
");
$stmt->execute([$id_member]);
$member = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$member) {
    die("Vendedor no encontrado.");
}

// Fetch Orders to Pay
// If Single: Fetch just that one
// If Member: Fetch all pending (Legacy/Bulk mode)
$orders = [];
// Alias columns for frontend compatibility
// p.fecha_creacion -> created_at
// p.id_pedido -> id_order
// p.monto_comision -> commission_amount
// ot.total -> order_total

if ($id_pedido_single) {
    $stmt_orders = $pdo->prepare("
        SELECT 
            o.id_pedido AS id_order, 
            o.fecha_creacion AS created_at, 
            o.estado,
            ot.total AS order_total,
            o.monto_comision AS commission_amount
        FROM tbl_pedido o
        LEFT JOIN vw_totales_pedido ot ON o.id_pedido = ot.id_pedido
        WHERE o.id_pedido = ? 
        AND o.estado = 2 
        AND o.monto_comision > 0
        AND (o.id_pago_comision IS NULL OR o.id_pago_comision = 0)
    ");
    $stmt_orders->execute([$id_pedido_single]);
    $orders = $stmt_orders->fetchAll(PDO::FETCH_ASSOC);
} else {
    $stmt_orders = $pdo->prepare("
        SELECT 
            o.id_pedido AS id_order, 
            o.fecha_creacion AS created_at, 
            o.estado,
            ot.total AS order_total,
            o.monto_comision AS commission_amount
        FROM tbl_pedido o
        JOIN tbl_miembro m ON o.id_vendedor = m.id_miembro
        LEFT JOIN vw_totales_pedido ot ON o.id_pedido = ot.id_pedido
        WHERE o.id_vendedor = ? 
        AND o.estado = 2 
        AND o.monto_comision > 0
        AND (o.id_pago_comision IS NULL OR o.id_pago_comision = 0)
        ORDER BY o.fecha_creacion ASC
    ");
    $stmt_orders->execute([$id_member]);
    $orders = $stmt_orders->fetchAll(PDO::FETCH_ASSOC);
}

$total_commission = 0;
foreach ($orders as $o) {
    $total_commission += ($o['commission_amount'] ?? 0);
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (empty($orders)) {
             throw new Exception("No hay pedidos para pagar.");
        }

        $pdo->beginTransaction();

        $notes = $_POST['notes'] ?? '';
        $payment_amount = $total_commission;
        $proof_path = null;

        // 1. Upload Proof
        if (isset($_FILES['payment_proof']) && $_FILES['payment_proof']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/../../uploads/comisiones/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $file_ext = pathinfo($_FILES['payment_proof']['name'], PATHINFO_EXTENSION);
            $filename = 'comm_' . time() . '_' . uniqid() . '.' . $file_ext;
            
            if (move_uploaded_file($_FILES['payment_proof']['tmp_name'], $upload_dir . $filename)) {
                $proof_path = 'uploads/comisiones/' . $filename;
            } else {
                throw new Exception("Error al subir comprobante.");
            }
        }

        // 2. Insert Payment Record
        // id_vendedor (tbl_pago_comision) -> id_miembro
        $next_payout_id = $pdo->query("SELECT COALESCE(MAX(id_pago_comision), 0) + 1 as next_id FROM tbl_pago_comision")->fetch()['next_id'];
        $stmt_pay = $pdo->prepare("
            INSERT INTO tbl_pago_comision (id_pago_comision, id_vendedor, monto, ruta_archivo, estado, notas, fecha_pago)
            VALUES (?, ?, ?, ?, 'completado', ?, NOW())
        ");
        $stmt_pay->execute([$next_payout_id, $id_member, $payment_amount, $proof_path, $notes]);
        $payout_id = $next_payout_id;

        if (!$payout_id) {
             throw new Exception("Error al registrar el pago.");
        }

        // 3. Update Orders
        $order_ids = array_column($orders, 'id_order');
        if (!empty($order_ids)) {
            // Create placeholders for IN clause
            $placeholders = implode(',', array_fill(0, count($order_ids), '?'));
            $sql_update = "UPDATE tbl_pedido SET id_pago_comision = ? WHERE id_pedido IN ($placeholders)";
            
            // Params: [new_payout_id, order_id_1, order_id_2, ...]
            $params = array_merge([$payout_id], $order_ids);
            
            $stmt_update = $pdo->prepare($sql_update);
            $stmt_update->execute($params);
        }

        $pdo->commit();
        header("Location: index.php?tab=paid&success=1");
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Pagar Comisiones - Mai Shop</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/Front/dashboard/dashboard.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/Front/dashboard/comisiones/comisiones.css">
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>

        <main class="main-content">
            <div class="dashboard-header">
                <div class="header-left">
                    <a href="<?= BASE_URL ?>/Front/dashboard/comisiones/index.php" class="btn btn-secondary" style="margin-bottom: 1rem; display: inline-flex; align-items: center; gap: 0.5rem; text-decoration: none; padding: 0.5rem 1rem; border-radius: 8px; background: #e2e8f0; color: #4a5568;">
                        <i class="fas fa-arrow-left"></i> Volver a Comisiones
                    </a>
                    <h1>Registrar Pago de Comisiones</h1>
                    <p>Vendedor: <strong><?php echo htmlspecialchars(($member['first_name'] ?? '') . ' ' . ($member['last_name'] ?? '')); ?></strong></p>
                </div>
            </div>

            <?php if (isset($error)): ?>
                <div style="background: #FEB2B2; color: #C53030; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <div class="content-grid" style="grid-template-columns: 2fr 1fr; gap: 2rem;">
                
                <!-- Orders List -->
                <div class="content-card">
                    <div class="card-header">
                        <h2 class="card-title">Pedidos a Pagar (<?php echo count($orders); ?>)</h2>
                    </div>
                    <?php if (empty($orders)): ?>
                        <p>No hay pedidos pendientes para este vendedor.</p>
                    <?php else: ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Pedido #</th>
                                    <th>Total Venta</th>
                                    <th>Comisión</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $o): ?>
                                    <tr>
                                        <td><?php echo date('d/m/Y', strtotime($o['created_at'])); ?></td>
                                        <td>#<?php echo str_pad($o['id_order'], 4, '0', STR_PAD_LEFT); ?></td>
                                        <td>$<?php echo number_format($o['order_total'] ?? 0, 0, ',', '.'); ?></td>
                                        <td>
                                            <strong>$<?php echo number_format($o['commission_amount'] ?? 0, 0, ',', '.'); ?></strong>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

                <!-- Payment Form -->
                <div>
                    <form method="POST" enctype="multipart/form-data" class="content-card">
                        <h2 class="card-title" style="margin-bottom: 1.5rem;">Resumen del Pago</h2>
                        
                        <div style="margin-bottom: 1.5rem;">
                            <label style="display: block; color: var(--gray-600); margin-bottom: 0.5rem;">Total a Pagar</label>
                            <div style="font-size: 2rem; font-weight: 700; color: var(--primary);">
                                $<?php echo number_format($total_commission, 0, ',', '.'); ?>
                            </div>
                            <div style="font-size: 0.9rem; color: var(--gray-500);">
                                Tasa de comisión: <?php echo htmlspecialchars($member['commission_percentage'] ?? 0); ?>%
                            </div>
                        </div>

                        <div style="margin-bottom: 1.5rem;">
                            <label style="display: block; font-weight: 500; margin-bottom: 0.5rem;">Método de Pago</label>
                            <select name="payment_method" required style="width: 100%; padding: 0.5rem; border: 1px solid var(--gray-300); border-radius: 8px; background: white;">
                                <option value="1">Transferencia Bancaria</option>
                                <option value="2">Nequi</option>
                                <option value="3">Daviplata</option>
                                <option value="4">Efectivo</option>
                                <option value="5">Otro</option>
                            </select>
                        </div>

                        <div style="margin-bottom: 1.5rem;">
                            <label style="display: block; font-weight: 500; margin-bottom: 0.5rem;">
                                Comprobante de Transferencia *
                            </label>
                            <input type="file" name="payment_proof" required accept="image/*" 
                                style="width: 100%; padding: 0.5rem; border: 1px solid var(--gray-300); border-radius: 8px;">
                        </div>

                        <div style="margin-bottom: 1.5rem;">
                            <label style="display: block; font-weight: 500; margin-bottom: 0.5rem;">Notas (Opcional)</label>
                            <textarea name="notes" rows="3" style="width: 100%; padding: 0.5rem; border: 1px solid var(--gray-300); border-radius: 8px;"></textarea>
                        </div>

                        <button type="submit" class="pay-btn" style="width: 100%; justify-content: center; font-size: 1.1rem; padding: 1rem;" 
                            <?php echo empty($orders) ? 'disabled' : ''; ?>>
                            <i class="fas fa-check-circle"></i> Confirmar Pago
                        </button>
                    </form>
                </div>

            </div>
        </main>
    </div>
</body>
</html>
