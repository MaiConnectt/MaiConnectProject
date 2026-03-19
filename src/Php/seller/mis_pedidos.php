<?php
require_once __DIR__ . '/seller_auth.php';

// Filtros
$status_filter = isset($_GET['status']) ? (int) $_GET['status'] : -1;
$records_per_page = 15;
$current_page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$offset = ($current_page - 1) * $records_per_page;

// Construir consulta
$where_clause = "WHERE o.id_vendedor = ?";
$params = [$_SESSION['member_id']];

if ($status_filter >= 0) {
    $where_clause .= " AND o.estado = ?";
    $params[] = $status_filter;
}

// Total de pedidos
try {
    $count_query = "SELECT COUNT(*) as total FROM tbl_pedido o $where_clause";
    $count_stmt = $pdo->prepare($count_query);
    $count_stmt->execute($params);
    $total_records = $count_stmt->fetch()['total'];
    $total_pages = ceil($total_records / $records_per_page);
} catch (PDOException $e) {
    $total_records = 0;
    $total_pages = 0;
}

// Obtener pedidos
try {
    $query = "
        SELECT 
            o.id_pedido,
            o.fecha_creacion,
            o.estado,
            o.estado_pago,
            o.nota_cancelacion,
            ot.total,
            o.telefono_contacto,
            o.monto_comision as commission
        FROM tbl_pedido o
        INNER JOIN vw_totales_pedido ot ON o.id_pedido = ot.id_pedido
        $where_clause
        ORDER BY o.fecha_creacion DESC
        LIMIT $records_per_page OFFSET $offset
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $orders = $stmt->fetchAll();
} catch (PDOException $e) {
    $orders = [];
}

