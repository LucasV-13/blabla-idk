<?php
session_start();

// Gestion des textes multilingues
$language = isset($_SESSION['language']) ? $_SESSION['language'] : 'fr';

// Définir les textes par défaut d'abord
$texts = [
    'unauthorized' => 'Non autorisé',
    'invalid_csrf_token' => 'Token CSRF invalide', 
    'invalid_parameters' => 'Paramètres invalides',
    'game_not_found' => 'Partie non trouvée',
    'game_not_in_progress' => 'La partie n\'est pas en cours',
    'invalid_card' => 'Carte invalide',
    'lower_card_exists' => 'Une carte plus petite existe',
    'server_error' => 'Erreur serveur',
    'card_played_success' => 'Carte jouée avec succès',
    'card_played_error' => 'Carte jouée, mais il y avait une carte plus petite en jeu. Vous perdez une vie !'
];

// Charger le fichier de langue si disponible
$language_file = '../../languages/' . $language . '.php';
if (file_exists($language_file)) {
    include($language_file);
}

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
$card_id = isset($_POST['card_id']) ? (int)$_POST['card_id'] : 0;
$partie_id = isset($_POST['partie_id']) ? (int)$_POST['partie_id'] : 0;
$user_id = $_SESSION['user_id'];

// Debug logging
error_log("play_card.php - Paramètres reçus: card_id=$card_id, partie_id=$partie_id, user_id=$user_id");

if ($card_id <= 0 || $partie_id <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $texts['invalid_parameters']]);
    exit();
}

// Inclure la connexion à la base de données
$connexion_file = '../../connexion/connexion.php';
if (!file_exists($connexion_file)) {
    // Fallback si le fichier de connexion n'existe pas
    try {
        $conn = new PDO("mysql:host=localhost;dbname=bddthemind;charset=utf8mb4", "user", "Eloi2023*");
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        error_log("Erreur connexion DB play_card.php: " . $e->getMessage());
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $texts['server_error']]);
        exit();
    }
} else {
    include($connexion_file);
}

