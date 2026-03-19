<?php
session_start();
require_once __DIR__ . '/../../config/conexion.php';
require_once __DIR__ . '/../../config/helpers.php';

// Auth check
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header('Location: ' . BASE_URL . '/src/Php/login/login.php');
    exit;
}

$id_member = $_GET['id_member'] ?? null;
$id_pedido_single = $_GET['id_pedido'] ?? null;

if (!$id_member && !$id_pedido_single) {
    header('Location: index.php');
    exit;
}

$orders = [];
$total_commission = 0;

try {
    require_once __DIR__ . '/comisiones_helper.php';

    // Determine member from order if only order ID is provided
    if ($id_pedido_single && !$id_member) {
        $stmt_mem = $pdo->prepare("
            SELECT m.id_miembro 
            FROM tbl_pedido p 
            JOIN tbl_miembro m ON p.id_vendedor = m.id_miembro 
            WHERE p.id_pedido = ?
        ");
        $stmt_mem->execute([$id_pedido_single]);
        $id_member = $stmt_mem->fetchColumn();
        if (!$id_member) throw new Exception("Pedido o vendedor no encontrado.");
    }

    // Fetch Member Info & Orders via helpers
    $member = obtenerMiembro($pdo, $id_member);
    $orders = obtenerPedidosPendientes($pdo, $id_member, $id_pedido_single);
    $total_commission = calcularTotalComision($orders);

    // Handle Form Submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (empty($orders)) {
             throw new Exception("No hay pedidos para pagar.");
        }

        $proof_path = null;
        $proof_path = null;

        if (isset($_FILES['payment_proof']) && $_FILES['payment_proof']['error'] === UPLOAD_ERR_OK) {
            $proof_path = subirComprobante($_FILES['payment_proof']);
        }

        $order_ids = array_column($orders, 'id_order');
        
        procesarPagoComision($pdo, $id_member, $total_commission, $proof_path, $order_ids);

        header("Location: index.php?tab=paid&success=1");
        exit;
    }

} catch (Exception $e) {
    if (!isset($member)) $member = [];
    $error = "Error: " . $e->getMessage();
    error_log("PAGAR COMISIONES ERROR: " . $e->getMessage());
}

$page_title = 'Pagar Comisiones - Mai Shop';
$extra_css = [BASE_URL . '/styles/comisiones.css'];
require_once __DIR__ . '/../includes/head.php';
?>
<!-- Sidebar -->
<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<main class="main-content">
            <div class="team-header">
                <div class="header-left">
                    <a href="<?= BASE_URL ?>/src/Php/dashboard/comisiones/index.php" class="btn btn-secondary" style="margin-bottom: 1rem; display: inline-flex; align-items: center; gap: 0.5rem; text-decoration: none; padding: 0.5rem 1rem; border-radius: 8px; background: #e2e8f0; color: #4a5568;">
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
                                        <td><?php echo formato_moneda($o['order_total'] ?? 0); ?></td>
                                        <td>
                                            <strong><?php echo formato_moneda($o['commission_amount'] ?? 0); ?></strong>
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
                                <?php echo formato_moneda($total_commission); ?>
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



                        <button type="submit" class="pay-btn" style="width: 100%; justify-content: center; font-size: 1.1rem; padding: 1rem;" 
                            <?php echo empty($orders) ? 'disabled' : ''; ?>>
                            <i class="fas fa-check-circle"></i> Confirmar Pago
                        </button>
                    </form>
                </div>

            </div>
        </main>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
