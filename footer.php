<?php
// Prevent direct access to footer.php
if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) {
    header("Location: ../index.php");
    exit();
}
?>
<footer class="site-footer">
    <div class="footer-content">
        <p>&copy; <?php echo date("Y"); ?> Water Bill Management System. All Rights Reserved.</p>
        <p>Developed by <span class="dev-name">Ama Eshwarage</span></p>
    </div>
</footer>

<!-- JS Scripts -->
<script src="../assets/js/main.js"></script>
</body>
</html>