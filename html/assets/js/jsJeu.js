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
                const x = e.clientX - rect.left;
                const y = e.clientY - rect.top;
                
                // Calculer les angles de rotation en fonction de la position du curseur
                const rotateY = ((x / rect.width) - 0.5) * 20;
                const rotateX = ((y / rect.height) - 0.5) * -20;
                
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
        setTimeout(animateCards, 500);
        setTimeout(enableCardHover, 1000);
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
            console.log('Bouton jouer carte cliqué'); // Debug
            console.log('Carte sélectionnée:', selectedCard); // Debug
            console.log('Statut de la partie:', gameState.status); // Debug
            
            if (selectedCard && gameState.status === 'en_cours') {
                const cardId = selectedCard.getAttribute('data-id');
                const cardValue = selectedCard.getAttribute('data-value');
                
                console.log('ID carte:', cardId, 'Valeur carte:', cardValue); // Debug
                
                if (cardId && cardValue) {
                    playCard(cardId, cardValue);
                } else {
                    console.error('ID ou valeur de carte manquante');
                    showError('Erreur: Données de carte invalides.');
                }
            } else if (!selectedCard) {
                showError('Veuillez sélectionner une carte à jouer.');
            } else {
                showError('La partie n\'est pas en cours.');
            }
        });
    }
    
    // Utiliser un shuriken
    if (useShurikenBtn) {
        useShurikenBtn.addEventListener('click', function() {
            console.log('Bouton shuriken cliqué'); // Debug
            console.log('Shurikens disponibles:', gameState.shurikens); // Debug
            
            if (gameState.shurikens > 0 && gameState.status === 'en_cours') {
                showShurikenModal();
            } else if (gameState.shurikens <= 0) {
                showError('Vous n\'avez plus de shurikens disponibles.');
            } else {
                showError('La partie n\'est pas en cours.');
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
            window.location.href = '../dashboard.php';
        });
    }
    
    if (nextLevelBtn && estAdmin) {
        nextLevelBtn.addEventListener('click', startNextLevel);
    }
    
    // Fonctions
    
    // Jouer une carte
    function playCard(cardId, cardValue) {
        console.log('Tentative de jeu de carte:', cardId, cardValue); // Debug
        
        // Effet sonore
        playSound('play_card');
        
        // Construire l'URL avec le chemin correct
        const url = '../plateau/actions/play_card.php';
        
        fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: `card_id=${encodeURIComponent(cardId)}&partie_id=${encodeURIComponent(partieId)}&csrf_token=${encodeURIComponent(csrfToken)}`
        })
        .then(response => {
            console.log('Réponse reçue:', response); // Debug
            if (!response.ok) {
                throw new Error('Erreur réseau: ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            console.log('Données reçues:', data); // Debug
            if (data.success) {
                // Effet visuel pour la carte jouée
                if (selectedCard) {
                    selectedCard.style.transition = 'all 0.5s ease-out';
                    selectedCard.style.transform = 'translateY(-100px) scale(0.5)';
                    selectedCard.style.opacity = '0';
                    setTimeout(() => {
                        if (selectedCard && selectedCard.parentNode) {
                            selectedCard.parentNode.removeChild(selectedCard);
                        }
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
                console.error('Erreur du serveur:', data.message); // Debug
                showError(data.message || 'Erreur lors du jeu de la carte.');
            }
        })
        .catch(error => {
            console.error('Erreur fetch:', error); // Debug
            showError('Une erreur est survenue. Veuillez réessayer.');
        });
    }
    
    // Utiliser un shuriken
    function useShuriken() {
        console.log('Tentative d\'utilisation de shuriken'); // Debug
        
        // Effet sonore
        playSound('shuriken');
        
        // Construire l'URL avec le chemin correct
        const url = '../plateau/actions/use_shuriken.php';
        
        fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: `partie_id=${encodeURIComponent(partieId)}&csrf_token=${encodeURIComponent(csrfToken)}&action_type=request`
        })
        .then(response => {
            console.log('Réponse shuriken reçue:', response); // Debug
            if (!response.ok) {
                throw new Error('Erreur réseau: ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            console.log('Données shuriken reçues:', data); // Debug
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
                console.error('Erreur shuriken du serveur:', data.message); // Debug
                showError(data.message || 'Erreur lors de l\'utilisation du shuriken.');
            }
        })
        .catch(error => {
            console.error('Erreur fetch shuriken:', error); // Debug
            showError('Une erreur est survenue. Veuillez réessayer.');
        });
    }
    
    // Fonctions d'administration
    
    // Démarrer la partie
    function startGame() {
        const url = '../plateau/actions/admin_action.php';
        
        fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: `action=start&partie_id=${encodeURIComponent(partieId)}&csrf_token=${encodeURIComponent(csrfToken)}`
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
        const url = '../plateau/actions/admin_action.php';
        
        fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: `action=pause&partie_id=${encodeURIComponent(partieId)}&csrf_token=${encodeURIComponent(csrfToken)}`
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
        const url = '../plateau/actions/admin_action.php';
        
        fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: `action=resume&partie_id=${encodeURIComponent(partieId)}&csrf_token=${encodeURIComponent(csrfToken)}`
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
            const url = '../plateau/actions/admin_action.php';
            
            fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: `action=cancel&partie_id=${encodeURIComponent(partieId)}&csrf_token=${encodeURIComponent(csrfToken)}`
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
                    window.location.href = '../dashboard.php';
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
        const url = '../plateau/actions/admin_action.php';
        
        fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: `action=next_level&partie_id=${encodeURIComponent(partieId)}&csrf_token=${encodeURIComponent(csrfToken)}`
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
        const url = '../plateau/actions/get_game_state.php';
        
        fetch(`${url}?partie_id=${encodeURIComponent(partieId)}&csrf_token=${encodeURIComponent(csrfToken)}`)
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
                if (niveauEl) niveauEl.textContent = data.niveau;
                
                // Mettre à jour l'affichage des vies
                if (viesEl) {
                    viesEl.innerHTML = '';
                    for (let i = 0; i < data.vies; i++) {
                        viesEl.innerHTML += '<span class="life-icon"></span>';
                    }
                    
                    // Si moins de vies que le maximum, ajouter des cœurs vides
                    const viesMax = 3; // Valeur par défaut
                    for (let i = data.vies; i < viesMax; i++) {
                        viesEl.innerHTML += '<span class="life-icon life-lost"></span>';
                    }
                }
                
                // Mettre à jour l'affichage des shurikens
                if (shurikensEl) {
                    shurikensEl.innerHTML = '';
                    for (let i = 0; i < data.shurikens; i++) {
                        shurikensEl.innerHTML += '<span class="shuriken-icon"></span>';
                    }
                }
                
                // Mettre à jour les cartes du joueur
                if (data.cartes && data.cartes.length >= 0) {
                    updatePlayerCards(data.cartes);
                }
                
                // Mettre à jour les cartes jouées
                if (data.cartesJouees && data.cartesJouees.length >= 0) {
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
        if (!playerCards) return;
        
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
        if (!playedCards) return;
        
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
        if (!playersList) return;
        
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
        if (!gameOverModal || !gameResultMessage) return;
        
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
        if (!info || !gameOverModal || !gameResultMessage) return;
        
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
        if (gameOverModal) {
            gameOverModal.style.display = 'none';
        }
    }
    
    // Afficher le modal de shuriken
    function showShurikenModal() {
        if (shurikenModal) {
            shurikenModal.style.display = 'block';
        }
    }
    
    // Cacher le modal de shuriken
    function hideShurikenModal() {
        if (shurikenModal) {
            shurikenModal.style.display = 'none';
        }
    }
    
    // Afficher un message d'erreur
    function showError(message) {
        if (errorMessage && errorModal) {
            errorMessage.textContent = message;
            errorModal.style.display = 'block';
        } else {
            // Fallback si les modals n'existent pas
            alert(message);
        }
    }
    
    // Cacher le message d'erreur
    function hideErrorModal() {
        if (errorModal) {
            errorModal.style.display = 'none';
        }
    }
    
    // Fonction pour jouer un son
    function playSound(soundType) {
        // Créer un élément audio
        const audio = document.createElement('audio');
        
        // Définir la source du son en fonction du type
        switch(soundType) {
            case 'select':
                audio.src = '../assets/sounds/card_select.mp3';
                break;
            case 'play_card':
                audio.src = '../assets/sounds/card_play.mp3';
                break;
            case 'error':
                audio.src = '../assets/sounds/error.mp3';
                break;
            case 'shuriken':
                audio.src = '../assets/sounds/shuriken.mp3';
                break;
            case 'start_game':
                audio.src = '../assets/sounds/game_start.mp3';
                break;
            case 'level_up':
                audio.src = '../assets/sounds/level_up.mp3';
                break;
            case 'level_complete':
                audio.src = '../assets/sounds/level_complete.mp3';
                break;
            case 'win':
                audio.src = '../assets/sounds/win.mp3';
                break;
            case 'lose':
                audio.src = '../assets/sounds/lose.mp3';
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
        audio.play().catch(e => console.log('Impossible de jouer le son:', e));
    }
    
    // Gestion de la visibilité de la page (arrêter/démarrer les mises à jour)
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            stopGameUpdates();
        } else if (gameState.status === 'en_cours') {
            startGameUpdates();
        }
    });
    
    // Debug : afficher l'état initial
    console.log('Jeu initialisé avec:', {
        partieId,
        userId,
        partieStatus,
        estAdmin,
        csrfToken: csrfToken ? 'présent' : 'absent'
    });
});