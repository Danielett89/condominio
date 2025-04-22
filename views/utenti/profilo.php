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

function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
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

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'];
$utente = null;
$appartamento = null;
$error = '';
$success = '';

// Recupera i dati dell'utente e dell'appartamento
try {
    $stmt = $db->prepare("SELECT u.*, a.numero_interno, a.superficie_mq, a.terrazzo_mq, a.millesimi, a.numero_occupanti
                         FROM utenti u
                         LEFT JOIN appartamenti a ON u.id_appartamento = a.id_appartamento
                         WHERE u.id_utente = ?");
    $stmt->execute([$userId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        $utente = [
            'id_utente' => $result['id_utente'],
            'nome' => $result['nome'],
            'cognome' => $result['cognome'],
            'email' => $result['email'],
            'ruolo' => $result['ruolo'],
            'stato_account' => $result['stato_account'],
            'data_registrazione' => $result['data_registrazione'],
            'ultimo_accesso' => $result['ultimo_accesso']
        ];
        
        if ($result['numero_interno']) {
            $appartamento = [
                'numero_interno' => $result['numero_interno'],
                'superficie_mq' => $result['superficie_mq'],
                'terrazzo_mq' => $result['terrazzo_mq'],
                'millesimi' => $result['millesimi'],
                'numero_occupanti' => $result['numero_occupanti']
            ];
        }
    }
} catch(PDOException $e) {
    $error = "Errore nel recupero dei dati: " . $e->getMessage();
}

// Gestione form di aggiornamento password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_password'])) {
    try {
        $currentPassword = $_POST['current_password'];
        $newPassword = $_POST['new_password'];
        $confirmPassword = $_POST['confirm_password'];
        
        // Verifica che la nuova password corrisponda alla conferma
        if ($newPassword !== $confirmPassword) {
            throw new Exception("La nuova password e la conferma non corrispondono.");
        }
        
        // Verifica la password attuale
        $stmt = $db->prepare("SELECT password FROM utenti WHERE id_utente = ?");
        $stmt->execute([$userId]);
        $storedPassword = $stmt->fetchColumn();
        
        // Nel caso stessimo usando password in chiaro
        if ($currentPassword !== $storedPassword) {
            throw new Exception("La password attuale non è corretta.");
        }
        
        // Aggiorna la password
        $stmt = $db->prepare("UPDATE utenti SET password = ? WHERE id_utente = ?");
        $stmt->execute([$newPassword, $userId]);
        
        $success = "Password aggiornata con successo!";
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Gestione form di aggiornamento occupanti (solo per proprietari/amministratori)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_occupanti']) && ($userRole === 'Proprietario' || $userRole === 'Amministratore')) {
    try {
        $idAppartamento = $_POST['id_appartamento'];
        $numeroOccupanti = (int)$_POST['numero_occupanti'];
        
        if ($numeroOccupanti < 0) {
            throw new Exception("Il numero di occupanti non può essere negativo.");
        }
        
        // Aggiorna il numero di occupanti
        $stmt = $db->prepare("UPDATE appartamenti SET numero_occupanti = ? WHERE id_appartamento = ?");
        $stmt->execute([$numeroOccupanti, $idAppartamento]);
        
        $success = "Numero di occupanti aggiornato con successo!";
        
        // Aggiorna i dati visualizzati
        if ($appartamento) {
            $appartamento['numero_occupanti'] = $numeroOccupanti;
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
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
    <title>Profilo - Condominio</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/mobile-app.css">
    <style>
        .profile-header {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .profile-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background-color: var(--primary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 2rem;
            font-weight: bold;
            color: white;
        }
        
        .profile-name {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .profile-role {
            color: #666;
            font-size: 0.9rem;
        }
        
        .section-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 15px;
            color: var(--primary-color);
        }
        
        .logout-button {
            background-color: #f5f5f5;
            color: #f44336;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 12px;
            border-radius: 8px;
            margin-top: 20px;
            text-decoration: none;
            font-weight: 500;
        }
        
        .logout-button i {
            margin-right: 10px;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="app-header">
        <div class="header-title">Profilo Utente</div>
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
        
        <?php if ($utente): ?>
            <!-- Profilo Utente -->
            <div class="app-card">
                <div class="profile-header">
                    <div class="profile-avatar">
                        <?= strtoupper(substr($utente['nome'], 0, 1)) ?>
                    </div>
                    <div class="profile-name"><?= htmlspecialchars($utente['nome'] . ' ' . $utente['cognome']) ?></div>
                    <div class="profile-role"><?= $utente['ruolo'] ?></div>
                </div>
                
                <div class="detail-group">
                    <div class="detail-label">Email</div>
                    <div class="detail-value"><?= htmlspecialchars($utente['email']) ?></div>
                </div>
                
                <div class="detail-group">
                    <div class="detail-label">Stato Account</div>
                    <div class="detail-value">
                        <?php if ($utente['stato_account'] === 'Approvato'): ?>
                            <span class="badge bg-success">Approvato</span>
                        <?php elseif ($utente['stato_account'] === 'In attesa'): ?>
                            <span class="badge bg-warning">In attesa di approvazione</span>
                        <?php else: ?>
                            <span class="badge bg-danger">Rifiutato</span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="detail-group">
                    <div class="detail-label">Data Registrazione</div>
                    <div class="detail-value"><?= date('d/m/Y', strtotime($utente['data_registrazione'])) ?></div>
                </div>
                
                <?php if ($utente['ultimo_accesso']): ?>
                <div class="detail-group">
                    <div class="detail-label">Ultimo Accesso</div>
                    <div class="detail-value"><?= date('d/m/Y H:i', strtotime($utente['ultimo_accesso'])) ?></div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Dettagli Appartamento -->
            <?php if ($appartamento): ?>
            <div class="app-card mt-4">
                <div class="section-title">Dettagli Appartamento</div>
                
                <div class="detail-group">
                    <div class="detail-label">Interno</div>
                    <div class="detail-value highlight"><?= htmlspecialchars($appartamento['numero_interno']) ?></div>
                </div>
                
                <div class="detail-group">
                    <div class="detail-label">Superficie</div>
                    <div class="detail-value"><?= $appartamento['superficie_mq'] ?> mq</div>
                </div>
                
                <?php if ($appartamento['terrazzo_mq'] > 0): ?>
                <div class="detail-group">
                    <div class="detail-label">Terrazzo</div>
                    <div class="detail-value"><?= $appartamento['terrazzo_mq'] ?> mq</div>
                </div>
                <?php endif; ?>
                
                <div class="detail-group">
                    <div class="detail-label">Millesimi</div>
                    <div class="detail-value"><?= $appartamento['millesimi'] ?></div>
                </div>
                
                <div class="detail-group">
                    <div class="detail-label">Numero Occupanti</div>
                    <div class="detail-value"><?= $appartamento['numero_occupanti'] ?></div>
                </div>
                
                <?php if ($userRole === 'Proprietario' || $userRole === 'Amministratore'): ?>
                <!-- Form per aggiornare il numero di occupanti -->
                <form method="post" action="" class="mt-3">
                    <input type="hidden" name="id_appartamento" value="<?= $utente['id_appartamento'] ?>">
                    <div class="form-group">
                        <label for="numero_occupanti" class="form-label">Aggiorna Numero Occupanti</label>
                        <input type="number" class="form-control" id="numero_occupanti" name="numero_occupanti" value="<?= $appartamento['numero_occupanti'] ?>" min="0">
                    </div>
                    <button type="submit" name="update_occupanti" class="btn btn-primary btn-block">Aggiorna Occupanti</button>
                </form>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <!-- Cambio Password -->
            <div class="app-card mt-4">
                <div class="section-title">Cambia Password</div>
                
                <form method="post" action="">
                    <div class="form-group">
                        <label for="current_password" class="form-label">Password Attuale</label>
                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="new_password" class="form-label">Nuova Password</label>
                        <input type="password" class="form-control" id="new_password" name="new_password" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password" class="form-label">Conferma Nuova Password</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                    </div>
                    
                    <button type="submit" name="update_password" class="btn btn-primary btn-block">Aggiorna Password</button>
                </form>
            </div>
            
            <!-- Pulsante Logout -->
            <a href="<?= BASE_PATH ?>/logout.php" class="logout-button">
                <i class="fas fa-sign-out-alt"></i> Esci dall'Account
            </a>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-exclamation-triangle empty-icon"></i>
                <p>Impossibile caricare i dati dell'utente</p>
                <a href="<?= BASE_PATH ?>/dashboard.php" class="btn btn-primary mt-3">Torna alla Dashboard</a>
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