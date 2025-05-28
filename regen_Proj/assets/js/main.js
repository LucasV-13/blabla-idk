/**
 * JavaScript principal pour The Mind
 * Configuration globale et utilitaires communs
 */

// Espace de noms global pour l'application
window.TheMind = window.TheMind || {};

// Configuration globale
TheMind.config = {
    // URLs
    baseUrl: '/',
    apiUrl: '/api/',
    assetsUrl: '/assets/',
    
    // Paramètres de jeu
    refreshInterval: 3000, // 3 secondes
    dashboardRefreshInterval: 30000, // 30 secondes
    
    // Sons
    sounds: {
        enabled: true,
        volume: 0.5,
        preload: true
    },
    
    // Animations
    animations: {
        enabled: true,
        duration: 300,
        easing: 'ease-out'
    },
    
    // Debug
    debug: false
};

// Utilitaires globaux
TheMind.utils = {
    /**
     * Récupère le token CSRF depuis le DOM
     */
    getCsrfToken() {
        const tokenInput = document.querySelector('input[name="csrf_token"]');
        const tokenMeta = document.querySelector('meta[name="csrf-token"]');
        return tokenInput?.value || tokenMeta?.content || '';
    },
    
    /**
     * Effectue une requête AJAX vers l'API
     */
    async fetchAPI(endpoint, options = {}) {
        const defaultOptions = {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        };
        
        // Ajouter le token CSRF pour les requêtes POST
        if (options.method === 'POST') {
            if (options.body instanceof FormData) {
                options.body.append('csrf_token', this.getCsrfToken());
            } else {
                defaultOptions.headers['Content-Type'] = 'application/x-www-form-urlencoded';
                if (typeof options.body === 'string') {
                    options.body += `&csrf_token=${encodeURIComponent(this.getCsrfToken())}`;
                } else {
                    options.body = `csrf_token=${encodeURIComponent(this.getCsrfToken())}`;
                }
            }
        }
        
        try {
            const response = await fetch(TheMind.config.apiUrl + endpoint, {
                ...defaultOptions,
                ...options
            });
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const contentType = response.headers.get('content-type');
            if (contentType && contentType.includes('application/json')) {
                return await response.json();
            } else {
                return await response.text();
            }
        } catch (error) {
            if (TheMind.config.debug) {
                console.error('API Error:', error);
            }
            throw error;
        }
    },
    
    /**
     * Affiche une notification temporaire
     */
    showNotification(message, type = 'info', duration = 3000) {
        // Supprimer les notifications existantes
        const existingNotifications = document.querySelectorAll('.notification');
        existingNotifications.forEach(notif => notif.remove());
        
        const notification = document.createElement('div');
        notification.className = `notification notification--${type}`;
        notification.innerHTML = `
            <div class="notification__content">
                <span class="notification__icon">${this.getNotificationIcon(type)}</span>
                <span class="notification__message">${message}</span>
                <button class="notification__close" onclick="this.parentElement.parentElement.remove()">×</button>
            </div>
        `;
        
        // Styles pour la notification
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            background: ${this.getNotificationColor(type)};
            color: white;
            padding: 15px 20px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            transform: translateX(100%);
            transition: transform 0.3s ease;
            max-width: 400px;
            word-wrap: break-word;
        `;
        
        document.body.appendChild(notification);
        
        // Animation d'entrée
        setTimeout(() => {
            notification.style.transform = 'translateX(0)';
        }, 100);
        
        // Suppression automatique
        if (duration > 0) {
            setTimeout(() => {
                notification.style.transform = 'translateX(100%)';
                setTimeout(() => notification.remove(), 300);
            }, duration);
        }
        
        return notification;
    },
    
    /**
     * Récupère l'icône pour un type de notification
     */
    getNotificationIcon(type) {
        const icons = {
            'success': '✓',
            'error': '✗',
            'warning': '⚠',
            'info': 'ℹ'
        };
        return icons[type] || icons.info;
    },
    
    /**
     * Récupère la couleur pour un type de notification
     */
    getNotificationColor(type) {
        const colors = {
            'success': '#4CAF50',
            'error': '#f44336',
            'warning': '#ff9800',
            'info': '#2196F3'
        };
        return colors[type] || colors.info;
    },
    
    /**
     * Joue un son
     */
    playSound(soundName) {
        if (!TheMind.config.sounds.enabled) return;
        
        try {
            const audio = new Audio(`${TheMind.config.assetsUrl}sounds/effects/${soundName}.mp3`);
            audio.volume = TheMind.config.sounds.volume;
            audio.play().catch(e => {
                if (TheMind.config.debug) {
                    console.log('Son non disponible:', soundName);
                }
            });
        } catch (error) {
            if (TheMind.config.debug) {
                console.error('Erreur son:', error);
            }
        }
    },
    
    /**
     * Formate un nombre avec des espaces
     */
    formatNumber(number) {
        return new Intl.NumberFormat('fr-FR').format(number);
    },
    
    /**
     * Échappe les caractères HTML
     */
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    },
    
    /**
     * Débounce une fonction
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
     * Throttle une fonction
     */
    throttle(func, limit) {
        let inThrottle;
        return function() {
            const args = arguments;
            const context = this;
            if (!inThrottle) {
                func.apply(context, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        };
    },
    
    /**
     * Vérifie si un élément est visible dans le viewport
     */
    isElementInViewport(element) {
        const rect = element.getBoundingClientRect();
        return (
            rect.top >= 0 &&
            rect.left >= 0 &&
            rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
            rect.right <= (window.innerWidth || document.documentElement.clientWidth)
        );
    },
    
    /**
     * Anime un élément avec CSS
     */
    animate(element, animationName, duration = 300) {
        if (!TheMind.config.animations.enabled) return Promise.resolve();
        
        return new Promise((resolve) => {
            element.style.animationDuration = `${duration}ms`;
            element.classList.add(animationName);
            
            const handleAnimationEnd = () => {
                element.classList.remove(animationName);
                element.removeEventListener('animationend', handleAnimationEnd);
                resolve();
            };
            
            element.addEventListener('animationend', handleAnimationEnd);
        });
    },
    
    /**
     * Copie un texte dans le presse-papiers
     */
    async copyToClipboard(text) {
        try {
            await navigator.clipboard.writeText(text);
            this.showNotification('Copié dans le presse-papiers', 'success', 2000);
        } catch (error) {
            // Fallback pour les navigateurs plus anciens
            const textarea = document.createElement('textarea');
            textarea.value = text;
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            document.body.removeChild(textarea);
            this.showNotification('Copié dans le presse-papiers', 'success', 2000);
        }
    }
};

// Gestionnaire d'événements global
TheMind.events = {
    /**
     * Initialise les événements globaux
     */
    init() {
        this.setupModalHandlers();
        this.setupFormHandlers();
        this.setupKeyboardHandlers();
        this.setupAccessibilityHandlers();
        this.setupErrorHandlers();
        
        if (TheMind.config.debug) {
            console.log('TheMind events initialized');
        }
    },
    
    /**
     * Gestion des modales
     */
    setupModalHandlers() {
        // Fermeture des modales en cliquant sur l'overlay
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('modal')) {
                this.closeModal(e.target);
            }
        });
        
        // Fermeture des modales avec les boutons de fermeture
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('modal__close')) {
                const modal = e.target.closest('.modal');
                if (modal) this.closeModal(modal);
            }
        });
        
        // Fermeture des modales avec Échap
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                const openModal = document.querySelector('.modal.show');
                if (openModal) this.closeModal(openModal);
            }
        });
    },
    
    /**
     * Ouvre une modale
     */
    openModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('show');
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
            
            // Focus sur le premier élément focusable
            const focusableElement = modal.querySelector('button, input, select, textarea, [tabindex]:not([tabindex="-1"])');
            if (focusableElement) {
                focusableElement.focus();
            }
        }
    },
    
    /**
     * Ferme une modale
     */
    closeModal(modal) {
        if (typeof modal === 'string') {
            modal = document.getElementById(modal);
        }
        
        if (modal) {
            modal.classList.remove('show');
            setTimeout(() => {
                modal.style.display = 'none';
                document.body.style.overflow = '';
            }, 300);
        }
    },
    
    /**
     * Gestion automatique des formulaires
     */
    setupFormHandlers() {
        // Ajout automatique des tokens CSRF
        document.addEventListener('submit', (e) => {
            const form = e.target;
            if (form.tagName === 'FORM' && !form.querySelector('input[name="csrf_token"]')) {
                const csrfInput = document.createElement('input');
                csrfInput.type = 'hidden';
                csrfInput.name = 'csrf_token';
                csrfInput.value = TheMind.utils.getCsrfToken();
                form.appendChild(csrfInput);
            }
        });
        
        // Validation en temps réel
        document.addEventListener('input', this.handleInputValidation.bind(this));
        document.addEventListener('blur', this.handleInputValidation.bind(this));
    },
    
    /**
     * Validation des champs de formulaire
     */
    handleInputValidation(e) {
        const input = e.target;
        if (!input.closest('.form-group')) return;
        
        const formGroup = input.closest('.form-group');
        const errorElement = formGroup.querySelector('.form-error');
        
        // Supprimer les messages d'erreur existants
        if (errorElement) {
            errorElement.remove();
        }
        
        // Retirer les classes d'état
        input.classList.remove('form-input--error', 'form-input--success');
        
        // Validation basique
        if (input.hasAttribute('required') && !input.value.trim()) {
            if (e.type === 'blur') {
                this.showFieldError(input, 'Ce champ est requis');
            }
            return;
        }
        
        // Validation email
        if (input.type === 'email' && input.value) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(input.value)) {
                this.showFieldError(input, 'Veuillez saisir une adresse email valide');
                return;
            }
        }
        
        // Validation mot de passe
        if (input.type === 'password' && input.value && input.value.length < 6) {
            this.showFieldError(input, 'Le mot de passe doit contenir au moins 6 caractères');
            return;
        }
        
        // Validation confirmation mot de passe
        if (input.name === 'confirm_password' && input.value) {
            const passwordField = document.querySelector('input[name="password"]');
            if (passwordField && input.value !== passwordField.value) {
                this.showFieldError(input, 'Les mots de passe ne correspondent pas');
                return;
            }
        }
        
        // Si tout est bon
        if (input.value.trim()) {
            input.classList.add('form-input--success');
        }
    },
    
    /**
     * Affiche une erreur sur un champ
     */
    showFieldError(input, message) {
        input.classList.add('form-input--error');
        
        const formGroup = input.closest('.form-group');
        const errorElement = document.createElement('span');
        errorElement.className = 'form-error';
        errorElement.textContent = message;
        
        formGroup.appendChild(errorElement);
    },
    
    /**
     * Gestion des raccourcis clavier
     */
    setupKeyboardHandlers() {
        document.addEventListener('keydown', (e) => {
            // Ctrl/Cmd + Enter pour soumettre les formulaires
            if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                const activeForm = document.activeElement?.closest('form');
                if (activeForm) {
                    const submitButton = activeForm.querySelector('button[type="submit"], input[type="submit"]');
                    if (submitButton) {
                        submitButton.click();
                    }
                }
            }
        });
    },
    
    /**
     * Gestion de l'accessibilité
     */
    setupAccessibilityHandlers() {
        // Navigation au clavier dans les listes
        document.addEventListener('keydown', (e) => {
            if (e.target.getAttribute('role') === 'listbox' || e.target.closest('[role="listbox"]')) {
                const items = e.target.querySelectorAll('[role="option"]');
                const currentIndex = Array.from(items).indexOf(document.activeElement);
                
                if (e.key === 'ArrowDown' && currentIndex < items.length - 1) {
                    e.preventDefault();
                    items[currentIndex + 1].focus();
                } else if (e.key === 'ArrowUp' && currentIndex > 0) {
                    e.preventDefault();
                    items[currentIndex - 1].focus();
                }
            }
        });
    },
    
    /**
     * Gestion globale des erreurs
     */
    setupErrorHandlers() {
        // Gestion des erreurs JavaScript
        window.addEventListener('error', (e) => {
            if (TheMind.config.debug) {
                console.error('Erreur JavaScript:', e.error);
            }
        });
        
        // Gestion des promesses rejetées
        window.addEventListener('unhandledrejection', (e) => {
            if (TheMind.config.debug) {
                console.error('Promise rejetée:', e.reason);
            }
            e.preventDefault();
        });
    }
};

// Gestionnaire de préférences utilisateur
TheMind.preferences = {
    /**
     * Sauvegarde une préférence
     */
    async save(key, value) {
        try {
            // Sauvegarder localement
            localStorage.setItem(`themind_${key}`, JSON.stringify(value));
            
            // Sauvegarder sur le serveur si connecté
            if (TheMind.utils.getCsrfToken()) {
                await TheMind.utils.fetchAPI('common/save-preferences.php', {
                    method: 'POST',
                    body: `preference=${encodeURIComponent(key)}&value=${encodeURIComponent(value)}`
                });
            }
        } catch (error) {
            if (TheMind.config.debug) {
                console.error('Erreur sauvegarde préférence:', error);
            }
        }
    },
    
    /**
     * Récupère une préférence
     */
    get(key, defaultValue = null) {
        try {
            const stored = localStorage.getItem(`themind_${key}`);
            return stored ? JSON.parse(stored) : defaultValue;
        } catch (error) {
            return defaultValue;
        }
    },
    
    /**
     * Applique les préférences sauvegardées
     */
    apply() {
        // Volume
        const volume = this.get('volume', 50);
        TheMind.config.sounds.volume = volume / 100;
        
        // Sons activés/désactivés
        const soundsEnabled = this.get('sounds_enabled', true);
        TheMind.config.sounds.enabled = soundsEnabled;
        
        // Animations
        const animationsEnabled = this.get('animations_enabled', true);
        TheMind.config.animations.enabled = animationsEnabled;
        
        // Appliquer les préférences au DOM
        document.documentElement.style.setProperty('--animation-duration', 
            animationsEnabled ? '0.3s' : '0s');
    }
};

// Initialisation au chargement du DOM
document.addEventListener('DOMContentLoaded', () => {
    // Initialiser les événements
    TheMind.events.init();
    
    // Appliquer les préférences
    TheMind.preferences.apply();
    
    // Détecter les capacités du navigateur
    TheMind.browser = {
        supportsWebP: document.createElement('canvas').toDataURL('image/webp').indexOf('data:image/webp') === 0,
        supportsLocalStorage: (() => {
            try {
                localStorage.setItem('test', 'test');
                localStorage.removeItem('test');
                return true;
            } catch (e) {
                return false;
            }
        })(),
        supportsTouchEvents: 'ontouchstart' in window,
        supportsAudio: (() => {
            try {
                return !!(new Audio());
            } catch (e) {
                return false;
            }
        })()
    };
    
    // Ajuster la configuration selon les capacités
    if (!TheMind.browser.supportsAudio) {
        TheMind.config.sounds.enabled = false;
    }
    
    // Ajouter des classes CSS pour les capacités
    if (TheMind.browser.supportsTouchEvents) {
        document.documentElement.classList.add('touch');
    } else {
        document.documentElement.classList.add('no-touch');
    }
    
    // Gestion du mode réduit pour les animations
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        TheMind.config.animations.enabled = false;
        document.documentElement.classList.add('reduced-motion');
    }
    
    // Gestion de la visibilité de la page
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            // Page cachée - arrêter les animations coûteuses
            TheMind.events.emit('page-hidden');
        } else {
            // Page visible - reprendre les animations
            TheMind.events.emit('page-visible');
        }
    });
    
    // Log d'initialisation
    if (TheMind.config.debug) {
        console.log('TheMind initialized', {
            config: TheMind.config,
            browser: TheMind.browser
        });
    }
});

// Système d'événements personnalisé
TheMind.events.callbacks = {};

TheMind.events.on = function(event, callback) {
    if (!this.callbacks[event]) {
        this.callbacks[event] = [];
    }
    this.callbacks[event].push(callback);
};

TheMind.events.off = function(event, callback) {
    if (this.callbacks[event]) {
        const index = this.callbacks[event].indexOf(callback);
        if (index > -1) {
            this.callbacks[event].splice(index, 1);
        }
    }
};

TheMind.events.emit = function(event, data) {
    if (this.callbacks[event]) {
        this.callbacks[event].forEach(callback => {
            try {
                callback(data);
            } catch (error) {
                if (TheMind.config.debug) {
                    console.error(`Erreur dans le callback de l'événement ${event}:`, error);
                }
            }
        });
    }
};

