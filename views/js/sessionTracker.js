let sessionStartTime = new Date().getTime();

window.addEventListener('beforeunload', function() {
    let duration = Math.round((new Date().getTime() - sessionStartTime) / 1000); // en segundos
    let page = window.location.pathname; // O cualquier otra información que desees
    let data = {
        duration: duration,
        page: page
    };

    // Enviar los datos al servidor
    fetch('/modules/loginsessiontracker/close_session.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(data),
    });
});
