<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../conexion.php';

// Get seller ID
$seller_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if (empty($seller_id)) {
    header('Location: equipo.php');
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
        header('Location: equipo.php');
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
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Perfil del Vendedor - Mai Shop</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../dashboard.css">
    <link rel="stylesheet" href="equipo.css">
    <style>
        .profile-header {
            background: white;
            padding: 2.5rem;
            border-radius: 20px;
            box-shadow: var(--shadow-sm);
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 2rem;
            flex-wrap: wrap;
        }

        .profile-avatar-large {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: var(--gradient-primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            font-weight: 700;
            box-shadow: 0 10px 20px rgba(201, 124, 137, 0.2);
        }

        .profile-info h1 {
            font-family: var(--font-heading);
            font-size: 2.2rem;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }

        .profile-meta {
            display: flex;
            gap: 1.5rem;
            flex-wrap: wrap;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--gray-600);
            font-size: 0.95rem;
        }

        .meta-item i {
            color: var(--primary-color);
        }

        .profile-actions {
            margin-left: auto;
            display: flex;
            gap: 1rem;
        }

        .btn-profile-action {
            padding: 0.75rem 1.5rem;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }

        .btn-profile-action.edit {
            background: var(--accent-color);
            color: var(--primary-color);
        }

        .btn-profile-action.whatsapp {
            background: #e6f9f0;
            color: #25d366;
        }

        .stats-grid-large {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card-large {
            background: white;
            padding: 2rem;
            border-radius: 20px;
            box-shadow: var(--shadow-sm);
            text-align: center;
            transition: all 0.3s ease;
        }

        .stat-card-large:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-md);
        }

        .stat-card-large i {
            font-size: 2rem;
            color: var(--primary-color);
            margin-bottom: 1rem;
            display: block;
        }

        .stat-card-large .value {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--dark);
            display: block;
            margin-bottom: 0.25rem;
        }

        .stat-card-large .label {
            color: var(--gray-500);
            font-size: 0.9rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .orders-card {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: var(--shadow-sm);
        }

        .card-title {
            font-family: var(--font-heading);
            font-size: 1.5rem;
            color: var(--dark);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .table-responsive {
            overflow-x: auto;
        }

        .orders-table {
            width: 100%;
            border-collapse: collapse;
        }

        .orders-table th {
            text-align: left;
            padding: 1rem;
            color: var(--gray-500);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.8rem;
            border-bottom: 2px solid var(--gray-100);
        }

        .orders-table td {
            padding: 1.25rem 1rem;
            border-bottom: 1px solid var(--gray-100);
            color: var(--gray-700);
        }

        .btn-view-order {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--accent-color);
            color: var(--primary-color);
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .btn-view-order:hover {
            background: var(--primary-color);
            color: white;
        }

        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            color: var(--gray-600);
            font-weight: 500;
            margin-bottom: 1.5rem;
            transition: color 0.3s ease;
        }

        .btn-back:hover {
            color: var(--primary-color);
        }
    </style>
</head>

<body>
    <div class="dashboard-container">
        <?php $base = '..';
        include __DIR__ . '/../includes/sidebar.php'; ?>

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
                        <?php if (!empty($seller['telefono'])): ?>
                            <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $seller['telefono']); ?>"
                                target="_blank" class="btn-profile-action whatsapp">
                                <i class="fab fa-whatsapp"></i> WhatsApp
                            </a>
                        <?php endif; ?>
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
                            <?php echo number_format($seller['total_sales'], 0, ',', '.'); ?>
                        </span>
                        <span class="label">Ventas Totales</span>
                    </div>
                    <div class="stat-card-large">
                        <i class="fas fa-wallet"></i>
                        <span class="value">$
                            <?php echo number_format($seller['total_commissions_earned'], 0, ',', '.'); ?>
                        </span>
                        <span class="label">Comisiones Generadas</span>
                    </div>
                    <div class="stat-card-large" style="border-bottom: 4px solid var(--primary-color);">
                        <i class="fas fa-clock"></i>
                        <span class="value" style="color: var(--primary-color);">$
                            <?php echo number_format($seller['balance_pending'], 0, ',', '.'); ?>
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
                                                <?php echo number_format($order['monto_total'], 0, ',', '.'); ?>
                                            </td>
                                            <td>
                                                <?php echo getStatusBadge($order['estado']); ?>
                                            </td>
                                            <td>
                                                <a href="../pedidos/ver.php?id=<?php echo $order['id_pedido']; ?>"
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
    </div>
    <script src="../dashboard.js"></script>
</body>

</html>