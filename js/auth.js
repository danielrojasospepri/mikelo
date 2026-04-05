/**
 * Mikelo - Módulo de Autenticación
 * Maneja login, logout, tokens y verificación de sesión
 */

const MikeloAuth = {
    // Auto-detecta la carpeta base desde la URL actual (/mikelo, /test, etc.)
    get API_BASE() {
        const seg = window.location.pathname.split('/');
        const baseFolder = seg[1] && !seg[1].includes('.') ? '/' + seg[1] : '';
        return baseFolder + '/api';
    },
    // Claves de localStorage únicas por entorno: evita colisión entre /mikelo, /test y raíz
    get TOKEN_KEY() {
        const seg = window.location.pathname.split('/');
        const env = seg[1] && !seg[1].includes('.') ? seg[1] : 'root';
        return 'mikelo_token_' + env;
    },
    get USER_KEY() {
        const seg = window.location.pathname.split('/');
        const env = seg[1] && !seg[1].includes('.') ? seg[1] : 'root';
        return 'mikelo_user_' + env;
    },

    /**
     * Realizar login
     * @param {string} usuario 
     * @param {string} password 
     * @returns {Promise<object>} Datos del usuario
     */
    async login(usuario, password) {
        const response = await fetch(`${this.API_BASE}/auth/login`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ usuario, password })
        });

        const data = await response.json();

        if (data.error) {
            throw new Error(data.mensaje || 'Error de autenticación');
        }

        // Guardar token y datos de usuario
        localStorage.setItem(this.TOKEN_KEY, data.token);
        localStorage.setItem(this.USER_KEY, JSON.stringify(data.usuario));

        return data.usuario;
    },

    /**
     * Cerrar sesión
     */
    async logout() {
        const token = this.getToken();
        
        if (token) {
            try {
                await fetch(`${this.API_BASE}/auth/logout`, {
                    method: 'POST',
                    headers: this.getAuthHeaders()
                });
            } catch (e) {
                console.error('Error en logout:', e);
            }
        }

        // Limpiar storage local
        localStorage.removeItem(this.TOKEN_KEY);
        localStorage.removeItem(this.USER_KEY);

        // Redirigir a login
        window.location.href = 'login.html';
    },

    /**
     * Obtener token guardado
     */
    getToken() {
        return localStorage.getItem(this.TOKEN_KEY);
    },

    /**
     * Obtener datos del usuario guardados
     */
    getUser() {
        const userStr = localStorage.getItem(this.USER_KEY);
        return userStr ? JSON.parse(userStr) : null;
    },

    /**
     * Verificar si hay sesión activa
     */
    isAuthenticated() {
        return !!this.getToken();
    },

    /**
     * Obtener headers con autorización
     */
    getAuthHeaders() {
        const token = this.getToken();
        return {
            'Content-Type': 'application/json',
            'Authorization': token ? `Bearer ${token}` : ''
        };
    },

    /**
     * Validar sesión contra el servidor
     */
    async validateSession() {
        const token = this.getToken();
        if (!token) return false;

        try {
            const response = await fetch(`${this.API_BASE}/auth/validar`, {
                headers: this.getAuthHeaders()
            });
            const data = await response.json();
            return data.valido === true;
        } catch (e) {
            console.error('Error validando sesión:', e);
            return false;
        }
    },

    /**
     * Requerir autenticación en una página
     * Redirige a login si no hay sesión válida
     */
    async requireAuth() {
        if (!this.isAuthenticated()) {
            window.location.href = 'login.html';
            return false;
        }

        const valid = await this.validateSession();
        if (!valid) {
            localStorage.removeItem(this.TOKEN_KEY);
            localStorage.removeItem(this.USER_KEY);
            window.location.href = 'login.html?expired=1';
            return false;
        }

        return true;
    },

    /**
     * Verificar nivel de rol
     * @param {number} nivelRequerido - Nivel máximo permitido (menor = más permisos)
     */
    hasPermission(nivelRequerido) {
        const user = this.getUser();
        if (!user) return false;
        return user.rol_nivel <= nivelRequerido;
    },

    /**
     * Verificar si es usuario de planta (admin o planta)
     */
    isPlanta() {
        return this.hasPermission(25);
    },

    /**
     * Verificar si es Planta Jefe o Admin (nivel <= 20)
     */
    isPlantaJefe() {
        return this.hasPermission(20);
    },

    /**
     * Verificar si es franquicia
     */
    isFranquicia() {
        const user = this.getUser();
        if (!user) return false;
        return user.rol_nivel >= 30;
    },

    /**
     * Verificar si es admin de franquicia (nivel 30)
     */
    isFranquiciaAdmin() {
        const user = this.getUser();
        if (!user) return false;
        return parseInt(user.rol_nivel) === 30 || user.rol_nivel <= 10; // Admin de franquicia o admin general
    },

    /**
     * Obtener sucursal principal del usuario
     */
    getSucursalPrincipal() {
        const user = this.getUser();
        if (!user || !user.sucursales || user.sucursales.length === 0) return null;
        
        // Buscar la principal o la primera
        const principal = user.sucursales.find(s => s.es_sucursal_principal);
        return principal || user.sucursales[0];
    },

    // Flag para evitar múltiples llamadas a logout() cuando hay 401s concurrentes
    _loggingOut: false,

    /**
     * Realizar petición autenticada
     */
    async fetch(url, options = {}) {
        const headers = {
            ...this.getAuthHeaders(),
            ...(options.headers || {})
        };

        const response = await fetch(`${this.API_BASE}${url}`, {
            ...options,
            headers
        });

        // Si es 401, cerrar sesión (sólo la primera vez, evita loop con 401s concurrentes)
        if (response.status === 401 && !this._loggingOut) {
            this._loggingOut = true;
            this.logout();
            return null;
        }

        return response;
    },

    /**
     * Requerir autenticación + nivel de rol mínimo
     * nivelMax: nivel máximo permitido (ej: 25 = planta, 40 = franquicia)
     * si el usuario tiene nivel > nivelMax, no tiene permisos para esa página
     */
    async requireAuthWithRole(nivelMax, redirectTo) {
        const authenticated = await this.requireAuth();
        if (!authenticated) return false;

        const user = this.getUser();
        if (!user) return false;

        if (user.rol_nivel > nivelMax) {
            // Sin permiso → redirigir
            const dest = redirectTo || (this.isFranquicia() ? 'pedidos_sucursal.html' : 'login.html');
            window.location.href = dest;
            return false;
        }
        return true;
    },

    /**
     * Requerir página solo para planta/depósito (niveles 10-25)
     * Redirige a pedidos_sucursal si es franquicia
     */
    async requirePlanta() {
        return this.requireAuthWithRole(25, 'pedidos_sucursal.html');
    },

    /**
     * Requerir página solo para franquicias (niveles 30-40)
     * Redirige a index si es planta
     */
    async requireFranquicia() {
        const authenticated = await this.requireAuth();
        if (!authenticated) return false;

        const user = this.getUser();
        if (!user) return false;

        // Admin puede ver todo
        if (user.rol_nivel <= 10) return true;

        if (user.rol_nivel < 30) {
            window.location.href = 'index.html';
            return false;
        }
        return true;
    },

    /**
     * Actualizar UI con datos del usuario
     */
    updateUI() {
        const user = this.getUser();
        if (!user) return;

        // Nombre completo del usuario en navbar
        const userNameEl = document.querySelector('.user-name, #userName');
        if (userNameEl) {
            userNameEl.textContent = user.nombre || user.usuario;
        }

        // Info unificada: sucursal o número de sucursales (nuevo estándar)
        const sucursalInfoEl = document.querySelector('#userSucursalInfo');
        if (sucursalInfoEl) {
            const sucursales = user.sucursales || [];
            if (sucursales.length === 0) {
                sucursalInfoEl.textContent = user.rol || '';
            } else if (sucursales.length === 1) {
                sucursalInfoEl.textContent = sucursales[0].sucursal || sucursales[0].nombre || 'Sucursal';
            } else {
                sucursalInfoEl.textContent = sucursales.length + ' sucursales autorizadas';
            }
        }

        // Retrocompatibilidad
        const userRoleEl = document.querySelector('.user-role, #userRole');
        if (userRoleEl) {
            userRoleEl.textContent = user.rol;
        }

        const sucursal = this.getSucursalPrincipal();
        const sucursalEl = document.querySelector('.user-sucursal, #userSucursal');
        if (sucursalEl && sucursal) {
            sucursalEl.textContent = sucursal.sucursal;
        }

        // Mostrar/ocultar elementos según rol
        document.querySelectorAll('[data-require-planta]').forEach(el => {
            el.style.display = this.isPlanta() ? '' : 'none';
        });

        document.querySelectorAll('[data-require-planta-jefe]').forEach(el => {
            el.style.display = this.isPlantaJefe() ? '' : 'none';
        });

        document.querySelectorAll('[data-require-franquicia]').forEach(el => {
            el.style.display = this.isFranquicia() ? '' : 'none';
        });

        document.querySelectorAll('[data-require-franquicia-admin]').forEach(el => {
            el.style.display = this.isFranquiciaAdmin() ? '' : 'none';
        });

        document.querySelectorAll('[data-require-admin]').forEach(el => {
            el.style.display = this.hasPermission(10) ? '' : 'none';
        });

        // Revelar sidebar ahora que los permisos ya están aplicados
        document.querySelectorAll('.nav-sidebar').forEach(el => {
            el.classList.add('sidebar-ready');
        });
    }
};

// Función helper para fetch autenticado
async function authFetch(url, options = {}) {
    return MikeloAuth.fetch(url, options);
}

// Exponer globalmente
window.MikeloAuth = MikeloAuth;
window.authFetch = authFetch;
