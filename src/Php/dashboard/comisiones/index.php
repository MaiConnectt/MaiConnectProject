<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../config/conexion.php';
require_once __DIR__ . '/../../config/helpers.php';

$current_user = [
    'id' => $_SESSION['user_id'] ?? 0,
    'name' => ($_SESSION['first_name'] ?? 'Usuario') . ' ' . ($_SESSION['last_name'] ?? ''),
    'email' => $_SESSION['email'] ?? '',
    'role' => 'Administrador'
];

// Active Tab
$active_tab = $_GET['tab'] ?? 'pending';

// 1. Fetch Pendientes
try {
    $sql_pending = "
        SELECT 
            p.id_pedido,
            p.fecha_creacion,
            p.monto_comision,
            ot.total as total_pedido,
            u.nombre, 
            u.apellido,
            m.porcentaje_comision
        FROM tbl_pedido p
        JOIN tbl_miembro m ON p.id_vendedor = m.id_miembro
        JOIN tbl_usuario u ON m.id_usuario = u.id_usuario
        JOIN vw_totales_pedido ot ON p.id_pedido = ot.id_pedido
        WHERE p.estado = 2 
        AND p.monto_comision > 0 
        AND p.id_pago_comision IS NULL
        ORDER BY p.fecha_creacion DESC
    ";
    $stmt = $pdo->query($sql_pending);
    $pending_orders = $stmt->fetchAll();
} catch (PDOException $e) {
    $pending_orders = [];
    $error = "Error al cargar pendientes: " . $e->getMessage();
}

// 2. Fetch Pagadas
try {
    $sql_paid = "
        SELECT 
            p.id_pedido,
            p.fecha_creacion,
            p.monto_comision,
            pc.fecha_pago,
            pc.ruta_archivo,
            u.nombre,
            u.apellido
        FROM tbl_pedido p
        JOIN tbl_pago_comision pc ON p.id_pago_comision = pc.id_pago_comision
        JOIN tbl_miembro m ON p.id_vendedor = m.id_miembro
        JOIN tbl_usuario u ON m.id_usuario = u.id_usuario
        WHERE p.id_pago_comision IS NOT NULL
        ORDER BY pc.fecha_pago DESC
    ";
    $stmt_paid = $pdo->query($sql_paid);
    $paid_orders = $stmt_paid->fetchAll();
} catch (PDOException $e) {
    $paid_orders = [];
}

$page_title = 'Comisiones - Mai Shop';
$extra_css = [BASE_URL . '/styles/comisiones.css'];
require_once __DIR__ . '/../includes/head.php';
?>

<!-- Sidebar -->
<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<!-- Main Content -->
<main class="main-content">
    <div class="team-header">
        <div class="header-left">
            <h1>Gestión de Comisiones</h1>
            <p>Administra los pagos pendientes a tu equipo de ventas</p>
        </div>
    </div>

    <!-- Tabs -->
    <div class="tabs">
        <a href="?tab=pending" class="tab-item <?php echo $active_tab === 'pending' ? 'active' : ''; ?>">
            <i class="fas fa-clock"></i> Pendientes
            <?php if (count($pending_orders) > 0): ?>
                <span class="badge"
                    style="background:var(--danger); color:white; font-size:0.75rem; padding:2px 6px; border-radius:10px; margin-left:5px;">
                    <?php echo count($pending_orders); ?>
                </span>
            <?php endif; ?>
        </a>
        <a href="?tab=paid" class="tab-item <?php echo $active_tab === 'paid' ? 'active' : ''; ?>">
            <i class="fas fa-check-circle"></i> Pagadas
        </a>
    </div>

    <div class="content-card">
        <?php if ($active_tab === 'pending'): ?>
            <div class="card-header">
                <h2 class="card-title">Comisiones Pendientes de Pago</h2>
            </div>

            <table class="data-table">
                <thead>
                    <tr>
                        <th>Pedido #</th>
                        <th>Fecha</th>
                        <th>Vendedor</th>
                        <th>Total Pedido</th>
                        <th>Comisión</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($pending_orders)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 2rem; color: var(--gray);">
                                No hay comisiones pendientes por pagar.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($pending_orders as $order): ?>
                            <tr>
                                <td>#<?php echo str_pad($order['id_pedido'], 4, '0', STR_PAD_LEFT); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($order['fecha_creacion'])); ?></td>
                                <td>
                                    <?php echo htmlspecialchars($order['nombre'] . ' ' . $order['apellido']); ?>
                                    <div style="font-size:0.8rem; color:var(--gray);">
                                        <?php echo $order['porcentaje_comision']; ?>%
                                    </div>
                                </td>
                                <td><?php echo formato_moneda($order['total_pedido'] ?? 0); ?></td>
                                <td>
                                    <span style="font-weight: 700; color: var(--danger);">
                                        <?php echo formato_moneda($order['monto_comision'] ?? 0); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="<?= BASE_URL ?>/src/Php/dashboard/comisiones/pagar.php?id_pedido=<?php echo $order['id_pedido']; ?>"
                                        class="btn btn-primary" style="padding: 0.4rem 0.8rem; font-size: 0.85rem;">
                                        <i class="fas fa-money-bill-wave"></i> Registrar Pago
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

        <?php else: ?>
            <!-- PAID TAB -->
            <div class="card-header">
                <h2 class="card-title">Historial de Comisiones Pagadas</h2>
            </div>

            <table class="data-table">
                <thead>
                    <tr>
                        <th>Pedido #</th>
                        <th>Fecha Pago</th>
                        <th>Vendedor</th>
                        <th>Monto Pagado</th>
                        <th>Comprobante</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($paid_orders)): ?>
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 2rem; color: var(--gray);">
                                No hay historial de pagos registrado.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($paid_orders as $pay): ?>
                            <tr>
                                <td>#<?php echo str_pad($pay['id_pedido'], 4, '0', STR_PAD_LEFT); ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($pay['fecha_pago'])); ?></td>
                                <td><?php echo htmlspecialchars($pay['nombre'] . ' ' . $pay['apellido']); ?></td>
                                <td>
                                    <span style="font-weight: 700; color: var(--success);">
                                        <?php echo formato_moneda($pay['monto_comision'] ?? 0); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($pay['ruta_archivo'])): ?>
                                        <a href="<?= BASE_URL ?>/src/Php/<?php echo htmlspecialchars($pay['ruta_archivo']); ?>"
                                            target="_blank" class="action-link">
                                            <i class="fas fa-file-invoice"></i> Ver Recibo
                                        </a>
                                    <?php else: ?>
                                        <span style="color:var(--gray-light);">Sin comprobante</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <?php require_once __DIR__ . '/../includes/footer.php'; ?>