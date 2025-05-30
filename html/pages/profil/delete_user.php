<?php
session_start();

// Vérification de l'authentification et des permissions
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role']) !== 'admin') {
    http_response_code(403);
    exit('Accès non autorisé');
}

// Vérification CSRF
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    http_response_code(403);
    exit('Jeton CSRF invalide');
}

// Récupérer l'ID utilisateur à supprimer
$userId = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if ($userId <= 0) {
    http_response_code(400);
    exit('ID utilisateur invalide');
}

// Empêcher la suppression de son propre compte
if ($userId === (int)$_SESSION['user_id']) {
    http_response_code(400);
    exit('Vous ne pouvez pas supprimer votre propre compte');
}

include('../../connexion/connexion.php');

try {
    // Commencer une transaction
    $conn->beginTransaction();
    
    // Vérifier d'abord si l'utilisateur est administrateur dans des parties
    $adminPartiesQuery = "SELECT id_partie FROM Utilisateurs_parties WHERE id_utilisateur = :user_id AND position = 1";
    $adminPartiesStmt = $conn->prepare($adminPartiesQuery);
    $adminPartiesStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $adminPartiesStmt->execute();
    
    $adminParties = $adminPartiesStmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Si l'utilisateur est admin de parties, réassigner ou supprimer ces parties
    foreach ($adminParties as $partieId) {
        // Vérifier s'il y a d'autres joueurs dans la partie
        $otherPlayersQuery = "SELECT id_utilisateur FROM Utilisateurs_parties WHERE id_partie = :partie_id AND id_utilisateur != :user_id ORDER BY position LIMIT 1";
        $otherPlayersStmt = $conn->prepare($otherPlayersQuery);
        $otherPlayersStmt->bindParam(':partie_id', $partieId, PDO::PARAM_INT);
        $otherPlayersStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $otherPlayersStmt->execute();
        
        if ($newAdmin = $otherPlayersStmt->fetch(PDO::FETCH_ASSOC)) {
            // Assigner un nouveau admin
            $updateAdminQuery = "UPDATE Utilisateurs_parties SET position = 1 WHERE id_partie = :partie_id AND id_utilisateur = :new_admin_id";
            $updateAdminStmt = $conn->prepare($updateAdminQuery);
            $updateAdminStmt->bindParam(':partie_id', $partieId, PDO::PARAM_INT);
            $updateAdminStmt->bindParam(':new_admin_id', $newAdmin['id_utilisateur'], PDO::PARAM_INT);
            $updateAdminStmt->execute();
        } else {
            // Supprimer les parties où il est le seul joueur
            $deletePartieQuery = "DELETE FROM Parties WHERE id = :partie_id";
            $deletePartieStmt = $conn->prepare($deletePartieQuery);
            $deletePartieStmt->bindParam(':partie_id', $partieId, PDO::PARAM_INT);
            $deletePartieStmt->execute();
        }
    }
    
    // Supprimer d'abord les références dans les tables associées
    $tables = [
        'Cartes' => 'id_utilisateur',
        'Actions_jeu' => 'id_utilisateur',
        'Utilisateurs_parties' => 'id_utilisateur',
        'Statistiques' => 'id_utilisateur',
        'Scores' => 'id_utilisateur',
        'Preferences_utilisateurs' => 'id_utilisateur'
    ];
    
    foreach ($tables as $table => $column) {
        $stmt = $conn->prepare("DELETE FROM $table WHERE $column = :user_id");
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
    }
    
    // Enfin, supprimer l'utilisateur
    $stmt = $conn->prepare("DELETE FROM Utilisateurs WHERE id = :user_id");
    $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();
    
    // Valider la transaction
    $conn->commit();
    
    // Répondre avec succès
    $_SESSION['success_message'] = "Utilisateur supprimé avec succès";
    
    // Si la requête est AJAX, renvoyer une réponse JSON
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Utilisateur supprimé avec succès']);
    } else {
        // Sinon, rediriger
        header("Location: profil.php");
    }
    
} catch (PDOException $e) {
    // Annuler la transaction en cas d'erreur
    $conn->rollBack();
    error_log("Erreur suppression utilisateur: " . $e->getMessage());
    
    // Si la requête est AJAX, renvoyer une réponse JSON
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la suppression de l\'utilisateur']);
    } else {
        // Sinon, rediriger avec message d'erreur
        $_SESSION['error_message'] = "Erreur lors de la suppression de l'utilisateur";
        header("Location: profil.php");
    }
}
exit();
?>