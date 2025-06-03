/**
 * Utilitaires JavaScript pour The Mind
 * Collection de fonctions utiles réutilisables
 */

class TheMindUtils {
    
    // === UTILITAIRES AJAX ===
    
    /**
     * Effectuer une requête AJAX sécurisée
     */
    static async request(url, options = {}) {
        const defaultOptions = {
            method: 'GET',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        };

        const config = { ...defaultOptions, ...options };

        // Ajouter le token CSRF pour les requêtes POST/PUT/DELETE
        if (['POST', 'PUT', 'DELETE'].includes(config.method.toUpperCase())) {
            const csrfToken = this.getCSRFToken();
            if (csrfToken) {
                if (config.body instanceof FormData) {
                    config.body.append('csrf_token', csrfToken);
                } else if (config.body instanceof URLSearchParams) {
                    config.body.append('csrf_token', csrfToken);
                } else if (typeof config.body === 'string') {
                    config.body += `&csrf_token=${encodeURIComponent(csrfToken)}`;
                } else {
                    config.body = new URLSearchParams(config.body || {});
                    config.body.append('csrf_token', csrfToken);
                }
            }
        }

        try {
            const response = await fetch(url, config);
            
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
            console.error('Erreur de requête:', error);
            throw error;
        }
    }

    /**
     * Requête GET simplifiée
     */
    static async get(url, params = {}) {
        const searchParams = new URLSearchParams(params);
        const urlWithParams = searchParams.toString() ? `${url}?${searchParams}` : url;
        return this.request(urlWithParams);
    }

    /**
     * Requête POST simplifiée
     */
    static async post(url, data = {}) {
        return this.request(url, {
            method: 'POST',
            body: new URLSearchParams(data)
        });
    }

