<?php
require_once dirname(__FILE__) . '/../../config/config.inc.php';
require_once dirname(__FILE__) . '/../../init.php';

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido', 405);
    }

    $context = Context::getContext();
    if (!$context->customer->isLogged()) {
        throw new Exception('Usuario no autenticado', 401);
    }

    $id_customer = (int)$context->customer->id;
    $timestamp = date('Y-m-d H:i:s');

    if ($id_customer > 0) {
        $sql = 'SELECT id_log FROM `' . _DB_PREFIX_ . 'loginsessiontracker` WHERE id_customer = ? AND fecha_fin IS NULL ORDER BY fecha_inicio DESC LIMIT 1';
        $rows = Db::getInstance()->executeS($sql, [$id_customer]);
        $id_log = isset($rows[0]['id_log']) ? (int)$rows[0]['id_log'] : null;
    } else {
        $id_log = null;
    }


    if ($id_log) {
        // Solo actualizar last_ping
        Db::getInstance()->update('loginsessiontracker', [
            'last_ping' => $timestamp
        ], 'id_log = ' . (int)$id_log);

        echo json_encode([
            'success' => true,
            'session_id' => $id_log,
            'last_ping' => $timestamp
        ]);
    } else {
        // No hay sesión activa, puede devolver success false o similar
        echo json_encode([
            'success' => false,
            'message' => 'No active session found'
        ]);
    }
} catch (Exception $e) {
    PrestaShopLogger::addLog('Error en ping_session.php: ' . $e->getMessage(), 3);
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        'error' => $e->getMessage(),
        'code' => $e->getCode()
    ]);
}
