</main>
    
    <?php if (isLoggedIn()): ?>
    <!-- Includi la barra di navigazione -->
<?php include_once $_SERVER['DOCUMENT_ROOT'] . BASE_PATH . '/includes/navbar.php'; ?>
    
    <!-- Floating Action Button -->
    <button class="floating-action-btn" id="fab">
        <i class="fas fa-plus"></i>
    </button>
    
    <!-- FAB Menu -->
    <div class="fab-menu" id="fabMenu">
        <a href="<?= BASE_PATH ?>/views/spese/create.php" class="fab-item">
            <i class="fas fa-plus-circle"></i>
            <span>Nuova Spesa</span>
        </a>
        <a href="<?= BASE_PATH ?>/views/manutenzioni/create.php" class="fab-item">
            <i class="fas fa-tools"></i>
            <span>Nuova Manutenzione</span>
        </a>
        <a href="<?= BASE_PATH ?>/views/segnalazioni/create.php" class="fab-item">
            <i class="fas fa-exclamation-circle"></i>
            <span>Nuova Segnalazione</span>
        </a>
    </div>
    <?php endif; ?>
    
    <!-- jQuery -->
    <script src="<?= BASE_PATH ?>/assets/js/jquery-3.7.1.min.js"></script>
    <!-- Bootstrap JS -->
    <script src="<?= BASE_PATH ?>/assets/js/bootstrap.bundle.min.js"></script>
    <!-- Custom Mobile JS -->
    <script src="<?= BASE_PATH ?>/assets/js/mobile-app.js"></script>
</body>
</html>