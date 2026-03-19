<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../config/conexion.php';

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    header('Location: ' . BASE_URL . '/src/Php/dashboard/equipo/equipo.php');
    exit;
}

$stmt = $pdo->prepare("
    SELECT m.*, u.nombre, u.apellido, u.email 
    FROM tbl_miembro m 
    JOIN tbl_usuario u ON m.id_usuario = u.id_usuario 
    WHERE m.id_miembro = ?
");
$stmt->execute([$id]);
$seller = $stmt->fetch();

if (!$seller) {
    header('Location: ' . BASE_URL . '/src/Php/dashboard/equipo/equipo.php');
    exit;
}
?>
<?php
$page_title = 'Editar Vendedor - Mai Shop';
$extra_css  = [BASE_URL . '/styles/equipo.css'];
require_once __DIR__ . '/../includes/head.php';
?>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<main class="main-content">
    <a href="equipo.php" class="btn-back"><i class="fas fa-arrow-left"></i> Volver al equipo</a>

    <div class="form-container">
        <h2 class="form-title"><i class="fas fa-edit" style="color: var(--primary);"></i> Editar Vendedor</h2>

        <form id="editForm">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id_miembro" value="<?php echo $seller['id_miembro']; ?>">

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label class="form-label">Nombre</label>
                    <input type="text" name="nombre" class="form-control" required
                        value="<?php echo htmlspecialchars($seller['nombre']); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Apellido</label>
                    <input type="text" name="apellido" class="form-control" required
                        value="<?php echo htmlspecialchars($seller['apellido']); ?>">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 1rem;">
                <div class="form-group">
                    <label class="form-label">Tipo Documento</label>
                    <select name="tipo_documento" class="form-control" required>
                        <option value="">Seleccionar</option>
                        <option value="CC" <?php echo ($seller['tipo_documento'] ?? '') === 'CC' ? 'selected' : ''; ?>>Cédula de Ciudadanía</option>
                        <option value="TI" <?php echo ($seller['tipo_documento'] ?? '') === 'TI' ? 'selected' : ''; ?>>Tarjeta de Identidad</option>
                        <option value="CE" <?php echo ($seller['tipo_documento'] ?? '') === 'CE' ? 'selected' : ''; ?>>Cédula de Extranjería</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Número de Documento</label>
                    <input type="text" name="numero_documento" class="form-control" required maxlength="15"
                        oninput="this.value = this.value.replace(/[^0-9]/g, '');"
                        value="<?php echo htmlspecialchars($seller['numero_documento'] ?? ''); ?>"
                        placeholder="Número de identificación">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Correo Electrónico</label>
                <input type="email" name="email" class="form-control" required
                    value="<?php echo htmlspecialchars($seller['email']); ?>">
            </div>

            <div class="form-group">
                <label class="form-label">Teléfono / WhatsApp</label>
                <input type="tel" name="telefono" class="form-control" required maxlength="10" minlength="10"
                    oninput="this.value = this.value.replace(/[^0-9]/g, '');"
                    value="<?php echo htmlspecialchars($seller['telefono'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label class="form-label">Universidad</label>
                <input type="text" name="universidad" class="form-control"
                    value="<?php echo htmlspecialchars($seller['universidad'] ?? ''); ?>"
                    placeholder="Ej. Universidad Central">
            </div>

            <div class="form-group">
                <label class="form-label">Estado</label>
                <select name="estado" class="form-control">
                    <option value="activo" <?php echo $seller['estado'] === 'activo' ? 'selected' : ''; ?>>Activo</option>
                    <option value="inactivo" <?php echo $seller['estado'] === 'inactivo' ? 'selected' : ''; ?>>Inactivo</option>
                </select>
            </div>

            <button type="submit" class="btn-submit">Guardar Cambios</button>
        </form>
    </div>
</main>

<script>
    document.getElementById('editForm').addEventListener('submit', function (e) {
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
                        title: '¡Cambios Guardados!',
                        message: data.message,
                        type: 'success',
                        onConfirm: () => {
                            window.location.href = 'equipo.php';
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
<?php
$extra_scripts = [];
require_once __DIR__ . '/../includes/footer.php';
?>