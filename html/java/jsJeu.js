document.addEventListener('DOMContentLoaded', function() {
    // Récupérer les éléments cachés
    const csrfToken = document.getElementById('csrf_token').value;
    const partieId = document.getElementById('partie_id').value;
    const userId = document.getElementById('user_id').value;
    const partieStatus = document.getElementById('partie_status').value;
    const estAdmin = document.getElementById('est_admin').value === '1';
    
    // Sélection des éléments DOM
    const playerCards = document.getElementById('player-cards');
    const playedCards = document.getElementById('played-cards');
    const playersList = document.getElementById('players-list');
    const niveauEl = document.getElementById('niveau');
    const viesEl = document.getElementById('vies');
    const shurikensEl = document.getElementById('shurikens');
    
    // Boutons d'action du joueur
    const playCardBtn = document.getElementById('play-card-btn');
    const useShurikenBtn = document.getElementById('use-shuriken-btn');
    
    // Boutons d'administration
    const startGameBtn = document.getElementById('start-game-btn');
    const pauseGameBtn = document.getElementById('pause-game-btn');
    const resumeGameBtn = document.getElementById('resume-game-btn');
    const cancelGameBtn = document.getElementById('cancel-game-btn');
    
    // Modals
    const gameOverModal = document.getElementById('game-over-modal');
    const gameResultMessage = document.getElementById('game-result-message');
    const backToDashboardBtn = document.getElementById('back-to-dashboard');
    const nextLevelBtn = document.getElementById('next-level-btn');
    const shurikenModal = document.getElementById('shuriken-modal');
    const confirmShurikenBtn = document.getElementById('confirm-shuriken');
    const cancelShurikenBtn = document.getElementById('cancel-shuriken');
    const errorModal = document.getElementById('error-modal');
    const errorMessage = document.getElementById('error-message');
    const closeErrorBtn = document.getElementById('close-error');
    
    // Variables d'état
    let selectedCard = null;
    let lastPlayedValue = 0;
    let gameInterval = null;
    let gameState = {
        niveau: parseInt(niveauEl.textContent),
        vies: viesEl.querySelectorAll('.life-icon').length,
        shurikens: shurikensEl.querySelectorAll('.shuriken-icon').length,
        status: partieStatus
    };
    
    // Animation de cartes
    const animateCards = () => {
        document.querySelectorAll('.card').forEach((card, index) => {
            card.style.transition = 'transform 0.5s ease-in-out';
            card.style.transform = 'translateY(-10px)';
            setTimeout(() => {
                card.style.transform = 'translateY(0)';
            }, 500 + index * 100);
        });
    };
    
    // Activer l'effet de survol pour les cartes (réactif aux mouvements)
    const enableCardHover = () => {
        document.querySelectorAll('.card').forEach(card => {
            card.addEventListener('mousemove', function(e) {
                // Calculer la position relative du curseur dans la carte
                const rect = card.getBoundingClientRect();
                const x = e.clientX - rect.left; // x position within the element.
                const y = e.clientY - rect.top;  // y position within the element.
                
                // Calculer les angles de rotation en fonction de la position du curseur
                const rotateY = ((x / rect.width) - 0.5) * 20; // -10 à 10 degrés sur l'axe Y
                const rotateX = ((y / rect.height) - 0.5) * -20; // 10 à -10 degrés sur l'axe X
                
                // Appliquer la rotation
                card.style.transform = `perspective(1000px) rotateX(${rotateX}deg) rotateY(${rotateY}deg)`;
            });
            
            card.addEventListener('mouseleave', function() {
                // Rétablir la position normale
                card.style.transform = 'perspective(1000px) rotateX(0) rotateY(0)';
            });
        });
    };
    
    // Démarrer la mise à jour automatique du jeu (toutes les 3 secondes)
    if (partieStatus === 'en_cours') {
        startGameUpdates();
        setTimeout(animateCards, 500); // Animation initiale des cartes
        setTimeout(enableCardHover, 1000); // Activer l'effet de survol après l'animation
    }
    
    // Gestionnaires d'événements
    
    // Sélection de carte
    if (playerCards) {
        playerCards.addEventListener('click', function(e) {
            const card = e.target.closest('.card');
            if (card && gameState.status === 'en_cours') {
                // Désélectionner la carte précédente
                if (selectedCard) {
                    selectedCard.classList.remove('selected');
                }
                
                // Sélectionner la nouvelle carte
                if (selectedCard !== card) {
                    card.classList.add('selected');
                    selectedCard = card;
                    
                    // Effet sonore de sélection
                    playSound('select');
                } else {
                    selectedCard = null;
                }
            }
        });
    }
    
    // Jouer une carte
    if (playCardBtn) {
        playCardBtn.addEventListener('click', function() {
            if (selectedCard && gameState.status === 'en_cours') {
                const cardId = selectedCard.getAttribute('data-id');
                const cardValue = selectedCard.getAttribute('data-value');
                
                playCard(cardId, cardValue);
            } else {
                showError('Veuillez sélectionner une carte à jouer.');
            }
        });
    }
    
    // Utiliser un shuriken
    if (useShurikenBtn) {
        useShurikenBtn.addEventListener('click', function() {
            if (gameState.shurikens > 0 && gameState.status === 'en_cours') {
                showShurikenModal();
            } else {
                showError('Vous n\'avez plus de shurikens disponibles.');
            }
        });
    }
    
    // Confirmation d'utilisation du shuriken
    if (confirmShurikenBtn) {
        confirmShurikenBtn.addEventListener('click', function() {
            useShuriken();
            hideShurikenModal();
        });
    }
    
    // Annulation d'utilisation du shuriken
    if (cancelShurikenBtn) {
        cancelShurikenBtn.addEventListener('click', hideShurikenModal);
    }
    
    // Fermer le message d'erreur
    if (closeErrorBtn) {
        closeErrorBtn.addEventListener('click', hideErrorModal);
    }
    
    // Boutons d'administration
    if (startGameBtn && estAdmin) {
        startGameBtn.addEventListener('click', startGame);
    }
    
    if (pauseGameBtn && estAdmin) {
        pauseGameBtn.addEventListener('click', pauseGame);
    }
    
    if (resumeGameBtn && estAdmin) {
        resumeGameBtn.addEventListener('click', resumeGame);
    }
    
    if (cancelGameBtn && estAdmin) {
        cancelGameBtn.addEventListener('click', cancelGame);
    }
    
    // Boutons de fin de partie
    if (backToDashboardBtn) {
        backToDashboardBtn.addEventListener('click', function() {
            window.location.href = 'dashboard.php';
        });
    }
    
    if (nextLevelBtn && estAdmin) {
        nextLevelBtn.addEventListener('click', startNextLevel);
    }
    
    // Fonctions
    
    // Jouer une carte
    function playCard(cardId, cardValue) {
        // Effet sonore
        playSound('play_card');
        
        fetch('plateau/actions/play_card.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: `card_id=${cardId}&partie_id=${partieId}&csrf_token=${csrfToken}`
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Erreur réseau');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                // Effet visuel pour la carte jouée
                if (selectedCard) {
                    selectedCard.style.transform = 'translateY(-100px) scale(0.5)';
                    selectedCard.style.opacity = '0';
                    setTimeout(() => {
                        selectedCard.remove();
                        selectedCard = null;
                    }, 500);
                }
                
                // Si erreur (mauvaise carte), jouer un son d'erreur
                if (data.error_card) {
                    playSound('error');
                    // Animation de perte de vie
                    const hearts = document.querySelectorAll('.life-icon:not(.life-lost)');
                    if (hearts.length > 0) {
                        const lastHeart = hearts[hearts.length - 1];
                        lastHeart.classList.add('life-lost');
                        lastHeart.style.animation = 'heartBeat 1s';
                    }
                }
                
                // Mettre à jour l'interface
                updateGameState();
            } else {
                showError(data.message || 'Erreur lors du jeu de la carte.');
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            showError('Une erreur est survenue. Veuillez réessayer.');
        });
    }
    
    // Utiliser un shuriken
    function useShuriken() {
        // Effet sonore
        playSound('shuriken');
        
        fetch('plateau/actions/use_shuriken.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: `partie_id=${partieId}&csrf_token=${csrfToken}`
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Erreur réseau');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                // Animation du shuriken
                const shuriken = document.querySelector('.shuriken-icon');
                if (shuriken) {
                    shuriken.style.animation = 'spin 1s linear';
                    setTimeout(() => {
                        // Mettre à jour l'interface
                        updateGameState();
                    }, 1000);
                } else {
                    updateGameState();
                }
            } else {
                showError(data.message || 'Erreur lors de l\'utilisation du shuriken.');
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            showError('Une erreur est survenue. Veuillez réessayer.');
        });
    }
    
    // Fonctions d'administration
    
    // Démarrer la partie
    function startGame() {
        fetch('plateau/actions/admin_action.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: `action=start&partie_id=${partieId}&csrf_token=${csrfToken}`
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Erreur réseau');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                // Effet sonore
                playSound('start_game');
                
                // Mettre à jour l'état de la partie
                gameState.status = 'en_cours';
                
                // Mettre à jour l'interface
                updateGameState();
                
                // Démarrer les mises à jour automatiques
                startGameUpdates();
                
                // Animer les cartes
                setTimeout(() => {
                    animateCards();
                    enableCardHover();
                }, 1000);
            } else {
                showError(data.message || 'Erreur lors du démarrage de la partie.');
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            showError('Une erreur est survenue. Veuillez réessayer.');
        });
    }
    
    // Mettre la partie en pause
    function pauseGame() {
        fetch('plateau/actions/admin_action.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: `action=pause&partie_id=${partieId}&csrf_token=${csrfToken}`
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Erreur réseau');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                // Mettre à jour l'état de la partie
                gameState.status = 'pause';
                
                // Arrêter les mises à jour automatiques
                stopGameUpdates();
                
                // Mettre à jour l'interface
                updateGameState();
            } else {
                showError(data.message || 'Erreur lors de la mise en pause de la partie.');
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            showError('Une erreur est survenue. Veuillez réessayer.');
        });
    }
    
    // Reprendre la partie
    function resumeGame() {
        fetch('plateau/actions/admin_action.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: `action=resume&partie_id=${partieId}&csrf_token=${csrfToken}`
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Erreur réseau');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                // Mettre à jour l'état de la partie
                gameState.status = 'en_cours';
                
                // Démarrer les mises à jour automatiques
                startGameUpdates();
                
                // Mettre à jour l'interface
                updateGameState();
            } else {
                showError(data.message || 'Erreur lors de la reprise de la partie.');
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            showError('Une erreur est survenue. Veuillez réessayer.');
        });
    }
    
    // Annuler la partie
    function cancelGame() {
        if (confirm('Êtes-vous sûr de vouloir annuler cette partie ? Cette action est irréversible.')) {
            fetch('plateau/actions/admin_action.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: `action=cancel&partie_id=${partieId}&csrf_token=${csrfToken}`
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Erreur réseau');
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    // Rediriger vers le tableau de bord
                    window.location.href = 'dashboard.php';
                } else {
                    showError(data.message || 'Erreur lors de l\'annulation de la partie.');
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                showError('Une erreur est survenue. Veuillez réessayer.');
            });
        }
    }
    
    // Passer au niveau suivant
    function startNextLevel() {
        fetch('plateau/actions/admin_action.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: `action=next_level&partie_id=${partieId}&csrf_token=${csrfToken}`
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Erreur réseau');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                // Effet sonore
                playSound('level_up');
                
                // Cacher le modal de fin de partie
                hideGameOverModal();
                
                // Mettre à jour l'état de la partie
                gameState.niveau++;
                gameState.status = 'en_cours';
                
                // Mettre à jour l'interface
                updateGameState();
                
                // Démarrer les mises à jour automatiques
                startGameUpdates();
                
                // Animer les nouvelles cartes
                setTimeout(() => {
                    animateCards();
                    enableCardHover();
                }, 1000);
            } else {
                showError(data.message || 'Erreur lors du passage au niveau suivant.');
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            showError('Une erreur est survenue. Veuillez réessayer.');
        });
    }
    
    // Mettre à jour l'état du jeu
    function updateGameState() {
        fetch('plateau/actions/get_game_state.php?partie_id=' + partieId + '&csrf_token=' + csrfToken)
        .then(response => {
            if (!response.ok) {
                throw new Error('Erreur réseau');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                // Mettre à jour l'état local du jeu
                gameState = {
                    niveau: data.niveau,
                    vies: data.vies,
                    shurikens: data.shurikens,
                    status: data.status
                };
                
                // Mettre à jour l'affichage du niveau
                niveauEl.textContent = data.niveau;
                
                // Mettre à jour l'affichage des vies
                viesEl.innerHTML = '';
                for (let i = 0; i < data.vies; i++) {
                    viesEl.innerHTML += '<span class="life-icon"></span>';
                }
                
                // Si moins de vies que le maximum, ajouter des cœurs vides
                const viesMax = 3; // Valeur par défaut
                for (let i = data.vies; i < viesMax; i++) {
                    viesEl.innerHTML += '<span class="life-icon life-lost"></span>';
                }
                
                // Mettre à jour l'affichage des shurikens
                shurikensEl.innerHTML = '';
                for (let i = 0; i < data.shurikens; i++) {
                    shurikensEl.innerHTML += '<span class="shuriken-icon"></span>';
                }
                
                // Mettre à jour les cartes du joueur
                if (data.cartes && data.cartes.length > 0) {
                    updatePlayerCards(data.cartes);
                }
                
                // Mettre à jour les cartes jouées
                if (data.cartesJouees && data.cartesJouees.length > 0) {
                    updatePlayedCards(data.cartesJouees);
                }
                
                // Mettre à jour la liste des joueurs
                if (data.joueurs) {
                    updatePlayersList(data.joueurs);
                }
                
                // Vérifier si la partie est terminée ou si le niveau est terminé
                if (data.status === 'terminee' || data.status === 'gagnee') {
                    stopGameUpdates();
                    showGameOverModal(data.status === 'gagnee');
                } else if (data.status === 'niveau_termine') {
                    stopGameUpdates();
                    showLevelCompleteModal(data.infoNiveau);
                }
            } else {
                console.error('Erreur lors de la récupération de l\'état du jeu:', data.message);
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
        });
    }
    
    // Mettre à jour les cartes du joueur
    function updatePlayerCards(cartes) {
        // Vérifier s'il y a eu des changements
        const currentCards = Array.from(playerCards.querySelectorAll('.card')).map(card => card.getAttribute('data-id'));
        const newCardIds = cartes.map(carte => carte.id.toString());
        
        // Si les cartes sont identiques, ne rien faire
        if (JSON.stringify(currentCards.sort()) === JSON.stringify(newCardIds.sort())) {
            return;
        }
        
        // Vider la zone des cartes du joueur
        playerCards.innerHTML = '';
        
        // Ajouter les nouvelles cartes
        if (cartes.length > 0) {
            cartes.forEach(carte => {
                const cardElement = document.createElement('div');
                cardElement.className = 'card';
                cardElement.setAttribute('data-id', carte.id);
                cardElement.setAttribute('data-value', carte.valeur);
                
                cardElement.innerHTML = `
                    <div class="card-inner">
                        <div class="card-front">
                            <div class="card-value">${carte.valeur}</div>
                        </div>
                        <div class="card-back">
                            <div class="card-logo">
                                <div class="mind-eye">👁️</div>
                            </div>
                        </div>
                    </div>
                `;
                
                playerCards.appendChild(cardElement);
            });
            
            // Réactiver l'animation de survol pour les nouvelles cartes
            enableCardHover();
        } else {
            playerCards.innerHTML = '<div class="no-cards-message">Toutes les cartes ont été jouées !</div>';
        }
        
        // Réinitialiser la carte sélectionnée
        selectedCard = null;
    }
    
    // Mettre à jour les cartes jouées
    function updatePlayedCards(cartesJouees) {
        // Vérifier s'il y a eu des changements
        const currentCardIds = Array.from(playedCards.querySelectorAll('.played-card'))
            .map(card => card.getAttribute('data-id'))
            .filter(id => id); // Filtrer les valeurs null/undefined
        
        const newCardIds = cartesJouees.map(carte => carte.id.toString());
        
        // Si les cartes sont identiques, ne rien faire
        if (currentCardIds.length > 0 && JSON.stringify(currentCardIds.slice(0, newCardIds.length).sort()) === JSON.stringify(newCardIds.sort())) {
            return;
        }
        
        // Vider la zone des cartes jouées
        playedCards.innerHTML = '';
        
        // Ajouter les nouvelles cartes jouées
        if (cartesJouees.length > 0) {
            cartesJouees.forEach((carte, index) => {
                const cardElement = document.createElement('div');
                cardElement.className = 'played-card';
                cardElement.setAttribute('data-id', carte.id);
                
                // Ajouter une classe pour animer l'entrée des nouvelles cartes
                if (index === 0 && !currentCardIds.includes(carte.id.toString())) {
                    cardElement.classList.add('new-card');
                }
                
                cardElement.innerHTML = `
                    <div class="played-card-value">${carte.valeur}</div>
                    <div class="played-card-info">jouée par ${carte.joueur_nom}</div>
                `;
                
                playedCards.appendChild(cardElement);
            });
        } else {
            playedCards.innerHTML = '<div class="no-cards-played">Aucune carte jouée</div>';
        }
    }
    
    // Mettre à jour la liste des joueurs
    function updatePlayersList(joueurs) {
        // Vider la zone des joueurs
        playersList.innerHTML = '';
        
        // Ajouter les joueurs
        joueurs.forEach(joueur => {
            const playerElement = document.createElement('div');
            playerElement.className = `player-item ${joueur.id == userId ? 'current-player' : ''}`;
            
            playerElement.innerHTML = `
                <div class="player-avatar">${joueur.avatar}</div>
                <div class="player-name">${joueur.identifiant}</div>
                ${joueur.position == 1 ? '<div class="player-badge admin-badge">👑</div>' : ''}
                <div class="player-cards-count">${joueur.cartes_en_main} cartes</div>
            `;
            
            playersList.appendChild(playerElement);
        });
    }
    
    // Démarrer les mises à jour automatiques du jeu
    function startGameUpdates() {
        // Arrêter les mises à jour en cours si nécessaire
        stopGameUpdates();
        
        // Mettre à jour immédiatement
        updateGameState();
        
        // Démarrer les mises à jour périodiques
        gameInterval = setInterval(updateGameState, 3000);
    }
    
    // Arrêter les mises à jour automatiques du jeu
    function stopGameUpdates() {
        if (gameInterval) {
            clearInterval(gameInterval);
            gameInterval = null;
        }
    }
    
    // Fonctions pour les modals
    
    // Afficher le modal de fin de partie
    function showGameOverModal(isWin) {
        gameResultMessage.innerHTML = isWin ? 
            '<h3>Félicitations !</h3><p>Vous avez terminé tous les niveaux avec succès !</p>' : 
            '<h3>Partie terminée</h3><p>Vous avez perdu toutes vos vies. Essayez encore !</p>';
        
        // Afficher ou masquer le bouton de niveau suivant selon le résultat
        if (nextLevelBtn) {
            nextLevelBtn.style.display = isWin ? 'none' : 'none';  // Toujours masqué en fin de partie
        }
        
        // Effet sonore
        playSound(isWin ? 'win' : 'lose');
        
        gameOverModal.style.display = 'block';
    }
    
    // Afficher le modal de niveau terminé
    function showLevelCompleteModal(info) {
        if (!info) return;
        
        gameResultMessage.innerHTML = `
            <h3>Niveau ${info.niveau_termine} terminé !</h3>
            <p>Vous pouvez maintenant passer au niveau ${info.prochain_niveau}.</p>
            ${info.prochain_niveau == 3 || info.prochain_niveau == 6 || info.prochain_niveau == 9 ? 
                '<p><strong>Bonus : +1 vie !</strong></p>' : ''}
            ${info.prochain_niveau == 2 || info.prochain_niveau == 5 || info.prochain_niveau == 8 ? 
                '<p><strong>Bonus : +1 shuriken !</strong></p>' : ''}
        `;
        
        // Afficher le bouton de niveau suivant
        if (nextLevelBtn) {
            nextLevelBtn.style.display = 'block';
        }
        
        // Effet sonore
        playSound('level_complete');
        
        gameOverModal.style.display = 'block';
    }
    
    // Cacher le modal de fin de partie
    function hideGameOverModal() {
        gameOverModal.style.display = 'none';
    }
    
    // Afficher le modal de shuriken
    function showShurikenModal() {
        shurikenModal.style.display = 'block';
    }
    
    // Cacher le modal de shuriken
    function hideShurikenModal() {
        shurikenModal.style.display = 'none';
    }
    
    // Afficher un message d'erreur
    function showError(message) {
        errorMessage.textContent = message;
        errorModal.style.display = 'block';
    }
    
    // Cacher le message d'erreur
    function hideErrorModal() {
        errorModal.style.display = 'none';
    }
    
    // Fonction pour jouer un son
    function playSound(soundType) {
        // Créer un élément audio
        const audio = document.createElement('audio');
        
        // Définir la source du son en fonction du type
        switch(soundType) {
            case 'select':
                audio.src = 'sounds/card_select.mp3';
                break;
            case 'play_card':
                audio.src = 'sounds/card_play.mp3';
                break;
            case 'error':
                audio.src = 'sounds/error.mp3';
                break;
            case 'shuriken':
                audio.src = 'sounds/shuriken.mp3';
                break;
            case 'start_game':
                audio.src = 'sounds/game_start.mp3';
                break;
            case 'level_up':
                audio.src = 'sounds/level_up.mp3';
                break;
            case 'level_complete':
                audio.src = 'sounds/level_complete.mp3';
                break;
            case 'win':
                audio.src = 'sounds/win.mp3';
                break;
            case 'lose':
                audio.src = 'sounds/lose.mp3';
                break;
            default:
                return; // Sortir si le type de son n'est pas reconnu
        }
        
        // Vérifier si le son existe
        audio.onerror = function() {
            console.log('Son non disponible: ' + soundType);
        };
        
        // Jouer le son
        audio.volume = 0.5; // Volume à 50%
        audio.play();
    }
    
    // Gestion de la visibilité de la page (arrêter/démarrer les mises à jour)
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            stopGameUpdates();
        } else if (gameState.status === 'en_cours') {
            startGameUpdates();
        }
    });
});