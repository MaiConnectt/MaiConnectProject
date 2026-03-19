<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../config/conexion.php';

// Get seller ID
$seller_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if (empty($seller_id)) {
    header('Location: ' . BASE_URL . '/src/Php/dashboard/equipo/equipo.php');
    exit;
}

// Get seller details with statistics
try {
    $stmt = $pdo->prepare("
        SELECT 
            m.*,
            u.nombre,
            u.apellido,
            u.email,
            (SELECT COUNT(*) FROM tbl_pedido o WHERE o.id_vendedor = m.id_miembro AND o.estado = 2) as total_orders,
            (SELECT COALESCE(SUM(ot.total),0) FROM tbl_pedido o JOIN vw_totales_pedido ot ON o.id_pedido = ot.id_pedido WHERE o.id_vendedor = m.id_miembro AND o.estado = 2) as total_sales,
            (SELECT COALESCE(SUM(monto_comision),0) FROM tbl_pedido o WHERE o.id_vendedor = m.id_miembro AND o.estado = 2) as total_commissions_earned,
            (SELECT COALESCE(SUM(monto_comision),0) FROM tbl_pedido o WHERE o.id_vendedor = m.id_miembro AND o.estado = 2 AND o.id_pago_comision IS NOT NULL) as total_paid,
            (SELECT COALESCE(SUM(monto_comision),0) FROM tbl_pedido o WHERE o.id_vendedor = m.id_miembro AND o.estado = 2 AND o.id_pago_comision IS NULL) as balance_pending
        FROM tbl_miembro m
        INNER JOIN tbl_usuario u ON m.id_usuario = u.id_usuario
        WHERE m.id_miembro = ?
    ");
    $stmt->execute([$seller_id]);
    $seller = $stmt->fetch();

    if (!$seller) {
        header('Location: ' . BASE_URL . '/src/Php/dashboard/equipo/equipo.php');
        exit;
    }

    // Get recent orders
    $stmt = $pdo->prepare("
        SELECT 
            o.*,
            vw.total as monto_total
        FROM tbl_pedido o
        LEFT JOIN vw_totales_pedido vw ON o.id_pedido = vw.id_pedido
        WHERE o.id_vendedor = ?
        ORDER BY o.fecha_creacion DESC
        LIMIT 10
    ");
    $stmt->execute([$seller_id]);
    $orders = $stmt->fetchAll();

} catch (PDOException $e) {
    $error = "Error al cargar los detalles del vendedor: " . $e->getMessage();
}

function getStatusBadge($status)
{
    switch ($status) {
        case 0:
            return '<span class="status-badge pending">Pendiente</span>';
        case 1:
            return '<span class="status-badge processing">En Proceso</span>';
        case 2:
            return '<span class="status-badge completed">Completado</span>';
        case 3:
            return '<span class="status-badge cancelled">Cancelado</span>';
        default:
            return '<span class="status-badge">Desconocido</span>';
    }
}
?>
<?php
$page_title = 'Perfil del Vendedor - Mai Shop';
$extra_css  = [BASE_URL . '/styles/equipo.css'];
require_once __DIR__ . '/../includes/head.php';
?>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<main class="main-content">
    <a href="equipo.php" class="btn-back"><i class="fas fa-arrow-left"></i> Volver al equipo</a>

    <?php if (isset($error)): ?>
        <div class="error-message">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo $error; ?>
        </div>
    <?php else: ?>
        <div class="profile-header">
            <div class="profile-avatar-large">
                <?php echo strtoupper(substr($seller['nombre'], 0, 1) . substr($seller['apellido'], 0, 1)); ?>
            </div>
            <div class="profile-info">
                <h1>
                    <?php echo htmlspecialchars($seller['nombre'] . ' ' . $seller['apellido']); ?>
                </h1>
                <div class="profile-meta">
                    <div class="meta-item">
                        <i class="fas fa-envelope"></i>
                        <?php echo htmlspecialchars($seller['email']); ?>
                    </div>
                    <?php if (!empty($seller['telefono'])): ?>
                        <div class="meta-item">
                            <i class="fas fa-phone"></i>
                            <?php echo htmlspecialchars($seller['telefono']); ?>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($seller['universidad'])): ?>
                        <div class="meta-item">
                            <i class="fas fa-graduation-cap"></i>
                            <?php echo htmlspecialchars($seller['universidad']); ?>
                        </div>
                    <?php endif; ?>
                    <div class="meta-item">
                        <i class="fas fa-calendar-alt"></i>
                        Miembro desde:
                        <?php echo date('d/m/Y', strtotime($seller['fecha_contratacion'])); ?>
                    </div>
                    <div class="meta-item">
                        <span
                            class="seller-status-badge <?php echo $seller['estado'] === 'activo' ? 'active' : 'inactive'; ?>">
                            <?php echo ucfirst($seller['estado']); ?>
                        </span>
                    </div>
                </div>
            </div>
            <div class="profile-actions">
                <a href="editar.php?id=<?php echo $seller['id_miembro']; ?>" class="btn-profile-action edit">
                    <i class="fas fa-edit"></i> Editar
                </a>
            </div>
        </div>

        <div class="stats-grid-large">
            <div class="stat-card-large">
                <i class="fas fa-shopping-cart"></i>
                <span class="value">
                    <?php echo $seller['total_orders']; ?>
                </span>
                <span class="label">Pedidos Completados</span>
            </div>
            <div class="stat-card-large">
                <i class="fas fa-dollar-sign"></i>
                <span class="value">$
                    <?php echo number_format($seller['total_sales'] ?? 0, 0, ',', '.'); ?>
                </span>
                <span class="label">Ventas Totales</span>
            </div>
            <div class="stat-card-large">
                <i class="fas fa-wallet"></i>
                <span class="value">$
                    <?php echo number_format($seller['total_commissions_earned'] ?? 0, 0, ',', '.'); ?>
                </span>
                <span class="label">Comisiones Generadas</span>
            </div>
            <div class="stat-card-large" style="border-bottom: 4px solid var(--primary-color);">
                <i class="fas fa-clock"></i>
                <span class="value" style="color: var(--primary-color);">$
                    <?php echo number_format($seller['balance_pending'] ?? 0, 0, ',', '.'); ?>
                </span>
                <span class="label">Saldo Pendiente</span>
            </div>
        </div>

        <div class="orders-card">
            <h2 class="card-title"><i class="fas fa-history"></i> Pedidos Recientes</h2>
            <div class="table-responsive">
                <table class="orders-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Fecha</th>
                            <th>Cliente/Dirección</th>
                            <th>Total</th>
                            <th>Estado</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($orders)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 3rem; color: var(--gray-400);">
                                    No hay pedidos registrados para este vendedor.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td>#
                                        <?php echo str_pad($order['id_pedido'], 4, '0', STR_PAD_LEFT); ?>
                                    </td>
                                    <td>
                                        <?php echo date('d/m/Y', strtotime($order['fecha_creacion'])); ?>
                                    </td>
                                    <td>
                                        <div style="font-weight: 600;">
                                            <?php echo htmlspecialchars($order['telefono_contacto']); ?>
                                        </div>
                                        <div style="font-size: 0.8rem; color: var(--gray-500);">
                                            <?php echo htmlspecialchars($order['direccion_entrega']); ?>
                                        </div>
                                    </td>
                                    <td style="font-weight: 700;">$
                                        <?php echo number_format($order['monto_total'] ?? 0, 0, ',', '.'); ?>
                                    </td>
                                    <td>
                                        <?php echo getStatusBadge($order['estado']); ?>
                                    </td>
                                    <td>
                                        <a href="<?= BASE_URL ?>/src/Php/dashboard/pedidos/ver.php?id=<?php echo $order['id_pedido']; ?>"
                                            class="btn-view-order" title="Ver pedido">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</main>

<?php
$extra_scripts = [];
require_once __DIR__ . '/../includes/footer.php';
?>