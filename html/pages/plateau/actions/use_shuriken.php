<?php
/**
 * FICHIER: use_shuriken.php
 * DESCRIPTION: Gère l'utilisation d'un shuriken dans le jeu The Mind
 * FONCTIONNALITÉ: Tous les joueurs doivent valider l'utilisation du shuriken
 * AUTEUR: Système The Mind
 * DATE: 2025-01-20
 */

// Démarrer la session uniquement si elle n'est pas déjà active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ÉTAPE 1: Définir les textes par défaut
$texts = [
    'unauthorized' => 'Non autorisé',
    'invalid_csrf_token' => 'Token CSRF invalide',
    'invalid_parameters' => 'Paramètres invalides',
    'game_not_found' => 'Partie non trouvée',
    'game_not_in_progress' => 'La partie n\'est pas en cours',
    'no_shurikens' => 'Aucun shuriken disponible',
    'server_error' => 'Erreur serveur',
    'shuriken_used_success' => 'Shuriken utilisé avec succès',
    'shuriken_vote_started' => 'Vote pour utiliser le shuriken démarré',
    'waiting_for_votes' => 'En attente des votes des autres joueurs',
    'shuriken_rejected' => 'L\'utilisation du shuriken a été rejetée',
    'shuriken_approved' => 'L\'utilisation du shuriken a été approuvée'
];

// ÉTAPE 2: Gestion des textes multilingues
$language = isset($_SESSION['language']) ? $_SESSION['language'] : 'fr';
$language_file = __DIR__ . '/../../../languages/' . $language . '.php';
if (file_exists($language_file)) {
    include($language_file);
}

// ÉTAPE 3: Vérification de l'authentification
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $texts['unauthorized']]);
    exit();
}

// ÉTAPE 4: Vérification CSRF
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $texts['invalid_csrf_token']]);
    exit();
}

// ÉTAPE 5: Récupération des paramètres
$partie_id = isset($_POST['partie_id']) ? (int)$_POST['partie_id'] : 0;
$user_id = $_SESSION['user_id'];
$action_type = isset($_POST['action_type']) ? $_POST['action_type'] : 'request'; // 'request', 'vote'
$vote = isset($_POST['vote']) ? $_POST['vote'] : null; // 'yes' ou 'no'

if ($partie_id <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $texts['invalid_parameters']]);
    exit();
}

// ÉTAPE 6: Connexion à la base de données
$connexion_file = __DIR__ . '/../../../connexion/connexion.php';
if (!file_exists($connexion_file)) {
    try {
        $conn = new PDO("mysql:host=localhost;dbname=bddthemind;charset=utf8mb4", "user", "Eloi2023*");
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $texts['server_error']]);
        exit();
    }
} else {
    include($connexion_file);
}

