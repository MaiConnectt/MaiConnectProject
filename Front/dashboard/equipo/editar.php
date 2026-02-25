<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../conexion.php';

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    header('Location: equipo.php');
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
    header('Location: equipo.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Vendedor - Mai Shop</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../dashboard.css">
    <link rel="stylesheet" href="equipo.css">
    <style>
        .form-container {
            max-width: 600px;
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
                        <label class="form-label">Porcentaje de Comisión (%)</label>
                        <input type="number" name="comision" class="form-control" step="0.1"
                            value="<?php echo floatval($seller['porcentaje_comision']); ?>" min="0" max="100">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Estado</label>
                        <select name="estado" class="form-control">
                            <option value="activo" <?php echo $seller['estado'] === 'activo' ? 'selected' : ''; ?>>Activo
                            </option>
                            <option value="inactivo" <?php echo $seller['estado'] === 'inactivo' ? 'selected' : ''; ?>>
                                Inactivo</option>
                        </select>
                    </div>

                    <button type="submit" class="btn-submit">Guardar Cambios</button>
                </form>
            </div>
        </main>
    </div>

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
</body>

</html>