<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../conexion.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'create':
            $nombre = trim($_POST['nombre'] ?? '');
            $apellido = trim($_POST['apellido'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $comision = floatval($_POST['comision'] ?? 5.0);
            $estado = $_POST['estado'] ?? 'activo';

            if (empty($nombre) || empty($email) || empty($password)) {
                throw new Exception("Nombre, Email y Contraseña son obligatorios");
            }

            $pdo->beginTransaction();

            // Check email
            $check = $pdo->prepare("SELECT id_usuario FROM tbl_usuario WHERE email = ?");
            $check->execute([$email]);
            if ($check->fetch()) {
                throw new Exception("El email ya está registrado");
            }

            // Get next IDs
            $next_user = $pdo->query("SELECT COALESCE(MAX(id_usuario), 0) + 1 FROM tbl_usuario")->fetchColumn();
            $next_member = $pdo->query("SELECT COALESCE(MAX(id_miembro), 0) + 1 FROM tbl_miembro")->fetchColumn();

            // Insert into tbl_usuario
            $stmt = $pdo->prepare("INSERT INTO tbl_usuario (id_usuario, nombre, apellido, email, contrasena, id_rol) VALUES (?, ?, ?, ?, ?, 2)");
            $stmt->execute([$next_user, $nombre, $apellido, $email, password_hash($password, PASSWORD_BCRYPT)]);

            // Insert into tbl_miembro
            $stmt = $pdo->prepare("INSERT INTO tbl_miembro (id_miembro, id_usuario, porcentaje_comision, estado, fecha_contratacion) VALUES (?, ?, ?, ?, CURRENT_DATE)");
            $stmt->execute([$next_member, $next_user, $comision, $estado]);

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Vendedor creado exitosamente']);
            break;

        case 'edit':
            $id_miembro = intval($_POST['id_miembro'] ?? 0);
            $nombre = trim($_POST['nombre'] ?? '');
            $apellido = trim($_POST['apellido'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $comision = floatval($_POST['comision'] ?? 0);
            $estado = $_POST['estado'] ?? 'activo';

            if (!$id_miembro || empty($nombre) || empty($email)) {
                throw new Exception("Datos incompletos");
            }

            $pdo->beginTransaction();

            // Get user ID
            $stmt = $pdo->prepare("SELECT id_usuario FROM tbl_miembro WHERE id_miembro = ?");
            $stmt->execute([$id_miembro]);
            $id_usuario = $stmt->fetchColumn();

            if (!$id_usuario)
                throw new Exception("Vendedor no encontrado");

            // Update tbl_usuario
            $stmt = $pdo->prepare("UPDATE tbl_usuario SET nombre = ?, apellido = ?, email = ? WHERE id_usuario = ?");
            $stmt->execute([$nombre, $apellido, $email, $id_usuario]);

            // Update tbl_miembro
            $stmt = $pdo->prepare("UPDATE tbl_miembro SET porcentaje_comision = ?, estado = ? WHERE id_miembro = ?");
            $stmt->execute([$comision, $estado, $id_miembro]);

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Vendedor actualizado exitosamente']);
            break;

        case 'delete':
            $id_miembro = intval($_POST['id_miembro'] ?? 0);
            if (!$id_miembro)
                throw new Exception("ID inválido");

            // 1. Validar si el vendedor tiene pedidos asociados
            $check_orders = $pdo->prepare("SELECT COUNT(*) FROM tbl_pedido WHERE id_vendedor = ?");
            $check_orders->execute([$id_miembro]);
            if ($check_orders->fetchColumn() > 0) {
                throw new Exception("No se puede eliminar porque tiene pedidos asociados. El vendedor será desactivado.");
            }

            // 2. Validar si tiene pagos de comisión registrados
            $check_payments = $pdo->prepare("SELECT COUNT(*) FROM tbl_pago_comision WHERE id_vendedor = ?");
            $check_payments->execute([$id_miembro]);
            if ($check_payments->fetchColumn() > 0) {
                throw new Exception("No se puede eliminar porque tiene pagos de comisión registrados. El vendedor será desactivado.");
            }

            // 3. Aplicar Eliminación Lógica (Soft Delete) - SIEMPRE, según requerimiento de eliminar DELETE físico
            $stmt = $pdo->prepare("UPDATE tbl_miembro SET estado = 'inactivo' WHERE id_miembro = ?");
            $stmt->execute([$id_miembro]);

            echo json_encode(['success' => true, 'message' => 'Vendedor desactivado exitosamente (Eliminación lógica)']);
            break;

        default:
            throw new Exception("Acción no válida");
    }
} catch (Exception $e) {
    if ($pdo->inTransaction())
        $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
