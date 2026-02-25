<?php
require_once __DIR__ . '/seller_auth.php';

// Configurar zona horaria
date_default_timezone_set('America/Bogota');

// Obtener productos activos con stock
try {
    $products_query = "SELECT id_producto, nombre_producto, precio, stock FROM tbl_producto WHERE stock > 0 AND estado = 'activo' ORDER BY nombre_producto";
    $products_stmt = $pdo->query($products_query);
    $products = $products_stmt->fetchAll();
} catch (PDOException $e) {
    $products = [];
}

// Procesar formulario - Ahora se maneja en acciones.php
$success_message = $_SESSION['success'] ?? null;
$error_message = $_SESSION['error'] ?? null;
unset($_SESSION['success'], $_SESSION['error']);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuevo Pedido - Mai Shop</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700;800&family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="seller.css">
    <style>
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .form-label {
            font-weight: 600;
            color: var(--gray-700);
            font-size: 0.875rem;
        }

        .form-input,
        .form-select,
        .form-textarea {
            padding: 0.75rem 1rem;
            border: 2px solid var(--gray-200);
            border-radius: 12px;
            font-size: 0.9375rem;
            font-family: 'Poppins', sans-serif;
            transition: all 0.3s ease;
        }

        .form-input:focus,
        .form-select:focus,
        .form-textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(255, 107, 157, 0.1);
        }

        .form-textarea {
            resize: vertical;
            min-height: 100px;
        }

        .product-selector {
            background: var(--gray-50);
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
        }

        .product-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            background: white;
            border-radius: 12px;
            margin-bottom: 0.75rem;
        }

        .product-item-name {
            flex: 1;
            font-weight: 500;
        }

        .product-item-price {
            color: var(--primary);
            font-weight: 600;
        }

        .quantity-input {
            width: 80px;
            text-align: center;
        }

        .commission-preview {
            background: linear-gradient(135deg, #4CAF50 0%, #388E3C 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 16px;
            margin-bottom: 1.5rem;
        }

        .commission-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .alert {
            padding: 1rem 1.5rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .alert-success {
            background: #e6f9f0;
            color: #22543d;
        }

        .alert-error {
            background: #ffe6e6;
            color: #c53030;
        }
    </style>
</head>

<body>
    <button class="menu-toggle" id="menuToggle">
        <i class="fas fa-bars"></i>
    </button>

    <div class="dashboard-container">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <main class="main-content">
            <div class="page-header">
                <h1>Crear Nuevo Pedido</h1>
                <p>Registra una nueva venta y gana comisiones</p>

                <?php if ($success_message): ?>
                    <div style="margin-bottom: 1.5rem; font-weight: 500; color: #22543d;">
                        <?php echo $success_message; ?>
                    </div>
                <?php endif; ?>

                <?php if ($error_message): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-triangle"></i>
                        <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="acciones.php" id="orderForm" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="crear_pedido">
                    <!-- Client Information -->
                    <div class="content-card">
                        <div class="card-header">
                            <h3 class="card-title">Datos de Entrega</h3>
                        </div>

                        <div class="form-grid">


                            <div class="form-group">
                                <label class="form-label">Teléfono / Contacto *</label>
                                <input type="tel" name="client_phone" class="form-input" required maxlength="10"
                                    minlength="10" pattern="\d{10}"
                                    oninput="this.value = this.value.replace(/[^0-9]/g, '');"
                                    title="Debe tener exactamente 10 dígitos numéricos" placeholder="Ej: 3001234567">
                            </div>

                            <div class="form-group">
                                <label class="form-label">Fecha de Entrega</label>
                                <input type="date" name="delivery_date" class="form-input">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Dirección de Entrega</label>
                            <input type="text" name="client_address" class="form-input">
                        </div>

                    </div>

                    <!-- Products Selection -->
                    <div class="content-card">
                        <div class="card-header">
                            <h3 class="card-title">Seleccionar Productos</h3>
                        </div>

                        <div class="product-selector" id="productSelector">
                            <?php foreach ($products as $product): ?>
                                <div class="product-item">
                                    <div class="product-item-name">
                                        <i class="fas fa-cookie-bite" style="color: var(--primary);"></i>
                                        <?php echo htmlspecialchars($product['nombre_producto']); ?>
                                        <span style="font-size: 0.75rem; color: var(--gray-500);">
                                            (Stock:
                                            <?php echo $product['stock']; ?>)
                                        </span>
                                    </div>
                                    <div class="product-item-price">
                                        $
                                        <?php echo number_format($product['precio'], 0, ',', '.'); ?>
                                    </div>
                                    <input type="number" name="products[<?php echo $product['id_producto']; ?>]"
                                        class="form-input quantity-input product-quantity" min="0" step="1"
                                        max="<?php echo $product['stock']; ?>" value="0"
                                        onkeypress="return event.charCode >= 48 && event.charCode <= 57"
                                        data-price="<?php echo $product['precio']; ?>">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Commission Preview -->
                    <div class="commission-preview">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <div style="font-size: 0.875rem; opacity: 0.9; margin-bottom: 0.25rem;">
                                    Total del Pedido
                                </div>
                                <div class="commission-value" id="orderTotal">$0</div>
                            </div>
                            <div style="text-align: right;">
                                <div style="font-size: 0.875rem; opacity: 0.9; margin-bottom: 0.25rem;">
                                    Tu Comisión (
                                    <?php echo number_format($_SESSION['commission_percentage'], 1); ?>%)
                                </div>
                                <div class="commission-value" id="commissionAmount">$0</div>
                            </div>
                        </div>
                    </div>

                    <!-- Notes -->
                    <div class="content-card">
                        <div class="form-group">
                            <label class="form-label">Notas Adicionales</label>
                            <textarea name="notes" class="form-textarea"
                                placeholder="Instrucciones especiales, detalles del pedido, etc."></textarea>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div style="display: flex; gap: 1rem;">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-check"></i> Crear Pedido
                        </button>
                        <a href="seller_dash.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancelar
                        </a>
                    </div>
                </form>
        </main>
    </div>

    <script src="seller.js"></script>
    <script>
        // Establecer fecha mínima de entrega dinámicamente
        const deliveryDateInput = document.getElementsByName('delivery_date')[0];
        if (deliveryDateInput) {
            let minDate = new Date();
            minDate.setDate(minDate.getDate() + 2);
            const year = minDate.getFullYear();
            const month = String(minDate.getMonth() + 1).padStart(2, '0');
            const day = String(minDate.getDate()).padStart(2, '0');
            let formattedDate = `${year}-${month}-${day}`;
            deliveryDateInput.min = formattedDate;
        }

        // Calculate totals in real-time
        const quantityInputs = document.querySelectorAll('.product-quantity');
        const orderTotalEl = document.getElementById('orderTotal');
        const commissionEl = document.getElementById('commissionAmount');
        const commissionPercentage = <?php echo $_SESSION['commission_percentage']; ?>;

        function calculateTotals() {
            let total = 0;
            quantityInputs.forEach(input => {
                const quantity = parseInt(input.value) || 0;
                const price = parseFloat(input.dataset.price) || 0;
                total += quantity * price;
            });

            const commission = total * (commissionPercentage / 100);

            orderTotalEl.textContent = '$' + total.toLocaleString('es-CO');
            commissionEl.textContent = '$' + Math.round(commission).toLocaleString('es-CO');
        }

        quantityInputs.forEach(input => {
            input.addEventListener('input', function () {
                // Forzar a 0 si es negativo
                if (this.value < 0) this.value = 0;
                // Forzar a entero
                if (this.value.includes('.')) this.value = Math.floor(this.value);

                calculateTotals();
            });

            // Bloquear caracteres no numéricos extra (por si acaso)
            input.addEventListener('keydown', function (e) {
                if (['-', '+', 'e', 'E', '.', ','].includes(e.key)) {
                    e.preventDefault();
                }
            });
        });

        // Form validation
        document.getElementById('orderForm').addEventListener('submit', function (e) {
            let hasProducts = false;
            quantityInputs.forEach(input => {
                if (parseInt(input.value) > 0) {
                    hasProducts = true;
                }
            });

            if (!hasProducts) {
                e.preventDefault();
                MaiModal.alert({
                    title: 'Pedido Incompleto',
                    message: 'Debes agregar al menos un producto al pedido para continuar.',
                    type: 'danger'
                });
            }
        });
    </script>
</body>

</html>