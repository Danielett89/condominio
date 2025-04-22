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

function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'Amministratore';
}

// Verifica se l'utente è amministratore
if (!isLoggedIn() || !isAdmin()) {
    redirect(BASE_PATH . '/dashboard.php');
}

// Connessione al database
try {
    $db = new PDO("mysql:host=31.11.39.173;dbname=Sql1693377_3;charset=utf8mb4", "Sql1693377", "S2zyEwzk\$ZnyJu");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Errore di connessione al database: " . $e->getMessage());
}

// Recupera i dati dell'amministrazione
$utentiInAttesa = [];
$appartamenti = [];
$statistiche = [
    'totale_utenti' => 0,
    'totale_appartamenti' => 0,
    'totale_spese' => 0,
    'totale_segnalazioni' => 0
];

try {
    // Utenti in attesa di approvazione
    $stmt = $db->query("SELECT id_utente, nome, cognome, email, ruolo, data_registrazione FROM utenti WHERE stato_account = 'In attesa' ORDER BY data_registrazione DESC");
    $utentiInAttesa = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Recupera appartamenti
    $stmt = $db->query("SELECT a.*, u.nome, u.cognome 
                         FROM appartamenti a 
                         LEFT JOIN utenti u ON a.id_proprietario = u.id_utente 
                         ORDER BY a.numero_interno");
    $appartamenti = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Statistiche
    $stmt = $db->query("SELECT COUNT(*) FROM utenti");
    $statistiche['totale_utenti'] = $stmt->fetchColumn();
    
    $stmt = $db->query("SELECT COUNT(*) FROM appartamenti");
    $statistiche['totale_appartamenti'] = $stmt->fetchColumn();
    
    $stmt = $db->query("SELECT COUNT(*) FROM spese");
    $statistiche['totale_spese'] = $stmt->fetchColumn();
    
    $stmt = $db->query("SELECT COUNT(*) FROM segnalazioni");
    $statistiche['totale_segnalazioni'] = $stmt->fetchColumn();
    
} catch(PDOException $e) {
    // Ignora errori
}

// Gestione Approvazione/Rifiuto Utenti
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['azione']) && isset($_POST['id_utente'])) {
    $azione = $_POST['azione'];
    $idUtente = (int)$_POST['id_utente'];
    
    try {
        if ($azione === 'approva') {
            $stmt = $db->prepare("UPDATE utenti SET stato_account = 'Approvato' WHERE id_utente = ?");
            $stmt->execute([$idUtente]);
            $_SESSION['flash_message'] = 'Utente approvato con successo!';
            $_SESSION['flash_type'] = 'success';
        } elseif ($azione === 'rifiuta') {
            $stmt = $db->prepare("UPDATE utenti SET stato_account = 'Rifiutato' WHERE id_utente = ?");
            $stmt->execute([$idUtente]);
            $_SESSION['flash_message'] = 'Utente rifiutato!';
            $_SESSION['flash_type'] = 'warning';
        }
        
        // Ricarica la pagina per aggiornare i dati
        redirect(BASE_PATH . '/views/admin/index.php');
        
    } catch(PDOException $e) {
        $_SESSION['flash_message'] = 'Errore: ' . $e->getMessage();
        $_SESSION['flash_type'] = 'danger';
        redirect(BASE_PATH . '/views/admin/index.php');
    }
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
    <title>Amministrazione - Condominio</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/mobile-app.css">
    <style>
        .tab-navigation {
            display: flex;
            background-color: white;
            margin-bottom: 15px;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .tab-button {
            flex: 1;
            padding: 12px 5px;
            text-align: center;
            background: none;
            border: none;
            font-size: 0.9rem;
            color: #666;
            position: relative;
        }
        
        .tab-button.active {
            color: var(--primary-color);
            font-weight: 500;
        }
        
        .tab-button.active::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 3px;
            background-color: var(--primary-color);
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
            animation: fadeIn 0.3s;
        }
        
        .user-actions {
            display: flex;
            gap: 10px;
        }
        
        .user-actions form {
            flex: 1;
        }
        
        .btn-approve {
            background-color: #4caf50;
            color: white;
        }
        
        .btn-reject {
            background-color: #f44336;
            color: white;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="app-header">
        <a href="<?= BASE_PATH ?>/dashboard.php" class="header-back">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div class="header-title">Pannello Amministrazione</div>
    </header>
    
    <!-- Content -->
    <main class="app-content">
        <?php if (!empty($flashMessage)): ?>
            <div class="alert alert-<?= $flashType ?>">
                <?= $flashMessage ?>
            </div>
        <?php endif; ?>
        
        <!-- Statistiche -->
        <div class="stats-grid">
            <div class="stat-card">
                <i class="fas fa-users"></i>
                <div class="stat-value"><?= $statistiche['totale_utenti'] ?></div>
                <div class="stat-label">Utenti</div>
            </div>
            
            <div class="stat-card">
                <i class="fas fa-home"></i>
                <div class="stat-value"><?= $statistiche['totale_appartamenti'] ?></div>
                <div class="stat-label">Appartamenti</div>
            </div>
            
            <div class="stat-card">
                <i class="fas fa-file-invoice-dollar"></i>
                <div class="stat-value"><?= $statistiche['totale_spese'] ?></div>
                <div class="stat-label">Spese</div>
            </div>
            
            <div class="stat-card">
                <i class="fas fa-exclamation-circle"></i>
                <div class="stat-value"><?= $statistiche['totale_segnalazioni'] ?></div>
                <div class="stat-label">Segnalazioni</div>
            </div>
        </div>
        
        <!-- Tabs Navigation -->
        <div class="tab-navigation">
            <button class="tab-button active" data-target="tab-approvazioni">
                <i class="fas fa-user-check"></i> Approvazioni
            </button>
            <button class="tab-button" data-target="tab-appartamenti">
                <i class="fas fa-building"></i> Appartamenti
            </button>
            <button class="tab-button" data-target="tab-utenti">
                <i class="fas fa-users"></i> Utenti
            </button>
        </div>
        
        <!-- Tab Content: Approvazioni -->
        <div id="tab-approvazioni" class="tab-content active">
            <div class="app-card">
                <div class="app-card-header">
                    <div class="card-header-title">Utenti in Attesa</div>
                </div>
                
                <div class="app-card-content">
                    <?php if (count($utentiInAttesa) > 0): ?>
                        <div class="list">
                            <?php foreach ($utentiInAttesa as $utente): ?>
                                <div class="list-item">
                                    <div class="list-item-icon">
                                        <i class="fas fa-user"></i>
                                    </div>
                                    <div class="list-item-content">
                                        <div class="list-item-title"><?= htmlspecialchars($utente['nome'] . ' ' . $utente['cognome']) ?></div>
                                        <div class="list-item-subtitle">
                                            <?= htmlspecialchars($utente['email']) ?> - <?= $utente['ruolo'] ?>
                                        </div>
                                    </div>
                                    <div class="user-actions">
                                        <form method="post" action="">
                                            <input type="hidden" name="id_utente" value="<?= $utente['id_utente'] ?>">
                                            <input type="hidden" name="azione" value="approva">
                                            <button type="submit" class="btn btn-approve">
                                                <i class="fas fa-check"></i>
                                            </button>
                                        </form>
                                        <form method="post" action="">
                                            <input type="hidden" name="id_utente" value="<?= $utente['id_utente'] ?>">
                                            <input type="hidden" name="azione" value="rifiuta">
                                            <button type="submit" class="btn btn-reject">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-user-check empty-icon"></i>
                            <p>Nessun utente in attesa di approvazione</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Tab Content: Appartamenti -->
        <div id="tab-appartamenti" class="tab-content">
            <div class="app-card">
                <div class="app-card-header">
                    <div class="card-header-title">Gestione Appartamenti</div>
                    <a href="<?= BASE_PATH ?>/views/admin/appartamenti_edit.php" class="card-header-action">
                        <i class="fas fa-plus"></i>
                    </a>
                </div>
                
                <div class="app-card-content">
                    <?php if (count($appartamenti) > 0): ?>
                        <div class="list">
                            <?php foreach ($appartamenti as $appartamento): ?>
                                <a href="<?= BASE_PATH ?>/views/admin/appartamenti_edit.php?id=<?= $appartamento['id_appartamento'] ?>" class="list-item clickable">
                                    <div class="list-item-icon">
                                        <i class="fas fa-home"></i>
                                    </div>
                                    <div class="list-item-content">
                                        <div class="list-item-title"><?= htmlspecialchars($appartamento['numero_interno']) ?></div>
                                        <div class="list-item-subtitle">
                                            <?= $appartamento['superficie_mq'] ?> mq - <?= $appartamento['millesimi'] ?> mill.
                                        </div>
                                    </div>
                                    <div class="list-item-action">
                                        <?php if (!empty($appartamento['nome'])): ?>
                                            <?= htmlspecialchars($appartamento['nome'] . ' ' . $appartamento['cognome']) ?>
                                        <?php else: ?>
                                            <span class="text-muted">Nessun proprietario</span>
                                        <?php endif; ?>
                                        <i class="fas fa-chevron-right ml-2"></i>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-building empty-icon"></i>
                            <p>Nessun appartamento configurato</p>
                            <a href="<?= BASE_PATH ?>/views/admin/appartamenti_edit.php" class="btn btn-primary mt-3">
                                <i class="fas fa-plus"></i> Aggiungi Appartamento
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Tab Content: Utenti -->
        <div id="tab-utenti" class="tab-content">
            <div class="app-card">
                <div class="app-card-header">
                    <div class="card-header-title">Gestione Utenti</div>
                    <a href="<?= BASE_PATH ?>/views/admin/utenti_edit.php" class="card-header-action">
                        <i class="fas fa-plus"></i>
                    </a>
                </div>
                
                <div class="app-card-content">
                    <?php 
                    // Recupera tutti gli utenti approvati
                    try {
                        $stmt = $db->query("SELECT u.*, a.numero_interno 
                                           FROM utenti u 
                                           LEFT JOIN appartamenti a ON u.id_appartamento = a.id_appartamento 
                                           WHERE u.stato_account = 'Approvato' 
                                           ORDER BY u.cognome, u.nome");
                        $utenti = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    } catch(PDOException $e) {
                        $utenti = [];
                    }
                    ?>
                    
                    <?php if (count($utenti) > 0): ?>
                        <div class="list">
                            <?php foreach ($utenti as $utente): ?>
                                <a href="<?= BASE_PATH ?>/views/admin/utenti_edit.php?id=<?= $utente['id_utente'] ?>" class="list-item clickable">
                                    <div class="list-item-icon">
                                        <i class="fas fa-user"></i>
                                    </div>
                                    <div class="list-item-content">
                                        <div class="list-item-title"><?= htmlspecialchars($utente['nome'] . ' ' . $utente['cognome']) ?></div>
                                        <div class="list-item-subtitle">
                                            <?= htmlspecialchars($utente['email']) ?> - <?= $utente['ruolo'] ?>
                                        </div>
                                    </div>
                                    <div class="list-item-action">
                                        <?php if (!empty($utente['numero_interno'])): ?>
                                            <?= htmlspecialchars($utente['numero_interno']) ?>
                                        <?php else: ?>
                                            <span class="text-muted">Nessun appartamento</span>
                                        <?php endif; ?>
                                        <i class="fas fa-chevron-right ml-2"></i>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-users empty-icon"></i>
                            <p>Nessun utente trovato</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
    
    <!-- Includi la barra di navigazione -->
<?php include_once $_SERVER['DOCUMENT_ROOT'] . BASE_PATH . '/includes/navbar.php'; ?>
    
    <!-- JavaScript -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="<?= BASE_PATH ?>/assets/js/mobile-app.js"></script>
    <script>
        $(document).ready(function() {
            // Tab Navigation
            $('.tab-button').on('click', function() {
                // Rimuovi la classe active da tutti i pulsanti e i contenuti
                $('.tab-button').removeClass('active');
                $('.tab-content').removeClass('active');
                
                // Aggiungi la classe active al pulsante cliccato
                $(this).addClass('active');
                
                // Attiva il contenuto corrispondente
                const targetId = $(this).data('target');
                $('#' + targetId).addClass('active');
            });
        });
    </script>
</body>
</html>