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
    'lower_card_exists' => 'Des cartes plus petites existaient chez les autres joueurs',
    'server_error' => 'Erreur serveur',
    'card_played_success' => 'Carte jouée avec succès',
    'card_played_error' => 'Carte jouée, mais des cartes plus petites existaient. Tout le monde perd une vie !',
    'wrong_order' => 'Cette carte ne peut pas être jouée dans cet ordre',
    'game_over' => 'Partie terminée - Toutes les vies ont été perdues'
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
    
    $valeurCarteJouee = (int)$carte['valeur'];
    error_log("play_card.php - Carte trouvée: valeur=" . $valeurCarteJouee);
    
    // ÉTAPE 1: Vérifier la dernière carte jouée pour s'assurer de l'ordre croissant
    $derniereCarteQuery = "SELECT MAX(valeur) as max_valeur 
                          FROM Cartes 
                          WHERE id_partie = :partie_id AND etat = 'jouee'";
    $derniereCarteStmt = $conn->prepare($derniereCarteQuery);
    $derniereCarteStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
    $derniereCarteStmt->execute();
    $maxValeurJouee = $derniereCarteStmt->fetch(PDO::FETCH_ASSOC)['max_valeur'] ?: 0;
    
    // La carte jouée doit être supérieure à la dernière carte jouée
    if ($valeurCarteJouee <= $maxValeurJouee) {
        error_log("play_card.php - Carte inférieure à la dernière jouée: $valeurCarteJouee <= $maxValeurJouee");
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false, 
            'message' => $texts['wrong_order'] . " (dernière carte jouée: $maxValeurJouee)"
        ]);
        exit();
    }
    
    // ÉTAPE 2: Vérifier s'il existe des cartes plus petites que celle jouée chez TOUS les joueurs
    $cartesPlusPetitesQuery = "SELECT 
                                 COUNT(*) as nb_cartes_plus_petites,
                                 MIN(valeur) as min_valeur,
                                 GROUP_CONCAT(DISTINCT u.identifiant SEPARATOR ', ') as joueurs_concernes
                               FROM Cartes c
                               JOIN Utilisateurs u ON c.id_utilisateur = u.id
                               WHERE c.id_partie = :partie_id 
                               AND c.etat = 'en_main' 
                               AND c.valeur < :valeur_carte_jouee";
    $cartesPlusPetitesStmt = $conn->prepare($cartesPlusPetitesQuery);
    $cartesPlusPetitesStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
    $cartesPlusPetitesStmt->bindParam(':valeur_carte_jouee', $valeurCarteJouee, PDO::PARAM_INT);
    $cartesPlusPetitesStmt->execute();
    $resultPlusPetites = $cartesPlusPetitesStmt->fetch(PDO::FETCH_ASSOC);
    
    $nbCartesPlusPetites = (int)$resultPlusPetites['nb_cartes_plus_petites'];
    $minValeurEnJeu = $resultPlusPetites['min_valeur'];
    $joueursConcernes = $resultPlusPetites['joueurs_concernes'];
    
    error_log("play_card.php - Vérifications: carte_jouee=$valeurCarteJouee, derniere_jouee=$maxValeurJouee, nb_plus_petites=$nbCartesPlusPetites, min_en_jeu=$minValeurEnJeu, joueurs=$joueursConcernes");
    
    // Commencer une transaction
    $conn->beginTransaction();
    
    // ÉTAPE 3: TOUJOURS jouer la carte d'abord
    $jouerCarteQuery = "UPDATE Cartes SET etat = 'jouee', date_action = NOW() WHERE id = :card_id";
    $jouerCarteStmt = $conn->prepare($jouerCarteQuery);
    $jouerCarteStmt->bindParam(':card_id', $card_id, PDO::PARAM_INT);
    $jouerCarteStmt->execute();
    
    error_log("play_card.php - Carte jouée avec succès");
    
    // ÉTAPE 4: Déterminer s'il y a une erreur (cartes plus petites en jeu)
    $erreurCarte = ($nbCartesPlusPetites > 0);
    $viesRestantes = $partie['vies_restantes'];
    
    if ($erreurCarte) {
        // ❌ ERREUR: Il existait des cartes plus petites en jeu
        // Tout le monde perd une vie
        $viesRestantes = $partie['vies_restantes'] - 1;
        
        $updateViesQuery = "UPDATE Parties SET vies_restantes = :vies WHERE id = :partie_id";
        $updateViesStmt = $conn->prepare($updateViesQuery);
        $updateViesStmt->bindParam(':vies', $viesRestantes, PDO::PARAM_INT);
        $updateViesStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
        $updateViesStmt->execute();
        
        error_log("play_card.php - ERREUR: Carte jouée trop tôt. Carte jouée: $valeurCarteJouee, Plus petite carte en jeu: $minValeurEnJeu chez: $joueursConcernes, Vies restantes: $viesRestantes");
        
        // Ajouter une entrée dans Actions_jeu pour l'erreur
        $actionQuery = "INSERT INTO Actions_jeu (id_partie, id_utilisateur, type_action, details) 
                       VALUES (:partie_id, :user_id, 'perdre_vie', :details)";
        $actionStmt = $conn->prepare($actionQuery);
        $actionStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
        $actionStmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $details = json_encode([
            'carte_jouee' => $valeurCarteJouee,
            'carte_min_en_jeu' => $minValeurEnJeu,
            'nb_cartes_plus_petites' => $nbCartesPlusPetites,
            'joueurs_concernes' => $joueursConcernes,
            'raison' => 'carte_jouee_trop_tot',
            'vies_avant' => $partie['vies_restantes'],
            'vies_après' => $viesRestantes
        ]);
        $actionStmt->bindParam(':details', $details, PDO::PARAM_STR);
        $actionStmt->execute();
        
        // Vérifier si la partie est perdue (plus de vies)
        if ($viesRestantes <= 0) {
            $updatePartieQuery = "UPDATE Parties SET status = 'terminee', date_fin = NOW() WHERE id = :partie_id";
            $updatePartieStmt = $conn->prepare($updatePartieQuery);
            $updatePartieStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
            $updatePartieStmt->execute();
            
            error_log("play_card.php - Partie terminée, plus de vies");
            
            // Enregistrer la fin de partie
            $actionFinQuery = "INSERT INTO Actions_jeu (id_partie, id_utilisateur, type_action, details) 
                              VALUES (:partie_id, :user_id, 'fin_partie', :details)";
            $actionFinStmt = $conn->prepare($actionFinQuery);
            $actionFinStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
            $actionFinStmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $detailsFinPartie = json_encode([
                'resultat' => 'defaite', 
                'niveau_atteint' => $partie['niveau'],
                'raison' => 'plus_de_vies',
                'derniere_carte_jouee' => $valeurCarteJouee
            ]);
            $actionFinStmt->bindParam(':details', $detailsFinPartie, PDO::PARAM_STR);
            $actionFinStmt->execute();
        }
    }
    
    // ÉTAPE 5: Ajouter une entrée dans Actions_jeu pour la carte jouée
    $actionJouerQuery = "INSERT INTO Actions_jeu (id_partie, id_utilisateur, type_action, details) 
                       VALUES (:partie_id, :user_id, 'jouer_carte', :details)";
    $actionJouerStmt = $conn->prepare($actionJouerQuery);
    $actionJouerStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
    $actionJouerStmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $detailsJouer = json_encode([
        'carte_id' => $card_id, 
        'valeur' => $valeurCarteJouee,
        'erreur' => $erreurCarte,
        'vies_après' => $viesRestantes
    ]);
    $actionJouerStmt->bindParam(':details', $detailsJouer, PDO::PARAM_STR);
    $actionJouerStmt->execute();
    
    // ÉTAPE 6: Vérifier si toutes les cartes ont été jouées (niveau terminé)
    $cartesRestantesQuery = "SELECT COUNT(*) as nbCartes FROM Cartes WHERE id_partie = :partie_id AND etat = 'en_main'";
    $cartesRestantesStmt = $conn->prepare($cartesRestantesQuery);
    $cartesRestantesStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
    $cartesRestantesStmt->execute();
    $cartesRestantes = $cartesRestantesStmt->fetch(PDO::FETCH_ASSOC)['nbCartes'];
    
    error_log("play_card.php - Cartes restantes: $cartesRestantes");
    
    $partieStatusFinal = $partie['status']; // Garder le statut actuel par défaut
    
    // Ne vérifier la fin de niveau que si on n'a pas perdu et qu'il reste des vies
    if ($cartesRestantes === 0 && $viesRestantes > 0) {
        // Toutes les cartes ont été jouées, niveau terminé avec succès
        if ($partie['niveau'] >= 12) {
            // Partie terminée (gagnée)
            $updatePartieQuery = "UPDATE Parties SET status = 'gagnee', date_fin = NOW() WHERE id = :partie_id";
            $updatePartieStmt = $conn->prepare($updatePartieQuery);
            $updatePartieStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
            $updatePartieStmt->execute();
            
            $partieStatusFinal = 'gagnee';
            error_log("play_card.php - Partie gagnée ! Niveau 12 terminé");
            
            // Enregistrer le score de victoire
            $actionVictoireQuery = "INSERT INTO Actions_jeu (id_partie, id_utilisateur, type_action, details) 
                                  VALUES (:partie_id, :user_id, 'victoire', :details)";
            $actionVictoireStmt = $conn->prepare($actionVictoireQuery);
            $actionVictoireStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
            $actionVictoireStmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $detailsVictoire = json_encode(['resultat' => 'victoire', 'niveau_final' => $partie['niveau']]);
            $actionVictoireStmt->bindParam(':details', $detailsVictoire, PDO::PARAM_STR);
            $actionVictoireStmt->execute();
        } else {
            // Passer au niveau suivant
            $updatePartieQuery = "UPDATE Parties SET status = 'niveau_termine' WHERE id = :partie_id";
            $updatePartieStmt = $conn->prepare($updatePartieQuery);
            $updatePartieStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
            $updatePartieStmt->execute();
            
            $partieStatusFinal = 'niveau_termine';
            error_log("play_card.php - Niveau " . $partie['niveau'] . " terminé");
            
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
    
    // ÉTAPE 7: Renvoyer une réponse de succès avec toutes les informations
    header('Content-Type: application/json');
    
    // Choisir le message approprié
    if ($viesRestantes <= 0) {
        $successMessage = $texts['game_over'];
    } elseif ($erreurCarte) {
        $successMessage = $texts['card_played_error'] . " (Plus petite carte: $minValeurEnJeu chez $joueursConcernes)";
    } else {
        $successMessage = $texts['card_played_success'];
    }
    
    $response = [
        'success' => true,
        'error_card' => $erreurCarte,
        'message' => $successMessage,
        'cartes_restantes' => $cartesRestantes,
        'partie_status' => $partieStatusFinal,
        'carte_jouee' => $valeurCarteJouee,
        'derniere_carte_jouee' => $maxValeurJouee,
        'plus_petite_carte_en_jeu' => $minValeurEnJeu,
        'nb_cartes_plus_petites' => $nbCartesPlusPetites,
        'joueurs_concernes' => $joueursConcernes,
        'vies_restantes' => $viesRestantes,
        'vies_perdues' => $erreurCarte ? 1 : 0,
        'game_over' => ($viesRestantes <= 0)
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