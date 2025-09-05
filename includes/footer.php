</div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/litepicker/dist/litepicker.js"></script>
    
    <script src="https://cdn.jsdelivr.net/npm/litepicker/dist/plugins/ranges.js"></script>
    
    <script>
        const BASE_URL = '<?php echo BASE_URL; ?>';
    </script>
    
    <script src="<?php echo BASE_URL; ?>/assets/js/main.js"></script>
    
    <?php 
    $current_page = basename($_SERVER['SCRIPT_NAME']);
    if ($current_page === 'dashboard.php'): ?>
        <script src="<?php echo BASE_URL; ?>/assets/js/dashboard.js"></script>
    <?php elseif ($current_page === 'setup.php'): ?>
        <script src="<?php echo BASE_URL; ?>/assets/js/setup.js"></script>
    <?php elseif ($current_page === 'products.php'): ?>
        <script src="<?php echo BASE_URL; ?>/assets/js/products.js"></script>
    <?php elseif ($current_page === 'create-workflow.php'): ?>
        <script src="<?php echo BASE_URL; ?>/assets/js/workflow-builder.js"></script>
    <?php endif; ?>

</body>
</html>