<?php
session_start();

// Vérification de l'authentification et expiration de session
if (!isset($_SESSION['user_id']) || (isset($_SESSION['expires']) && $_SESSION['expires'] < time())) {
    // Détruire la session si elle a expiré
    session_destroy();
    header("Location: ../index.php");
    exit();
}

// Protection CSRF
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Vérification de l'ID de partie
$partie_id = isset($_GET['partie_id']) ? (int)$_GET['partie_id'] : 0;

if ($partie_id <= 0) {
    // Si un ID est fourni via POST (comme lors de la jointure à une nouvelle partie)
    if (isset($_POST['game_id']) && is_numeric($_POST['game_id'])) {
        $partie_id = (int)$_POST['game_id'];
        
        // Vérification CSRF pour les soumissions de formulaire
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            header("Location: ../dashboard.php");
            exit();
        }
        
        // Vérifier si l'utilisateur fait déjà partie de cette partie
        include('../connexion/connexion.php');
        
        try {
            $user_id = $_SESSION['user_id'];
            
            // Vérifier si l'utilisateur fait déjà partie de la partie
            $checkQuery = "SELECT * FROM Utilisateurs_parties 
                          WHERE id_utilisateur = :user_id AND id_partie = :partie_id";
            $checkStmt = $conn->prepare($checkQuery);
            $checkStmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $checkStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
            $checkStmt->execute();
            
            if ($checkStmt->rowCount() === 0) {
                // L'utilisateur ne fait pas encore partie de la partie, vérifier s'il peut la rejoindre
                $partieQuery = "SELECT p.*, 
                               (SELECT COUNT(*) FROM Utilisateurs_parties WHERE id_partie = p.id) as joueurs_actuels
                               FROM Parties p
                               WHERE p.id = :partie_id AND p.status = 'en_attente'";
                $partieStmt = $conn->prepare($partieQuery);
                $partieStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
                $partieStmt->execute();
                
                if ($partie = $partieStmt->fetch(PDO::FETCH_ASSOC)) {
                    if ($partie['joueurs_actuels'] < $partie['nombre_joueurs']) {
                        // L'utilisateur peut rejoindre la partie
                        $maxPosQuery = "SELECT MAX(position) as max_pos FROM Utilisateurs_parties WHERE id_partie = :partie_id";
                        $maxPosStmt = $conn->prepare($maxPosQuery);
                        $maxPosStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
                        $maxPosStmt->execute();
                        $maxPos = $maxPosStmt->fetch(PDO::FETCH_ASSOC)['max_pos'] ?? 0;
                        
                        // Ajouter l'utilisateur à la partie
                        $newPosition = $maxPos + 1;
                        $joinQuery = "INSERT INTO Utilisateurs_parties (id_utilisateur, id_partie, position) 
                                     VALUES (:user_id, :partie_id, :position)";
                        $joinStmt = $conn->prepare($joinQuery);
                        $joinStmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                        $joinStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
                        $joinStmt->bindParam(':position', $newPosition, PDO::PARAM_INT);
                        $joinStmt->execute();
                    } else {
                        // La partie est déjà complète
                        header("Location: ../dashboard.php");
                        exit();
                    }
                } else {
                    // La partie n'existe pas ou n'est plus en attente
                    header("Location: ../dashboard.php");
                    exit();
                }
            }
        } catch (PDOException $e) {
            error_log("Erreur lors de la jointure à la partie: " . $e->getMessage());
            header("Location: ../dashboard.php");
            exit();
        }
    } else {
        // Aucun ID de partie valide
        header("Location: ../dashboard.php");
        exit();
    }
}

// Rediriger vers le plateau de jeu
header("Location: ../plateau-jeu.php?partie_id=" . $partie_id);
exit();
?>