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
    redirect(BASE_PATH . '/views/spese/index.php');
}

$idSpesa = (int)$_GET['id'];

// Connessione al database
try {
    $db = new PDO("mysql:host=31.11.39.173;dbname=Sql1693377_3;charset=utf8mb4", "Sql1693377", "S2zyEwzk\$ZnyJu");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Errore di connessione al database: " . $e->getMessage());
}

// Recupera i dati della spesa
$spesa = null;
$quote = [];
$userRole = $_SESSION['user_role'];
$userId = $_SESSION['user_id'];

try {
    // Recupera dettagli spesa
    $stmt = $db->prepare("SELECT s.*, u.nome, u.cognome 
                          FROM spese s
                          JOIN utenti u ON s.id_utente_inserimento = u.id_utente
                          WHERE s.id_spesa = ?");
    $stmt->execute([$idSpesa]);
    $spesa = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$spesa) {
        redirect(BASE_PATH . '/views/spese/index.php');
    }
    
    // Recupera le quote
    if ($userRole === 'Affittuario') {
        // Solo la quota dell'affittuario
        $stmt = $db->prepare("SELECT qs.*, a.numero_interno, a.millesimi
                              FROM quote_spese qs
                              JOIN appartamenti a ON qs.id_appartamento = a.id_appartamento
                              JOIN utenti u ON a.id_appartamento = u.id_appartamento
                              WHERE qs.id_spesa = ? AND u.id_utente = ?");
        $stmt->execute([$idSpesa, $userId]);
    } else {
        // Tutte le quote per proprietari e admin
        $stmt = $db->prepare("SELECT qs.*, a.numero_interno, a.millesimi
                              FROM quote_spese qs
                              JOIN appartamenti a ON qs.id_appartamento = a.id_appartamento
                              WHERE qs.id_spesa = ?
                              ORDER BY a.numero_interno");
        $stmt->execute([$idSpesa]);
    }
    
    $quote = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
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
    <title>Dettaglio Spesa - Condominio</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/mobile-app.css">
    <style>
        .expense-type {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            color: white;
            margin-bottom: 10px;
        }
        
        .expense-type-ordinaria {
            background-color: #4caf50;
        }
        
        .expense-type-straordinaria {
            background-color: #f44336;
        }
        
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
        <a href="<?= BASE_PATH ?>/views/spese/index.php" class="header-back">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div class="header-title">Dettaglio Spesa</div>
        <?php if (isProprietario()): ?>
        <div class="header-actions">
            <a href="<?= BASE_PATH ?>/views/spese/edit.php?id=<?= $idSpesa ?>" class="btn-header-action">
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
        
        <!-- Dettagli Spesa -->
        <div class="app-card">
            <div class="app-card-header">
                <div class="card-header-title">Dettagli Spesa</div>
            </div>
            
            <div class="app-card-content">
                <span class="expense-type expense-type-<?= strtolower($spesa['tipologia']) ?>">
                    <?= $spesa['tipologia'] ?>
                </span>
                
                <div class="detail-group">
                    <div class="detail-label">Descrizione</div>
                    <div class="detail-value highlight"><?= htmlspecialchars($spesa['descrizione']) ?></div>
                </div>
                
                <div class="detail-group">
                    <div class="detail-label">Importo Totale</div>
                    <div class="detail-value highlight"><?= formatCurrency($spesa['importo']) ?></div>
                </div>
                
                <div class="detail-group">
                    <div class="detail-label">Data Spesa</div>
                    <div class="detail-value"><?= date('d/m/Y', strtotime($spesa['data_spesa'])) ?></div>
                </div>
                
                <div class="detail-group">
                    <div class="detail-label">Metodo Ripartizione</div>
                    <div class="detail-value"><?= $spesa['metodo_ripartizione'] ?></div>
                </div>
                
                <div class="detail-group">
                    <div class="detail-label">Inserito da</div>
                    <div class="detail-value"><?= htmlspecialchars($spesa['nome'] . ' ' . $spesa['cognome']) ?></div>
                </div>
                
                <div class="detail-group">
                    <div class="detail-label">Data Inserimento</div>
                    <div class="detail-value"><?= date('d/m/Y H:i', strtotime($spesa['data_inserimento'])) ?></div>
                </div>
                
                <?php if (!empty($spesa['note'])): ?>
                <div class="detail-group">
                    <div class="detail-label">Note</div>
                    <div class="detail-value note-text"><?= nl2br(htmlspecialchars($spesa['note'])) ?></div>
                </div>
                <?php endif; ?>
                
                <?php if (isProprietario()): ?>
                <div class="action-buttons">
                    <a href="<?= BASE_PATH ?>/views/spese/edit.php?id=<?= $idSpesa ?>" class="btn btn-primary">
                        <i class="fas fa-edit"></i> Modifica
                    </a>
                    <a href="<?= BASE_PATH ?>/views/comunicazioni/create.php?ref=spesa&id=<?= $idSpesa ?>" class="btn btn-outline">
                        <i class="fas fa-comment"></i> Comunica
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Ripartizione Quote -->
        <div class="app-card mt-4">
            <div class="app-card-header">
                <div class="card-header-title">Ripartizione Quote</div>
            </div>
            
            <div class="app-card-content">
                <?php if (count($quote) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Interno</th>
                                    <th>Importo</th>
                                    <th>Stato</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($quote as $quota): ?>
                                <tr>
                                    <td><?= $quota['numero_interno'] ?></td>
                                    <td><?= formatCurrency($quota['importo_quota']) ?></td>
                                    <td>
                                        <?php if ($quota['pagato']): ?>
                                            <span class="badge bg-success">Pagato</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning">Da pagare</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <?php if (count($quote) > 1): ?>
                            <tfoot>
                                <tr>
                                    <th>Totale</th>
                                    <th><?= formatCurrency(array_sum(array_column($quote, 'importo_quota'))) ?></th>
                                    <th></th>
                                </tr>
                            </tfoot>
                            <?php endif; ?>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-exclamation-triangle empty-icon"></i>
                        <p>Nessuna quota trovata per questa spesa</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
    
    <!-- Includi la barra di navigazione -->
<?php include_once $_SERVER['DOCUMENT_ROOT'] . BASE_PATH . '/includes/navbar.php'; ?>
    
    <!-- JavaScript -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="<?= BASE_PATH ?>/assets/js/mobile-app.js"></script>
</body>
</html>