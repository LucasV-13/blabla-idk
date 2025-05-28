<div class="form-group">
                                <label for="quick_game_name" class="form-label">Nom de la partie</label>
                                <input type="text" id="quick_game_name" name="game_name" class="form-input" required 
                                       placeholder="Ma super partie">
                            </div>
                            
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="quick_player_count" class="form-label">Joueurs</label>
                                    <select id="quick_player_count" name="player_count" class="form-select">
                                        <option value="2">2 joueurs</option>
                                        <option value="3">3 joueurs</option>
                                        <option value="4">4 joueurs</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="quick_difficulty" class="form-label">Difficulté</label>
                                    <select id="quick_difficulty" name="difficulty" class="form-select">
                                        <option value="facile">🟢 Facile</option>
                                        <option value="moyen" selected>🟡 Moyen</option>
                                        <option value="difficile">🔴 Difficile</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">
                                    <input type="checkbox" name="is_private" style="margin-right: 0.5rem;">
                                    Partie privée
                                </label>
                            </div>
                            
                            <button type="submit" class="btn btn-primary btn-block">
                                🎮 Créer la partie
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Activité -->
            <div id="activity" class="section">
                <div class="section-header">
                    <h1 class="section-title">
                        📈 Activité et logs
                    </h1>
                    <button class="btn btn-outline" onclick="refreshActivity()">
                        🔄 Actualiser
                    </button>
                </div>
                
                <div class="activity-feed">
                    <?php if (!empty($recentActivity)): ?>
                    <?php foreach ($recentActivity as $activity): ?>
                    <div class="activity-item">
                        <div class="activity-icon">
                            <?= $activity['type'] === 'user_created' ? '👤' : '🎮' ?>
                        </div>
                        <div class="activity-content">
                            <div class="activity-title">
                                <?php if ($activity['type'] === 'user_created'): ?>
                                    Nouvel utilisateur créé : <strong><?= htmlspecialchars($activity['details']) ?></strong>
                                <?php else: ?>
                                    Nouvelle partie créée : <strong><?= htmlspecialchars($activity['details']) ?></strong>
                                <?php endif; ?>
                            </div>
                            <div class="activity-time">
                                <?= date('d/m/Y H:i:s', strtotime($activity['date_action'])) ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">📋</div>
                        <h3>Aucune activité récente</h3>
                        <p>Les nouvelles activités apparaîtront ici</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Système -->
            <div id="system" class="section">
                <div class="section-header">
                    <h1 class="section-title">
                        ⚙️ Informations système
                    </h1>
                    <button class="btn btn-outline" onclick="refreshSystemStats()">
                        🔄 Actualiser
                    </button>
                </div>
                
                <div class="system-info">
                    <div class="system-info-item">
                        <div class="system-info-label">Base de données</div>
                        <div class="system-info-value">
                            <?= formatBytes(($systemStats['db_size_mb'] ?? 0) * 1024 * 1024) ?>
                        </div>
                    </div>
                    
                    <div class="system-info-item">
                        <div class="system-info-label">Connexions actives</div>
                        <div class="system-info-value">
                            <?= number_format($systemStats['active_connections'] ?? 0) ?>
                        </div>
                    </div>
                    
                    <div class="system-info-item">
                        <div class="system-info-label">Uptime serveur</div>
                        <div class="system-info-value">
                            <?= formatUptime($systemStats['server_uptime'] ?? 0) ?>
                        </div>
                    </div>
                    
                    <div class="system-info-item">
                        <div class="system-info-label">Version PHP</div>
                        <div class="system-info-value">
                            <?= PHP_VERSION ?>
                        </div>
                    </div>
                </div>
                
                <div class="card" style="margin-top: 2rem;">
                    <div class="card-header">
                        <h3 class="card-title">🛠️ Outils de maintenance</h3>
                    </div>
                    
                    <div class="quick-actions">
                        <button class="btn btn-warning" onclick="cleanupExpiredSessions()">
                            🧹 Nettoyer les sessions expirées
                        </button>
                        <button class="btn btn-warning" onclick="optimizeDatabase()">
                            ⚡ Optimiser la base de données
                        </button>
                        <button class="btn btn-outline" onclick="exportLogs()">
                            📋 Exporter les logs
                        </button>
                        <button class="btn btn-outline" onclick="generateBackup()">
                            💾 Sauvegarde
                        </button>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <!-- Modals -->
    
    <!-- Modal création/édition utilisateur -->
    <div id="userModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="userModalTitle">Nouvel utilisateur</h2>
                <button class="modal-close" onclick="closeModal('userModal')">&times;</button>
            </div>
            
            <form id="userForm">
                <input type="hidden" id="editUserId" name="user_id">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="modalUsername" class="form-label">Nom d'utilisateur *</label>
                        <input type="text" id="modalUsername" name="username" class="form-input" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="modalEmail" class="form-label">Email *</label>
                        <input type="email" id="modalEmail" name="email" class="form-input" required>
                    </div>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="modalPassword" class="form-label">Mot de passe</label>
                        <input type="password" id="modalPassword" name="password" class="form-input">
                        <small class="form-help">Laisser vide pour ne pas modifier</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="modalRole" class="form-label">Rôle *</label>
                        <select id="modalRole" name="role_id" class="form-select" required>
                            <option value="">Sélectionner un rôle</option>
                            <?php foreach ($roles as $role): ?>
                            <option value="<?= $role['id'] ?>"><?= htmlspecialchars(ucfirst($role['nom'])) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="modalAvatar" class="form-label">Avatar</label>
                    <input type="text" id="modalAvatar" name="avatar" class="form-input" value="👤">
                </div>
                
                <div class="form-actions" style="margin-top: 2rem; display: flex; gap: 1rem; justify-content: flex-end;">
                    <button type="button" class="btn btn-outline" onclick="closeModal('userModal')">
                        Annuler
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <span id="userSubmitText">Créer</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Modal création/édition partie -->
    <div id="gameModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Nouvelle partie</h2>
                <button class="modal-close" onclick="closeModal('gameModal')">&times;</button>
            </div>
            
            <form id="gameForm">
                <div class="form-group">
                    <label for="modalGameName" class="form-label">Nom de la partie *</label>
                    <input type="text" id="modalGameName" name="game_name" class="form-input" required 
                           placeholder="Entrez un nom pour la partie">
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="modalPlayerCount" class="form-label">Nombre de joueurs</label>
                        <select id="modalPlayerCount" name="player_count" class="form-select">
                            <option value="2">2 joueurs</option>
                            <option value="3">3 joueurs</option>
                            <option value="4">4 joueurs</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="modalDifficulty" class="form-label">Difficulté</label>
                        <select id="modalDifficulty" name="difficulty" class="form-select">
                            <option value="facile">🟢 Facile</option>
                            <option value="moyen" selected>🟡 Moyen</option>
                            <option value="difficile">🔴 Difficile</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="modalStartingLevel" class="form-label">Niveau de départ</label>
                        <select id="modalStartingLevel" name="starting_level" class="form-select">
                            <?php for ($i = 1; $i <= 12; $i++): ?>
                            <option value="<?= $i ?>" <?= $i === 1 ? 'selected' : '' ?>>Niveau <?= $i ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            <input type="checkbox" id="modalIsPrivate" name="is_private" style="margin-right: 0.5rem;">
                            Partie privée
                        </label>
                    </div>
                </div>
                
                <div class="form-actions" style="margin-top: 2rem; display: flex; gap: 1rem; justify-content: flex-end;">
                    <button type="button" class="btn btn-outline" onclick="closeModal('gameModal')">
                        Annuler
                    </button>
                    <button type="submit" class="btn btn-primary">
                        Créer la partie
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Modal détails utilisateur -->
    <div id="userDetailsModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Détails utilisateur</h2>
                <button class="modal-close" onclick="closeModal('userDetailsModal')">&times;</button>
            </div>
            
            <div id="userDetailsContent">
                <div class="loading">Chargement...</div>
            </div>
        </div>
    </div>
    
    <!-- Modal détails partie -->
    <div id="gameDetailsModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Détails partie</h2>
                <button class="modal-close" onclick="closeModal('gameDetailsModal')">&times;</button>
            </div>
            
            <div id="gameDetailsContent">
                <div class="loading">Chargement...</div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="<?= ASSETS_URL ?>js/main.js"></script>
    
    <!-- Scripts spécifiques -->
    <?php foreach ($jsFiles as $jsFile): ?>
    <script src="<?= ASSETS_URL ?>js/<?= $jsFile ?>.js"></script>
    <?php endforeach; ?>
    
    <!-- JavaScript Admin Panel -->
    <script>
        // Configuration globale pour cette page
        window.TheMind.config.userId = <?= json_encode($adminId) ?>;
        window.TheMind.config.isAdmin = true;
        window.TheMind.config.pageType = 'admin';
        
        // Module Admin Panel
        window.TheMind.AdminPanel = {
            init: function() {
                this.bindEvents();
                this.initNavigation();
                this.initForms();
                console.log('Admin Panel initialized');
            },
            
            bindEvents: function() {
                // Navigation
                document.querySelectorAll('.nav-link').forEach(link => {
                    link.addEventListener('click', this.handleNavigation.bind(this));
                });
                
                // Formulaires de création rapide
                const quickUserForm = document.getElementById('quickCreateUserForm');
                if (quickUserForm) {
                    quickUserForm.addEventListener('submit', this.handleQuickUserCreate.bind(this));
                }
                
                const quickGameForm = document.getElementById('quickCreateGameForm');
                if (quickGameForm) {
                    quickGameForm.addEventListener('submit', this.handleQuickGameCreate.bind(this));
                }
                
                // Modals
                const userForm = document.getElementById('userForm');
                if (userForm) {
                    userForm.addEventListener('submit', this.handleUserFormSubmit.bind(this));
                }
                
                const gameForm = document.getElementById('gameForm');
                if (gameForm) {
                    gameForm.addEventListener('submit', this.handleGameFormSubmit.bind(this));
                }
            },
            
            initNavigation: function() {
                // Gérer les hash URLs
                const hash = window.location.hash.substring(1);
                if (hash) {
                    this.showSection(hash);
                }
                
                window.addEventListener('hashchange', () => {
                    const newHash = window.location.hash.substring(1);
                    if (newHash) {
                        this.showSection(newHash);
                    }
                });
            },
            
            initForms: function() {
                // Auto-génération de mots de passe
                document.querySelectorAll('input[name="password"]').forEach(input => {
                    const generateBtn = document.createElement('button');
                    generateBtn.type = 'button';
                    generateBtn.className = 'btn btn-outline btn-sm';
                    generateBtn.textContent = '🎲 Générer';
                    generateBtn.style.marginTop = '0.5rem';
                    
                    generateBtn.addEventListener('click', () => {
                        input.value = this.generatePassword();
                    });
                    
                    input.parentNode.appendChild(generateBtn);
                });
            },
            
            handleNavigation: function(event) {
                event.preventDefault();
                const link = event.currentTarget;
                const section = link.dataset.section;
                
                this.showSection(section);
                window.location.hash = section;
            },
            
            showSection: function(sectionId) {
                // Cacher toutes les sections
                document.querySelectorAll('.section').forEach(section => {
                    section.classList.remove('active');
                });
                
                // Désactiver tous les liens nav
                document.querySelectorAll('.nav-link').forEach(link => {
                    link.classList.remove('active');
                });
                
                // Afficher la section demandée
                const targetSection = document.getElementById(sectionId);
                if (targetSection) {
                    targetSection.classList.add('active');
                }
                
                // Activer le lien nav correspondant
                const targetLink = document.querySelector(`[data-section="${sectionId}"]`);
                if (targetLink) {
                    targetLink.classList.add('active');
                }
            },
            
            handleQuickUserCreate: function(event) {
                event.preventDefault();
                const formData = new FormData(event.target);
                formData.append('csrf_token', window.TheMind.config.csrfToken);
                formData.append('ajax_action', 'create_user');
                formData.append('avatar', '👤');
                
                this.submitForm(formData, 'Utilisateur créé avec succès!')
                    .then(() => {
                        event.target.reset();
                        this.refreshUsers();
                    });
            },
            
            handleQuickGameCreate: function(event) {
                event.preventDefault();
                const formData = new FormData(event.target);
                formData.append('csrf_token', window.TheMind.config.csrfToken);
                formData.append('ajax_action', 'create_game');
                
                this.submitForm(formData, 'Partie créée avec succès!')
                    .then(() => {
                        event.target.reset();
                        this.refreshGames();
                    });
            },
            
            handleUserFormSubmit: function(event) {
                event.preventDefault();
                const formData = new FormData(event.target);
                formData.append('csrf_token', window.TheMind.config.csrfToken);
                
                const isEdit = formData.get('user_id');
                formData.append('ajax_action', isEdit ? 'update_user' : 'create_user');
                
                this.submitForm(formData, isEdit ? 'Utilisateur modifié!' : 'Utilisateur créé!')
                    .then(() => {
                        closeModal('userModal');
                        this.refreshUsers();
                    });
            },
            
            handleGameFormSubmit: function(event) {
                event.preventDefault();
                const formData = new FormData(event.target);
                formData.append('csrf_token', window.TheMind.config.csrfToken);
                formData.append('ajax_action', 'create_game');
                
                this.submitForm(formData, 'Partie créée avec succès!')
                    .then(() => {
                        closeModal('gameModal');
                        this.refreshGames();
                    });
            },
            
            submitForm: function(formData, successMessage) {
                return fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        this.showNotification(successMessage, 'success');
                        return data;
                    } else {
                        throw new Error(data.message || 'Erreur inconnue');
                    }
                })
                .catch(error => {
                    this.showNotification(error.message, 'error');
                    throw error;
                });
            },
            
            refreshUsers: function() {
                // Recharger la section utilisateurs
                window.location.reload();
            },
            
            refreshGames: function() {
                // Recharger la section parties
                window.location.reload();
            },
            
            generatePassword: function() {
                const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*';
                let result = '';
                for (let i = 0; i < 12; i++) {
                    result += chars.charAt(Math.floor(Math.random() * chars.length));
                }
                return result;
            },
            
            showNotification: function(message, type = 'info') {
                if (window.TheMind.Profile && window.TheMind.Profile.showNotification) {
                    window.TheMind.Profile.showNotification(message, type);
                } else {
                    alert(message);
                }
            }
        };
        
        // Fonctions globales pour les actions
        function openCreateUserModal() {
            document.getElementById('userModalTitle').textContent = 'Nouvel utilisateur';
            document.getElementById('userSubmitText').textContent = 'Créer';
            document.getElementById('userForm').reset();
            document.getElementById('editUserId').value = '';
            document.getElementById('modalPassword').required = true;
            openModal('userModal');
        }
        
        function openCreateGameModal() {
            document.getElementById('gameForm').reset();
            openModal('gameModal');
        }
        
        function editUser(userId) {
            const formData = new FormData();
            formData.append('csrf_token', window.TheMind.config.csrfToken);
            formData.append('ajax_action', 'get_user_details');
            formData.append('user_id', userId);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const user = data.user;
                    document.getElementById('userModalTitle').textContent = 'Modifier utilisateur';
                    document.getElementById('userSubmitText').textContent = 'Modifier';
                    document.getElementById('editUserId').value = user.id;
                    document.getElementById('modalUsername').value = user.identifiant;
                    document.getElementById('modalEmail').value = user.mail;
                    document.getElementById('modalRole').value = user.id_role;
                    document.getElementById('modalAvatar').value = user.avatar;
                    document.getElementById('modalPassword').required = false;
                    document.getElementById('modalPassword').value = '';
                    openModal('userModal');
                } else {
                    window.TheMind.AdminPanel.showNotification(data.message, 'error');
                }
            });
        }
        
        function deleteUser(userId, username) {
            if (!confirm(`Êtes-vous sûr de vouloir supprimer l'utilisateur "${username}" ?`)) {
                return;
            }
            
            const formData = new FormData();
            formData.append('csrf_token', window.TheMind.config.csrfToken);
            formData.append('ajax_action', 'delete_user');
            formData.append('user_id', userId);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.TheMind.AdminPanel.showNotification('Utilisateur supprimé', 'success');
                    window.TheMind.AdminPanel.refreshUsers();
                } else {
                    window.TheMind.AdminPanel.showNotification(data.message, 'error');
                }
            });
        }
        
        function viewUser(userId) {
            const formData = new FormData();
            formData.append('csrf_token', window.TheMind.config.csrfToken);
            formData.append('ajax_action', 'get_user_details');
            formData.append('user_id', userId);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const user = data.user;
                    document.getElementById('userDetailsContent').innerHTML = `
                        <div class="user-details">
                            <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 2rem;">
                                <div class="avatar-display" style="width: 80px; height: 80px; font-size: 2.5rem;">
                                    ${user.avatar}
                                </div>
                                <div>
                                    <h3 style="margin: 0;">${user.identifiant}</h3>
                                    <p style="margin: 0; color: var(--text-secondary);">${user.mail}</p>
                                    <span class="badge badge-secondary">${user.role_nom}</span>
                                </div>
                            </div>
                            
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                                <div class="stat-card">
                                    <div class="stat-value">${user.parties_jouees || 0}</div>
                                    <div class="stat-label">Parties jouées</div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-value">${user.parties_gagnees || 0}</div>
                                    <div class="stat-label">Parties gagnées</div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-value">${user.taux_reussite || 0}%</div>
                                    <div class="stat-label">Taux de réussite</div>
                                </div>
                            </div>
                            
                            <div style="margin-top: 2rem;">
                                <p><strong>Inscription :</strong> ${new Date(user.date_creation).toLocaleDateString()}</p>
                                <p><strong>Dernière connexion :</strong> ${user.derniere_connexion ? new Date(user.derniere_connexion).toLocaleDateString() : 'Jamais'}</p>
                            </div>
                        </div>
                    `;
                    openModal('userDetailsModal');
                } else {
                    window.TheMind.AdminPanel.showNotification(data.message, 'error');
                }
            });
        }
        
        function viewGame(gameId) {
            const formData = new FormData();
            formData.append('csrf_token', window.TheMind.config.csrfToken);
            formData.append('ajax_action', 'get_game_details');
            formData.append('game_id', gameId);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const game = data.game;
                    document.getElementById('gameDetailsContent').innerHTML = `
                        <div class="game-details">
                            <h3>${game.nom || 'Partie #' + game.id}</h3>
                            <p><strong>Statut :</strong> ${game.status}</p>
                            <p><strong>Niveau :</strong> ${game.niveau}</p>
                            <p><strong>Joueurs :</strong> ${game.joueurs_actuels}/${game.nombre_joueurs}</p>
                            <p><strong>Difficulté :</strong> ${game.difficulte}</p>
                            <p><strong>Vies restantes :</strong> ${game.vies_restantes}</p>
                            <p><strong>Shurikens restants :</strong> ${game.shurikens_restants}</p>
                            <p><strong>Créée le :</strong> ${new Date(game.date_creation).toLocaleDateString()}</p>
                            ${game.joueurs_noms ? '<p><strong>Joueurs :</strong> ' + game.joueurs_noms + '</p>' : ''}
                        </div>
                    `;
                    openModal('gameDetailsModal');
                } else {
                    window.TheMind.AdminPanel.showNotification(data.message, 'error');
                }
            });
        }
        
        function manageGame(gameId, action) {
            let confirmMessage = '';
            switch(action) {
                case 'start': confirmMessage = 'Démarrer cette partie ?'; break;
                case 'pause': confirmMessage = 'Mettre en pause cette partie ?'; break;
                case 'cancel': confirmMessage = 'Annuler définitivement cette partie ?'; break;
                default: return;
            }
            
            if (!confirm(confirmMessage)) return;
            
            const formData = new FormData();
            formData.append('csrf_token', window.TheMind.config.csrfToken);
            formData.append('ajax_action', 'manage_game');
            formData.append('game_id', gameId);
            formData.append('game_action', action);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.TheMind.AdminPanel.showNotification(data.message, 'success');
                    window.TheMind.AdminPanel.refreshGames();
                } else {
                    window.TheMind.AdminPanel.showNotification(data.message, 'error');
                }
            });
        }
        
        function exportData(type) {
            const formData = new FormData();
            formData.append('csrf_token', window.TheMind.config.csrfToken);
            formData.append('ajax_action', 'export_data');
            formData.append('export_type', type);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Créer et télécharger le fichier CSV
                    const csv = this.convertToCSV(data.data);
                    const blob = new Blob([csv], { type: 'text/csv' });
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = `${type}_export_${new Date().toISOString().split('T')[0]}.csv`;
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    window.URL.revokeObjectURL(url);
                    
                    window.TheMind.AdminPanel.showNotification('Export téléchargé', 'success');
                } else {
                    window.TheMind.AdminPanel.showNotification(data.message, 'error');
                }
            });
        }
        
        function convertToCSV(data) {
            if (!data || data.length === 0) return '';
            
            const headers = Object.keys(data[0]);
            const csvContent = [
                headers.join(','),
                ...data.map(row => headers.map(header => {
                    const value = row[header] || '';
                    return `"${value.toString().replace(/"/g, '""')}"`;
                }).join(','))
            ].join('\n');
            
            return csvContent;
        }
        
        function refreshOverview() {
            window.location.reload();
        }
        
        function refreshUsers() {
            window.TheMind.AdminPanel.refreshUsers();
        }
        
        function refreshGames() {
            window.TheMind.AdminPanel.refreshGames();
        }
        
        function refreshActivity() {
            window.location.reload();
        }
        
        function refreshSystemStats() {
            window.location.reload();
        }
        
        // Fonctions de maintenance
        function cleanupExpiredSessions() {
            if (!confirm('Nettoyer toutes les sessions expirées ?')) return;
            
            window.TheMind.AdminPanel.showNotification('Nettoyage des sessions en cours...', 'info');
            
            // Simulation de nettoyage
            setTimeout(() => {
                window.TheMind.AdminPanel.showNotification('Sessions expirées nettoyées', 'success');
            }, 2000);
        }
        
        function optimizeDatabase() {
            if (!confirm('Optimiser la base de données ? Cette opération peut prendre quelques minutes.')) return;
            
            window.TheMind.AdminPanel.showNotification('Optimisation en cours...', 'info');
            
            // Simulation d'optimisation
            setTimeout(() => {
                window.TheMind.AdminPanel.showNotification('Base de données optimisée', 'success');
            }, 3000);
        }
        
        function exportLogs() {
            window.TheMind.AdminPanel.showNotification('Export des logs en cours...', 'info');
            
            // Simulation d'export de logs
            setTimeout(() => {
                const logContent = `[${new Date().toISOString()}] Admin panel accessed by user ${window.TheMind.config.userId}\n`;
                const blob = new Blob([logContent], { type: 'text/plain' });
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `admin_logs_${new Date().toISOString().split('T')[0]}.txt`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                window.URL.revokeObjectURL(url);
                
                window.TheMind.AdminPanel.showNotification('Logs exportés', 'success');
            }, 1000);
        }
        
        function generateBackup() {
            if (!confirm('Générer une sauvegarde complète ?')) return;
            
            window.TheMind.AdminPanel.showNotification('Génération de la sauvegarde...', 'info');
            
            // Simulation de sauvegarde
            setTimeout(() => {
                window.TheMind.AdminPanel.showNotification('Sauvegarde générée avec succès', 'success');
            }, 5000);
        }
        
        // Fonctions utilitaires pour les modals
        function openModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.add('active');
                document.body.style.overflow = 'hidden';
            }
        }
        
        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.remove('active');
                document.body.style.overflow = 'auto';
            }
        }
        
        // Fermer les modals en cliquant à l'extérieur
        document.addEventListener('click', function(event) {
            if (event.target.classList.contains('modal-overlay')) {
                event.target.classList.remove('active');
                document.body.style.overflow = 'auto';
            }
        });
        
        // Raccourcis clavier
        document.addEventListener('keydown', function(event) {
            // Echap pour fermer les modals
            if (event.key === 'Escape') {
                document.querySelectorAll('.modal-overlay.active').forEach(modal => {
                    modal.classList.remove('active');
                });
                document.body.style.overflow = 'auto';
            }
            
            // Ctrl+N pour nouvel utilisateur
            if (event.ctrlKey && event.key === 'n') {
                event.preventDefault();
                openCreateUserModal();
            }
            
            // Ctrl+G pour nouvelle partie
            if (event.ctrlKey && event.key === 'g') {
                event.preventDefault();
                openCreateGameModal();
            }
        });
        
        // Initialisation
        document.addEventListener('DOMContentLoaded', function() {
            window.TheMind.AdminPanel.init();
            
            // Animation d'entrée pour les cartes de stats
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
            
            // Auto-actualisation des données (toutes les 30 secondes)
            setInterval(() => {
                if (document.querySelector('#overview.active')) {
                    // Actualiser seulement si on est sur la vue d'ensemble
                    fetch(window.location.href)
                        .then(response => response.text())
                        .then(html => {
                            // Extraire et mettre à jour les statistiques
                            const parser = new DOMParser();
                            const doc = parser.parseFromString(html, 'text/html');
                            const newStats = doc.querySelectorAll('.stat-value');
                            const currentStats = document.querySelectorAll('.stat-value');
                            
                            newStats.forEach((stat, index) => {
                                if (currentStats[index] && stat.textContent !== currentStats[index].textContent) {
                                    currentStats[index].style.color = 'var(--accent-color)';
                                    currentStats[index].textContent = stat.textContent;
                                    
                                    setTimeout(() => {
                                        currentStats[index].style.color = 'var(--primary-color)';
                                    }, 1000);
                                }
                            });
                        })
                        .catch(error => {
                            console.log('Erreur actualisation auto:', error);
                        });
                }
            }, 30000);
            
            console.log('Admin Panel fully loaded');
        });
    </script>
    
    <!-- Champs cachés pour JavaScript -->
    <input type="hidden" id="csrf_token" value="<?= SessionManager::getCSRFToken() ?>">
    <input type="hidden" id="admin_id" value="<?= htmlspecialchars($adminId) ?>">
    <input type="hidden" id="language" value="<?= htmlspecialchars($language) ?>">
