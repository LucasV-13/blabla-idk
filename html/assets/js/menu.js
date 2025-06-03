/**
 * JavaScript pour la gestion du menu The Mind
 */

class MenuManager {
    constructor() {
        this.init();
    }

    init() {
        // Attendre que le DOM soit chargé
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.setupEventListeners());
        } else {
            this.setupEventListeners();
        }
    }

    setupEventListeners() {
        // Éléments DOM
        this.elements = {
            settingsModal: document.getElementById('settingsModal'),
            rulesModal: document.getElementById('rulesModal'),
            settingsBtn: document.getElementById('settingsBtn'),
            rulesBtn: document.getElementById('rulesBtn'),
            dashboardBtn: document.getElementById('dashboardBtn'),
            profileBtnModal: document.getElementById('profileBtnModal'),
            closeSettings: document.getElementById('closeSettings'),
            closeRules: document.getElementById('closeRules'),
            volumeSlider: document.getElementById('volumeSlider'),
            languageSelect: document.getElementById('languageSelect')
        };

        // Configuration des écouteurs d'événements
        this.setupButtonListeners();
        this.setupModalListeners();
        this.setupSettingsListeners();
        this.setupKeyboardListeners();

        // Initialiser les préférences
        this.loadPreferences();
    }

    setupButtonListeners() {
        // Bouton des paramètres
        if (this.elements.settingsBtn) {
            this.elements.settingsBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.openModal('settings');
            });
        }

        // Bouton des règles
        if (this.elements.rulesBtn) {
            this.elements.rulesBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.openModal('rules');
            });
        }

        // Bouton tableau de bord
        if (this.elements.dashboardBtn) {
            this.elements.dashboardBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.navigateTo('pages/dashboard.php');
            });
        }

        // Bouton profil dans la modal
        if (this.elements.profileBtnModal) {
            this.elements.profileBtnModal.addEventListener('click', (e) => {
                e.preventDefault();
                this.closeModal('settings');
                this.navigateTo('pages/profil/profil.php');
            });
        }
    }

    setupModalListeners() {
        // Boutons de fermeture
        if (this.elements.closeSettings) {
            this.elements.closeSettings.addEventListener('click', () => {
                this.closeModal('settings');
            });
        }

        if (this.elements.closeRules) {
            this.elements.closeRules.addEventListener('click', () => {
                this.closeModal('rules');
            });
        }

        // Fermeture en cliquant à l'extérieur
        window.addEventListener('click', (event) => {
            if (event.target === this.elements.settingsModal) {
                this.closeModal('settings');
            }
            if (event.target === this.elements.rulesModal) {
                this.closeModal('rules');
            }
        });
    }

    setupSettingsListeners() {
        // Contrôle du volume
        if (this.elements.volumeSlider) {
            this.elements.volumeSlider.addEventListener('input', () => {
                this.updateVolume();
            });

            this.elements.volumeSlider.addEventListener('change', () => {
                this.savePreference('volume', this.elements.volumeSlider.value);
            });
        }

        // Sélecteur de langue
        if (this.elements.languageSelect) {
            this.elements.languageSelect.addEventListener('change', () => {
                this.changeLanguage();
            });
        }
    }

    setupKeyboardListeners() {
        // Raccourcis clavier
        document.addEventListener('keydown', (e) => {
            // Échap pour fermer les modals
            if (e.key === 'Escape') {
                this.closeAllModals();
            }

            // Ctrl/Cmd + raccourcis
            if (e.ctrlKey || e.metaKey) {
                switch (e.key) {
                    case ',': // Ctrl+, pour les paramètres
                        e.preventDefault();
                        this.openModal('settings');
                        break;
                    case 'h': // Ctrl+H pour l'aide/règles
                        e.preventDefault();
                        this.openModal('rules');
                        break;
                    case 'd': // Ctrl+D pour le dashboard
                        e.preventDefault();
                        this.navigateTo('pages/dashboard.php');
                        break;
                }
            }
        });
    }

    // === GESTION DES MODALS ===

    openModal(modalType) {
        const modal = modalType === 'settings' ? this.elements.settingsModal : this.elements.rulesModal;
        
        if (modal) {
            modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
            
            // Animation d'ouverture
            modal.style.opacity = '0';
            modal.style.transform = 'scale(0.9)';
            
            requestAnimationFrame(() => {
                modal.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                modal.style.opacity = '1';
                modal.style.transform = 'scale(1)';
            });

            // Focus sur le premier élément interactif
            const firstFocusable = modal.querySelector('button, input, select, textarea, [tabindex]:not([tabindex="-1"])');
            if (firstFocusable) {
                firstFocusable.focus();
            }

            // Log de l'ouverture
            this.logEvent('modal_opened', { type: modalType });
        }
    }

    closeModal(modalType) {
        const modal = modalType === 'settings' ? this.elements.settingsModal : this.elements.rulesModal;
        
        if (modal && modal.style.display === 'block') {
            // Animation de fermeture
            modal.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
            modal.style.opacity = '0';
            modal.style.transform = 'scale(0.9)';
            
            setTimeout(() => {
                modal.style.display = 'none';
                document.body.style.overflow = 'auto';
                modal.style.transition = '';
            }, 300);

            // Log de la fermeture
            this.logEvent('modal_closed', { type: modalType });
        }
    }

    closeAllModals() {
        this.closeModal('settings');
        this.closeModal('rules');
    }

    // === GESTION DES PRÉFÉRENCES ===

    loadPreferences() {
        // Charger le volume depuis localStorage
        const savedVolume = localStorage.getItem('themind_volume');
        if (savedVolume && this.elements.volumeSlider) {
            this.elements.volumeSlider.value = savedVolume;
            this.updateVolume();
        }

        // Charger la langue depuis la session (déjà gérée côté serveur)
        // Mais on peut synchroniser l'affichage
        if (window.THEMIND && window.THEMIND.LANGUAGE && this.elements.languageSelect) {
            this.elements.languageSelect.value = window.THEMIND.LANGUAGE;
        }
    }

    updateVolume() {
        if (!this.elements.volumeSlider) return;

        const volume = this.elements.volumeSlider.value / 100;
        
        // Appliquer le volume à tous les éléments audio
        document.querySelectorAll('audio').forEach(audio => {
            audio.volume = volume;
        });

        // Sauvegarder dans localStorage
        localStorage.setItem('themind_volume', this.elements.volumeSlider.value);

        // Log du changement de volume
        this.logEvent('volume_changed', { volume: volume });
    }

    changeLanguage() {
        if (!this.elements.languageSelect) return;

        const selectedLanguage = this.elements.languageSelect.value;
        
        this.savePreference('language', selectedLanguage).then(() => {
            // Afficher un message de chargement
            this.showNotification('Changement de langue...', 'info');
            
            // Recharger la page pour appliquer la nouvelle langue
            setTimeout(() => {
                window.location.reload();
            }, 500);
        }).catch(error => {
            console.error('Erreur lors du changement de langue:', error);
            this.showNotification('Erreur lors du changement de langue', 'error');
        });
    }

    savePreference(type, value) {
        const csrfToken = this.getCSRFToken();
        const baseUrl = this.getBaseUrl();

        return fetch(baseUrl + 'save/save_preferences.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                preference: type,
                value: value,
                csrf_token: csrfToken
            })
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Erreur réseau');
            }
            return response.text();
        })
        .catch(error => {
            console.error('Erreur lors de la sauvegarde des préférences:', error);
            throw error;
        });
    }

    // === NAVIGATION ===

    navigateTo(path) {
        const baseUrl = this.getBaseUrl();
        window.location.href = baseUrl + path;
    }

    // === NOTIFICATIONS ===

    showNotification(message, type = 'info', duration = 3000) {
        // Supprimer les notifications existantes
        const existingNotifications = document.querySelectorAll('.flash-message');
        existingNotifications.forEach(notif => notif.remove());

        // Créer la nouvelle notification
        const notification = document.createElement('div');
        notification.className = `flash-message flash-${type}`;
        notification.textContent = message;

        // Ajouter au DOM
        document.body.appendChild(notification);

        // Animation d'entrée
        notification.style.opacity = '0';
        notification.style.transform = 'translateY(-20px)';
        
        requestAnimationFrame(() => {
            notification.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
            notification.style.opacity = '1';
            notification.style.transform = 'translateY(0)';
        });

        // Suppression automatique
        setTimeout(() => {
            notification.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
            notification.style.opacity = '0';
            notification.style.transform = 'translateY(-20px)';
            
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 300);
        }, duration);
    }

    // === UTILITAIRES ===

    getCSRFToken() {
        // Essayer de récupérer le token depuis les variables globales
        if (window.THEMIND && window.THEMIND.CSRF_TOKEN) {
            return window.THEMIND.CSRF_TOKEN;
        }

        // Essayer de récupérer depuis un élément caché
        const tokenElement = document.getElementById('csrf_token');
        if (tokenElement) {
            return tokenElement.value;
        }

        // Essayer de récupérer depuis un meta tag
        const metaToken = document.querySelector('meta[name="csrf-token"]');
        if (metaToken) {
            return metaToken.getAttribute('content');
        }

        console.warn('Token CSRF non trouvé');
        return '';
    }

    getBaseUrl() {
        // Essayer de récupérer l'URL de base depuis les variables globales
        if (window.THEMIND && window.THEMIND.SITE_URL) {
            return window.THEMIND.SITE_URL;
        }

        // Essayer de récupérer depuis un élément caché
        const baseUrlElement = document.getElementById('base_url');
        if (baseUrlElement) {
            return baseUrlElement.value;
        }

        // Fallback
        return '/html/';
    }

    logEvent(eventName, data = {}) {
        if (window.THEMIND && window.THEMIND.DEBUG_MODE) {
            console.log(`[Menu] ${eventName}:`, data);
        }

        // Envoyer à un service d'analytics si nécessaire
        // this.sendAnalytics(eventName, data);
    }

    // === GESTION DES ERREURS ===

    handleError(error, context = '') {
        console.error(`[Menu Error] ${context}:`, error);
        
        // Afficher une notification d'erreur à l'utilisateur si approprié
        if (context.includes('preference') || context.includes('language')) {
            this.showNotification('Une erreur est survenue. Veuillez réessayer.', 'error');
        }
    }

    // === ACCESSIBILITÉ ===

    setupAccessibility() {
        // Gestion du focus pour les modals
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Tab') {
                const activeModal = document.querySelector('.modal[style*="block"]');
                if (activeModal) {
                    this.trapFocus(activeModal, e);
                }
            }
        });
    }

    trapFocus(modal, event) {
        const focusableElements = modal.querySelectorAll(
            'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
        );
        
        const firstFocusable = focusableElements[0];
        const lastFocusable = focusableElements[focusableElements.length - 1];

        if (event.shiftKey) {
            if (document.activeElement === firstFocusable) {
                lastFocusable.focus();
                event.preventDefault();
            }
        } else {
            if (document.activeElement === lastFocusable) {
                firstFocusable.focus();
                event.preventDefault();
            }
        }
    }

    // === RESPONSIVE ===

    handleResize() {
        // Ajuster les modals sur mobile
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => {
            if (window.innerWidth <= 768) {
                modal.classList.add('mobile-modal');
            } else {
                modal.classList.remove('mobile-modal');
            }
        });
    }

    // === INITIALISATION AVANCÉE ===

    setupAdvancedFeatures() {
        // Gestion du redimensionnement
        window.addEventListener('resize', this.handleResize.bind(this));
        this.handleResize();

        // Configuration de l'accessibilité
        this.setupAccessibility();

        // Précharger les sons si nécessaire
        this.preloadSounds();

        // Configuration des animations
        this.setupAnimations();
    }

    preloadSounds() {
        const sounds = ['click', 'notification', 'error'];
        sounds.forEach(sound => {
            const audio = new Audio(`${this.getBaseUrl()}assets/sounds/${sound}.mp3`);
            audio.preload = 'auto';
            audio.volume = (this.elements.volumeSlider?.value || 50) / 100;
        });
    }

    setupAnimations() {
        // Désactiver les animations si l'utilisateur préfère un mouvement réduit
        if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            document.documentElement.style.setProperty('--animation-duration', '0s');
        }
    }

    playSound(soundName) {
        if (!this.elements.volumeSlider) return;
        
        const volume = this.elements.volumeSlider.value / 100;
        if (volume === 0) return;

        const audio = new Audio(`${this.getBaseUrl()}assets/sounds/${soundName}.mp3`);
        audio.volume = volume;
        audio.play().catch(error => {
            console.log('Impossible de jouer le son:', error);
        });
    }
}

// === FONCTIONS UTILITAIRES GLOBALES ===

/**
 * Afficher une notification
 */
function showNotification(message, type = 'info', duration = 3000) {
    if (window.menuManager) {
        window.menuManager.showNotification(message, type, duration);
    }
}

/**
 * Jouer un son
 */
function playMenuSound(soundName) {
    if (window.menuManager) {
        window.menuManager.playSound(soundName);
    }
}

/**
 * Fermer toutes les modals
 */
function closeAllModals() {
    if (window.menuManager) {
        window.menuManager.closeAllModals();
    }
}

// === INITIALISATION ===

// Initialiser le gestionnaire de menu dès que possible
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.menuManager = new MenuManager();
        window.menuManager.setupAdvancedFeatures();
    });
} else {
    window.menuManager = new MenuManager();
    window.menuManager.setupAdvancedFeatures();
}

// Export pour les modules si nécessaire
if (typeof module !== 'undefined' && module.exports) {
    module.exports = MenuManager;
}