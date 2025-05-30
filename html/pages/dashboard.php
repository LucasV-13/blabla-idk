<?php
/**
 * Dashboard - Tableau de bord refactorisé
 */

// Initialisation
require_once __DIR__ . '/../includes/init.php';

// CORRECTION : Utiliser la méthode corrigée
Init::protectedPage();

try {
    // Vérifier que les classes existent avant de les utiliser
    if (function_exists('gameDb')) {
        $gameDb = gameDb();
        $parties = $gameDb->getAvailableGames($_SESSION['user_id']);
    } else {
        // Fallback si gameDb n'est pas disponible
        include(__DIR__ . '/../connexion/connexion.php');
        
        $sql = "SELECT Parties.id, Parties.niveau, Parties.status, Parties.nombre_joueurs, Parties.nom,
                       (SELECT COUNT(*) FROM Utilisateurs_Parties WHERE id_partie = Parties.id) as joueurs_actuels,
                       (SELECT identifiant FROM Utilisateurs WHERE id = 
                            (SELECT id_utilisateur FROM Utilisateurs_Parties WHERE id_partie = Parties.id AND position = 1 LIMIT 1)
                       ) as admin_nom,
                       (SELECT COUNT(*) FROM Utilisateurs_Parties WHERE id_partie = Parties.id AND id_utilisateur = :user_id) as user_joined
                FROM Parties
                ORDER BY Parties.date_creation DESC";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
        $stmt->execute();
        $parties = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    if (class_exists('Logger')) {
        Logger::info('Dashboard accédé', [
            'user_id' => $_SESSION['user_id'],
            'parties_count' => count($parties)
        ]);
    }
    
} catch (Exception $e) {
    if (class_exists('Logger')) {
        Logger::error('Erreur lors du chargement du dashboard', ['error' => $e->getMessage()]);
    }
    $parties = [];
    setFlashMessage('Erreur lors du chargement des parties', 'error');
}

// Inclure le menu
include __DIR__ . '/menu/menu.php';
?>
<!DOCTYPE html>
<html lang="<?php echo $language; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $texts['dashboard']; ?> - The Mind</title>
    
    <!-- Styles -->
    <link rel="stylesheet" href="<?php echo getAssetUrl('css/common.css'); ?>">
    <link rel="stylesheet" href="<?php echo getAssetUrl('css/styleDash.css'); ?>">
    <link rel="stylesheet" href="<?php echo getAssetUrl('css/styleMenu.css'); ?>">
    
    <style>
        /* Styles additionnels pour éviter les conflits */
        .main-container {
            padding-top: 80px;
        }
        
        .flash-message {
            position: fixed;
            top: 80px;
            right: 20px;
            padding: 15px 20px;
            border-radius: 8px;
            color: white;
            font-weight: bold;
            z-index: 2000;
            max-width: 400px;
            animation: slideDown 0.3s ease;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        }
        
        .flash-success {
            background: linear-gradient(135deg, #4CAF50, #2E7D32);
        }
        
        .flash-error {
            background: linear-gradient(135deg, #f44336, #d32f2f);
        }
        
        .flash-info {
            background: linear-gradient(135deg, #00c2cb, #0097A7);
        }
    </style>
</head>
<body>
    <!-- Variables JavaScript globales -->
    <script>
        window.THEMIND = <?php echo json_encode([
            'SITE_URL' => SITE_URL,
            'ASSETS_URL' => ASSETS_PATH,
            'PAGES_URL' => PAGES_PATH,
            'CSRF_TOKEN' => $csrfToken,
            'CURRENT_USER' => $currentUser,
            'LANGUAGE' => $language,
            'TEXTS' => $texts
        ], JSON_UNESCAPED_UNICODE); ?>;
    </script>
    
    <div class="main-container">
        <h1 class="fade-in"><?php echo $texts['dashboard']; ?></h1>

        <!-- Messages flash -->
        <?php showFlashMessage(); ?>

        <!-- Conteneur du tableau -->
        <div class="table-container card fade-in">
            <div class="table-responsive">
                <table class="table">
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
                    <tbody id="parties-tbody">
                        <?php if (count($parties) > 0): ?>
                            <?php foreach($parties as $partie): ?>
                                <tr data-game-id="<?php echo $partie['id']; ?>" class="game-row">
                                    <td class="game-name">
                                        <strong><?php echo $texts['game_name_column'] . ' ' . htmlspecialchars($partie['id']); ?></strong>
                                        <?php if (!empty($partie['nom'])): ?>
                                            <br><small><?php echo htmlspecialchars($partie['nom']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="players-count">
                                        <span class="badge badge-secondary">
                                            <?php echo $partie['joueurs_actuels'] . '/' . $partie['nombre_joueurs']; ?>
                                        </span>
                                    </td>
                                    <td class="admin-name">
                                        <?php echo $partie['admin_nom'] ? htmlspecialchars($partie['admin_nom']) : 'Non assigné'; ?>
                                    </td>
                                    <td class="game-level">
                                        <span class="badge badge-primary">
                                            <?php echo $texts['level'] . ' ' . $partie['niveau']; ?>
                                        </span>
                                    </td>
                                    <td class="game-status">
                                        <?php 
                                        $statusClass = 'badge ';
                                        $statusText = $texts['status_waiting'];
                                        
                                        switch($partie['status']) {
                                            case 'en_attente': 
                                                $statusClass .= 'badge-warning';
                                                $statusText = $texts['status_waiting']; 
                                                break;
                                            case 'en_cours': 
                                                $statusClass .= 'badge-success';
                                                $statusText = $texts['status_in_progress']; 
                                                break;
                                            case 'terminee': 
                                                $statusClass .= 'badge-secondary';
                                                $statusText = $texts['status_completed']; 
                                                break;
                                            case 'pause': 
                                                $statusClass .= 'badge-info';
                                                $statusText = $texts['status_paused']; 
                                                break;
                                            case 'annulee': 
                                                $statusClass .= 'badge-error';
                                                $statusText = $texts['status_cancelled']; 
                                                break;
                                        }
                                        ?>
                                        <span class="<?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                                    </td>
                                    <td class="game-action">
                                        <?php if ($partie['status'] === 'en_attente' && $partie['joueurs_actuels'] < $partie['nombre_joueurs']): ?>
                                            <button class="btn btn-primary btn-sm join-game-btn" 
                                                    data-game-id="<?php echo $partie['id']; ?>">
                                                <?php echo $texts['join']; ?>
                                            </button>
                                        <?php elseif ($partie['status'] === 'en_cours'): ?>
                                            <?php if (isset($partie['user_joined']) && $partie['user_joined']): ?>
                                                <a href="<?php echo getPageUrl('plateau-jeu.php?partie_id=' . $partie['id']); ?>" 
                                                   class="btn btn-success btn-sm">
                                                    <?php echo $texts['play_card']; ?>
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted"><?php echo $texts['status_in_progress']; ?></span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted">
                                    <em><?php echo $texts['no_games_available']; ?></em>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Boutons d'action -->
        <div class="action-buttons fade-in">
            <button class="btn btn-secondary" id="refresh-btn">
                <span class="btn-icon">🔄</span>
                <?php echo $texts['refresh']; ?>
            </button>
            
            <button class="btn btn-primary" id="join-game-btn">
                <span class="btn-icon">🎮</span>
                <?php echo $texts['join_game']; ?>
            </button>
            
            <button class="btn btn-info" id="filter-games-btn">
                <span class="btn-icon">🔍</span>
                <?php echo $texts['filter_games']; ?>
            </button>
            
            <?php if (Security::checkPermission('admin', false)): ?>
                <a href="<?php echo getPageUrl('profil/profil.php'); ?>" class="btn btn-warning">
                    <span class="btn-icon">⚙️</span>
                    <?php echo $texts['administration']; ?>
                </a>
                
                <a href="<?php echo getPageUrl('test_plateau.php'); ?>" class="btn btn-success">
                    <span class="btn-icon">🧪</span>
                    Test Plateau
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal pour rejoindre une partie -->
    <div id="joinGameModal" class="modal">
        <div class="modal-content">
            <span class="close" id="closeJoinModal">&times;</span>
            <h3><?php echo $texts['join_game']; ?></h3>
            
            <form id="joinGameForm">
                <div class="form-group">
                    <label for="gameSelect"><?php echo $texts['select_game']; ?></label>
                    <select id="gameSelect" name="game_id" required class="form-control">
                        <option value="">-- Sélectionnez une partie --</option>
                        <?php foreach($parties as $partie): ?>
                            <?php if ($partie['status'] === 'en_attente' && $partie['joueurs_actuels'] < $partie['nombre_joueurs']): ?>
                                <option value="<?php echo $partie['id']; ?>">
                                    <?php echo $texts['game_name_column'] . ' ' . $partie['id'] . ' - ' . $texts['level'] . ' ' . $partie['niveau']; ?>
                                    (<?php echo $partie['joueurs_actuels'] . '/' . $partie['nombre_joueurs']; ?>)
                                </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="modal-buttons">
                    <button type="button" class="btn btn-secondary" id="cancelJoinBtn">
                        <?php echo $texts['cancel']; ?>
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <?php echo $texts['join']; ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal de filtrage -->
    <div id="filterModal" class="modal">
        <div class="modal-content">
            <span class="close" id="closeFilterModal">&times;</span>
            <h3><?php echo $texts['filter_games']; ?></h3>
            
            <form id="filterForm">
                <div class="form-group">
                    <label for="filterAdmin"><?php echo $texts['admin_column']; ?></label>
                    <input type="text" id="filterAdmin" placeholder="Nom de l'administrateur">
                </div>
                
                <div class="form-group">
                    <label for="filterStatus"><?php echo $texts['status_column']; ?></label>
                    <select id="filterStatus">
                        <option value="">Tous les statuts</option>
                        <option value="en_attente"><?php echo $texts['status_waiting']; ?></option>
                        <option value="en_cours"><?php echo $texts['status_in_progress']; ?></option>
                        <option value="terminee"><?php echo $texts['status_completed']; ?></option>
                        <option value="pause"><?php echo $texts['status_paused']; ?></option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="filterLevel"><?php echo $texts['level_column']; ?></label>
                    <select id="filterLevel">
                        <option value="">Tous les niveaux</option>
                        <?php for ($i = 1; $i <= 12; $i++): ?>
                            <option value="<?php echo $i; ?>"><?php echo $texts['level'] . ' ' . $i; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                
                <div class="modal-buttons">
                    <button type="button" class="btn btn-secondary" id="resetFilterBtn">
                        Réinitialiser
                    </button>
                    <button type="submit" class="btn btn-primary">
                        Appliquer
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- JavaScript -->
    <script src="<?php echo getAssetUrl('js/utils.js'); ?>"></script>
    <script src="<?php echo getAssetUrl('js/jsMenu.js'); ?>"></script>
    <script src="<?php echo getAssetUrl('js/jsDash.js'); ?>"></script>
</body>
</html>