</body>
</html><?php
/**
 * Panel Administrateur - The Mind
 * 
 * Interface complète de gestion pour les administrateurs :
 * - Gestion des utilisateurs (CRUD complet)
 * - Création et monitoring des parties
 * - Statistiques globales du serveur
 * - Outils de modération et maintenance
 * 
 * @package TheMind
 * @version 1.0
 * @since Phase 3
 */

declare(strict_types=1);

// Headers de sécurité renforcés
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

// Configuration et dépendances
require_once '../../config/database.php';
require_once '../../config/session.php';
require_once '../../config/constants.php';

// Vérification authentification et autorisation ADMIN
if (!SessionManager::isLoggedIn()) {
    header('Location: ' . BASE_URL . 'index.php');
    exit;
}

$userRole = SessionManager::get('role', '');
if (strtolower($userRole) !== 'admin') {
    header('Location: ' . BASE_URL . 'pages/profile/index.php');
    exit;
}

// Prolonger la session
SessionManager::extendSession();

// Configuration de la page
$pageTitle = 'Panel Administrateur';
$cssFiles = ['profile', 'forms'];
$jsFiles = ['profile', 'admin'];

// Gestion des textes multilingues
$language = SessionManager::get('language', 'fr');
require_once "../../languages/{$language}.php";

