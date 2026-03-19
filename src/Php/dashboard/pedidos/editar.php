<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../config/conexion.php';

// Get order ID
$order_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if (empty($order_id)) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get form data
        $nombre_cliente = trim($_POST['customer_name'] ?? '');
        $telefono_cliente = trim($_POST['customer_phone'] ?? '');
        $direccion_entrega = trim($_POST['delivery_address'] ?? '');
        $fecha_entrega = !empty($_POST['delivery_date']) ? $_POST['delivery_date'] : null;
        $estado_nuevo_str = $_POST['status'] ?? 'pending';
        $notas = trim($_POST['notes'] ?? '');
        $productos = $_POST['products'] ?? [];

        // Map status string to integer
        $estado_map = ['pending' => 0, 'processing' => 1, 'completed' => 2, 'cancelled' => 3];
        $estado_nuevo = $estado_map[$estado_nuevo_str] ?? 0;

        // Validate
        if (empty($telefono_cliente)) {
            throw new Exception('El teléfono del cliente es obligatorio');
        }

        if (empty($productos) || !is_array($productos)) {
            throw new Exception('Debe agregar al menos un producto');
        }

        // Parse array to json for PostgreSQL JSON array 
        $productos_json = json_encode(array_values($productos));

        // Connect to new secure SQL function
        $stmt_edit = $pdo->prepare("SELECT fun_editar_pedido(?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt_edit->execute([
            $order_id,
            $_SESSION['user_id'], // p_id_usuario_cambio
            $nombre_cliente,
            $telefono_cliente,
            $direccion_entrega,
            $fecha_entrega,
            $estado_nuevo,
            $notas,
            $productos_json
        ]);

        $resultado_json = $stmt_edit->fetchColumn();
        $resultado = json_decode($resultado_json, true);

        if (!$resultado || empty($resultado['success'])) {
            throw new Exception($resultado['message'] ?? 'Error interno al actualizar el pedido');
        }

        header("Location: ver.php?id=$order_id&success=1");
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction())
            $pdo->rollBack();
        $error = $e->getMessage();
    }
}

