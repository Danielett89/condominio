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

// Recupera i dati per la dashboard
$userId = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'];

// Ottieni dati generali
$totalSpese = 0;
$speseRecenti = [];
$ultimaSpesaAcqua = null;
$prossimiInterventi = [];
$messaggiRecenti = [];
$importoTotaleSpese = 0;
$interno = '';

try {
    // Conteggio totale spese
    $stmt = $db->query("SELECT COUNT(*) FROM spese");
    $totalSpese = $stmt->fetchColumn();
    
    // Importo totale spese
    $stmt = $db->query("SELECT SUM(importo) FROM spese");
    $importoTotaleSpese = $stmt->fetchColumn() ?: 0;
    
    // Ottieni l'appartamento dell'utente (se non è admin/proprietario)
    if ($userRole === 'Affittuario') {
        $stmt = $db->prepare("SELECT a.numero_interno FROM appartamenti a 
                               JOIN utenti u ON a.id_appartamento = u.id_appartamento 
                               WHERE u.id_utente = ?");
        $stmt->execute([$userId]);
        $interno = $stmt->fetchColumn();
        
        // Filtra le spese per appartamento
        $stmt = $db->prepare("SELECT s.*, qs.importo_quota FROM spese s 
                              JOIN quote_spese qs ON s.id_spesa = qs.id_spesa 
                              JOIN appartamenti a ON qs.id_appartamento = a.id_appartamento
                              JOIN utenti u ON a.id_appartamento = u.id_appartamento
                              WHERE u.id_utente = ?
                              ORDER BY s.data_spesa DESC LIMIT 5");
        $stmt->execute([$userId]);
    } else {
        // Ottieni tutte le spese per admin/proprietario
        $stmt = $db->query("SELECT * FROM spese ORDER BY data_spesa DESC LIMIT 5");
    }
    $speseRecenti = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Ultima spesa acqua
    $stmt = $db->query("SELECT * FROM spese_acqua ORDER BY data_lettura DESC LIMIT 1");
    $ultimaSpesaAcqua = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Prossime manutenzioni
    $stmt = $db->query("SELECT * FROM manutenzioni 
                         WHERE data_inizio >= CURDATE() AND stato != 'Annullata'
                         ORDER BY data_inizio ASC LIMIT 3");
    $prossimiInterventi = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Messaggi recenti
    if ($userRole === 'Affittuario') {
        $stmt = $db->prepare("SELECT m.*, u.nome, u.cognome 
                              FROM messaggi m 
                              JOIN utenti u ON m.id_utente = u.id_utente 
                              WHERE m.visibile_a = 'Tutti'
                              ORDER BY m.data_invio DESC LIMIT 3");
        $stmt->execute();
    } else {
        $stmt = $db->query("SELECT m.*, u.nome, u.cognome 
                            FROM messaggi m 
                            JOIN utenti u ON m.id_utente = u.id_utente 
                            ORDER BY m.data_invio DESC LIMIT 3");
    }
    $messaggiRecenti = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
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
    <title>Dashboard - Gestione Condominio</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/mobile-app.css">
    <style>
        .document-icon {
            font-size: 1.2rem;
        }
        
        .doc-pdf { color: #f44336; }
        .doc-word { color: #2196f3; }
        .doc-excel { color: #4caf50; }
        .doc-image { color: #ff9800; }
        .doc-other { color: #9e9e9e; }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="app-header">
        <div class="header-title">Dashboard</div>
        <div class="header-actions">
            <button class="btn-notification" id="btnNotification">
                <i class="fas fa-bell"></i>
            </button>
        </div>
    </header>
    
    <!-- Content -->
    <main class="app-content">
        <!-- Welcome Card -->
        <div class="welcome-card">
            <h2>Benvenuto, <?= $_SESSION['user_name'] ?></h2>
            <p><?= $userRole === 'Affittuario' ? 'Interno: ' . $interno : 'Visualizzazione completa' ?></p>
        </div>
        
        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <i class="fas fa-file-invoice-dollar"></i>
                <div class="stat-value"><?= $totalSpese ?></div>
                <div class="stat-label">Spese Totali</div>
            </div>
            
            <div class="stat-card">
                <i class="fas fa-euro-sign"></i>
                <div class="stat-value"><?= formatCurrency($importoTotaleSpese) ?></div>
                <div class="stat-label">Importo Totale</div>
            </div>
            
            <div class="stat-card">
                <i class="fas fa-tint"></i>
                <div class="stat-value">
                    <?= $ultimaSpesaAcqua ? formatCurrency($ultimaSpesaAcqua['importo_totale']) : '0,00 €' ?>
                </div>
                <div class="stat-label">Ultima Acqua</div>
            </div>
            
            <div class="stat-card">
                <i class="fas fa-tools"></i>
                <div class="stat-value"><?= count($prossimiInterventi) ?></div>
                <div class="stat-label">Interventi</div>
            </div>
        </div>
        
        <!-- Spese Recenti -->
        <div class="app-card">
            <div class="app-card-header">
                <div class="card-header-title">Spese Recenti</div>
                <a href="<?= BASE_PATH ?>/views/spese/index.php" class="card-header-action">
                    <i class="fas fa-arrow-right"></i>
                </a>
            </div>
            
            <div class="app-card-content">
                <?php if (count($speseRecenti) > 0): ?>
                    <div class="list">
                        <?php foreach ($speseRecenti as $spesa): ?>
                            <div class="list-item">
                                <div class="list-item-icon">
                                    <i class="fas fa-file-invoice-dollar"></i>
                                </div>
                                <div class="list-item-content">
                                    <div class="list-item-title"><?= htmlspecialchars($spesa['descrizione']) ?></div>
                                    <div class="list-item-subtitle">
                                        <?= date('d/m/Y', strtotime($spesa['data_spesa'])) ?> - 
                                        <?= $spesa['tipologia'] ?>
                                    </div>
                                </div>
                                <div class="list-item-action">
                                    <?= formatCurrency($spesa['importo']) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-file-invoice-dollar empty-icon"></i>
                        <p>Nessuna spesa registrata</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Documenti Recenti -->
        <div class="app-card">
            <div class="app-card-header">
                <div class="card-header-title">Documenti Recenti</div>
                <a href="<?= BASE_PATH ?>/views/documenti/index.php" class="card-header-action">
                    <i class="fas fa-arrow-right"></i>
                </a>
            </div>
            
            <div class="app-card-content">
                <?php
                // Recupera i documenti recenti
                try {
                    if ($userRole === 'Affittuario') {
                        $stmt = $db->query("SELECT d.*, u.nome, u.cognome 
                                            FROM documenti d
                                            JOIN utenti u ON d.id_utente_caricamento = u.id_utente
                                            WHERE d.visibile_a = 'Tutti'
                                            ORDER BY d.data_caricamento DESC LIMIT 3");
                    } elseif ($userRole === 'Proprietario') {
                        $stmt = $db->query("SELECT d.*, u.nome, u.cognome 
                                            FROM documenti d
                                            JOIN utenti u ON d.id_utente_caricamento = u.id_utente
                                            WHERE d.visibile_a IN ('Tutti', 'Solo proprietari')
                                            ORDER BY d.data_caricamento DESC LIMIT 3");
                    } else {
                        $stmt = $db->query("SELECT d.*, u.nome, u.cognome 
                                            FROM documenti d
                                            JOIN utenti u ON d.id_utente_caricamento = u.id_utente
                                            ORDER BY d.data_caricamento DESC LIMIT 3");
                    }
                    $documentiRecenti = $stmt->fetchAll(PDO::FETCH_ASSOC);
                } catch(PDOException $e) {
                    $documentiRecenti = [];
                }
                ?>
                
                <?php if (count($documentiRecenti) > 0): ?>
                    <div class="list">
                        <?php foreach ($documentiRecenti as $documento): ?>
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
                                    <div class="list-item-title"><?= htmlspecialchars($documento['titolo']) ?></div>
                                    <div class="list-item-subtitle">
                                        <?= date('d/m/Y', strtotime($documento['data_caricamento'])) ?>
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
                        <p>Nessun documento presente</p>
                        <?php if (isProprietario()): ?>
                        <a href="<?= BASE_PATH ?>/views/documenti/upload.php" class="btn btn-primary mt-3">
                            <i class="fas fa-upload"></i> Carica Documento
                        </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Prossimi Interventi -->
        <div class="app-card">
            <div class="app-card-header">
                <div class="card-header-title">Prossimi Interventi</div>
                <a href="<?= BASE_PATH ?>/views/manutenzioni/index.php" class="card-header-action">
                    <i class="fas fa-arrow-right"></i>
                </a>
            </div>
            
            <div class="app-card-content">
                <?php if (count($prossimiInterventi) > 0): ?>
                    <div class="list">
                        <?php foreach ($prossimiInterventi as $intervento): ?>
                            <div class="list-item">
                                <div class="list-item-icon">
                                    <i class="fas fa-tools"></i>
                                </div>
                                <div class="list-item-content">
                                    <div class="list-item-title"><?= htmlspecialchars($intervento['titolo']) ?></div>
                                    <div class="list-item-subtitle">
                                        <?= date('d/m/Y', strtotime($intervento['data_inizio'])) ?> - 
                                        <?= $intervento['stato'] ?>
                                    </div>
                                </div>
                                <div class="list-item-action">
                                    <a href="<?= BASE_PATH ?>/views/manutenzioni/view.php?id=<?= $intervento['id_manutenzione'] ?>">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-tools empty-icon"></i>
                        <p>Nessun intervento programmato</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Messaggi Recenti -->
        <div class="app-card">
            <div class="app-card-header">
                <div class="card-header-title">Comunicazioni Recenti</div>
                <a href="<?= BASE_PATH ?>/views/comunicazioni/index.php" class="card-header-action">
                    <i class="fas fa-arrow-right"></i>
                </a>
            </div>
            
            <div class="app-card-content">
                <?php if (count($messaggiRecenti) > 0): ?>
                    <div class="list">
                        <?php foreach ($messaggiRecenti as $messaggio): ?>
                            <div class="list-item">
                                <div class="list-item-icon chat-avatar">
                                    <?= strtoupper(substr($messaggio['nome'], 0, 1)) ?>
                                </div>
                                <div class="list-item-content">
                                    <div class="list-item-title">
                                        <?= htmlspecialchars($messaggio['nome'] . ' ' . $messaggio['cognome']) ?>
                                    </div>
                                    <div class="list-item-subtitle message-preview">
                                        <?= substr(htmlspecialchars($messaggio['messaggio']), 0, 50) . (strlen($messaggio['messaggio']) > 50 ? '...' : '') ?>
                                    </div>
                                </div>
                                <div class="list-item-timestamp">
                                    <?= date('d/m H:i', strtotime($messaggio['data_invio'])) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-comments empty-icon"></i>
                        <p>Nessun messaggio</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <?php if (isProprietario()): ?>
        <div class="quick-actions">
            <h3>Azioni Rapide</h3>
            <div class="action-buttons">
                <a href="<?= BASE_PATH ?>/views/spese/create.php" class="action-button">
                    <i class="fas fa-plus-circle"></i>
                    <span>Nuova Spesa</span>
                </a>
                <a href="<?= BASE_PATH ?>/views/acqua/create.php" class="action-button">
                    <i class="fas fa-tint"></i>
                    <span>Spesa Acqua</span>
                </a>
                <a href="<?= BASE_PATH ?>/views/manutenzioni/create.php" class="action-button">
                    <i class="fas fa-tools"></i>
                    <span>Manutenzione</span>
                </a>
                <a href="<?= BASE_PATH ?>/views/documenti/upload.php" class="action-button">
                    <i class="fas fa-file-upload"></i>
                    <span>Documento</span>
                </a>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Calendario Raccolta Differenziata -->
<div class="app-card">
    <div class="app-card-header">
        <div class="card-header-title">Calendario Raccolta</div>
        <a href="<?= BASE_PATH ?>/views/calendario/index.php" class="card-header-action">
            <i class="fas fa-arrow-right"></i>
        </a>
    </div>
    
    <div class="app-card-content">
        <?php
        // Determina il giorno corrente
        $giornoCorrente = date('N'); // 1 (Lunedì) a 7 (Domenica)
        $giorniSettimana = ['', 'Lunedi', 'Martedi', 'Mercoledi', 'Giovedi', 'Venerdi', 'Sabato', 'Domenica'];
        $giornoCorrenteNome = $giorniSettimana[$giornoCorrente];
        
        // Carica dati raccolta per il giorno corrente
        try {
            $stmt = $db->prepare("SELECT * FROM raccolta_differenziata WHERE giorno = ? ORDER BY orario_tipo DESC");
            $stmt->execute([$giornoCorrenteNome]);
            $raccoltaOggi = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            // Dati statici se la tabella non esiste
            $raccoltaOggi = [];
            
            if ($giornoCorrenteNome === 'Lunedi') {
                $raccoltaOggi[] = ['tipo_rifiuto' => 'Indifferenziato', 'orario_limite' => '06:30:00', 'orario_tipo' => 'mattina', 'colore' => '#666666'];
            } elseif ($giornoCorrenteNome === 'Martedi') {
                $raccoltaOggi[] = ['tipo_rifiuto' => 'Organico', 'orario_limite' => '20:00:00', 'orario_tipo' => 'sera', 'colore' => '#8bc34a'];
                $raccoltaOggi[] = ['tipo_rifiuto' => 'Plastica e Metallo', 'orario_limite' => '06:30:00', 'orario_tipo' => 'mattina', 'colore' => '#ffeb3b'];
            } elseif ($giornoCorrenteNome === 'Mercoledi') {
                $raccoltaOggi[] = ['tipo_rifiuto' => 'Carta e Cartone', 'orario_limite' => '06:30:00', 'orario_tipo' => 'mattina', 'colore' => '#2196f3'];
            } elseif ($giornoCorrenteNome === 'Giovedi') {
                $raccoltaOggi[] = ['tipo_rifiuto' => 'Organico', 'orario_limite' => '20:00:00', 'orario_tipo' => 'sera', 'colore' => '#8bc34a'];
                $raccoltaOggi[] = ['tipo_rifiuto' => 'Indifferenziato', 'orario_limite' => '06:30:00', 'orario_tipo' => 'mattina', 'colore' => '#666666'];
            } elseif ($giornoCorrenteNome === 'Venerdi') {
                $raccoltaOggi[] = ['tipo_rifiuto' => 'Plastica e Metallo', 'orario_limite' => '06:30:00', 'orario_tipo' => 'mattina', 'colore' => '#ffeb3b'];
            } elseif ($giornoCorrenteNome === 'Sabato') {
                $raccoltaOggi[] = ['tipo_rifiuto' => 'Organico', 'orario_limite' => '20:00:00', 'orario_tipo' => 'sera', 'colore' => '#8bc34a'];
                $raccoltaOggi[] = ['tipo_rifiuto' => 'Carta e Cartone', 'orario_limite' => '06:30:00', 'orario_tipo' => 'mattina', 'colore' => '#2196f3'];
            }
        }
        
        // Carica dati raccolta per domani
        $giornoSuccessivo = $giornoCorrente == 7 ? 1 : $giornoCorrente + 1;
        $giornoSuccessivoNome = $giorniSettimana[$giornoSuccessivo];
        
        try {
            $stmt = $db->prepare("SELECT * FROM raccolta_differenziata WHERE giorno = ? ORDER BY orario_tipo DESC");
            $stmt->execute([$giornoSuccessivoNome]);
            $raccoltaDomani = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            // Dati statici se la tabella non esiste
            $raccoltaDomani = [];
            
            if ($giornoSuccessivoNome === 'Lunedi') {
                $raccoltaDomani[] = ['tipo_rifiuto' => 'Indifferenziato', 'orario_limite' => '06:30:00', 'orario_tipo' => 'mattina', 'colore' => '#666666'];
            } elseif ($giornoSuccessivoNome === 'Martedi') {
                $raccoltaDomani[] = ['tipo_rifiuto' => 'Organico', 'orario_limite' => '20:00:00', 'orario_tipo' => 'sera', 'colore' => '#8bc34a'];
                $raccoltaDomani[] = ['tipo_rifiuto' => 'Plastica e Metallo', 'orario_limite' => '06:30:00', 'orario_tipo' => 'mattina', 'colore' => '#ffeb3b'];
            } elseif ($giornoSuccessivoNome === 'Mercoledi') {
                $raccoltaDomani[] = ['tipo_rifiuto' => 'Carta e Cartone', 'orario_limite' => '06:30:00', 'orario_tipo' => 'mattina', 'colore' => '#2196f3'];
            } elseif ($giornoSuccessivoNome === 'Giovedi') {
                $raccoltaDomani[] = ['tipo_rifiuto' => 'Organico', 'orario_limite' => '20:00:00', 'orario_tipo' => 'sera', 'colore' => '#8bc34a'];
                $raccoltaDomani[] = ['tipo_rifiuto' => 'Indifferenziato', 'orario_limite' => '06:30:00', 'orario_tipo' => 'mattina', 'colore' => '#666666'];
            } elseif ($giornoSuccessivoNome === 'Venerdi') {
                $raccoltaDomani[] = ['tipo_rifiuto' => 'Plastica e Metallo', 'orario_limite' => '06:30:00', 'orario_tipo' => 'mattina', 'colore' => '#ffeb3b'];
            } elseif ($giornoSuccessivoNome === 'Sabato') {
                $raccoltaDomani[] = ['tipo_rifiuto' => 'Organico', 'orario_limite' => '20:00:00', 'orario_tipo' => 'sera', 'colore' => '#8bc34a'];
                $raccoltaDomani[] = ['tipo_rifiuto' => 'Carta e Cartone', 'orario_limite' => '06:30:00', 'orario_tipo' => 'mattina', 'colore' => '#2196f3'];
            }
        }
        ?>
        
        <div style="display: flex; flex-direction: column; gap: 15px;">
            <!-- Oggi -->
            <div>
                <h3 class="mb-2">Oggi - <?= $giornoCorrenteNome ?></h3>
                <?php if (count($raccoltaOggi) > 0): ?>
                    <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                        <?php foreach ($raccoltaOggi as $item): ?>
                            <span style="display: inline-flex; align-items: center; background-color: <?= $item['colore'] ?>; color: white; padding: 5px 10px; border-radius: 15px; font-size: 0.9rem;">
                                <?php
                                $icon = 'fa-trash';
                                switch ($item['tipo_rifiuto']) {
                                    case 'Organico':
                                        $icon = 'fa-apple-whole';
                                        break;
                                    case 'Plastica e Metallo':
                                        $icon = 'fa-bottle-water';
                                        break;
                                    case 'Carta e Cartone':
                                        $icon = 'fa-newspaper';
                                        break;
                                    case 'Vetro':
                                        $icon = 'fa-wine-bottle';
                                        break;
                                }
                                ?>
                                <i class="fas <?= $icon ?> mr-2"></i>
                                <?= $item['tipo_rifiuto'] ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted">Nessuna raccolta prevista</p>
                <?php endif; ?>
            </div>
            
            <!-- Domani -->
            <div>
                <h3 class="mb-2">Domani - <?= $giornoSuccessivoNome ?></h3>
                <?php if (count($raccoltaDomani) > 0): ?>
                    <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                        <?php foreach ($raccoltaDomani as $item): ?>
                            <span style="display: inline-flex; align-items: center; background-color: <?= $item['colore'] ?>; color: white; padding: 5px 10px; border-radius: 15px; font-size: 0.9rem;">
                                <?php
                                $icon = 'fa-trash';
                                switch ($item['tipo_rifiuto']) {
                                    case 'Organico':
                                        $icon = 'fa-apple-whole';
                                        break;
                                    case 'Plastica e Metallo':
                                        $icon = 'fa-bottle-water';
                                        break;
                                    case 'Carta e Cartone':
                                        $icon = 'fa-newspaper';
                                        break;
                                    case 'Vetro':
                                        $icon = 'fa-wine-bottle';
                                        break;
                                }
                                ?>
                                <i class="fas <?= $icon ?> mr-2"></i>
                                <?= $item['tipo_rifiuto'] ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted">Nessuna raccolta prevista</p>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="text-center mt-3">
            <a href="<?= BASE_PATH ?>/views/calendario/index.php" class="btn btn-outline">
                Visualizza Calendario Completo
            </a>
        </div>
    </div>
</div>
    </main>
    
   <!-- Includi la barra di navigazione -->
<?php include_once $_SERVER['DOCUMENT_ROOT'] . BASE_PATH . '/includes/navbar.php'; ?>
    
    <!-- Floating Action Button (solo per proprietari e admin) -->
    <?php if (isProprietario()): ?>
    <button class="floating-action-btn" id="fab">
        <i class="fas fa-plus"></i>
    </button>
    
    <!-- FAB Menu -->
    <div class="fab-menu" id="fabMenu">
        <a href="<?= BASE_PATH ?>/views/spese/create.php" class="fab-item">
            <i class="fas fa-file-invoice-dollar"></i>
            <span>Nuova Spesa</span>
        </a>
        <a href="<?= BASE_PATH ?>/views/acqua/create.php" class="fab-item">
            <i class="fas fa-tint"></i>
            <span>Spesa Acqua</span>
        </a>
        <a href="<?= BASE_PATH ?>/views/documenti/upload.php" class="fab-item">
            <i class="fas fa-file-upload"></i>
            <span>Carica Documento</span>
        </a>
        <a href="<?= BASE_PATH ?>/views/manutenzioni/create.php" class="fab-item">
            <i class="fas fa-tools"></i>
            <span>Nuova Manutenzione</span>
        </a>
        <a href="<?= BASE_PATH ?>/views/comunicazioni/create.php" class="fab-item">
            <i class="fas fa-comment-dots"></i>
            <span>Nuovo Messaggio</span>
        </a>
    </div>
    <?php endif; ?>
    
    <!-- JavaScript -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="<?= BASE_PATH ?>/assets/js/mobile-app.js"></script>
</body>
</html>