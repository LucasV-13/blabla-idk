<?php
session_start();

// Vérification de l'authentification
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

// Vérification CSRF
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    header("Location: ../dashboard.php");
    exit();
}

// Récupérer les paramètres du formulaire
$gameTitle = isset($_POST['gameTitle']) ? htmlspecialchars($_POST['gameTitle']) : 'Partie sans nom';
$playerCount = isset($_POST['playerCount']) ? (int)$_POST['playerCount'] : 2;
$difficultyLevel = isset($_POST['difficultyLevel']) ? htmlspecialchars($_POST['difficultyLevel']) : 'medium';
$gamePrivacy = isset($_POST['gamePrivacy']) ? htmlspecialchars($_POST['gamePrivacy']) : 'public';
$startingLevel = isset($_POST['startingLevel']) ? (int)$_POST['startingLevel'] : 1;

// Valider les données
if ($playerCount < 2 || $playerCount > 4) {
    $playerCount = 2;
}

if ($startingLevel < 1 || $startingLevel > 12) {
    $startingLevel = 1;
}

// Définir le nombre de vies et de shurikens selon la difficulté et le nombre de joueurs
$vies = 2; // Valeur par défaut
$shurikens = 1; // Valeur par défaut

if ($playerCount == 2) {
    $vies = 3; // Règle du jeu: 2 joueurs = 3 vies
}

// Ajuster selon la difficulté
if ($difficultyLevel == 'easy') {
    $vies += 1;
} elseif ($difficultyLevel == 'hard') {
    $vies -= 1;
    if ($vies < 1) $vies = 1; // Minimum 1 vie
}

include('../connexion/connexion.php');

try {
    // Commencer une transaction
    $conn->beginTransaction();
    
    // Créer la partie
    $createPartieQuery = "INSERT INTO Parties (nom, niveau, nombre_joueurs, vies_restantes, shurikens_restants, 
                         difficulte, status, prive, date_creation) 
                         VALUES (:nom, :niveau, :nombre_joueurs, :vies, :shurikens, :difficulte, 'en_attente', :prive, NOW())";
    $createPartieStmt = $conn->prepare($createPartieQuery);
    $createPartieStmt->bindParam(':nom', $gameTitle, PDO::PARAM_STR);
    $createPartieStmt->bindParam(':niveau', $startingLevel, PDO::PARAM_INT);
    $createPartieStmt->bindParam(':nombre_joueurs', $playerCount, PDO::PARAM_INT);
    $createPartieStmt->bindParam(':vies', $vies, PDO::PARAM_INT);
    $createPartieStmt->bindParam(':shurikens', $shurikens, PDO::PARAM_INT);
    $createPartieStmt->bindParam(':difficulte', $difficultyLevel, PDO::PARAM_STR);
    $prive = ($gamePrivacy == 'private') ? 1 : 0;
    $createPartieStmt->bindParam(':prive', $prive, PDO::PARAM_INT);
    $createPartieStmt->execute();
    
    // Récupérer l'ID de la nouvelle partie
    $partie_id = $conn->lastInsertId();
    
    // Ajouter le créateur comme premier joueur (administrateur position = 1)
    $addUserQuery = "INSERT INTO Utilisateurs_parties (id_utilisateur, id_partie, position) 
                    VALUES (:user_id, :partie_id, 1)";
    $addUserStmt = $conn->prepare($addUserQuery);
    $addUserStmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
    $addUserStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
    $addUserStmt->execute();
    
    // Valider la transaction
    $conn->commit();
    
    // Rediriger vers la page de jeu
    header("Location: ../plateau-jeu.php?partie_id=$partie_id");
    exit();
    
} catch (PDOException $e) {
    // En cas d'erreur, annuler la transaction
    $conn->rollBack();
    error_log("Erreur create_game.php: " . $e->getMessage());
    $_SESSION['error_message'] = "Une erreur est survenue lors de la création de la partie.";
    header("Location: ../profil.php");
    exit();
}
?>