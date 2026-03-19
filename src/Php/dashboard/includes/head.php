<?php
/**
 * Partial: head.php
 * Bloque <head> reutilizable del dashboard.
 *
 * Variables esperadas antes de hacer include:
 *   $page_title  (string) — Título de la pestaña, ej: "Dashboard - Mai Shop"
 *   $extra_css   (array, opcional) — Rutas CSS adicionales relativas al archivo que incluye
 *
 * Ejemplo de uso:
 *   $page_title = "Pedidos - Mai Shop";
 *   $extra_css  = ["pedidos.css"];
 *   require_once __DIR__ . '/../includes/head.php';
 */
$page_title = $page_title ?? 'Mai Shop';
$extra_css = $extra_css ?? [];
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?php echo htmlspecialchars($page_title); ?>
    </title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700;800&family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">

    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- Dashboard Styles (Base) -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/styles/dashboard.css">

    <?php foreach ($extra_css as $css): ?>
        <link rel="stylesheet" href="<?php echo htmlspecialchars($css); ?>">
    <?php endforeach; ?>
</head>

<body>
    <!-- Mobile Menu Toggle -->
    <button class="menu-toggle" id="menuToggle">
        <i class="fas fa-bars"></i>
    </button>

    <div class="dashboard-container">