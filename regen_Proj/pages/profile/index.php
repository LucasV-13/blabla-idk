<?php
/**
 * Page profil utilisateur - The Mind
 * 
 * Affiche les informations personnelles, statistiques de jeu,
 * historique des parties et actions disponibles selon le rôle
 * 
 * @package TheMind
 * @version 1.0
 * @since Phase 3
 */

declare(strict_types=1);

// Headers de sécurité
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Configuration et dépendances
require_once '../../config/database.php';
require_once '../../config/session.php';
require_once '../../config/constants.php';

// Vérification authentification
if (!SessionManager::isLoggedIn()) {
    header('Location: ' . BASE_URL . 'index.php');
    exit;
}

// Prolonger la session
SessionManager::extendSession();

// Récupération des informations utilisateur
$userId = SessionManager::get('user_id');
$username = SessionManager::get('username', 'Utilisateur');
$email = SessionManager::get('email', '');
$avatar = SessionManager::get('avatar', '👤');
$role = SessionManager::get('role', 'joueur');
$isAdmin = strtolower($role) === 'admin';

// Configuration de la page
$pageTitle = 'Profil Utilisateur';
$cssFiles = ['profile'];
$jsFiles = ['profile'];

// Gestion des textes multilingues
$language = SessionManager::get('language', 'fr');
require_once "../../languages/{$language}.php";

// Connexion base de données
$db = Database::getInstance();
$conn = $db->getConnection();

// Pagination
$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1;
$limit = 10; // Parties par page
$offset = ($page - 1) * $limit;

try {
    // 1. Récupération des statistiques utilisateur
    $statsQuery = "
        SELECT 
            s.parties_jouees,
            s.parties_gagnees,
            s.taux_reussite,
            s.cartes_jouees,
            s.temps_total_jeu,
            s.niveau_max_atteint,
            s.shurikens_utilises,
            u.date_creation,
            u.derniere_connexion
        FROM Statistiques s
        LEFT JOIN Utilisateurs u ON s.id_utilisateur = u.id
        WHERE s.id_utilisateur = :user_id
    ";
    
    $statsStmt = $conn->prepare($statsQuery);
    $statsStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $statsStmt->execute();
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
    
    // Valeurs par défaut si pas de statistiques
    if (!$stats) {
        $stats = [
            'parties_jouees' => 0,
            'parties_gagnees' => 0,
            'taux_reussite' => 0,
            'cartes_jouees' => 0,
            'temps_total_jeu' => 0,
            'niveau_max_atteint' => 0,
            'shurikens_utilises' => 0,
            'date_creation' => date('Y-m-d H:i:s'),
            'derniere_connexion' => date('Y-m-d H:i:s')
        ];
    }
    
    // 2. Récupération de l'historique des parties
    $historicQuery = "
        SELECT 
            p.id,
            p.nom,
            p.niveau,
            p.status,
            p.nombre_joueurs,
            p.difficulte,
            p.date_creation,
            p.date_debut,
            p.date_fin,
            up.position,
            (SELECT COUNT(*) FROM Utilisateurs_parties WHERE id_partie = p.id) as joueurs_actuels,
            (SELECT identifiant FROM Utilisateurs WHERE id = 
                (SELECT id_utilisateur FROM Utilisateurs_parties WHERE id_partie = p.id AND position = 1)
            ) as admin_nom
        FROM Parties p
        JOIN Utilisateurs_parties up ON p.id = up.id_partie
        WHERE up.id_utilisateur = :user_id
        ORDER BY p.date_creation DESC
        LIMIT :limit OFFSET :offset
    ";
    
    $historicStmt = $conn->prepare($historicQuery);
    $historicStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $historicStmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $historicStmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $historicStmt->execute();
    $historique = $historicStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 3. Comptage total pour pagination
    $countQuery = "
        SELECT COUNT(*) as total
        FROM Parties p
        JOIN Utilisateurs_parties up ON p.id = up.id_partie
        WHERE up.id_utilisateur = :user_id
    ";
    
    $countStmt = $conn->prepare($countQuery);
    $countStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $countStmt->execute();
    $totalParties = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    $totalPages = ceil($totalParties / $limit);
    
    // 4. Récupération des achievements récents
    $achievementsQuery = "
        SELECT 
            type,
            details,
            date_obtention
        FROM Achievements 
        WHERE id_utilisateur = :user_id 
        ORDER BY date_obtention DESC 
        LIMIT 5
    ";
    
    $achievementsStmt = $conn->prepare($achievementsQuery);
    $achievementsStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $achievementsStmt->execute();
    $achievements = $achievementsStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Erreur profil utilisateur: " . $e->getMessage());
    $stats = $historique = $achievements = [];
    $totalPages = 1;
}

