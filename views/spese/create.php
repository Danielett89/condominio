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

// Gestione del form
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Recupera i dati del form
        $descrizione = sanitize($_POST['descrizione']);
        $importo = str_replace(',', '.', $_POST['importo']);
        $dataSpesa = $_POST['data_spesa'];
        $tipologia = $_POST['tipologia'];
        $metodoRipartizione = $_POST['metodo_ripartizione'];
        $note = sanitize($_POST['note'] ?? '');
        $userId = $_SESSION['user_id'];
        $documento = ''; // Gestione file documento non implementata in questa versione
        
        // Validazione
        if (empty($descrizione) || empty($importo) || !is_numeric($importo) || empty($dataSpesa)) {
            throw new Exception("Tutti i campi obbligatori devono essere compilati correttamente.");
        }
        
        // Inizia la transazione
        $db->beginTransaction();
        
        // Inserisci la spesa
        $stmt = $db->prepare("INSERT INTO spese (descrizione, importo, data_spesa, tipologia, metodo_ripartizione, documento, note, id_utente_inserimento) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$descrizione, $importo, $dataSpesa, $tipologia, $metodoRipartizione, $documento, $note, $userId]);
        $idSpesa = $db->lastInsertId();
        
        // Calcola e inserisci le quote per ogni appartamento
        foreach ($appartamenti as $appartamento) {
            $importoQuota = 0;
            
            // Calcola la quota in base al metodo di ripartizione
            switch ($metodoRipartizione) {
                case 'Millesimi':
                    // 100% in base ai millesimi
                    $importoQuota = $importo * ($appartamento['millesimi'] / $totalMillesimi);
                    break;
            }
            
            // Inserisci la quota
            $stmt = $db->prepare("INSERT INTO quote_spese (id_spesa, id_appartamento, importo_quota, pagato) 
                                  VALUES (?, ?, ?, 0)");
            $stmt->execute([$idSpesa, $appartamento['id_appartamento'], $importoQuota]);
        }
        
        // Conferma la transazione
        $db->commit();
        
        // Redirect con messaggio di successo
        $_SESSION['flash_message'] = 'Spesa aggiunta con successo!';
        $_SESSION['flash_type'] = 'success';
        redirect(BASE_PATH . '/views/spese/view.php?id=' . $idSpesa);
        
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
    <title>Nuova Spesa - Condominio</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/mobile-app.css">
</head>
<body>
    <!-- Header -->
    <header class="app-header">
        <a href="<?= BASE_PATH ?>/views/spese/index.php" class="header-back">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div class="header-title">Nuova Spesa</div>
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
                <div class="card-header-title">Inserisci Spesa</div>
            </div>
            
            <div class="app-card-content">
                <form method="post" action="">
                    <div class="form-group">
                        <label for="descrizione" class="form-label">Descrizione*</label>
                        <input type="text" class="form-control" id="descrizione" name="descrizione" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="importo" class="form-label">Importo Totale (€)*</label>
                        <input type="text" class="form-control" id="importo" name="importo" required placeholder="0,00">
                    </div>
                    
                    <div class="form-group">
                        <label for="data_spesa" class="form-label">Data Spesa*</label>
                        <input type="date" class="form-control" id="data_spesa" name="data_spesa" required value="<?= date('Y-m-d') ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="tipologia" class="form-label">Tipologia*</label>
                        <select class="form-control" id="tipologia" name="tipologia" required>
                            <option value="Ordinaria" selected>Ordinaria</option>
                            <option value="Straordinaria">Straordinaria</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="metodo_ripartizione" class="form-label">Metodo di Ripartizione*</label>
                        <select class="form-control" id="metodo_ripartizione" name="metodo_ripartizione" required>
                            <option value="Millesimi" selected>In base ai millesimi</option>
                            <!-- Potrebbero essere aggiunti altri metodi di ripartizione in futuro -->
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="note" class="form-label">Note</label>
                        <textarea class="form-control" id="note" name="note" rows="3" placeholder="Note opzionali"></textarea>
                    </div>
                    
                    <div class="form-group mt-4">
                        <button type="submit" class="btn btn-primary btn-block">Salva Spesa</button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Anteprima Ripartizione -->
        <div class="app-card mt-4">
            <div class="app-card-header">
                <div class="card-header-title">Anteprima Ripartizione</div>
            </div>
            
            <div class="app-card-content">
                <div class="ripartizione-preview">
                    <p class="text-center mb-3">La ripartizione verrà calcolata automaticamente in base ai millesimi.</p>
                    
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Interno</th>
                                    <th>Millesimi</th>
                                    <th>Percentuale</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($appartamenti as $app): ?>
                                <tr>
                                    <td><?= $app['numero_interno'] ?></td>
                                    <td><?= $app['millesimi'] ?></td>
                                    <td><?= number_format(($app['millesimi'] / $totalMillesimi) * 100, 2) ?>%</td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th>Totale</th>
                                    <th><?= $totalMillesimi ?></th>
                                    <th>100%</th>
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
            $('#importo').on('input', function() {
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
            
            // Calcolo importi preventivi quando cambia l'importo totale
            $('#importo').on('input', function() {
                calcolaPreventivo();
            });
            
            function calcolaPreventivo() {
                const importoTotale = parseFloat($('#importo').val().replace(',', '.')) || 0;
                const totalMillesimi = <?= $totalMillesimi ?>;
                
                <?php foreach ($appartamenti as $index => $app): ?>
                const millesimi<?= $index ?> = <?= $app['millesimi'] ?>;
                const quota<?= $index ?> = importoTotale * (millesimi<?= $index ?> / totalMillesimi);
                <?php endforeach; ?>
            }
        });
    </script>
</body>
</html>