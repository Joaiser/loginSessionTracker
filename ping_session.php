<?php
require_once dirname(__FILE__) . '/../../config/config.inc.php';
require_once dirname(__FILE__) . '/../../init.php';

header('Content-Type: application/json');

try {
    // Verificación básica
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido', 405);
    }

    if (!Context::getContext()->customer->isLogged()) {
        throw new Exception('Usuario no autenticado', 401);
    }

    $id_customer = (int)Context::getContext()->customer->id;
    $input = json_decode(file_get_contents('php://input'), true);

    $dispositivo = pSQL($_SERVER['HTTP_USER_AGENT']);
    $navegador = pSQL($_SERVER['HTTP_USER_AGENT']);

    // Buscar o crear sesión activa
    $id_log = Db::getInstance()->getValue(
        'SELECT id_log FROM `' . _DB_PREFIX_ . 'loginsessiontracker` ' .
            'WHERE id_customer = ' . (int)$id_customer . ' AND fecha_fin IS NULL ' .
            'ORDER BY fecha_inicio DESC LIMIT 1'
    );

    // Si no hay sesión activa, crear una nueva
    if (!$id_log) {
        Db::getInstance()->insert('loginsessiontracker', [
            'id_customer' => $id_customer,
            'fecha_inicio' => date('Y-m-d H:i:s'),
            'dispositivo' => $dispositivo,
            'navegador' => $navegador
        ]);
        $id_log = Db::getInstance()->Insert_ID();
    }

    // Actualizar last_ping (esto mantiene la sesión activa)
    Db::getInstance()->update('loginsessiontracker', [
        'last_ping' => date('Y-m-d H:i:s')
    ], 'id_log = ' . (int)$id_log);

    // Registrar página visitada si es diferente
    if (isset($input['page'])) {
        $current_page = pSQL($input['page']);
        $last_page = Db::getInstance()->getValue(
            'SELECT pagina_visitada FROM ' . _DB_PREFIX_ . 'loginsession_pages 
            WHERE id_log = ' . (int)$id_log . '
            ORDER BY fecha DESC LIMIT 1'
        );

        if (!$last_page || $last_page !== $current_page) {
            Db::getInstance()->insert('loginsession_pages', [
                'id_log' => (int)$id_log,
                'pagina_visitada' => $current_page,
                'fecha' => date('Y-m-d H:i:s')
            ]);
        }
    }

    echo json_encode([
        'success' => true,
        'session_id' => $id_log,
        'last_ping' => date('Y-m-d H:i:s')
    ]);
} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        'error' => $e->getMessage(),
        'code' => $e->getCode()
    ]);
}
