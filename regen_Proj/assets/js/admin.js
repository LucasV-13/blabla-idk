/**
 * Admin JavaScript - The Mind
 * 
 * Gestion avancée du panel administrateur avec fonctionnalités complètes :
 * - Navigation entre sections
 * - Gestion CRUD des utilisateurs et parties
 * - Statistiques en temps réel
 * - Outils de maintenance et monitoring
 * 
 * @package TheMind
 * @version 1.0
 * @since Phase 3
 */

(function() {
    'use strict';
    
    // Configuration spécifique à l'admin
    const AdminConfig = {
        refreshInterval: 30000, // 30 secondes
        animationDuration: 300,
        shortcuts: {
            newUser: 'n',
            newGame: 'g',
            refresh: 'r',
            export: 'e'
        },
        tables: {
            pageSize: 20,
            sortable: true,
            searchable: true
        }
    };
    
    // Module principal d'administration
    window.TheMind.Admin = {
        // État interne
        state: {
            currentSection: 'overview',
            refreshTimers: new Map(),
            modals: new Map(),
            tables: new Map(),
            charts: new Map()
        },
        
        // Initialisation
        init: function() {
            this.bindEvents();
            this.initNavigation();
            this.initTables();
            this.initCharts();
            this.initKeyboardShortcuts();
            this.initAutoRefresh();
            this.initRealTimeUpdates();
            
            console.log('Admin module initialized');
        },
        
        // Liaison des événements
        bindEvents: function() {
            // Navigation
            document.querySelectorAll('.nav-link').forEach(link => {
                link.addEventListener('click', this.handleNavigation.bind(this));
            });
            
            // Boutons d'action
            this.bindActionButtons();
            
            // Formulaires
            this.bindForms();
            
            // Modals
            this.bindModals();
            
            // Tables
            this.bindTableEvents();
        },
        
        // Navigation entre sections
        initNavigation: function() {
            // Gérer l'URL hash
            const hash = window.location.hash.substring(1);
            if (hash && document.getElementById(hash)) {
                this.showSection(hash);
            }
            
            // Écouter les changements d'hash
            window.addEventListener('hashchange', () => {
                const newHash = window.location.hash.substring(1);
                if (newHash) {
                    this.showSection(newHash);
                }
            });
            
            // Navigation avec les flèches
            document.addEventListener('keydown', (e) => {
                if (e.ctrlKey) {
                    switch(e.key) {
                        case 'ArrowLeft':
                            this.navigateToPrevious();
                            break;
                        case 'ArrowRight':
                            this.navigateToNext();
                            break;
                    }
                }
            });
        },
        
        // Affichage d'une section
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
                this.animateSection(targetSection);
            }
            
            // Activer le lien nav correspondant
            const targetLink = document.querySelector(`[data-section="${sectionId}"]`);
            if (targetLink) {
                targetLink.classList.add('active');
            }
            
            // Mettre à jour l'état
            this.state.currentSection = sectionId;
            
            // Déclencher les actions spécifiques à la section
            this.onSectionChange(sectionId);
        },
        
        // Animation d'entrée pour les sections
        animateSection: function(section) {
            const elements = section.querySelectorAll('.stat-card, .card, .data-table');
            
            elements.forEach((element, index) => {
                element.style.opacity = '0';
                element.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    element.style.transition = `all ${AdminConfig.animationDuration}ms ease`;
                    element.style.opacity = '1';
                    element.style.transform = 'translateY(0)';
                }, index * 50);
            });
        },
        
        // Actions lors du changement de section
        onSectionChange: function(sectionId) {
            switch(sectionId) {
                case 'overview':
                    this.refreshOverviewStats();
                    break;
                case 'users':
                    this.refreshUsersTable();
                    break;
                case 'games':
                    this.refreshGamesTable();
                    break;
                case 'system':
                    this.refreshSystemStats();
                    break;
            }
        },
        
        // Gestion de la navigation
        handleNavigation: function(event) {
            event.preventDefault();
            const link = event.currentTarget;
            const section = link.dataset.section;
            
            if (section) {
                this.showSection(section);
                window.location.hash = section;
            }
        },
        
        // Navigation précédente/suivante
        navigateToPrevious: function() {
            const sections = ['overview', 'users', 'games', 'create', 'activity', 'system'];
            const currentIndex = sections.indexOf(this.state.currentSection);
            const prevIndex = currentIndex > 0 ? currentIndex - 1 : sections.length - 1;
            
            this.showSection(sections[prevIndex]);
            window.location.hash = sections[prevIndex];
        },
        
        navigateToNext: function() {
            const sections = ['overview', 'users', 'games', 'create', 'activity', 'system'];
            const currentIndex = sections.indexOf(this.state.currentSection);
            const nextIndex = currentIndex < sections.length - 1 ? currentIndex + 1 : 0;
            
            this.showSection(sections[nextIndex]);
            window.location.hash = sections[nextIndex];
        },
        
        // Liaison des boutons d'action
        bindActionButtons: function() {
            // Boutons de rafraîchissement
            document.querySelectorAll('[onclick*="refresh"]').forEach(btn => {
                const originalOnClick = btn.getAttribute('onclick');
                btn.removeAttribute('onclick');
                btn.addEventListener('click', () => {
                    this.showLoadingState(btn);
                    eval(originalOnClick);
                });
            });
            
            // Boutons d'export
            document.querySelectorAll('[onclick*="export"]').forEach(btn => {
                const originalOnClick = btn.getAttribute('onclick');
                btn.removeAttribute('onclick');
                btn.addEventListener('click', () => {
                    this.showLoadingState(btn);
                    eval(originalOnClick);
                });
            });
        },
        
        // État de chargement pour les boutons
        showLoadingState: function(button) {
            const originalText = button.textContent;
            const originalIcon = button.querySelector('.icon');
            
            button.disabled = true;
            button.textContent = 'Chargement...';
            
            if (originalIcon) {
                originalIcon.textContent = '⏳';
            }
            
            setTimeout(() => {
                button.disabled = false;
                button.textContent = originalText;
                if (originalIcon) {
                    // Restaurer l'icône originale selon le contexte
                    const iconMap = {
                        'refresh': '🔄',
                        'export': '📥',
                        'create': '➕'
                    };
                    
                    Object.keys(iconMap).forEach(key => {
                        if (originalText.toLowerCase().includes(key)) {
                            originalIcon.textContent = iconMap[key];
                        }
                    });
                }
            }, 2000);
        },
        
        // Liaison des formulaires
        bindForms: function() {
            // Formulaires de création rapide
            const forms = ['quickCreateUserForm', 'quickCreateGameForm', 'userForm', 'gameForm'];
            
            forms.forEach(formId => {
                const form = document.getElementById(formId);
                if (form) {
                    form.addEventListener('submit', this.handleFormSubmit.bind(this));
                    this.addFormValidation(form);
                }
            });
        },
        
        // Gestion de soumission de formulaire
        handleFormSubmit: function(event) {
            event.preventDefault();
            
            const form = event.target;
            const submitBtn = form.querySelector('button[type="submit"]');
            
            // Validation côté client
            if (!this.validateForm(form)) {
                return;
            }
            
            // État de chargement
            this.showLoadingState(submitBtn);
            
            // Préparation des données
            const formData = new FormData(form);
            formData.append('csrf_token', window.TheMind.config.csrfToken);
            
            // Déterminer l'action selon le formulaire
            const action = this.getFormAction(form);
            if (action) {
                formData.append('ajax_action', action);
            }
            
            // Envoi de la requête
            this.submitFormData(formData)
                .then(response => {
                    if (response.success) {
                        this.showNotification(response.message || 'Action réussie', 'success');
                        this.handleFormSuccess(form, response);
                    } else {
                        throw new Error(response.message || 'Erreur inconnue');
                    }
                })
                .catch(error => {
                    this.showNotification(error.message, 'error');
                })
                .finally(() => {
                    submitBtn.disabled = false;
                    // Restaurer le texte original du bouton
                });
        },
        
        // Déterminer l'action du formulaire
        getFormAction: function(form) {
            const formId = form.id;
            const actions = {
                'quickCreateUserForm': 'create_user',
                'quickCreateGameForm': 'create_game',
                'userForm': form.querySelector('[name="user_id"]')?.value ? 'update_user' : 'create_user',
                'gameForm': 'create_game'
            };
            
            return actions[formId];
        },
        
        // Gestion du succès de formulaire
        handleFormSuccess: function(form, response) {
            const formId = form.id;
            
            switch(formId) {
                case 'quickCreateUserForm':
                case 'userForm':
                    form.reset();
                    this.refreshUsersTable();
                    if (formId === 'userForm') {
                        this.closeModal('userModal');
                    }
                    break;
                    
                case 'quickCreateGameForm':
                case 'gameForm':
                    form.reset();
                    this.refreshGamesTable();
                    if (formId === 'gameForm') {
                        this.closeModal('gameModal');
                    }
                    break;
            }
        },
        
        // Validation de formulaire
        validateForm: function(form) {
            let isValid = true;
            const requiredFields = form.querySelectorAll('[required]');
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    this.showFieldError(field, 'Ce champ est obligatoire');
                    isValid = false;
                } else {
                    this.clearFieldError(field);
                }
            });
            
            // Validations spécifiques
            const emailFields = form.querySelectorAll('input[type="email"]');
            emailFields.forEach(field => {
                if (field.value && !this.isValidEmail(field.value)) {
                    this.showFieldError(field, 'Email invalide');
                    isValid = false;
                }
            });
            
            const passwordFields = form.querySelectorAll('input[type="password"][name="password"]');
            passwordFields.forEach(field => {
                if (field.value && field.value.length < 8) {
                    this.showFieldError(field, 'Mot de passe trop court (min 8 caractères)');
                    isValid = false;
                }
            });
            
            return isValid;
        },
        
        // Ajouter validation en temps réel
        addFormValidation: function(form) {
            const inputs = form.querySelectorAll('input, select, textarea');
            
            inputs.forEach(input => {
                input.addEventListener('blur', () => {
                    this.validateField(input);
                });
                
                input.addEventListener('input', () => {
                    this.clearFieldError(input);
                });
            });
        },
        
        // Validation d'un champ
        validateField: function(field) {
            if (field.required && !field.value.trim()) {
                this.showFieldError(field, 'Ce champ est obligatoire');
                return false;
            }
            
            if (field.type === 'email' && field.value && !this.isValidEmail(field.value)) {
                this.showFieldError(field, 'Email invalide');
                return false;
            }
            
            if (field.type === 'password' && field.name === 'password' && field.value && field.value.length < 8) {
                this.showFieldError(field, 'Mot de passe trop court');
                return false;
            }
            
            this.clearFieldError(field);
            return true;
        },
        
        // Afficher erreur de champ
        showFieldError: function(field, message) {
            this.clearFieldError(field);
            
            field.classList.add('error');
            
            const errorDiv = document.createElement('div');
            errorDiv.className = 'field-error';
            errorDiv.textContent = message;
            errorDiv.style.cssText = `
                color: #ef4444;
                font-size: 0.875rem;
                margin-top: 0.25rem;
                display: flex;
                align-items: center;
                gap: 0.25rem;
            `;
            errorDiv.innerHTML = `⚠️ ${message}`;
            
            field.parentNode.appendChild(errorDiv);
        },
        
        // Effacer erreur de champ
        clearFieldError: function(field) {
            field.classList.remove('error');
            const existingError = field.parentNode.querySelector('.field-error');
            if (existingError) {
                existingError.remove();
            }
        },
        
        // Validation email
        isValidEmail: function(email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        },
        
        // Envoi de données de formulaire
        submitFormData: function(formData) {
            return fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`Erreur HTTP: ${response.status}`);
                }
                return response.json();
            });
        },
        
        // Liaison des modals
        bindModals: function() {
            // Fermeture par clic extérieur
            document.addEventListener('click', (event) => {
                if (event.target.classList.contains('modal-overlay')) {
                    this.closeModal(event.target.id);
                }
            });
            
            // Fermeture par boutons
            document.querySelectorAll('.modal-close').forEach(btn => {
                btn.addEventListener('click', (event) => {
                    const modal = event.target.closest('.modal-overlay');
                    if (modal) {
                        this.closeModal(modal.id);
                    }
                });
            });
        },
        
        // Ouvrir modal
        openModal: function(modalId, data = null) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.add('active');
                document.body.style.overflow = 'hidden';
                
                // Pré-remplir les données si fournies
                if (data) {
                    this.populateModal(modalId, data);
                }
                
                // Focus sur le premier champ
                const firstInput = modal.querySelector('input, select, textarea');
                if (firstInput) {
                    setTimeout(() => firstInput.focus(), 100);
                }
            }
        },
        
        // Fermer modal
        closeModal: function(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.remove('active');
                document.body.style.overflow = 'auto';
                
                // Nettoyer le formulaire
                const form = modal.querySelector('form');
                if (form) {
                    form.reset();
                    this.clearFormErrors(form);
                }
            }
        },
        
        // Nettoyer les erreurs de formulaire
        clearFormErrors: function(form) {
            form.querySelectorAll('.error').forEach(field => {
                field.classList.remove('error');
            });
            form.querySelectorAll('.field-error').forEach(error => {
                error.remove();
            });
        },
        
        // Pré-remplir un modal
        populateModal: function(modalId, data) {
            const modal = document.getElementById(modalId);
            if (!modal) return;
            
            Object.keys(data).forEach(key => {
                const field = modal.querySelector(`[name="${key}"]`);
                if (field) {
                    field.value = data[key];
                }
            });
        },
        
        // Initialisation des tableaux
        initTables: function() {
            const tables = document.querySelectorAll('.data-table');
            
            tables.forEach(table => {
                this.enhanceTable(table);
            });
        },
        
        // Amélioration des tableaux
        enhanceTable: function(table) {
            // Ajouter la recherche
            this.addTableSearch(table);
            
            // Ajouter le tri
            this.addTableSort(table);
            
            // Ajouter la pagination
            this.addTablePagination(table);
            
            // Mémoriser le tableau
            this.state.tables.set(table.id, {
                element: table,
                currentPage: 1,
                sortColumn: null,
                sortDirection: 'asc',
                searchTerm: ''
            });
        },
        
        // Ajouter recherche au tableau
        addTableSearch: function(table) {
            const tableContainer = table.closest('.table-container') || table.parentNode;
            
            const searchContainer = document.createElement('div');
            searchContainer.className = 'table-search';
            searchContainer.style.cssText = `
                margin-bottom: 1rem;
                display: flex;
                gap: 1rem;
                align-items: center;
            `;
            
            searchContainer.innerHTML = `
                <input type="text" placeholder="🔍 Rechercher..." 
                       class="form-input" style="max-width: 300px;">
                <button class="btn btn-outline btn-sm" onclick="this.previousElementSibling.value=''; this.click();">
                    ❌ Effacer
                </button>
            `;
            
            const searchInput = searchContainer.querySelector('input');
            searchInput.addEventListener('input', (e) => {
                this.filterTable(table, e.target.value);
            });
            
            tableContainer.insertBefore(searchContainer, table);
        },
        
        // Filtrer le tableau
        filterTable: function(table, searchTerm) {
            const rows = table.querySelectorAll('tbody tr');
            const term = searchTerm.toLowerCase();
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(term) ? '' : 'none';
            });
            
            // Mettre à jour l'état
            if (this.state.tables.has(table.id)) {
                this.state.tables.get(table.id).searchTerm = searchTerm;
            }
        },
        
        // Ajouter tri au tableau
        addTableSort: function(table) {
            const headers = table.querySelectorAll('th');
            
            headers.forEach((header, index) => {
                if (header.textContent.trim()) {
                    header.style.cursor = 'pointer';
                    header.style.userSelect = 'none';
                    header.addEventListener('click', () => {
                        this.sortTable(table, index);
                    });
                    
                    // Ajouter indicateur de tri
                    header.innerHTML += ' <span class="sort-indicator">↕️</span>';
                }
            });
        },
        
        // Trier le tableau
        sortTable: function(table, columnIndex) {
            const tbody = table.querySelector('tbody');
            const rows = Array.from(tbody.querySelectorAll('tr'));
            const headers = table.querySelectorAll('th');
            
            // Déterminer la direction du tri
            const tableState = this.state.tables.get(table.id);
            let direction = 'asc';
            
            if (tableState && tableState.sortColumn === columnIndex) {
                direction = tableState.sortDirection === 'asc' ? 'desc' : 'asc';
            }
            
            // Effectuer le tri
            rows.sort((a, b) => {
                const aVal = a.cells[columnIndex].textContent.trim();
                const bVal = b.cells[columnIndex].textContent.trim();
                
                // Détecter si les valeurs sont numériques
                const aNum = parseFloat(aVal.replace(/[^\d.-]/g, ''));
                const bNum = parseFloat(bVal.replace(/[^\d.-]/g, ''));
                
                let comparison = 0;
                if (!isNaN(aNum) && !isNaN(bNum)) {
                    comparison = aNum - bNum;
                } else {
                    comparison = aVal.localeCompare(bVal);
                }
                
                return direction === 'asc' ? comparison : -comparison;
            });
            
            // Réappliquer les lignes triées
            rows.forEach(row => tbody.appendChild(row));
            
            // Mettre à jour les indicateurs de tri
            headers.forEach((header, index) => {
                const indicator = header.querySelector('.sort-indicator');
                if (indicator) {
                    if (index === columnIndex) {
                        indicator.textContent = direction === 'asc' ? '↑' : '↓';
                        header.style.background = 'var(--primary-color)';
                        header.style.color = 'white';
                    } else {
                        indicator.textContent = '↕️';
                        header.style.background = '';
                        header.style.color = '';
                    }
                }
            });
            
            // Mettre à jour l'état
            if (tableState) {
                tableState.sortColumn = columnIndex;
                tableState.sortDirection = direction;
            }
        },
        
        // Ajouter pagination au tableau
        addTablePagination: function(table) {
            const rows = table.querySelectorAll('tbody tr');
            if (rows.length <= AdminConfig.tables.pageSize) return;
            
            const tableContainer = table.closest('.table-container') || table.parentNode;
            
            const paginationContainer = document.createElement('div');
            paginationContainer.className = 'table-pagination';
            paginationContainer.style.cssText = `
                margin-top: 1rem;
                display: flex;
                justify-content: center;
                gap: 0.5rem;
                align-items: center;
            `;
            
            this.updatePagination(table, paginationContainer);
            tableContainer.appendChild(paginationContainer);
            
            // Afficher la première page
            this.showTablePage(table, 1);
        },
        
        // Mettre à jour la pagination
        updatePagination: function(table, container) {
            const visibleRows = Array.from(table.querySelectorAll('tbody tr')).filter(row => 
                row.style.display !== 'none'
            );
            const totalPages = Math.ceil(visibleRows.length / AdminConfig.tables.pageSize);
            const currentPage = this.state.tables.get(table.id)?.currentPage || 1;
            
            container.innerHTML = '';
            
            if (totalPages <= 1) return;
            
            // Bouton précédent
            const prevBtn = document.createElement('button');
            prevBtn.className = 'btn btn-outline btn-sm';
            prevBtn.textContent = '← Précédent';
            prevBtn.disabled = currentPage === 1;
            prevBtn.addEventListener('click', () => {
                this.showTablePage(table, currentPage - 1);
            });
            container.appendChild(prevBtn);
            
            // Numéros de page
            const startPage = Math.max(1, currentPage - 2);
            const endPage = Math.min(totalPages, currentPage + 2);
            
            for (let i = startPage; i <= endPage; i++) {
                const pageBtn = document.createElement('button');
                pageBtn.className = `btn ${i === currentPage ? 'btn-primary' : 'btn-outline'} btn-sm`;
                pageBtn.textContent = i;
                pageBtn.addEventListener('click', () => {
                    this.showTablePage(table, i);
                });
                container.appendChild(pageBtn);
            }
            
            // Bouton suivant
            const nextBtn = document.createElement('button');
            nextBtn.className = 'btn btn-outline btn-sm';
            nextBtn.textContent = 'Suivant →';
            nextBtn.disabled = currentPage === totalPages;
            nextBtn.addEventListener('click', () => {
                this.showTablePage(table, currentPage + 1);
            });
            container.appendChild(nextBtn);
            
            // Info pagination
            const info = document.createElement('span');
            info.style.cssText = 'margin-left: 1rem; color: var(--text-secondary); font-size: 0.875rem;';
            info.textContent = `Page ${currentPage} sur ${totalPages} (${visibleRows.length} éléments)`;
            container.appendChild(info);
        },
        
        // Afficher une page de tableau
        showTablePage: function(table, page) {
            const visibleRows = Array.from(table.querySelectorAll('tbody tr')).filter(row => 
                row.style.display !== 'none'
            );
            const startIndex = (page - 1) * AdminConfig.tables.pageSize;
            const endIndex = startIndex + AdminConfig.tables.pageSize;
            
            // Cacher toutes les lignes
            table.querySelectorAll('tbody tr').forEach(row => {
                row.style.display = 'none';
            });
            
            // Afficher les lignes de la page courante
            for (let i = startIndex; i < endIndex && i < visibleRows.length; i++) {
                visibleRows[i].style.display = '';
            }
            
            // Mettre à jour l'état
            if (this.state.tables.has(table.id)) {
                this.state.tables.get(table.id).currentPage = page;
            }
            
            // Mettre à jour la pagination
            const paginationContainer = table.parentNode.querySelector('.table-pagination');
            if (paginationContainer) {
                this.updatePagination(table, paginationContainer);
            }
        },
        
        // Liaison des événements de tableau
        bindTableEvents: function() {
            // Double-clic pour éditer
            document.querySelectorAll('.data-table tbody tr').forEach(row => {
                row.addEventListener('dblclick', () => {
                    const table = row.closest('table');
                    if (table.id === 'usersTable') {
                        const userId = this.extractIdFromRow(row, 'user');
                        if (userId) {
                            window.editUser(userId);
                        }
                    } else if (table.id === 'gamesTable') {
                        const gameId = this.extractIdFromRow(row, 'game');
                        if (gameId) {
                            window.viewGame(gameId);
                        }
                    }
                });
            });
        },
        
        // Extraire l'ID d'une ligne de tableau
        extractIdFromRow: function(row, type) {
            const actionButtons = row.querySelectorAll('.action-btn');
            for (const btn of actionButtons) {
                const onclick = btn.getAttribute('onclick');
                if (onclick) {
                    const match = onclick.match(/\((\d+)/);
                    if (match) {
                        return match[1];
                    }
                }
            }
            return null;
        },
        
        // Initialisation des raccourcis clavier
        initKeyboardShortcuts: function() {
            document.addEventListener('keydown', (event) => {
                // Ignorer si on est dans un champ de saisie
                if (event.target.tagName === 'INPUT' || event.target.tagName === 'TEXTAREA') {
                    return;
                }
                
                if (event.ctrlKey || event.metaKey) {
                    switch(event.key.toLowerCase()) {
                        case AdminConfig.shortcuts.newUser:
                            event.preventDefault();
                            window.openCreateUserModal();
                            break;
                        case AdminConfig.shortcuts.newGame:
                            event.preventDefault();
                            window.openCreateGameModal();
                            break;
                        case AdminConfig.shortcuts.refresh:
                            event.preventDefault();
                            this.refreshCurrentSection();
                            break;
                        case AdminConfig.shortcuts.export:
                            event.preventDefault();
                            this.showExportDialog();
                            break;
                    }
                }
                
                // Fermer les modals avec Échap
                if (event.key === 'Escape') {
                    document.querySelectorAll('.modal-overlay.active').forEach(modal => {
                        this.closeModal(modal.id);
                    });
                }
            });
        },
        
        // Rafraîchir la section courante
        refreshCurrentSection: function() {
            const refreshFunctions = {
                'overview': 'refreshOverview',
                'users': 'refreshUsers',
                'games': 'refreshGames',
                'activity': 'refreshActivity',
                'system': 'refreshSystemStats'
            };
            
            const functionName = refreshFunctions[this.state.currentSection];
            if (functionName && window[functionName]) {
                window[functionName]();
            }
        },
        
        // Afficher le dialogue d'export
        showExportDialog: function() {
            const dialog = document.createElement('div');
            dialog.className = 'modal-overlay active';
            dialog.innerHTML = `
                <div class="modal-content">
                    <div class="modal-header">
                        <h2 class="modal-title">Exporter les données</h2>
                        <button class="modal-close">&times;</button>
                    </div>
                    <div class="export-options">
                        <button class="btn btn-primary btn-block" onclick="exportData('users')">
                            👥 Exporter les utilisateurs
                        </button>
                        <button class="btn btn-primary btn-block" onclick="exportData('games')">
                            🎮 Exporter les parties
                        </button>
                        <button class="btn btn-outline btn-block" onclick="exportLogs()">
                            📋 Exporter les logs
                        </button>
                    </div>
                </div>
            `;
            
            document.body.appendChild(dialog);
            
            // Fermeture
            dialog.querySelector('.modal-close').addEventListener('click', () => {
                document.body.removeChild(dialog);
            });
            
            dialog.addEventListener('click', (e) => {
                if (e.target === dialog) {
                    document.body.removeChild(dialog);
                }
            });
        },
        
        // Initialisation du rafraîchissement automatique
        initAutoRefresh: function() {
            // Rafraîchissement des statistiques globales
            this.state.refreshTimers.set('overview', setInterval(() => {
                if (this.state.currentSection === 'overview') {
                    this.refreshOverviewStats();
                }
            }, AdminConfig.refreshInterval));
            
            // Rafraîchissement de l'activité
            this.state.refreshTimers.set('activity', setInterval(() => {
                if (this.state.currentSection === 'activity') {
                    this.refreshActivityFeed();
                }
            }, AdminConfig.refreshInterval / 2)); // Plus fréquent pour l'activité
        },
        
        // Rafraîchissement des stats de vue d'ensemble
        refreshOverviewStats: function() {
            fetch(window.location.href)
                .then(response => response.text())
                .then(html => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    const newStats = doc.querySelectorAll('.stat-value');
                    const currentStats = document.querySelectorAll('.stat-value');
                    
                    newStats.forEach((stat, index) => {
                        if (currentStats[index] && stat.textContent !== currentStats[index].textContent) {
                            // Animation de changement
                            currentStats[index].style.color = 'var(--accent-color)';
                            currentStats[index].style.transform = 'scale(1.1)';
                            currentStats[index].textContent = stat.textContent;
                            
                            setTimeout(() => {
                                currentStats[index].style.color = 'var(--primary-color)';
                                currentStats[index].style.transform = 'scale(1)';
                            }, 500);
                        }
                    });
                })
                .catch(error => {
                    console.log('Erreur rafraîchissement stats:', error);
                });
        },
        
        // Rafraîchissement du flux d'activité
        refreshActivityFeed: function() {
            // Simulation de nouvelles activités
            const activityFeed = document.querySelector('.activity-feed');
            if (activityFeed && Math.random() < 0.1) { // 10% de chance
                this.addNewActivity({
                    type: Math.random() > 0.5 ? 'user_created' : 'game_created',
                    details: 'Nouvel élément',
                    date_action: new Date().toISOString()
                });
            }
        },
        
        // Ajouter une nouvelle activité
        addNewActivity: function(activity) {
            const activityFeed = document.querySelector('.activity-feed');
            if (!activityFeed) return;
            
            const activityItem = document.createElement('div');
            activityItem.className = 'activity-item';
            activityItem.style.opacity = '0';
            activityItem.innerHTML = `
                <div class="activity-icon">
                    ${activity.type === 'user_created' ? '👤' : '🎮'}
                </div>
                <div class="activity-content">
                    <div class="activity-title">
                        ${activity.type === 'user_created' ? 'Nouvel utilisateur' : 'Nouvelle partie'} : 
                        <strong>${activity.details}</strong>
                    </div>
                    <div class="activity-time">
                        ${new Date(activity.date_action).toLocaleString()}
                    </div>
                </div>
            `;
            
            // Insérer en haut
            activityFeed.insertBefore(activityItem, activityFeed.firstChild);
            
            // Animation d'apparition
            setTimeout(() => {
                activityItem.style.transition = 'all 0.5s ease';
                activityItem.style.opacity = '1';
            }, 100);
            
            // Supprimer les anciens éléments (garder max 10)
            const items = activityFeed.querySelectorAll('.activity-item');
            if (items.length > 10) {
                items[items.length - 1].remove();
            }
        },
        
        // Rafraîchissement des tableaux
        refreshUsersTable: function() {
            this.refreshTable('usersTable', 'users');
        },
        
        refreshGamesTable: function() {
            this.refreshTable('gamesTable', 'games');
        },
        
        // Rafraîchissement générique de tableau
        refreshTable: function(tableId, dataType) {
            const table = document.getElementById(tableId);
            if (!table) return;
            
            const tbody = table.querySelector('tbody');
            const originalContent = tbody.innerHTML;
            
            // Indicateur de chargement
            tbody.innerHTML = '<tr><td colspan="100%" class="loading">Rafraîchissement...</td></tr>';
            
            // Simuler le chargement (dans un vrai contexte, faire une requête AJAX)
            setTimeout(() => {
                tbody.innerHTML = originalContent;
                this.bindTableEvents();
                this.showNotification(`Tableau ${dataType} rafraîchi`, 'info', 2000);
            }, 1000);
        },
        
        // Initialisation des mises à jour temps réel
        initRealTimeUpdates: function() {
            // WebSocket ou Server-Sent Events pourraient être utilisés ici
            // Pour la démo, on simule avec des timers
            
            setInterval(() => {
                this.simulateRealTimeUpdate();
            }, 10000); // Toutes les 10 secondes
        },
        
        // Simulation de mise à jour temps réel
        simulateRealTimeUpdate: function() {
            if (Math.random() < 0.3) { // 30% de chance
                const updateTypes = ['user_online', 'game_started', 'game_finished'];
                const type = updateTypes[Math.floor(Math.random() * updateTypes.length)];
                
                this.showRealTimeNotification(type);
            }
        },
        
        // Notification temps réel
        showRealTimeNotification: function(type) {
            const messages = {
                'user_online': '👤 Nouvel utilisateur connecté',
                'game_started': '🎮 Partie démarrée',
                'game_finished': '🏆 Partie terminée'
            };
            
            const message = messages[type] || 'Mise à jour';
            this.showNotification(message, 'info', 3000);
            
            // Mettre à jour les statistiques si on est sur la vue d'ensemble
            if (this.state.currentSection === 'overview') {
                this.refreshOverviewStats();
            }
        },
        
        // Initialisation des graphiques
        initCharts: function() {
            // Si Chart.js est disponible, initialiser les graphiques
            if (typeof Chart !== 'undefined') {
                this.createOverviewCharts();
            }
        },
        
        // Créer les graphiques de vue d'ensemble
        createOverviewCharts: function() {
            // Graphique d'évolution des utilisateurs
            const userChart = this.createChart('userStatsChart', {
                type: 'line',
                data: {
                    labels: ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Jun'],
                    datasets: [{
                        label: 'Nouveaux utilisateurs',
                        data: [12, 19, 3, 5, 2, 3],
                        borderColor: 'var(--primary-color)',
                        backgroundColor: 'rgba(255, 75, 43, 0.1)'
                    }]
                }
            });
            
            // Graphique de répartition des parties
            const gameChart = this.createChart('gameStatsChart', {
                type: 'doughnut',
                data: {
                    labels: ['En cours', 'Terminées', 'En attente'],
                    datasets: [{
                        data: [30, 60, 10],
                        backgroundColor: [
                            'var(--primary-color)',
                            'var(--success-color)',
                            'var(--warning-color)'
                        ]
                    }]
                }
            });
            
            this.state.charts.set('users', userChart);
            this.state.charts.set('games', gameChart);
        },
        
        // Créer un graphique
        createChart: function(canvasId, config) {
            const canvas = document.getElementById(canvasId);
            if (canvas && typeof Chart !== 'undefined') {
                return new Chart(canvas, config);
            }
            return null;
        },
        
        // Notification
        showNotification: function(message, type = 'info', duration = 5000) {
            if (window.TheMind.Profile && window.TheMind.Profile.showNotification) {
                window.TheMind.Profile.showNotification(message, type, duration);
            } else {
                // Fallback simple
                const notification = document.createElement('div');
                notification.style.cssText = `
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    background: var(--card-bg);
                    border: 1px solid var(--border-color);
                    border-radius: var(--border-radius);
                    padding: 1rem;
                    box-shadow: var(--shadow-lg);
                    z-index: 10000;
                    max-width: 300px;
                `;
                notification.textContent = message;
                
                document.body.appendChild(notification);
                
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.remove();
                    }
                }, duration);
            }
        },
        
        // Nettoyage lors de la destruction
        destroy: function() {
            // Nettoyer les timers
            this.state.refreshTimers.forEach(timer => {
                clearInterval(timer);
            });
            
            // Nettoyer les graphiques
            this.state.charts.forEach(chart => {
                if (chart && chart.destroy) {
                    chart.destroy();
                }
            });
            
            console.log('Admin module destroyed');
        }
    };
    
    // Fonctions utilitaires globales
    window.AdminUtils = {
        // Formatage des nombres
        formatNumber: function(num) {
            return new Intl.NumberFormat().format(num);
        },
        
        // Formatage des dates
        formatDate: function(date) {
            return new Date(date).toLocaleDateString();
        },
        
        // Formatage des tailles de fichier
        formatFileSize: function(bytes) {
            const units = ['B', 'KB', 'MB', 'GB'];
            let size = bytes;
            let unitIndex = 0;
            
            while (size >= 1024 && unitIndex < units.length - 1) {
                size /= 1024;
                unitIndex++;
            }
            
            return `${Math.round(size * 100) / 100} ${units[unitIndex]}`;
        },
        
        // Génération de mot de passe
        generatePassword: function(length = 12) {
            const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*';
            let result = '';
            for (let i = 0; i < length; i++) {
                result += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            return result;
        },
        
        // Validation des données
        validateEmail: function(email) {
            const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return re.test(email);
        },
        
        // Debounce pour les recherches
        debounce: function(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }
    };
    
    // Initialisation automatique
    document.addEventListener('DOMContentLoaded', function() {
        if (window.TheMind && window.TheMind.config.pageType === 'admin') {
            window.TheMind.Admin.init();
        }
    });
    
    // Nettoyage avant fermeture
    window.addEventListener('beforeunload', function() {
        if (window.TheMind.Admin) {
            window.TheMind.Admin.destroy();
        }
    });
    
})();