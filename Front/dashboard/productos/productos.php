<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../conexion.php';

// Pagination settings
$records_per_page = 12; // 12 products per page (3x4 grid)
$current_page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$offset = ($current_page - 1) * $records_per_page;

// Filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$estado_filter = isset($_GET['estado']) ? $_GET['estado'] : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'name_asc';

// Build WHERE clause
$where_conditions = [];
$params = [];

if (empty($estado_filter)) {
    $where_conditions[] = "estado = 'activo'";
}

if (!empty($search)) {
    $where_conditions[] = "(nombre_producto LIKE ? OR descripcion LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($estado_filter)) {
    $where_conditions[] = "estado = ?";
    $params[] = $estado_filter;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Determine ORDER BY clause
$order_by = "nombre_producto ASC";
switch ($sort) {
    case 'name_desc':
        $order_by = "nombre_producto DESC";
        break;
    case 'price_asc':
        $order_by = "precio ASC";
        break;
    case 'price_desc':
        $order_by = "precio DESC";
        break;
    case 'newest':
        $order_by = "fecha_creacion DESC";
        break;
}

// Get total count for pagination
try {
    $count_stmt = $pdo->prepare("SELECT COUNT(*) as total FROM tbl_producto $where_clause");
    $count_stmt->execute($params);
    $total_records = $count_stmt->fetch()['total'];
    $total_pages = ceil($total_records / $records_per_page);
} catch (PDOException $e) {
    $total_records = 0;
    $total_pages = 0;
}

// Get products with pagination
$query = "
    SELECT 
        id_producto,
        nombre_producto as name,
        descripcion as short_description,
        precio as price,
        stock,
        estado
    FROM tbl_producto
    $where_clause
    ORDER BY $order_by
    LIMIT $records_per_page OFFSET $offset
";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $products = $stmt->fetchAll();
} catch (PDOException $e) {
    $products = [];
    $error_message = "Error al cargar productos: " . $e->getMessage();
}

$categories = []; // Placeholder si no hay tabla de categorías vinculada aún
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Productos - Mai Shop</title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700;800&family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">

    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- Dashboard Styles -->
    <link rel="stylesheet" href="../dashboard.css">
    <link rel="stylesheet" href="productos.css">
</head>

