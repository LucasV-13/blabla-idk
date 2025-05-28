<?php
session_start();

// Gestion des textes multilingues
$language = isset($_SESSION['language']) ? $_SESSION['language'] : 'fr';
include('languages/' . $language . '.php');

// Vérification CSRF
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        // Tentative de CSRF détectée
        http_response_code(403);
        die($texts['unauthorized']);
    }
    
    // Détruire la session
    session_unset();
    session_destroy();
    
    // Rediriger vers la page d'accueil
    header("Location: index.php");
    exit();
} else {
    // Méthode incorrecte
    http_response_code(405);
    die($texts['method_not_allowed']);
}
?>