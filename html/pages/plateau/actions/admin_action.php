<?php
/**
 * FICHIER: admin_action.php
 * DESCRIPTION: Gère toutes les actions d'administration d'une partie
 * ACTIONS: start, pause, resume, cancel, next_level
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
    'not_admin' => 'Vous n\'êtes pas administrateur de cette partie',
    'action_not_recognized' => 'Action non reconnue',
    'server_error' => 'Erreur serveur',
    'game_started_success' => 'Partie démarrée avec succès',
    'game_paused_success' => 'Partie mise en pause',
    'game_resumed_success' => 'Partie reprise',
    'game_cancelled_success' => 'Partie annulée',
    'level_completed' => 'Niveau terminé avec succès',
    'max_level_reached' => 'Niveau maximum atteint',
    'game_already_started' => 'La partie a déjà commencé',
    'game_not_in_progress' => 'La partie n\'est pas en cours',
    'game_not_paused' => 'La partie n\'est pas en pause',
    'level' => 'Niveau',
    'started' => 'commencé',
    'bonus' => 'Bonus',
    'life' => 'vie',
    'shuriken' => 'shuriken'
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
$action = isset($_POST['action']) ? $_POST['action'] : '';
$partie_id = isset($_POST['partie_id']) ? (int)$_POST['partie_id'] : 0;
$user_id = $_SESSION['user_id'];

if (empty($action) || $partie_id <= 0) {
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
    // ÉTAPE 7: Vérifier que l'utilisateur est admin de la partie
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
    
    // ÉTAPE 8: Récupérer les informations de la partie
    $partieQuery = "SELECT status, niveau, nombre_joueurs, vies_restantes, shurikens_restants 
                   FROM Parties WHERE id = :partie_id";
    $partieStmt = $conn->prepare($partieQuery);
    $partieStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
    $partieStmt->execute();
    $partie = $partieStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$partie) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Partie non trouvée']);
        exit();
    }
    
    // ÉTAPE 9: Traitement selon l'action demandée
    $message = '';
    $success = false;
    
    switch ($action) {
        case 'start':
            // DÉMARRER LA PARTIE
            if ($partie['status'] !== 'en_attente') {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $texts['game_already_started']]);
                exit();
            }
            
            // Mettre à jour le statut de la partie
            $startQuery = "UPDATE Parties SET status = 'en_cours', date_début = NOW() WHERE id = :partie_id";
            $startStmt = $conn->prepare($startQuery);
            $startStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
            $startStmt->execute();
            
            // Distribuer les cartes aux joueurs
            distributeCards($conn, $partie_id, $partie['niveau'], $partie['nombre_joueurs']);
            
            // Enregistrer l'action
            $actionQuery = "INSERT INTO Actions_jeu (id_partie, id_utilisateur, type_action, details) 
                           VALUES (:partie_id, :user_id, 'demarrer_partie', :details)";
            $actionStmt = $conn->prepare($actionQuery);
            $actionStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
            $actionStmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $details = json_encode(['niveau' => $partie['niveau'], 'timestamp' => time()]);
            $actionStmt->bindParam(':details', $details, PDO::PARAM_STR);
            $actionStmt->execute();
            
            $message = $texts['game_started_success'];
            $success = true;
            break;
            
        case 'pause':
            // METTRE EN PAUSE
            if ($partie['status'] !== 'en_cours') {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $texts['game_not_in_progress']]);
                exit();
            }
            
            $pauseQuery = "UPDATE Parties SET status = 'pause' WHERE id = :partie_id";
            $pauseStmt = $conn->prepare($pauseQuery);
            $pauseStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
            $pauseStmt->execute();
            
            // Enregistrer l'action
            $actionQuery = "INSERT INTO Actions_jeu (id_partie, id_utilisateur, type_action, details) 
                           VALUES (:partie_id, :user_id, 'pause_partie', :details)";
            $actionStmt = $conn->prepare($actionQuery);
            $actionStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
            $actionStmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $details = json_encode(['timestamp' => time()]);
            $actionStmt->bindParam(':details', $details, PDO::PARAM_STR);
            $actionStmt->execute();
            
            $message = $texts['game_paused_success'];
            $success = true;
            break;
            
        case 'resume':
            // REPRENDRE LA PARTIE
            if ($partie['status'] !== 'pause') {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $texts['game_not_paused']]);
                exit();
            }
            
            $resumeQuery = "UPDATE Parties SET status = 'en_cours' WHERE id = :partie_id";
            $resumeStmt = $conn->prepare($resumeQuery);
            $resumeStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
            $resumeStmt->execute();
            
            // Enregistrer l'action
            $actionQuery = "INSERT INTO Actions_jeu (id_partie, id_utilisateur, type_action, details) 
                           VALUES (:partie_id, :user_id, 'reprendre_partie', :details)";
            $actionStmt = $conn->prepare($actionQuery);
            $actionStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
            $actionStmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $details = json_encode(['timestamp' => time()]);
            $actionStmt->bindParam(':details', $details, PDO::PARAM_STR);
            $actionStmt->execute();
            
            $message = $texts['game_resumed_success'];
            $success = true;
            break;
            
        case 'cancel':
            // ANNULER LA PARTIE
            $cancelQuery = "UPDATE Parties SET status = 'annulee', date_fin = NOW() WHERE id = :partie_id";
            $cancelStmt = $conn->prepare($cancelQuery);
            $cancelStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
            $cancelStmt->execute();
            
            // Enregistrer l'action
            $actionQuery = "INSERT INTO Actions_jeu (id_partie, id_utilisateur, type_action, details) 
                           VALUES (:partie_id, :user_id, 'annuler_partie', :details)";
            $actionStmt = $conn->prepare($actionQuery);
            $actionStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
            $actionStmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $details = json_encode(['raison' => 'annulation_admin', 'timestamp' => time()]);
            $actionStmt->bindParam(':details', $details, PDO::PARAM_STR);
            $actionStmt->execute();
            
            $message = $texts['game_cancelled_success'];
            $success = true;
            break;
            
        case 'next_level':
            // PASSER AU NIVEAU SUIVANT
            if ($partie['status'] !== 'niveau_termine') {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Le niveau n\'est pas terminé']);
                exit();
            }
            
            $currentLevel = $partie['niveau'];
            if ($currentLevel >= 12) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $texts['max_level_reached']]);
                exit();
            }
            
            $newLevel = $currentLevel + 1;
            
            // Calculer les bonus selon les règles du jeu
            $bonusVie = false;
            $bonusShuriken = false;
            $viesRestantes = $partie['vies_restantes'];
            $shurikensRestants = $partie['shurikens_restants'];
            
            // Bonus vie aux niveaux 3, 6, 9, 12
            if (in_array($newLevel, [3, 6, 9, 12])) {
                $bonusVie = true;
                $viesRestantes++;
            }
            
            // Bonus shuriken aux niveaux 2, 5, 8, 11
            if (in_array($newLevel, [2, 5, 8, 11])) {
                $bonusShuriken = true;
                $shurikensRestants++;
            }
            
            // Mettre à jour la partie
            $updateLevelQuery = "UPDATE Parties 
                                SET niveau = :niveau, status = 'en_cours', 
                                    vies_restantes = :vies, shurikens_restants = :shurikens 
                                WHERE id = :partie_id";
            $updateLevelStmt = $conn->prepare($updateLevelQuery);
            $updateLevelStmt->bindParam(':niveau', $newLevel, PDO::PARAM_INT);
            $updateLevelStmt->bindParam(':vies', $viesRestantes, PDO::PARAM_INT);
            $updateLevelStmt->bindParam(':shurikens', $shurikensRestants, PDO::PARAM_INT);
            $updateLevelStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
            $updateLevelStmt->execute();
            
            // Supprimer toutes les anciennes cartes
            $deleteCardsQuery = "DELETE FROM Cartes WHERE id_partie = :partie_id";
            $deleteCardsStmt = $conn->prepare($deleteCardsQuery);
            $deleteCardsStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
            $deleteCardsStmt->execute();
            
            // Distribuer de nouvelles cartes
            distributeCards($conn, $partie_id, $newLevel, $partie['nombre_joueurs']);
            
            // Enregistrer l'action
            $actionQuery = "INSERT INTO Actions_jeu (id_partie, id_utilisateur, type_action, details) 
                           VALUES (:partie_id, :user_id, 'nouveau_niveau', :details)";
            $actionStmt = $conn->prepare($actionQuery);
            $actionStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
            $actionStmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $details = json_encode([
                'ancien_niveau' => $currentLevel,
                'nouveau_niveau' => $newLevel,
                'bonus_vie' => $bonusVie,
                'bonus_shuriken' => $bonusShuriken,
                'vies_restantes' => $viesRestantes,
                'shurikens_restants' => $shurikensRestants
            ]);
            $actionStmt->bindParam(':details', $details, PDO::PARAM_STR);
            $actionStmt->execute();
            
            // Construire le message de succès
            $message = $texts['level'] . ' ' . $newLevel . ' ' . $texts['started'];
            if ($bonusVie) {
                $message .= ' - ' . $texts['bonus'] . ' : +1 ' . $texts['life'];
            }
            if ($bonusShuriken) {
                $message .= ' - ' . $texts['bonus'] . ' : +1 ' . $texts['shuriken'];
            }
            
            $success = true;
            break;
            
        default:
            // Action non reconnue
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $texts['action_not_recognized']]);
            exit();
    }
    
    // ÉTAPE 10: Retourner la réponse de succès
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'action' => $action,
        'partie_id' => $partie_id
    ]);
    
} catch (PDOException $e) {
    // ÉTAPE 11: Gestion des erreurs
    error_log("Erreur admin_action.php: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $texts['server_error']]);
}

/**
 * FONCTION: distributeCards
 * DESCRIPTION: Distribue les cartes aux joueurs selon le niveau
 * PARAMÈTRES: 
 *   - $conn: Connexion PDO à la base de données
 *   - $partie_id: ID de la partie
 *   - $niveau: Niveau actuel (détermine le nombre de cartes par joueur)
 *   - $nombre_joueurs: Nombre de joueurs dans la partie
 */
