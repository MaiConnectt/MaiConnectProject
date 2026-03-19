<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../config/helpers.php';

// --- FUNCIONES DE AYUDA ---

function getSalesStats($pdo)
{
    // Combinamos las 3 estadísticas en una sola consulta para ahorrar viajes a la BD
    $sql = "SELECT 
                (SELECT COALESCE(SUM(ot.total), 0) FROM tbl_pedido o JOIN vw_totales_pedido ot ON o.id_pedido = ot.id_pedido WHERE o.estado = 2) as total_sales,
                (SELECT COUNT(*) FROM tbl_pedido) as total_orders,
                (SELECT COALESCE(SUM(cantidad), 0) FROM tbl_detalle_pedido od JOIN tbl_pedido o ON od.id_pedido = o.id_pedido WHERE o.estado = 2) as total_items";
    
    return $pdo->query($sql)->fetch(PDO::FETCH_ASSOC);
}


function getOrdersByStatus($pdo)
{
    $sql = "SELECT estado as status, COUNT(*) as count FROM tbl_pedido GROUP BY estado";
    return $pdo->query($sql)->fetchAll();
}

function getTopProducts($pdo)
{
    // Top 5 productos más vendidos
    $sql = "
        SELECT p.nombre_producto as name, SUM(od.cantidad) as total_sold
        FROM tbl_detalle_pedido od
        JOIN tbl_producto p ON od.id_producto = p.id_producto
        JOIN tbl_pedido o ON od.id_pedido = o.id_pedido
        WHERE o.estado = 2
        GROUP BY p.id_producto, p.nombre_producto
        ORDER BY total_sold DESC
        LIMIT 5
    ";
    return $pdo->query($sql)->fetchAll();
}

function getSalesByMonth($pdo)
{
    $sql = "
        SELECT 
            TO_CHAR(DATE_TRUNC('month', o.fecha_creacion), 'YYYY-MM') AS mes,
            TO_CHAR(DATE_TRUNC('month', o.fecha_creacion), 'Mon YYYY') AS mes_label,
            COALESCE(SUM(ot.total), 0) AS total_ventas
        FROM tbl_pedido o
        JOIN vw_totales_pedido ot ON o.id_pedido = ot.id_pedido
        WHERE o.estado = 2
        GROUP BY DATE_TRUNC('month', o.fecha_creacion)
        ORDER BY DATE_TRUNC('month', o.fecha_creacion) ASC
    ";
    return $pdo->query($sql)->fetchAll();
}

function getSellersByUniversity($pdo)
{
    $sql = "
        SELECT 
            COALESCE(universidad, 'Sin especificar') AS universidad,
            COUNT(*) AS total_vendedores
        FROM tbl_miembro
        WHERE estado != 'eliminado'
        GROUP BY universidad
        ORDER BY total_vendedores DESC
    ";
    return $pdo->query($sql)->fetchAll();
}

try {
    $stats = getSalesStats($pdo);
    $orders_status = getOrdersByStatus($pdo);
    $top_products = getTopProducts($pdo);
    $sales_by_month = getSalesByMonth($pdo);
    $sellers_by_uni = getSellersByUniversity($pdo);
} catch (PDOException $e) {
    // En caso de error, arrays vacíos para no romper la UI
    $error = $e->getMessage();
    $stats = ['total_sales' => 0, 'total_orders' => 0, 'total_items' => 0];
    $orders_status = [];
    $top_products = [];
    $sales_by_month = [];
    $sellers_by_uni = [];
}


// Status labels
$status_map = [0 => 'Pendiente', 1 => 'En Proceso', 2 => 'Completado'];
// Colores: Pendiente (Amarillo), Proceso (Azul), Completado (Verde)
$color_map = [0 => '#e6c86e', 1 => '#74ebd5', 2 => '#20ba5a'];

// Prellenamos con 0 para garantizar que siempre aparezcan las 3 leyendas y colores, aunque no haya pedidos en ese estado
$status_counts = [0 => 0, 1 => 0, 2 => 0];

