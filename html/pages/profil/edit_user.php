<?php
session_start();

// Vérification de l'authentification et des permissions
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role']) !== 'admin') {
    header("Location: ../index.php");
    exit();
}

// Protection CSRF
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

include('../connexion/connexion.php');

// Récupérer l'ID de l'utilisateur à modifier
$userId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Vérifier si l'ID est valide
if ($userId <= 0) {
    header("Location: profil.php");
    exit();
}

// Récupérer les données de l'utilisateur
try {
    $userQuery = "SELECT u.id, u.identifiant, u.mail, u.avatar, r.id as role_id, r.nom as role_nom 
                 FROM Utilisateurs u 
                 JOIN Roles r ON u.id_role = r.id 
                 WHERE u.id = :user_id";
    $userStmt = $conn->prepare($userQuery);
    $userStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $userStmt->execute();
    
    if (!($user = $userStmt->fetch(PDO::FETCH_ASSOC))) {
        $_SESSION['error_message'] = "Utilisateur non trouvé.";
        header("Location: ../profil.php");
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
    $newUsername = isset($_POST['username']) ? $_POST['username'] : '';
    $newEmail = isset($_POST['email']) ? $_POST['email'] : '';
    $newPassword = isset($_POST['password']) ? $_POST['password'] : '';
    $newRoleId = isset($_POST['role_id']) ? (int)$_POST['role_id'] : 0;
    $newAvatar = isset($_POST['avatar']) ? $_POST['avatar'] : '';
    
    // Valider les données
    if (empty($newUsername) || empty($newEmail)) {
        $_SESSION['error_message'] = "Les champs nom d'utilisateur et email sont obligatoires.";
        header("Location: edit_user.php?id=$userId&csrf_token=" . $_SESSION['csrf_token']);
        exit();
    }
    
    try {
        // Commencer une transaction
        $conn->beginTransaction();
        
        // Préparer la requête de mise à jour
        $updateQuery = "UPDATE Utilisateurs SET identifiant = :username, mail = :email";
        $params = [
            ':username' => $newUsername,
            ':email' => $newEmail
        ];
        
        // Ajouter le mot de passe s'il est fourni
        if (!empty($newPassword)) {
            $updateQuery .= ", mdp = :password";
            $params[':password'] = $newPassword;
        }
        
        // Ajouter le rôle s'il est valide
        if ($newRoleId > 0) {
            $updateQuery .= ", id_role = :role_id";
            $params[':role_id'] = $newRoleId;
        }
        
        // Ajouter l'avatar s'il est fourni
        if (!empty($newAvatar)) {
            $updateQuery .= ", avatar = :avatar";
            $params[':avatar'] = $newAvatar;
        }
        
        // Finaliser la requête
        $updateQuery .= " WHERE id = :user_id";
        $params[':user_id'] = $userId;
        
        // Exécuter la mise à jour
        $updateStmt = $conn->prepare($updateQuery);
        foreach ($params as $key => $value) {
            $updateStmt->bindValue($key, $value);
        }
        $updateStmt->execute();
        
        // Valider la transaction
        $conn->commit();
        
        $_SESSION['success_message'] = "Utilisateur modifié avec succès.";
        header("Location: profil.php");
        exit();
        
    } catch (PDOException $e) {
        // Annuler la transaction en cas d'erreur
        $conn->rollBack();
        error_log("Erreur modification utilisateur: " . $e->getMessage());
        $_SESSION['error_message'] = "Une erreur est survenue lors de la modification de l'utilisateur.";
        header("Location: edit_user.php?id=$userId&csrf_token=" . $_SESSION['csrf_token']);
        exit();
    }
}

// Récupérer les messages d'erreur
$errorMessage = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : '';
unset($_SESSION['error_message']);

// Inclure le menu
include('../menu/menu.php');
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier l'utilisateur - The Mind</title>
    <link rel="stylesheet" href="../style/styleProfil.css">
    <link rel="stylesheet" href="../style/styleMenu.css">
    <script src="../java/jsProfil.js"></script>
    <style>
        .edit-user-container {
            max-width: 600px;
            margin: 20px auto;
            background-color: #222;
            border-radius: 10px;
            padding: 20px;
        }
        .form-header {
            margin-bottom: 20px;
            text-align: center;
        }
        .error-message {
            background-color: #f44336;
            color: white;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .form-footer {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
        }
        .cancel-btn {
            background-color: #f44336;
        }
        .avatar-options {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 10px;
        }
        .avatar-option {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: #333;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 20px;
        }
        .avatar-option.selected {
            border: 2px solid #4CAF50;
        }
    </style>
</head>
<body>
    <input type="hidden" id="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
    <input type="hidden" id="base_url" value="../">

    <div class="edit-user-container">
        <div class="form-header">
            <h2>Modifier l'utilisateur</h2>
        </div>
        
        <?php if (!empty($errorMessage)): ?>
        <div class="error-message">
            <?php echo htmlspecialchars($errorMessage); ?>
        </div>
        <?php endif; ?>
        
        <form method="post" action="edit_user.php?id=<?php echo $userId; ?>">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            
            <div class="form-group">
                <label for="username">Nom d'utilisateur</label>
                <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($user['identifiant']); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['mail']); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="password">Nouveau mot de passe (laisser vide pour ne pas modifier)</label>
                <input type="password" id="password" name="password">
            </div>
            
            <div class="form-group">
                <label for="role_id">Rôle</label>
                <select id="role_id" name="role_id">
                    <?php foreach ($roles as $role): ?>
                    <option value="<?php echo $role['id']; ?>" <?php echo ($role['id'] == $user['role_id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($role['nom']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Avatar</label>
                <input type="hidden" id="avatar" name="avatar" value="<?php echo htmlspecialchars($user['avatar']); ?>">
                <div class="avatar-options">
                    <?php
                    $avatars = ['👤', '👨', '👩', '🧑', '👦', '👧', '👨‍🦰', '👩‍🦰', '👱‍♂️', '👱‍♀️', '👴', '👵', '🧔', '🧙‍♂️', '🧙‍♀️', '👮‍♂️', '👮‍♀️', '🦸‍♂️', '🦸‍♀️'];
                    foreach ($avatars as $avatar):
                    ?>
                    <div class="avatar-option <?php echo ($avatar === $user['avatar']) ? 'selected' : ''; ?>" data-avatar="<?php echo $avatar; ?>">
                        <?php echo $avatar; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="form-footer">
                <button type="button" class="cancel-btn" onclick="window.location.href='profil.php'">Annuler</button>
                <button type="submit">Enregistrer les modifications</button>
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