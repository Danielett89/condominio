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

// Recupera le manutenzioni
$manutenzioni = [];
$stato_filtro = isset($_GET['stato']) ? $_GET['stato'] : '';

try {
    $query = "SELECT m.*, u.nome, u.cognome 
              FROM manutenzioni m
              JOIN utenti u ON m.id_utente_inserimento = u.id_utente";
    
    // Aggiungi filtro per stato se specificato
    if (!empty($stato_filtro)) {
        $query .= " WHERE m.stato = :stato";
    }
    
    $query .= " ORDER BY m.data_inizio DESC";
    
    $stmt = $db->prepare($query);
    
    if (!empty($stato_filtro)) {
        $stmt->bindParam(':stato', $stato_filtro);
    }
    
    $stmt->execute();
    $manutenzioni = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
    <title>Manutenzioni - Condominio</title>
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
        
        .maintenance-card {
            margin-bottom: 15px;
            position: relative;
            overflow: hidden;
        }
        
        .maintenance-card .status-indicator {
            position: absolute;
            top: 0;
            bottom: 0;
            left: 0;
            width: 5px;
        }
        
        .maintenance-card .card-content {
            padding-left: 10px;
        }
        
        .status-pianificata { background-color: #ff9800; }
        .status-in-corso { background-color: #2196f3; }
        .status-completata { background-color: #4caf50; }
        .status-annullata { background-color: #f44336; }
        
        .maintenance-title {
            font-weight: 600;
            font-size: 1.1rem;
            margin-bottom: 5px;
        }
        
        .maintenance-dates {
            font-size: 0.8rem;
            color: #666;
            margin-bottom: 8px;
        }
        
        .maintenance-status {
            font-size: 0.8rem;
            padding: 3px 8px;
            border-radius: 20px;
            display: inline-block;
            margin-bottom: 8px;
        }
        
        .maintenance-cost {
            font-weight: 600;
            color: #333;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="app-header">
        <a href="<?= BASE_PATH ?>/dashboard.php" class="header-back">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div class="header-title">Manutenzioni</div>
        <?php if (isProprietario()): ?>
        <div class="header-actions">
            <a href="<?= BASE_PATH ?>/views/manutenzioni/create.php" class="btn-header-action">
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
        
        <!-- Filtri -->
        <div class="filters">
            <a href="<?= BASE_PATH ?>/views/manutenzioni/index.php" class="filter-button <?= empty($stato_filtro) ? 'active' : '' ?>">
                Tutte
            </a>
            <a href="<?= BASE_PATH ?>/views/manutenzioni/index.php?stato=Pianificata" class="filter-button <?= $stato_filtro === 'Pianificata' ? 'active' : '' ?>">
                Pianificate
            </a>
            <a href="<?= BASE_PATH ?>/views/manutenzioni/index.php?stato=In corso" class="filter-button <?= $stato_filtro === 'In corso' ? 'active' : '' ?>">
                In corso
            </a>
            <a href="<?= BASE_PATH ?>/views/manutenzioni/index.php?stato=Completata" class="filter-button <?= $stato_filtro === 'Completata' ? 'active' : '' ?>">
                Completate
            </a>
            <a href="<?= BASE_PATH ?>/views/manutenzioni/index.php?stato=Annullata" class="filter-button <?= $stato_filtro === 'Annullata' ? 'active' : '' ?>">
                Annullate
            </a>
        </div>
        
        <!-- Elenco Manutenzioni -->
        <?php if (count($manutenzioni) > 0): ?>
            <?php foreach ($manutenzioni as $manutenzione): ?>
                <a href="<?= BASE_PATH ?>/views/manutenzioni/view.php?id=<?= $manutenzione['id_manutenzione'] ?>" class="app-card maintenance-card clickable">
                    <div class="status-indicator status-<?= strtolower(str_replace(' ', '-', $manutenzione['stato'])) ?>"></div>
                    <div class="card-content">
                        <div class="maintenance-title"><?= htmlspecialchars($manutenzione['titolo']) ?></div>
                        <div class="maintenance-dates">
                            <i class="far fa-calendar-alt"></i> 
                            <?= date('d/m/Y', strtotime($manutenzione['data_inizio'])) ?>
                            <?php if (!empty($manutenzione['data_fine'])): ?>
                                - <?= date('d/m/Y', strtotime($manutenzione['data_fine'])) ?>
                            <?php endif; ?>
                        </div>
                        <div class="maintenance-status bg-<?= strtolower(str_replace(' ', '-', $manutenzione['stato'])) ?>">
                            <?= $manutenzione['stato'] ?>
                        </div>
                        <div class="maintenance-cost">
                            <?php if (!empty($manutenzione['costo_effettivo'])): ?>
                                Costo: <?= formatCurrency($manutenzione['costo_effettivo']) ?>
                            <?php elseif (!empty($manutenzione['costo_previsto'])): ?>
                                Preventivo: <?= formatCurrency($manutenzione['costo_previsto']) ?>
                            <?php else: ?>
                                Costo: N/D
                            <?php endif; ?>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-tools empty-icon"></i>
                <p>Nessuna manutenzione <?= !empty($stato_filtro) ? strtolower($stato_filtro) : '' ?> trovata</p>
                <?php if (isProprietario()): ?>
                    <a href="<?= BASE_PATH ?>/views/manutenzioni/create.php" class="btn btn-primary mt-3">
                        <i class="fas fa-plus"></i> Aggiungi Manutenzione
                    </a>
                <?php endif; ?>
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