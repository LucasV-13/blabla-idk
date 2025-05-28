<?php
session_start();

// Assurez-vous que les variables de session essentielles sont définies
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1; // Utilisateur par défaut
    $_SESSION['username'] = 'Testeur';
    $_SESSION['role'] = 'joueur';
    $_SESSION['email'] = 'test@example.com';
    $_SESSION['avatar'] = '👤';
}

// Protection CSRF
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Connexion à la base de données
include('connexion/connexion.php');

// Créer une partie de test temporaire
try {
    // Vérifier si une partie de test existe déjà
    $checkPartieQuery = "SELECT id FROM Parties WHERE nom = 'Partie Test Temp'";
    $checkPartieStmt = $conn->prepare($checkPartieQuery);
    $checkPartieStmt->execute();
    $partieExistante = $checkPartieStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($partieExistante) {
        $partie_id = $partieExistante['id'];
        
        // Supprimer les anciennes données associées
        $deleteCardsQuery = "DELETE FROM Cartes WHERE id_partie = :partie_id";
        $deleteCardsStmt = $conn->prepare($deleteCardsQuery);
        $deleteCardsStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
        $deleteCardsStmt->execute();
        
        $deleteUsersQuery = "DELETE FROM Utilisateurs_parties WHERE id_partie = :partie_id";
        $deleteUsersStmt = $conn->prepare($deleteUsersQuery);
        $deleteUsersStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
        $deleteUsersStmt->execute();
    } else {
        // Créer une nouvelle partie
        $createPartieQuery = "INSERT INTO Parties (nom, niveau, nombre_joueurs, vies_restantes, shurikens_restants, 
                              difficulte, status, prive, date_creation) 
                             VALUES ('Partie Test Temp', 1, 2, 3, 1, 'facile', 'en_cours', 0, NOW())";
        $createPartieStmt = $conn->prepare($createPartieQuery);
        $createPartieStmt->execute();
        
        $partie_id = $conn->lastInsertId();
    }
    
    // Ajouter l'utilisateur courant à la partie
    $addUserQuery = "INSERT INTO Utilisateurs_parties (id_utilisateur, id_partie, position) 
                    VALUES (:user_id, :partie_id, 1)";
    $addUserStmt = $conn->prepare($addUserQuery);
    $addUserStmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
    $addUserStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
    $addUserStmt->execute();
    
    // Créer un utilisateur fictif si nécessaire
    $checkBotQuery = "SELECT id FROM Utilisateurs WHERE identifiant = 'Bot_Test'";
    $checkBotStmt = $conn->prepare($checkBotQuery);
    $checkBotStmt->execute();
    $bot = $checkBotStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$bot) {
        $createBotQuery = "INSERT INTO Utilisateurs (identifiant, mail, mdp, avatar, id_role) 
                          VALUES ('Bot_Test', 'bot@test.com', 'password123', '🤖', 2)";
        $createBotStmt = $conn->prepare($createBotQuery);
        $createBotStmt->execute();
        $bot_id = $conn->lastInsertId();
    } else {
        $bot_id = $bot['id'];
    }
    
    // Ajouter le bot à la partie
    $addBotQuery = "INSERT INTO Utilisateurs_parties (id_utilisateur, id_partie, position) 
                   VALUES (:bot_id, :partie_id, 2)";
    $addBotStmt = $conn->prepare($addBotQuery);
    $addBotStmt->bindParam(':bot_id', $bot_id, PDO::PARAM_INT);
    $addBotStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
    $addBotStmt->execute();
    
    // Distribuer quelques cartes pour le test
    // Générer un deck de cartes
    $deck = range(1, 100);
    shuffle($deck);
    
    // Donner 3 cartes à l'utilisateur
    $insertCarteQuery = "INSERT INTO Cartes (id_partie, id_utilisateur, valeur, etat) 
                        VALUES (:partie_id, :user_id, :valeur, 'en_main')";
    $insertCarteStmt = $conn->prepare($insertCarteQuery);
    
    for ($i = 0; $i < 3; $i++) {
        $cardValue = array_pop($deck);
        $insertCarteStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
        $insertCarteStmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
        $insertCarteStmt->bindParam(':valeur', $cardValue, PDO::PARAM_INT);
        $insertCarteStmt->execute();
    }
    
    // Donner 3 cartes au bot
    for ($i = 0; $i < 3; $i++) {
        $cardValue = array_pop($deck);
        $insertCarteStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
        $insertCarteStmt->bindParam(':user_id', $bot_id, PDO::PARAM_INT);
        $insertCarteStmt->bindParam(':valeur', $cardValue, PDO::PARAM_INT);
        $insertCarteStmt->execute();
    }
    
    // Ajouter quelques cartes déjà jouées pour tester
    $insertJoueeQuery = "INSERT INTO Cartes (id_partie, id_utilisateur, valeur, etat, date_action) 
                        VALUES (:partie_id, :user_id, :valeur, 'jouee', NOW())";
    $insertJoueeStmt = $conn->prepare($insertJoueeQuery);
    
    $jouee1 = 5; // Première carte jouée
    $insertJoueeStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
    $insertJoueeStmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
    $insertJoueeStmt->bindParam(':valeur', $jouee1, PDO::PARAM_INT);
    $insertJoueeStmt->execute();
    
    $jouee2 = 12; // Deuxième carte jouée
    $insertJoueeStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
    $insertJoueeStmt->bindParam(':user_id', $bot_id, PDO::PARAM_INT);
    $insertJoueeStmt->bindParam(':valeur', $jouee2, PDO::PARAM_INT);
    $insertJoueeStmt->execute();
    
    // Rediriger vers le plateau de jeu
    header("Location: plateau-jeu.php?partie_id=$partie_id");
    exit();
    
} catch (PDOException $e) {
    echo "Erreur lors de la création de la partie de test: " . $e->getMessage();
    exit();
}
?>