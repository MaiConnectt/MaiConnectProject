<?php
/**
 * Partial: footer.php
 * Cierra el layout del dashboard y carga los scripts comunes.
 *
 * Variables esperadas antes de hacer include:
 *   $extra_scripts (array, opcional) — URLs de scripts adicionales a cargar
 *
 * Ejemplo de uso:
 *   $extra_scripts = ["pedidos.js", "https://cdn.jsdelivr.net/npm/chart.js"];
 *   require_once __DIR__ . '/../includes/footer.php';
 */
$extra_scripts = $extra_scripts ?? [];
?>
</main>
</div><!-- /.dashboard-container -->

<!-- Dashboard Core Script -->
<script src="<?= BASE_URL ?>/src/JavaScript/dashboard.js"></script>

<?php foreach ($extra_scripts as $script): ?>
    <script src="<?php echo htmlspecialchars($script); ?>"></script>
<?php endforeach; ?>
</body>

</html>