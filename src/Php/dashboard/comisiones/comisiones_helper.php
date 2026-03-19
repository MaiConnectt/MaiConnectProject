<?php
/**
 * Helper para la gestión de comisiones - Separa la lógica de negocio del controlador
 */

/**
 * Obtiene la información del miembro/vendedor
 * @throws Exception si no se encuentra
 */
function obtenerMiembro(PDO $pdo, int $id_member): array {
    $stmt = $pdo->prepare("
        SELECT 
            m.*, 
            u.nombre AS first_name, 
            u.apellido AS last_name, 
            u.email,
            m.porcentaje_comision AS commission_percentage
        FROM tbl_miembro m 
        JOIN tbl_usuario u ON m.id_usuario = u.id_usuario 
        WHERE m.id_miembro = ?
    ");
    $stmt->execute([$id_member]);
    $member = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$member) {
        throw new Exception("Vendedor no encontrado.");
    }
    return $member;
}

/**
 * Obtiene los pedidos pendientes por pagar
 * Si se especifica $id_pedido, obtiene solo ese pedido (si es válido)
 * Si no, obtiene todos los del vendedor
 */
function obtenerPedidosPendientes(PDO $pdo, int $id_member, ?int $id_pedido = null): array {
    if ($id_pedido) {
        $stmt_orders = $pdo->prepare("
            SELECT 
                o.id_pedido AS id_order, 
                o.fecha_creacion AS created_at, 
                o.estado,
                ot.total AS order_total,
                o.monto_comision AS commission_amount
            FROM tbl_pedido o
            LEFT JOIN vw_totales_pedido ot ON o.id_pedido = ot.id_pedido
            WHERE o.id_pedido = ? 
            AND o.estado = 2 
            AND o.monto_comision > 0
            AND (o.id_pago_comision IS NULL OR o.id_pago_comision = 0)
        ");
        $stmt_orders->execute([$id_pedido]);
        return $stmt_orders->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmt_orders = $pdo->prepare("
            SELECT 
                o.id_pedido AS id_order, 
                o.fecha_creacion AS created_at, 
                o.estado,
                ot.total AS order_total,
                o.monto_comision AS commission_amount
            FROM tbl_pedido o
            JOIN tbl_miembro m ON o.id_vendedor = m.id_miembro
            LEFT JOIN vw_totales_pedido ot ON o.id_pedido = ot.id_pedido
            WHERE o.id_vendedor = ? 
            AND o.estado = 2 
            AND o.monto_comision > 0
            AND (o.id_pago_comision IS NULL OR o.id_pago_comision = 0)
            ORDER BY o.fecha_creacion ASC
        ");
        $stmt_orders->execute([$id_member]);
        return $stmt_orders->fetchAll(PDO::FETCH_ASSOC);
    }
}

/**
 * Calcula el monto total de comisión a pagar de un arreglo de pedidos
 */
function calcularTotalComision(array $orders): float {
    $total = 0;
    foreach ($orders as $o) {
        $total += ($o['commission_amount'] ?? 0);
    }
    return (float) $total;
}

/**
 * Sube el comprobante de pago al servidor
 * @throws Exception si hay un error al subir o si el archivo no es válido
 */
function subirComprobante(array $file): ?string {
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null; // Opcional o validado previamente
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("Error técnico en la subida del archivo (Código: " . $file['error'] . ").");
    }

    // 1. Validación de tamaño (Max 2MB)
    $max_size = 2 * 1024 * 1024; // 2MB en bytes
    if ($file['size'] > $max_size) {
        throw new Exception("El archivo supera el tamaño máximo permitido de 2MB.");
    }

    // 2. Validación de tipo de archivo (solo imágenes permitidas)
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp'];
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($file_ext, $allowed_extensions)) {
        throw new Exception("Tipo de archivo no válido. Solo se permiten imágenes (JPG, JPEG, PNG, WEBP).");
    }

    // 3. Validación de seguridad adicional (verificar contenido de imagen)
    $image_info = @getimagesize($file['tmp_name']);
    if ($image_info === false) {
        throw new Exception("El archivo no parece ser una imagen válida o está corrupto.");
    }
    
    $upload_dir = __DIR__ . '/../../uploads/comisiones/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $filename = 'comm_' . time() . '_' . uniqid() . '.' . $file_ext;
    
    if (move_uploaded_file($file['tmp_name'], $upload_dir . $filename)) {
        return 'uploads/comisiones/' . $filename;
    } else {
        throw new Exception("Error al guardar el comprobante en el servidor.");
    }
}

/**
 * Ejecuta la función SQL fun_pagar_comisiones
 * @throws Exception si ocurre un fallo en base de datos
 */
function procesarPagoComision(PDO $pdo, int $id_member, float $total, ?string $proof, array $order_ids): array {
    $order_ids_json = json_encode($order_ids);

    $stmt_pay = $pdo->prepare("SELECT fun_pagar_comisiones(?, ?, ?, ?)");
    $stmt_pay->execute([
        $id_member,
        $total,
        $proof,
        $order_ids_json
    ]);

    $resultado_json = $stmt_pay->fetchColumn();
    $resultado = json_decode($resultado_json, true);

    if (!$resultado || empty($resultado['success'])) {
        throw new Exception($resultado['message'] ?? 'Error interno al registrar el pago de comisiones');
    }

    return $resultado;
}