// Variables utilisateur
$adminId = SessionManager::get('user_id');
$adminUsername = SessionManager::get('username', 'Admin');

// Messages de feedback
$successMessage = '';
$errorMessage = '';
$actionResponse = [];

// Connexion base de données
$db = Database::getInstance();
$conn = $db->getConnection();

// Gestion des actions AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    
    // Vérification CSRF pour AJAX
    if (!SessionManager::validateCSRFToken(filter_input(INPUT_POST, 'csrf_token'))) {
        echo json_encode(['success' => false, 'message' => 'Token CSRF invalide']);
        exit;
    }
    
    $action = filter_input(INPUT_POST, 'ajax_action', FILTER_SANITIZE_STRING);
    
    try {
        switch ($action) {
            case 'create_user':
                $result = handleCreateUser($conn);
                break;
                
            case 'update_user':
                $result = handleUpdateUser($conn);
                break;
                
            case 'delete_user':
                $result = handleDeleteUser($conn);
                break;
                
            case 'create_game':
                $result = handleCreateGame($conn, $adminId);
                break;
                
            case 'manage_game':
                $result = handleManageGame($conn);
                break;
                
            case 'get_user_details':
                $result = handleGetUserDetails($conn);
                break;
                
            case 'get_game_details':
                $result = handleGetGameDetails($conn);
                break;
                
            case 'export_data':
                $result = handleExportData($conn);
                break;
                
            default:
                $result = ['success' => false, 'message' => 'Action non reconnue'];
        }
        
        echo json_encode($result);
        exit;
        
    } catch (Exception $e) {
        error_log("Erreur admin panel: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
        exit;
    }
}

