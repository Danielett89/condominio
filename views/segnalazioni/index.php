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

function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'Amministratore';
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

// Recupera le segnalazioni
$segnalazioni = [];
$userRole = $_SESSION['user_role'];
$userId = $_SESSION['user_id'];
$filtroStato = isset($_GET['stato']) ? $_GET['stato'] : '';

try {
    if ($userRole === 'Affittuario') {
        // Filtra le segnalazioni dell'affittuario
        $query = "SELECT s.*, a.numero_interno, u.nome, u.cognome
                 FROM segnalazioni s
                 JOIN appartamenti a ON s.id_appartamento = a.id_appartamento
                 JOIN utenti u ON s.id_utente = u.id_utente
                 WHERE s.id_utente = ?";
        
        // Aggiungi filtro per stato se specificato
        if (!empty($filtroStato)) {
            $query .= " AND s.stato = ?";
        }
        
        $query .= " ORDER BY s.data_segnalazione DESC";
        
        $stmt = $db->prepare($query);
        
        if (!empty($filtroStato)) {
            $stmt->execute([$userId, $filtroStato]);
        } else {
            $stmt->execute([$userId]);
        }
    } else {
        // Per proprietari e admin, vedi tutte le segnalazioni
        $query = "SELECT s.*, a.numero_interno, u.nome, u.cognome
                 FROM segnalazioni s
                 JOIN appartamenti a ON s.id_appartamento = a.id_appartamento
                 JOIN utenti u ON s.id_utente = u.id_utente";
        
        // Aggiungi filtro per stato se specificato
        if (!empty($filtroStato)) {
            $query .= " WHERE s.stato = ?";
        }
        
        $query .= " ORDER BY s.data_segnalazione DESC";
        
        $stmt = $db->prepare($query);
        
        if (!empty($filtroStato)) {
            $stmt->execute([$filtroStato]);
        } else {
            $stmt->execute();
        }
    }
    
    $segnalazioni = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    // Ignora errori se le tabelle non esistono ancora
}

// Gestione messaggi flash
$flashMessage = '';
$flashType = '';

