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

// Recupera le spese
$spese = [];
$userId = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'];
$filtroTipologia = isset($_GET['tipologia']) ? $_GET['tipologia'] : '';

try {
    if ($userRole === 'Affittuario') {
        // Filtra le spese per appartamento dell'affittuario
        $query = "SELECT s.*, qs.importo_quota, u.nome, u.cognome 
                 FROM spese s
                 JOIN quote_spese qs ON s.id_spesa = qs.id_spesa
                 JOIN appartamenti a ON qs.id_appartamento = a.id_appartamento
                 JOIN utenti u ON s.id_utente_inserimento = u.id_utente
                 JOIN utenti u2 ON a.id_appartamento = u2.id_appartamento
                 WHERE u2.id_utente = ?";
        
        // Aggiungi filtro per tipologia se specificato
        if (!empty($filtroTipologia)) {
            $query .= " AND s.tipologia = ?";
        }
        
        $query .= " ORDER BY s.data_spesa DESC";
        
        $stmt = $db->prepare($query);
        
        if (!empty($filtroTipologia)) {
            $stmt->execute([$userId, $filtroTipologia]);
        } else {
            $stmt->execute([$userId]);
        }
    } else {
        // Ottieni tutte le spese per admin/proprietario
        $query = "SELECT s.*, u.nome, u.cognome 
                 FROM spese s
                 JOIN utenti u ON s.id_utente_inserimento = u.id_utente";
        
        // Aggiungi filtro per tipologia se specificato
        if (!empty($filtroTipologia)) {
            $query .= " WHERE s.tipologia = ?";
        }
        
        $query .= " ORDER BY s.data_spesa DESC";
        
        $stmt = $db->prepare($query);
        
        if (!empty($filtroTipologia)) {
            $stmt->execute([$filtroTipologia]);
        } else {
            $stmt->execute();
        }
    }
    
    $spese = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Ottieni i totali per tipologia
    $stmtTotali = $db->query("SELECT tipologia, SUM(importo) as totale FROM spese GROUP BY tipologia");
    $totaliTipologia = $stmtTotali->fetchAll(PDO::FETCH_KEY_PAIR);
    
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
    <title>Gestione Spese - Condominio</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/mobile-app.css">
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .filters {
            display: flex;
            overflow-x: auto;
            padding: 0 0 10px 0;
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
        
        .expense-type {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 20px;
            font-size: 0.75rem;
            color: white;
        }
        
        .expense-type-ordinaria {
            background-color: #4caf50;
        }
        
        .expense-type-straordinaria {
            background-color: #f44336;
        }
        
        .button-group {
            display: flex;
            margin-top: 20px;
            gap: 10px;
        }
        
        .button-group .btn {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 12px;
        }
        
        .button-group .btn i {
            font-size: 1.5rem;
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="app-header">
        <a href="<?= BASE_PATH ?>/dashboard.php" class="header-back">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div class="header-title">Gestione Spese</div>
        <?php if (isProprietario()): ?>
        <div class="header-actions">
            <a href="<?= BASE_PATH ?>/views/spese/create.php" class="btn-header-action">
                <i class="fas fa-plus"></i>
            </a>
        </div>
        <?php endif; ?>
    </header>
    
    <!-- Content -->
    <main class="app-content">
        <?php if (!empty($flashMessage)): ?>
            <div class="alert alert-<?= $flashType ?>">
                <?= $flashMessage ?>
            </div>
        <?php endif; ?>
        
        <!-- Riepilogo Spese -->
        <div class="stats-grid">
            <div class="stat-card">
                <i class="fas fa-money-bill-wave"></i>
                <div class="stat-value"><?= formatCurrency($totaliTipologia['Ordinaria'] ?? 0) ?></div>
                <div class="stat-label">Spese Ordinarie</div>
            </div>
            
            <div class="stat-card">
                <i class="fas fa-tools"></i>
                <div class="stat-value"><?= formatCurrency($totaliTipologia['Straordinaria'] ?? 0) ?></div>
                <div class="stat-label">Spese Straordinarie</div>
            </div>
        </div>
        
        <!-- Filtri -->
        <div class="filters">
            <a href="<?= BASE_PATH ?>/views/spese/index.php" class="filter-button <?= empty($filtroTipologia) ? 'active' : '' ?>">
                Tutte
            </a>
            <a href="<?= BASE_PATH ?>/views/spese/index.php?tipologia=Ordinaria" class="filter-button <?= $filtroTipologia === 'Ordinaria' ? 'active' : '' ?>">
                Ordinarie
            </a>
            <a href="<?= BASE_PATH ?>/views/spese/index.php?tipologia=Straordinaria" class="filter-button <?= $filtroTipologia === 'Straordinaria' ? 'active' : '' ?>">
                Straordinarie
            </a>
            <a href="<?= BASE_PATH ?>/views/acqua/index.php" class="filter-button">
                Acqua
            </a>
        </div>
        
        <!-- Elenco Spese -->
        <div class="app-card">
            <div class="app-card-header">
                <div class="card-header-title">
                    <?php if (!empty($filtroTipologia)): ?>
                        Spese <?= strtolower($filtroTipologia) ?>
                    <?php else: ?>
                        Tutte le spese
                    <?php endif; ?>
                </div>
                <?php if (isProprietario()): ?>
                <a href="<?= BASE_PATH ?>/views/spese/create.php" class="card-header-action">
                    <i class="fas fa-plus"></i>
                </a>
                <?php endif; ?>
            </div>
            
            <div class="app-card-content">
                <?php if (count($spese) > 0): ?>
                    <div class="list">
                        <?php foreach ($spese as $spesa): ?>
                            <a href="<?= BASE_PATH ?>/views/spese/view.php?id=<?= $spesa['id_spesa'] ?>" class="list-item clickable">
                                <div class="list-item-icon">
                                    <i class="fas fa-file-invoice-dollar"></i>
                                </div>
                                <div class="list-item-content">
                                    <div class="list-item-title"><?= htmlspecialchars($spesa['descrizione']) ?></div>
                                    <div class="list-item-subtitle">
                                        <?= date('d/m/Y', strtotime($spesa['data_spesa'])) ?> 
                                        <span class="expense-type expense-type-<?= strtolower($spesa['tipologia']) ?>">
                                            <?= $spesa['tipologia'] ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="list-item-action">
                                    <?php if (isset($spesa['importo_quota'])): ?>
                                        <?= formatCurrency($spesa['importo_quota']) ?>
                                    <?php else: ?>
                                        <?= formatCurrency($spesa['importo']) ?>
                                    <?php endif; ?>
                                    <i class="fas fa-chevron-right ml-2"></i>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-file-invoice-dollar empty-icon"></i>
                        <p>Nessuna spesa trovata</p>
                        <?php if (isProprietario()): ?>
                        <a href="<?= BASE_PATH ?>/views/spese/create.php" class="btn btn-primary mt-3">
                            <i class="fas fa-plus"></i> Aggiungi Spesa
                        </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if (isProprietario()): ?>
        <!-- Pulsanti Azione Rapida -->
        <div class="button-group">
            <a href="<?= BASE_PATH ?>/views/spese/create.php" class="btn btn-primary">
                <i class="fas fa-plus-circle"></i>
                <span>Nuova Spesa</span>
            </a>
            <a href="<?= BASE_PATH ?>/views/acqua/create.php" class="btn btn-primary">
                <i class="fas fa-tint"></i>
                <span>Spesa Acqua</span>
            </a>
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