try {
    // ÉTAPE 7: Vérifier l'état de la partie et les shurikens
    $partieQuery = "SELECT status, shurikens_restants, niveau FROM Parties WHERE id = :partie_id";
    $partieStmt = $conn->prepare($partieQuery);
    $partieStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
    $partieStmt->execute();
    
    $partie = $partieStmt->fetch(PDO::FETCH_ASSOC);
    if (!$partie) {
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
    
    // ÉTAPE 8: Récupérer la liste des joueurs de la partie
    $joueursQuery = "SELECT DISTINCT id_utilisateur FROM Utilisateurs_Parties WHERE id_partie = :partie_id";
    $joueursStmt = $conn->prepare($joueursQuery);
    $joueursStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
    $joueursStmt->execute();
    $joueurs = $joueursStmt->fetchAll(PDO::FETCH_COLUMN);
    
    $totalJoueurs = count($joueurs);
    
    // ÉTAPE 9: Gestion selon le type d'action
    if ($action_type === 'request') {
        // DEMANDE D'UTILISATION DU SHURIKEN
        
        // Vérifier s'il n'y a pas déjà un vote en cours
        $voteEnCoursQuery = "SELECT COUNT(*) as count FROM Actions_jeu 
                            WHERE id_partie = :partie_id AND type_action = 'vote_shuriken_request' 
                            AND DATE(date_action) = CURDATE()";
        $voteEnCoursStmt = $conn->prepare($voteEnCoursQuery);
        $voteEnCoursStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
        $voteEnCoursStmt->execute();
        $voteEnCours = $voteEnCoursStmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;
        
        if ($voteEnCours) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Un vote est déjà en cours']);
            exit();
        }
        
        // Créer la demande de vote
        $demandeur = $user_id;
        $requestDetails = json_encode([
            'demandeur' => $demandeur,
            'total_joueurs' => $totalJoueurs,
            'joueurs' => $joueurs,
            'timestamp' => time()
        ]);
        
        // Enregistrer la demande
        $requestQuery = "INSERT INTO Actions_jeu (id_partie, id_utilisateur, type_action, details) 
                        VALUES (:partie_id, :user_id, 'vote_shuriken_request', :details)";
        $requestStmt = $conn->prepare($requestQuery);
        $requestStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
        $requestStmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $requestStmt->bindParam(':details', $requestDetails, PDO::PARAM_STR);
        $requestStmt->execute();
        
        $request_id = $conn->lastInsertId();
        
        // Automatiquement voter "oui" pour le demandeur
        $voteDetails = json_encode([
            'vote' => 'yes',
            'request_id' => $request_id,
            'timestamp' => time()
        ]);
        
        $voteQuery = "INSERT INTO Actions_jeu (id_partie, id_utilisateur, type_action, details) 
                     VALUES (:partie_id, :user_id, 'vote_shuriken', :details)";
        $voteStmt = $conn->prepare($voteQuery);
        $voteStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
        $voteStmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $voteStmt->bindParam(':details', $voteDetails, PDO::PARAM_STR);
        $voteStmt->execute();
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => $texts['shuriken_vote_started'],
            'vote_started' => true,
            'request_id' => $request_id,
            'waiting_for_votes' => true
        ]);
        
    } elseif ($action_type === 'vote') {
        // VOTE POUR L'UTILISATION DU SHURIKEN
        
        $request_id = isset($_POST['request_id']) ? (int)$_POST['request_id'] : 0;
        
        if (!$vote || !in_array($vote, ['yes', 'no']) || $request_id <= 0) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $texts['invalid_parameters']]);
            exit();
        }
        
        // Vérifier que l'utilisateur n'a pas déjà voté
        $dejaVoteQuery = "SELECT COUNT(*) as count FROM Actions_jeu 
                         WHERE id_partie = :partie_id AND id_utilisateur = :user_id 
                         AND type_action = 'vote_shuriken' 
                         AND JSON_EXTRACT(details, '$.request_id') = :request_id";
        $dejaVoteStmt = $conn->prepare($dejaVoteQuery);
        $dejaVoteStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
        $dejaVoteStmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $dejaVoteStmt->bindParam(':request_id', $request_id, PDO::PARAM_INT);
        $dejaVoteStmt->execute();
        $dejaVote = $dejaVoteStmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;
        
        if ($dejaVote) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Vous avez déjà voté']);
            exit();
        }
        
        // Enregistrer le vote
        $voteDetails = json_encode([
            'vote' => $vote,
            'request_id' => $request_id,
            'timestamp' => time()
        ]);
        
        $voteQuery = "INSERT INTO Actions_jeu (id_partie, id_utilisateur, type_action, details) 
                     VALUES (:partie_id, :user_id, 'vote_shuriken', :details)";
        $voteStmt = $conn->prepare($voteQuery);
        $voteStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
        $voteStmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $voteStmt->bindParam(':details', $voteDetails, PDO::PARAM_STR);
        $voteStmt->execute();
        
        // ÉTAPE 10: Vérifier si tous les joueurs ont voté
        $votesQuery = "SELECT details FROM Actions_jeu 
                      WHERE id_partie = :partie_id AND type_action = 'vote_shuriken' 
                      AND JSON_EXTRACT(details, '$.request_id') = :request_id";
        $votesStmt = $conn->prepare($votesQuery);
        $votesStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
        $votesStmt->bindParam(':request_id', $request_id, PDO::PARAM_INT);
        $votesStmt->execute();
        $votes = $votesStmt->fetchAll(PDO::FETCH_COLUMN);
        
        $votesOui = 0;
        $votesNon = 0;
        
        foreach ($votes as $voteData) {
            $voteInfo = json_decode($voteData, true);
            if ($voteInfo['vote'] === 'yes') {
                $votesOui++;
            } else {
                $votesNon++;
            }
        }
        
        $totalVotes = $votesOui + $votesNon;
        
        // Si tous les joueurs ont voté
        if ($totalVotes >= $totalJoueurs) {
            if ($votesOui === $totalJoueurs) {
                // ÉTAPE 11: UTILISER LE SHURIKEN (unanimité)
                
                // Commencer une transaction
                $conn->beginTransaction();
                
                // Réduire le nombre de shurikens
                $shurikensRestants = $partie['shurikens_restants'] - 1;
                $updateShurikensQuery = "UPDATE Parties SET shurikens_restants = :shurikens WHERE id = :partie_id";
                $updateShurikensStmt = $conn->prepare($updateShurikensQuery);
                $updateShurikensStmt->bindParam(':shurikens', $shurikensRestants, PDO::PARAM_INT);
                $updateShurikensStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
                $updateShurikensStmt->execute();
                
                // Pour chaque joueur, défausser sa plus petite carte
                $cartesDéfaussées = [];
                foreach ($joueurs as $joueur_id) {
                    $plusPetiteCarteQuery = "SELECT id, valeur FROM Cartes 
                                            WHERE id_partie = :partie_id AND id_utilisateur = :user_id AND etat = 'en_main' 
                                            ORDER BY valeur ASC LIMIT 1";
                    $plusPetiteCarteStmt = $conn->prepare($plusPetiteCarteQuery);
                    $plusPetiteCarteStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
                    $plusPetiteCarteStmt->bindParam(':user_id', $joueur_id, PDO::PARAM_INT);
                    $plusPetiteCarteStmt->execute();
                    
                    if ($carte = $plusPetiteCarteStmt->fetch(PDO::FETCH_ASSOC)) {
                        // Défausser la carte
                        $defausserCarteQuery = "UPDATE Cartes SET etat = 'defaussee', date_action = NOW() WHERE id = :card_id";
                        $defausserCarteStmt = $conn->prepare($defausserCarteQuery);
                        $defausserCarteStmt->bindParam(':card_id', $carte['id'], PDO::PARAM_INT);
                        $defausserCarteStmt->execute();
                        
                        $cartesDéfaussées[] = [
                            'userId' => $joueur_id,
                            'cardId' => $carte['id'],
                            'value' => $carte['valeur']
                        ];
                    }
                }
                
                // Enregistrer l'utilisation du shuriken
                $actionQuery = "INSERT INTO Actions_jeu (id_partie, id_utilisateur, type_action, details) 
                               VALUES (:partie_id, :user_id, 'utiliser_shuriken', :details)";
                $actionStmt = $conn->prepare($actionQuery);
                $actionStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
                $actionStmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                $details = json_encode([
                    'shurikens_restants' => $shurikensRestants,
                    'cartes_defaussees' => $cartesDéfaussées,
                    'request_id' => $request_id,
                    'votes_pour' => $votesOui,
                    'votes_contre' => $votesNon
                ]);
                $actionStmt->bindParam(':details', $details, PDO::PARAM_STR);
                $actionStmt->execute();
                
                // Vérifier si toutes les cartes ont été jouées/défaussées
                $cartesRestantesQuery = "SELECT COUNT(*) as nbCartes FROM Cartes 
                                        WHERE id_partie = :partie_id AND etat = 'en_main'";
                $cartesRestantesStmt = $conn->prepare($cartesRestantesQuery);
                $cartesRestantesStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
                $cartesRestantesStmt->execute();
                $cartesRestantes = $cartesRestantesStmt->fetch(PDO::FETCH_ASSOC)['nbCartes'];
                
                // Valider la transaction
                $conn->commit();
                
                $partieStatus = 'en_cours';
                
                // Vérifier si le niveau est terminé
                if ($cartesRestantes === 0) {
                    if ($partie['niveau'] >= 12) {
                        // Partie gagnée
                        $updatePartieQuery = "UPDATE Parties SET status = 'gagnee', date_fin = NOW() WHERE id = :partie_id";
                        $updatePartieStmt = $conn->prepare($updatePartieQuery);
                        $updatePartieStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
                        $updatePartieStmt->execute();
                        $partieStatus = 'gagnee';
                    } else {
                        // Niveau terminé
                        $updatePartieQuery = "UPDATE Parties SET status = 'niveau_termine' WHERE id = :partie_id";
                        $updatePartieStmt = $conn->prepare($updatePartieQuery);
                        $updatePartieStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
                        $updatePartieStmt->execute();
                        $partieStatus = 'niveau_termine';
                    }
                }
                
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'message' => $texts['shuriken_used_success'],
                    'shuriken_used' => true,
                    'cartes_defaussees' => $cartesDéfaussées,
                    'cartes_restantes' => $cartesRestantes,
                    'partie_status' => $partieStatus,
                    'shurikens_restants' => $shurikensRestants
                ]);
                
            } else {
                // Vote rejeté
                $rejectQuery = "INSERT INTO Actions_jeu (id_partie, id_utilisateur, type_action, details) 
                               VALUES (:partie_id, :user_id, 'vote_shuriken_rejected', :details)";
                $rejectStmt = $conn->prepare($rejectQuery);
                $rejectStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
                $rejectStmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                $rejectDetails = json_encode([
                    'request_id' => $request_id,
                    'votes_pour' => $votesOui,
                    'votes_contre' => $votesNon,
                    'total_joueurs' => $totalJoueurs
                ]);
                $rejectStmt->bindParam(':details', $rejectDetails, PDO::PARAM_STR);
                $rejectStmt->execute();
                
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'message' => $texts['shuriken_rejected'],
                    'shuriken_rejected' => true,
                    'votes_pour' => $votesOui,
                    'votes_contre' => $votesNon
                ]);
            }
        } else {
            // Pas encore tous les votes
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => $texts['waiting_for_votes'],
                'vote_registered' => true,
                'votes_received' => $totalVotes,
                'votes_needed' => $totalJoueurs,
                'votes_pour' => $votesOui,
                'votes_contre' => $votesNon
            ]);
        }
        
    } else {
        // Type d'action non reconnu
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $texts['invalid_parameters']]);
    }
    
} catch (PDOException $e) {
    // ÉTAPE 12: Gestion des erreurs
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    
    error_log("Erreur use_shuriken.php: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $texts['server_error']]);
}
?>