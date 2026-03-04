<?php

/**
 * Función 1: Limpia y sanitiza cadenas de texto provenientes de formularios.
 */
function limpiar_cadena($texto)
{
    if (empty($texto))
        return '';
    $texto = trim($texto);
    $texto = stripslashes($texto);
    $texto = htmlspecialchars($texto);
    return $texto;
}

/**
 * Función 2: Valida que un número de teléfono tenga exactamente 10 dígitos numéricos.
 */
function validar_telefono($telefono)
{
    return preg_match('/^[0-9]{10}$/', $telefono);
}

/**
 * Función 3: Formatea una cantidad numérica a formato de moneda (Pesos Colombia).
 */
function formato_moneda($cantidad)
{
    return '$ ' . number_format((float) $cantidad, 0, ',', '.');
}

/**
 * Función 4: Genera un nombre de archivo único para evitar sobreescribir imágenes.
 */
function generar_nombre_archivo($nombre_original, $prefijo = 'file')
{
    $ext = pathinfo($nombre_original, PATHINFO_EXTENSION);
    return $prefijo . '_' . time() . '_' . uniqid() . '.' . $ext;
}
?>