// Get order details
try {
    $stmt = $pdo->prepare("
        SELECT 
            o.id_pedido,
            o.id_cliente,
            o.estado,
            o.estado_pago,
            o.notas,
            o.fecha_creacion,
            o.fecha_actualizacion,
            c.nombre as customer_name,
            c.telefono as customer_phone,
            c.email as customer_email,
            ot.total as total_amount
        FROM tbl_pedido o
        LEFT JOIN tbl_cliente c ON o.id_cliente = c.id_cliente
        LEFT JOIN vw_totales_pedido ot ON o.id_pedido = ot.id_pedido
        WHERE o.id_pedido = ? AND o.estado_logico = 'activo'
    ");
    $stmt->execute([$id_pedido]);
    $order = $stmt->fetch();

    if (!$order) {
        header('Location: index.php');
        exit;
    }

    // Get order items
    $stmt = $pdo->prepare("
        SELECT dp.id_detalle_pedido, dp.id_producto, dp.cantidad, dp.precio_unitario, p.nombre_producto as product_name 
        FROM tbl_detalle_pedido dp
        JOIN tbl_producto p ON dp.id_producto = p.id_producto
        WHERE dp.id_pedido = ? AND dp.estado = 'activo'
        ORDER BY dp.id_detalle_pedido
    ");
    $stmt->execute([$id_pedido]);
    $items = $stmt->fetchAll();

    // Map status integer to string for display
    $status_str_map = [0 => 'pending', 1 => 'processing', 2 => 'completed', 3 => 'cancelled'];
    $order['status_str'] = $status_str_map[$order['estado'] ?? 0] ?? 'pending';

} catch (PDOException $e) {
    $error = "Error al cargar el pedido: " . $e->getMessage();
}

$page_title = 'Editar Pedido - Mai Shop';
$extra_css = [BASE_URL . '/styles/pedidos.css'];
require_once __DIR__ . '/../includes/head.php';
?>

<!-- Sidebar -->
<?php 
$base = '..';
include __DIR__ . '/../includes/sidebar.php'; 
?>

<!-- Main Content -->
<main class="main-content">
            <div class="form-container">
                <h1 class="orders-title" style="margin-bottom: var(--spacing-md);">
                    <i class="fas fa-edit"></i> Editar Pedido:
                    #<?php echo str_pad($order['id_pedido'], 4, '0', STR_PAD_LEFT); ?>
                </h1>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-triangle"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="editar.php?id=<?php echo $order_id; ?>" id="orderForm">
                    <input type="hidden" name="old_status" value="<?php echo htmlspecialchars($order['estado']); ?>">
                    <input type="hidden" name="customer_id" value="<?php echo $order['id_cliente']; ?>">

                    <!-- Customer Section -->
                    <div class="form-section">
                        <h2 class="section-title">
                            <i class="fas fa-user"></i> Información del Cliente
                        </h2>

                        <div class="form-grid">
                            <div>
                                <label class="form-label required">Nombre del Cliente (Ref)</label>
                                <input type="text" name="customer_name" class="form-input"
                                    value="<?php echo htmlspecialchars($order['customer_name'] ?? ''); ?>" required>
                            </div>

                            <div>
                                <label class="form-label required">Teléfono de Contacto</label>
                                <input type="tel" name="customer_phone" class="form-input"
                                    value="<?php echo htmlspecialchars($order['telefono_contacto'] ?? ''); ?>" required
                                    maxlength="10">
                            </div>

                            <div>
                                <label class="form-label required">Dirección de Entrega</label>
                                <input type="text" name="delivery_address" class="form-input"
                                    value="<?php echo htmlspecialchars($order['direccion_entrega'] ?? ''); ?>" required>
                            </div>

                            <div>
                                <label class="form-label required">Fecha de Entrega</label>
                                <input type="date" name="delivery_date" class="form-input"
                                    value="<?php echo date('Y-m-d', strtotime($order['fecha_entrega'] ?? 'now')); ?>"
                                    required>
                            </div>
                        </div>
                    </div>

                    <!-- Products Section -->
                    <div class="form-section">
                        <h2 class="section-title">
                            <i class="fas fa-cookie-bite"></i> Productos
                        </h2>

                        <table class="products-table" id="productsTable">
                            <thead>
                                <tr>
                                    <th>Producto</th>
                                    <th style="width: 100px;">Cantidad</th>
                                    <th style="width: 120px;">Precio Unit.</th>
                                    <th style="width: 120px;">Subtotal</th>
                                    <th style="width: 60px;"></th>
                                </tr>
                            </thead>
                            <tbody id="productsBody">
                                <?php foreach ($items as $index => $item): ?>
                                    <tr class="product-row">
                                        <td><input type="text" name="products[<?php echo $index; ?>][name]"
                                                class="product-name"
                                                value="<?php echo htmlspecialchars($item['product_name']); ?>" required>
                                        </td>
                                        <td><input type="number" name="products[<?php echo $index; ?>][quantity]"
                                                class="product-quantity" min="1" value="<?php echo $item['quantity']; ?>"
                                                required></td>
                                        <td><input type="number" name="products[<?php echo $index; ?>][price]"
                                                class="product-price" min="0" step="1000"
                                                value="<?php echo $item['unit_price']; ?>" required></td>
                                        <td><input type="text" class="product-subtotal" readonly
                                                value="$<?php echo number_format($item['cantidad'] * $item['precio_unitario'], 0, ',', '.'); ?>">
                                        </td>
                                        <td>
                                            <?php if ($index > 0): ?>
                                                <button type="button" class="btn-remove-product"><i
                                                        class="fas fa-trash"></i></button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <button type="button" class="btn-add-product" id="addProductBtn">
                            <i class="fas fa-plus"></i> Agregar Producto
                        </button>

                        <div class="total-display">
                            Total: <span id="totalAmount">$
                                <?php echo number_format($order['total_amount'], 0, ',', '.'); ?>
                            </span>
                        </div>
                    </div>

                    <!-- Order Details Section -->
                    <div class="form-section">
                        <h2 class="section-title">
                            <i class="fas fa-info-circle"></i> Detalles del Pedido
                        </h2>

                        <div class="form-grid">
                            <div>
                                <label class="form-label">Estado</label>
                                <select name="status" class="form-select">
                                    <option value="pending" <?php echo $order['status_str'] === 'pending' ? 'selected' : ''; ?>>Pendiente</option>
                                    <option value="completed" <?php echo $order['status_str'] === 'completed' ? 'selected' : ''; ?>>Completado</option>
                                    <option value="cancelled" <?php echo $order['status_str'] === 'cancelled' ? 'selected' : ''; ?>>Cancelado</option>
                                </select>
                            </div>

                            <div class="form-group-full">
                                <label class="form-label">Notas</label>
                                <textarea name="notes" class="form-textarea"
                                    placeholder="Notas adicionales sobre el pedido..."><?php echo htmlspecialchars($order['notes'] ?? ''); ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="form-actions">
                        <a href="ver.php?id=<?php echo $order_id; ?>" class="btn-cancel">
                            <i class="fas fa-times"></i> Cancelar
                        </a>
                        <button type="submit" class="btn-submit">
                            <i class="fas fa-save"></i> Guardar Cambios
                        </button>
                    </div>
                </form>
            </div>
<script>
    window.ProductsData = {
        initialIndex: <?php echo count($items); ?>
    }
</script>
<?php
$extra_scripts = [BASE_URL . '/src/JavaScript/pedidos_form.js'];
require_once __DIR__ . '/../includes/footer.php';
?>