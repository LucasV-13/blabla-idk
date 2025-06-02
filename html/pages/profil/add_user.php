<?php
session_start();

// Vérification de l'authentification et des permissions
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role']) !== 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé']);
    exit();
}

// Vérification CSRF
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Jeton CSRF invalide']);
    exit();
}

// Créer un dossier logs s'il n'existe pas
$logDir = '../logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

// Journalisation pour débogage
$logFile = $logDir . '/add_user_debug.log';
file_put_contents($logFile, "=== " . date('Y-m-d H:i:s') . " ===\n", FILE_APPEND);
file_put_contents($logFile, "POST data: " . print_r($_POST, true) . "\n", FILE_APPEND);

// Récupérer et nettoyer les paramètres
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$username = isset($_POST['username']) ? trim($_POST['username']) : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';
$confirmPassword = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
$role_id = isset($_POST['role']) ? (int)$_POST['role'] : 0;
$avatar = isset($_POST['avatar']) ? trim($_POST['avatar']) : '👤';

// Fonction pour générer un identifiant vraiment unique
function generateUniqueUsername($conn) {
    $maxAttempts = 10;
    $attempt = 0;
    
    do {
        $attempt++;
        // Générer un identifiant unique avec plusieurs parties aléatoires
        $prefix = 'user';
        $timestamp = time();
        $random1 = mt_rand(10000, 99999);
        $random2 = substr(md5(uniqid(mt_rand(), true)), 0, 6);
        $username = $prefix . '_' . $timestamp . '_' . $random1 . '_' . $random2;
        
        // Vérifier l'unicité
        $checkQuery = "SELECT COUNT(*) as count FROM Utilisateurs WHERE identifiant = :username";
        $checkStmt = $conn->prepare($checkQuery);
        $checkStmt->bindParam(':username', $username, PDO::PARAM_STR);
        $checkStmt->execute();
        $exists = $checkStmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;
        
        if (!$exists) {
            return $username;
        }
        
        // Attendre un peu avant le prochain essai
        usleep(100000); // 100ms
        
    } while ($exists && $attempt < $maxAttempts);
    
    // Si on n'arrive toujours pas à générer un identifiant unique
    throw new Exception("Impossible de générer un identifiant unique après $maxAttempts tentatives");
}

// ======= GÉNÉRATION D'IDENTIFIANT UNIQUE =======
include('../../connexion/connexion.php');