foreach ($orders_status as $row) {
    if (isset($status_counts[$row['status']])) {
        $status_counts[$row['status']] = (int)$row['count'];
    }
}

$status_labels = [];
$status_data = [];
$status_colors = [];

foreach ($status_counts as $status => $count) {
    if ($count > 0 || true) { // Se muestran incluso en 0 para mantener la estructura (si deseas ocultar los de 0, quita el "|| true")
        $status_labels[] = $status_map[$status];
        $status_data[] = $count;
        $status_colors[] = $color_map[$status];
    }
}
?>
<?php
$page_title = 'Reportes - Mai Shop';
require_once __DIR__ . '/includes/head.php';
?>
<!-- Sidebar -->
<?php include __DIR__ . '/includes/sidebar.php'; ?>

<main class="main-content">

    <div class="dashboard-header">
        <div class="header-left">
            <h1>Reportes y Estadísticas</h1>
            <p>Visión general del rendimiento de tu negocio</p>
        </div>
    </div>

    <!-- Stats Grid -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-header">
                <div>
                    <div class="stat-value">
                        <?php echo formato_moneda($stats['total_sales'] ?? 0); ?>
                    </div>
                    <div class="stat-label">Ventas Totales</div>
                </div>
                <div class="stat-icon" style="background: var(--gradient-primary);"><i
                        class="fas fa-dollar-sign"></i></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-header">
                <div>
                    <div class="stat-value">
                        <?php echo number_format($stats['total_orders'] ?? 0); ?>
                    </div>
                    <div class="stat-label">Pedidos Totales</div>
                </div>
                <div class="stat-icon" style="background: var(--gradient-secondary);"><i
                        class="fas fa-shopping-bag"></i></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-header">
                <div>
                    <div class="stat-value">
                        <?php echo number_format($stats['total_items'] ?? 0); ?>
                    </div>
                    <div class="stat-label">Productos Vendidos</div>
                </div>
                <div class="stat-icon" style="background: #a65c68;"><i class="fas fa-box"></i></div>
            </div>
        </div>
    </div>

    <!-- Charts Grid: Fila 1 -->
    <div class="charts-grid">

        <!-- Status Chart -->
        <div class="chart-card">
            <div class="chart-header">
                <h3 class="chart-title">Estado de Pedidos</h3>
            </div>
            <div style="height: 400px; position: relative;">
                <canvas id="statusChart" style="height: 400px;"></canvas>
            </div>
        </div>

        <!-- Ventas por Mes (Line Chart) -->
        <div class="chart-card">
            <div class="chart-header">
                <h3 class="chart-title">Ventas por Mes</h3>
            </div>
            <div style="height: 400px; position: relative;">
                <canvas id="salesMonthChart" style="height: 400px;"></canvas>
            </div>
        </div>

    </div>

    <!-- Charts Grid: Fila 2 -->
    <div class="charts-grid" style="margin-top: 2rem;">

        <!-- Top Products -->
        <div class="chart-card">
            <div class="chart-header">
                <h3 class="chart-title">Top 5 Productos Más Vendidos</h3>
            </div>
            <canvas id="productsChart" height="160"></canvas>
        </div>

        <!-- Vendedores por Universidad -->
        <div class="chart-card">
            <div class="chart-header">
                <h3 class="chart-title">Vendedores por Universidad</h3>
            </div>
            <canvas id="uniChart"></canvas>
        </div>

    </div>

</main>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="<?= BASE_URL ?>/src/JavaScript/dashboard.js"></script>
<script>
    window.ReportsData = {
        status: {
            labels: <?php echo json_encode($status_labels); ?>,
            data: <?php echo json_encode($status_data); ?>,
            colors: <?php echo json_encode($status_colors); ?>
        },
        products: <?php echo json_encode($top_products); ?>,
        salesMonth: <?php echo json_encode($sales_by_month); ?>,
        universities: <?php echo json_encode($sellers_by_uni); ?>
    };
</script>
<script src="<?= BASE_URL ?>/src/JavaScript/reports.js"></script>
<?php
require_once __DIR__ . '/includes/footer.php';
?>