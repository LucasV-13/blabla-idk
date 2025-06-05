<?php 
// Connexion à la base de données avec PDO
// Informations de connexion
$servername = "localhost";  // Serveur local
$username = "user";         // Nom d'utilisateur
$password = "Eloi2023*";    // Mot de passe
$dbname = "bddthemind";     // Nom de la base de données

// Connexion avec PDO et gestion des erreurs
try {
    // Construction du DSN (Data Source Name)
    $dsn = "mysql:host={$servername};dbname={$dbname};charset=utf8mb4";
    
    // Options de PDO pour une meilleure gestion des erreurs et sécurité
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,      // Lève des exceptions en cas d'erreur
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,            // Retourne les résultats sous forme de tableaux associatifs
        PDO::ATTR_EMULATE_PREPARES   => false,                       // Désactive l'émulation des requêtes préparées
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"  // Force l'encodage utf8mb4
    ];
    
    // Création de l'instance PDO
    $conn = new PDO($dsn, $username, $password, $options);
    
} catch (PDOException $e) {
    // En production, enregistrer l'erreur dans un fichier log
    error_log("Erreur de connexion PDO: " . $e->getMessage());
    
    // Afficher un message d'erreur générique à l'utilisateur
    die("Une erreur est survenue lors de la connexion à la base de données. Veuillez réessayer plus tard.");
}
?>