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

function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Verifica se l'utente è loggato e ha i permessi
if (!isLoggedIn() || !isProprietario()) {
    redirect(BASE_PATH . '/dashboard.php');
}

// Connessione al database
try {
    $db = new PDO("mysql:host=31.11.39.173;dbname=Sql1693377_3;charset=utf8mb4", "Sql1693377", "S2zyEwzk\$ZnyJu");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Errore di connessione al database: " . $e->getMessage());
}

// Ottieni gli appartamenti per la ripartizione
$appartamenti = [];
try {
    $stmt = $db->query("SELECT id_appartamento, numero_interno, millesimi, numero_occupanti FROM appartamenti ORDER BY numero_interno");
    $appartamenti = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    // Ignora errori
}

// Calcola totali per percentuali
$totalMillesimi = array_sum(array_column($appartamenti, 'millesimi'));
$totalOccupanti = array_sum(array_column($appartamenti, 'numero_occupanti'));

// Gestione del form
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Recupera i dati del form
        $dataLettura = $_POST['data_lettura'];
        $importoTotale = str_replace(',', '.', $_POST['importo_totale']);
        $metodoRipartizione = $_POST['metodo_ripartizione'];
        $letturaContatore = sanitize($_POST['lettura_contatore'] ?? '');
        $consumoMc = str_replace(',', '.', $_POST['consumo_mc'] ?? 0);
        $note = sanitize($_POST['note'] ?? '');
        $userId = $_SESSION['user_id'];
        
        // Validazione
        if (empty($dataLettura) || empty($importoTotale) || !is_numeric($importoTotale)) {
            throw new Exception("Tutti i campi obbligatori devono essere compilati correttamente.");
        }
        
        // Gestione del file bolletta
        $percorsoFile = null;
        if (isset($_FILES['bolletta']) && $_FILES['bolletta']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['bolletta'];
            
            // Controllo dimensione (max 10MB)
            if ($file['size'] > 10 * 1024 * 1024) {
                throw new Exception("Il file è troppo grande. La dimensione massima è 10MB.");
            }
            
            // Controllo estensione
            $estensione = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if ($estensione !== 'pdf') {
                throw new Exception("Solo file PDF sono accettati per le bollette.");
            }
            
            // Crea la directory se non esiste
            $uploadDir = $_SERVER['DOCUMENT_ROOT'] . BASE_PATH . '/uploads/bollette/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            // Genera un nome file unico
            $nomeFile = 'bolletta_acqua_' . date('Ymd') . '_' . uniqid() . '.pdf';
            $percorsoFile = 'uploads/bollette/' . $nomeFile;
            $percorsoCompleto = $_SERVER['DOCUMENT_ROOT'] . BASE_PATH . '/' . $percorsoFile;
            
            // Sposta il file
            if (!move_uploaded_file($file['tmp_name'], $percorsoCompleto)) {
                throw new Exception("Errore nel caricamento del file.");
            }
            
            // Se è stato caricato un file, caricalo anche come documento
            $titoloDocumento = "Bolletta Acqua - " . date('d/m/Y', strtotime($dataLettura));
            $stmt = $db->prepare("INSERT INTO documenti (titolo, descrizione, percorso_file, tipo_documento, id_utente_caricamento, visibile_a) 
                                VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$titoloDocumento, "Bolletta acqua del " . date('d/m/Y', strtotime($dataLettura)), $percorsoFile, "Bollette", $userId, "Tutti"]);
        }
        
        // Inizia la transazione
        $db->beginTransaction();
        
        // Inserisci la spesa acqua
        $stmt = $db->prepare("INSERT INTO spese_acqua (data_lettura, importo_totale, metodo_ripartizione, lettura_contatore, consumo_mc, note, id_utente_inserimento, documento) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$dataLettura, $importoTotale, $metodoRipartizione, $letturaContatore, $consumoMc, $note, $userId, $percorsoFile]);
        $idSpesaAcqua = $db->lastInsertId();
        
        // Calcola e inserisci le quote per ogni appartamento
        foreach ($appartamenti as $appartamento) {
            // Salta appartamenti senza occupanti se il metodo include occupanti
            if (($metodoRipartizione === 'Solo occupanti' || $metodoRipartizione === 'Misto') && $appartamento['numero_occupanti'] == 0) {
                continue;
            }
            
            $quotaMillesimi = 0;
            $quotaOccupanti = 0;
            $importoQuota = 0;
            
            // Calcola la quota in base al metodo di ripartizione
            switch ($metodoRipartizione) {
                case 'Solo millesimi':
                    // 100% in base ai millesimi
                    $importoQuota = $importoTotale * ($appartamento['millesimi'] / $totalMillesimi);
                    $quotaMillesimi = $importoQuota;
                    break;
                    
                case 'Solo occupanti':
                    // 100% in base agli occupanti
                    $importoQuota = $importoTotale * ($appartamento['numero_occupanti'] / $totalOccupanti);
                    $quotaOccupanti = $importoQuota;
                    break;
                    
                case 'Misto':
                    // 30% millesimi, 70% occupanti
                    $quotaMillesimi = ($importoTotale * 0.3) * ($appartamento['millesimi'] / $totalMillesimi);
                    $quotaOccupanti = ($importoTotale * 0.7) * ($appartamento['numero_occupanti'] / $totalOccupanti);
                    $importoQuota = $quotaMillesimi + $quotaOccupanti;
                    break;
            }
            
            // Inserisci la quota
            $stmt = $db->prepare("INSERT INTO quote_spese_acqua (id_spesa_acqua, id_appartamento, importo_quota, quota_millesimi, quota_occupanti, pagato) 
                                  VALUES (?, ?, ?, ?, ?, 0)");
            $stmt->execute([$idSpesaAcqua, $appartamento['id_appartamento'], $importoQuota, $quotaMillesimi, $quotaOccupanti]);
        }
        
        // Conferma la transazione
        $db->commit();
        
        // Redirect con messaggio di successo
        $_SESSION['flash_message'] = 'Spesa acqua aggiunta con successo!';
        $_SESSION['flash_type'] = 'success';
        redirect(BASE_PATH . '/views/acqua/view.php?id=' . $idSpesaAcqua);
        
    } catch (Exception $e) {
        // Annulla la transazione in caso di errore
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $error = "Errore: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <title>Nuova Spesa Acqua - Condominio</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/mobile-app.css">
    <style>
        .file-input-container {
            position: relative;
            overflow: hidden;
            margin-bottom: 10px;
        }
        
        .file-input-container input[type="file"] {
            position: absolute;
            font-size: 100px;
            right: 0;
            top: 0;
            opacity: 0;
            cursor: pointer;
        }
        
        .file-input-button {
            background-color: #f5f5f5;
            color: #333;
            border: 1px dashed #ccc;
            padding: 15px;
            text-align: center;
            border-radius: 8px;
        }
        
        .file-input-button i {
            font-size: 24px;
            color: #f44336;
            margin-bottom: 10px;
        }
        
        .file-input-button.has-file {
            border-color: var(--primary-color);
            background-color: rgba(25, 118, 210, 0.1);
        }
        
        .file-input-button.has-file i {
            color: var(--primary-color);
        }
        
        .selected-file {
            margin-top: 10px;
            display: none;
        }
        
        .selected-file.visible {
            display: block;
        }
        
        .file-name {
            font-weight: 500;
            word-break: break-all;
        }
        
        .file-info {
            font-size: 0.8rem;
            color: #666;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="app-header">
        <a href="<?= BASE_PATH ?>/views/acqua/index.php" class="header-back">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div class="header-title">Nuova Spesa Acqua</div>
    </header>
    
    <!-- Content -->
    <main class="app-content">
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger">
                <?= $error ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="alert alert-success">
                <?= $success ?>
            </div>
        <?php endif; ?>
        
        <div class="app-card">
            <div class="app-card-header">
                <div class="card-header-title">Inserisci Spesa Acqua</div>
            </div>
            
            <div class="app-card-content">
                <form method="post" action="" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="data_lettura" class="form-label">Data Lettura/Bolletta*</label>
                        <input type="date" class="form-control" id="data_lettura" name="data_lettura" required value="<?= date('Y-m-d') ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="importo_totale" class="form-label">Importo Totale (€)*</label>
                        <input type="text" class="form-control" id="importo_totale" name="importo_totale" required placeholder="0,00">
                    </div>
                    
                    <div class="form-group">
                        <label for="metodo_ripartizione" class="form-label">Metodo di Ripartizione*</label>
                        <select class="form-control" id="metodo_ripartizione" name="metodo_ripartizione" required>
                            <option value="Misto" selected>Misto (30% millesimi, 70% occupanti)</option>
                            <option value="Solo occupanti">Solo occupanti</option>
                            <option value="Solo millesimi">Solo millesimi</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="lettura_contatore" class="form-label">Lettura Contatore</label>
                        <input type="text" class="form-control" id="lettura_contatore" name="lettura_contatore" placeholder="Opzionale">
                    </div>
                    
                    <div class="form-group">
                        <label for="consumo_mc" class="form-label">Consumo (mc)</label>
                        <input type="text" class="form-control" id="consumo_mc" name="consumo_mc" placeholder="0,00">
                    </div>
                    
                    <div class="form-group">
                        <label for="bolletta" class="form-label">Bolletta PDF (opzionale)</label>
                        <div class="file-input-container">
                            <div class="file-input-button" id="fileInputButton">
                                <i class="fas fa-file-pdf"></i>
                                <div>Carica bolletta in PDF</div>
                            </div>
                            <input type="file" id="bolletta" name="bolletta" accept=".pdf">
                            
                            <div class="selected-file" id="selectedFile">
                                <div class="file-name" id="fileName"></div>
                                <div class="file-info" id="fileInfo"></div>
                            </div>
                        </div>
                        <small class="form-text text-muted">Carica la bolletta in formato PDF (max 10MB)</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="note" class="form-label">Note</label>
                        <textarea class="form-control" id="note" name="note" rows="3" placeholder="Note opzionali"></textarea>
                    </div>
                    
                    <div class="form-group mt-4">
                        <button type="submit" class="btn btn-primary btn-block">Salva Spesa Acqua</button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Anteprima Ripartizione -->
        <div class="app-card">
            <div class="app-card-header">
                <div class="card-header-title">Anteprima Ripartizione</div>
            </div>
            
            <div class="app-card-content">
                <div class="ripartizione-preview">
                    <p class="text-center mb-3">La ripartizione verrà calcolata automaticamente in base al metodo selezionato.</p>
                    
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Interno</th>
                                    <th>Millesimi</th>
                                    <th>Occupanti</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($appartamenti as $app): ?>
                                <tr>
                                    <td><?= $app['numero_interno'] ?></td>
                                    <td><?= $app['millesimi'] ?></td>
                                    <td><?= $app['numero_occupanti'] ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th>Totale</th>
                                    <th><?= $totalMillesimi ?></th>
                                    <th><?= $totalOccupanti ?></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
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
            // Gestisci il formato numerico per l'input dell'importo
            $('#importo_totale, #consumo_mc').on('input', function() {
                // Rimuovi tutti i caratteri eccetto numeri, punto e virgola
                let value = $(this).val().replace(/[^\d,\.]/g, '');
                
                // Sostituisci il punto con la virgola (formato italiano)
                value = value.replace(/\./g, ',');
                
                // Assicurati che ci sia solo una virgola
                const parts = value.split(',');
                if (parts.length > 2) {
                    value = parts[0] + ',' + parts.slice(1).join('');
                }
                
                // Limita i decimali a 2
                if (parts.length > 1 && parts[1].length > 2) {
                    value = parts[0] + ',' + parts[1].substring(0, 2);
                }
                
                $(this).val(value);
            });
            
            // Aggiorna l'interfaccia quando un file viene selezionato
            $('#bolletta').on('change', function() {
                const fileInput = this;
                const fileName = $('#fileName');
                const fileInfo = $('#fileInfo');
                const fileInputButton = $('#fileInputButton');
                const selectedFile = $('#selectedFile');
                
                if (fileInput.files && fileInput.files[0]) {
                    const file = fileInput.files[0];
                    
                    // Mostra il nome del file
                    fileName.text(file.name);
                    
                    // Mostra dimensione del file
                    const fileSize = (file.size / 1024).toFixed(2) + ' KB';
                    fileInfo.text('Dimensione: ' + fileSize);
                    
                    // Cambia stile del pulsante
                    fileInputButton.addClass('has-file');
                    fileInputButton.find('div').text('Bolletta selezionata');
                    fileInputButton.find('i').removeClass('fa-file-pdf').addClass('fa-check-circle');
                    
                    // Mostra info file
                    selectedFile.addClass('visible');
                } else {
                    // Reset
                    fileInputButton.removeClass('has-file');
                    fileInputButton.find('div').text('Carica bolletta in PDF');
                    fileInputButton.find('i').removeClass('fa-check-circle').addClass('fa-file-pdf');
                    selectedFile.removeClass('visible');
                }
            });
        });
    </script>
</body>
</html>