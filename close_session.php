<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

// Verificar si la petición es POST y si contiene los datos
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty(file_get_contents("php://input"))) {
    $data = json_decode(file_get_contents("php://input"), true);
    
    // Obtener los datos enviados por JS
    $duration = isset($data['duration']) ? (int)$data['duration'] : 0;
    $page = isset($data['page']) ? pSQL($data['page']) : '';

    // Si el ID del cliente está disponible en el contexto, registrar la sesión
    if (Context::getContext()->customer->isLogged()) {
        $id_customer = (int) Context::getContext()->customer->id;
        $fecha_fin = date('Y-m-d H:i:s');

        // Registrar la duración y la página visitada
        $sql = "UPDATE " . _DB_PREFIX_ . "loginsessiontracker
                SET fecha_fin = '$fecha_fin', duracion = $duration, pagina_visitada = '$page'
                WHERE id_customer = $id_customer AND fecha_fin IS NULL";

        Db::getInstance()->execute($sql);

        // Incrementar el número de sesiones si es necesario:
        Db::getInstance()->update('loginsessiontracker', [
            'total_sesiones' => ['type' => 'sql', 'value' => 'total_sesiones + 1']
        ], 'id_customer = ' . $id_customer);
    }

    // Responder con éxito
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'No data received']);
}
