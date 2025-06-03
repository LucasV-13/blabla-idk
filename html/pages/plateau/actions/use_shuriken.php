<?php
// html/pages/plateau/actions/use_shuriken.php - VERSION CORRIGÉE avec système de vote

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
$action = isset($_POST['action']) ? $_POST['action'] : 'propose'; // 'propose' ou 'vote'
$vote = isset($_POST['vote']) ? $_POST['vote'] : null; // 'yes' ou 'no'

if ($partie_id <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $texts['invalid_parameters']]);
    exit();
}

include('../../connexion/connexion.php');

try {
    // Vérifier l'état de la partie et le nombre de shurikens
    $partieQuery = "SELECT status, shurikens_restants, nombre_joueurs FROM Parties WHERE id = :partie_id";
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

    // Vérifier qu'il n'y a pas déjà un vote en cours
    $voteQuery = "SELECT * FROM Shuriken_votes WHERE id_partie = :partie_id AND status = 'en_cours'";
    $voteStmt = $conn->prepare($voteQuery);
    $voteStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
    $voteStmt->execute();
    $voteEnCours = $voteStmt->fetch(PDO::FETCH_ASSOC);

    if ($action === 'propose') {
        // Proposer l'utilisation du shuriken
        if ($voteEnCours) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Un vote est déjà en cours']);
            exit();
        }

        // Créer un nouveau vote
        $createVoteQuery = "INSERT INTO Shuriken_votes (id_partie, id_proposeur, status, date_creation) 
                           VALUES (:partie_id, :user_id, 'en_cours', NOW())";
        $createVoteStmt = $conn->prepare($createVoteQuery);
        $createVoteStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
        $createVoteStmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $createVoteStmt->execute();

        $vote_id = $conn->lastInsertId();

        // Ajouter le vote du proposeur (automatiquement OUI)
        $addVoteQuery = "INSERT INTO Shuriken_vote_details (id_vote, id_utilisateur, vote) 
                        VALUES (:vote_id, :user_id, 'yes')";
        $addVoteStmt = $conn->prepare($addVoteQuery);
        $addVoteStmt->bindParam(':vote_id', $vote_id, PDO::PARAM_INT);
        $addVoteStmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $addVoteStmt->execute();

        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => 'Vote pour utiliser le shuriken lancé',
            'vote_started' => true,
            'vote_id' => $vote_id
        ]);

    } elseif ($action === 'vote') {
        // Voter pour ou contre
        if (!$voteEnCours) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Aucun vote en cours']);
            exit();
        }

        if (!in_array($vote, ['yes', 'no'])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Vote invalide']);
            exit();
        }

        $vote_id = $voteEnCours['id'];

        // Vérifier si l'utilisateur a déjà voté
        $checkVoteQuery = "SELECT * FROM Shuriken_vote_details WHERE id_vote = :vote_id AND id_utilisateur = :user_id";
        $checkVoteStmt = $conn->prepare($checkVoteQuery);
        $checkVoteStmt->bindParam(':vote_id', $vote_id, PDO::PARAM_INT);
        $checkVoteStmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $checkVoteStmt->execute();

        if ($checkVoteStmt->rowCount() > 0) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Vous avez déjà voté']);
            exit();
        }

        // Enregistrer le vote
        $addVoteQuery = "INSERT INTO Shuriken_vote_details (id_vote, id_utilisateur, vote) 
                        VALUES (:vote_id, :user_id, :vote)";
        $addVoteStmt = $conn->prepare($addVoteQuery);
        $addVoteStmt->bindParam(':vote_id', $vote_id, PDO::PARAM_INT);
        $addVoteStmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $addVoteStmt->bindParam(':vote', $vote, PDO::PARAM_STR);
        $addVoteStmt->execute();

        // Vérifier si tous les joueurs ont voté
        $countVotesQuery = "SELECT COUNT(*) as votes_count FROM Shuriken_vote_details WHERE id_vote = :vote_id";
        $countVotesStmt = $conn->prepare($countVotesQuery);
        $countVotesStmt->bindParam(':vote_id', $vote_id, PDO::PARAM_INT);
        $countVotesStmt->execute();
        $votesCount = $countVotesStmt->fetch(PDO::FETCH_ASSOC)['votes_count'];

        if ($votesCount >= $partie['nombre_joueurs']) {
            // Tous les joueurs ont voté, calculer le résultat
            $resultQuery = "SELECT 
                              SUM(CASE WHEN vote = 'yes' THEN 1 ELSE 0 END) as yes_votes,
                              SUM(CASE WHEN vote = 'no' THEN 1 ELSE 0 END) as no_votes
                            FROM Shuriken_vote_details WHERE id_vote = :vote_id";
            $resultStmt = $conn->prepare($resultQuery);
            $resultStmt->bindParam(':vote_id', $vote_id, PDO::PARAM_INT);
            $resultStmt->execute();
            $result = $resultStmt->fetch(PDO::FETCH_ASSOC);

            $approved = ($result['no_votes'] == 0); // Unanimité requise

            // Mettre à jour le statut du vote
            $updateVoteQuery = "UPDATE Shuriken_votes SET status = :status, resultat = :resultat 
                               WHERE id = :vote_id";
            $updateVoteStmt = $conn->prepare($updateVoteQuery);
            $status = $approved ? 'approuve' : 'rejete';
            $updateVoteStmt->bindParam(':status', $status, PDO::PARAM_STR);
            $updateVoteStmt->bindParam(':resultat', $approved ? '1' : '0', PDO::PARAM_INT);
            $updateVoteStmt->bindParam(':vote_id', $vote_id, PDO::PARAM_INT);
            $updateVoteStmt->execute();

            if ($approved) {
                // Exécuter l'effet du shuriken
                $conn->beginTransaction();

                // Réduire le nombre de shurikens
                $shurikensRestants = $partie['shurikens_restants'] - 1;
                $updateShurikensQuery = "UPDATE Parties SET shurikens_restants = :shurikens WHERE id = :partie_id";
                $updateShurikensStmt = $conn->prepare($updateShurikensQuery);
                $updateShurikensStmt->bindParam(':shurikens', $shurikensRestants, PDO::PARAM_INT);
                $updateShurikensStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
                $updateShurikensStmt->execute();

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

                        $cartesDéfaussées[] = [
                            'userId' => $joueurId,
                            'cardId' => $carte['id'],
                            'value' => $carte['valeur']
                        ];
                    }
                }

                $conn->commit();

                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'message' => 'Shuriken utilisé avec succès !',
                    'vote_completed' => true,
                    'vote_approved' => true,
                    'cartes_defaussees' => $cartesDéfaussées
                ]);
            } else {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'message' => 'Vote rejeté - Le shuriken ne sera pas utilisé',
                    'vote_completed' => true,
                    'vote_approved' => false
                ]);
            }
        } else {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'Vote enregistré - En attente des autres joueurs',
                'vote_recorded' => true,
                'votes_count' => $votesCount,
                'total_players' => $partie['nombre_joueurs']
            ]);
        }
    }

} catch (PDOException $e) {
    error_log("Erreur use_shuriken.php: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $texts['server_error']]);
}
?>