// Récupération des données pour l'affichage
try {
    // 1. Statistiques globales
    $globalStats = getGlobalStats($conn);
    
    // 2. Liste des utilisateurs
    $users = getUsers($conn);
    
    // 3. Liste des rôles
    $roles = getRoles($conn);
    
    // 4. Parties actives
    $activeGames = getActiveGames($conn);
    
    // 5. Activité récente
    $recentActivity = getRecentActivity($conn);
    
    // 6. Statistiques système
    $systemStats = getSystemStats($conn);
    
} catch (PDOException $e) {
    error_log("Erreur récupération données admin: " . $e->getMessage());
    $globalStats = $users = $roles = $activeGames = $recentActivity = $systemStats = [];
}

// === FONCTIONS DE GESTION ===

function handleCreateUser($conn): array {
    $username = trim(filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING) ?? '');
    $email = trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL) ?? '');
    $password = filter_input(INPUT_POST, 'password', FILTER_UNSAFE_RAW) ?? '';
    $roleId = filter_input(INPUT_POST, 'role_id', FILTER_VALIDATE_INT) ?? 0;
    $avatar = trim(filter_input(INPUT_POST, 'avatar', FILTER_SANITIZE_STRING) ?? '👤');
    
    // Validation
    if (empty($username) || empty($email) || empty($password) || $roleId <= 0) {
        return ['success' => false, 'message' => 'Tous les champs sont obligatoires'];
    }
    
    if (strlen($username) < 3 || strlen($password) < 8) {
        return ['success' => false, 'message' => 'Nom d\'utilisateur min 3 caractères, mot de passe min 8 caractères'];
    }
    
    // Vérification unicité
    $checkQuery = "SELECT COUNT(*) FROM Utilisateurs WHERE identifiant = :username OR mail = :email";
    $checkStmt = $conn->prepare($checkQuery);
    $checkStmt->bindParam(':username', $username, PDO::PARAM_STR);
    $checkStmt->bindParam(':email', $email, PDO::PARAM_STR);
    $checkStmt->execute();
    
    if ($checkStmt->fetchColumn() > 0) {
        return ['success' => false, 'message' => 'Nom d\'utilisateur ou email déjà utilisé'];
    }
    
    // Création utilisateur
    $conn->beginTransaction();
    
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    $insertQuery = "INSERT INTO Utilisateurs (identifiant, mail, mdp, avatar, id_role, date_creation) 
                   VALUES (:username, :email, :password, :avatar, :role_id, NOW())";
    $insertStmt = $conn->prepare($insertQuery);
    $insertStmt->bindParam(':username', $username, PDO::PARAM_STR);
    $insertStmt->bindParam(':email', $email, PDO::PARAM_STR);
    $insertStmt->bindParam(':password', $hashedPassword, PDO::PARAM_STR);
    $insertStmt->bindParam(':avatar', $avatar, PDO::PARAM_STR);
    $insertStmt->bindParam(':role_id', $roleId, PDO::PARAM_INT);
    $insertStmt->execute();
    
    $userId = $conn->lastInsertId();
    
    // Créer les statistiques initiales
    $statsQuery = "INSERT INTO Statistiques (id_utilisateur, parties_jouees, parties_gagnees, taux_reussite, cartes_jouees) 
                  VALUES (:user_id, 0, 0, 0, 0)";
    $statsStmt = $conn->prepare($statsQuery);
    $statsStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $statsStmt->execute();
    
    $conn->commit();
    
    return ['success' => true, 'message' => 'Utilisateur créé avec succès', 'user_id' => $userId];
}

