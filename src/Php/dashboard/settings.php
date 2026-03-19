<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/conexion.php';

$message = '';
$messageType = '';

// Obtener datos actuales del usuario
// Obtener datos actuales del usuario
try {
    $stmt = $pdo->prepare("SELECT nombre, apellido, email FROM tbl_usuario WHERE id_usuario = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user_data = $stmt->fetch();
} catch (PDOException $e) {
    $message = 'Error al cargar datos: ' . $e->getMessage();
    $messageType = 'error';
    $user_data = ['nombre' => '', 'apellido' => '', 'email' => ''];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        // Actualizar Perfil
        $nombre = trim($_POST['first_name']);
        $apellido = trim($_POST['last_name']);
        $email = trim($_POST['email']);

        try {
            $update = $pdo->prepare("UPDATE tbl_usuario SET nombre = ?, apellido = ?, email = ? WHERE id_usuario = ?");
            $update->execute([$nombre, $apellido, $email, $_SESSION['user_id']]);

            // Actualizar datos locales para reflejar cambios
            $user_data['nombre'] = $nombre;
            $user_data['apellido'] = $apellido;
            $user_data['email'] = $email;

            $message = 'Perfil actualizado correctamente.';
            $messageType = 'success';
        } catch (PDOException $e) {
            $message = 'Error al actualizar perfil: ' . $e->getMessage();
            $messageType = 'error';
        }

    } elseif (isset($_POST['update_password'])) {
        // Actualizar Contraseña
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        if ($new_password !== $confirm_password) {
            $message = 'Las nuevas contraseñas no coinciden.';
            $messageType = 'error';
        } else {
            try {
                // Obtener contraseña actual
                $stmt = $pdo->prepare("SELECT contrasena FROM tbl_usuario WHERE id_usuario = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $db_user = $stmt->fetch();

                if ($db_user && password_verify($current_password, $db_user['contrasena'])) {
                    $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
                    $update = $pdo->prepare("UPDATE tbl_usuario SET contrasena = ? WHERE id_usuario = ?");
                    $update->execute([$new_hash, $_SESSION['user_id']]);

                    $message = 'Contraseña actualizada correctamente.';
                    $messageType = 'success';
                } else {
                    $message = 'La contraseña actual es incorrecta.';
                    $messageType = 'error';
                }
            } catch (PDOException $e) {
                $message = 'Error al actualizar contraseña: ' . $e->getMessage();
                $messageType = 'error';
            }
        }
    }
}
?>
<?php
$page_title = 'Configuración - Mai Shop';
require_once __DIR__ . '/includes/head.php';
?>
<!-- Sidebar -->
<?php include __DIR__ . '/includes/sidebar.php'; ?>

<main class="main-content">
            <div class="dashboard-header">
                <div class="header-left">
                    <h1>Configuración</h1>
                    <p>Gestiona tu perfil y preferencias</p>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="alert <?php echo $messageType; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <div class="settings-grid">
                <!-- Perfil -->
                <div class="settings-card">
                    <div class="card-header">
                        <h3 class="card-title">Información Personal</h3>
                    </div>
                    <form method="POST" action="settings.php">
                        <input type="hidden" name="update_profile" value="1">
                        <div class="form-group">
                            <label class="form-label">Nombre</label>
                            <input type="text" name="first_name" class="form-input"
                                value="<?php echo htmlspecialchars($user_data['nombre']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Apellido</label>
                            <input type="text" name="last_name" class="form-input"
                                value="<?php echo htmlspecialchars($user_data['apellido']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Correo Electrónico</label>
                            <input type="email" name="email" class="form-input"
                                value="<?php echo htmlspecialchars($user_data['email']); ?>" required>
                        </div>
                        <button type="submit" class="btn-submit">
                            Actualizar Perfil
                        </button>
                    </form>
                </div>

                <!-- Contraseña -->
                <div class="settings-card">
                    <div class="card-header">
                        <h3 class="card-title">Seguridad</h3>
                    </div>
                    <form method="POST" action="settings.php">
                        <input type="hidden" name="update_password" value="1">
                        <div class="form-group">
                            <label class="form-label">Contraseña Actual</label>
                            <input type="password" name="current_password" class="form-input" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Nueva Contraseña</label>
                            <input type="password" name="new_password" class="form-input" required minlength="6">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Confirmar Nueva Contraseña</label>
                            <input type="password" name="confirm_password" class="form-input" required minlength="6">
                        </div>
                        <button type="submit" class="btn-submit">
                            Cambiar Contraseña
                        </button>
                    </form>
                </div>
            </div>
        </main>
<?php
require_once __DIR__ . '/includes/footer.php';
?>