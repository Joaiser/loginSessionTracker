<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/../../config/config.inc.php';
require_once __DIR__ . '/../../init.php';

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido');
    }

    $input = file_get_contents('php://input');
    if (!$input) {
        throw new Exception('No se recibieron datos');
    }

    $data = json_decode($input, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Datos JSON inválidos');
    }

    if (!Context::getContext()->customer->isLogged()) {
        throw new Exception('Usuario no autenticado');
    }

    if (!isset($data['session_id']) || !is_numeric($data['session_id'])) {
        throw new Exception('ID de sesión no válido');
    }

    $id_log = (int)$data['session_id'];
    $fecha_fin = date('Y-m-d H:i:s');

    $result = Db::getInstance()->update('loginsessiontracker', [
        'fecha_fin' => $fecha_fin
    ], 'id_log = ' . $id_log . ' AND fecha_fin IS NULL');

    if (!$result) {
        throw new Exception('No se encontraron sesiones activas para cerrar');
    }

    if (isset($data['page']) && !empty($data['page'])) {
        Db::getInstance()->insert('loginsession_pages', [
            'id_log' => $id_log,
            'pagina_visitada' => pSQL($data['page']),
            'fecha' => $fecha_fin
        ]);
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'Sesión cerrada correctamente'
    ]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
