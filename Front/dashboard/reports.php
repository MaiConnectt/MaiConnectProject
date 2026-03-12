<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../conexion.php';

// --- FUNCIONES DE AYUDA ---

function getSalesStats($pdo)
{
    // Ventas totales (Solo completadas: estado = 2)
    $sql_sales = "SELECT COALESCE(SUM(ot.total), 0) as total_sales 
                  FROM tbl_pedido o 
                  JOIN vw_totales_pedido ot ON o.id_pedido = ot.id_pedido 
                  WHERE o.estado = 2";
    $total_sales = $pdo->query($sql_sales)->fetchColumn();

    // Total Pedidos (Todos)
    $sql_orders = "SELECT COUNT(*) FROM tbl_pedido";
    $total_orders = $pdo->query($sql_orders)->fetchColumn();

    // Productos Vendidos
    $sql_items = "SELECT COALESCE(SUM(cantidad), 0) 
                  FROM tbl_detalle_pedido od 
                  JOIN tbl_pedido o ON od.id_pedido = o.id_pedido 
                  WHERE o.estado = 2";
    $total_items = $pdo->query($sql_items)->fetchColumn();

    return [
        'total_sales' => $total_sales,
        'total_orders' => $total_orders,
        'total_items' => $total_items
    ];
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
$status_labels = [];
$status_data = [];
$status_colors = [];
foreach ($orders_status as $row) {
    $status_labels[] = $status_map[$row['status']] ?? 'Desconocido';
    $status_data[] = $row['count'];
    // Colores: Pendiente (Amarillo), Proceso (Azul), Completado (Verde)
    if ($row['status'] == 0)
        $status_colors[] = '#e6c86e';
    elseif ($row['status'] == 1)
        $status_colors[] = '#74ebd5';
    elseif ($row['status'] == 2)
        $status_colors[] = '#20ba5a';
    else
        $status_colors[] = '#cbd5e0';
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes - Mai Shop</title>
    <link
        href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600&family=Poppins:wght@300;400;500;600&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="dashboard.css">
    <style>
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }

        .chart-card {
            background: var(--white);
            padding: 1.5rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
        }

        .chart-header {
            margin-bottom: 1rem;
            border-bottom: 1px solid var(--gray-light);
            padding-bottom: 0.5rem;
        }

        .chart-title {
            font-family: var(--font-heading);
            font-size: 1.2rem;
            color: var(--dark);
        }

        @media (max-width: 768px) {
            .charts-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <button class="menu-toggle" id="menuToggle"><i class="fas fa-bars"></i></button>

    <div class="dashboard-container">
        <!-- Sidebar -->
        <?php $base = '.';
        include __DIR__ . '/includes/sidebar.php'; ?>
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
                            <div class="stat-value">$
                                <?php echo number_format($stats['total_sales'] ?? 0, 0, ',', '.'); ?>
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
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="dashboard.js"></script>
    <script>
        // Configuración común
        Chart.defaults.font.family = "'Poppins', sans-serif";
        Chart.defaults.color = '#6e5c5f';


        // 2. Estado de Pedidos
        new Chart(document.getElementById('statusChart'), {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($status_labels); ?>,
                datasets: [{
                    data: <?php echo json_encode($status_data); ?>,
                    backgroundColor: <?php echo json_encode($status_colors); ?>,
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });

        // 3. Top Productos
        const productsData = <?php echo json_encode($top_products); ?>;
        new Chart(document.getElementById('productsChart'), {
            type: 'bar',
            data: {
                labels: productsData.map(p => p.name),
                datasets: [{
                    label: 'Unidades Vendidas',
                    data: productsData.map(p => p.total_sold),
                    backgroundColor: '#e6c86e',
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                indexAxis: 'y',
                plugins: {
                    legend: { display: false }
                }
            }
        });

        // 4. Ventas por Mes (Línea)
        const salesMonthData = <?php echo json_encode($sales_by_month); ?>;
        new Chart(document.getElementById('salesMonthChart'), {
            type: 'line',
            data: {
                labels: salesMonthData.map(r => r.mes_label),
                datasets: [{
                    label: 'Ventas',
                    data: salesMonthData.map(r => parseFloat(r.total_ventas)),
                    borderColor: '#a65c68',
                    backgroundColor: 'rgba(166, 92, 104, 0.1)',
                    borderWidth: 2,
                    tension: 0.4,
                    fill: true,
                    pointBackgroundColor: '#a65c68',
                    pointRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: val => '$' + val.toLocaleString('es-CO')
                        }
                    }
                }
            }
        });

        // 5. Vendedores por Universidad (Barras)
        const uniData = <?php echo json_encode($sellers_by_uni); ?>;
        new Chart(document.getElementById('uniChart'), {
            type: 'bar',
            data: {
                labels: uniData.map(r => r.universidad),
                datasets: [{
                    label: 'Vendedores',
                    data: uniData.map(r => parseInt(r.total_vendedores)),
                    backgroundColor: 'rgba(255, 107, 107, 0.75)',
                    borderColor: 'rgba(255, 107, 107, 1)',
                    borderWidth: 1,
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: { beginAtZero: true, ticks: { stepSize: 1 } }
                },
                plugins: { legend: { display: false } }
            }
        });
    </script>
</body>

</html>