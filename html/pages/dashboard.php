<?php
/**
 * Dashboard - Tableau de bord refactorisé
 */

// Initialisation
require_once __DIR__ . '/../includes/init.php';

// Initialiser pour une page protégée
Init::protectedPage();

try {
    // Récupérer les parties disponibles avec les informations utilisateur
    $gameDb = gameDb();
    $parties = $gameDb->getAvailableGames($_SESSION['user_id']);
    
    Logger::info('Dashboard accédé', [
        'user_id' => $_SESSION['user_id'],
        'parties_count' => count($parties)
    ]);
    
} catch (Exception $e) {
    Logger::error('Erreur lors du chargement du dashboard', ['error' => $e->getMessage()]);
    $parties = [];
    setFlashMessage('Erreur lors du chargement des parties', 'error');
}
?><!DOCTYPE html>
<html lang="<?php echo Language::getCurrentLanguage(); ?>">
<head>
    <?php generateMeta(t('dashboard'), 'Tableau de bord du jeu The Mind'); ?>
    
    <!-- Styles -->
    <?php includeCommonCSS(); ?>
    <link rel="stylesheet" href="<?php echo getAssetUrl('css/dashboard.css'); ?>">
</head>
<body>
    <?php generateJSGlobals(); ?>
    
    <!-- Menu -->
    <?php include __DIR__ . '/menu/menu.php'; ?>
    
    <div class="main-container">
        <h1 class="fade-in"><?php echo t('dashboard'); ?></h1>

        <!-- Messages flash -->
        <?php showFlashMessage(); ?>

        <!-- Conteneur du tableau -->
        <div class="table-container card fade-in">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th><?php echo t('game_name_column'); ?></th>
                            <th><?php echo t('players_column'); ?></th>
                            <th><?php echo t('admin_column'); ?></th>
                            <th><?php echo t('level_column'); ?></th>
                            <th><?php echo t('status_column'); ?></th>
                            <th><?php echo t('action_column'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="parties-tbody">
                        <?php if (count($parties) > 0): ?>
                            <?php foreach($parties as $partie): ?>
                                <tr data-game-id="<?php echo $partie['id']; ?>" class="game-row">
                                    <td class="game-name">
                                        <strong><?php echo t('game_name_column') . ' ' . htmlspecialchars($partie['id']); ?></strong>
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
                                            <?php echo t('level') . ' ' . $partie['niveau']; ?>
                                        </span>
                                    </td>
                                    <td class="game-status">
                                        <?php 
                                        $statusClass = 'badge ';
                                        $statusText = t('status_waiting');
                                        
                                        switch($partie['status']) {
                                            case 'en_attente': 
                                                $statusClass .= 'badge-warning';
                                                $statusText = t('status_waiting'); 
                                                break;
                                            case 'en_cours': 
                                                $statusClass .= 'badge-success';
                                                $statusText = t('status_in_progress'); 
                                                break;
                                            case 'terminee': 
                                                $statusClass .= 'badge-secondary';
                                                $statusText = t('status_completed'); 
                                                break;
                                            case 'pause': 
                                                $statusClass .= 'badge-info';
                                                $statusText = t('status_paused'); 
                                                break;
                                            case 'annulee': 
                                                $statusClass .= 'badge-error';
                                                $statusText = t('status_cancelled'); 
                                                break;
                                        }
                                        ?>
                                        <span class="<?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                                    </td>
                                    <td class="game-action">
                                        <?php if ($partie['status'] === 'en_attente' && $partie['joueurs_actuels'] < $partie['nombre_joueurs']): ?>
                                            <button class="btn btn-primary btn-sm join-game-btn" 
                                                    data-game-id="<?php echo $partie['id']; ?>">
                                                <?php echo t('join'); ?>
                                            </button>
                                        <?php elseif ($partie['status'] === 'en_cours'): ?>
                                            <?php if (isset($partie['user_joined']) && $partie['user_joined']): ?>
                                                <a href="<?php echo getPageUrl('plateau-jeu.php?partie_id=' . $partie['id']); ?>" 
                                                   class="btn btn-success btn-sm">
                                                    <?php echo t('play_card'); ?>
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted"><?php echo t('status_in_progress'); ?></span>
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
                                    <em><?php echo t('no_games_available'); ?></em>
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
                <?php echo t('refresh'); ?>
            </button>
            
            <button class="btn btn-primary" id="join-game-btn">
                <span class="btn-icon">🎮</span>
                <?php echo t('join_game'); ?>
            </button>
            
            <button class="btn btn-info" id="filter-games-btn">
                <span class="btn-icon">🔍</span>
                <?php echo t('filter_games'); ?>
            </button>
            
            <?php if (Security::checkPermission('admin', false)): ?>
                <a href="<?php echo getPageUrl('profil/profil.php'); ?>" class="btn btn-warning">
                    <span class="btn-icon">⚙️</span>
                    <?php echo t('administration'); ?>
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
            <h3><?php echo t('join_game'); ?></h3>
            
            <form id="joinGameForm">
                <div class="form-group">
                    <label for="gameSelect"><?php echo t('select_game'); ?></label>
                    <select id="gameSelect" name="game_id" required class="form-control">
                        <option value="">-- Sélectionnez une partie --</option>
                        <?php foreach($parties as $partie): ?>
                            <?php if ($partie['status'] === 'en_attente' && $partie['joueurs_actuels'] < $partie['nombre_joueurs']): ?>
                                <option value="<?php echo $partie['id']; ?>">
                                    <?php echo t('game_name_column') . ' ' . $partie['id'] . ' - ' . t('level') . ' ' . $partie['niveau']; ?>
                                    (<?php echo $partie['joueurs_actuels'] . '/' . $partie['nombre_joueurs']; ?>)
                                </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="modal-buttons">
                    <button type="button" class="btn btn-secondary" id="cancelJoinBtn">
                        <?php echo t('cancel'); ?>
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <?php echo t('join'); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal de filtrage -->
    <div id="filterModal" class="modal">
        <div class="modal-content">
            <span class="close" id="closeFilterModal">&times;</span>
            <h3><?php echo t('filter_games'); ?></h3>
            
            <form id="filterForm">
                <div class="form-group">
                    <label for="filterAdmin"><?php echo t('admin_column'); ?></label>
                    <input type="text" id="filterAdmin" placeholder="Nom de l'administrateur">
                </div>
                
                <div class="form-group">
                    <label for="filterStatus"><?php echo t('status_column'); ?></label>
                    <select id="filterStatus">
                        <option value="">Tous les statuts</option>
                        <option value="en_attente"><?php echo t('status_waiting'); ?></option>
                        <option value="en_cours"><?php echo t('status_in_progress'); ?></option>
                        <option value="terminee"><?php echo t('status_completed'); ?></option>
                        <option value="pause"><?php echo t('status_paused'); ?></option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="filterLevel"><?php echo t('level_column'); ?></label>
                    <select id="filterLevel">
                        <option value="">Tous les niveaux</option>
                        <?php for ($i = 1; $i <= 12; $i++): ?>
                            <option value="<?php echo $i; ?>"><?php echo t('level') . ' ' . $i; ?></option>
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
    <?php includeCommonJS(); ?>
    <script src="<?php echo getAssetUrl('js/dashboard.js'); ?>"></script>
</body>
</html>