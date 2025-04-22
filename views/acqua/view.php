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
    redirect(BASE_PATH . '/views/acqua/index.php');
}

$idSpesaAcqua = (int)$_GET['id'];

// Connessione al database
try {
    $db = new PDO("mysql:host=31.11.39.173;dbname=Sql1693377_3;charset=utf8mb4", "Sql1693377", "S2zyEwzk\$ZnyJu");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Errore di connessione al database: " . $e->getMessage());
}

// Recupera i dati della spesa acqua
$spesaAcqua = null;
$quote = [];
$userRole = $_SESSION['user_role'];
$userId = $_SESSION['user_id'];

try {
    // Recupera dettagli spesa
    $stmt = $db->prepare("SELECT sa.*, u.nome, u.cognome 
                          FROM spese_acqua sa
                          JOIN utenti u ON sa.id_utente_inserimento = u.id_utente
                          WHERE sa.id_spesa_acqua = ?");
    $stmt->execute([$idSpesaAcqua]);
    $spesaAcqua = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$spesaAcqua) {
        redirect(BASE_PATH . '/views/acqua/index.php');
    }
    
    // Recupera le quote
    if ($userRole === 'Affittuario') {
        // Solo la quota dell'affittuario
        $stmt = $db->prepare("SELECT qsa.*, a.numero_interno, a.millesimi
                              FROM quote_spese_acqua qsa
                              JOIN appartamenti a ON qsa.id_appartamento = a.id_appartamento
                              JOIN utenti u ON a.id_appartamento = u.id_appartamento
                              WHERE qsa.id_spesa_acqua = ? AND u.id_utente = ?");
        $stmt->execute([$idSpesaAcqua, $userId]);
    } else {
        // Tutte le quote per proprietari e admin
        $stmt = $db->prepare("SELECT qsa.*, a.numero_interno, a.millesimi
                              FROM quote_spese_acqua qsa
                              JOIN appartamenti a ON qsa.id_appartamento = a.id_appartamento
                              WHERE qsa.id_spesa_acqua = ?
                              ORDER BY a.numero_interno");
        $stmt->execute([$idSpesaAcqua]);
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
    <title>Dettaglio Spesa Acqua - Condominio</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/mobile-app.css">
    <style>
        .btn-outline {
            background-color: transparent;
            border: 1px solid #ddd;
            color: #333;
        }
        
        .btn-sm {
            padding: 8px 12px;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="app-header">
        <a href="<?= BASE_PATH ?>/views/acqua/index.php" class="header-back">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div class="header-title">Dettaglio Spesa Acqua</div>
        <?php if (isProprietario()): ?>
        <div class="header-actions">
            <a href="<?= BASE_PATH ?>/views/acqua/edit.php?id=<?= $idSpesaAcqua ?>" class="btn-header-action">
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
                <div class="card-header-title">Spesa Acqua</div>
            </div>
            
            <div class="app-card-content">
                <div class="detail-group">
                    <div class="detail-label">Data Lettura/Bolletta</div>
                    <div class="detail-value"><?= date('d/m/Y', strtotime($spesaAcqua['data_lettura'])) ?></div>
                </div>
                
                <div class="detail-group">
                    <div class="detail-label">Importo Totale</div>
                    <div class="detail-value highlight"><?= formatCurrency($spesaAcqua['importo_totale']) ?></div>
                </div>
                
                <div class="detail-group">
                    <div class="detail-label">Metodo Ripartizione</div>
                    <div class="detail-value">
                        <?php
                        switch ($spesaAcqua['metodo_ripartizione']) {
                            case 'Solo millesimi':
                                echo '<span class="badge bg-primary">100% Millesimi</span>';
                                break;
                            case 'Solo occupanti':
                                echo '<span class="badge bg-success">100% Occupanti</span>';
                                break;
                            case 'Misto':
                                echo '<span class="badge bg-info">30% Millesimi, 70% Occupanti</span>';
                                break;
                        }
                        ?>
                    </div>
                </div>
                
                <?php if (!empty($spesaAcqua['lettura_contatore'])): ?>
                <div class="detail-group">
                    <div class="detail-label">Lettura Contatore</div>
                    <div class="detail-value"><?= htmlspecialchars($spesaAcqua['lettura_contatore']) ?></div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($spesaAcqua['consumo_mc'])): ?>
                <div class="detail-group">
                    <div class="detail-label">Consumo</div>
                    <div class="detail-value"><?= $spesaAcqua['consumo_mc'] ?> mc</div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($spesaAcqua['documento'])): ?>
                <div class="detail-group">
                    <div class="detail-label">Bolletta</div>
                    <div class="detail-value">
                        <a href="<?= BASE_PATH . '/' . $spesaAcqua['documento'] ?>" class="btn btn-outline btn-sm" target="_blank">
                            <i class="fas fa-file-pdf"></i> Visualizza bolletta
                        </a>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="detail-group">
                    <div class="detail-label">Inserito da</div>
                    <div class="detail-value"><?= htmlspecialchars($spesaAcqua['nome'] . ' ' . $spesaAcqua['cognome']) ?></div>
                </div>
                
                <div class="detail-group">
                    <div class="detail-label">Data Inserimento</div>
                    <div class="detail-value"><?= date('d/m/Y H:i', strtotime($spesaAcqua['data_inserimento'])) ?></div>
                </div>
                
                <?php if (!empty($spesaAcqua['note'])): ?>
                <div class="detail-group">
                    <div class="detail-label">Note</div>
                    <div class="detail-value note-text"><?= nl2br(htmlspecialchars($spesaAcqua['note'])) ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Ripartizione Quote -->
        <div class="app-card">
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
                                    <?php if ($spesaAcqua['metodo_ripartizione'] === 'Misto'): ?>
                                    <th>Quota Mill.</th>
                                    <th>Quota Occ.</th>
                                    <?php endif; ?>
                                    <th>Stato</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($quote as $quota): ?>
                                <tr>
                                    <td class="text-nowrap"><?= $quota['numero_interno'] ?></td>
                                    <td class="text-nowrap"><?= formatCurrency($quota['importo_quota']) ?></td>
                                    <?php if ($spesaAcqua['metodo_ripartizione'] === 'Misto'): ?>
                                    <td class="text-nowrap"><?= formatCurrency($quota['quota_millesimi']) ?></td>
                                    <td class="text-nowrap"><?= formatCurrency($quota['quota_occupanti']) ?></td>
                                    <?php endif; ?>
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
                                    <?php if ($spesaAcqua['metodo_ripartizione'] === 'Misto'): ?>
                                    <th><?= formatCurrency(array_sum(array_column($quote, 'quota_millesimi'))) ?></th>
                                    <th><?= formatCurrency(array_sum(array_column($quote, 'quota_occupanti'))) ?></th>
                                    <?php endif; ?>
                                    <th></th>
                                </tr>
                            </tfoot>
                            <?php endif; ?>
                        </table>
                    </div>
                    
                    <!-- Dettagli Ripartizione -->
                    <div class="ripartizione-info mt-3">
                        <div class="detail-group">
                            <div class="detail-label">Totale Quote</div>
                            <div class="detail-value highlight">
                                <?= formatCurrency(array_sum(array_column($quote, 'importo_quota'))) ?>
                            </div>
                        </div>
                        
                        <?php if ($spesaAcqua['metodo_ripartizione'] === 'Misto'): ?>
                        <div class="detail-group">
                            <div class="detail-label">Quota Millesimi (30%)</div>
                            <div class="detail-value">
                                <?= formatCurrency($spesaAcqua['importo_totale'] * 0.3) ?>
                            </div>
                        </div>
                        
                        <div class="detail-group">
                            <div class="detail-label">Quota Occupanti (70%)</div>
                            <div class="detail-value">
                                <?= formatCurrency($spesaAcqua['importo_totale'] * 0.7) ?>
                            </div>
                        </div>
                        <?php endif; ?>
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