/**
 * JavaScript pour le dashboard The Mind
 */

class DashboardManager {
    constructor() {
        this.refreshInterval = 30000; // 30 secondes
        this.refreshTimer = null;
        this.isLoading = false;
        this.filters = {
            admin: '',
            status: '',
            level: ''
        };
        
        this.init();
    }

    init() {
        utils.ready(() => {
            this.setupElements();
            this.setupEventListeners();
            this.setupAutoRefresh();
            this.setupKeyboardShortcuts();
        });
    }

    setupElements() {
        this.elements = {
            // Boutons
            refreshBtn: utils.$('#refresh-btn'),
            joinGameBtn: utils.$('#join-game-btn'),
            filterGamesBtn: utils.$('#filter-games-btn'),
            
            // Tableau
            partiesTBody: utils.$('#parties-tbody'),
            gameRows: utils.$$('.game-row'),
            
            // Modals
            joinGameModal: utils.$('#joinGameModal'),
            filterModal: utils.$('#filterModal'),
            closeJoinModal: utils.$('#closeJoinModal'),
            closeFilterModal: utils.$('#closeFilterModal'),
            
            // Formulaires
            joinGameForm: utils.$('#joinGameForm'),
            filterForm: utils.$('#filterForm'),
            gameSelect: utils.$('#gameSelect'),
            
            // Filtres
            filterAdmin: utils.$('#filterAdmin'),
            filterStatus: utils.$('#filterStatus'),
            filterLevel: utils.$('#filterLevel'),
            resetFilterBtn: utils.$('#resetFilterBtn'),
            cancelJoinBtn: utils.$('#cancelJoinBtn')
        };
    }

    setupEventListeners() {
        // Bouton rafraîchir
        if (this.elements.refreshBtn) {
            this.elements.refreshBtn.addEventListener('click', () => {
                this.refreshGames();
            });
        }

        // Bouton rejoindre une partie
        if (this.elements.joinGameBtn) {
            this.elements.joinGameBtn.addEventListener('click', () => {
                this.openJoinModal();
            });
        }

        // Bouton filtrer
        if (this.elements.filterGamesBtn) {
            this.elements.filterGamesBtn.addEventListener('click', () => {
                this.openFilterModal();
            });
        }

        // Boutons de jointure directs
        utils.delegate('.join-game-btn', 'click', (e) => {
            const gameId = e.target.dataset.gameId;
            if (gameId) {
                this.joinSpecificGame(gameId);
            }
        });

        // Sélection de ligne
        this.setupRowSelection();

        // Modals
        this.setupModalEvents();

        // Formulaires
        this.setupFormEvents();
    }

    setupRowSelection() {
        utils.delegate('.game-row', 'click', (e) => {
            // Ne pas sélectionner si on clique sur un bouton
            if (e.target.closest('button') || e.target.closest('a')) {
                return;
            }

            // Désélectionner toutes les lignes
            utils.$$('.game-row').forEach(row => {
                row.classList.remove('selected');
            });

            // Sélectionner la ligne cliquée
            e.target.closest('.game-row').classList.add('selected');
        });
    }

