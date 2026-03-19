<?php
/**
 * header.php — Partial del <head> y layout del seller.
 * 
 * Variables disponibles antes de incluir:
 *   $pageTitle   (string) — Título de la página (ej: "Mis Pedidos")
 *   $extraStyles (string, opcional) — CSS adicional entre <style> tags
 */
$pageTitle = $pageTitle ?? 'Dashboard';
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - Mai Shop</title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700;800&family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- Seller Styles -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/styles/seller.css">

    <?php if (!empty($extraStyles)): ?>
        <style><?= $extraStyles ?></style>
    <?php endif; ?>
</head>

<body>
    <button class="menu-toggle" id="menuToggle"><i class="fas fa-bars"></i></button>
    <div class="dashboard-container">
        <?php include __DIR__ . '/sidebar.php'; ?>
        <main class="main-content">
