class SessionTracker {
    constructor() {
        this.trackingEndpoint = '/modules/loginsessiontracker/close_session.php';
        this.initEvents();
    }

    initEvents() {
        // Solo eventos esenciales para cerrar sesión
        window.addEventListener('beforeunload', () => this.endSession());
        window.addEventListener('pagehide', () => this.endSession());
    }

    endSession() {
        const data = {
            page: window.location.href
        };

        const blob = new Blob([JSON.stringify(data)], { type: 'application/json' });
        navigator.sendBeacon(this.trackingEndpoint, blob);
    }
}

// Iniciar solo si el usuario está logueado
if (typeof isLogged !== 'undefined' && isLogged) {
    new SessionTracker();
}