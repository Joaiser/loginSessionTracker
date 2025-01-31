<?php
require_once dirname(__FILE__) . '/../../config/config.inc.php';
require_once dirname(__FILE__) . '/../../init.php';

$inputJSON = file_get_contents('php://input');
$data = json_decode($inputJSON, true);

if (!isset($data['duration']) || !isset($data['page'])) {
    exit;
}

$id_customer = (int) Context::getContext()->customer->id;
$duration = round((int) $_POST['duration'] / 60);  // Convertir duración a minutos
$page = pSQL($data['page']);
$fecha_fin = date('Y-m-d H:i:s');

// Actualizar la base de datos con la duración de la sesión y la página visitada
Db::getInstance()->update(
    'loginsessiontracker',
    [
        'fecha_fin' => $fecha_fin,
        'duracion' => $duration,
        'pagina_visitada' => $page
    ],
    'id_customer = ' . $id_customer . ' ORDER BY fecha_inicio DESC LIMIT 1'
);

echo json_encode(['status' => 'success']);
?>