function handleUpdateUser($conn): array {
    $userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT) ?? 0;
    $username = trim(filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING) ?? '');
    $email = trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL) ?? '');
    $roleId = filter_input(INPUT_POST, 'role_id', FILTER_VALIDATE_INT) ?? 0;
    $avatar = trim(filter_input(INPUT_POST, 'avatar', FILTER_SANITIZE_STRING) ?? '👤');
    $newPassword = filter_input(INPUT_POST, 'new_password', FILTER_UNSAFE_RAW);
    
    if ($userId <= 0 || empty($username) || empty($email) || $roleId <= 0) {
        return ['success' => false, 'message' => 'Données invalides'];
    }
    
    // Vérification unicité (hors utilisateur actuel)
    $checkQuery = "SELECT COUNT(*) FROM Utilisateurs WHERE (identifiant = :username OR mail = :email) AND id != :user_id";
    $checkStmt = $conn->prepare($checkQuery);
    $checkStmt->bindParam(':username', $username, PDO::PARAM_STR);
    $checkStmt->bindParam(':email', $email, PDO::PARAM_STR);
    $checkStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $checkStmt->execute();
    
    if ($checkStmt->fetchColumn() > 0) {
        return ['success' => false, 'message' => 'Nom d\'utilisateur ou email déjà utilisé'];
    }
    
    // Mise à jour
    $updateQuery = "UPDATE Utilisateurs SET identifiant = :username, mail = :email, avatar = :avatar, id_role = :role_id";
    $params = [
        ':username' => $username,
        ':email' => $email,
        ':avatar' => $avatar,
        ':role_id' => $roleId,
        ':user_id' => $userId
    ];
    
    if (!empty($newPassword)) {
        $updateQuery .= ", mdp = :password";
        $params[':password'] = password_hash($newPassword, PASSWORD_DEFAULT);
    }
    
    $updateQuery .= " WHERE id = :user_id";
    
    $updateStmt = $conn->prepare($updateQuery);
    foreach ($params as $key => $value) {
        $updateStmt->bindValue($key, $value);
    }
    $updateStmt->execute();
    
    return ['success' => true, 'message' => 'Utilisateur mis à jour avec succès'];
}

function handleDeleteUser($conn): array {
    $userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT) ?? 0;
    
    if ($userId <= 0) {
        return ['success' => false, 'message' => 'ID utilisateur invalide'];
    }
    
    // Empêcher la suppression du dernier admin
    $adminCountQuery = "SELECT COUNT(*) FROM Utilisateurs u JOIN Roles r ON u.id_role = r.id WHERE r.nom = 'admin'";
    $adminCountStmt = $conn->prepare($adminCountQuery);
    $adminCountStmt->execute();
    $adminCount = $adminCountStmt->fetchColumn();
    
    $userRoleQuery = "SELECT r.nom FROM Utilisateurs u JOIN Roles r ON u.id_role = r.id WHERE u.id = :user_id";
    $userRoleStmt = $conn->prepare($userRoleQuery);
    $userRoleStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $userRoleStmt->execute();
    $userRole = $userRoleStmt->fetchColumn();
    
    if ($userRole === 'admin' && $adminCount <= 1) {
        return ['success' => false, 'message' => 'Impossible de supprimer le dernier administrateur'];
    }
    
    $conn->beginTransaction();
    
    // Supprimer les données associées
    $tables = [
        'Cartes' => 'id_utilisateur',
        'Actions_jeu' => 'id_utilisateur',
        'Utilisateurs_parties' => 'id_utilisateur',
        'Statistiques' => 'id_utilisateur',
        'Scores' => 'id_utilisateur',
        'Achievements' => 'id_utilisateur'
    ];
    
    foreach ($tables as $table => $column) {
        $deleteQuery = "DELETE FROM $table WHERE $column = :user_id";
        $deleteStmt = $conn->prepare($deleteQuery);
        $deleteStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $deleteStmt->execute();
    }
    
    // Supprimer l'utilisateur
    $deleteUserQuery = "DELETE FROM Utilisateurs WHERE id = :user_id";
    $deleteUserStmt = $conn->prepare($deleteUserQuery);
    $deleteUserStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $deleteUserStmt->execute();
    
    $conn->commit();
    
    return ['success' => true, 'message' => 'Utilisateur supprimé avec succès'];
}

function handleCreateGame($conn, $adminId): array {
    $gameName = trim(filter_input(INPUT_POST, 'game_name', FILTER_SANITIZE_STRING) ?? '');
    $playerCount = filter_input(INPUT_POST, 'player_count', FILTER_VALIDATE_INT) ?? 2;
    $difficulty = filter_input(INPUT_POST, 'difficulty', FILTER_SANITIZE_STRING) ?? 'moyen';
    $isPrivate = filter_input(INPUT_POST, 'is_private', FILTER_VALIDATE_BOOLEAN) ?? false;
    $startingLevel = filter_input(INPUT_POST, 'starting_level', FILTER_VALIDATE_INT) ?? 1;
    
    if (empty($gameName)) {
        return ['success' => false, 'message' => 'Le nom de la partie est obligatoire'];
    }
    
    if ($playerCount < 2 || $playerCount > 4) {
        $playerCount = 2;
    }
    
    if (!in_array($difficulty, ['facile', 'moyen', 'difficile'])) {
        $difficulty = 'moyen';
    }
    
    if ($startingLevel < 1 || $startingLevel > 12) {
        $startingLevel = 1;
    }
    
    // Calcul des vies selon la difficulté
    $lives = $playerCount === 2 ? 3 : 2;
    if ($difficulty === 'facile') $lives++;
    if ($difficulty === 'difficile') $lives = max(1, $lives - 1);
    
    $conn->beginTransaction();
    
    // Créer la partie
    $createQuery = "INSERT INTO Parties (nom, niveau, nombre_joueurs, vies_restantes, shurikens_restants, 
                   difficulte, status, prive, date_creation) 
                   VALUES (:nom, :niveau, :nombre_joueurs, :vies, 1, :difficulte, :status, :prive, NOW())";
    $createStmt = $conn->prepare($createQuery);
    $createStmt->bindParam(':nom', $gameName, PDO::PARAM_STR);
    $createStmt->bindParam(':niveau', $startingLevel, PDO::PARAM_INT);
    $createStmt->bindParam(':nombre_joueurs', $playerCount, PDO::PARAM_INT);
    $createStmt->bindParam(':vies', $lives, PDO::PARAM_INT);
    $createStmt->bindParam(':difficulte', $difficulty, PDO::PARAM_STR);
    $status = GAME_STATUS_WAITING;
    $createStmt->bindParam(':status', $status, PDO::PARAM_STR);
    $createStmt->bindParam(':prive', $isPrivate, PDO::PARAM_BOOL);
    $createStmt->execute();
    
    $gameId = $conn->lastInsertId();
    
    // Ajouter l'admin comme premier joueur
    $joinQuery = "INSERT INTO Utilisateurs_parties (id_utilisateur, id_partie, position) VALUES (:admin_id, :game_id, 1)";
    $joinStmt = $conn->prepare($joinQuery);
    $joinStmt->bindParam(':admin_id', $adminId, PDO::PARAM_INT);
    $joinStmt->bindParam(':game_id', $gameId, PDO::PARAM_INT);
    $joinStmt->execute();
    
    $conn->commit();
    
    return ['success' => true, 'message' => 'Partie créée avec succès', 'game_id' => $gameId];
}

