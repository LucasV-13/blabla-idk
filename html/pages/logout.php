<?php
session_start();

// Gestion des textes multilingues
$language = isset($_SESSION['language']) ? $_SESSION['language'] : 'fr';

// Définir les textes par défaut d'abord
$texts = [
    'unauthorized' => 'Accès non autorisé',
    'method_not_allowed' => 'Méthode non autorisée',
    'logout_success' => 'Déconnexion réussie',
    'logout_error' => 'Erreur lors de la déconnexion'
];

// Charger le fichier de langue s'il existe
$language_file = '../languages/' . $language . '.php';
if (file_exists($language_file)) {
    include($language_file);
}

// Vérification CSRF
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        // Tentative de CSRF détectée
        http_response_code(403);
        die($texts['unauthorized']);
    }
    
    // Détruire la session de manière sécurisée
    // Nettoyer toutes les variables de session
    $_SESSION = array();
    
    // Supprimer le cookie de session
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Détruire la session
    session_destroy();
    
    // Rediriger vers la page d'accueil
    header("Location: ../index.php");
    exit();
} else {
    // Méthode incorrecte
    http_response_code(405);
    die($texts['method_not_allowed']);
}
?>