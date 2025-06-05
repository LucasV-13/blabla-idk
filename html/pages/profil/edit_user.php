<?php
session_start();

// Vérification de l'authentification et des permissions
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role']) !== 'admin') {
    header("Location: ../dashboard.php");
    exit();
}

// Protection CSRF
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

include('../../connexion/connexion.php');

// Récupérer l'ID de l'utilisateur à modifier
$userId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Vérifier si l'ID est valide
if ($userId <= 0) {
    $_SESSION['error_message'] = "ID utilisateur invalide.";
    header("Location: profil.php");
    exit();
}

// Récupérer les données de l'utilisateur
try {
    $userQuery = "SELECT u.id, u.identifiant, u.mail, u.mdp, u.avatar, r.id as role_id, r.nom as role_nom 
                 FROM Utilisateurs u 
                 JOIN Roles r ON u.id_role = r.id 
                 WHERE u.id = :user_id";
    $userStmt = $conn->prepare($userQuery);
    $userStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $userStmt->execute();
    
    if (!($user = $userStmt->fetch(PDO::FETCH_ASSOC))) {
        $_SESSION['error_message'] = "Utilisateur non trouvé.";
        header("Location: profil.php");
        exit();
    }
    
    // Récupérer la liste des rôles disponibles
    $rolesQuery = "SELECT id, nom FROM Roles ORDER BY id";
    $rolesStmt = $conn->prepare($rolesQuery);
    $rolesStmt->execute();
    $roles = $rolesStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Erreur edit_user.php: " . $e->getMessage());
    $_SESSION['error_message'] = "Une erreur est survenue lors de la récupération des données.";
    header("Location: profil.php");
    exit();
}

// Traitement du formulaire de modification
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérification CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error_message'] = "Erreur de validation du formulaire.";
        header("Location: profil.php");
        exit();
    }
    
    // Récupérer les données du formulaire
    $newUsername = isset($_POST['username']) ? trim($_POST['username']) : '';
    $newEmail = isset($_POST['email']) ? trim($_POST['email']) : '';
    $newPassword = isset($_POST['password']) ? trim($_POST['password']) : '';
    $newRoleId = isset($_POST['role_id']) ? (int)$_POST['role_id'] : 0;
    $newAvatar = isset($_POST['avatar']) ? trim($_POST['avatar']) : '';
    
    // Valider les données
    if (empty($newUsername) || empty($newEmail)) {
        $_SESSION['error_message'] = "Les champs nom d'utilisateur et email sont obligatoires.";
        header("Location: edit_user.php?id=$userId");
        exit();
    }
    
    // Valider l'email
    if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error_message'] = "Adresse email invalide.";
        header("Location: edit_user.php?id=$userId");
        exit();
    }
    
    try {
        // Commencer une transaction
        $conn->beginTransaction();
        
        // Vérifier l'unicité du nom d'utilisateur (sauf pour l'utilisateur actuel)
        $checkUsernameQuery = "SELECT COUNT(*) as count FROM Utilisateurs WHERE identifiant = :username AND id != :user_id";
        $checkUsernameStmt = $conn->prepare($checkUsernameQuery);
        $checkUsernameStmt->bindParam(':username', $newUsername, PDO::PARAM_STR);
        $checkUsernameStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $checkUsernameStmt->execute();
        $usernameCount = $checkUsernameStmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        if ($usernameCount > 0) {
            throw new Exception("Ce nom d'utilisateur est déjà utilisé.");
        }
        
        // Vérifier l'unicité de l'email (sauf pour l'utilisateur actuel)
        $checkEmailQuery = "SELECT COUNT(*) as count FROM Utilisateurs WHERE mail = :email AND id != :user_id";
        $checkEmailStmt = $conn->prepare($checkEmailQuery);
        $checkEmailStmt->bindParam(':email', $newEmail, PDO::PARAM_STR);
        $checkEmailStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $checkEmailStmt->execute();
        $emailCount = $checkEmailStmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        if ($emailCount > 0) {
            throw new Exception("Cette adresse email est déjà utilisée.");
        }
        
        // Préparer la requête de mise à jour
        $updateFields = [
            'identifiant = :username',
            'mail = :email'
        ];
        
        $params = [
            ':username' => $newUsername,
            ':email' => $newEmail,
            ':user_id' => $userId
        ];
        
        // Ajouter le mot de passe s'il est fourni
        if (!empty($newPassword)) {
            $updateFields[] = 'mdp = :password';
            // Hasher le mot de passe pour une meilleure sécurité
            $params[':password'] = password_hash($newPassword, PASSWORD_DEFAULT);
        }
        
        // Ajouter le rôle s'il est valide
        if ($newRoleId > 0) {
            // Vérifier que le rôle existe
            $checkRoleQuery = "SELECT COUNT(*) as count FROM Roles WHERE id = :role_id";
            $checkRoleStmt = $conn->prepare($checkRoleQuery);
            $checkRoleStmt->bindParam(':role_id', $newRoleId, PDO::PARAM_INT);
            $checkRoleStmt->execute();
            $roleExists = $checkRoleStmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;
            
            if ($roleExists) {
                $updateFields[] = 'id_role = :role_id';
                $params[':role_id'] = $newRoleId;
            }
        }
        
        // Ajouter l'avatar s'il est fourni
        if (!empty($newAvatar)) {
            $updateFields[] = 'avatar = :avatar';
            $params[':avatar'] = $newAvatar;
        }
        
        // Construire et exécuter la requête
        $updateQuery = "UPDATE Utilisateurs SET " . implode(', ', $updateFields) . " WHERE id = :user_id";
        $updateStmt = $conn->prepare($updateQuery);
        $updateStmt->execute($params);
        
        // Valider la transaction
        $conn->commit();
        
        $_SESSION['success_message'] = "Utilisateur modifié avec succès.";
        header("Location: profil.php");
        exit();
        
    } catch (Exception $e) {
        // Annuler la transaction en cas d'erreur
        $conn->rollBack();
        error_log("Erreur modification utilisateur: " . $e->getMessage());
        $_SESSION['error_message'] = $e->getMessage();
        header("Location: edit_user.php?id=$userId");
        exit();
    }
}