function handleManageGame($conn): array {
    $gameId = filter_input(INPUT_POST, 'game_id', FILTER_VALIDATE_INT) ?? 0;
    $action = filter_input(INPUT_POST, 'game_action', FILTER_SANITIZE_STRING) ?? '';
    
    if ($gameId <= 0 || empty($action)) {
        return ['success' => false, 'message' => 'Paramètres invalides'];
    }
    
    switch ($action) {
        case 'start':
            $updateQuery = "UPDATE Parties SET status = :status, date_debut = NOW() WHERE id = :game_id";
            $status = GAME_STATUS_IN_PROGRESS;
            $message = 'Partie démarrée';
            break;
            
        case 'pause':
            $updateQuery = "UPDATE Parties SET status = :status WHERE id = :game_id";
            $status = 'pause';
            $message = 'Partie mise en pause';
            break;
            
        case 'resume':
            $updateQuery = "UPDATE Parties SET status = :status WHERE id = :game_id";
            $status = GAME_STATUS_IN_PROGRESS;
            $message = 'Partie reprise';
            break;
            
        case 'cancel':
            $updateQuery = "UPDATE Parties SET status = :status, date_fin = NOW() WHERE id = :game_id";
            $status = GAME_STATUS_CANCELLED;
            $message = 'Partie annulée';
            break;
            
        default:
            return ['success' => false, 'message' => 'Action non reconnue'];
    }
    
    $updateStmt = $conn->prepare($updateQuery);
    $updateStmt->bindParam(':status', $status, PDO::PARAM_STR);
    $updateStmt->bindParam(':game_id', $gameId, PDO::PARAM_INT);
    $updateStmt->execute();
    
    return ['success' => true, 'message' => $message];
}

function handleGetUserDetails($conn): array {
    $userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT) ?? 0;
    
    if ($userId <= 0) {
        return ['success' => false, 'message' => 'ID utilisateur invalide'];
    }
    
    $query = "SELECT u.*, r.nom as role_nom, s.* 
             FROM Utilisateurs u 
             LEFT JOIN Roles r ON u.id_role = r.id 
             LEFT JOIN Statistiques s ON u.id = s.id_utilisateur 
             WHERE u.id = :user_id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();
    
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        return ['success' => false, 'message' => 'Utilisateur non trouvé'];
    }
    
    return ['success' => true, 'user' => $user];
}

function handleGetGameDetails($conn): array {
    $gameId = filter_input(INPUT_POST, 'game_id', FILTER_VALIDATE_INT) ?? 0;
    
    if ($gameId <= 0) {
        return ['success' => false, 'message' => 'ID partie invalide'];
    }
    
    $query = "SELECT p.*, 
             (SELECT COUNT(*) FROM Utilisateurs_parties WHERE id_partie = p.id) as joueurs_actuels,
             (SELECT GROUP_CONCAT(u.identifiant) FROM Utilisateurs u 
              JOIN Utilisateurs_parties up ON u.id = up.id_utilisateur 
              WHERE up.id_partie = p.id ORDER BY up.position) as joueurs_noms
             FROM Parties p WHERE p.id = :game_id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':game_id', $gameId, PDO::PARAM_INT);
    $stmt->execute();
    
    $game = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$game) {
        return ['success' => false, 'message' => 'Partie non trouvée'];
    }
    
    return ['success' => true, 'game' => $game];
}

function handleExportData($conn): array {
    $exportType = filter_input(INPUT_POST, 'export_type', FILTER_SANITIZE_STRING) ?? '';
    
    switch ($exportType) {
        case 'users':
            $query = "SELECT u.identifiant, u.mail, r.nom as role, u.date_creation, 
                     s.parties_jouees, s.parties_gagnees, s.taux_reussite 
                     FROM Utilisateurs u 
                     LEFT JOIN Roles r ON u.id_role = r.id 
                     LEFT JOIN Statistiques s ON u.id = s.id_utilisateur 
                     ORDER BY u.date_creation DESC";
            break;
            
        case 'games':
            $query = "SELECT p.nom, p.niveau, p.status, p.difficulte, p.nombre_joueurs,
                     p.date_creation, p.date_debut, p.date_fin,
                     (SELECT COUNT(*) FROM Utilisateurs_parties WHERE id_partie = p.id) as joueurs_actuels
                     FROM Parties p ORDER BY p.date_creation DESC";
            break;
            
        default:
            return ['success' => false, 'message' => 'Type d\'export non reconnu'];
    }
    
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return ['success' => true, 'data' => $data, 'type' => $exportType];
}

// === FONCTIONS DE RÉCUPÉRATION DE DONNÉES ===