<body>
    <!-- Mobile Menu Toggle -->
    <button class="menu-toggle" id="menuToggle">
        <i class="fas fa-bars"></i>
    </button>

    <div class="dashboard-container">
        <!-- Sidebar -->
        <?php $base = '..';
        include __DIR__ . '/../includes/sidebar.php'; ?>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <div class="products-header">
                <div class="header-left">
                    <h1>Catálogo de Productos</h1>
                    <p>Gestiona tus productos de panadería</p>
                </div>
                <a href="nuevo.php" class="btn-new-product">
                    <i class="fas fa-plus"></i> Nuevo Producto
                </a>
            </div>

            <!-- Filter Bar -->
            <div class="filter-bar">
                <!-- Search and Sort -->
                <form method="GET" action="productos.php" class="search-sort-bar">
                    <div class="search-group">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" id="searchInput" placeholder="Buscar productos..."
                            value="<?php echo htmlspecialchars($search); ?>">
                    </div>

                    <select name="estado" class="sort-select" onchange="this.form.submit()">
                        <option value="">Todos los estados</option>
                        <option value="activo" <?php echo $estado_filter === 'activo' ? 'selected' : ''; ?>>Activos
                        </option>
                        <option value="inactivo" <?php echo $estado_filter === 'inactivo' ? 'selected' : ''; ?>>Inactivos
                        </option>
                    </select>

                    <select name="sort" class="sort-select" onchange="this.form.submit()">
                        <option value="name_asc" <?php echo $sort === 'name_asc' ? 'selected' : ''; ?>>Nombre (A-Z)
                        </option>
                        <option value="name_desc" <?php echo $sort === 'name_desc' ? 'selected' : ''; ?>>Nombre (Z-A)
                        </option>
                        <option value="price_asc" <?php echo $sort === 'price_asc' ? 'selected' : ''; ?>>Precio (Menor)
                        </option>
                        <option value="price_desc" <?php echo $sort === 'price_desc' ? 'selected' : ''; ?>>Precio (Mayor)
                        </option>
                        <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Más Recientes</option>
                    </select>

                    <?php if (!empty($search) || !empty($estado_filter)): ?>
                        <a href="productos.php" class="btn-clear-filters">
                            <i class="fas fa-times"></i> Limpiar
                        </a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Products Grid -->
            <?php if (isset($error_message)): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <?php if (empty($products)): ?>
                <div class="empty-state">
                    <i class="fas fa-cookie-bite"></i>
                    <h3>No hay productos</h3>
                    <p>No se encontraron productos con los filtros seleccionados.</p>
                    <a href="nuevo.php" class="btn-new-product">
                        <i class="fas fa-plus"></i> Crear Primer Producto
                    </a>
                </div>
            <?php else: ?>
                <div class="products-grid">
                    <?php foreach ($products as $product): ?>
                        <div class="product-card">
                            <!-- Product Image -->
                            <div class="product-image-container">
                                <?php if (!empty($product['main_image'])): ?>
                                    <img src="../../uploads/productos/<?php echo htmlspecialchars($product['main_image']); ?>"
                                        alt="<?php echo htmlspecialchars($product['name']); ?>" class="product-image">
                                <?php else: ?>
                                    <div class="product-image-placeholder">
                                        <i class="fas fa-image"></i>
                                    </div>
                                <?php endif; ?>

                                <!-- Badges -->
                                <div class="product-badges">
                                    <span class="badge"
                                        style="background: <?php echo $product['estado'] === 'activo' ? '#4CAF50' : '#F44336'; ?>;">
                                        <?php echo ucfirst($product['estado']); ?>
                                    </span>
                                    <?php if ($product['stock'] <= 0): ?>
                                        <span class="badge badge-out-of-stock">Agotado</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Product Info -->
                            <div class="product-info">
                                <!-- Category -->
                                <?php if (!empty($product['category_name'])): ?>
                                    <div class="product-category" style="color: <?php echo $product['category_color']; ?>">
                                        <i class="fas <?php echo $product['category_icon']; ?>"></i>
                                        <span>
                                            <?php echo htmlspecialchars($product['category_name']); ?>
                                        </span>
                                    </div>
                                <?php endif; ?>

                                <!-- Name -->
                                <h3 class="product-name">
                                    <?php echo htmlspecialchars($product['name']); ?>
                                </h3>

                                <!-- Description -->
                                <?php if (!empty($product['short_description'])): ?>
                                    <p class="product-description">
                                        <?php echo htmlspecialchars(substr($product['short_description'], 0, 80)) . (strlen($product['short_description']) > 80 ? '...' : ''); ?>
                                    </p>
                                <?php endif; ?>

                                <!-- Price -->
                                <div class="product-price">
                                    $ <?php echo number_format($product['price'], 0, ',', '.'); ?>
                                    <span style="font-size: 0.8rem; color: var(--gray-500); margin-left: 0.5rem;">
                                        (Stock: <?php echo $product['stock']; ?>)
                                    </span>
                                </div>

                                <!-- Actions -->
                                <div class="product-actions">
                                    <a href="editar.php?id=<?php echo $product['id_producto']; ?>" class="btn-action edit"
                                        title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button class="btn-action delete btn-delete"
                                        data-product-id="<?php echo $product['id_producto']; ?>"
                                        data-product-name="<?php echo htmlspecialchars($product['name']); ?>" title="Eliminar">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <div class="pagination-info">
                            Mostrando
                            <?php echo min($offset + 1, $total_records); ?> -
                            <?php echo min($offset + $records_per_page, $total_records); ?> de
                            <?php echo $total_records; ?> productos
                        </div>
                        <div class="pagination-buttons">
                            <?php if ($current_page > 1): ?>
                                <a href="?page=<?php echo $current_page - 1; ?><?php echo !empty($category_filter) ? '&category=' . $category_filter : ''; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($sort) ? '&sort=' . $sort : ''; ?>"
                                    class="btn-page">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            <?php endif; ?>

                            <?php for ($i = max(1, $current_page - 2); $i <= min($total_pages, $current_page + 2); $i++): ?>
                                <a href="?page=<?php echo $i; ?><?php echo !empty($category_filter) ? '&category=' . $category_filter : ''; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($sort) ? '&sort=' . $sort : ''; ?>"
                                    class="btn-page <?php echo $i === $current_page ? 'active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>

                            <?php if ($current_page < $total_pages): ?>
                                <a href="?page=<?php echo $current_page + 1; ?><?php echo !empty($category_filter) ? '&category=' . $category_filter : ''; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($sort) ? '&sort=' . $sort : ''; ?>"
                                    class="btn-page">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </main>
    </div>

    <!-- Confirmation Modal -->
    <div class="modal-overlay" id="confirmModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="modalTitle">Confirmar Acción</h3>
                <button class="modal-close" id="modalClose">&times;</button>
            </div>
            <div class="modal-body">
                <p id="modalMessage">¿Estás seguro de realizar esta acción?</p>
            </div>
            <div class="modal-footer">
                <button class="btn-modal cancel" id="modalCancel">Cancelar</button>
                <button class="btn-modal confirm" id="modalConfirm">Confirmar</button>
            </div>
        </div>
    </div>

    <script src="../dashboard.js"></script>
    <script src="productos.js"></script>
</body>

</html>