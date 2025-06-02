<?php
session_start();

// Gestion des textes multilingues
$language = isset($_SESSION['language']) ? $_SESSION['language'] : 'fr';
include('../../languages/' . $language . '.php');

// Vérification de l'authentification
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $texts['unauthorized']]);
    exit();
}

// Vérification CSRF
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $texts['invalid_csrf_token']]);
    exit();
}

// Récupérer les paramètres
$action = isset($_POST['action']) ? $_POST['action'] : '';
$partie_id = isset($_POST['partie_id']) ? (int)$_POST['partie_id'] : 0;
$user_id = $_SESSION['user_id'];

if (empty($action) || $partie_id <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $texts['invalid_parameters']]);
    exit();
}

include('../../connexion/connexion.php');

try {
    // Vérifier que l'utilisateur est admin de la partie
    $adminCheck = "SELECT 1 FROM Utilisateurs_parties 
                  WHERE id_partie = :partie_id AND id_utilisateur = :user_id AND position = 1";
    $adminStmt = $conn->prepare($adminCheck);
    $adminStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
    $adminStmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $adminStmt->execute();
    
    if ($adminStmt->rowCount() === 0) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $texts['not_admin']]);
        exit();
    }
    
    // Traiter les différentes actions
    switch ($action) {
        case 'start':
            // Démarrer la partie
            $startQuery = "UPDATE Parties SET status = 'en_cours', date_début = NOW() WHERE id = :partie_id AND status = 'en_attente'";
            $startStmt = $conn->prepare($startQuery);
            $startStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
            $success = $startStmt->execute();
            
            // Distribuer les cartes aux joueurs
            if ($success) {
                distributeCards($conn, $partie_id);
            }
            
            $message = $texts['game_started_success'];
            break;
            
        case 'pause':
            // Mettre la partie en pause
            $pauseQuery = "UPDATE Parties SET status = 'pause' WHERE id = :partie_id AND status = 'en_cours'";
            $pauseStmt = $conn->prepare($pauseQuery);
            $pauseStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
            $pauseStmt->execute();
            $message = $texts['game_paused_success'];
            break;
            
        case 'resume':
            // Reprendre la partie
            $resumeQuery = "UPDATE Parties SET status = 'en_cours' WHERE id = :partie_id AND status = 'pause'";
            $resumeStmt = $conn->prepare($resumeQuery);
            $resumeStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
            $resumeStmt->execute();
            $message = $texts['game_resumed_success'];
            break;
            
        case 'cancel':
            // Annuler la partie
            $cancelQuery = "UPDATE Parties SET status = 'annulee', date_fin = NOW() WHERE id = :partie_id";
            $cancelStmt = $conn->prepare($cancelQuery);
            $cancelStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
            $cancelStmt->execute();
            $message = $texts['game_cancelled_success'];
            
            // Ajouter une entrée dans Actions_jeu
            $actionQuery = "INSERT INTO Actions_jeu (id_partie, id_utilisateur, type_action, details) 
                           VALUES (:partie_id, :user_id, 'fin_partie', :details)";
            $actionStmt = $conn->prepare($actionQuery);
            $actionStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
            $actionStmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $details = json_encode(['raison' => 'annulation_admin']);
            $actionStmt->bindParam(':details', $details, PDO::PARAM_STR);
            $actionStmt->execute();
            break;
            
        case 'next_level':
            // Passer au niveau suivant
            $nextLevelQuery = "SELECT niveau FROM Parties WHERE id = :partie_id";
            $nextLevelStmt = $conn->prepare($nextLevelQuery);
            $nextLevelStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
            $nextLevelStmt->execute();
            $currentLevel = $nextLevelStmt->fetch(PDO::FETCH_ASSOC)['niveau'];
            
            if ($currentLevel >= 12) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $texts['max_level_reached']]);
                exit();
            }
            
            $newLevel = $currentLevel + 1;
            
            // Vérifier si on attribue une récompense (vie ou shuriken)
            $bonusVie = false;
            $bonusShuriken = false;
            
            // Selon les règles de The Mind, on gagne une vie aux niveaux 3, 6, 9
            if ($newLevel == 3 || $newLevel == 6 || $newLevel == 9) {
                $bonusVie = true;
                
                // Récupérer les vies restantes
                $viesQuery = "SELECT vies_restantes FROM Parties WHERE id = :partie_id";
                $viesStmt = $conn->prepare($viesQuery);
                $viesStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
                $viesStmt->execute();
                $viesRestantes = $viesStmt->fetch(PDO::FETCH_ASSOC)['vies_restantes'];
                
                // Ajouter une vie
                $viesRestantes++;
                $updateViesQuery = "UPDATE Parties SET vies_restantes = :vies WHERE id = :partie_id";
                $updateViesStmt = $conn->prepare($updateViesQuery);
                $updateViesStmt->bindParam(':vies', $viesRestantes, PDO::PARAM_INT);
                $updateViesStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
                $updateViesStmt->execute();
            }
            
            // Selon les règles de The Mind, on gagne un shuriken aux niveaux 2, 5, 8
            if ($newLevel == 2 || $newLevel == 5 || $newLevel == 8) {
                $bonusShuriken = true;
                
                // Récupérer les shurikens restants
                $shurikensQuery = "SELECT shurikens_restants FROM Parties WHERE id = :partie_id";
                $shurikensStmt = $conn->prepare($shurikensQuery);
                $shurikensStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
                $shurikensStmt->execute();
                $shurikensRestants = $shurikensStmt->fetch(PDO::FETCH_ASSOC)['shurikens_restants'];
                
                // Ajouter un shuriken
                $shurikensRestants++;
                $updateShurikensQuery = "UPDATE Parties SET shurikens_restants = :shurikens WHERE id = :partie_id";
                $updateShurikensStmt = $conn->prepare($updateShurikensQuery);
                $updateShurikensStmt->bindParam(':shurikens', $shurikensRestants, PDO::PARAM_INT);
                $updateShurikensStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
                $updateShurikensStmt->execute();
            }
            
            // Mettre à jour le niveau et l'état de la partie
            $updateLevelQuery = "UPDATE Parties SET niveau = :niveau, status = 'en_cours' WHERE id = :partie_id";
            $updateLevelStmt = $conn->prepare($updateLevelQuery);
            $updateLevelStmt->bindParam(':niveau', $newLevel, PDO::PARAM_INT);
            $updateLevelStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
            $updateLevelStmt->execute();
            
            // Supprimer toutes les anciennes cartes
            $deleteCardsQuery = "DELETE FROM Cartes WHERE id_partie = :partie_id";
            $deleteCardsStmt = $conn->prepare($deleteCardsQuery);
            $deleteCardsStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
            $deleteCardsStmt->execute();
            
            // Distribuer de nouvelles cartes
            distributeCards($conn, $partie_id);
            
            // Ajouter une entrée dans Actions_jeu
            $actionQuery = "INSERT INTO Actions_jeu (id_partie, id_utilisateur, type_action, details) 
                           VALUES (:partie_id, :user_id, 'nouveau_niveau', :details)";
            $actionStmt = $conn->prepare($actionQuery);
            $actionStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
            $actionStmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $details = json_encode([
                'ancien_niveau' => $currentLevel,
                'nouveau_niveau' => $newLevel,
                'bonus_vie' => $bonusVie,
                'bonus_shuriken' => $bonusShuriken
            ]);
            $actionStmt->bindParam(':details', $details, PDO::PARAM_STR);
            $actionStmt->execute();
            
            $message = $texts['level'] . ' ' . $newLevel . ' ' . $texts['started'];
            if ($bonusVie) {
                $message .= ' - ' . $texts['bonus'] . ' : +1 ' . $texts['life'];
            }
            if ($bonusShuriken) {
                $message .= ' - ' . $texts['bonus'] . ' : +1 ' . $texts['shuriken'];
            }
            break;
            
        default:
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $texts['action_not_recognized']]);
            exit();
    }
    
    // Renvoyer une réponse de succès
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => $message
    ]);
    
} catch (PDOException $e) {
    error_log("Erreur admin_action.php: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $texts['server_error']]);
}

// Fonction pour distribuer les cartes
function distributeCards($conn, $partie_id) {
    // Récupérer les informations sur la partie
    $partieInfoQuery = "SELECT nombre_joueurs, niveau FROM Parties WHERE id = :partie_id";
    $partieInfoStmt = $conn->prepare($partieInfoQuery);
    $partieInfoStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
    $partieInfoStmt->execute();
    $partieInfo = $partieInfoStmt->fetch(PDO::FETCH_ASSOC);
    
    $niveau = $partieInfo['niveau'];
    $cardsPerPlayer = $niveau; // Le nombre de cartes par joueur est égal au niveau
    
    // Récupérer les joueurs de la partie
    $joueursQuery = "SELECT id_utilisateur FROM Utilisateurs_parties WHERE id_partie = :partie_id ORDER BY position";
    $joueursStmt = $conn->prepare($joueursQuery);
    $joueursStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
    $joueursStmt->execute();
    $joueurs = $joueursStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Générer un deck de cartes (1-100)
    $deck = range(1, 100);
    shuffle($deck);
    
    // Distribuer les cartes aux joueurs
    $insertCarteQuery = "INSERT INTO Cartes (id_partie, id_utilisateur, valeur, etat) VALUES (:partie_id, :user_id, :valeur, 'en_main')";
    $insertCarteStmt = $conn->prepare($insertCarteQuery);
    
    foreach ($joueurs as $joueur) {
        $userId = $joueur['id_utilisateur'];
        
        for ($i = 0; $i < $cardsPerPlayer; $i++) {
            if (empty($deck)) break; // Vérifier qu'il reste des cartes
            
            $cardValue = array_pop($deck);
            
            $insertCarteStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
            $insertCarteStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $insertCarteStmt->bindParam(':valeur', $cardValue, PDO::PARAM_INT);
            $insertCarteStmt->execute();
        }
    }
}
?>