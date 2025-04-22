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

// Recupera la categoria selezionata (se presente)
$categoria = isset($_GET['categoria']) ? $_GET['categoria'] : '';

// Recupera i documenti in base al ruolo dell'utente
$userRole = $_SESSION['user_role'];
$documenti = [];

try {
    if ($userRole === 'Affittuario') {
        // Gli affittuari vedono solo i documenti visibili a tutti
        $query = "SELECT d.*, u.nome, u.cognome 
                 FROM documenti d
                 JOIN utenti u ON d.id_utente_caricamento = u.id_utente
                 WHERE d.visibile_a = 'Tutti'";
        
        // Filtra per categoria se specificata
        if (!empty($categoria)) {
            $query .= " AND d.tipo_documento = :categoria";
        }
        
        $query .= " ORDER BY d.data_caricamento DESC";
        
        $stmt = $db->prepare($query);
        
        if (!empty($categoria)) {
            $stmt->bindParam(':categoria', $categoria);
        }
        
        $stmt->execute();
    } elseif ($userRole === 'Proprietario') {
        // I proprietari vedono i documenti visibili a tutti e ai proprietari
        $query = "SELECT d.*, u.nome, u.cognome 
                 FROM documenti d
                 JOIN utenti u ON d.id_utente_caricamento = u.id_utente
                 WHERE d.visibile_a IN ('Tutti', 'Solo proprietari')";
        
        // Filtra per categoria se specificata
        if (!empty($categoria)) {
            $query .= " AND d.tipo_documento = :categoria";
        }
        
        $query .= " ORDER BY d.data_caricamento DESC";
        
        $stmt = $db->prepare($query);
        
        if (!empty($categoria)) {
            $stmt->bindParam(':categoria', $categoria);
        }
        
        $stmt->execute();
    } else {
        // Gli amministratori vedono tutti i documenti
        $query = "SELECT d.*, u.nome, u.cognome 
                 FROM documenti d
                 JOIN utenti u ON d.id_utente_caricamento = u.id_utente";
        
        // Filtra per categoria se specificata
        if (!empty($categoria)) {
            $query .= " WHERE d.tipo_documento = :categoria";
        }
        
        $query .= " ORDER BY d.data_caricamento DESC";
        
        $stmt = $db->prepare($query);
        
        if (!empty($categoria)) {
            $stmt->bindParam(':categoria', $categoria);
        }
        
        $stmt->execute();
    }
    
    $documenti = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Recupera tutte le categorie disponibili per i filtri
    $stmtCategorie = $db->query("SELECT DISTINCT tipo_documento FROM documenti WHERE tipo_documento IS NOT NULL AND tipo_documento != '' ORDER BY tipo_documento");
    $categorie = $stmtCategorie->fetchAll(PDO::FETCH_COLUMN);
    
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
    <title>Documenti - Condominio</title>
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
        
        .document-icon {
            font-size: 1.5rem;
        }
        
        .doc-pdf { color: #f44336; }
        .doc-word { color: #2196f3; }
        .doc-excel { color: #4caf50; }
        .doc-image { color: #ff9800; }
        .doc-other { color: #9e9e9e; }
        
        .visibility-badge {
            font-size: 0.7rem;
            padding: 2px 6px;
            border-radius: 10px;
            background-color: #e0e0e0;
            color: #333;
            display: inline-block;
            margin-left: 5px;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="app-header">
        <a href="<?= BASE_PATH ?>/dashboard.php" class="header-back">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div class="header-title">Documenti</div>
        <?php if (isProprietario()): ?>
        <div class="header-actions">
            <a href="<?= BASE_PATH ?>/views/documenti/upload.php" class="btn-header-action">
                <i class="fas fa-upload"></i>
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
        <?php if (count($categorie) > 0): ?>
        <div class="filters">
            <a href="<?= BASE_PATH ?>/views/documenti/index.php" class="filter-button <?= empty($categoria) ? 'active' : '' ?>">
                Tutti
            </a>
            <?php foreach ($categorie as $cat): ?>
                <a href="<?= BASE_PATH ?>/views/documenti/index.php?categoria=<?= urlencode($cat) ?>" class="filter-button <?= $categoria === $cat ? 'active' : '' ?>">
                    <?= htmlspecialchars($cat) ?>
                </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <!-- Elenco Documenti -->
        <div class="app-card">
            <div class="app-card-header">
                <div class="card-header-title">
                    <?php if (!empty($categoria)): ?>
                        Documenti: <?= htmlspecialchars($categoria) ?>
                    <?php else: ?>
                        Tutti i documenti
                    <?php endif; ?>
                </div>
                <?php if (isProprietario()): ?>
                <a href="<?= BASE_PATH ?>/views/documenti/upload.php" class="card-header-action">
                    <i class="fas fa-upload"></i>
                </a>
                <?php endif; ?>
            </div>
            
            <div class="app-card-content">
                <?php if (count($documenti) > 0): ?>
                    <div class="list">
                        <?php foreach ($documenti as $documento): ?>
                            <?php
                            // Determina l'icona in base al tipo di file
                            $estensione = pathinfo($documento['percorso_file'], PATHINFO_EXTENSION);
                            $iconClass = 'doc-other';
                            $iconType = 'fa-file';
                            
                            if (in_array(strtolower($estensione), ['pdf'])) {
                                $iconClass = 'doc-pdf';
                                $iconType = 'fa-file-pdf';
                            } elseif (in_array(strtolower($estensione), ['doc', 'docx'])) {
                                $iconClass = 'doc-word';
                                $iconType = 'fa-file-word';
                            } elseif (in_array(strtolower($estensione), ['xls', 'xlsx'])) {
                                $iconClass = 'doc-excel';
                                $iconType = 'fa-file-excel';
                            } elseif (in_array(strtolower($estensione), ['jpg', 'jpeg', 'png', 'gif'])) {
                                $iconClass = 'doc-image';
                                $iconType = 'fa-file-image';
                            }
                            ?>
                            <a href="<?= BASE_PATH ?>/views/documenti/view.php?id=<?= $documento['id_documento'] ?>" class="list-item clickable">
                                <div class="list-item-icon">
                                    <i class="fas <?= $iconType ?> document-icon <?= $iconClass ?>"></i>
                                </div>
                                <div class="list-item-content">
                                    <div class="list-item-title">
                                        <?= htmlspecialchars($documento['titolo']) ?>
                                        <?php if (isProprietario() && $documento['visibile_a'] !== 'Tutti'): ?>
                                            <span class="visibility-badge">
                                                <?= $documento['visibile_a'] === 'Solo proprietari' ? 'Solo proprietari' : 'Solo admin' ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="list-item-subtitle">
                                        <?= date('d/m/Y', strtotime($documento['data_caricamento'])) ?> - 
                                        <?= htmlspecialchars($documento['tipo_documento'] ?: 'Generico') ?>
                                    </div>
                                </div>
                                <div class="list-item-action">
                                    <i class="fas fa-chevron-right"></i>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-file-alt empty-icon"></i>
                        <p>Nessun documento trovato</p>
                        <?php if (isProprietario()): ?>
                        <a href="<?= BASE_PATH ?>/views/documenti/upload.php" class="btn btn-primary mt-3">
                            <i class="fas fa-upload"></i> Carica Documento
                        </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
    
    <!-- Includi la barra di navigazione -->
<?php include_once $_SERVER['DOCUMENT_ROOT'] . BASE_PATH . '/includes/navbar.php'; ?>
    
    <!-- Floating Action Button (solo per proprietari) -->
    <?php if (isProprietario()): ?>
    <a href="<?= BASE_PATH ?>/views/documenti/upload.php" class="floating-action-btn">
        <i class="fas fa-upload"></i>
    </a>
    <?php endif; ?>
    
    <!-- JavaScript -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="<?= BASE_PATH ?>/assets/js/mobile-app.js"></script>
</body>
</html>