/**
 * Dashboard JavaScript - Gestion du tableau de bord
 * Fonctionnalités spécifiques à la page dashboard
 */

// Configuration du dashboard
const DashboardConfig = {
    refreshInterval: window.DASHBOARD_REFRESH_INTERVAL || 30000,
    apiEndpoint: '../api/game/get_parties.php',
    joinGameEndpoint: '../join_game.php',
    gamePageUrl: 'game.php',
    sounds: {
        notification: true,
        volume: 0.5
    }
};

// Variables globales du dashboard
let refreshTimer = null;
let isRefreshing = false;
let selectedGameRow = null;

/**
 * Initialisation du dashboard
 */
function initDashboard() {
    console.log('🎮 Initialisation du Dashboard The Mind');
    
    // Configuration des éléments DOM
    setupEventListeners();
    setupKeyboardShortcuts();
    setupRowSelection();
    
    // Démarrer l'actualisation automatique
    startAutoRefresh();
    
    // Vérifier la connexion réseau
    checkNetworkStatus();
    
    console.log('✅ Dashboard initialisé avec succès');
}

/**
 * Configuration des écouteurs d'événements
 */
function setupEventListeners() {
    // Boutons principaux
    const refreshBtn = document.querySelector('[onclick="refreshPage()"]');
    const joinBtn = document.querySelector('[onclick="joinGame()"]');
    const filterBtn = document.querySelector('[onclick="filterGames()"]');
    
    if (refreshBtn) refreshBtn.onclick = refreshPage;
    if (joinBtn) joinBtn.onclick = joinGame;
    if (filterBtn) filterBtn.onclick = filterGames;
    
    // Fermeture des modales en cliquant à l'extérieur
    window.addEventListener('click', handleModalClick);
    
    // Gestion de la visibilité de la page
    document.addEventListener('visibilitychange', handleVisibilityChange);
    
    // Gestion du réseau
    window.addEventListener('online', handleOnline);
    window.addEventListener('offline', handleOffline);
    
    // Effets sur les boutons
    setupButtonEffects();
}

/**
 * Configuration des raccourcis clavier
 */
function setupKeyboardShortcuts() {
    document.addEventListener('keydown', function(e) {
        switch(e.key) {
            case 'Escape':
                closeAllModals();
                break;
            case 'r':
            case 'R':
                if (e.ctrlKey || e.metaKey) {
                    e.preventDefault();
                    refreshPage();
                }
                break;
            case 'j':
            case 'J':
                if (e.ctrlKey || e.metaKey) {
                    e.preventDefault();
                    joinGame();
                }
                break;
            case 'f':
            case 'F':
                if (e.ctrlKey || e.metaKey) {
                    e.preventDefault();
                    filterGames();
                }
                break;
            case 'Enter':
                if (selectedGameRow) {
                    const joinBtn = selectedGameRow.querySelector('.join-btn');
                    const playLink = selectedGameRow.querySelector('.play-btn');
                    
                    if (joinBtn) {
                        joinBtn.click();
                    } else if (playLink) {
                        playLink.click();
                    }
                }
                break;
            case 'ArrowUp':
            case 'ArrowDown':
                e.preventDefault();
                navigateTableWithKeyboard(e.key);
                break;
        }
    });
}

/**
 * Navigation au clavier dans le tableau
 */
function navigateTableWithKeyboard(direction) {
    const rows = document.querySelectorAll('.dashboard-table tbody tr');
    if (rows.length === 0) return;
    
    let currentIndex = -1;
    if (selectedGameRow) {
        currentIndex = Array.from(rows).indexOf(selectedGameRow);
    }
    
    if (direction === 'ArrowDown') {
        currentIndex = Math.min(currentIndex + 1, rows.length - 1);
    } else if (direction === 'ArrowUp') {
        currentIndex = Math.max(currentIndex - 1, 0);
    }
    
    if (currentIndex >= 0 && rows[currentIndex]) {
        selectRow(rows[currentIndex]);
    }
}

/**
 * Configuration de la sélection des lignes
 */
function setupRowSelection() {
    const rows = document.querySelectorAll('.dashboard-table tbody tr');
    rows.forEach(row => {
        row.addEventListener('click', () => selectRow(row));
        row.setAttribute('tabindex', '0');
        
        // Accessibilité
        row.addEventListener('focus', () => selectRow(row));
    });
}

/**
 * Sélectionner une ligne du tableau
 */
function selectRow(row) {
    // Retirer la sélection précédente
    document.querySelectorAll('.dashboard-table tbody tr').forEach(r => {
        r.classList.remove('selected');
        r.setAttribute('aria-selected', 'false');
    });
    
    // Sélectionner la nouvelle ligne
    row.classList.add('selected');
    row.setAttribute('aria-selected', 'true');
    selectedGameRow = row;
    
    // Faire défiler pour rendre visible si nécessaire
    row.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

/**
 * Configuration des effets sur les boutons
 */
function setupButtonEffects() {
    const buttons = document.querySelectorAll('.dashboard-btn');
    buttons.forEach(btn => {
        btn.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-3px) scale(1.02)';
        });
        
        btn.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0) scale(1)';
        });
        
        btn.addEventListener('mousedown', function() {
            this.style.transform = 'translateY(1px) scale(0.98)';
        });
        
        btn.addEventListener('mouseup', function() {
            this.style.transform = 'translateY(-3px) scale(1.02)';
        });
    });
}

