<?php
/**
 * helpers.php — Funciones de utilidad compartidas
 *
 * Incluir después de conexion.php en cualquier módulo que necesite estas funciones.
 * require_once __DIR__ . '/../../config/helpers.php';
 */

if (!function_exists('formato_moneda')) {
    /**
     * Formatea un número como moneda colombiana (COP).
     * Ejemplo: 1500000 → "$1.500.000"
     *
     * @param  float|int|null $valor
     * @return string
     */
    function formato_moneda($valor): string
    {
        $valor = $valor ?? 0;
        return '$' . number_format((float) $valor, 0, ',', '.');
    }
}

if (!function_exists('limpiar_cadena')) {
    /**
     * Sanitiza una cadena de texto eliminando espacios y caracteres peligrosos.
     *
     * @param  string $cadena
     * @return string
     */
    function limpiar_cadena(string $cadena): string
    {
        return htmlspecialchars(strip_tags(trim($cadena)), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('validar_telefono')) {
    /**
     * Valida que el teléfono tenga exactamente 10 dígitos numéricos.
     *
     * @param  string $telefono
     * @return bool
     */
    function validar_telefono(string $telefono): bool
    {
        return (bool) preg_match('/^[0-9]{10}$/', $telefono);
    }
}

if (!function_exists('getStatusBadge')) {
    /**
     * Retorna el HTML de un badge según el estado del pedido
     * @param int $status Estado del pedido (0, 1, 2, 3)
     * @return string HTML span con el badge
     */
    function getStatusBadge(int $status): string
    {
        switch ($status) {
            case 0: return '<span class="badge pending">Pendiente</span>';
            case 1: return '<span class="badge processing">En Proceso</span>';
            case 2: return '<span class="badge completed">Completado</span>';
            case 3: return '<span class="badge error">Cancelado</span>';
            default: return '<span class="badge">Desconocido</span>';
        }
    }
}

if (!function_exists('getPaymentBadge')) {
    /**
     * Retorna el HTML de un badge según el estado del pago
     * @param int $status Estado del pago (0, 1, 2, 3)
     * @return string HTML span con el badge
     */
    function getPaymentBadge(int $status): string
    {
        switch ($status) {
            case 0: return '<span class="badge" style="background: #eee; color: #666;">Sin Pago</span>';
            case 1: return '<span class="badge processing">Por Validar</span>';
            case 2: return '<span class="badge completed">Aprobado</span>';
            case 3: return '<span class="badge error">Rechazado</span>';
            default: return '<span class="badge">Desconocido</span>';
        }
    }
}

if (!function_exists('validarImagen')) {
    /**
     * Valida la seguridad, tamaño, tipo real y extensión de un archivo de imagen.
     * @throws Exception Cuando el archivo no pasa las verificaciones críticas.
     */
    function validarImagen(array $file): void
    {
        if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
            return; // No se subió archivo (es opcional)
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("Error técnico durante la transmisión del archivo al servidor (Código: " . $file['error'] . ").");
        }

        // 1. Tamaño máximo 2MB
        $max_size = 2 * 1024 * 1024;
        if ($file['size'] > $max_size) {
            throw new Exception("La imagen supera el tamaño máximo permitido de 2MB.");
        }

        // 2. Extensión de nombre válida
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed_extensions)) {
            throw new Exception("Tipo de archivo no válido. Solo extensiones: JPG, JPEG, PNG, WEBP.");
        }

        // 3. Inspeccionar el contenido real de la imagen
        $image_info = @getimagesize($file['tmp_name']);
        if ($image_info === false) {
            throw new Exception("El archivo no es una imagen válida, o sus dimensiones están corruptas.");
        }

        // 4. Verificación estricta del tipo MIME contra su contenido real (Anti-malware/PHP injection)
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        $allowed_mimes = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($mime, $allowed_mimes)) {
            throw new Exception("El contenido binario del archivo no es seguro ni coincide con una imagen permitida.");
        }
    }
}