$pageTitle = 'Mis Pedidos';
?>
<?php include __DIR__ . '/includes/header.php'; ?>
            <div class="page-header">
                <h1>Mis Pedidos</h1>
                <p>Historial de ventas realizadas</p>

                <?php
                $success_msg = $_SESSION['success'] ?? null;
                $error_msg = $_SESSION['error'] ?? null;
                unset($_SESSION['success'], $_SESSION['error']);
                ?>

                <?php if ($success_msg): ?>
                    <div style="margin-top: 1rem; font-weight: 500; color: #22543d;">
                        <?php echo $success_msg; ?>
                    </div>
                <?php endif; ?>

                <?php if ($error_msg): ?>
                    <div style="margin-top: 1rem; font-weight: 500; color: #c53030;">
                        <?php echo $error_msg; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="content-card">
                <div class="card-header">
                    <h3 class="card-title">Filtrar por Estado</h3>
                    <div style="display: flex; gap: 0.5rem;">
                        <a href="?status=-1"
                            class="btn <?php echo $status_filter === -1 ? 'btn-primary' : 'btn-secondary'; ?>"
                            style="padding: 0.5rem 1rem; font-size: 0.875rem;">Todos</a>
                        <a href="?status=0"
                            class="btn <?php echo $status_filter === 0 ? 'btn-primary' : 'btn-secondary'; ?>"
                            style="padding: 0.5rem 1rem; font-size: 0.875rem;">Pendiente</a>
                        <a href="?status=1"
                            class="btn <?php echo $status_filter === 1 ? 'btn-primary' : 'btn-secondary'; ?>"
                            style="padding: 0.5rem 1rem; font-size: 0.875rem;">En Proceso</a>
                        <a href="?status=2"
                            class="btn <?php echo $status_filter === 2 ? 'btn-primary' : 'btn-secondary'; ?>"
                            style="padding: 0.5rem 1rem; font-size: 0.875rem;">Completado</a>
                    </div>
                </div>

                <?php if (empty($orders)): ?>
                    <div style="text-align: center; padding: 3rem; color: var(--gray-500);">
                        <i class="fas fa-shopping-cart" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.3;"></i>
                        <p>No se encontraron pedidos</p>
                        <a href="nuevo_pedido.php" class="btn btn-primary" style="margin-top: 1rem;"><i
                                class="fas fa-plus"></i> Crear Pedido</a>
                    </div>
                <?php else: ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Pedido #</th>
                                <th>Contacto</th>
                                <th>Fecha</th>
                                <th>Total</th>
                                <th>Comisión</th>
                                <th>Estado</th>
                                <th>Pago</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td style="font-weight: 600;">#
                                        <?php echo str_pad($order['id_pedido'], 4, '0', STR_PAD_LEFT); ?>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($order['telefono_contacto'] ?? '-'); ?>
                                    </td>
                                    <td>
                                        <?php echo date('d/m/Y H:i', strtotime($order['fecha_creacion'])); ?>
                                    </td>
                                    <td style="font-weight: 600;">
                                        <?php echo formato_moneda($order['total']); ?>
                                    </td>
                                    <td style="color: var(--success); font-weight: 600;">
                                        <?php echo formato_moneda($order['commission']); ?>
                                    </td>
                                    <td>
                                        <?php echo getStatusBadge($order['estado']); ?>
                                    </td>
                                    <td>
                                        <?php echo getPaymentBadge($order['estado_pago']); ?>
                                    </td>
                                    <td>
                                        <div style="display: flex; gap: 0.25rem;">
                                            <?php if ($order['estado'] == 3): ?>
                                                <!-- Pedido cancelado: solo icono de nota -->
                                                <?php $nota = $order['nota_cancelacion'] ?? ''; ?>
                                                <?php if (!empty($nota)): ?>
                                                    <button
                                                        onclick="verNotaCancelacion(<?php echo $order['id_pedido']; ?>, <?php echo json_encode($nota, JSON_HEX_QUOT | JSON_HEX_APOS | JSON_HEX_TAG); ?>)"
                                                        class="btn btn-secondary"
                                                        style="padding: 0.25rem 0.5rem; font-size: 0.8rem; background:#fff3cd; color:#856404; border:1px solid #ffc107;"
                                                        title="Ver motivo de cancelación">
                                                        <i class="fas fa-file-alt"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <span style="font-size:0.78rem; color:#999; font-style:italic;">Sin nota</span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <!-- Pedido activo: botones normales -->
                                                <a href="https://wa.me/57<?php echo preg_replace('/[^0-9]/', '', $order['telefono_contacto']); ?>"
                                                    target="_blank" class="btn btn-secondary"
                                                    style="padding: 0.25rem 0.5rem; font-size: 0.8rem; background: #25D366; color: white; border: none;"
                                                    title="WhatsApp">
                                                    <i class="fab fa-whatsapp"></i>
                                                </a>

                                                <?php if ($order['estado_pago'] == 0 || $order['estado_pago'] == 3): ?>
                                                    <button
                                                        onclick="openUploadModal(<?php echo $order['id_pedido']; ?>, <?php echo $order['total']; ?>)"
                                                        class="btn btn-primary" style="padding: 0.25rem 0.5rem; font-size: 0.8rem;"
                                                        title="Subir Pago">
                                                        <i class="fas fa-upload"></i>
                                                    </button>
                                                <?php endif; ?>

                                                <?php if ($order['estado'] == 1 && $order['estado_pago'] == 2): ?>
                                                    <button onclick="markAsCompleted(<?php echo $order['id_pedido']; ?>)"
                                                        class="btn btn-primary"
                                                        style="padding: 0.25rem 0.5rem; font-size: 0.8rem; background: #20ba5a;"
                                                        title="Completar pedido">
                                                        <i class="fas fa-check-double"></i>
                                                    </button>
                                                <?php elseif ($order['estado'] == 1 && $order['estado_pago'] != 2): ?>
                                                    <span title="Pago pendiente de aprobación — no se puede completar aún"
                                                        style="display:inline-flex; align-items:center; padding: 0.25rem 0.5rem; font-size: 0.8rem; background: #eee; color: #999; border-radius: 6px; cursor: not-allowed;">
                                                        <i class="fas fa-lock"></i>
                                                    </span>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <?php if ($total_pages > 1): ?>
                        <div style="display: flex; justify-content: center; gap: 0.5rem; margin-top: 2rem;">
                            <?php if ($current_page > 1): ?>
                                <a href="?page=<?php echo $current_page - 1; ?>&status=<?php echo $status_filter; ?>"
                                    class="btn btn-secondary"><i class="fas fa-chevron-left"></i></a>
                            <?php endif; ?>
                            <?php for ($i = max(1, $current_page - 2); $i <= min($total_pages, $current_page + 2); $i++): ?>
                                <a href="?page=<?php echo $i; ?>&status=<?php echo $status_filter; ?>"
                                    class="btn <?php echo $i === $current_page ? 'btn-primary' : 'btn-secondary'; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                            <?php if ($current_page < $total_pages): ?>
                                <a href="?page=<?php echo $current_page + 1; ?>&status=<?php echo $status_filter; ?>"
                                    class="btn btn-secondary"><i class="fas fa-chevron-right"></i></a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>
    <?php include __DIR__ . '/includes/modals_pedidos.php'; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>