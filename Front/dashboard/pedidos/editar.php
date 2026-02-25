<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../conexion.php';

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
        $pdo->beginTransaction();

        // Get form data
        $nombre_cliente = trim($_POST['customer_name'] ?? '');
        $telefono_cliente = trim($_POST['customer_phone'] ?? '');
        $direccion_entrega = trim($_POST['delivery_address'] ?? '');
        $fecha_entrega = $_POST['delivery_date'] ?? null;
        $estado_anterior = (int) ($_POST['old_status'] ?? 0);
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

        if (empty($productos)) {
            throw new Exception('Debe agregar al menos un producto');
        }

        // Update order
        $stmt = $pdo->prepare("
            UPDATE tbl_pedido 
            SET estado = ?, notas = ?, fecha_actualizacion = NOW(), 
                telefono_contacto = ?, direccion_entrega = ?, fecha_entrega = COALESCE(?, fecha_entrega)
            WHERE id_pedido = ?
        ");
        $stmt->execute([$estado_nuevo, $notas, $telefono_cliente, $direccion_entrega, $fecha_entrega, $order_id]);

        // Marcar detalles anteriores como inactivos (Eliminación lógica)
        $stmt = $pdo->prepare("UPDATE tbl_detalle_pedido SET estado = 'inactivo' WHERE id_pedido = ?");
        $stmt->execute([$order_id]);

        // Create new order items
        $stmt_detail = $pdo->prepare("
            INSERT INTO tbl_detalle_pedido (id_detalle_pedido, id_pedido, id_producto, cantidad, precio_unitario)
            VALUES (?, ?, ?, ?, ?)
        ");

        foreach ($productos as $product) {
            // Find or create product
            $stmt_prod = $pdo->prepare("SELECT id_producto FROM tbl_producto WHERE nombre_producto = ? LIMIT 1");
            $stmt_prod->execute([$product['name']]);
            $id_producto = $stmt_prod->fetchColumn();

            if (!$id_producto) {
                // If product doesn't exist, create it with default stock 0
                $next_prod_id = $pdo->query("SELECT COALESCE(MAX(id_producto), 0) + 1")->fetchColumn();
                $stmt_new_prod = $pdo->prepare("INSERT INTO tbl_producto (id_producto, nombre_producto, precio, stock, estado) VALUES (?, ?, ?, 0, 'activo')");
                $stmt_new_prod->execute([$next_prod_id, $product['name'], $product['price']]);
                $id_producto = $next_prod_id;
            }

            if ($id_producto && !empty($product['quantity']) && !empty($product['price'])) {
                $next_detail_id = $pdo->query("SELECT COALESCE(MAX(id_detalle_pedido), 0) + 1")->fetchColumn();
                $stmt_detail->execute([
                    $next_detail_id,
                    $order_id,
                    $id_producto,
                    $product['quantity'],
                    $product['price']
                ]);
            }
        }

        // Add history entry if status changed or data updated
        $next_historial_id = $pdo->query("SELECT COALESCE(MAX(id_historial), 0) + 1")->fetchColumn();
        $log = $pdo->prepare("INSERT INTO tbl_historial_pedido (id_historial, id_pedido, usuario_cambio, estado_anterior, estado_nuevo, motivo) VALUES (?, ?, ?, ?, ?, ?)");
        $log->execute([
            $next_historial_id,
            $order_id,
            $_SESSION['user_id'],
            $estado_anterior,
            $estado_nuevo,
            'Detalles del pedido editados desde el panel de administración'
        ]);

        $pdo->commit();

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
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Pedido - Mai Shop</title>

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
    <link rel="stylesheet" href="pedidos.css">

    <style>
        /* Reuse styles from nuevo.php */
        .form-container {
            background: var(--white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            padding: var(--spacing-lg);
            margin-bottom: var(--spacing-md);
        }

        .form-section {
            margin-bottom: var(--spacing-lg);
            padding-bottom: var(--spacing-lg);
            border-bottom: 2px solid var(--accent-color);
        }

        .form-section:last-child {
            border-bottom: none;
        }

        .section-title {
            font-family: var(--font-heading);
            font-size: 1.3rem;
            color: var(--dark);
            margin-bottom: var(--spacing-md);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: var(--spacing-md);
        }

        .form-group-full {
            grid-column: 1 / -1;
        }

        .form-label {
            display: block;
            font-weight: 500;
            color: var(--gray-dark);
            margin-bottom: 0.5rem;
        }

        .form-label.required::after {
            content: ' *';
            color: #ff6b9d;
        }

        .form-input,
        .form-select,
        .form-textarea {
            width: 100%;
            padding: 0.8rem;
            border: 2px solid var(--gray-light);
            border-radius: var(--radius-md);
            font-family: var(--font-body);
            font-size: 0.95rem;
            transition: all var(--transition-fast);
        }

        .form-input:focus,
        .form-select:focus,
        .form-textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(201, 124, 137, 0.1);
        }

        .form-textarea {
            resize: vertical;
            min-height: 100px;
        }

        .products-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: var(--spacing-sm);
        }

        .products-table th {
            background: var(--accent-color);
            padding: 0.8rem;
            text-align: left;
            font-weight: 600;
            color: var(--dark);
        }

        .products-table td {
            padding: 0.8rem;
            border-bottom: 1px solid var(--gray-light);
        }

        .products-table input {
            width: 100%;
            padding: 0.6rem;
            border: 2px solid var(--gray-light);
            border-radius: var(--radius-sm);
        }

        .btn-add-product,
        .btn-remove-product {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: var(--radius-sm);
            cursor: pointer;
            font-family: var(--font-body);
            font-weight: 500;
            transition: all var(--transition-fast);
        }

        .btn-add-product {
            background: var(--gradient-primary);
            color: var(--white);
            margin-top: var(--spacing-sm);
        }

        .btn-add-product:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .btn-remove-product {
            background: rgba(255, 107, 157, 0.1);
            color: #ff6b9d;
        }

        .btn-remove-product:hover {
            background: #ff6b9d;
            color: var(--white);
        }

        .total-display {
            text-align: right;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-top: var(--spacing-md);
        }

        .form-actions {
            display: flex;
            gap: var(--spacing-md);
            justify-content: flex-end;
            margin-top: var(--spacing-lg);
        }

        .btn-submit,
        .btn-cancel {
            padding: 1rem 2rem;
            border: none;
            border-radius: var(--radius-md);
            font-family: var(--font-body);
            font-weight: 600;
            cursor: pointer;
            transition: all var(--transition-normal);
            text-decoration: none;
            display: inline-block;
        }

        .btn-submit {
            background: var(--gradient-primary);
            color: var(--white);
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .btn-cancel {
            background: var(--gray-light);
            color: var(--gray-dark);
        }

        .btn-cancel:hover {
            background: var(--gray);
            color: var(--white);
        }

        .alert {
            padding: 1rem;
            border-radius: var(--radius-md);
            margin-bottom: var(--spacing-md);
        }

        .alert-error {
            background: rgba(255, 107, 157, 0.1);
            color: #ff6b9d;
            border: 2px solid #ff6b9d;
        }
    </style>
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
        </main>
    </div>

    <script src="../dashboard.js"></script>
    <script>
        // Product management (same as nuevo.php)
        let productIndex = <?php echo count($items); ?>;

        document.getElementById('addProductBtn').addEventListener('click', function () {
            const tbody = document.getElementById('productsBody');
            const row = document.createElement('tr');
            row.className = 'product-row';
            row.innerHTML = `
                <td><input type="text" name="products[${productIndex}][name]" class="product-name" placeholder="Nombre del producto" required></td>
                <td><input type="number" name="products[${productIndex}][quantity]" class="product-quantity" min="1" value="1" required></td>
                <td><input type="number" name="products[${productIndex}][price]" class="product-price" min="0" step="1000" placeholder="0" required></td>
                <td><input type="text" class="product-subtotal" readonly value="$0"></td>
                <td><button type="button" class="btn-remove-product"><i class="fas fa-trash"></i></button></td>
            `;
            tbody.appendChild(row);
            productIndex++;
            attachProductListeners(row);
        });

        function attachProductListeners(row) {
            const quantityInput = row.querySelector('.product-quantity');
            const priceInput = row.querySelector('.product-price');
            const subtotalInput = row.querySelector('.product-subtotal');
            const removeBtn = row.querySelector('.btn-remove-product');

            function updateSubtotal() {
                const quantity = parseFloat(quantityInput.value) || 0;
                const price = parseFloat(priceInput.value) || 0;
                const subtotal = quantity * price;
                subtotalInput.value = '$' + subtotal.toLocaleString('es-CO');
                updateTotal();
            }

            quantityInput.addEventListener('input', updateSubtotal);
            priceInput.addEventListener('input', updateSubtotal);

            if (removeBtn) {
                removeBtn.addEventListener('click', function () {
                    row.remove();
                    updateTotal();
                });
            }
        }

        function updateTotal() {
            let total = 0;
            document.querySelectorAll('.product-row').forEach(row => {
                const quantity = parseFloat(row.querySelector('.product-quantity').value) || 0;
                const price = parseFloat(row.querySelector('.product-price').value) || 0;
                total += quantity * price;
            });
            document.getElementById('totalAmount').textContent = '$' + total.toLocaleString('es-CO');
        }

        // Attach listeners to existing rows
        document.querySelectorAll('.product-row').forEach(attachProductListeners);
    </script>
</body>

</html>