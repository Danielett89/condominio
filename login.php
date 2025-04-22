<?php
session_start();

// Definisci il percorso base
define('BASE_PATH', '/daniele/condominio');

// Funzioni base
function redirect($url) {
    header("Location: $url");
    exit;
}

function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Reindirizza alla dashboard se l'utente è già loggato
if (isLoggedIn()) {
    redirect(BASE_PATH . '/dashboard.php');
}

// Gestione del form di login
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Connessione al database
    try {
        $db = new PDO("mysql:host=31.11.39.173;dbname=Sql1693377_3;charset=utf8mb4", "Sql1693377", "S2zyEwzk\$ZnyJu");
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch(PDOException $e) {
        die("Errore di connessione al database: " . $e->getMessage());
    }
    
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];
    
    if (empty($email) || empty($password)) {
        $error = 'Inserisci sia email che password';
    } else {
        try {
            // Verifica dell'utente con password in chiaro
            $stmt = $db->prepare("SELECT id_utente, nome, cognome, email, password, ruolo, stato_account FROM utenti WHERE email = ? AND password = ?");
            $stmt->execute([$email, $password]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                // Verifica lo stato dell'account
                if ($user['stato_account'] === 'Approvato') {
                    // Login riuscito
                    $_SESSION['user_id'] = $user['id_utente'];
                    $_SESSION['user_name'] = $user['nome'] . ' ' . $user['cognome'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_role'] = $user['ruolo'];
                    
                    // Aggiorna l'ultimo accesso
                    $stmt = $db->prepare("UPDATE utenti SET ultimo_accesso = NOW() WHERE id_utente = ?");
                    $stmt->execute([$user['id_utente']]);
                    
                    // Reindirizza alla dashboard
                    redirect(BASE_PATH . '/dashboard.php');
                } else {
                    $error = 'Il tuo account è in attesa di approvazione';
                }
            } else {
                $error = 'Email o password non validi';
            }
        } catch(PDOException $e) {
            $error = 'Errore: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Accedi - Gestione Condominio</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #f5f7fa;
            color: #333;
            margin: 0;
            padding: 20px;
        }
        
        .container {
            max-width: 400px;
            margin: 50px auto;
            padding: 30px;
            background-color: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        h1 {
            color: #1976d2;
            text-align: center;
            margin-bottom: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            box-sizing: border-box;
        }
        
        button {
            width: 100%;
            padding: 14px;
            background-color: #1976d2;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
        }
        
        .error {
            background-color: #f44336;
            color: white;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .text-center {
            text-align: center;
        }
        
        a {
            color: #1976d2;
            text-decoration: none;
        }
        
        .login-info {
            margin-top: 30px;
            background-color: #e3f2fd;
            padding: 15px;
            border-radius: 8px;
            font-size: 14px;
        }
        
        .login-info p {
            margin: 5px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Accedi</h1>
        
        <?php if (!empty($error)): ?>
            <div class="error"><?= $error ?></div>
        <?php endif; ?>
        
        <form method="post" action="">
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <div class="form-group">
                <button type="submit">Accedi</button>
            </div>
        </form>
        
        <div class="login-info">
            <p><strong>Credenziali demo:</strong></p>
            <p>Email: admin@esempio.it</p>
            <p>Password: password123</p>
        </div>
        
        <div class="text-center">
            <p>Non hai un account? <a href="<?= BASE_PATH ?>/register.php">Registrati</a></p>
        </div>
    </div>
</body>
</html>