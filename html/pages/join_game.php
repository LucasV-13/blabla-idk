<?php
session_start();

// Gestion des textes multilingues
$language = isset($_SESSION['language']) ? $_SESSION['language'] : 'fr';
include('../languages/' . $language . '.php');

// Vérification de l'authentification
if (!isset($_SESSION['user_id']) || (isset($_SESSION['expires']) && $_SESSION['expires'] < time())) {
    header("Location: ../index.php");
    exit();
}

// Vérification CSRF
if (!isset($_GET['csrf_token']) || $_GET['csrf_token'] !== $_SESSION['csrf_token']) {
    header("Location: dashboard.php");
    exit();
}

// Récupérer l'ID de partie
$game_id = isset($_GET['game_id']) ? (int)$_GET['game_id'] : 0;

if ($game_id <= 0) {
    header("Location: dashboard.php");
    exit();
}

include('../connexion/connexion.php');

try {
    $user_id = $_SESSION['user_id'];
    
    // Vérifier si l'utilisateur fait déjà partie de la partie
    $checkQuery = "SELECT * FROM Utilisateurs_parties 
                  WHERE id_utilisateur = :user_id AND id_partie = :partie_id";
    $checkStmt = $conn->prepare($checkQuery);
    $checkStmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $checkStmt->bindParam(':partie_id', $game_id, PDO::PARAM_INT);
    $checkStmt->execute();
    
    if ($checkStmt->rowCount() > 0) {
        // L'utilisateur fait déjà partie de la partie, rediriger vers la page de jeu
        header("Location: plateau-jeu.php?partie_id=$game_id");
        exit();
    }
    
    // Vérifier si la partie est en attente et s'il reste de la place
    $partieQuery = "SELECT p.*, 
                   (SELECT COUNT(*) FROM Utilisateurs_parties WHERE id_partie = p.id) as joueurs_actuels
                   FROM Parties p
                   WHERE p.id = :partie_id AND p.status = 'en_attente'";
    $partieStmt = $conn->prepare($partieQuery);
    $partieStmt->bindParam(':partie_id', $game_id, PDO::PARAM_INT);
    $partieStmt->execute();
    
    if ($partie = $partieStmt->fetch(PDO::FETCH_ASSOC)) {
        if ($partie['joueurs_actuels'] < $partie['nombre_joueurs']) {
            // Trouver la position maximale actuelle
            $maxPosQuery = "SELECT MAX(position) as max_pos FROM Utilisateurs_parties WHERE id_partie = :partie_id";
            $maxPosStmt = $conn->prepare($maxPosQuery);
            $maxPosStmt->bindParam(':partie_id', $game_id, PDO::PARAM_INT);
            $maxPosStmt->execute();
            $maxPos = $maxPosStmt->fetch(PDO::FETCH_ASSOC)['max_pos'] ?? 0;
            
            // Ajouter l'utilisateur à la partie
            $newPosition = $maxPos + 1;
            $joinQuery = "INSERT INTO Utilisateurs_parties (id_utilisateur, id_partie, position) 
                         VALUES (:user_id, :partie_id, :position)";
            $joinStmt = $conn->prepare($joinQuery);
            $joinStmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $joinStmt->bindParam(':partie_id', $game_id, PDO::PARAM_INT);
            $joinStmt->bindParam(':position', $newPosition, PDO::PARAM_INT);
            $joinStmt->execute();
            
            // Rediriger vers la page de jeu
            header("Location: plateau-jeu.php?partie_id=$game_id");
            exit();
        } else {
            // La partie est déjà complète
            $_SESSION['error_message'] = $texts['error_message_full_game'];
            header("Location: dashboard.php");
            exit();
        }
    } else {
        // La partie n'existe pas ou n'est plus en attente
        $_SESSION['error_message'] = $texts['error_message_game_unavailable'];
        header("Location: dashboard.php");
        exit();
    }
    
} catch (PDOException $e) {
    error_log("Erreur join_game.php: " . $e->getMessage());
    $_SESSION['error_message'] = $texts['error_message_join_failed'];
    header("Location: dashboard.php");
    exit();
}
?>