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
$partie_id = isset($_POST['partie_id']) ? (int)$_POST['partie_id'] : 0;
$user_id = $_SESSION['user_id'];

if ($partie_id <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $texts['invalid_parameters']]);
    exit();
}

include('../../connexion/connexion.php');

try {
    // Vérifier l'état de la partie et le nombre de shurikens
    $partieQuery = "SELECT status, shurikens_restants FROM Parties WHERE id = :partie_id";
    $partieStmt = $conn->prepare($partieQuery);
    $partieStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
    $partieStmt->execute();
    
    if (!($partie = $partieStmt->fetch(PDO::FETCH_ASSOC))) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $texts['game_not_found']]);
        exit();
    }
    
    if ($partie['status'] !== 'en_cours') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $texts['game_not_in_progress']]);
        exit();
    }
    
    if ($partie['shurikens_restants'] <= 0) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $texts['no_shurikens']]);
        exit();
    }
    
    // Commencer une transaction
    $conn->beginTransaction();
    
    // Réduire le nombre de shurikens
    $shurikensRestants = $partie['shurikens_restants'] - 1;
    $updateShurikensQuery = "UPDATE Parties SET shurikens_restants = :shurikens WHERE id = :partie_id";
    $updateShurikensStmt = $conn->prepare($updateShurikensQuery);
    $updateShurikensStmt->bindParam(':shurikens', $shurikensRestants, PDO::PARAM_INT);
    $updateShurikensStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
    $updateShurikensStmt->execute();
    
    // Enregistrer l'action d'utilisation du shuriken
    $actionQuery = "INSERT INTO Actions_jeu (id_partie, id_utilisateur, type_action, details) 
                    VALUES (:partie_id, :user_id, 'utiliser_shuriken', :details)";
    $actionStmt = $conn->prepare($actionQuery);
    $actionStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
    $actionStmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $details = json_encode(['shurikens_restants' => $shurikensRestants]);
    $actionStmt->bindParam(':details', $details, PDO::PARAM_STR);
    $actionStmt->execute();
    
    // Pour chaque joueur, défausser sa plus petite carte
    $joueursQuery = "SELECT DISTINCT id_utilisateur FROM Cartes WHERE id_partie = :partie_id AND etat = 'en_main'";
    $joueursStmt = $conn->prepare($joueursQuery);
    $joueursStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
    $joueursStmt->execute();
    
    $cartesDéfaussées = [];
    
    while ($joueur = $joueursStmt->fetch(PDO::FETCH_ASSOC)) {
        $joueurId = $joueur['id_utilisateur'];
        
        // Récupérer la plus petite carte du joueur
        $plusPetiteCarteQuery = "SELECT id, valeur FROM Cartes 
                                WHERE id_partie = :partie_id AND id_utilisateur = :user_id AND etat = 'en_main' 
                                ORDER BY valeur ASC LIMIT 1";
        $plusPetiteCarteStmt = $conn->prepare($plusPetiteCarteQuery);
        $plusPetiteCarteStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
        $plusPetiteCarteStmt->bindParam(':user_id', $joueurId, PDO::PARAM_INT);
        $plusPetiteCarteStmt->execute();
        
        if ($carte = $plusPetiteCarteStmt->fetch(PDO::FETCH_ASSOC)) {
            // Défausser la carte
            $defausserCarteQuery = "UPDATE Cartes SET etat = 'defaussee', date_action = NOW() WHERE id = :card_id";
            $defausserCarteStmt = $conn->prepare($defausserCarteQuery);
            $defausserCarteStmt->bindParam(':card_id', $carte['id'], PDO::PARAM_INT);
            $defausserCarteStmt->execute();
            
            // Stocker les informations pour le retour
            $cartesDéfaussées[] = [
                'userId' => $joueurId,
                'cardId' => $carte['id'],
                'value' => $carte['valeur']
            ];
        }
    }
    
    // Vérifier si toutes les cartes ont été jouées/défaussées (niveau terminé)
    $cartesRestantesQuery = "SELECT COUNT(*) as nbCartes FROM Cartes WHERE id_partie = :partie_id AND etat = 'en_main'";
    $cartesRestantesStmt = $conn->prepare($cartesRestantesQuery);
    $cartesRestantesStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
    $cartesRestantesStmt->execute();
    $cartesRestantes = $cartesRestantesStmt->fetch(PDO::FETCH_ASSOC)['nbCartes'];
    
    // Valider la transaction
    $conn->commit();
    
    $partieStatus = 'en_cours';
    $partieInfo = null;
    
    if ($cartesRestantes === 0) {
        // Toutes les cartes ont été jouées, niveau terminé avec succès
        $partieInfoQuery = "SELECT niveau FROM Parties WHERE id = :partie_id";
        $partieInfoStmt = $conn->prepare($partieInfoQuery);
        $partieInfoStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
        $partieInfoStmt->execute();
        $partieInfo = $partieInfoStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($partieInfo['niveau'] >= 12) {
            // Partie terminée (gagné)
            $updatePartieQuery = "UPDATE Parties SET status = 'gagnee', date_fin = NOW() WHERE id = :partie_id";
            $updatePartieStmt = $conn->prepare($updatePartieQuery);
            $updatePartieStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
            $updatePartieStmt->execute();
            
            // Appeler la procédure pour enregistrer les scores
            $saveScoreQuery = "CALL save_game_score(:partie_id, TRUE)";
            $saveScoreStmt = $conn->prepare($saveScoreQuery);
            $saveScoreStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
            $saveScoreStmt->execute();
            
            $partieStatus = 'gagnee';
        } else {
            // Passer au niveau suivant
            $updatePartieQuery = "UPDATE Parties SET status = 'niveau_termine' WHERE id = :partie_id";
            $updatePartieStmt = $conn->prepare($updatePartieQuery);
            $updatePartieStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
            $updatePartieStmt->execute();
            
            // Ajouter une entrée dans Actions_jeu
            $actionNiveauQuery = "INSERT INTO Actions_jeu (id_partie, id_utilisateur, type_action, details) 
                              VALUES (:partie_id, :user_id, 'nouveau_niveau', :details)";
            $actionNiveauStmt = $conn->prepare($actionNiveauQuery);
            $actionNiveauStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
            $actionNiveauStmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $detailsNiveau = json_encode(['niveau_termine' => $partieInfo['niveau'], 'methode' => 'shuriken']);
            $actionNiveauStmt->bindParam(':details', $detailsNiveau, PDO::PARAM_STR);
            $actionNiveauStmt->execute();
            
            $partieStatus = 'niveau_termine';
        }
    }
    
    // Renvoyer une réponse de succès
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => $texts['shuriken_used_success'],
        'cartes_defaussees' => $cartesDéfaussées,
        'cartes_restantes' => $cartesRestantes,
        'partie_status' => $partieStatus
    ]);
    
} catch (PDOException $e) {
    // Annuler la transaction en cas d'erreur
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    
    error_log("Erreur use_shuriken.php: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $texts['server_error']]);
}
?>