if (isset($_SESSION['flash_message']) && isset($_SESSION['flash_type'])) {
    $flashMessage = $_SESSION['flash_message'];
    $flashType = $_SESSION['flash_type'];
    unset($_SESSION['flash_message']);
    unset($_SESSION['flash_type']);
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <title>Segnalazioni - Condominio</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/mobile-app.css">
    <style>
        .filters {
            display: flex;
            overflow-x: auto;
            padding: 10px 0;
            margin: 0 -15px 15px -15px;
            padding: 0 15px;
            -webkit-overflow-scrolling: touch;
        }
        
        .filter-button {
            padding: 8px 12px;
            border-radius: 20px;
            margin-right: 10px;
            white-space: nowrap;
            background-color: #f5f5f5;
            color: #333;
            border: none;
            font-size: 0.8rem;
            transition: background-color 0.2s;
        }
        
        .filter-button.active {
            background-color: var(--primary-color);
            color: white;
        }
        
        .badge-priority {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 5px;
        }
        
        .priority-bassa { background-color: #4caf50; }
        .priority-media { background-color: #ff9800; }
        .priority-alta { background-color: #f44336; }
        .priority-urgente { background-color: #9c27b0; }
        
        .report-status {
            font-size: 0.75rem;
            padding: 3px 8px;
            border-radius: 20px;
            color: white;
            display: inline-block;
        }
        
        .status-aperta { background-color: #f44336; }
        .status-in-lavorazione { background-color: #ff9800; }
        .status-risolta { background-color: #4caf50; }
        .status-chiusa { background-color: #9e9e9e; }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="app-header">
        <a href="<?= BASE_PATH ?>/dashboard.php" class="header-back">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div class="header-title">Segnalazioni</div>
        <div class="header-actions">
            <a href="<?= BASE_PATH ?>/views/segnalazioni/create.php" class="btn-header-action">
                <i class="fas fa-plus"></i>
            </a>
        </div>
    </header>
    
    <!-- Content -->
    <main class="app-content">
        <?php if (!empty($flashMessage)): ?>
            <div class="alert alert-<?= $flashType ?>">
                <?= $flashMessage ?>
            </div>
        <?php endif; ?>
        
        <!-- Filtri -->
        <div class="filters">
            <a href="<?= BASE_PATH ?>/views/segnalazioni/index.php" class="filter-button <?= empty($filtroStato) ? 'active' : '' ?>">
                Tutte
            </a>
            <a href="<?= BASE_PATH ?>/views/segnalazioni/index.php?stato=Aperta" class="filter-button <?= $filtroStato === 'Aperta' ? 'active' : '' ?>">
                Aperte
            </a>
            <a href="<?= BASE_PATH ?>/views/segnalazioni/index.php?stato=In lavorazione" class="filter-button <?= $filtroStato === 'In lavorazione' ? 'active' : '' ?>">
                In lavorazione
            </a>
            <a href="<?= BASE_PATH ?>/views/segnalazioni/index.php?stato=Risolta" class="filter-button <?= $filtroStato === 'Risolta' ? 'active' : '' ?>">
                Risolte
            </a>
            <a href="<?= BASE_PATH ?>/views/segnalazioni/index.php?stato=Chiusa" class="filter-button <?= $filtroStato === 'Chiusa' ? 'active' : '' ?>">
                Chiuse
            </a>
        </div>
        
        <!-- Elenco Segnalazioni -->
        <div class="app-card">
            <div class="app-card-header">
                <div class="card-header-title">
                    <?php if (!empty($filtroStato)): ?>
                        Segnalazioni <?= strtolower($filtroStato) ?>
                    <?php else: ?>
                        Tutte le segnalazioni
                    <?php endif; ?>
                </div>
                <a href="<?= BASE_PATH ?>/views/segnalazioni/create.php" class="card-header-action">
                    <i class="fas fa-plus"></i>
                </a>
            </div>
            
            <div class="app-card-content">
                <?php if (count($segnalazioni) > 0): ?>
                    <div class="list">
                        <?php foreach ($segnalazioni as $segnalazione): ?>
                            <a href="<?= BASE_PATH ?>/views/segnalazioni/view.php?id=<?= $segnalazione['id_segnalazione'] ?>" class="list-item clickable">
                                <div class="list-item-icon">
                                    <i class="fas fa-exclamation-circle"></i>
                                </div>
                                <div class="list-item-content">
                                    <div class="list-item-title">
                                        <span class="badge-priority priority-<?= strtolower($segnalazione['priorita']) ?>"></span>
                                        <?= htmlspecialchars($segnalazione['titolo']) ?>
                                    </div>
                                    <div class="list-item-subtitle">
                                        <?= date('d/m/Y', strtotime($segnalazione['data_segnalazione'])) ?> - 
                                        <?= htmlspecialchars($segnalazione['numero_interno']) ?>
                                    </div>
                                </div>
                                <div class="list-item-action">
                                    <span class="report-status status-<?= strtolower(str_replace(' ', '-', $segnalazione['stato'])) ?>">
                                        <?= $segnalazione['stato'] ?>
                                    </span>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-exclamation-circle empty-icon"></i>
                        <p>Nessuna segnalazione trovata</p>
                        <a href="<?= BASE_PATH ?>/views/segnalazioni/create.php" class="btn btn-primary mt-3">
                            <i class="fas fa-plus"></i> Nuova Segnalazione
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
    
    <!-- Includi la barra di navigazione -->
<?php include_once $_SERVER['DOCUMENT_ROOT'] . BASE_PATH . '/includes/navbar.php'; ?>
    
    <!-- Floating Action Button -->
    <a href="<?= BASE_PATH ?>/views/segnalazioni/create.php" class="floating-action-btn">
        <i class="fas fa-plus"></i>
    </a>
    
    <!-- JavaScript -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="<?= BASE_PATH ?>/assets/js/mobile-app.js"></script>
</body>
</html>