/**
 * Game JavaScript - Logique du jeu The Mind
 * Gestion du plateau de jeu, cartes, interactions et temps réel
 */

// Module Game dans l'espace de noms TheMind
TheMind.game = (function() {
    'use strict';

    // Configuration par défaut
    let config = {
        partieId: null,
        userId: null,
        userPosition: null,
        estAdmin: false,
        status: 'en_attente',
        niveau: 1,
        viesMax: 3,
        updateInterval: 3000,
        sounds: {}
    };

    // Données du jeu
    let gameData = {
        cartes: [],
        cartesJouees: [],
        joueurs: [],
        partie: {},
        selectedCard: null,
        lastUpdateTime: 0
    };

    // État du jeu
    let gameState = {
        isUpdating: false,
        updateTimer: null,
        soundEnabled: true,
        animationsEnabled: true,
        connectionLost: false
    };

    // Cache des éléments DOM
    const elements = {
        playerCards: null,
        playedCards: null,
        playersList: null,
        gameStatusBadge: null,
        playCardBtn: null,
        useShurikenBtn: null,
        adminControls: null,
        infoDisplays: {},
        modals: {},
        sounds: {}
    };

    /**
     * Initialisation du module game
     */
    function init() {
        TheMind.utils.log('Game: Initialisation');
        
        loadConfig();
        cacheElements();
        bindEvents();
        initSounds();
        loadInitialData();
        initKeyboardShortcuts();
        
        TheMind.utils.log('Game: Initialisé avec succès');
    }

    /**
     * Charge la configuration depuis les éléments cachés
     */
    function loadConfig() {
        const partieId = document.getElementById('partie-id');
        const userId = document.getElementById('user-id');
        const userPosition = document.getElementById('user-position');
        const estAdmin = document.getElementById('est-admin');
        const viesMax = document.getElementById('vies-max');

        if (partieId) config.partieId = parseInt(partieId.value);
        if (userId) config.userId = parseInt(userId.value);
        if (userPosition) config.userPosition = parseInt(userPosition.value);
        if (estAdmin) config.estAdmin = estAdmin.value === '1';
        if (viesMax) config.viesMax = parseInt(viesMax.value);

        // Merge avec la config externe si elle existe
        if (window.TheMind && window.TheMind.game && window.TheMind.game.config) {
            config = { ...config, ...window.TheMind.game.config };
        }
    }

    /**
     * Cache les éléments DOM fréquemment utilisés
     */
    function cacheElements() {
        elements.playerCards = document.getElementById('player-cards');
        elements.playedCards = document.getElementById('played-cards');
        elements.playersList = document.getElementById('players-list');
        elements.gameStatusBadge = document.querySelector('.game-status-badge');
        elements.playCardBtn = document.getElementById('play-card-btn');
        elements.useShurikenBtn = document.getElementById('use-shuriken-btn');
        elements.adminControls = document.getElementById('admin-controls');

        // Éléments d'affichage des informations
        elements.infoDisplays = {
            niveau: document.getElementById('niveau-display'),
            vies: document.getElementById('vies-display'),
            shurikens: document.getElementById('shurikens-display'),
            cardsCount: document.getElementById('cards-count'),
            playedCount: document.getElementById('played-count'),
            currentLevel: document.getElementById('current-level')
        };

        // Modales
        elements.modals = {
            shuriken: document.getElementById('shuriken-modal'),
            gameResult: document.getElementById('game-result-modal'),
            gameLog: document.getElementById('game-log-modal')
        };

        // Sons
        if (config.sounds) {
            Object.entries(config.sounds).forEach(([key, id]) => {
                elements.sounds[key] = document.getElementById(id);
            });
        }
    }

    /**
     * Lie les événements aux éléments
     */
    function bindEvents() {
        // Cartes du joueur
        if (elements.playerCards) {
            elements.playerCards.addEventListener('click', handleCardClick);
            elements.playerCards.addEventListener('keydown', handleCardKeydown);
        }

        // Boutons d'action
        if (elements.playCardBtn) {
            elements.playCardBtn.addEventListener('click', playSelectedCard);
        }

        if (elements.useShurikenBtn) {
            elements.useShurikenBtn.addEventListener('click', showShurikenModal);
        }

        // Boutons admin
        bindAdminControls();

        // Modales
        bindModalEvents();

        // Événements globaux
        document.addEventListener('visibilitychange', handleVisibilityChange);
        window.addEventListener('beforeunload', cleanup);
        window.addEventListener('online', handleOnline);
        window.addEventListener('offline', handleOffline);
    }

    /**
     * Lie les événements des contrôles admin
     */
    function bindAdminControls() {
        const startBtn = document.getElementById('start-game-btn');
        const pauseBtn = document.getElementById('pause-game-btn');
        const resumeBtn = document.getElementById('resume-game-btn');
        const cancelBtn = document.getElementById('cancel-game-btn');

        if (startBtn) startBtn.addEventListener('click', () => adminAction('start'));
        if (pauseBtn) pauseBtn.addEventListener('click', () => adminAction('pause'));
        if (resumeBtn) resumeBtn.addEventListener('click', () => adminAction('resume'));
        if (cancelBtn) cancelBtn.addEventListener('click', () => confirmAdminAction('cancel'));
    }

    /**
     * Lie les événements des modales
     */
    function bindModalEvents() {
        // Modal shuriken
        const confirmShuriken = document.getElementById('confirm-shuriken');
        const cancelShuriken = document.getElementById('cancel-shuriken');

        if (confirmShuriken) confirmShuriken.addEventListener('click', useShuriken);
        if (cancelShuriken) cancelShuriken.addEventListener('click', hideShurikenModal);

        // Modal résultat
        const nextLevelBtn = document.getElementById('next-level-btn');
        if (nextLevelBtn) nextLevelBtn.addEventListener('click', () => adminAction('next_level'));
    }

    /**
     * Initialise les sons
     */
    function initSounds() {
        Object.values(elements.sounds).forEach(audio => {
            if (audio) {
                audio.volume = TheMind.utils.getVolume();
                audio.preload = 'auto';
            }
        });
    }

    /**
     * Charge les données initiales
     */
    function loadInitialData() {
        if (window.TheMind && window.TheMind.game && window.TheMind.game.initialData) {
            const data = window.TheMind.game.initialData;
            
            gameData.cartes = data.cartes || [];
            gameData.cartesJouees = data.cartesJouees || [];
            gameData.joueurs = data.joueurs || [];
            gameData.partie = data.partie || {};

            updateGameDisplay();
        }
    }

    /**
     * Initialise les raccourcis clavier
     */
    function initKeyboardShortcuts() {
        document.addEventListener('keydown', function(e) {
            // Ignorer si dans un champ de saisie
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') {
                return;
            }

            // Ignorer si modificateurs
            if (e.ctrlKey || e.metaKey || e.altKey) {
                return;
            }

            switch (e.key) {
                case ' ':
                case 'Enter':
                    e.preventDefault();
                    playSelectedCard();
                    break;
                case 's':
                case 'S':
                    e.preventDefault();
                    showShurikenModal();
                    break;
                case 'ArrowLeft':
                case 'ArrowRight':
                    e.preventDefault();
                    navigateCards(e.key === 'ArrowLeft' ? -1 : 1);
                    break;
                case '1':
                case '2':
                case '3':
                case '4':
                case '5':
                case '6':
                case '7':
                case '8':
                case '9':
                    e.preventDefault();
                    selectCardByIndex(parseInt(e.key) - 1);
                    break;
            }
        });
    }

    /**
     * Gère le clic sur une carte
     */
    function handleCardClick(e) {
        const card = e.target.closest('.game-card');
        if (!card || !card.classList.contains('selectable')) return;

        e.preventDefault();
        selectCard(card);
        playSound('cardSelect');
    }

    /**
     * Gère les touches sur une carte
     */
    function handleCardKeydown(e) {
        const card = e.target.closest('.game-card');
        if (!card || !card.classList.contains('selectable')) return;

        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            selectCard(card);
            playSound('cardSelect');
        }
    }

    /**
     * Sélectionne une carte
     */
    function selectCard(cardElement) {
        if (!cardElement) return;

        // Désélectionner la carte précédente
        if (gameData.selectedCard) {
            gameData.selectedCard.classList.remove('selected');
            gameData.selectedCard.setAttribute('aria-selected', 'false');
        }

        // Sélectionner la nouvelle carte
        if (gameData.selectedCard === cardElement) {
            gameData.selectedCard = null;
            updatePlayCardButton();
        } else {
            gameData.selectedCard = cardElement;
            cardElement.classList.add('selected');
            cardElement.setAttribute('aria-selected', 'true');
            cardElement.focus();
            updatePlayCardButton();
        }
    }

    /**
     * Sélectionne une carte par index
     */
    function selectCardByIndex(index) {
        const cards = elements.playerCards?.querySelectorAll('.game-card.selectable');
        if (cards && cards[index]) {
            selectCard(cards[index]);
        }
    }

    /**
     * Navigue entre les cartes avec les flèches
     */
    function navigateCards(direction) {
        const cards = Array.from(elements.playerCards?.querySelectorAll('.game-card.selectable') || []);
        if (cards.length === 0) return;

        let currentIndex = gameData.selectedCard ? 
            cards.indexOf(gameData.selectedCard) : -1;

        let newIndex = currentIndex + direction;
        if (newIndex < 0) newIndex = cards.length - 1;
        if (newIndex >= cards.length) newIndex = 0;

        selectCard(cards[newIndex]);
    }

    /**
     * Met à jour l'état du bouton jouer carte
     */
    function updatePlayCardButton() {
        if (!elements.playCardBtn) return;

        const hasSelection = gameData.selectedCard !== null;
        const canPlay = config.status === 'en_cours' && hasSelection;

        elements.playCardBtn.disabled = !canPlay;
        elements.playCardBtn.setAttribute('aria-disabled', !canPlay);

        if (hasSelection) {
            const cardValue = gameData.selectedCard.dataset.cardValue;
            const btnText = elements.playCardBtn.querySelector('.btn-text');
            if (btnText) {
                btnText.textContent = `${TheMind.config.texts.play_card} ${cardValue}`;
            }
        } else {
            const btnText = elements.playCardBtn.querySelector('.btn-text');
            if (btnText) {
                btnText.textContent = TheMind.config.texts.play_card;
            }
        }
    }

    /**
     * Joue la carte sélectionnée
     */
    async function playSelectedCard() {
        if (!gameData.selectedCard || config.status !== 'en_cours') {
            return;
        }

        const cardId = parseInt(gameData.selectedCard.dataset.cardId);
        const cardValue = parseInt(gameData.selectedCard.dataset.cardValue);

        try {
            TheMind.utils.log(`Game: Tentative de jouer la carte ${cardValue} (ID: ${cardId})`);

            // Désactiver temporairement les interactions
            setCardInteractionsEnabled(false);

            // Animation de jeu de carte
            gameData.selectedCard.classList.add('card-playing');

            const response = await TheMind.api.post('/game/play-card', {
                partie_id: config.partieId,
                card_id: cardId
            });

            if (response.success) {
                TheMind.utils.log(`Game: Carte ${cardValue} jouée avec succès`);
                
                // Son de succès ou d'erreur
                if (response.error_card) {
                    playSound('error');
                    showNotification(TheMind.config.texts.card_played_error, 'warning');
                } else {
                    playSound('cardPlay');
                    showNotification(TheMind.config.texts.card_played_success, 'success');
                }

                // Supprimer la carte de l'interface après l'animation
                setTimeout(() => {
                    if (gameData.selectedCard) {
                        gameData.selectedCard.remove();
                        gameData.selectedCard = null;
                        updatePlayCardButton();
                    }
                }, 800);

                // Vérifier l'état de la partie
                if (response.partie_status) {
                    handleGameStatusChange(response.partie_status);
                }

                // Actualiser l'état du jeu
                updateGameState();

            } else {
                throw new Error(response.message || 'Erreur lors du jeu de la carte');
            }

        } catch (error) {
            TheMind.utils.log(`Game: Erreur jeu carte - ${error.message}`, 'error');
            
            // Remettre la carte en état normal
            if (gameData.selectedCard) {
                gameData.selectedCard.classList.remove('card-playing');
            }
            
            playSound('error');
            showNotification(error.message, 'error');
        } finally {
            setCardInteractionsEnabled(true);
        }
    }

    /**
     * Active/désactive les interactions avec les cartes
     */
    function setCardInteractionsEnabled(enabled) {
        const cards = elements.playerCards?.querySelectorAll('.game-card');
        if (cards) {
            cards.forEach(card => {
                if (enabled) {
                    card.classList.add('selectable');
                    card.setAttribute('tabindex', '0');
                } else {
                    card.classList.remove('selectable');
                    card.setAttribute('tabindex', '-1');
                }
            });
        }

        if (elements.playCardBtn) {
            elements.playCardBtn.disabled = !enabled;
        }
    }

    /**
     * Affiche la modal de shuriken
     */
    function showShurikenModal() {
        if (config.status !== 'en_cours' || !elements.modals.shuriken) return;

        // Vérifier qu'il reste des shurikens
        const shurikensRestants = gameData.partie.shurikens_restants || 0;
        if (shurikensRestants <= 0) {
            showNotification(TheMind.config.texts.no_shurikens, 'warning');
            return;
        }

        TheMind.utils.showModal('shuriken-modal');
    }

    /**
     * Cache la modal de shuriken
     */
    function hideShurikenModal() {
        TheMind.utils.hideModal('shuriken-modal');
    }

    /**
     * Utilise un shuriken
     */
    async function useShuriken() {
        try {
            TheMind.utils.log('Game: Utilisation d\'un shuriken');

            hideShurikenModal();
            
            // Effet visuel sur le bouton shuriken
            if (elements.useShurikenBtn) {
                elements.useShurikenBtn.classList.add('shuriken-effect');
                setTimeout(() => {
                    elements.useShurikenBtn.classList.remove('shuriken-effect');
                }, 1000);
            }

            const response = await TheMind.api.post('/game/use-shuriken', {
                partie_id: config.partieId
            });

            if (response.success) {
                TheMind.utils.log('Game: Shuriken utilisé avec succès');
                
                playSound('shuriken');
                showNotification(TheMind.config.texts.shuriken_used_success, 'success');

                // Vérifier l'état de la partie
                if (response.partie_status) {
                    handleGameStatusChange(response.partie_status);
                }

                // Actualiser l'état du jeu
                updateGameState();

            } else {
                throw new Error(response.message || 'Erreur lors de l\'utilisation du shuriken');
            }

        } catch (error) {
            TheMind.utils.log(`Game: Erreur shuriken - ${error.message}`, 'error');
            playSound('error');
            showNotification(error.message, 'error');
        }
    }

    /**
     * Exécute une action admin
     */
    async function adminAction(action) {
        if (!config.estAdmin) {
            TheMind.utils.log('Game: Tentative d\'action admin sans permissions', 'warn');
            return;
        }

        try {
            TheMind.utils.log(`Game: Action admin - ${action}`);

            const response = await TheMind.api.post('/game/admin-action', {
                partie_id: config.partieId,
                action: action
            });

            if (response.success) {
                TheMind.utils.log(`Game: Action admin ${action} réussie`);
                
                showNotification(response.message, 'success');
                
                // Actions spécifiques selon le type
                switch (action) {
                    case 'start':
                        config.status = 'en_cours';
                        startGameUpdates();
                        playSound('gameStart');
                        break;
                    case 'pause':
                        config.status = 'pause';
                        stopGameUpdates();
                        break;
                    case 'resume':
                        config.status = 'en_cours';
                        startGameUpdates();
                        break;
                    case 'cancel':
                        config.status = 'annulee';
                        stopGameUpdates();
                        setTimeout(() => {
                            window.location.href = 'dashboard.php';
                        }, 2000);
                        return;
                    case 'next_level':
                        hideGameResultModal();
                        config.status = 'en_cours';
                        startGameUpdates();
                        playSound('levelComplete');
                        break;
                }

                updateGameDisplay();

            } else {
                throw new Error(response.message || `Erreur lors de l'action ${action}`);
            }

        } catch (error) {
            TheMind.utils.log(`Game: Erreur action admin ${action} - ${error.message}`, 'error');
            playSound('error');
            showNotification(error.message, 'error');
        }
    }

    /**
     * Confirme une action admin destructive
     */
    function confirmAdminAction(action) {
        const messages = {
            cancel: TheMind.config.texts.confirm_cancel_game || 'Êtes-vous sûr de vouloir annuler cette partie ?'
        };

        const message = messages[action];
        if (message && confirm(message)) {
            adminAction(action);
        }
    }

    /**
     * Démarre les mises à jour automatiques du jeu
     */
    function startGameUpdates() {
        if (gameState.updateTimer) {
            clearInterval(gameState.updateTimer);
        }

        // Mise à jour immédiate
        updateGameState();

        // Démarrer les mises à jour périodiques
        gameState.updateTimer = setInterval(updateGameState, config.updateInterval);
        
        TheMind.utils.log('Game: Mises à jour automatiques démarrées');
    }

    /**
     * Arrête les mises à jour automatiques du jeu
     */
    function stopGameUpdates() {
        if (gameState.updateTimer) {
            clearInterval(gameState.updateTimer);
            gameState.updateTimer = null;
        }
        
        TheMind.utils.log('Game: Mises à jour automatiques arrêtées');
    }

    /**
     * Met à jour l'état du jeu depuis le serveur
     */
    async function updateGameState() {
        if (gameState.isUpdating) return;

        gameState.isUpdating = true;

        try {
            const response = await TheMind.api.get(`/game/state?partie_id=${config.partieId}`);

            if (response.success) {
                const data = response.data;
                
                // Mettre à jour les données locales
                gameData.cartes = data.cartes || [];
                gameData.cartesJouees = data.cartesJouees || [];
                gameData.joueurs = data.joueurs || [];
                gameData.partie = data.partie || {};
                
                // Mettre à jour la configuration
                config.status = data.partie.status;
                config.niveau = data.partie.niveau;

                // Mettre à jour l'affichage
                updateGameDisplay();

                // Vérifier les changements d'état
                if (data.partie.status !== config.status) {
                    handleGameStatusChange(data.partie.status);
                }

                gameState.lastUpdateTime = Date.now();
                
                // Rétablir la connexion si elle était perdue
                if (gameState.connectionLost) {
                    gameState.connectionLost = false;
                    hideConnectionLostNotification();
                }

            } else {
                throw new Error(response.message || 'Erreur lors de la mise à jour');
            }

        } catch (error) {
            TheMind.utils.log(`Game: Erreur mise à jour - ${error.message}`, 'error');
            
            // Gérer la perte de connexion
            if (!gameState.connectionLost) {
                gameState.connectionLost = true;
                showConnectionLostNotification();
            }
        } finally {
            gameState.isUpdating = false;
        }
    }

    /**
     * Met à jour l'affichage du jeu
     */
    function updateGameDisplay() {
        updateInfoDisplays();
        updatePlayerCards();
        updatePlayedCards();
        updatePlayersList();
        updateGameStatus();
        updateAdminControls();
    }

    /**
     * Met à jour les affichages d'informations
     */
    function updateInfoDisplays() {
        // Niveau
        if (elements.infoDisplays.niveau) {
            elements.infoDisplays.niveau.textContent = config.niveau;
        }
        if (elements.infoDisplays.currentLevel) {
            elements.infoDisplays.currentLevel.textContent = config.niveau;
        }

        // Vies
        if (elements.infoDisplays.vies && gameData.partie.vies_restantes !== undefined) {
            updateLivesDisplay(gameData.partie.vies_restantes);
        }

        // Shurikens
        if (elements.infoDisplays.shurikens && gameData.partie.shurikens_restants !== undefined) {
            updateShurikensDisplay(gameData.partie.shurikens_restants);
        }

        // Compteur de cartes
        if (elements.infoDisplays.cardsCount) {
            elements.infoDisplays.cardsCount.textContent = gameData.cartes.length;
        }

        // Compteur de cartes jouées
        if (elements.infoDisplays.playedCount) {
            elements.infoDisplays.playedCount.textContent = gameData.cartesJouees.length;
        }
    }

    /**
     * Met à jour l'affichage des vies
     */
    function updateLivesDisplay(viesRestantes) {
        const container = elements.infoDisplays.vies;
        if (!container) return;

        container.innerHTML = '';

        // Vies restantes
        for (let i = 0; i < viesRestantes; i++) {
            const life = document.createElement('span');
            life.className = 'life-icon active';
            life.setAttribute('data-life', i + 1);
            life.textContent = '❤️';
            container.appendChild(life);
        }

        // Vies perdues
        for (let i = viesRestantes; i < config.viesMax; i++) {
            const life = document.createElement('span');
            life.className = 'life-icon lost';
            life.setAttribute('data-life', i + 1);
            life.textContent = '💔';
            container.appendChild(life);
        }
    }

    /**
     * Met à jour l'affichage des shurikens
     */
    function updateShurikensDisplay(shurikensRestants) {
        const container = elements.infoDisplays.shurikens;
        if (!container) return;

        container.innerHTML = '';

        for (let i = 0; i < shurikensRestants; i++) {
            const shuriken = document.createElement('span');
            shuriken.className = 'shuriken-icon available';
            shuriken.textContent = '⭐';
            container.appendChild(shuriken);
        }

        // Mettre à jour le bouton shuriken
        if (elements.useShurikenBtn) {
            elements.useShurikenBtn.disabled = shurikensRestants <= 0 || config.status !== 'en_cours';
        }
    }

    /**
     * Met à jour les cartes du joueur
     */
    function updatePlayerCards() {
        if (!elements.playerCards) return;

        const currentCards = Array.from(elements.playerCards.querySelectorAll('.game-card'))
            .map(card => parseInt(card.dataset.cardId));
        
        const newCards = gameData.cartes.map(carte => carte.id);

        // Si les cartes sont identiques, pas besoin de mettre à jour
        if (arraysEqual(currentCards.sort(), newCards.sort())) {
            return;
        }

        // Reconstruire l'affichage des cartes
        elements.playerCards.innerHTML = '';

        if (gameData.cartes.length === 0) {
            elements.playerCards.innerHTML = `
                <div class="no-cards-message">
                    <div class="no-cards-icon">🎴</div>
                    <div class="no-cards-text">${TheMind.config.texts.no_cards_left}</div>
                </div>
            `;
        } else {
            gameData.cartes.forEach((carte, index) => {
                const cardElement = createCardElement(carte, index);
                elements.playerCards.appendChild(cardElement);
            });
        }

        // Réinitialiser la sélection
        gameData.selectedCard = null;
        updatePlayCardButton();
    }

    /**
     * Crée un élément de carte
     */
    function createCardElement(carte, index) {
        const cardElement = document.createElement('div');
        cardElement.className = 'game-card selectable card-dealing';
        cardElement.setAttribute('data-card-id', carte.id);
        cardElement.setAttribute('data-card-value', carte.valeur);
        cardElement.setAttribute('data-card-index', index);
        cardElement.setAttribute('tabindex', '0');
        cardElement.setAttribute('role', 'button');
        cardElement.setAttribute('aria-label', `${TheMind.config.texts.card} ${carte.valeur}`);
        cardElement.setAttribute('aria-selected', 'false');

        cardElement.innerHTML = `
            <div class="card-inner">
                <div class="card-front">
                    <div class="card-value">${carte.valeur}</div>
                    <div class="card-suit">🧠</div>
                </div>
                <div class="card-back">
                    <div class="card-logo">
                        <div class="mind-eye">👁️</div>
                    </div>
                </div>
            </div>
        `;

        // Animation d'apparition avec délai
        setTimeout(() => {
            cardElement.classList.remove('card-dealing');
        }, index * 100 + 100);

        return cardElement;
    }

    /**
     * Met à jour les cartes jouées
     */
    function updatePlayedCards() {
        if (!elements.playedCards) return;

        if (gameData.cartesJouees.length === 0) {
            elements.playedCards.innerHTML = `
                <div class="no-played-cards">
                    <div class="no-played-icon">🎴</div>
                    <div class="no-played-text">${TheMind.config.texts.no_cards_played_yet}</div>
                </div>
            `;
            return;
        }

        // Reconstruire l'affichage
        elements.playedCards.innerHTML = '';

        gameData.cartesJouees.forEach((carte, index) => {
            const playedElement = document.createElement('div');
            playedElement.className = 'played-card';
            playedElement.setAttribute('data-card-id', carte.id);
            playedElement.setAttribute('data-card-value', carte.valeur);
            playedElement.setAttribute('data-played-order', index);

            playedElement.innerHTML = `
                <div class="played-card-value">${carte.valeur}</div>
                <div class="played-card-info">
                    <div class="player-info">
                        <span class="player-avatar">${carte.joueur_avatar}</span>
                        <span class="player-name">${carte.joueur_nom}</span>
                    </div>
                    <div class="play-time">
                        ${new Date(carte.date_action).toLocaleTimeString('fr-FR', {hour: '2-digit', minute: '2-digit'})}
                    </div>
                </div>
            `;

            elements.playedCards.appendChild(playedElement);
        });
    }

    /**
     * Met à jour la liste des joueurs
     */
    function updatePlayersList() {
        if (!elements.playersList) return;

        elements.