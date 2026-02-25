<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../conexion.php';

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    header('Location: productos.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM tbl_producto WHERE id_producto = ?");
$stmt->execute([$id]);
$product = $stmt->fetch();

if (!$product) {
    header('Location: productos.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Producto - Mai Shop</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../dashboard.css">
    <link rel="stylesheet" href="productos.css">
    <style>
        .form-container {
            max-width: 700px;
            margin: 2rem auto;
            background: white;
            padding: 2rem;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
        }

        .form-title {
            margin-bottom: 2rem;
            color: var(--gray-800);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--gray-700);
        }

        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid var(--gray-200);
            border-radius: 10px;
            font-family: inherit;
            transition: border-color 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
        }

        .btn-submit {
            background: var(--gradient-primary);
            color: white;
            border: none;
            padding: 1rem 2rem;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
            margin-top: 1rem;
            box-shadow: var(--shadow-sm);
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
            opacity: 1;
        }

        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            color: var(--gray-600);
            font-weight: 500;
            margin-bottom: 1.5rem;
        }
    </style>
</head>

<body>
    <div class="dashboard-container">
        <?php $base = '..';
        include __DIR__ . '/../includes/sidebar.php'; ?>
        <main class="main-content">
            <a href="productos.php" class="btn-back"><i class="fas fa-arrow-left"></i> Volver a productos</a>

            <div class="form-container">
                <h2 class="form-title"><i class="fas fa-edit" style="color: var(--primary);"></i> Editar Producto</h2>

                <form id="productEditForm">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id_producto" value="<?php echo $product['id_producto']; ?>">

                    <div class="form-group">
                        <label class="form-label">Nombre del Producto</label>
                        <input type="text" name="nombre" class="form-control" required
                            value="<?php echo htmlspecialchars($product['nombre_producto']); ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Descripción</label>
                        <textarea name="descripcion" class="form-control"
                            rows="3"><?php echo htmlspecialchars($product['descripcion']); ?></textarea>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label class="form-label">Precio ($)</label>
                            <input type="number" name="precio" class="form-control" required min="0" step="100"
                                value="<?php echo floatval($product['precio']); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Stock Actual</label>
                            <input type="number" name="stock" class="form-control" required min="0"
                                value="<?php echo intval($product['stock']); ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Estado</label>
                        <select name="estado" class="form-control">
                            <option value="activo" <?php echo $product['estado'] === 'activo' ? 'selected' : ''; ?>>Activo
                            </option>
                            <option value="inactivo" <?php echo $product['estado'] === 'inactivo' ? 'selected' : ''; ?>>
                                Inactivo</option>
                        </select>
                    </div>

                    <button type="submit" class="btn-submit">Guardar Cambios</button>
                </form>
            </div>
        </main>
    </div>

    <script>
        document.getElementById('productEditForm').addEventListener('submit', function (e) {
            e.preventDefault();
            const formData = new FormData(this);

            fetch('acciones.php', {
                method: 'POST',
                body: formData
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        MaiModal.alert({
                            title: '¡Actualizado!',
                            message: data.message,
                            type: 'success',
                            onConfirm: () => {
                                window.location.href = 'productos.php';
                            }
                        });
                    } else {
                        MaiModal.alert({
                            title: 'Error',
                            message: data.message,
                            type: 'danger'
                        });
                    }
                })
                .catch(err => {
                    MaiModal.alert({
                        title: 'Error Técnico',
                        message: err.message,
                        type: 'danger'
                    });
                });
        });
    </script>
</body>

</html>