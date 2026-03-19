<?php
/**
 * pedidos_helper.php — Lógica centralizada para creación de pedidos.
 * Solo los vendedores crean pedidos.
 * 
 * Uso:
 *   require_once __DIR__ . '/pedidos_helper.php';
 *   $resultado = crearPedido($data, $pdo, $member_id);
 */

/**
 * Valida los datos del pedido.
 * @throws Exception si hay datos inválidos
 */
function validarDatosPedido(array $data): void
{
    if (empty($data['telefono'])) {
        throw new Exception("El teléfono de contacto es obligatorio");
    }
    if (!preg_match('/^[0-9]{10}$/', $data['telefono'])) {
        throw new Exception("El teléfono de contacto debe tener exactamente 10 dígitos numéricos");
    }
    if (empty($data['fecha'])) {
        throw new Exception("La fecha de entrega es obligatoria");
    }
    // Validar que la fecha sea mínimo 2 días después de hoy
    $fechaMinima = date('Y-m-d', strtotime('+2 days'));
    if ($data['fecha'] < $fechaMinima) {
        throw new Exception("La fecha de entrega debe ser al menos 2 días después de hoy ($fechaMinima)");
    }
    if (empty($data['direccion'])) {
        throw new Exception("La dirección de entrega es obligatoria");
    }
    if (!is_array($data['productos']) || empty($data['productos'])) {
        throw new Exception("Formato de productos inválido");
    }
}

/**
 * Inserta el pedido maestro en tbl_pedido.
 * @return int ID del nuevo pedido
 */
function insertarPedido(PDO $pdo, array $data, int $member_id): int
{
    $stmt = $pdo->prepare(
        "INSERT INTO tbl_pedido (id_vendedor, telefono_contacto, fecha_entrega, direccion_entrega, notas, estado, estado_pago, monto_comision)
         VALUES (?, ?, ?, ?, ?, 0, 0, 0)
         RETURNING id_pedido"
    );
    $stmt->execute([
        $member_id,
        $data['telefono'],
        $data['fecha'],
        $data['direccion'],
        $data['notas'] ?? '',
    ]);

    return (int) $stmt->fetchColumn();
}

/**
 * Inserta las líneas de detalle del pedido.
 * Productos viene como [id_producto => cantidad].
 * @return float Total del pedido
 */
function insertarDetalle(PDO $pdo, int $id_pedido, array $productos): float
{
    $total = 0.0;

    foreach ($productos as $id_producto => $cantidad) {
        $cantidad = (int) $cantidad;
        if ($cantidad <= 0) continue;

        // Buscar producto existente (con lock para concurrencia)
        $stmt = $pdo->prepare("SELECT precio, nombre_producto FROM tbl_producto WHERE id_producto = ? FOR UPDATE");
        $stmt->execute([$id_producto]);
        $producto = $stmt->fetch();

        if (!$producto) {
            throw new Exception("Producto no encontrado: #" . $id_producto);
        }

        // Insertar detalle (ID generado por SERIAL)
        $pdo->prepare(
            "INSERT INTO tbl_detalle_pedido (id_pedido, id_producto, cantidad, precio_unitario)
             VALUES (?, ?, ?, ?)"
        )->execute([$id_pedido, $id_producto, $cantidad, $producto['precio']]);

        $total += ($cantidad * $producto['precio']);
    }

    if ($total <= 0) {
        throw new Exception("Debes agregar al menos un producto con cantidad mayor a 0");
    }

    return $total;
}

/**
 * Calcula y guarda la comisión del vendedor en el pedido.
 */
function calcularComision(PDO $pdo, int $id_pedido, float $total, int $member_id, float $porcentaje_comision): void
{
    $comision = round($total * ($porcentaje_comision / 100), 2);

    $pdo->prepare("UPDATE tbl_pedido SET monto_comision = ? WHERE id_pedido = ?")
        ->execute([$comision, $id_pedido]);
}

/**
 * Registra el evento en el historial de cambios del pedido.
 */
function guardarHistorial(PDO $pdo, int $id_pedido, int $user_id, string $motivo): void
{
    $pdo->prepare(
        "INSERT INTO tbl_historial_pedido (id_pedido, usuario_cambio, estado_anterior, estado_nuevo, motivo)
         VALUES (?, ?, NULL, 0, ?)"
    )->execute([$id_pedido, $user_id, $motivo]);
}

/**
 * Crea un pedido completo: validar → insertar pedido → detalles → comisión → historial.
 *
 * @param array $data Datos del pedido:
 *   - telefono (string, 10 dígitos)
 *   - direccion (string)
 *   - fecha (string, Y-m-d)
 *   - notas (string, opcional)
 *   - productos (array [id_producto => cantidad])
 *   - user_id (int, ID del usuario en sesión)
 *   - commission_percentage (float, % comisión del vendedor)
 * @param PDO $pdo Conexión a base de datos
 * @param int $member_id ID del miembro/vendedor
 * @return array ['success' => bool, 'message' => string, 'id_pedido' => int]
 */
function crearPedido(array $data, PDO $pdo, int $member_id): array
{
    try {
        // 1. Validar
        validarDatosPedido($data);

        // 2. Transacción
        $pdo->beginTransaction();

        // 3. Insertar pedido maestro
        $id_pedido = insertarPedido($pdo, $data, $member_id);

        // 4. Insertar detalle (productos)
        $total = insertarDetalle($pdo, $id_pedido, $data['productos']);

        // 5. Calcular comisión
        $porcentaje = $data['commission_percentage'] ?? 10.0;
        calcularComision($pdo, $id_pedido, $total, $member_id, $porcentaje);

        // 6. Registrar historial
        $user_id = $data['user_id'] ?? $member_id;
        guardarHistorial($pdo, $id_pedido, $user_id, 'Pedido creado por el vendedor');

        // 7. Confirmar
        $pdo->commit();

        return [
            'success' => true,
            'message' => 'Pedido creado exitosamente',
            'id_pedido' => $id_pedido,
        ];

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return [
            'success' => false,
            'message' => $e->getMessage(),
            'id_pedido' => 0,
        ];
    }
}