// Récupérer les messages
$errorMessage = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : '';
$successMessage = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';
unset($_SESSION['error_message'], $_SESSION['success_message']);

// Gestion des textes multilingues
$language = isset($_SESSION['language']) ? $_SESSION['language'] : 'fr';
if ($language === 'fr') {
    $texts = [
        'edit_user_title' => 'Modifier l\'utilisateur',
        'username' => 'Nom d\'utilisateur',
        'email' => 'Email',
        'password' => 'Nouveau mot de passe',
        'password_help' => '(laisser vide pour ne pas modifier)',
        'role' => 'Rôle',
        'avatar' => 'Avatar',
        'save_changes' => 'Enregistrer les modifications',
        'cancel' => 'Annuler',
        'required_field' => 'Champ requis'
    ];
} else {
    $texts = [
        'edit_user_title' => 'Edit User',
        'username' => 'Username',
        'email' => 'Email',
        'password' => 'New Password',
        'password_help' => '(leave empty to keep current password)',
        'role' => 'Role',
        'avatar' => 'Avatar',
        'save_changes' => 'Save Changes',
        'cancel' => 'Cancel',
        'required_field' => 'Required field'
    ];
}
?>

<!DOCTYPE html>
<html lang="<?php echo $language; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $texts['edit_user_title']; ?> - The Mind</title>
    <link rel="stylesheet" href="../../assets/css/styleProfil.css">
    <link rel="stylesheet" href="../../assets/css/styleMenu.css">
    <style>
        .edit-user-container {
            max-width: 600px;
            margin: 20px auto;
            background-color: #222;
            border-radius: 10px;
            padding: 30px;
            color: #fff;
        }
        
        .form-header {
            margin-bottom: 30px;
            text-align: center;
        }
        
        .form-header h2 {
            color: var(--secondary-color);
            margin-bottom: 10px;
        }
        
        .message {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .error-message {
            background-color: rgba(244, 67, 54, 0.2);
            color: #f44336;
            border: 1px solid #f44336;
        }
        
        .success-message {
            background-color: rgba(76, 175, 80, 0.2);
            color: #4CAF50;
            border: 1px solid #4CAF50;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--secondary-color);
            font-weight: bold;
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px;
            background-color: #333;
            border: 1px solid #555;
            border-radius: 5px;
            color: #fff;
            font-size: 14px;
            box-sizing: border-box;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--accent-color);
            box-shadow: 0 0 5px rgba(0, 194, 203, 0.3);
        }
        
        .form-help {
            font-size: 12px;
            color: #999;
            margin-top: 5px;
        }
        
        .form-footer {
            display: flex;
            justify-content: space-between;
            margin-top: 30px;
            gap: 15px;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 5px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            min-width: 120px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: var(--dark-color);
        }
        
        .btn-secondary {
            background-color: #666;
            color: #fff;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
        }
        
        .avatar-options {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 10px;
        }
        
        .avatar-option {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: #444;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 20px;
            border: 2px solid transparent;
            transition: all 0.3s;
        }
        
        .avatar-option:hover {
            background-color: #555;
        }
        
        .avatar-option.selected {
            border-color: var(--accent-color);
            background-color: rgba(0, 194, 203, 0.2);
        }
        
        @media (max-width: 768px) {
            .edit-user-container {
                margin: 10px;
                padding: 20px;
            }
            
            .form-footer {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <input type="hidden" id="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
    <input type="hidden" id="base_url" value="../../">

    <div class="edit-user-container">
        <div class="form-header">
            <h2><?php echo $texts['edit_user_title']; ?></h2>
            <p>ID: <?php echo $userId; ?> - <?php echo htmlspecialchars($user['identifiant']); ?></p>
        </div>
        
        <?php if (!empty($errorMessage)): ?>
        <div class="message error-message">
            <?php echo htmlspecialchars($errorMessage); ?>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($successMessage)): ?>
        <div class="message success-message">
            <?php echo htmlspecialchars($successMessage); ?>
        </div>
        <?php endif; ?>
        
        <form method="post" action="">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            
            <div class="form-group">
                <label for="username"><?php echo $texts['username']; ?> *</label>
                <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($user['identifiant']); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="email"><?php echo $texts['email']; ?> *</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['mail']); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="password"><?php echo $texts['password']; ?></label>
                <input type="password" id="password" name="password" autocomplete="new-password">
                <div class="form-help"><?php echo $texts['password_help']; ?></div>
            </div>
            
            <div class="form-group">
                <label for="role_id"><?php echo $texts['role']; ?></label>
                <select id="role_id" name="role_id">
                    <?php foreach ($roles as $role): ?>
                    <option value="<?php echo $role['id']; ?>" <?php echo ($role['id'] == $user['role_id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($role['nom']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label><?php echo $texts['avatar']; ?></label>
                <input type="hidden" id="avatar" name="avatar" value="<?php echo htmlspecialchars($user['avatar']); ?>">
                <div class="avatar-options">
                    <?php
                    $avatars = ['👤', '👨', '👩', '🧑', '👦', '👧', '👨‍🦰', '👩‍🦰', '👱‍♂️', '👱‍♀️', '👴', '👵', '🧔', '🧙‍♂️', '🧙‍♀️', '👮‍♂️', '👮‍♀️', '🦸‍♂️', '🦸‍♀️', '🐰', '🍆', '💩'];
                    foreach ($avatars as $avatar):
                    ?>
                    <div class="avatar-option <?php echo ($avatar === $user['avatar']) ? 'selected' : ''; ?>" data-avatar="<?php echo $avatar; ?>">
                        <?php echo $avatar; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="form-footer">
                <a href="profil.php" class="btn btn-secondary"><?php echo $texts['cancel']; ?></a>
                <button type="submit" class="btn btn-primary"><?php echo $texts['save_changes']; ?></button>
            </div>
        </form>
    </div>
    
    <script>
        // Script pour la sélection d'avatar
        document.querySelectorAll('.avatar-option').forEach(option => {
            option.addEventListener('click', function() {
                // Retirer la classe 'selected' de tous les avatars
                document.querySelectorAll('.avatar-option').forEach(opt => {
                    opt.classList.remove('selected');
                });
                
                // Ajouter la classe 'selected' à l'avatar cliqué
                this.classList.add('selected');
                
                // Mettre à jour la valeur du champ caché
                document.getElementById('avatar').value = this.getAttribute('data-avatar');
            });
        });
    </script>
</body>
</html>