function distributeCards($conn, $partie_id, $niveau, $nombre_joueurs) {
    // Le nombre de cartes par joueur = niveau
    $cardsPerPlayer = $niveau;
    
    // Récupérer les joueurs de la partie
    $joueursQuery = "SELECT id_utilisateur FROM Utilisateurs_parties 
                     WHERE id_partie = :partie_id ORDER BY position";
    $joueursStmt = $conn->prepare($joueursQuery);
    $joueursStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
    $joueursStmt->execute();
    $joueurs = $joueursStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Générer un deck de cartes (1-100) et le mélanger
    $deck = range(1, 100);
    shuffle($deck);
    
    // Distribuer les cartes aux joueurs
    $insertCarteQuery = "INSERT INTO Cartes (id_partie, id_utilisateur, valeur, etat, date_action) 
                        VALUES (:partie_id, :user_id, :valeur, 'en_main', NOW())";
    $insertCarteStmt = $conn->prepare($insertCarteQuery);
    
    foreach ($joueurs as $joueur) {
        $userId = $joueur['id_utilisateur'];
        
        for ($i = 0; $i < $cardsPerPlayer; $i++) {
            if (empty($deck)) {
                break; // Plus de cartes disponibles
            }
            
            $cardValue = array_pop($deck);
            
            $insertCarteStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
            $insertCarteStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $insertCarteStmt->bindParam(':valeur', $cardValue, PDO::PARAM_INT);
            $insertCarteStmt->execute();
        }
    }
}
?>