function getGlobalStats($conn): array {
    $stats = [];
    
    // Nombre total d'utilisateurs
    $stmt = $conn->query("SELECT COUNT(*) FROM Utilisateurs");
    $stats['total_users'] = $stmt->fetchColumn();
    
    // Nombre total de parties
    $stmt = $conn->query("SELECT COUNT(*) FROM Parties");
    $stats['total_games'] = $stmt->fetchColumn();
    
    // Parties actives
    $stmt = $conn->query("SELECT COUNT(*) FROM Parties WHERE status IN ('en_attente', 'en_cours', 'pause')");
    $stats['active_games'] = $stmt->fetchColumn();
    
    // Utilisateurs connectés récemment (24h)
    $stmt = $conn->query("SELECT COUNT(*) FROM Utilisateurs WHERE derniere_connexion > DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    $stats['recent_users'] = $stmt->fetchColumn();
    
    // Parties créées cette semaine
    $stmt = $conn->query("SELECT COUNT(*) FROM Parties WHERE date_creation > DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $stats['weekly_games'] = $stmt->fetchColumn();
    
    // Taux de réussite moyen
    $stmt = $conn->query("SELECT AVG(taux_reussite) FROM Statistiques WHERE parties_jouees > 0");
    $stats['avg_success_rate'] = round($stmt->fetchColumn(), 1);
    
    return $stats;
}

function getUsers($conn, $limit = 50): array {
    $query = "SELECT u.id, u.identifiant, u.mail, u.avatar, r.nom as role_nom, u.date_creation, u.derniere_connexion,
             s.parties_jouees, s.parties_gagnees, s.taux_reussite
             FROM Utilisateurs u 
             LEFT JOIN Roles r ON u.id_role = r.id 
             LEFT JOIN Statistiques s ON u.id = s.id_utilisateur 
             ORDER BY u.date_creation DESC LIMIT :limit";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getRoles($conn): array {
    $query = "SELECT id, nom, description FROM Roles ORDER BY id";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getActiveGames($conn): array {
    $query = "SELECT p.id, p.nom, p.niveau, p.status, p.difficulte, p.nombre_joueurs, p.date_creation,
             (SELECT COUNT(*) FROM Utilisateurs_parties WHERE id_partie = p.id) as joueurs_actuels,
             (SELECT identifiant FROM Utilisateurs WHERE id = 
                (SELECT id_utilisateur FROM Utilisateurs_parties WHERE id_partie = p.id AND position = 1)
             ) as admin_nom
             FROM Parties p 
             WHERE p.status IN ('en_attente', 'en_cours', 'pause')
             ORDER BY p.date_creation DESC LIMIT 20";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getRecentActivity($conn): array {
    $query = "SELECT 'user_created' as type, identifiant as details, date_creation as date_action FROM Utilisateurs 
             WHERE date_creation > DATE_SUB(NOW(), INTERVAL 7 DAY)
             UNION ALL
             SELECT 'game_created' as type, nom as details, date_creation as date_action FROM Parties 
             WHERE date_creation > DATE_SUB(NOW(), INTERVAL 7 DAY)
             ORDER BY date_action DESC LIMIT 10";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getSystemStats($conn): array {
    $stats = [];
    
    // Taille de la base de données
    $dbName = 'bddthemind'; // À adapter selon votre configuration
    $stmt = $conn->query("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb 
                         FROM information_schema.tables WHERE table_schema = '$dbName'");
    $stats['db_size_mb'] = $stmt->fetchColumn() ?: 0;
    
    // Nombre de connexions actives
    $stmt = $conn->query("SHOW STATUS LIKE 'Threads_connected'");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['active_connections'] = $result ? $result['Value'] : 0;
    
    // Uptime du serveur
    $stmt = $conn->query("SHOW STATUS LIKE 'Uptime'");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['server_uptime'] = $result ? $result['Value'] : 0;
    
    return $stats;
}

// Fonctions utilitaires
function formatBytes($bytes, $precision = 2): string {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

function formatUptime($seconds): string {
    $days = floor($seconds / 86400);
    $hours = floor(($seconds % 86400) / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    
    return "{$days}j {$hours}h {$minutes}m";
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
    <meta name="description" content="Panel Administrateur - The Mind">
    <title><?= htmlspecialchars($texts['admin_panel']) ?> - The Mind</title>
    
    <!-- CSS Core -->
    <link rel="stylesheet" href="<?= ASSETS_URL ?>css/main.css">
    <link rel="stylesheet" href="<?= ASSETS_URL ?>css/components/buttons.css">
    <link rel="stylesheet" href="<?= ASSETS_URL ?>css/components/modals.css">
    <link rel="stylesheet" href="<?= ASSETS_URL ?>css/components/forms.css">
    
    <!-- CSS spécifiques -->
    <?php foreach ($cssFiles as $cssFile): ?>
    <link rel="stylesheet" href="<?= ASSETS_URL ?>css/<?= $cssFile ?>.css">
    <?php endforeach; ?>
    
    <!-- CSS Admin Panel spécifique -->
    <style>
        .admin-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
            display: grid;
            grid-template-columns: 250px 1fr;
            gap: 2rem;
            min-height: calc(100vh - 120px);
        }
        
        .admin-sidebar {
            background: var(--card-bg);
            border-radius: var(--border-radius-lg);
            padding: 2rem;
            height: fit-content;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
            position: sticky;
            top: 2rem;
        }
        
        .admin-main {
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }
        
        .admin-nav {
            list-style: none;
            margin: 0;
            padding: 0;
        }
        
        .admin-nav li {
            margin-bottom: 0.5rem;
        }
        
        .admin-nav a {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            border-radius: var(--border-radius);
            color: var(--text-secondary);
            text-decoration: none;
            transition: all 0.3s ease;
            font-weight: 500;
        }
        
        .admin-nav a:hover,
        .admin-nav a.active {
            background: var(--primary-color);
            color: white;
            transform: translateX(4px);
        }
        
        .admin-nav .icon {
            font-size: 1.25rem;
        }
        
        .stats-overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: var(--card-bg);
            padding: 2rem 1.5rem;
            border-radius: var(--border-radius-lg);
            text-align: center;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: var(--gradient-primary);
        }
        
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }
        
        .stat-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
            font-family: 'Orbitron', monospace;
        }
        
        .stat-label {
            color: var(--text-secondary);
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }
        
        .section {
            background: var(--card-bg);
            border-radius: var(--border-radius-lg);
            padding: 2rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-color);
            display: none;
        }
        
        .section.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border-color);
        }
        
        .section-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        
        .data-table th,
        .data-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        
        .data-table th {
            background: var(--bg-secondary);
            font-weight: 600;
            color: var(--text-primary);
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        .data-table tbody tr {
            transition: all 0.2s ease;
        }
        
        .data-table tbody tr:hover {
            background: var(--bg-hover);
        }
        
        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }
        
        .action-btn {
            background: none;
            border: none;
            color: var(--text-secondary);
            cursor: pointer;
            font-size: 1.125rem;
            padding: 0.25rem;
            border-radius: var(--border-radius);
            transition: all 0.3s ease;
        }
        
        .action-btn:hover {
            background: var(--bg-secondary);
            color: var(--primary-color);
        }
        
        .action-btn.danger:hover {
            color: #ef4444;
        }
        
        .quick-actions {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
        }
        
        .avatar-display {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--gradient-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-right: 0.5rem;
        }
        
        .user-info {
            display: flex;
            align-items: center;
        }
        
        .loading {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            color: var(--text-secondary);
        }
        
        .loading::before {
            content: '';
            width: 20px;
            height: 20px;
            border: 2px solid var(--border-color);
            border-top: 2px solid var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-right: 0.5rem;
        }
        
        .system-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .system-info-item {
            background: var(--bg-secondary);
            padding: 1rem;
            border-radius: var(--border-radius);
            border-left: 4px solid var(--accent-color);
        }
        
        .system-info-label {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-bottom: 0.25rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .system-info-value {
            font-size: 1.125rem;
            color: var(--text-primary);
            font-weight: 600;
            font-family: 'Orbitron', monospace;
        }
        
        .activity-feed {
            max-height: 400px;
            overflow-y: auto;
        }
        
        .activity-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
            transition: all 0.3s ease;
        }
        
        .activity-item:hover {
            background: var(--bg-hover);
        }
        
        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--gradient-secondary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            flex-shrink: 0;
        }
        
        .activity-content {
            flex: 1;
        }
        
        .activity-title {
            font-weight: 600;
            color: var(--text-primary);
            margin: 0 0 0.25rem 0;
        }
        
        .activity-time {
            font-size: 0.875rem;
            color: var(--text-secondary);
            font-family: 'Orbitron', monospace;
        }
        
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            display: none;
            z-index: 10000;
            align-items: center;
            justify-content: center;
        }
        
        .modal-overlay.active {
            display: flex;
        }
        
        .modal-content {
            background: var(--card-bg);
            border-radius: var(--border-radius-lg);
            padding: 2rem;
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: var(--shadow-xl);
            border: 1px solid var(--border-color);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border-color);
        }
        
        .modal-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0;
        }
        
        .modal-close {
            background: none;
            border: none;
            color: var(--text-secondary);
            cursor: pointer;
            font-size: 1.5rem;
            padding: 0;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: var(--border-radius);
            transition: all 0.3s ease;
        }
        
        .modal-close:hover {
            background: var(--bg-secondary);
            color: var(--text-primary);
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @media (max-width: 1024px) {
            .admin-container {
                grid-template-columns: 200px 1fr;
                gap: 1.5rem;
            }
            
            .stats-overview {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .admin-container {
                grid-template-columns: 1fr;
                padding: 1rem;
            }
            
            .admin-sidebar {
                position: static;
                order: 2;
            }
            
            .admin-main {
                order: 1;
            }
            
            .stats-overview {
                grid-template-columns: 1fr;
            }
            
            .quick-actions {
                flex-direction: column;
            }
            
            .data-table {
                font-size: 0.875rem;
            }
            
            .data-table th:nth-child(n+4),
            .data-table td:nth-child(n+4) {
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
                <a href="<?= BASE_URL ?>pages/profile/index.php" class="btn btn-secondary">
                    👤 <?= htmlspecialchars($texts['profile']) ?>
                </a>
                <a href="<?= BASE_URL ?>pages/dashboard.php" class="btn btn-outline">
                    🏠 <?= htmlspecialchars($texts['dashboard']) ?>
                </a>
            </div>
        </div>
    </nav>

    <!-- Contenu principal -->
    <main class="admin-container">
        <!-- Sidebar navigation -->
        <aside class="admin-sidebar">
            <h2 style="margin: 0 0 2rem 0; color: var(--text-primary); font-size: 1.25rem;">
                👑 Panel Admin
            </h2>
            
            <nav>
                <ul class="admin-nav">
                    <li>
                        <a href="#overview" class="nav-link active" data-section="overview">
                            <span class="icon">📊</span>
                            Vue d'ensemble
                        </a>
                    </li>
                    <li>
                        <a href="#users" class="nav-link" data-section="users">
                            <span class="icon">👥</span>
                            Utilisateurs
                        </a>
                    </li>
                    <li>
                        <a href="#games" class="nav-link" data-section="games">
                            <span class="icon">🎮</span>
                            Parties
                        </a>
                    </li>
                    <li>
                        <a href="#create" class="nav-link" data-section="create">
                            <span class="icon">➕</span>
                            Créer
                        </a>
                    </li>
                    <li>
                        <a href="#activity" class="nav-link" data-section="activity">
                            <span class="icon">📈</span>
                            Activité
                        </a>
                    </li>
                    <li>
                        <a href="#system" class="nav-link" data-section="system">
                            <span class="icon">⚙️</span>
                            Système
                        </a>
                    </li>
                </ul>
            </nav>
            
            <div style="margin-top: 2rem; padding-top: 1rem; border-top: 1px solid var(--border-color);">
                <div style="text-align: center; color: var(--text-secondary); font-size: 0.875rem;">
                    Connecté en tant que<br>
                    <strong style="color: var(--text-primary);"><?= htmlspecialchars($adminUsername) ?></strong>
                </div>
            </div>
        </aside>
        
        <!-- Contenu principal -->
        <section class="admin-main">
            <!-- Vue d'ensemble -->
            <div id="overview" class="section active">
                <div class="section-header">
                    <h1 class="section-title">
                        📊 Vue d'ensemble
                    </h1>
                    <button class="btn btn-outline" onclick="refreshOverview()">
                        🔄 Actualiser
                    </button>
                </div>
                
                <div class="stats-overview">
                    <div class="stat-card">
                        <div class="stat-value"><?= number_format($globalStats['total_users'] ?? 0) ?></div>
                        <div class="stat-label">Utilisateurs totaux</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-value"><?= number_format($globalStats['total_games'] ?? 0) ?></div>
                        <div class="stat-label">Parties totales</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-value"><?= number_format($globalStats['active_games'] ?? 0) ?></div>
                        <div class="stat-label">Parties actives</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-value"><?= number_format($globalStats['recent_users'] ?? 0) ?></div>
                        <div class="stat-label">Connectés 24h</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-value"><?= number_format($globalStats['weekly_games'] ?? 0) ?></div>
                        <div class="stat-label">Parties cette semaine</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-value"><?= number_format($globalStats['avg_success_rate'] ?? 0, 1) ?>%</div>
                        <div class="stat-label">Taux réussite moyen</div>
                    </div>
                </div>
                
                <!-- Activité récente dans l'overview -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">📋 Activité récente</h3>
                    </div>
                    
                    <div class="activity-feed" style="max-height: 300px;">
                        <?php if (!empty($recentActivity)): ?>
                        <?php foreach ($recentActivity as $activity): ?>
                        <div class="activity-item">
                            <div class="activity-icon">
                                <?= $activity['type'] === 'user_created' ? '👤' : '🎮' ?>
                            </div>
                            <div class="activity-content">
                                <div class="activity-title">
                                    <?php if ($activity['type'] === 'user_created'): ?>
                                        Nouvel utilisateur : <?= htmlspecialchars($activity['details']) ?>
                                    <?php else: ?>
                                        Nouvelle partie : <?= htmlspecialchars($activity['details']) ?>
                                    <?php endif; ?>
                                </div>
                                <div class="activity-time">
                                    <?= date('d/m/Y H:i', strtotime($activity['date_action'])) ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">📊</div>
                            <p>Aucune activité récente</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Gestion des utilisateurs -->
            <div id="users" class="section">
                <div class="section-header">
                    <h1 class="section-title">
                        👥 Gestion des utilisateurs
                    </h1>
                    <div class="quick-actions">
                        <button class="btn btn-primary" onclick="openCreateUserModal()">
                            ➕ Nouvel utilisateur
                        </button>
                        <button class="btn btn-outline" onclick="exportData('users')">
                            📥 Exporter
                        </button>
                        <button class="btn btn-outline" onclick="refreshUsers()">
                            🔄 Actualiser
                        </button>
                    </div>
                </div>
                
                <div class="table-container">
                    <table class="data-table" id="usersTable">
                        <thead>
                            <tr>
                                <th>Utilisateur</th>
                                <th>Email</th>
                                <th>Rôle</th>
                                <th>Parties jouées</th>
                                <th>Taux réussite</th>
                                <th>Inscription</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                            <tr>
                                <td>
                                    <div class="user-info">
                                        <div class="avatar-display">
                                            <?= htmlspecialchars($user['avatar']) ?>
                                        </div>
                                        <div>
                                            <strong><?= htmlspecialchars($user['identifiant']) ?></strong>
                                            <?php if ($user['derniere_connexion'] && 
                                                     strtotime($user['derniere_connexion']) > strtotime('-24 hours')): ?>
                                            <br><small style="color: #10b981;">🟢 En ligne récemment</small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($user['mail']) ?></td>
                                <td>
                                    <span class="badge <?= $user['role_nom'] === 'admin' ? 'badge-warning' : 'badge-secondary' ?>">
                                        <?= $user['role_nom'] === 'admin' ? '👑' : '🎮' ?>
                                        <?= htmlspecialchars(ucfirst($user['role_nom'])) ?>
                                    </span>
                                </td>
                                <td><?= number_format($user['parties_jouees'] ?? 0) ?></td>
                                <td><?= number_format($user['taux_reussite'] ?? 0, 1) ?>%</td>
                                <td><?= date('d/m/Y', strtotime($user['date_creation'])) ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="action-btn" onclick="viewUser(<?= $user['id'] ?>)" title="Voir détails">
                                            👁️
                                        </button>
                                        <button class="action-btn" onclick="editUser(<?= $user['id'] ?>)" title="Modifier">
                                            ✏️
                                        </button>
                                        <?php if ($user['id'] != $adminId): ?>
                                        <button class="action-btn danger" onclick="deleteUser(<?= $user['id'] ?>, '<?= htmlspecialchars($user['identifiant']) ?>')" title="Supprimer">
                                            🗑️
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Gestion des parties -->
            <div id="games" class="section">
                <div class="section-header">
                    <h1 class="section-title">
                        🎮 Gestion des parties
                    </h1>
                    <div class="quick-actions">
                        <button class="btn btn-primary" onclick="openCreateGameModal()">
                            ➕ Nouvelle partie
                        </button>
                        <button class="btn btn-outline" onclick="exportData('games')">
                            📥 Exporter
                        </button>
                        <button class="btn btn-outline" onclick="refreshGames()">
                            🔄 Actualiser
                        </button>
                    </div>
                </div>
                
                <div class="table-container">
                    <table class="data-table" id="gamesTable">
                        <thead>
                            <tr>
                                <th>Partie</th>
                                <th>Statut</th>
                                <th>Niveau</th>
                                <th>Joueurs</th>
                                <th>Difficulté</th>
                                <th>Administrateur</th>
                                <th>Création</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($activeGames as $game): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($game['nom'] ?: "Partie #{$game['id']}") ?></strong>
                                </td>
                                <td><?= getStatusBadge($game['status']) ?></td>
                                <td>
                                    <span class="badge badge-primary">
                                        🎯 <?= number_format($game['niveau']) ?>
                                    </span>
                                </td>
                                <td><?= number_format($game['joueurs_actuels']) ?>/<?= number_format($game['nombre_joueurs']) ?></td>
                                <td>
                                    <?= getDifficultyIcon($game['difficulte']) ?>
                                    <?= htmlspecialchars(ucfirst($game['difficulte'])) ?>
                                </td>
                                <td><?= htmlspecialchars($game['admin_nom'] ?: 'Non assigné') ?></td>
                                <td><?= date('d/m/Y H:i', strtotime($game['date_creation'])) ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="action-btn" onclick="viewGame(<?= $game['id'] ?>)" title="Voir détails">
                                            👁️
                                        </button>
                                        <button class="action-btn" onclick="manageGame(<?= $game['id'] ?>, 'start')" title="Démarrer">
                                            ▶️
                                        </button>
                                        <button class="action-btn" onclick="manageGame(<?= $game['id'] ?>, 'pause')" title="Pause">
                                            ⏸️
                                        </button>
                                        <button class="action-btn danger" onclick="manageGame(<?= $game['id'] ?>, 'cancel')" title="Annuler">
                                            ❌
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Création rapide -->
            <div id="create" class="section">
                <div class="section-header">
                    <h1 class="section-title">
                        ➕ Création rapide
                    </h1>
                </div>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem;">
                    <!-- Créer un utilisateur -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">👤 Nouvel utilisateur</h3>
                        </div>
                        
                        <form id="quickCreateUserForm">
                            <div class="form-group">
                                <label for="quick_username" class="form-label">Nom d'utilisateur</label>
                                <input type="text" id="quick_username" name="username" class="form-input" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="quick_email" class="form-label">Email</label>
                                <input type="email" id="quick_email" name="email" class="form-input" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="quick_password" class="form-label">Mot de passe</label>
                                <input type="password" id="quick_password" name="password" class="form-input" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="quick_role" class="form-label">Rôle</label>
                                <select id="quick_role" name="role_id" class="form-select" required>
                                    <option value="">Sélectionner un rôle</option>
                                    <?php foreach ($roles as $role): ?>
                                    <option value="<?= $role['id'] ?>"><?= htmlspecialchars(ucfirst($role['nom'])) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <button type="submit" class="btn btn-primary btn-block">
                                ➕ Créer l'utilisateur
                            </button>
                        </form>
                    </div>
                    
                    <!-- Créer une partie -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">🎮 Nouvelle partie</h3>
                        </div>
                        
                        <form id="quickCreateGameForm">
                            <div class="form-group">
                                <label for="quick_game_name" class="form-label">Nom de la partie</label>