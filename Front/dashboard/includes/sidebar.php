<?php
// Function to determine if a link is active based on path
function isActive($path_segment)
{
    return strpos($_SERVER['PHP_SELF'], $path_segment) !== false ? 'active' : '';
}

// Count pending commissions for notification badge
$pending_commissions_count = 0;
try {
    if (isset($pdo)) {
        $stmt_badge = $pdo->query("SELECT COUNT(*) FROM tbl_pedido WHERE estado = 2 AND monto_comision > 0 AND id_pago_comision IS NULL");
        $pending_commissions_count = $stmt_badge->fetchColumn();
    }
} catch (Exception $e) {
    // Silent fail for sidebar
}
?>
<aside class="sidebar">
    <div class="sidebar-header">
        <img src="<?= BASE_URL ?>/Front/img/mai.png" alt="Mai Shop" class="sidebar-logo">
        <h2 class="sidebar-title" style="color: #ff6b6b;">Mai Connect</h2>
    </div>

    <nav class="sidebar-nav">
        <!-- Dashboard -->
        <a href="<?= BASE_URL ?>/Front/dashboard/dash.php" class="nav-item <?php echo isActive('/dash.php'); ?>">
            <i class="fas fa-home"></i>
            <span>Dashboard</span>
        </a>

        <!-- Pedidos -->
        <a href="<?= BASE_URL ?>/Front/dashboard/pedidos/pedidos.php"
            class="nav-item <?php echo isActive('/pedidos/'); ?>">
            <i class="fas fa-shopping-cart"></i>
            <span>Pedidos</span>
        </a>

        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'ADMIN'): ?>
            <!-- Productos (Solo Admin) -->
            <a href="<?= BASE_URL ?>/Front/dashboard/productos/productos.php"
                class="nav-item <?php echo isActive('/productos/'); ?>">
                <i class="fas fa-box"></i>
                <span>Productos</span>
            </a>

            <!-- Vendedores / Equipo (Solo Admin) -->
            <a href="<?= BASE_URL ?>/Front/dashboard/equipo/equipo.php"
                class="nav-item <?php echo isActive('/equipo/'); ?>">
                <i class="fas fa-users"></i>
                <span>Vendedores</span>
            </a>
        <?php endif; ?>

        <!-- Comisiones -->
        <a href="<?= BASE_URL ?>/Front/dashboard/comisiones/index.php"
            class="nav-item <?php echo isActive('/comisiones/'); ?>"
            style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <i class="fas fa-dollar-sign"></i>
                <span>Comisiones</span>
            </div>
            <?php if ($pending_commissions_count > 0): ?>
                <span
                    style="background: #ff6b6b; color: white; padding: 2px 8px; border-radius: 12px; font-size: 0.75rem; font-weight: bold;">
                    <?php echo $pending_commissions_count; ?>
                </span>
            <?php endif; ?>
        </a>

        <!-- Reportes -->
        <a href="<?= BASE_URL ?>/Front/dashboard/reports.php" class="nav-item <?php echo isActive('/reports.php'); ?>">
            <i class="fas fa-chart-line"></i>
            <span>Reportes</span>
        </a>

        <!-- Configuración -->
        <a href="<?= BASE_URL ?>/Front/dashboard/settings.php"
            class="nav-item <?php echo isActive('/settings.php'); ?>">
            <i class="fas fa-cog"></i>
            <span>Configuración</span>
        </a>
    </nav>

    <a href="<?= BASE_URL ?>/Front/dashboard/logout.php" class="logout-btn">
        <i class="fas fa-sign-out-alt"></i>
        <span>Cerrar Sesión</span>
    </a>
</aside>

<!-- Global Modals and Scripts -->
<?php include_once __DIR__ . '/modals.php'; ?>
<link rel="stylesheet" href="<?= BASE_URL ?>/Front/css/mai-modal.css">
<script src="<?= BASE_URL ?>/Front/js/mai-modal.js"></script>