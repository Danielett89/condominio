<?php
// Parametri di connessione al database
$host = '31.11.39.173';
$dbname = 'Sql1693377_3';
$username = 'Sql1693377';
$password = 'S2zyEwzk$ZnyJu';

try {
    // Creazione della connessione PDO
    $db = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    
    // Imposta la modalità di errore su eccezioni
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Imposta il fetch mode predefinito a FETCH_ASSOC
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    // In caso di errore, visualizza un messaggio e termina lo script
    die("Errore di connessione al database: " . $e->getMessage());
}