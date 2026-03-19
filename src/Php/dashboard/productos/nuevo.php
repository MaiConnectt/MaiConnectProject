<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../config/conexion.php';
$page_title = 'Nuevo Producto - Mai Shop';
$extra_css = [BASE_URL . '/styles/productos.css'];
require_once __DIR__ . '/../includes/head.php';
?>

<!-- Sidebar -->
<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<main class="main-content">
    <a href="productos.php" class="btn-back">
        <i class="fas fa-arrow-left"></i> Volver a productos
    </a>

    <div class="form-container">
        <h2 class="form-title">
            <i class="fas fa-plus-circle" style="color: var(--primary);"></i> Nuevo Producto
        </h2>

        <form id="productForm" enctype="multipart/form-data">
            <input type="hidden" name="action" value="create">

            <div class="form-group">
                <label class="form-label">Nombre del Producto</label>
                <input type="text" name="nombre" class="form-control" required placeholder="Ej: Pastel de Chocolate">
            </div>

            <div class="form-group">
                <label class="form-label">Descripción</label>
                <textarea name="descripcion" class="form-control" rows="3"
                    placeholder="Breve descripción del producto..."></textarea>
            </div>

            <div class="form-group">
                <label class="form-label">Precio ($)</label>
                <input type="number" name="precio" class="form-control" required min="0" step="100" placeholder="0">
            </div>

            <div class="form-group">
                <label class="form-label">Imagen del Producto (Opcional)</label>
                <input type="file" name="imagen" class="form-control" accept="image/*">
            </div>

            <div class="form-group">
                <label class="form-label">Estado</label>
                <select name="estado" class="form-control">
                    <option value="activo">Activo (Visible para vendedores)</option>
                    <option value="inactivo">Inactivo (Oculto)</option>
                </select>
            </div>

            <button type="submit" class="btn-submit">Crear Producto</button>
        </form>
    </div>
</main>

<?php 
$extra_scripts = [BASE_URL . '/src/JavaScript/productos_form.js'];
require_once __DIR__ . '/../includes/footer.php'; 
?>