<?php
session_start();

// Vérification de l'authentification
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit('Non autorisé');
}

// Gestion des textes multilingues
$language = isset($_SESSION['language']) ? $_SESSION['language'] : 'fr';
include('languages/' . $language . '.php');

// Vérification CSRF
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    http_response_code(403);
    exit($texts['invalid_csrf_token']);
}

// Récupérer les données
$preference_type = isset($_POST['preference']) ? $_POST['preference'] : null;
$preference_value = isset($_POST['value']) ? $_POST['value'] : null;

if (!$preference_type || $preference_value === null) {
    http_response_code(400);
    exit($texts['invalid_parameters']);
}

// Stocker simplement dans la session (version simplifiée sans base de données)
if ($preference_type === 'language') {
    $_SESSION['language'] = $preference_value;
} else {
    $_SESSION[$preference_type] = $preference_value;
}

// Répondre avec succès
http_response_code(200);
echo $texts['preferences_saved'];
?>