/**
 * Actualiser les données des parties
 */
async function loadParties() {
    if (isRefreshing) return;
    
    isRefreshing = true;
    
    try {
        const response = await fetch(DashboardConfig.apiEndpoint, {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        const parties = await response.json();
        
        if (Array.isArray(parties)) {
            updatePartiesTable(parties);
            updateJoinGameModal(parties);
            setupRowSelection();
        } else {
            console.warn('Format de données inattendu:', parties);
            // Fallback: recharger la page
            setTimeout(() => window.location.reload(), 1000);
        }
        
    } catch (error) {
        console.error('Erreur lors du chargement des parties:', error);
        handleLoadError(error);
    } finally {
        isRefreshing = false;
    }
}

/**
 * Mettre à jour le tableau des parties (version simple)
 */
function updatePartiesTable(parties) {
    // Pour l'instant, simple rechargement de page
    // TODO: Mise à jour dynamique du tableau sans rechargement
    console.log(`📊 ${parties.length} parties récupérées`);
    
    // Planifier un rechargement doux
    setTimeout(() => {
        if (!document.hidden) {
            window.location.reload();
        }
    }, 100);
}

/**
 * Mettre à jour le modal de sélection de partie
 */
function updateJoinGameModal(parties) {
    const select = document.getElementById('gameSelect');
    if (!select) return;
    
    // Sauvegarder la sélection actuelle
    const currentValue = select.value;
    
    // Vider et repeupler
    select.innerHTML = '';
    
    let hasAvailableGames = false;
    
    parties.forEach(partie => {
        if (partie.status === 'en_attente' && 
            parseInt(partie.joueurs_actuels) < parseInt(partie.nombre_joueurs)) {
            
            const option = document.createElement('option');
            option.value = partie.id;
            option.textContent = `Partie ${partie.id} (Niveau ${partie.niveau})`;
            
            if (partie.id == currentValue) {
                option.selected = true;
            }
            
            select.appendChild(option);
            hasAvailableGames = true;
        }
    });
    
    // Désactiver le bouton si aucune partie disponible
    const submitBtn = select.closest('form').querySelector('button[type="submit"]');
    if (submitBtn) {
        submitBtn.disabled = !hasAvailableGames;
        if (!hasAvailableGames) {
            submitBtn.textContent = 'Aucune partie disponible';
        } else {
            submitBtn.textContent = 'Rejoindre';
        }
    }
}

/**
 * Gestion des erreurs de chargement
 */
function handleLoadError(error) {
    console.error('🚨 Erreur de chargement:', error);
    
    // Afficher une notification d'erreur
    showNotification('Erreur de connexion. Nouvelle tentative dans 10 secondes...', 'error');
    
    // Réessayer après un délai
    setTimeout(() => {
        if (!document.hidden) {
            loadParties();
        }
    }, 10000);
}

/**
 * Rafraîchir la page
 */
function refreshPage() {
    console.log('🔄 Actualisation manuelle des parties');
    
    // Effet visuel sur le bouton
    const refreshBtn = document.querySelector('[onclick="refreshPage()"]');
    if (refreshBtn) {
        refreshBtn.style.transform = 'rotate(360deg)';
        refreshBtn.disabled = true;
        
        setTimeout(() => {
            refreshBtn.style.transform = '';
            refreshBtn.disabled = false;
        }, 1000);
    }
    
    loadParties();
    showNotification('Données actualisées', 'success');
}

/**
 * Rejoindre une partie
 */
function joinGame() {
    const modal = document.getElementById('joinGameModal');
    if (modal) {
        modal.style.display = 'block';
        
        // Focus sur le select
        const select = document.getElementById('gameSelect');
        if (select) {
            setTimeout(() => select.focus(), 100);
        }
    }
}

/**
 * Rejoindre une partie spécifique
 */
function joinSpecificGame(gameId) {
    if (!gameId) {
        showNotification('ID de partie invalide', 'error');
        return;
    }
    
    console.log(`🎮 Rejoindre la partie ${gameId}`);
    
    const csrfToken = document.querySelector('input[name="csrf_token"]')?.value;
    if (!csrfToken) {
        showNotification('Erreur de sécurité (CSRF)', 'error');
        return;
    }
    
    // Construire l'URL avec les paramètres
    const url = new URL(DashboardConfig.joinGameEndpoint, window.location.origin);
    url.searchParams.set('game_id', gameId);
    url.searchParams.set('csrf_token', csrfToken);
    
    // Redirection
    window.location.href = url.toString();
}

/**
 * Filtrer les parties
 */
function filterGames() {
    const adminName = prompt('Entrez le nom de l\'administrateur pour filtrer :');
    if (!adminName) return;
    
    console.log(`🔍 Filtrage par admin: ${adminName}`);
    
    const rows = document.querySelectorAll('.dashboard-table tbody tr');
    let visibleCount = 0;
    
    rows.forEach(row => {
        const adminCell = row.children[2]?.textContent.toLowerCase() || '';
        const shouldShow = adminCell.includes(adminName.toLowerCase());
        
        row.style.display = shouldShow ? '' : 'none';
        if (shouldShow) visibleCount++;
    });
    
    showNotification(`${visibleCount} partie(s) trouvée(s)`, 'success');
}

/**
 * Fermer une modal spécifique
 */
function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
    }
}

