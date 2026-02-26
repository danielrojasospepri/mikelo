/**
 * Sistema de Notificaciones - J.A.R.V.I.S
 * Muestra badges con contadores de pedidos pendientes en el sidebar
 * 
 * Uso: Incluir este script después de mikelo-auth.js
 * Se inicializa automáticamente al cargar la página
 */

const MikeloNotificaciones = {
    // Configuración
    intervaloActualizacion: 60000, // 1 minuto
    intervalId: null,

    /**
     * Inicializar sistema de notificaciones
     */
    async init() {
        // Solo para personal de planta (rol nivel < 30)
        const usuario = MikeloAuth.getUser();
        if (!usuario || usuario.rol_nivel >= 30) {
            return;
        }

        // Agregar badges al sidebar
        this.agregarBadges();

        // Primera carga
        await this.actualizarContadores();

        // Actualización periódica
        this.intervalId = setInterval(() => {
            this.actualizarContadores();
        }, this.intervaloActualizacion);
    },

    /**
     * Agregar badges al sidebar
     */
    agregarBadges() {
        // Badge en Panel Producción
        const linkProduccion = document.querySelector('a[href="panel_produccion.html"] p');
        if (linkProduccion && !linkProduccion.querySelector('.badge-pedidos')) {
            linkProduccion.innerHTML = `
                Panel Producción
                <span class="badge badge-danger right badge-pedidos" style="display: none;">0</span>
            `;
        }

        // Badge en link de Pedidos (para sucursales que vean pedidos)
        const linkPedidos = document.querySelector('a[href="pedidos_sucursal.html"] p');
        if (linkPedidos && !linkPedidos.querySelector('.badge-mis-pedidos')) {
            // Este badge se puede usar para mostrar pedidos propios pendientes
        }
    },

    /**
     * Actualizar contadores desde API
     */
    async actualizarContadores() {
        try {
            const response = await MikeloAuth.fetch('/pedidos/contadores');
            if (!response) return;
            
            const data = await response.json();

            if (!data.error) {
                this.mostrarBadge('.badge-pedidos', data.pendientes, data.urgentes > 0);
            }
        } catch (error) {
            console.error('Error actualizando contadores:', error);
        }
    },

    /**
     * Mostrar u ocultar badge
     */
    mostrarBadge(selector, cantidad, esUrgente = false) {
        const badge = document.querySelector(selector);
        if (!badge) return;

        if (cantidad > 0) {
            badge.textContent = cantidad > 99 ? '99+' : cantidad;
            badge.style.display = 'inline-block';
            badge.className = `badge right ${esUrgente ? 'badge-danger' : 'badge-warning'} ${selector.replace('.', '')}`;
        } else {
            badge.style.display = 'none';
        }
    },

    /**
     * Detener actualizaciones
     */
    detener() {
        if (this.intervalId) {
            clearInterval(this.intervalId);
            this.intervalId = null;
        }
    }
};

// Auto-inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', () => {
    // Esperar a que MikeloAuth esté disponible
    if (typeof MikeloAuth !== 'undefined') {
        setTimeout(() => MikeloNotificaciones.init(), 500);
    }
});