// Fonctions utilitaires
function formatDuration(int $seconds): string {
    if ($seconds < 60) return $seconds . 's';
    if ($seconds < 3600) return floor($seconds / 60) . 'min';
    return floor($seconds / 3600) . 'h ' . floor(($seconds % 3600) / 60) . 'min';
}

function getStatusBadge(string $status): string {
    $badges = [
        GAME_STATUS_WAITING => '<span class="badge badge-warning">En attente</span>',
        GAME_STATUS_IN_PROGRESS => '<span class="badge badge-primary">En cours</span>',
        GAME_STATUS_COMPLETED => '<span class="badge badge-success">Terminée</span>',
        GAME_STATUS_CANCELLED => '<span class="badge badge-danger">Annulée</span>',
        'gagnee' => '<span class="badge badge-success">Gagnée</span>',
        'pause' => '<span class="badge badge-secondary">En pause</span>'
    ];
    
    return $badges[$status] ?? '<span class="badge badge-secondary">Inconnu</span>';
}

function getDifficultyIcon(string $difficulty): string {
    $icons = [
        'facile' => '🟢',
        'moyen' => '🟡', 
        'difficile' => '🔴'
    ];
    
    return $icons[$difficulty] ?? '⚪';
}
?>

<!DOCTYPE html>
<html lang="<?= htmlspecialchars($language) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= htmlspecialchars($texts['profile_title']) ?> - The Mind">
    <title><?= htmlspecialchars($texts['profile_title']) ?> - The Mind</title>
    
    <!-- CSS Core -->
    <link rel="stylesheet" href="<?= ASSETS_URL ?>css/main.css">
    <link rel="stylesheet" href="<?= ASSETS_URL ?>css/components/buttons.css">
    <link rel="stylesheet" href="<?= ASSETS_URL ?>css/components/modals.css">
    <link rel="stylesheet" href="<?= ASSETS_URL ?>css/components/forms.css">
    
    <!-- CSS spécifiques -->
    <?php foreach ($cssFiles as $cssFile): ?>
    <link rel="stylesheet" href="<?= ASSETS_URL ?>css/<?= $cssFile ?>.css">
    <?php endforeach; ?>
    
    <!-- CSS Profile spécifique -->
    <style>
        .profile-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 2rem;
        }
        
        .profile-sidebar {
            background: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 2rem;
            height: fit-content;
            box-shadow: var(--shadow-md);
        }
        
        .profile-main {
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }
        
        .profile-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .profile-avatar {
            width: 120px;
            height: 120px;
            font-size: 4rem;
            background: var(--gradient-primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            box-shadow: var(--shadow-lg);
            animation: float 3s ease-in-out infinite;
        }
        
        .profile-info h1 {
            margin: 0 0 0.5rem 0;
            color: var(--text-primary);
            font-size: 2rem;
        }
        
        .profile-info .email {
            color: var(--text-secondary);
            margin-bottom: 1rem;
        }
        
        .role-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: var(--border-radius);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.875rem;
        }
        
        .role-admin {
            background: linear-gradient(135deg, #ff6b35, #f7931e);
            color: white;
        }
        
        .role-joueur {
            background: var(--gradient-secondary);
            color: var(--dark-color);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin: 2rem 0;
        }
        
        .stat-card {
            background: var(--card-bg);
            padding: 1.5rem;
            border-radius: var(--border-radius);
            text-align: center;
            box-shadow: var(--shadow-sm);
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }
        
        .stat-value {
            font-size: 2.5rem;
            font-weight: bold;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: var(--text-secondary);
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .action-buttons {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            margin-top: 2rem;
        }
        
        .card {
            background: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 2rem;
            box-shadow: var(--shadow-sm);
        }
        
        .card-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border-color);
        }
        
        .card-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0;
        }
        
        .history-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .history-table th,
        .history-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        
        .history-table th {
            background: var(--bg-secondary);
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .history-table tbody tr:hover {
            background: var(--bg-hover);
        }
        
        .badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .badge-success { background: #10b981; color: white; }
        .badge-warning { background: #f59e0b; color: white; }
        .badge-primary { background: var(--primary-color); color: white; }
        .badge-danger { background: #ef4444; color: white; }
        .badge-secondary { background: #6b7280; color: white; }
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 2rem;
        }
        
        .pagination a,
        .pagination span {
            padding: 0.5rem 1rem;
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            text-decoration: none;
            color: var(--text-primary);
            transition: all 0.3s ease;
        }
        
        .pagination a:hover {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }
        
        .pagination .current {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: var(--text-secondary);
        }
        
        .empty-state-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }
        
        .achievements-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .achievement-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            background: var(--bg-secondary);
            border-radius: var(--border-radius);
            border-left: 4px solid var(--accent-color);
        }
        
        .achievement-icon {
            font-size: 2rem;
        }
        
        .achievement-info h4 {
            margin: 0 0 0.25rem 0;
            color: var(--text-primary);
        }
        
        .achievement-info small {
            color: var(--text-secondary);
        }
        
        @media (max-width: 768px) {
            .profile-container {
                grid-template-columns: 1fr;
                padding: 1rem;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .history-table {
                font-size: 0.875rem;
            }
            
            .history-table th:nth-child(n+4),
            .history-table td:nth-child(n+4) {
                display: none;
            }
        }
    </style>
</head>

<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-container">
            <a href="<?= BASE_URL ?>pages/dashboard.php" class="nav-brand">
                <span class="brand-icon">🧠</span>
                The Mind
            </a>
            
            <div class="nav-actions">
                <a href="<?= BASE_URL ?>pages/dashboard.php" class="btn btn-secondary">
                    🏠 <?= htmlspecialchars($texts['dashboard']) ?>
                </a>
                
                <?php if ($isAdmin): ?>
                <a href="<?= BASE_URL ?>pages/profile/admin.php" class="btn btn-primary">
                    ⚙️ <?= htmlspecialchars($texts['admin_panel']) ?>
                </a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- Contenu principal -->
    <main class="profile-container">
        <!-- Sidebar profil -->
        <aside class="profile-sidebar">
            <div class="profile-header">
                <div class="profile-avatar">
                    <?= htmlspecialchars($avatar) ?>
                </div>
                
                <div class="profile-info">
                    <h1><?= htmlspecialchars($username) ?></h1>
                    <div class="email"><?= htmlspecialchars($email) ?></div>
                    
                    <div class="role-badge <?= $isAdmin ? 'role-admin' : 'role-joueur' ?>">
                        <?= $isAdmin ? '👑' : '🎮' ?>
                        <?= htmlspecialchars(ucfirst($role)) ?>
                    </div>
                </div>
            </div>
            
            <!-- Informations compte -->
            <div class="account-info">
                <h3><?= htmlspecialchars($texts['account_info']) ?></h3>
                <div class="info-item">
                    <strong><?= htmlspecialchars($texts['member_since']) ?>:</strong>
                    <span><?= date('d/m/Y', strtotime($stats['date_creation'])) ?></span>
                </div>
                <div class="info-item">
                    <strong><?= htmlspecialchars($texts['last_connection']) ?>:</strong>
                    <span><?= date('d/m/Y H:i', strtotime($stats['derniere_connexion'])) ?></span>
                </div>
            </div>
            
            <!-- Actions utilisateur -->
            <div class="action-buttons">
                <a href="<?= BASE_URL ?>pages/profile/edit.php" class="btn btn-primary btn-block">
                    ✏️ <?= htmlspecialchars($texts['edit_profile']) ?>
                </a>
                
                <?php if ($isAdmin): ?>
                <a href="<?= BASE_URL ?>pages/profile/admin.php" class="btn btn-secondary btn-block">
                    ⚙️ <?= htmlspecialchars($texts['admin_panel']) ?>
                </a>
                <?php endif; ?>
                
                <form method="post" action="<?= BASE_URL ?>api/user/logout.php" style="margin: 0;">
                    <input type="hidden" name="csrf_token" value="<?= SessionManager::getCSRFToken() ?>">
                    <button type="submit" class="btn btn-outline btn-block">
                        🚪 <?= htmlspecialchars($texts['logout']) ?>
                    </button>
                </form>
            </div>
            
            <!-- Achievements récents -->
            <?php if (!empty($achievements)): ?>
            <div class="recent-achievements">
                <h3><?= htmlspecialchars($texts['recent_achievements']) ?></h3>
                <div class="achievements-list">
                    <?php foreach ($achievements as $achievement): ?>
                    <div class="achievement-item">
                        <div class="achievement-icon">🏆</div>
                        <div class="achievement-info">
                            <h4><?= htmlspecialchars($achievement['type']) ?></h4>
                            <small><?= date('d/m/Y', strtotime($achievement['date_obtention'])) ?></small>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </aside>
        
        <!-- Contenu principal -->
        <section class="profile-main">
            <!-- Statistiques -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title"><?= htmlspecialchars($texts['statistics']) ?></h2>
                </div>
                
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-value"><?= number_format($stats['parties_jouees']) ?></div>
                        <div class="stat-label"><?= htmlspecialchars($texts['games_played']) ?></div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-value"><?= number_format($stats['parties_gagnees']) ?></div>
                        <div class="stat-label"><?= htmlspecialchars($texts['games_won']) ?></div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-value"><?= number_format($stats['taux_reussite'], 1) ?>%</div>
                        <div class="stat-label"><?= htmlspecialchars($texts['success_rate']) ?></div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-value"><?= number_format($stats['niveau_max_atteint']) ?></div>
                        <div class="stat-label"><?= htmlspecialchars($texts['max_level']) ?></div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-value"><?= number_format($stats['cartes_jouees']) ?></div>
                        <div class="stat-label"><?= htmlspecialchars($texts['cards_played']) ?></div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-value"><?= formatDuration((int)$stats['temps_total_jeu']) ?></div>
                        <div class="stat-label"><?= htmlspecialchars($texts['total_time']) ?></div>
                    </div>
                </div>
            </div>
            
            <!-- Historique des parties -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title"><?= htmlspecialchars($texts['game_history']) ?></h2>
                    <span class="badge badge-secondary"><?= number_format($totalParties) ?> <?= htmlspecialchars($texts['total_games']) ?></span>
                </div>
                
                <?php if (!empty($historique)): ?>
                <div class="table-container">
                    <table class="history-table">
                        <thead>
                            <tr>
                                <th><?= htmlspecialchars($texts['game_name']) ?></th>
                                <th><?= htmlspecialchars($texts['level']) ?></th>
                                <th><?= htmlspecialchars($texts['status']) ?></th>
                                <th><?= htmlspecialchars($texts['difficulty']) ?></th>
                                <th><?= htmlspecialchars($texts['players']) ?></th>
                                <th><?= htmlspecialchars($texts['date']) ?></th>
                                <th><?= htmlspecialchars($texts['role']) ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($historique as $partie): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($partie['nom'] ?: "Partie #{$partie['id']}") ?></strong>
                                </td>
                                <td>
                                    <span class="level-badge">
                                        🎯 <?= number_format($partie['niveau']) ?>
                                    </span>
                                </td>
                                <td><?= getStatusBadge($partie['status']) ?></td>
                                <td>
                                    <?= getDifficultyIcon($partie['difficulte']) ?>
                                    <?= htmlspecialchars(ucfirst($partie['difficulte'])) ?>
                                </td>
                                <td><?= number_format($partie['joueurs_actuels']) ?>/<?= number_format($partie['nombre_joueurs']) ?></td>
                                <td><?= date('d/m/Y H:i', strtotime($partie['date_creation'])) ?></td>
                                <td>
                                    <?php if ($partie['position'] == 1): ?>
                                        <span class="badge badge-warning">👑 Admin</span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary">🎮 Joueur</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?>">&laquo; <?= htmlspecialchars($texts['previous']) ?></a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <?php if ($i == $page): ?>
                            <span class="current"><?= $i ?></span>
                        <?php else: ?>
                            <a href="?page=<?= $i ?>"><?= $i ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?= $page + 1 ?>"><?= htmlspecialchars($texts['next']) ?> &raquo;</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">🎮</div>
                    <h3><?= htmlspecialchars($texts['no_games_yet']) ?></h3>
                    <p><?= htmlspecialchars($texts['start_playing_message']) ?></p>
                    <a href="<?= BASE_URL ?>pages/dashboard.php" class="btn btn-primary">
                        🚀 <?= htmlspecialchars($texts['start_playing']) ?>
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <!-- Scripts -->
    <script src="<?= ASSETS_URL ?>js/main.js"></script>
    
    <!-- Scripts spécifiques -->
    <?php foreach ($jsFiles as $jsFile): ?>
    <script src="<?= ASSETS_URL ?>js/<?= $jsFile ?>.js"></script>
    <?php endforeach; ?>
    
    <!-- Configuration JavaScript -->
    <script>
        // Configuration globale pour cette page
        window.TheMind.config.userId = <?= json_encode($userId) ?>;
        window.TheMind.config.isAdmin = <?= json_encode($isAdmin) ?>;
        window.TheMind.config.pageType = 'profile';
        
        // Initialisation de la page profil
        document.addEventListener('DOMContentLoaded', function() {
            // Animation d'entrée pour les cartes statistiques
            const statCards = document.querySelectorAll('.stat-card');
            statCards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    card.style.transition = 'all 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
            
            // Gestion des tooltips pour les statistiques
            const statValues = document.querySelectorAll('.stat-value');
            statValues.forEach(stat => {
                stat.addEventListener('mouseenter', function() {
                    this.style.transform = 'scale(1.1)';
                });
                
                stat.addEventListener('mouseleave', function() {
                    this.style.transform = 'scale(1)';
                });
            });
            
            // Confirmation pour la déconnexion
            const logoutForm = document.querySelector('form[action*="logout"]');
            if (logoutForm) {
                logoutForm.addEventListener('submit', function(e) {
                    if (!confirm('<?= htmlspecialchars($texts['confirm_logout']) ?>')) {
                        e.preventDefault();
                    }
                });
            }
            
            console.log('Page profil utilisateur initialisée');
        });
    </script>
    
    <!-- Champs cachés pour JavaScript -->
    <input type="hidden" id="csrf_token" value="<?= SessionManager::getCSRFToken() ?>">
    <input type="hidden" id="user_id" value="<?= htmlspecialchars($userId) ?>">
    <input type="hidden" id="language" value="<?= htmlspecialchars($language) ?>">
</body>
</html>