/**
 * Fermer toutes les modales
 */
function closeAllModals() {
    document.querySelectorAll('.modal').forEach(modal => {
        modal.style.display = 'none';
    });
}

/**
 * Gestion des clics sur les modales
 */
function handleModalClick(event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
    }
}

/**
 * Afficher une notification
 */
function showNotification(message, type = 'success', duration = 3000) {
    // Supprimer les notifications existantes
    document.querySelectorAll('.notification').forEach(n => n.remove());
    
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.textContent = message;
    
    // Ajouter une icône selon le type
    const icon = type === 'success' ? '✅' : type === 'error' ? '❌' : 'ℹ️';
    notification.innerHTML = `<span style="margin-right: 8px;">${icon}</span>${message}`;
    
    document.body.appendChild(notification);
    
    // Animation d'entrée
    requestAnimationFrame(() => {
        notification.style.transform = 'translateX(0)';
        notification.style.opacity = '1';
    });
    
    // Suppression automatique
    setTimeout(() => {
        notification.classList.add('fade-out');
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 500);
    }, duration);
    
    // Permettre fermeture manuelle
    notification.addEventListener('click', () => {
        notification.classList.add('fade-out');
        setTimeout(() => notification.remove(), 300);
    });
}

/**
 * Démarrer l'actualisation automatique
 */
function startAutoRefresh() {
    stopAutoRefresh(); // S'assurer qu'aucun timer n'est déjà actif
    
    console.log(`⏰ Actualisation automatique démarrée (${DashboardConfig.refreshInterval/1000}s)`);
    
    // Première actualisation immédiate
    setupRowSelection();
    
    // Actualisation périodique
    refreshTimer = setInterval(() => {
        if (!document.hidden && !isRefreshing) {
            loadParties();
        }
    }, DashboardConfig.refreshInterval);
}

/**
 * Arrêter l'actualisation automatique
 */
function stopAutoRefresh() {
    if (refreshTimer) {
        clearInterval(refreshTimer);
        refreshTimer = null;
        console.log('⏸️ Actualisation automatique arrêtée');
    }
}

/**
 * Gestion de la visibilité de la page
 */
function handleVisibilityChange() {
    if (document.hidden) {
        console.log('📱 Page masquée - Pause actualisation');
        stopAutoRefresh();
    } else {
        console.log('📱 Page visible - Reprise actualisation');
        startAutoRefresh();
    }
}

/**
 * Gestion du retour en ligne
 */
function handleOnline() {
    console.log('🌐 Connexion rétablie');
    showNotification('Connexion rétablie', 'success');
    startAutoRefresh();
}

/**
 * Gestion de la perte de connexion
 */
function handleOffline() {
    console.log('📶 Connexion perdue');
    showNotification('Connexion perdue - Mode hors ligne', 'error', 5000);
    stopAutoRefresh();
}

/**
 * Vérifier le statut de la connexion réseau
 */
function checkNetworkStatus() {
    if (navigator.onLine === false) {
        handleOffline();
    }
}

/**
 * Fonctions utilitaires
 */
const DashboardUtils = {
    /**
     * Formater le temps relatif
     */
    formatRelativeTime(date) {
        const now = new Date();
        const diff = now - new Date(date);
        const minutes = Math.floor(diff / 60000);
        
        if (minutes < 1) return 'À l\'instant';
        if (minutes < 60) return `Il y a ${minutes} min`;
        
        const hours = Math.floor(minutes / 60);
        if (hours < 24) return `Il y a ${hours}h`;
        
        return new Date(date).toLocaleDateString();
    },
    
    /**
     * Débouncer une fonction
     */
    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    },
    
    /**
     * Vérifier si on est en mode debug
     */
    isDebugMode() {
        return window.location.search.includes('debug=1') || 
               localStorage.getItem('dashboard_debug') === 'true';
    }
};

/**
 * Mode debug
 */
if (DashboardUtils.isDebugMode()) {
    console.log('🐛 Mode Debug Dashboard activé');
    window.DashboardDebug = {
        config: DashboardConfig,
        loadParties,
        refreshTimer,
        selectedGameRow,
        utils: DashboardUtils
    };
}

/**
 * Initialisation automatique au chargement du DOM
 */
document.addEventListener('DOMContentLoaded', initDashboard);

/**
 * Nettoyage avant fermeture de page
 */
window.addEventListener('beforeunload', () => {
    stopAutoRefresh();
    console.log('👋 Dashboard nettoyé');
});

/**
 * Export pour compatibilité (si nécessaire)
 */
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        initDashboard,
        refreshPage,
        joinGame,
        joinSpecificGame,
        filterGames,
        closeModal,
        showNotification
    };
}