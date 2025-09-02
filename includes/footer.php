        </div>
    </main>

    <!--
    SCRIPT LOADING SECTION
    Correct Order: Main Library -> Plugin -> Our Code
    -->

    <!-- 1. Main Litepicker Library -->
    <script src="https://cdn.jsdelivr.net/npm/litepicker/dist/litepicker.js"></script>
    
    <!-- 2. Litepicker Ranges Plugin (depends on the main library) -->
    <script src="https://cdn.jsdelivr.net/npm/litepicker/dist/plugins/ranges.js"></script>
    
    <!-- 3. Global variables for our scripts to use -->
    <script>
        const BASE_URL = '<?php echo BASE_URL; ?>';
    </script>
    
    <!-- 4. Our main/global script -->
    <script src="<?php echo BASE_URL; ?>/assets/js/main.js"></script>
    
    <!-- 5. Page-specific scripts -->
    <?php 
    $current_page = basename($_SERVER['SCRIPT_NAME']);
    if ($current_page === 'dashboard.php'): ?>
        <script src="<?php echo BASE_URL; ?>/assets/js/dashboard.js"></script>
    <?php endif; ?>

</body>
</html>