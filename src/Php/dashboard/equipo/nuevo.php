<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../config/conexion.php';
?>
<?php
$page_title = 'Nuevo Vendedor - Mai Shop';
$extra_css  = [BASE_URL . '/styles/equipo.css'];
require_once __DIR__ . '/../includes/head.php';
?>

    <div class="dashboard-container">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>
        <main class="main-content">
            <a href="equipo.php" class="btn-back"><i class="fas fa-arrow-left"></i> Volver al equipo</a>

            <div class="form-container">
                <h2 class="form-title"><i class="fas fa-user-plus" style="color: var(--primary);"></i> Nuevo Vendedor
                </h2>

                <form id="sellerForm">
                    <input type="hidden" name="action" value="create">

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label class="form-label">Nombre</label>
                            <input type="text" name="nombre" class="form-control" required placeholder="Nombre">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Apellido</label>
                            <input type="text" name="apellido" class="form-control" required placeholder="Apellido">
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 1rem;">
                        <div class="form-group">
                            <label class="form-label">Tipo Documento</label>
                            <select name="tipo_documento" class="form-control" required>
                                <option value="">Seleccionar</option>
                                <option value="CC">Cédula de Ciudadanía</option>
                                <option value="TI">Tarjeta de Identidad</option>
                                <option value="CE">Cédula de Extranjería</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Número de Documento</label>
                            <input type="text" name="numero_documento" class="form-control" required maxlength="15"
                                oninput="this.value = this.value.replace(/[^0-9]/g, '');"
                                placeholder="Número de identificación">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Correo Electrónico</label>
                        <input type="email" name="email" class="form-control" required placeholder="email@ejemplo.com">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Teléfono / WhatsApp</label>
                        <input type="tel" name="telefono" class="form-control" required maxlength="10" minlength="10"
                            oninput="this.value = this.value.replace(/[^0-9]/g, '');" placeholder="3001234567">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Contraseña</label>
                        <input type="password" name="password" class="form-control" required placeholder="********">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Universidad</label>
                        <input type="text" name="universidad" class="form-control"
                            placeholder="Ej. Universidad Central">
                    </div>



                    <div class="form-group">
                        <label class="form-label">Estado Inicial</label>
                        <select name="estado" class="form-control">
                            <option value="activo">Activo</option>
                            <option value="inactivo">Inactivo</option>
                        </select>
                    </div>

                    <button type="submit" class="btn-submit">Crear Vendedor</button>
                </form>
            </div>
        </main>
    </div>

<script>
    document.getElementById('sellerForm').addEventListener('submit', function (e) {
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
                        title: '¡Vendedor Creado!',
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