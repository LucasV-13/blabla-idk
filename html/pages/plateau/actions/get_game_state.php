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
if (!isset($_GET['csrf_token']) || $_GET['csrf_token'] !== $_SESSION['csrf_token']) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $texts['invalid_csrf_token']]);
    exit();
}

// Récupérer les paramètres
$partie_id = isset($_GET['partie_id']) ? (int)$_GET['partie_id'] : 0;
$user_id = $_SESSION['user_id'];

if ($partie_id <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $texts['invalid_game_id']]);
    exit();
}

include('../../connexion/connexion.php');

try {
    // Récupérer l'état de la partie
    $partieQuery = "SELECT niveau, vies_restantes, shurikens_restants, status 
                    FROM Parties 
                    WHERE id = :partie_id";
    $partieStmt = $conn->prepare($partieQuery);
    $partieStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
    $partieStmt->execute();
    
    if (!($partie = $partieStmt->fetch(PDO::FETCH_ASSOC))) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $texts['game_not_found']]);
        exit();
    }
    
    // Récupérer les cartes du joueur
    $cartesQuery = "SELECT id, valeur 
                   FROM Cartes 
                   WHERE id_partie = :partie_id AND id_utilisateur = :user_id AND etat = 'en_main'
                   ORDER BY valeur";
    $cartesStmt = $conn->prepare($cartesQuery);
    $cartesStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
    $cartesStmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $cartesStmt->execute();
    $cartes = $cartesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Récupérer les dernières cartes jouées
    $cartesJoueesQuery = "SELECT c.id, c.valeur, u.identifiant as joueur_nom 
                          FROM Cartes c 
                          JOIN Utilisateurs u ON c.id_utilisateur = u.id
                          WHERE c.id_partie = :partie_id AND c.etat = 'jouee'
                          ORDER BY c.date_action DESC
                          LIMIT 10";
    $cartesJoueesStmt = $conn->prepare($cartesJoueesQuery);
    $cartesJoueesStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
    $cartesJoueesStmt->execute();
    $cartesJouees = $cartesJoueesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Récupérer la liste des joueurs
    $joueursQuery = "SELECT u.id, u.identifiant, u.avatar, up.position,
                      (SELECT COUNT(*) FROM Cartes WHERE id_utilisateur = u.id AND id_partie = :partie_id AND etat = 'en_main') as cartes_en_main
                     FROM Utilisateurs u
                     JOIN Utilisateurs_parties up ON u.id = up.id_utilisateur
                     WHERE up.id_partie = :partie_id
                     ORDER BY up.position";
    $joueursStmt = $conn->prepare($joueursQuery);
    $joueursStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
    $joueursStmt->execute();
    $joueurs = $joueursStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Vérifier s'il y a un vote shuriken en cours
    $shurikenVote = null;
    $shurikenVoteQuery = "SELECT sv.id, sv.id_proposeur, sv.status, u.identifiant as proposeur_nom,
                                 (SELECT COUNT(*) FROM Shuriken_vote_details WHERE id_vote = sv.id) as votes_count,
                                 (SELECT COUNT(*) FROM Shuriken_vote_details WHERE id_vote = sv.id AND id_utilisateur = :user_id) as user_voted
                          FROM Shuriken_votes sv
                          JOIN Utilisateurs u ON sv.id_proposeur = u.id
                          WHERE sv.id_partie = :partie_id AND sv.status = 'en_cours'";
    $shurikenVoteStmt = $conn->prepare($shurikenVoteQuery);
    $shurikenVoteStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
    $shurikenVoteStmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $shurikenVoteStmt->execute();
    
    if ($voteData = $shurikenVoteStmt->fetch(PDO::FETCH_ASSOC)) {
        $shurikenVote = [
            'id' => $voteData['id'],
            'proposeur_nom' => $voteData['proposeur_nom'],
            'votes_count' => $voteData['votes_count'],
            'user_voted' => $voteData['user_voted'] > 0,
            'total_players' => count($joueurs)
        ];
    }
    
    // Si le statut est "niveau_termine", fournir des informations supplémentaires
    $infoNiveau = null;
    if ($partie['status'] === 'niveau_termine') {
        $infoNiveauQuery = "SELECT niveau FROM Parties WHERE id = :partie_id";
        $infoNiveauStmt = $conn->prepare($infoNiveauQuery);
        $infoNiveauStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
        $infoNiveauStmt->execute();
        $niveau = $infoNiveauStmt->fetch(PDO::FETCH_ASSOC)['niveau'];
        
        $infoNiveau = [
            'niveau_termine' => $niveau,
            'prochain_niveau' => $niveau + 1
        ];
    }
    
    // Renvoyer les données au format JSON
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'niveau' => $partie['niveau'],
        'vies' => $partie['vies_restantes'],
        'shurikens' => $partie['shurikens_restants'],
        'status' => $partie['status'],
        'cartes' => $cartes,
        'cartesJouees' => $cartesJouees,
        'joueurs' => $joueurs,
        'infoNiveau' => $infoNiveau,
        'shuriken_vote' => $shurikenVote
    ]);
    
} catch (PDOException $e) {
    error_log("Erreur get_game_state.php: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $texts['server_error']]);
}
?>