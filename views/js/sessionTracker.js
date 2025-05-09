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

        console.log('[SessionTracker] Inicializando...');
        this.initEvents();
        this.startSession();
    }

    initEvents() {
        const activityEvents = ['mousemove', 'keydown', 'scroll', 'click', 'touchstart'];
        activityEvents.forEach(event => {
            window.addEventListener(event, () => {
                this.recordActivity();
                console.log(`[SessionTracker] Actividad detectada: ${event}`);
            });
        });

        window.addEventListener('beforeunload', () => {
            console.log('[SessionTracker] Evento beforeunload: cerrando sesión');
            this.closeSession();
        });

        window.addEventListener('pagehide', () => {
            console.log('[SessionTracker] Evento pagehide: cerrando sesión');
            this.closeSession();
        });
    }

    recordActivity() {
        this.lastActivity = Date.now();
    }

    startSession() {
        console.log('[SessionTracker] Iniciando sesión...');
        this.sendPing(); // Ping inicial
        this.startPingInterval();
        this.startInactivityCheck();
    }

    startPingInterval() {
        this.pingTimer = setInterval(() => {
            console.log('[SessionTracker] Ejecutando ping programado...');
            this.sendPing();
        }, this.pingInterval);
    }

    startInactivityCheck() {
        this.inactivityTimer = setInterval(() => {
            const inactivo = Date.now() - this.lastActivity > this.inactivityTimeout;
            console.log(`[SessionTracker] Verificando inactividad... ${inactivo ? 'INACTIVO' : 'Activo'}`);
            if (inactivo) {
                console.warn('[SessionTracker] Usuario inactivo, cerrando sesión...');
                this.closeSession();
            }
        }, 60000); // Verificar cada minuto
    }

    async sendPing() {
        try {
            console.log('[SessionTracker] Enviando ping al servidor...');
            const response = await fetch(this.pingEndpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    timestamp: new Date().toISOString()
                }),
                credentials: 'include'
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const text = await response.text();
            if (!text) {
                throw new Error('Respuesta vacía del servidor');
            }

            const data = JSON.parse(text);
            console.log('[SessionTracker] Respuesta del servidor:', data);

            if (data.success && data.session_id) {
                if (!this.sessionId) {
                    console.log(`[SessionTracker] Sesión iniciada con ID: ${data.session_id}`);
                }
                this.sessionId = data.session_id;
            } else {
                console.warn('[SessionTracker] Ping fallido o sin sesión activa.');
            }
        } catch (error) {
            console.error('[SessionTracker] Error en sendPing:', error);
        }
    }

    closeSession() {
        clearInterval(this.pingTimer);
        clearInterval(this.inactivityTimer);

        if (this.sessionId) {
            const data = {
                session_id: this.sessionId,
                page: window.location.href,
                timestamp: new Date().toISOString()
            };

            console.log(`[SessionTracker] Cerrando sesión con ID: ${this.sessionId}`);
            navigator.sendBeacon(this.closeEndpoint, new Blob(
                [JSON.stringify(data)],
                { type: 'application/json' }
            ));
        } else {
            console.warn('[SessionTracker] No hay sessionId, no se puede cerrar sesión');
        }
    }
}

new SessionTracker();