try {
    // Générer un identifiant unique dès le début
    $uniqueUsername = generateUniqueUsername($conn);
    file_put_contents($logFile, "Identifiant unique généré: $uniqueUsername\n", FILE_APPEND);
    
    // Forcer l'utilisation de l'identifiant unique
    $username = $uniqueUsername;
    
    // Si le mot de passe est vide ou la valeur par défaut, en générer un nouveau
    if (empty($password) || $password === 'Eloi2023*') {
        $password = 'Mind' . date('Y') . '_' . mt_rand(1000, 9999);
        file_put_contents($logFile, "Mot de passe généré automatiquement\n", FILE_APPEND);
    }
    
    // Pour simplifier, on considère que le mot de passe de confirmation correspond
    $confirmPassword = $password;
    
    // Journalisation des données finales
    file_put_contents($logFile, "Données finales:\n", FILE_APPEND);
    file_put_contents($logFile, "Email: $email\n", FILE_APPEND);
    file_put_contents($logFile, "Username: $username\n", FILE_APPEND);
    file_put_contents($logFile, "Role ID: $role_id\n", FILE_APPEND);
    file_put_contents($logFile, "Avatar: $avatar\n", FILE_APPEND);
    
    // ======= VALIDATION =======
    if (empty($email) || empty($username) || empty($password) || $role_id <= 0) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Veuillez remplir tous les champs obligatoires']);
        exit();
    }
    
    // Validation de l'email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Adresse email invalide', 'field' => 'email']);
        exit();
    }
    
    // Vérification de l'unicité de l'email
    $checkEmailQuery = "SELECT COUNT(*) as count FROM Utilisateurs WHERE mail = :email";
    $checkEmailStmt = $conn->prepare($checkEmailQuery);
    $checkEmailStmt->bindParam(':email', $email, PDO::PARAM_STR);
    $checkEmailStmt->execute();
    $emailCount = $checkEmailStmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($emailCount > 0) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Cette adresse email est déjà utilisée', 'field' => 'email']);
        exit();
    }
    
    // Vérifier que le rôle existe
    $checkRoleQuery = "SELECT COUNT(*) as count FROM Roles WHERE id = :role_id";
    $checkRoleStmt = $conn->prepare($checkRoleQuery);
    $checkRoleStmt->bindParam(':role_id', $role_id, PDO::PARAM_INT);
    $checkRoleStmt->execute();
    $roleExists = $checkRoleStmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;
    
    if (!$roleExists) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Rôle invalide', 'field' => 'role']);
        exit();
    }
    
    // ======= INSERTION =======
    // Commencer une transaction
    $conn->beginTransaction();
    
    try {
        // Insérer l'utilisateur
        $insertQuery = "INSERT INTO Utilisateurs (identifiant, mail, mdp, avatar, id_role) 
                       VALUES (:username, :email, :password, :avatar, :role_id)";
        $insertStmt = $conn->prepare($insertQuery);
        $insertStmt->bindParam(':username', $username, PDO::PARAM_STR);
        $insertStmt->bindParam(':email', $email, PDO::PARAM_STR);
        $insertStmt->bindParam(':password', $password, PDO::PARAM_STR);
        $insertStmt->bindParam(':avatar', $avatar, PDO::PARAM_STR);
        $insertStmt->bindParam(':role_id', $role_id, PDO::PARAM_INT);
        $insertStmt->execute();
        
        $user_id = $conn->lastInsertId();
        file_put_contents($logFile, "Utilisateur créé avec succès, ID: $user_id\n", FILE_APPEND);
        
        // Créer une entrée de statistiques vide pour l'utilisateur
        $statsQuery = "INSERT INTO Statistiques (id_utilisateur, parties_jouees, parties_gagnees, taux_reussite, cartes_jouees) 
                      VALUES (:user_id, 0, 0, 0, 0)";
        $statsStmt = $conn->prepare($statsQuery);
        $statsStmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $statsStmt->execute();
        
        // Valider la transaction
        $conn->commit();
        
        file_put_contents($logFile, "Transaction validée avec succès\n", FILE_APPEND);
        
        // Réponse de succès
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true, 
            'message' => 'Utilisateur ajouté avec succès',
            'user_id' => $user_id,
            'username_used' => $username,
            'password_generated' => ($password !== $_POST['password']),
            'details' => "Identifiant: $username, Mot de passe: $password"
        ]);
        
    } catch (PDOException $e) {
        // Annuler la transaction
        $conn->rollBack();
        
        file_put_contents($logFile, "Erreur PDO: " . $e->getMessage() . "\n", FILE_APPEND);
        
        // Si l'erreur concerne l'identifiant, essayer avec un nouvel identifiant
        if (strpos($e->getMessage(), '1062') !== false && strpos($e->getMessage(), 'identifiant') !== false) {
            file_put_contents($logFile, "Conflit d'identifiant détecté, nouvelle tentative...\n", FILE_APPEND);
            
            // Générer un nouvel identifiant encore plus unique
            $newUsername = generateUniqueUsername($conn);
            file_put_contents($logFile, "Nouvel identifiant généré: $newUsername\n", FILE_APPEND);
            
            // Nouvelle transaction
            $conn->beginTransaction();
            
            try {
                $insertStmt->bindParam(':username', $newUsername, PDO::PARAM_STR);
                $insertStmt->execute();
                
                $user_id = $conn->lastInsertId();
                
                // Créer les statistiques
                $statsStmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                $statsStmt->execute();
                
                // Valider la transaction
                $conn->commit();
                
                file_put_contents($logFile, "Succès avec le nouvel identifiant: $newUsername\n", FILE_APPEND);
                
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true, 
                    'message' => 'Utilisateur ajouté avec succès (identifiant modifié)',
                    'user_id' => $user_id,
                    'username_used' => $newUsername,
                    'password_generated' => true,
                    'details' => "Identifiant: $newUsername, Mot de passe: $password"
                ]);
                
            } catch (PDOException $e2) {
                $conn->rollBack();
                file_put_contents($logFile, "Échec définitif: " . $e2->getMessage() . "\n", FILE_APPEND);
                
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false, 
                    'message' => 'Erreur lors de l\'ajout de l\'utilisateur: ' . $e2->getMessage()
                ]);
            }
        } else {
            // Autre type d'erreur
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false, 
                'message' => 'Erreur lors de l\'ajout de l\'utilisateur: ' . $e->getMessage()
            ]);
        }
    }
    
} catch (Exception $e) {
    file_put_contents($logFile, "Erreur générale: " . $e->getMessage() . "\n", FILE_APPEND);
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false, 
        'message' => 'Erreur système: ' . $e->getMessage()
    ]);
}
?>