try {
    // Vérifier l'état de la partie
    $partieQuery = "SELECT status, niveau, vies_restantes FROM Parties WHERE id = :partie_id";
    $partieStmt = $conn->prepare($partieQuery);
    $partieStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
    $partieStmt->execute();
    
    $partie = $partieStmt->fetch(PDO::FETCH_ASSOC);
    if (!$partie) {
        error_log("play_card.php - Partie non trouvée: $partie_id");
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $texts['game_not_found']]);
        exit();
    }
    
    if ($partie['status'] !== 'en_cours') {
        error_log("play_card.php - Partie pas en cours: " . $partie['status']);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $texts['game_not_in_progress']]);
        exit();
    }
    
    // Vérifier que la carte appartient au joueur et est jouable
    $carteQuery = "SELECT valeur 
                   FROM Cartes 
                   WHERE id = :card_id AND id_partie = :partie_id AND id_utilisateur = :user_id AND etat = 'en_main'";
    $carteStmt = $conn->prepare($carteQuery);
    $carteStmt->bindParam(':card_id', $card_id, PDO::PARAM_INT);
    $carteStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
    $carteStmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $carteStmt->execute();
    
    $carte = $carteStmt->fetch(PDO::FETCH_ASSOC);
    if (!$carte) {
        error_log("play_card.php - Carte invalide: card_id=$card_id, user_id=$user_id");
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $texts['invalid_card']]);
        exit();
    }
    
    error_log("play_card.php - Carte trouvée: valeur=" . $carte['valeur']);
    
    // Vérifier quelle est la plus petite carte en jeu (toutes mains confondues)
    $verifCarteQuery = "SELECT MIN(valeur) as min_valeur 
                        FROM Cartes 
                        WHERE id_partie = :partie_id AND etat = 'en_main'";
    $verifCarteStmt = $conn->prepare($verifCarteQuery);
    $verifCarteStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
    $verifCarteStmt->execute();
    $minValeur = $verifCarteStmt->fetch(PDO::FETCH_ASSOC)['min_valeur'];
    
    // Récupérer la dernière carte jouée
    $derniereCarteQuery = "SELECT MAX(valeur) as max_valeur 
                          FROM Cartes 
                          WHERE id_partie = :partie_id AND etat = 'jouee'";
    $derniereCarteStmt = $conn->prepare($derniereCarteQuery);
    $derniereCarteStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
    $derniereCarteStmt->execute();
    $maxValeurJouee = $derniereCarteStmt->fetch(PDO::FETCH_ASSOC)['max_valeur'] ?: 0;
    
    error_log("play_card.php - Vérifications: carte_valeur=" . $carte['valeur'] . ", min_valeur=$minValeur, max_jouee=$maxValeurJouee");
    
    // Vérifier si la carte est jouée dans le bon ordre
    if ($carte['valeur'] < $maxValeurJouee) {
        // Erreur : la carte est plus petite que la dernière carte jouée
        error_log("play_card.php - Carte trop petite par rapport à la dernière jouée");
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $texts['lower_card_exists'] . ' (dernière jouée: ' . $maxValeurJouee . ')']);
        exit();
    }
    
    // Commencer une transaction
    $conn->beginTransaction();
    
    // Vérifier si la carte est la plus petite de toutes les cartes en jeu
    $erreurCarte = false;
    if ($carte['valeur'] > $minValeur) {
        // Il existe une carte plus petite en jeu
        // Réduire les vies de la partie
        $viesRestantes = $partie['vies_restantes'] - 1;
        
        $updateViesQuery = "UPDATE Parties SET vies_restantes = :vies WHERE id = :partie_id";
        $updateViesStmt = $conn->prepare($updateViesQuery);
        $updateViesStmt->bindParam(':vies', $viesRestantes, PDO::PARAM_INT);
        $updateViesStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
        $updateViesStmt->execute();
        
        $erreurCarte = true;
        
        error_log("play_card.php - Erreur de carte, vie perdue. Vies restantes: $viesRestantes");
        
        // Ajouter une entrée dans Actions_jeu
        $actionQuery = "INSERT INTO Actions_jeu (id_partie, id_utilisateur, type_action, details) 
                       VALUES (:partie_id, :user_id, 'perdre_vie', :details)";
        $actionStmt = $conn->prepare($actionQuery);
        $actionStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
        $actionStmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $details = json_encode(['carte_jouee' => $carte['valeur'], 'carte_min' => $minValeur]);
        $actionStmt->bindParam(':details', $details, PDO::PARAM_STR);
        $actionStmt->execute();
        
        // Vérifier si la partie est perdue (plus de vies)
        if ($viesRestantes <= 0) {
            $updatePartieQuery = "UPDATE Parties SET status = 'terminee', date_fin = NOW() WHERE id = :partie_id";
            $updatePartieStmt = $conn->prepare($updatePartieQuery);
            $updatePartieStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
            $updatePartieStmt->execute();
            
            // Appeler la procédure pour enregistrer les scores si elle existe
            try {
                $saveScoreQuery = "CALL save_game_score(:partie_id, FALSE)";
                $saveScoreStmt = $conn->prepare($saveScoreQuery);
                $saveScoreStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
                $saveScoreStmt->execute();
            } catch (PDOException $e) {
                // La procédure n'existe peut-être pas, continuer sans erreur
                error_log("play_card.php - Procédure save_game_score non disponible: " . $e->getMessage());
            }
        }
    }
    
    // Jouer la carte (mettre à jour son état)
    $jouerCarteQuery = "UPDATE Cartes SET etat = 'jouee', date_action = NOW() WHERE id = :card_id";
    $jouerCarteStmt = $conn->prepare($jouerCarteQuery);
    $jouerCarteStmt->bindParam(':card_id', $card_id, PDO::PARAM_INT);
    $jouerCarteStmt->execute();
    
    error_log("play_card.php - Carte jouée avec succès");
    
    // Ajouter une entrée dans Actions_jeu pour la carte jouée
    $actionJouerQuery = "INSERT INTO Actions_jeu (id_partie, id_utilisateur, type_action, details) 
                       VALUES (:partie_id, :user_id, 'jouer_carte', :details)";
    $actionJouerStmt = $conn->prepare($actionJouerQuery);
    $actionJouerStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
    $actionJouerStmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $detailsJouer = json_encode(['carte_id' => $card_id, 'valeur' => $carte['valeur']]);
    $actionJouerStmt->bindParam(':details', $detailsJouer, PDO::PARAM_STR);
    $actionJouerStmt->execute();
    
    // Vérifier si toutes les cartes ont été jouées (niveau terminé)
    $cartesRestantesQuery = "SELECT COUNT(*) as nbCartes FROM Cartes WHERE id_partie = :partie_id AND etat = 'en_main'";
    $cartesRestantesStmt = $conn->prepare($cartesRestantesQuery);
    $cartesRestantesStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
    $cartesRestantesStmt->execute();
    $cartesRestantes = $cartesRestantesStmt->fetch(PDO::FETCH_ASSOC)['nbCartes'];
    
    error_log("play_card.php - Cartes restantes: $cartesRestantes");
    
    $partieStatusFinal = 'en_cours';
    
    if ($cartesRestantes === 0) {
        // Toutes les cartes ont été jouées, niveau terminé avec succès
        if ($partie['niveau'] >= 12) {
            // Partie terminée (gagné)
            $updatePartieQuery = "UPDATE Parties SET status = 'gagnee', date_fin = NOW() WHERE id = :partie_id";
            $updatePartieStmt = $conn->prepare($updatePartieQuery);
            $updatePartieStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
            $updatePartieStmt->execute();
            
            $partieStatusFinal = 'gagnee';
            
            // Appeler la procédure pour enregistrer les scores si elle existe
            try {
                $saveScoreQuery = "CALL save_game_score(:partie_id, TRUE)";
                $saveScoreStmt = $conn->prepare($saveScoreQuery);
                $saveScoreStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
                $saveScoreStmt->execute();
            } catch (PDOException $e) {
                error_log("play_card.php - Procédure save_game_score non disponible: " . $e->getMessage());
            }
        } else {
            // Passer au niveau suivant
            $updatePartieQuery = "UPDATE Parties SET status = 'niveau_termine' WHERE id = :partie_id";
            $updatePartieStmt = $conn->prepare($updatePartieQuery);
            $updatePartieStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
            $updatePartieStmt->execute();
            
            $partieStatusFinal = 'niveau_termine';
            
            // Ajouter une entrée dans Actions_jeu
            $actionNiveauQuery = "INSERT INTO Actions_jeu (id_partie, id_utilisateur, type_action, details) 
                               VALUES (:partie_id, :user_id, 'niveau_termine', :details)";
            $actionNiveauStmt = $conn->prepare($actionNiveauQuery);
            $actionNiveauStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
            $actionNiveauStmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $detailsNiveau = json_encode(['niveau_termine' => $partie['niveau']]);
            $actionNiveauStmt->bindParam(':details', $detailsNiveau, PDO::PARAM_STR);
            $actionNiveauStmt->execute();
        }
    }
    
    // Valider la transaction
    $conn->commit();
    
    // Renvoyer une réponse de succès
    header('Content-Type: application/json');
    
    // Choisir le message approprié en fonction de s'il y a eu une erreur de carte
    $successMessage = $erreurCarte ? 
        $texts['card_played_error'] : 
        $texts['card_played_success'];
    
    $response = [
        'success' => true,
        'error_card' => $erreurCarte,
        'message' => $successMessage,
        'cartes_restantes' => $cartesRestantes,
        'partie_status' => $partieStatusFinal
    ];
    
    error_log("play_card.php - Réponse: " . json_encode($response));
    
    echo json_encode($response);
    
} catch (PDOException $e) {
    // Annuler la transaction en cas d'erreur
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    
    error_log("Erreur play_card.php: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $texts['server_error']]);
}
?>