    /**
     * Requête JSON
     */
    static async postJSON(url, data = {}) {
        return this.request(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(data)
        });
    }

    // === UTILITAIRES DOM ===

    /**
     * Sélecteur d'élément avec vérification
     */
    static $(selector, context = document) {
        return context.querySelector(selector);
    }

    /**
     * Sélecteur d'éléments multiples
     */
    static $(selector, context = document) {
        return Array.from(context.querySelectorAll(selector));
    }

    /**
     * Créer un élément DOM
     */
    static createElement(tag, attributes = {}, content = '') {
        const element = document.createElement(tag);
        
        Object.entries(attributes).forEach(([key, value]) => {
            if (key === 'className') {
                element.className = value;
            } else if (key === 'innerHTML') {
                element.innerHTML = value;
            } else if (key === 'textContent') {
                element.textContent = value;
            } else {
                element.setAttribute(key, value);
            }
        });

        if (content) {
            element.textContent = content;
        }

        return element;
    }

    /**
     * Attendre que le DOM soit prêt
     */
    static ready(callback) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', callback);
        } else {
            callback();
        }
    }

    /**
     * Ajouter un écouteur d'événement avec délégation
     */
    static delegate(selector, event, handler, context = document) {
        context.addEventListener(event, (e) => {
            if (e.target.matches(selector)) {
                handler.call(e.target, e);
            }
        });
    }

    // === UTILITAIRES DE VALIDATION ===

    /**
     * Valider une adresse email
     */
    static validateEmail(email) {
        const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return regex.test(email);
    }

    /**
     * Valider un mot de passe
     */
    static validatePassword(password, minLength = 6) {
        return password && password.length >= minLength;
    }

    /**
     * Valider un nom d'utilisateur
     */
    static validateUsername(username) {
        const regex = /^[a-zA-Z0-9_-]{3,20}$/;
        return regex.test(username);
    }

    /**
     * Nettoyer et valider une entrée
     */
    static sanitizeInput(input, type = 'text') {
        if (typeof input !== 'string') return '';
        
        switch (type) {
            case 'email':
                return input.trim().toLowerCase();
            case 'username':
                return input.trim().replace(/[^a-zA-Z0-9_-]/g, '');
            case 'number':
                return input.replace(/[^0-9]/g, '');
            case 'text':
            default:
                return input.trim();
        }
    }

    // === UTILITAIRES DE FORMATAGE ===

    /**
     * Formater une date
     */
    static formatDate(date, locale = 'fr-FR', options = {}) {
        const defaultOptions = {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        };
        
        const config = { ...defaultOptions, ...options };
        
        if (typeof date === 'string') {
            date = new Date(date);
        }
        
        return date.toLocaleDateString(locale, config);
    }

    /**
     * Formater un temps écoulé
     */
    static timeAgo(date, locale = 'fr') {
        const now = new Date();
        const diff = now - (typeof date === 'string' ? new Date(date) : date);
        const seconds = Math.floor(diff / 1000);
        
        const intervals = {
            fr: {
                year: { value: 31536000, singular: 'an', plural: 'ans' },
                month: { value: 2592000, singular: 'mois', plural: 'mois' },
                week: { value: 604800, singular: 'semaine', plural: 'semaines' },
                day: { value: 86400, singular: 'jour', plural: 'jours' },
                hour: { value: 3600, singular: 'heure', plural: 'heures' },
                minute: { value: 60, singular: 'minute', plural: 'minutes' },
                second: { value: 1, singular: 'seconde', plural: 'secondes' }
            },
            en: {
                year: { value: 31536000, singular: 'year', plural: 'years' },
                month: { value: 2592000, singular: 'month', plural: 'months' },
                week: { value: 604800, singular: 'week', plural: 'weeks' },
                day: { value: 86400, singular: 'day', plural: 'days' },
                hour: { value: 3600, singular: 'hour', plural: 'hours' },
                minute: { value: 60, singular: 'minute', plural: 'minutes' },
                second: { value: 1, singular: 'second', plural: 'seconds' }
            }
        };

        const currentIntervals = intervals[locale] || intervals.fr;
        
        for (const [key, interval] of Object.entries(currentIntervals)) {
            const count = Math.floor(seconds / interval.value);
            if (count >= 1) {
                const unit = count === 1 ? interval.singular : interval.plural;
                return locale === 'fr' ? `il y a ${count} ${unit}` : `${count} ${unit} ago`;
            }
        }
        
        return locale === 'fr' ? 'à l\'instant' : 'just now';
    }

    /**
     * Formater un nombre
     */
    static formatNumber(number, locale = 'fr-FR') {
        return new Intl.NumberFormat(locale).format(number);
    }

    /**
     * Formater une taille de fichier
     */
    static formatFileSize(bytes) {
        const units = ['B', 'KB', 'MB', 'GB', 'TB'];
        let size = bytes;
        let unitIndex = 0;
        
        while (size >= 1024 && unitIndex < units.length - 1) {
            size /= 1024;
            unitIndex++;
        }
        
        return `${size.toFixed(1)} ${units[unitIndex]}`;
    }

    // === UTILITAIRES DE SESSION ET SÉCURITÉ ===

    /**
     * Obtenir le token CSRF
     */
    static getCSRFToken() {
        // Essayer différentes sources
        const sources = [
            () => window.THEMIND?.CSRF_TOKEN,
            () => this.$('meta[name="csrf-token"]')?.getAttribute('content'),
            () => this.$('#csrf_token')?.value,
            () => this.$('input[name="csrf_token"]')?.value
        ];

        for (const source of sources) {
            try {
                const token = source();
                if (token) return token;
            } catch (e) {
                continue;
            }
        }

        console.warn('Token CSRF non trouvé');
        return null;
    }

    /**
     * Obtenir l'URL de base
     */
    static getBaseUrl() {
        return window.THEMIND?.SITE_URL || '/html/';
    }

    /**
     * Obtenir les informations de l'utilisateur courant
     */
    static getCurrentUser() {
        return window.THEMIND?.CURRENT_USER || null;
    }

    // === UTILITAIRES DE STOCKAGE ===

    /**
     * Sauvegarder dans localStorage avec gestion d'erreurs
     */
    static setLocalStorage(key, value) {
        try {
            localStorage.setItem(key, JSON.stringify(value));
            return true;
        } catch (error) {
            console.warn('Impossible de sauvegarder dans localStorage:', error);
            return false;
        }
    }

    /**
     * Récupérer depuis localStorage
     */
    static getLocalStorage(key, defaultValue = null) {
        try {
            const item = localStorage.getItem(key);
            return item ? JSON.parse(item) : defaultValue;
        } catch (error) {
            console.warn('Impossible de lire localStorage:', error);
            return defaultValue;
        }
    }

    /**
     * Supprimer de localStorage
     */
    static removeLocalStorage(key) {
        try {
            localStorage.removeItem(key);
            return true;
        } catch (error) {
            console.warn('Impossible de supprimer de localStorage:', error);
            return false;
        }
    }

    // === UTILITAIRES D'ANIMATION ===

    /**
     * Animer un élément avec CSS
     */
    static animate(element, animation, duration = 300) {
        return new Promise((resolve) => {
            const animationName = `animate-${Date.now()}`;
            
            element.style.animationName = animation;
            element.style.animationDuration = `${duration}ms`;
            element.style.animationFillMode = 'both';
            
            const handleAnimationEnd = () => {
                element.style.animation = '';
                element.removeEventListener('animationend', handleAnimationEnd);
                resolve();
            };
            
            element.addEventListener('animationend', handleAnimationEnd);
        });
    }

    /**
     * Faire défiler vers un élément
     */
    static scrollTo(element, offset = 0, behavior = 'smooth') {
        const targetElement = typeof element === 'string' ? this.$(element) : element;
        
        if (targetElement) {
            const rect = targetElement.getBoundingClientRect();
            const scrollTop = window.pageYOffset + rect.top + offset;
            
            window.scrollTo({
                top: scrollTop,
                behavior: behavior
            });
        }
    }

    // === UTILITAIRES DE NOTIFICATION ===

    /**
     * Afficher une notification toast
     */
    static showToast(message, type = 'info', duration = 3000) {
        const toast = this.createElement('div', {
            className: `toast toast-${type}`,
            innerHTML: `
                <div class="toast-content">
                    <span class="toast-message">${message}</span>
                    <button class="toast-close">&times;</button>
                </div>
            `
        });

        // Styles pour le toast
        Object.assign(toast.style, {
            position: 'fixed',
            top: '20px',
            right: '20px',
            padding: '15px 20px',
            borderRadius: '8px',
            color: 'white',
            fontWeight: 'bold',
            zIndex: '10000',
            maxWidth: '400px',
            opacity: '0',
            transform: 'translateY(-20px)',
            transition: 'all 0.3s ease'
        });

        // Couleurs selon le type
        const colors = {
            success: '#4CAF50',
            error: '#f44336',
            warning: '#ff9800',
            info: '#2196F3'
        };
        toast.style.backgroundColor = colors[type] || colors.info;

        document.body.appendChild(toast);

        // Animation d'entrée
        setTimeout(() => {
            toast.style.opacity = '1';
            toast.style.transform = 'translateY(0)';
        }, 10);

        // Gestion de la fermeture
        const closeToast = () => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(-20px)';
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            }, 300);
        };

        // Fermeture au clic
        this.$('.toast-close', toast)?.addEventListener('click', closeToast);

        // Fermeture automatique
        if (duration > 0) {
            setTimeout(closeToast, duration);
        }

        return toast;
    }

    // === UTILITAIRES DE DEBUGGING ===

    /**
     * Logger avec niveaux
     */
    static log(level, message, data = null) {
        if (!window.THEMIND?.DEBUG_MODE) return;

        const styles = {
            debug: 'color: #666',
            info: 'color: #2196F3',
            warn: 'color: #ff9800',
            error: 'color: #f44336'
        };

        const timestamp = new Date().toISOString();
        console[level](`%c[${timestamp}] ${message}`, styles[level] || '', data);
    }

    static debug(message, data) { this.log('debug', message, data); }
    static info(message, data) { this.log('info', message, data); }
    static warn(message, data) { this.log('warn', message, data); }
    static error(message, data) { this.log('error', message, data); }

    // === UTILITAIRES DIVERS ===

    /**
     * Débouncer une fonction
     */
    static debounce(func, wait, immediate = false) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                timeout = null;
                if (!immediate) func(...args);
            };
            const callNow = immediate && !timeout;
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
            if (callNow) func(...args);
        };
    }

    /**
     * Throttler une fonction
     */
    static throttle(func, limit) {
        let inThrottle;
        return function(...args) {
            if (!inThrottle) {
                func.apply(this, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        };
    }

    /**
     * Générer un ID unique
     */
    static generateId(prefix = 'id') {
        return `${prefix}-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;
    }

    /**
     * Copier du texte dans le presse-papiers
     */
    static async copyToClipboard(text) {
        try {
            await navigator.clipboard.writeText(text);
            return true;
        } catch (error) {
            // Fallback pour les navigateurs plus anciens
            const textArea = document.createElement('textarea');
            textArea.value = text;
            document.body.appendChild(textArea);
            textArea.select();
            try {
                document.execCommand('copy');
                return true;
            } catch (err) {
                return false;
            } finally {
                document.body.removeChild(textArea);
            }
        }
    }

    /**
     * Détecter le type d'appareil
     */
    static getDeviceInfo() {
        const userAgent = navigator.userAgent;
        return {
            isMobile: /Android|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(userAgent),
            isTablet: /iPad|Android(?=.*Mobile)/i.test(userAgent),
            isDesktop: !/Android|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(userAgent),
            browser: this.getBrowserName(userAgent),
            os: this.getOS(userAgent)
        };
    }

    static getBrowserName(userAgent) {
        if (userAgent.includes('Chrome')) return 'Chrome';
        if (userAgent.includes('Firefox')) return 'Firefox';
        if (userAgent.includes('Safari')) return 'Safari';
        if (userAgent.includes('Edge')) return 'Edge';
        if (userAgent.includes('Opera')) return 'Opera';
        return 'Unknown';
    }

    static getOS(userAgent) {
        if (userAgent.includes('Windows')) return 'Windows';
        if (userAgent.includes('Mac')) return 'macOS';
        if (userAgent.includes('Linux')) return 'Linux';
        if (userAgent.includes('Android')) return 'Android';
        if (userAgent.includes('iOS')) return 'iOS';
        return 'Unknown';
    }
}

// === FONCTIONS GLOBALES RACCOURCIES ===

// Sélecteurs
window.$ = (selector, context) => TheMindUtils.$(selector, context);
window.$ = (selector, context) => TheMindUtils.$(selector, context);

// AJAX
window.ajax = {
    get: (url, params) => TheMindUtils.get(url, params),
    post: (url, data) => TheMindUtils.post(url, data),
    request: (url, options) => TheMindUtils.request(url, options)
};

// Notifications
window.toast = (message, type, duration) => TheMindUtils.showToast(message, type, duration);

// Utilitaires
window.utils = TheMindUtils;

// Initialisation quand le DOM est prêt
TheMindUtils.ready(() => {
    TheMindUtils.info('Utils JavaScript initialisés');
    
    // Ajouter des styles CSS pour les toasts si nécessaire
    if (!document.querySelector('#toast-styles')) {
        const style = document.createElement('style');
        style.id = 'toast-styles';
        style.textContent = `
            .toast-content {
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            .toast-close {
                background: none;
                border: none;
                color: inherit;
                font-size: 18px;
                cursor: pointer;
                margin-left: 10px;
                padding: 0;
                width: 20px;
                height: 20px;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .toast-close:hover {
                opacity: 0.7;
            }
        `;
        document.head.appendChild(style);
    }
});

// Export pour les modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = TheMindUtils;
}