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

// Controlla se l'ID è stato fornito
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    redirect(BASE_PATH . '/views/manutenzioni/index.php');
}

$idManutenzione = (int)$_GET['id'];

// Connessione al database
try {
    $db = new PDO("mysql:host=31.11.39.173;dbname=Sql1693377_3;charset=utf8mb4", "Sql1693377", "S2zyEwzk\$ZnyJu");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Errore di connessione al database: " . $e->getMessage());
}

// Recupera i dati della manutenzione
$manutenzione = null;

try {
    $stmt = $db->prepare("SELECT m.*, u.nome, u.cognome 
                          FROM manutenzioni m
                          JOIN utenti u ON m.id_utente_inserimento = u.id_utente
                          WHERE m.id_manutenzione = ?");
    $stmt->execute([$idManutenzione]);
    $manutenzione = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$manutenzione) {
        redirect(BASE_PATH . '/views/manutenzioni/index.php');
    }
} catch(PDOException $e) {
    die("Errore: " . $e->getMessage());
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
    <title>Dettaglio Manutenzione - Condominio</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/mobile-app.css">
    <style>
        .maintenance-status-banner {
            padding: 10px 15px;
            color: white;
            font-weight: 600;
            margin: -15px -15px 15px -15px;
            border-radius: 12px 12px 0 0;
        }
        
        .status-pianificata { background-color: #ff9800; }
        .status-in-corso { background-color: #2196f3; }
        .status-completata { background-color: #4caf50; }
        .status-annullata { background-color: #f44336; }
        
        .action-buttons {
            display: flex;
            margin-top: 20px;
            gap: 10px;
        }
        
        .action-buttons .btn {
            flex: 1;
        }
        
        .btn-outline {
            background-color: transparent;
            border: 1px solid #ddd;
            color: #333;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="app-header">
        <a href="<?= BASE_PATH ?>/views/manutenzioni/index.php" class="header-back">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div class="header-title">Dettaglio Manutenzione</div>
        <?php if (isProprietario()): ?>
        <div class="header-actions">
            <a href="<?= BASE_PATH ?>/views/manutenzioni/edit.php?id=<?= $idManutenzione ?>" class="btn-header-action">
                <i class="fas fa-edit"></i>
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
        
        <!-- Dettagli Manutenzione -->
        <div class="app-card">
            <div class="maintenance-status-banner status-<?= strtolower(str_replace(' ', '-', $manutenzione['stato'])) ?>">
                <?= $manutenzione['stato'] ?>
            </div>
            
            <div class="detail-group">
                <div class="detail-label">Titolo</div>
                <div class="detail-value highlight"><?= htmlspecialchars($manutenzione['titolo']) ?></div>
            </div>
            
            <div class="detail-group">
                <div class="detail-label">Descrizione</div>
                <div class="detail-value"><?= nl2br(htmlspecialchars($manutenzione['descrizione'])) ?></div>
            </div>
            
            <div class="detail-group">
                <div class="detail-label">Data Inizio</div>
                <div class="detail-value"><?= date('d/m/Y', strtotime($manutenzione['data_inizio'])) ?></div>
            </div>
            
            <?php if (!empty($manutenzione['data_fine'])): ?>
            <div class="detail-group">
                <div class="detail-label">Data Fine</div>
                <div class="detail-value"><?= date('d/m/Y', strtotime($manutenzione['data_fine'])) ?></div>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($manutenzione['costo_previsto'])): ?>
            <div class="detail-group">
                <div class="detail-label">Costo Previsto</div>
                <div class="detail-value"><?= formatCurrency($manutenzione['costo_previsto']) ?></div>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($manutenzione['costo_effettivo'])): ?>
            <div class="detail-group">
                <div class="detail-label">Costo Effettivo</div>
                <div class="detail-value highlight"><?= formatCurrency($manutenzione['costo_effettivo']) ?></div>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($manutenzione['fornitore'])): ?>
            <div class="detail-group">
                <div class="detail-label">Fornitore</div>
                <div class="detail-value"><?= htmlspecialchars($manutenzione['fornitore']) ?></div>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($manutenzione['contatto_fornitore'])): ?>
            <div class="detail-group">
                <div class="detail-label">Contatto Fornitore</div>
                <div class="detail-value">
                    <?php if (filter_var($manutenzione['contatto_fornitore'], FILTER_VALIDATE_EMAIL)): ?>
                        <a href="mailto:<?= htmlspecialchars($manutenzione['contatto_fornitore']) ?>">
                            <?= htmlspecialchars($manutenzione['contatto_fornitore']) ?>
                        </a>
                    <?php elseif (preg_match('/^\+?[0-9\s-]{8,}$/', $manutenzione['contatto_fornitore'])): ?>
                        <a href="tel:<?= preg_replace('/\s+/', '', $manutenzione['contatto_fornitore']) ?>">
                            <?= htmlspecialchars($manutenzione['contatto_fornitore']) ?>
                        </a>
                    <?php else: ?>
                        <?= htmlspecialchars($manutenzione['contatto_fornitore']) ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($manutenzione['note'])): ?>
            <div class="detail-group">
                <div class="detail-label">Note</div>
                <div class="detail-value note-text"><?= nl2br(htmlspecialchars($manutenzione['note'])) ?></div>
            </div>
            <?php endif; ?>
            
            <div class="detail-group">
                <div class="detail-label">Inserito da</div>
                <div class="detail-value"><?= htmlspecialchars($manutenzione['nome'] . ' ' . $manutenzione['cognome']) ?></div>
            </div>
            
            <div class="detail-group">
                <div class="detail-label">Data Inserimento</div>
                <div class="detail-value"><?= date('d/m/Y H:i', strtotime($manutenzione['data_inserimento'])) ?></div>
            </div>
            
            <?php if (isProprietario()): ?>
            <div class="action-buttons">
                <a href="<?= BASE_PATH ?>/views/manutenzioni/edit.php?id=<?= $idManutenzione ?>" class="btn btn-primary">
                    <i class="fas fa-edit"></i> Modifica
                </a>
                <a href="<?= BASE_PATH ?>/views/comunicazioni/create.php?ref=manutenzione&id=<?= $idManutenzione ?>" class="btn btn-outline">
                    <i class="fas fa-comment"></i> Comunica
                </a>
            </div>
            <?php endif; ?>
        </div>
    </main>
    
    <!-- Includi la barra di navigazione -->
<?php include_once $_SERVER['DOCUMENT_ROOT'] . BASE_PATH . '/includes/navbar.php'; ?>
    
    <!-- JavaScript -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="<?= BASE_PATH ?>/assets/js/mobile-app.js"></script>
</body>
</html>