    setupModalEvents() {
        // Fermeture des modals
        if (this.elements.closeJoinModal) {
            this.elements.closeJoinModal.addEventListener('click', () => {
                this.closeModal('join');
            });
        }

        if (this.elements.closeFilterModal) {
            this.elements.closeFilterModal.addEventListener('click', () => {
                this.closeModal('filter');
            });
        }

        if (this.elements.cancelJoinBtn) {
            this.elements.cancelJoinBtn.addEventListener('click', () => {
                this.closeModal('join');
            });
        }

        // Fermeture en cliquant à l'extérieur
        window.addEventListener('click', (e) => {
            if (e.target === this.elements.joinGameModal) {
                this.closeModal('join');
            }
            if (e.target === this.elements.filterModal) {
                this.closeModal('filter');
            }
        });

        // Échap pour fermer
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                this.closeModal('join');
                this.closeModal('filter');
            }
        });
    }

    setupFormEvents() {
        // Formulaire de jointure
        if (this.elements.joinGameForm) {
            this.elements.joinGameForm.addEventListener('submit', (e) => {
                e.preventDefault();
                const gameId = this.elements.gameSelect.value;
                if (gameId) {
                    this.joinSpecificGame(gameId);
                    this.closeModal('join');
                }
            });
        }

        // Formulaire de filtrage
        if (this.elements.filterForm) {
            this.elements.filterForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.applyFilters();
                this.closeModal('filter');
            });
        }

        // Réinitialiser les filtres
        if (this.elements.resetFilterBtn) {
            this.elements.resetFilterBtn.addEventListener('click', () => {
                this.resetFilters();
            });
        }
    }

    setupAutoRefresh() {
        // Démarrer le rafraîchissement automatique
        this.startAutoRefresh();

        // Gérer la visibilité de la page
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                this.stopAutoRefresh();
            } else {
                this.startAutoRefresh();
            }
        });
    }

    setupKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            // Raccourcis clavier
            if (e.ctrlKey || e.metaKey) {
                switch (e.key) {
                    case 'r': // Ctrl+R pour rafraîchir
                        e.preventDefault();
                        this.refreshGames();
                        break;
                    case 'j': // Ctrl+J pour rejoindre
                        e.preventDefault();
                        this.openJoinModal();
                        break;
                    case 'f': // Ctrl+F pour filtrer
                        e.preventDefault();
                        this.openFilterModal();
                        break;
                }
            }

            // F5 pour rafraîchir
            if (e.key === 'F5') {
                e.preventDefault();
                this.refreshGames();
            }
        });
    }

    // === GESTION DES PARTIES ===

    async refreshGames() {
        if (this.isLoading) return;

        this.setLoadingState(true);
        
        try {
            const parties = await utils.get('get_parties.php');
            
            if (parties && Array.isArray(parties)) {
                this.updatePartiesTable(parties);
                this.updateJoinGameModal(parties);
                this.showNotification('Données actualisées', 'success', 2000);
                
                utils.info('Parties rafraîchies', { count: parties.length });
            } else {
                throw new Error('Format de réponse invalide');
            }
            
        } catch (error) {
            utils.error('Erreur lors du rafraîchissement', error);
            this.showNotification('Erreur lors du rafraîchissement', 'error');
        } finally {
            this.setLoadingState(false);
        }
    }

    updatePartiesTable(parties) {
        if (!this.elements.partiesTBody) return;

        // Sauvegarder la sélection actuelle
        const selectedGameId = utils.$('.game-row.selected')?.dataset.gameId;

        // Vider le tableau
        this.elements.partiesTBody.innerHTML = '';

        if (parties.length > 0) {
            parties.forEach(partie => {
                const row = this.createGameRow(partie);
                this.elements.partiesTBody.appendChild(row);

                // Restaurer la sélection
                if (selectedGameId && partie.id.toString() === selectedGameId) {
                    row.classList.add('selected');
                }
            });
        } else {
            const emptyRow = utils.createElement('tr', {
                innerHTML: `<td colspan="6" class="text-center text-muted">
                    <em>${window.THEMIND?.TEXTS?.no_games_available || 'Aucune partie disponible'}</em>
                </td>`
            });
            this.elements.partiesTBody.appendChild(emptyRow);
        }

        // Rétablir les écouteurs d'événements
        this.setupRowSelection();
        
        // Appliquer les filtres si actifs
        if (this.hasActiveFilters()) {
            this.applyFiltersToTable();
        }
    }

    createGameRow(partie) {
        const texts = window.THEMIND?.TEXTS || {};
        
        // Déterminer le statut et sa classe
        const statusInfo = this.getStatusInfo(partie.status);
        
        // Déterminer l'action disponible
        const actionHtml = this.getActionHtml(partie);

        const row = utils.createElement('tr', {
            className: 'game-row',
            'data-game-id': partie.id,
            innerHTML: `
                <td class="game-name">
                    <strong>${texts.game_name_column || 'Partie'} ${partie.id}</strong>
                    ${partie.nom ? `<br><small>${utils.escapeHtml(partie.nom)}</small>` : ''}
                </td>
                <td class="players-count">
                    <span class="badge badge-secondary">
                        ${partie.joueurs_actuels}/${partie.nombre_joueurs}
                    </span>
                </td>
                <td class="admin-name">
                    ${partie.admin_nom ? utils.escapeHtml(partie.admin_nom) : 'Non assigné'}
                </td>
                <td class="game-level">
                    <span class="badge badge-primary">
                        ${texts.level || 'Niveau'} ${partie.niveau}
                    </span>
                </td>
                <td class="game-status">
                    <span class="badge ${statusInfo.class}">${statusInfo.text}</span>
                </td>
                <td class="game-action">
                    ${actionHtml}
                </td>
            `
        });

        return row;
    }

    getStatusInfo(status) {
        const texts = window.THEMIND?.TEXTS || {};
        
        const statusMap = {
            'en_attente': { class: 'badge-warning', text: texts.status_waiting || 'En attente' },
            'en_cours': { class: 'badge-success', text: texts.status_in_progress || 'En cours' },
            'terminee': { class: 'badge-secondary', text: texts.status_completed || 'Terminée' },
            'pause': { class: 'badge-info', text: texts.status_paused || 'En pause' },
            'annulee': { class: 'badge-error', text: texts.status_cancelled || 'Annulée' }
        };

        return statusMap[status] || { class: 'badge-secondary', text: status };
    }

    getActionHtml(partie) {
        const texts = window.THEMIND?.TEXTS || {};
        
        if (partie.status === 'en_attente' && partie.joueurs_actuels < partie.nombre_joueurs) {
            return `<button class="btn btn-primary btn-sm join-game-btn" data-game-id="${partie.id}">
                ${texts.join || 'Rejoindre'}
            </button>`;
        } else if (partie.status === 'en_cours' && partie.user_joined) {
            const baseUrl = window.THEMIND?.PAGES_URL || '/html/pages/';
            return `<a href="${baseUrl}plateau-jeu.php?partie_id=${partie.id}" class="btn btn-success btn-sm">
                ${texts.play_card || 'Jouer'}
            </a>`;
        } else if (partie.status === 'en_cours') {
            return `<span class="text-muted">${texts.status_in_progress || 'En cours'}</span>`;
        } else {
            return '<span class="text-muted">-</span>';
        }
    }

    updateJoinGameModal(parties) {
        if (!this.elements.gameSelect) return;

        // Sauvegarder la sélection actuelle
        const currentValue = this.elements.gameSelect.value;

        // Vider et repeupler le select
        this.elements.gameSelect.innerHTML = '<option value="">-- Sélectionnez une partie --</option>';

        const availableGames = parties.filter(partie => 
            partie.status === 'en_attente' && 
            partie.joueurs_actuels < partie.nombre_joueurs
        );

        availableGames.forEach(partie => {
            const option = utils.createElement('option', {
                value: partie.id,
                textContent: `Partie ${partie.id} - Niveau ${partie.niveau} (${partie.joueurs_actuels}/${partie.nombre_joueurs})`
            });
            this.elements.gameSelect.appendChild(option);
        });

        // Restaurer la sélection si possible
        if (currentValue && utils.$(`option[value="${currentValue}"]`, this.elements.gameSelect)) {
            this.elements.gameSelect.value = currentValue;
        }
    }

    async joinSpecificGame(gameId) {
        if (!gameId) return;

        this.setLoadingState(true);
        
        try {
            const csrfToken = utils.getCSRFToken();
            const baseUrl = utils.getBaseUrl();
            
            // Rediriger vers join_game.php
            window.location.href = `${baseUrl}pages/join_game.php?game_id=${gameId}&csrf_token=${encodeURIComponent(csrfToken)}`;
            
        } catch (error) {
            utils.error('Erreur lors de la jointure', error);
            this.showNotification('Erreur lors de la jointure à la partie', 'error');
            this.setLoadingState(false);
        }
    }

    // === GESTION DES MODALS ===

    openJoinModal() {
        if (this.elements.joinGameModal) {
            this.elements.joinGameModal.style.display = 'block';
            document.body.style.overflow = 'hidden';
            
            // Focus sur le select
            if (this.elements.gameSelect) {
                this.elements.gameSelect.focus();
            }
        }
    }

    openFilterModal() {
        if (this.elements.filterModal) {
            this.elements.filterModal.style.display = 'block';
            document.body.style.overflow = 'hidden';
            
            // Focus sur le premier champ
            if (this.elements.filterAdmin) {
                this.elements.filterAdmin.focus();
            }
        }
    }

    closeModal(type) {
        const modal = type === 'join' ? this.elements.joinGameModal : this.elements.filterModal;
        
        if (modal && modal.style.display === 'block') {
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }
    }

    // === GESTION DES FILTRES ===

    applyFilters() {
        // Récupérer les valeurs des filtres
        this.filters.admin = this.elements.filterAdmin?.value.toLowerCase().trim() || '';
        this.filters.status = this.elements.filterStatus?.value || '';
        this.filters.level = this.elements.filterLevel?.value || '';

        // Appliquer les filtres
        this.applyFiltersToTable();

        // Mettre à jour l'indicateur de filtre actif
        this.updateFilterIndicator();

        utils.info('Filtres appliqués', this.filters);
    }

    applyFiltersToTable() {
        const rows = utils.$('.game-row');
        
        rows.forEach(row => {
            const shouldShow = this.shouldShowRow(row);
            row.style.display = shouldShow ? '' : 'none';
        });
    }

    shouldShowRow(row) {
        // Filtre par admin
        if (this.filters.admin) {
            const adminCell = row.querySelector('.admin-name');
            const adminName = adminCell ? adminCell.textContent.toLowerCase() : '';
            if (!adminName.includes(this.filters.admin)) {
                return false;
            }
        }

        // Filtre par statut
        if (this.filters.status) {
            const statusBadge = row.querySelector('.game-status .badge');
            const rowStatus = row.dataset.status || this.getStatusFromBadge(statusBadge);
            if (rowStatus !== this.filters.status) {
                return false;
            }
        }

        // Filtre par niveau
        if (this.filters.level) {
            const levelBadge = row.querySelector('.game-level .badge');
            const levelText = levelBadge ? levelBadge.textContent : '';
            const levelMatch = levelText.match(/\d+/);
            const rowLevel = levelMatch ? levelMatch[0] : '';
            if (rowLevel !== this.filters.level) {
                return false;
            }
        }

        return true;
    }

    getStatusFromBadge(badge) {
        if (!badge) return '';
        
        const classList = badge.classList;
        if (classList.contains('badge-warning')) return 'en_attente';
        if (classList.contains('badge-success')) return 'en_cours';
        if (classList.contains('badge-secondary')) return 'terminee';
        if (classList.contains('badge-info')) return 'pause';
        if (classList.contains('badge-error')) return 'annulee';
        
        return '';
    }

    resetFilters() {
        // Réinitialiser les valeurs
        if (this.elements.filterAdmin) this.elements.filterAdmin.value = '';
        if (this.elements.filterStatus) this.elements.filterStatus.value = '';
        if (this.elements.filterLevel) this.elements.filterLevel.value = '';

        // Réinitialiser les filtres internes
        this.filters = { admin: '', status: '', level: '' };

        // Afficher toutes les lignes
        utils.$('.game-row').forEach(row => {
            row.style.display = '';
        });

        // Mettre à jour l'indicateur
        this.updateFilterIndicator();

        utils.info('Filtres réinitialisés');
    }

    hasActiveFilters() {
        return this.filters.admin || this.filters.status || this.filters.level;
    }

    updateFilterIndicator() {
        if (this.elements.filterGamesBtn) {
            if (this.hasActiveFilters()) {
                this.elements.filterGamesBtn.classList.add('filter-active');
            } else {
                this.elements.filterGamesBtn.classList.remove('filter-active');
            }
        }
    }

    // === GESTION DU RAFRAÎCHISSEMENT AUTOMATIQUE ===

    startAutoRefresh() {
        this.stopAutoRefresh(); // S'assurer qu'il n'y a pas de timer en cours
        this.refreshTimer = setInterval(() => {
            this.refreshGames();
        }, this.refreshInterval);
    }

    stopAutoRefresh() {
        if (this.refreshTimer) {
            clearInterval(this.refreshTimer);
            this.refreshTimer = null;
        }
    }

    // === UTILITAIRES ===

    setLoadingState(loading) {
        this.isLoading = loading;

        if (loading) {
            // Afficher un indicateur de chargement
            this.showLoadingOverlay();
            
            // Désactiver le bouton de rafraîchissement
            if (this.elements.refreshBtn) {
                this.elements.refreshBtn.disabled = true;
                this.elements.refreshBtn.innerHTML = '<span class="loading"></span> Chargement...';
            }
        } else {
            // Cacher l'indicateur de chargement
            this.hideLoadingOverlay();
            
            // Réactiver le bouton de rafraîchissement
            if (this.elements.refreshBtn) {
                this.elements.refreshBtn.disabled = false;
                this.elements.refreshBtn.innerHTML = '<span class="btn-icon">🔄</span> Rafraîchir';
            }
        }
    }

    showLoadingOverlay() {
        let overlay = utils.$('.loading-overlay');
        if (!overlay) {
            overlay = utils.createElement('div', {
                className: 'loading-overlay',
                innerHTML: '<div class="loading-spinner"></div>'
            });
            document.body.appendChild(overlay);
        }
    }

    hideLoadingOverlay() {
        const overlay = utils.$('.loading-overlay');
        if (overlay) {
            overlay.remove();
        }
    }

    showNotification(message, type = 'info', duration = 3000) {
        // Utiliser la fonction toast des utils
        if (window.toast) {
            window.toast(message, type, duration);
        } else {
            // Fallback simple
            console.log(`[${type.toUpperCase()}] ${message}`);
        }
    }

    // === NETTOYAGE ===

    destroy() {
        this.stopAutoRefresh();
        
        // Nettoyer les écouteurs d'événements si nécessaire
        // (En général, pas nécessaire car on ne recrée pas l'instance)
    }
}

// Utilitaire pour échapper le HTML
utils.escapeHtml = function(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
};

// Initialisation
let dashboardManager;
utils.ready(() => {
    dashboardManager = new DashboardManager();
    utils.info('Dashboard manager initialisé');
});

// Nettoyage lors du déchargement de la page
window.addEventListener('beforeunload', () => {
    if (dashboardManager) {
        dashboardManager.destroy();
    }
});

// Export pour usage externe/tests
if (typeof module !== 'undefined' && module.exports) {
    module.exports = DashboardManager;
}