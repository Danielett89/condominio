<?php
session_start();

// Definisci il percorso base
define('BASE_PATH', '/daniele/condominio');

// Funzioni base
function redirect($url) {
    header("Location: $url");
    exit;
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isProprietario() {
    return isset($_SESSION['user_role']) && ($_SESSION['user_role'] === 'Proprietario' || $_SESSION['user_role'] === 'Amministratore');
}

function formatCurrency($amount) {
    return number_format($amount, 2, ',', '.') . ' €';
}

// Verifica se l'utente è loggato
if (!isLoggedIn()) {
    redirect(BASE_PATH . '/login.php');
}

// Connessione al database
try {
    $db = new PDO("mysql:host=31.11.39.173;dbname=Sql1693377_3;charset=utf8mb4", "Sql1693377", "S2zyEwzk\$ZnyJu");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Errore di connessione al database: " . $e->getMessage());
}

// Recupera lo storico delle spese acqua
$speseAcqua = [];
$userId = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'];

try {
    if ($userRole === 'Affittuario') {
        // Filtra le spese per appartamento dell'affittuario
        $stmt = $db->prepare("SELECT sa.*, qsa.importo_quota, u.nome, u.cognome 
                              FROM spese_acqua sa
                              JOIN quote_spese_acqua qsa ON sa.id_spesa_acqua = qsa.id_spesa_acqua
                              JOIN appartamenti a ON qsa.id_appartamento = a.id_appartamento
                              JOIN utenti u ON sa.id_utente_inserimento = u.id_utente
                              JOIN utenti u2 ON a.id_appartamento = u2.id_appartamento
                              WHERE u2.id_utente = ?
                              ORDER BY sa.data_lettura DESC");
        $stmt->execute([$userId]);
    } else {
        // Ottieni tutte le spese per admin/proprietario
        $stmt = $db->query("SELECT sa.*, u.nome, u.cognome 
                           FROM spese_acqua sa
                           JOIN utenti u ON sa.id_utente_inserimento = u.id_utente
                           ORDER BY sa.data_lettura DESC");
    }
    $speseAcqua = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    // Ignora errori se le tabelle non esistono ancora
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <title>Gestione Acqua - Condominio</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/mobile-app.css">
</head>
<body>
    <!-- Header -->
    <header class="app-header">
        <a href="<?= BASE_PATH ?>/dashboard.php" class="header-back">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div class="header-title">Gestione Acqua</div>
        <?php if (isProprietario()): ?>
        <div class="header-actions">
            <a href="<?= BASE_PATH ?>/views/acqua/create.php" class="btn-header-action">
                <i class="fas fa-plus"></i>
            </a>
        </div>
        <?php endif; ?>
    </header>
    
    <!-- Content -->
    <main class="app-content">
        <!-- Elenco Spese Acqua -->
        <div class="app-card">
            <div class="app-card-header">
                <div class="card-header-title">Storico Spese Acqua</div>
                <?php if (isProprietario()): ?>
                <a href="<?= BASE_PATH ?>/views/acqua/create.php" class="card-header-action">
                    <i class="fas fa-plus"></i>
                </a>
                <?php endif; ?>
            </div>
            
            <div class="app-card-content">
                <?php if (count($speseAcqua) > 0): ?>
                    <div class="list">
                        <?php foreach ($speseAcqua as $spesa): ?>
                            <a href="<?= BASE_PATH ?>/views/acqua/view.php?id=<?= $spesa['id_spesa_acqua'] ?>" class="list-item clickable">
                                <div class="list-item-icon">
                                    <i class="fas fa-tint"></i>
                                </div>
                                <div class="list-item-content">
                                    <div class="list-item-title">
                                        Spesa Acqua del <?= date('d/m/Y', strtotime($spesa['data_lettura'])) ?>
                                    </div>
                                    <div class="list-item-subtitle">
                                        <?= $spesa['metodo_ripartizione'] ?> - 
                                        <?= isset($spesa['consumo_mc']) ? $spesa['consumo_mc'] . ' mc' : 'N/D' ?>
                                    </div>
                                </div>
                                <div class="list-item-action">
                                    <?php if (isset($spesa['importo_quota'])): ?>
                                        <?= formatCurrency($spesa['importo_quota']) ?>
                                    <?php else: ?>
                                        <?= formatCurrency($spesa['importo_totale']) ?>
                                    <?php endif; ?>
                                    <i class="fas fa-chevron-right ml-2"></i>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-tint empty-icon"></i>
                        <p>Nessuna spesa acqua registrata</p>
                        <?php if (isProprietario()): ?>
                        <a href="<?= BASE_PATH ?>/views/acqua/create.php" class="btn btn-primary mt-3">
                            <i class="fas fa-plus"></i> Aggiungi Spesa Acqua
                        </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if (isProprietario()): ?>
        <!-- Informazioni sulla ripartizione -->
        <div class="app-card info-card">
            <div class="app-card-header">
                <div class="card-header-title">Informazioni sulla Ripartizione</div>
            </div>
            
            <div class="app-card-content">
                <p><i class="fas fa-info-circle text-primary"></i> La ripartizione delle spese dell'acqua viene calcolata automaticamente utilizzando il metodo misto:</p>
                <ul class="info-list">
                    <li><strong>30%</strong> in base ai millesimi dell'appartamento</li>
                    <li><strong>70%</strong> in base al numero di occupanti</li>
                </ul>
                <p>I millesimi e il numero di occupanti sono già configurati nel sistema.</p>
            </div>
        </div>
        <?php endif; ?>
    </main>
    
    <!-- Includi la barra di navigazione -->
<?php include_once $_SERVER['DOCUMENT_ROOT'] . BASE_PATH . '/includes/navbar.php'; ?>
    
    <!-- JavaScript -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="<?= BASE_PATH ?>/assets/js/mobile-app.js"></script>
</body>
</html>