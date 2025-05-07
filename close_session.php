<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/../../config/config.inc.php';
require_once __DIR__ . '/../../init.php';

header('Content-Type: application/json');

try {
    // Verificar método POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido');
    }

    // Obtener datos
    $input = file_get_contents('php://input');
    if (!$input) {
        throw new Exception('No se recibieron datos');
    }

    $data = json_decode($input, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Datos JSON inválidos');
    }

    // Verificar usuario autenticado
    if (!Context::getContext()->customer->isLogged()) {
        throw new Exception('Usuario no autenticado');
    }

    $id_customer = (int)Context::getContext()->customer->id;
    $fecha_fin = date('Y-m-d H:i:s');

    // Actualizar sesión activa
    $result = Db::getInstance()->update('loginsessiontracker', [
        'fecha_fin' => $fecha_fin
    ], 'id_customer = ' . $id_customer . ' AND fecha_fin IS NULL');

    if (!$result) {
        throw new Exception('No se encontraron sesiones activas para cerrar');
    }

    // Registrar última página visitada si se proporcionó
    if (isset($data['page']) && !empty($data['page'])) {
        $last_session = Db::getInstance()->getValue('
            SELECT id_log FROM ' . _DB_PREFIX_ . 'loginsessiontracker
            WHERE id_customer = ' . $id_customer . '
            ORDER BY fecha_inicio DESC LIMIT 1
        ');

        if ($last_session) {
            Db::getInstance()->insert('loginsession_pages', [
                'id_log' => (int)$last_session,
                'pagina_visitada' => pSQL($data['page']),
                'fecha' => $fecha_fin
            ]);
        }
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
