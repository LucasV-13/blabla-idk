<?php
/**
 * Dashboard - Tableau de bord principal
 * Version finale propre avec CSS et JS externes
 */

// Chargement de la configuration
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/constants.php';

// Démarrage de la session sécurisée
SessionManager::start();

// Vérification de l'authentification
if (!SessionManager::isLoggedIn()) {
    header('Location: ../index.php');
    exit();
}

// Prolonger la session
SessionManager::extendSession();

// Gestion de la langue
$language = SessionManager::get('language', DEFAULT_LANGUAGE);
if (file_exists(__DIR__ . '/../languages/' . $language . '.php')) {
    require_once __DIR__ . '/../languages/' . $language . '.php';
} else {
    // Textes par défaut si le fichier de langue n'existe pas
    $texts = [
        'dashboard' => 'Tableau de bord',
        'refresh' => 'Rafraîchir',
        'join_game' => 'Rejoindre une Partie',
        'filter_games' => 'Filtrer les Parties',
        'administration' => 'Administration',
        'game_name_column' => 'Partie',
        'players_column' => 'Joueurs',
        'admin_column' => 'Administrateur',
        'level_column' => 'Niveau',
        'status_column' => 'Statut',
        'action_column' => 'Action',
        'no_games_available' => 'Aucune partie disponible pour le moment.',
        'status_waiting' => 'En attente',
        'status_in_progress' => 'En cours',
        'status_completed' => 'Terminée',
        'status_full' => 'Complète',
        'status_paused' => 'En pause',
        'status_cancelled' => 'Annulée',
        'join' => 'Rejoindre',
        'play_card' => 'Jouer',
        'select_game' => 'Sélectionnez une partie :',
        'filter_by_admin' => 'Entrez le nom de l\'administrateur pour filtrer :',
        'data_updated' => 'Données actualisées',
        'error' => 'Erreur'
    ];
}

// Protection CSRF
$csrfToken = SessionManager::getCSRFToken();

// Récupération des informations utilisateur
$user_id = SessionManager::get('user_id');
$username = SessionManager::get('username', 'User');
$role = SessionManager::get('role', ROLE_PLAYER);

// Connexion à la base de données
$db = Database::getInstance();
$conn = $db->getConnection();

// Récupération des parties disponibles
try {
    $sql = "SELECT Parties.id, Parties.niveau, Parties.status, Parties.nombre_joueurs,
       (SELECT COUNT(*) FROM Utilisateurs_parties WHERE id_partie = Parties.id) as joueurs_actuels,
       (SELECT identifiant FROM Utilisateurs WHERE id = 
            (SELECT id_utilisateur FROM Utilisateurs_parties WHERE id_partie = Parties.id AND position = 1 LIMIT 1)
       ) as admin_nom
        FROM Parties
        WHERE status IN ('" . GAME_STATUS_WAITING . "', '" . GAME_STATUS_PLAYING . "', '" . GAME_STATUS_PAUSED . "')
        ORDER BY Parties.id DESC";

    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $parties = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Erreur dashboard: " . $e->getMessage());
    $parties = [];
}

// Inclure le menu si disponible
$menuPath = __DIR__ . '/../includes/menu.php';
if (file_exists($menuPath)) {
    include($menuPath);
}
?>

<!DOCTYPE html>
<html lang="<?php echo $language; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $texts['dashboard']; ?> - The Mind</title>
    
    <!-- Meta SEO -->
    <meta name="description" content="Tableau de bord The Mind - Rejoignez ou créez des parties de jeu coopératif">
    <meta name="keywords" content="the mind, jeu coopératif, cartes, tableau de bord">
    
    <!-- CSS External Files -->
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>css/main.css">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>css/dashboard.css">
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700&display=swap" rel="stylesheet">
    
    <!-- Favicons -->
    <link rel="icon" type="image/x-icon" href="<?php echo ASSETS_URL; ?>images/favicon.ico">
    <link rel="apple-touch-icon" href="<?php echo ASSETS_URL; ?>images/apple-touch-icon.png">
