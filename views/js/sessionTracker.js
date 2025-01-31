document.addEventListener("DOMContentLoaded", function () {
    let startTime = Date.now();

    // Escuchar el evento de cierre de la página
    window.addEventListener("beforeunload", function () {
        let duration = Math.floor((Date.now() - startTime) / 1000);
        let data = JSON.stringify({
            duration: duration,
            page: window.location.pathname
        });

        // Usar sendBeacon para asegurar que los datos se envíen sin interrumpir la navegación
        navigator.sendBeacon("modules/loginsessiontracker/ajax.php", data);

        // También puedes usar fetch para un manejo más detallado, si es necesario
        fetch("modules/loginsessiontracker/ajax.php", {
            method: "POST",
            body: data,
            headers: { "Content-Type": "application/json" }
        })
        .then(response => response.json())
        .then(data => console.log("Respuesta del servidor:", data))
        .catch(error => console.error("Error:", error));
    });
});