// Gestionnaire de timer global
TheMind.timers = {
    intervals: new Map(),
    timeouts: new Map(),
    
    setInterval(name, callback, delay) {
        this.clearInterval(name);
        const id = setInterval(callback, delay);
        this.intervals.set(name, id);
        return id;
    },
    
    clearInterval(name) {
        const id = this.intervals.get(name);
        if (id) {
            clearInterval(id);
            this.intervals.delete(name);
        }
    },
    
    setTimeout(name, callback, delay) {
        this.clearTimeout(name);
        const id = setTimeout(() => {
            callback();
            this.timeouts.delete(name);
        }, delay);
        this.timeouts.set(name, id);
        return id;
    },
    
    clearTimeout(name) {
        const id = this.timeouts.get(name);
        if (id) {
            clearTimeout(id);
            this.timeouts.delete(name);
        }
    },
    
    clearAll() {
        this.intervals.forEach(id => clearInterval(id));
        this.timeouts.forEach(id => clearTimeout(id));
        this.intervals.clear();
        this.timeouts.clear();
    }
};

// Gestion des états de chargement
TheMind.loading = {
    show(target = document.body, message = 'Chargement...') {
        const loader = document.createElement('div');
        loader.className = 'loading-overlay';
        loader.innerHTML = `
            <div class="loading-content">
                <div class="loading-spinner"></div>
                <div class="loading-message">${message}</div>
            </div>
        `;
        
        loader.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            backdrop-filter: blur(5px);
        `;
        
        target.appendChild(loader);
        return loader;
    },
    
    hide(loader) {
        if (loader && loader.parentNode) {
            loader.style.opacity = '0';
            setTimeout(() => {
                loader.remove();
            }, 300);
        }
    }
};

// Gestionnaire de cache simple
TheMind.cache = {
    data: new Map(),
    
    set(key, value, ttl = 300000) { // TTL par défaut: 5 minutes
        const expiryTime = Date.now() + ttl;
        this.data.set(key, { value, expiryTime });
    },
    
    get(key) {
        const item = this.data.get(key);
        if (!item) return null;
        
        if (Date.now() > item.expiryTime) {
            this.data.delete(key);
            return null;
        }
        
        return item.value;
    },
    
    delete(key) {
        this.data.delete(key);
    },
    
    clear() {
        this.data.clear();
    },
    
    // Nettoyage automatique des éléments expirés
    cleanup() {
        const now = Date.now();
        for (const [key, item] of this.data.entries()) {
            if (now > item.expiryTime) {
                this.data.delete(key);
            }
        }
    }
};

// Nettoyage automatique du cache toutes les 5 minutes
TheMind.timers.setInterval('cache-cleanup', () => {
    TheMind.cache.cleanup();
}, 300000);

// Gestionnaire de formulaires avancé
TheMind.forms = {
    /**
     * Sérialise un formulaire en objet
     */
    serialize(form) {
        const data = {};
        const formData = new FormData(form);
        
        for (const [key, value] of formData.entries()) {
            if (data[key]) {
                // Si la clé existe déjà, créer un tableau
                if (Array.isArray(data[key])) {
                    data[key].push(value);
                } else {
                    data[key] = [data[key], value];
                }
            } else {
                data[key] = value;
            }
        }
        
        return data;
    },
    
    /**
     * Valide un formulaire complet
     */
    validate(form) {
        const errors = [];
        const inputs = form.querySelectorAll('input, select, textarea');
        
        inputs.forEach(input => {
            // Effacer les erreurs précédentes
            input.classList.remove('form-input--error');
            const errorElement = input.parentNode.querySelector('.form-error');
            if (errorElement) errorElement.remove();
            
            // Validation
            if (input.hasAttribute('required') && !input.value.trim()) {
                errors.push({ field: input.name, message: 'Ce champ est requis' });
                TheMind.events.showFieldError(input, 'Ce champ est requis');
            }
        });
        
        return {
            isValid: errors.length === 0,
            errors
        };
    },
    
    /**
     * Soumet un formulaire via AJAX
     */
    async submit(form, options = {}) {
        const validation = this.validate(form);
        if (!validation.isValid) {
            return { success: false, errors: validation.errors };
        }
        
        const formData = new FormData(form);
        const loader = options.showLoader ? TheMind.loading.show() : null;
        
        try {
            const response = await fetch(form.action || window.location.href, {
                method: form.method || 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            
            const result = await response.json();
            
            if (loader) TheMind.loading.hide(loader);
            
            if (result.success) {
                if (options.successMessage) {
                    TheMind.utils.showNotification(options.successMessage, 'success');
                }
                if (options.resetForm) {
                    form.reset();
                }
            } else {
                if (options.errorMessage || result.message) {
                    TheMind.utils.showNotification(options.errorMessage || result.message, 'error');
                }
            }
            
            return result;
        } catch (error) {
            if (loader) TheMind.loading.hide(loader);
            TheMind.utils.showNotification('Une erreur est survenue', 'error');
            throw error;
        }
    }
};

// Nettoyage à la fermeture de la page
window.addEventListener('beforeunload', () => {
    TheMind.timers.clearAll();
    TheMind.cache.clear();
});

// Export pour compatibilité
if (typeof module !== 'undefined' && module.exports) {
    module.exports = TheMind;
}