</head>
<body class="dashboard-page">
    <!-- Configuration JavaScript -->
    <script>
        // Configuration globale passée au JavaScript
        window.DASHBOARD_REFRESH_INTERVAL = <?php echo DASHBOARD_REFRESH_INTERVAL; ?>;
        window.CSRF_TOKEN = '<?php echo $csrfToken; ?>';
        window.USER_ID = <?php echo (int)$user_id; ?>;
        window.USER_ROLE = '<?php echo htmlspecialchars($role); ?>';
        window.LANGUAGE = '<?php echo htmlspecialchars($language); ?>';
        
        // Textes pour JavaScript
        window.TEXTS = <?php echo json_encode($texts, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
        
        // Constantes de jeu
        window.GAME_STATUS = {
            WAITING: '<?php echo GAME_STATUS_WAITING; ?>',
            PLAYING: '<?php echo GAME_STATUS_PLAYING; ?>',
            PAUSED: '<?php echo GAME_STATUS_PAUSED; ?>',
            FINISHED: '<?php echo GAME_STATUS_FINISHED; ?>',
            CANCELLED: '<?php echo GAME_STATUS_CANCELLED; ?>'
        };
    </script>

    <!---- Tableau de Bord --->
    <h1 class="dashboard-title"><?php echo $texts['dashboard']; ?></h1>

    <!-- Champ caché pour le jeton CSRF -->
    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">

    <div class="table-container">
        <table class="dashboard-table">
          <!--- Colonnes du Tableau --->
          <thead>
            <tr>
              <th><?php echo $texts['game_name_column']; ?></th>
              <th><?php echo $texts['players_column']; ?></th>
              <th><?php echo $texts['admin_column']; ?></th>
              <th><?php echo $texts['level_column']; ?></th>
              <th><?php echo $texts['status_column']; ?></th>
              <th><?php echo $texts['action_column']; ?></th>
            </tr>
          </thead>
          
          <!--- Lignes du Tableau --->
          <tbody>
            <?php
            if (count($parties) > 0) {
                foreach($parties as $row) {
                    echo "<tr>";
                    echo "<td>" . $texts['game_name_column'] . " " . htmlspecialchars($row["id"]) . "</td>";
                    echo "<td>" . htmlspecialchars($row["joueurs_actuels"]) . "/" . htmlspecialchars($row["nombre_joueurs"]) . "</td>";
                    echo "<td>" . ($row["admin_nom"] ? htmlspecialchars($row["admin_nom"]) : "Non assigné") . "</td>";
                    echo "<td>" . htmlspecialchars($row["niveau"]) . "</td>";
                    
                    // Traduire le statut avec classes CSS
                    $statut = $texts['status_waiting']; // Valeur par défaut
                    $statusClass = 'status-waiting';
                    
                    switch($row["status"]) {
                        case GAME_STATUS_WAITING: 
                            $statut = $texts['status_waiting']; 
                            $statusClass = 'status-waiting';
                            break;
                        case GAME_STATUS_PLAYING: 
                            $statut = $texts['status_in_progress']; 
                            $statusClass = 'status-playing';
                            break;
                        case GAME_STATUS_FINISHED: 
                            $statut = $texts['status_completed']; 
                            $statusClass = 'status-finished';
                            break;
                        case GAME_STATUS_PAUSED: 
                            $statut = $texts['status_paused']; 
                            $statusClass = 'status-paused';
                            break;
                        case GAME_STATUS_CANCELLED: 
                            $statut = $texts['status_cancelled']; 
                            $statusClass = 'status-cancelled';
                            break;
                    }
                    echo "<td><span class=\"" . $statusClass . "\">" . $statut . "</span></td>";
                    
                    // Bouton d'action
                    echo "<td>";
                    if ($row["status"] == GAME_STATUS_WAITING && $row["joueurs_actuels"] < $row["nombre_joueurs"]) {
                        echo "<button onclick=\"joinSpecificGame(" . htmlspecialchars($row["id"]) . ")\" class=\"join-btn\">" . $texts['join'] . "</button>";
                    } else if ($row["status"] == GAME_STATUS_PLAYING) {
                        // Vérifier si l'utilisateur est dans cette partie avec PDO
                        try {
                            $check_user = $conn->prepare("SELECT * FROM Utilisateurs_parties WHERE id_utilisateur = :user_id AND id_partie = :partie_id");
                            $check_user->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                            $check_user->bindParam(':partie_id', $row["id"], PDO::PARAM_INT);
                            $check_user->execute();
                            
                            if ($check_user->rowCount() > 0) {
                                echo "<a href=\"game.php?partie_id=" . htmlspecialchars($row["id"]) . "\" class=\"play-btn\">" . $texts['play_card'] . "</a>";
                            } else {
                                echo "<span>" . $texts['status_in_progress'] . "</span>";
                            }
                        } catch (PDOException $e) {
                            error_log("Erreur vérification utilisateur: " . $e->getMessage());
                            echo "<span>" . $texts['error'] . "</span>";
                        }
                    } else {
                        echo "<span>-</span>";
                    }
                    echo "</td>";
                    
                    echo "</tr>";
                }
            } else {
                echo "<tr><td colspan='6' class=\"empty-state\">";
                echo "<div class=\"empty-icon\">🎮</div>";
                echo "<div class=\"empty-message\">" . $texts['no_games_available'] . "</div>";
                echo "</td></tr>";
            }
            ?>
          </tbody>
        </table>
      </div>

      <!---- Boutons Inférieurs de Rafraîchir, Rejoindre et Filtrer --->
      <div class="button-container">
        <button class="dashboard-btn" onclick="refreshPage()" title="Ctrl+R">
            <?php echo $texts['refresh']; ?>
        </button>
        <button class="dashboard-btn" onclick="joinGame()" title="Ctrl+J">
            <?php echo $texts['join_game']; ?>
        </button>
        <button class="dashboard-btn" onclick="filterGames()" title="Ctrl+F">
            <?php echo $texts['filter_games']; ?>
        </button>
        <?php if (strtolower($role) == ROLE_ADMIN): ?>
        <button class="dashboard-btn admin-btn" onclick="window.location.href='../test_plateau.php'">
            <?php echo $texts['administration']; ?>
        </button>
        <?php endif; ?>
      </div>

      <!-- Modal pour rejoindre une partie -->
      <div id="joinGameModal" class="modal" style="display: none;" role="dialog" aria-labelledby="joinGameTitle">
        <div class="modal-content">
            <span class="close" onclick="closeModal('joinGameModal')" aria-label="<?php echo $texts['close'] ?? 'Fermer'; ?>">&times;</span>
            <h2 id="joinGameTitle"><?php echo $texts['join_game']; ?></h2>
            <form id="joinGameForm" method="get" action="../join_game.php">
                <div class="form-group">
                    <label for="gameSelect"><?php echo $texts['select_game']; ?></label>
                    <select id="gameSelect" name="game_id" required>
                        <?php
                        $hasAvailableGames = false;
                        // Parcourir les parties disponibles
                        foreach($parties as $row) {
                            if ($row["status"] == GAME_STATUS_WAITING && $row["joueurs_actuels"] < $row["nombre_joueurs"]) {
                                echo "<option value='" . htmlspecialchars($row["id"]) . "'>" . $texts['game_name_column'] . " " . 
                                     htmlspecialchars($row["id"]) . " (" . $texts['level_column'] . " " . 
                                     htmlspecialchars($row["niveau"]) . ")</option>";
                                $hasAvailableGames = true;
                            }
                        }
                        
                        if (!$hasAvailableGames) {
                            echo "<option value='' disabled>" . $texts['no_games_available'] . "</option>";
                        }
                        ?>
                    </select>
                </div>
                <!-- Ajouter un jeton CSRF pour sécuriser le formulaire -->
                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                <button type="submit" <?php echo !$hasAvailableGames ? 'disabled' : ''; ?>>
                    <?php echo $hasAvailableGames ? $texts['join'] : $texts['no_games_available']; ?>
                </button>
            </form>
        </div>
      </div>

    <!-- Scripts externes -->
    <script src="<?php echo ASSETS_URL; ?>js/main.js"></script>
    <script src="<?php echo ASSETS_URL; ?>js/dashboard.js"></script>
    
    <!-- Analytics et métriques (optionnel) -->
    <?php if (defined('ENABLE_ANALYTICS') && ENABLE_ANALYTICS): ?>
    <script>
        // Code Analytics ici si nécessaire
        console.log('📊 Analytics activées');
    </script>
    <?php endif; ?>
    
    <!-- Schema.org pour SEO -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "WebApplication",
        "name": "The Mind Dashboard",
        "description": "Tableau de bord pour le jeu coopératif The Mind",
        "url": "<?php echo (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']; ?>",
        "applicationCategory": "Game",
        "operatingSystem": "Web Browser",
        "offers": {
            "@type": "Offer",
            "price": "0",
            "priceCurrency": "EUR"
        }
    }
    </script>
    
    <!-- Support PWA (optionnel) -->
    <link rel="manifest" href="<?php echo ASSETS_URL; ?>manifest.json">
    <meta name="theme-color" content="#1a1a2e">
    
    <!-- Préchargement des ressources critiques -->
    <link rel="prefetch" href="../api/game/get_parties.php">
    <link rel="prefetch" href="../join_game.php">
    
</body>
</html>