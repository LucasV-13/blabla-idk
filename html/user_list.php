<?php
session_start();

// Vérification de l'authentification
if (!isset($_SESSION['user_id']) || (isset($_SESSION['expires']) && $_SESSION['expires'] < time())) {
    // Détruire la session si elle a expiré
    session_destroy();
    header("Location: index.php");
    exit();
}

// Prolonger la session d'une heure
$_SESSION['expires'] = time() + (60 * 60);

// Protection CSRF
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Vérifier si l'utilisateur est un administrateur
$estAdmin = (strtolower($_SESSION['role']) === 'admin');

// Si l'utilisateur n'est pas admin, rediriger vers le tableau de bord
if (!$estAdmin) {
    header("Location: dashboard.php");
    exit();
}

// Inclure la connexion à la base de données
include('connexion/connexion.php');

// Gestion des textes multilingues
$language = isset($_SESSION['language']) ? $_SESSION['language'] : 'fr';
include('languages/' . $language . '.php');

// Récupérer la liste des utilisateurs
try {
    $usersSql = "SELECT utilisateurs.id, utilisateurs.identifiant, utilisateurs.mail, 
                        utilisateurs.avatar, roles.nom as role_nom 
                FROM Utilisateurs 
                JOIN Roles ON utilisateurs.id_role = roles.id 
                ORDER BY utilisateurs.id";
    $usersStmt = $conn->prepare($usersSql);
    $usersStmt->execute();
    $utilisateurs = $usersStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erreur liste utilisateurs: " . $e->getMessage());
    $utilisateurs = [];
}

// Inclure le menu si nécessaire
if (file_exists('menu/menu.php')) {
    include('menu/menu.php');
}
?>

<!DOCTYPE html>
<html lang="<?php echo $language; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Liste des Utilisateurs - The Mind</title>
    <link rel="stylesheet" href="style/styleDash.css">
    <?php if (file_exists('style/styleMenu.css')): ?>
    <link rel="stylesheet" href="style/styleMenu.css">
    <?php endif; ?>
    <style>
        .users-container {
            width: 90%;
            max-width: 1000px;
            margin: 20px auto;
            background-color: #222;
            border-radius: 10px;
            padding: 20px;
            color: #fff;
        }
        
        .users-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            border-bottom: 1px solid #444;
            padding-bottom: 10px;
        }
        
        .users-header h2 {
            color: var(--secondary-color);
            margin: 0;
        }
        
        .btn {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: var(--dark-color);
            border: none;
            padding: 8px 16px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.3s ease;
        }
        
        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
        }
        
        .users-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .users-table th {
            background-color: #333;
            padding: 12px;
            text-align: left;
        }
        
        .users-table td {
            padding: 10px 12px;
            border-bottom: 1px solid #444;
        }
        
        .users-table tr:hover {
            background-color: #2a2a2a;
        }
        
        .user-avatar {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .avatar-icon {
            width: 40px;
            height: 40px;
            background-color: #4a4a4a;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }
        
        .action-btn {
            background: none;
            border: none;
            color: #fff;
            cursor: pointer;
            font-size: 18px;
            margin: 0 5px;
            transition: all 0.2s ease;
        }
        
        .edit-btn:hover {
            color: var(--accent-color);
        }
        
        .delete-btn:hover {
            color: #f44336;
        }
        
        .empty-message {
            text-align: center;
            padding: 20px;
            font-style: italic;
            color: #888;
        }
        
        .return-btn {
            margin-top: 20px;
            text-align: center;
        }
        
        @media (max-width: 768px) {
            .users-table th:nth-child(3), 
            .users-table td:nth-child(3) {
                display: none; /* Cacher la colonne email sur mobile */
            }
        }
    </style>
</head>
<body>
    <!-- Champ caché pour le jeton CSRF (pour JS) -->
    <input type="hidden" id="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
    
    <div class="users-container">
        <div class="users-header">
            <h2>Liste des Utilisateurs</h2>
            <button class="btn" onclick="window.location.href='dashboard.php'">Retour au Tableau de Bord</button>
        </div>
        
        <?php if (count($utilisateurs) > 0): ?>
        <table class="users-table">
            <thead>
                <tr>
                    <th>Utilisateur</th>
                    <th>Rôle</th>
                    <th>Email</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($utilisateurs as $user): ?>
                <tr>
                    <td class="user-avatar">
                        <div class="avatar-icon"><?php echo htmlspecialchars($user['avatar']); ?></div>
                        <span><?php echo htmlspecialchars($user['identifiant']); ?></span>
                    </td>
                    <td><?php echo htmlspecialchars($user['role_nom']); ?></td>
                    <td><?php echo htmlspecialchars($user['mail']); ?></td>
                    <td>
                        <button class="action-btn edit-btn" title="Modifier" onclick="editUser(<?php echo $user['id']; ?>)">✏️</button>
                        <?php if ($_SESSION['user_id'] != $user['id']): // Ne pas permettre de se supprimer soi-même ?>
                        <button class="action-btn delete-btn" title="Supprimer" onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['identifiant']); ?>')">🗑️</button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty-message">Aucun utilisateur trouvé.</div>
        <?php endif; ?>
        
        <div class="return-btn">
            <button class="btn" onclick="window.location.href='dashboard.php'">Retour au Tableau de Bord</button>
        </div>
    </div>
    
    <script>
    // Fonction pour modifier un utilisateur
    function editUser(userId) {
        // Rediriger vers la page d'édition avec l'ID de l'utilisateur
        window.location.href = 'profil/edit_user.php?id=' + userId + '&csrf_token=<?php echo $_SESSION['csrf_token']; ?>';
    }
    
    // Fonction pour supprimer un utilisateur
    function deleteUser(userId, username) {
        if (confirm('Êtes-vous sûr de vouloir supprimer l\'utilisateur ' + username + ' ?')) {
            // Créer un formulaire pour la soumission POST
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'profil/delete_user.php';
            form.style.display = 'none';
            
            // Ajouter les champs nécessaires
            const csrfField = document.createElement('input');
            csrfField.type = 'hidden';
            csrfField.name = 'csrf_token';
            csrfField.value = document.getElementById('csrf_token').value;
            
            const idField = document.createElement('input');
            idField.type = 'hidden';
            idField.name = 'id';
            idField.value = userId;
            
            // Ajouter les champs au formulaire
            form.appendChild(csrfField);
            form.appendChild(idField);
            
            // Ajouter le formulaire au document et le soumettre
            document.body.appendChild(form);
            form.submit();
        }
    }
    </script>
</body>
</html>