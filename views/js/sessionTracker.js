class SessionTracker {
    constructor() {
        this.pingEndpoint = '/modules/loginsessiontracker/ping_session.php';
        this.closeEndpoint = '/modules/loginsessiontracker/close_session.php';
        this.pingInterval = 30000; // 30 segundos
        this.inactivityTimeout = 300000; // 5 minutos
        this.lastActivity = Date.now();
        this.pingTimer = null;
        this.inactivityTimer = null;
        this.sessionId = null;

        this.initEvents();
        this.startSession();
    }

    initEvents() {
        // Eventos de actividad
        ['mousemove', 'keydown', 'scroll', 'click', 'touchstart'].forEach(event => {
            window.addEventListener(event, () => this.recordActivity());
        });

        // Eventos de cierre
        window.addEventListener('beforeunload', () => this.closeSession());
        window.addEventListener('pagehide', () => this.closeSession());
    }

    recordActivity() {
        this.lastActivity = Date.now();
    }

    startSession() {
        this.sendPing(); // Ping inicial
        this.startPingInterval();
        this.startInactivityCheck();
    }

    startPingInterval() {
        this.pingTimer = setInterval(() => {
            this.sendPing();
        }, this.pingInterval);
    }

    startInactivityCheck() {
        this.inactivityTimer = setInterval(() => {
            if (Date.now() - this.lastActivity > this.inactivityTimeout) {
                this.closeSession();
            }
        }, 60000); // Verificar cada minuto
    }

    async sendPing() {
        try {
            const response = await fetch(this.pingEndpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    page: window.location.href,
                    timestamp: new Date().toISOString()
                }),
                credentials: 'include'
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const text = await response.text();
            if (!text) {
                throw new Error('Empty response from server');
            }

            const data = JSON.parse(text);
            if (data.success && data.session_id) {
                this.sessionId = data.session_id;
            }
        } catch (error) {
            console.error('Error en ping:', error);
            // Opcional: reintentar o notificar al usuario
        }
    }

    closeSession() {
        // Limpiar intervalos
        clearInterval(this.pingTimer);
        clearInterval(this.inactivityTimer);

        // Enviar cierre de sesión
        if (this.sessionId) {
            const data = {
                session_id: this.sessionId,
                page: window.location.href,
                timestamp: new Date().toISOString()
            };

            navigator.sendBeacon(this.closeEndpoint, new Blob(
                [JSON.stringify(data)],
                { type: 'application/json' }
            ));
        }
    }
}

// Iniciar tracker siempre (la verificación de login se hace en el servidor)
new SessionTracker();