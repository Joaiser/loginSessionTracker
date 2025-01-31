<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

class Loginsessiontracker extends Module
{
    public function __construct()
    {
        $this->name = 'loginsessiontracker';
        $this->tab = 'administration';
        $this->version = '1.0.0';
        $this->author = 'Aitor';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = ['min' => '1.7', 'max' => _PS_VERSION_];
        $this->bootstrap = true;
        parent::__construct();
        $this->displayName = $this->l('Login Session Tracker');
        $this->description = $this->l('Registra el inicio de sesión y la navegación de los usuarios.');
    }

    public function install()
    {
        return parent::install()
            && $this->registerHook('displayHeader')
            && $this->createDatabaseTable();
    }

    public function uninstall()
    {
        return parent::uninstall() && $this->removeDatabaseTable();
    }

    private function createDatabaseTable()
    {
        $sql = "CREATE TABLE IF NOT EXISTS " . _DB_PREFIX_ . "loginsessiontracker (
            id_log INT AUTO_INCREMENT PRIMARY KEY,
            id_customer INT NOT NULL,
            fecha_inicio DATETIME NOT NULL,
            fecha_fin DATETIME NULL,
            duracion INT NULL,
            pagina_visitada TEXT NULL,
            total_sesiones INT DEFAULT 1,
            dispositivo VARCHAR(50) NULL,
            navegador VARCHAR(50) NULL,
            INDEX (id_customer)
        ) ENGINE=" . _MYSQL_ENGINE_ . " DEFAULT CHARSET=utf8;";

        return Db::getInstance()->execute($sql);
    }

    private function removeDatabaseTable()
    {
        $sql = "DROP TABLE IF EXISTS " . _DB_PREFIX_ . "loginsessiontracker";
        return Db::getInstance()->execute($sql);
    }

    // Para manejar el cierre de sesión y calcular la duración de la sesión cuando la pestaña se cierre o el navegador se cierre
    public function trackSessionClose($id_customer)
    {
        if (!$id_customer) {
            return;
        }

        $fecha_fin = date('Y-m-d H:i:s');

        // Calcular la duración en segundos
        $sql = "SELECT fecha_inicio FROM " . _DB_PREFIX_ . "loginsessiontracker
                WHERE id_customer = " . (int) $id_customer . " ORDER BY fecha_inicio DESC LIMIT 1";
        $fecha_inicio = Db::getInstance()->getValue($sql);
        if ($fecha_inicio) {
            $duration = strtotime($fecha_fin) - strtotime($fecha_inicio);

            Db::getInstance()->update(
                'loginsessiontracker',
                [
                    'fecha_fin' => $fecha_fin,
                    'duracion' => $duration
                ],
                'id_customer = ' . (int) $id_customer . ' AND fecha_inicio = "' . pSQL($fecha_inicio) . '"'
            );
        }
    }

    public function hookDisplayHeader()
    {
        if (!$this->context->customer->isLogged()) {
            return;
        }

        $id_customer = (int) $this->context->customer->id;
        $current_page = pSQL($_SERVER['REQUEST_URI']); // Capturar la url completa
        if ($this->context->controller->php_self == 'pagenotfound') {
            return;
        }

        $fecha_actual = date('Y-m-d H:i:s');

        // Registrar dispositivo y navegador
        $device = 'PC'; // Valor por defecto
        if (preg_match('/mobile/i', $_SERVER['HTTP_USER_AGENT'])) {
            $device = 'Móvil';
        } elseif (preg_match('/tablet/i', $_SERVER['HTTP_USER_AGENT'])) {
            $device = 'Tablet'; // Añadir la opción para Tablet
        }

        $browser = '';
        if (strpos($_SERVER['HTTP_USER_AGENT'], 'Chrome') !== false) {
            $browser = 'Chrome';
        } elseif (strpos($_SERVER['HTTP_USER_AGENT'], 'Firefox') !== false) {
            $browser = 'Firefox';
        } elseif (strpos($_SERVER['HTTP_USER_AGENT'], 'Safari') !== false) {
            $browser = 'Safari';
        } elseif (strpos($_SERVER['HTTP_USER_AGENT'], 'Opera') !== false) {
            $browser = 'Opera';
        } elseif (strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE') !== false) {
            $browser = 'Internet Explorer';
        } elseif (strpos($_SERVER['HTTP_USER_AGENT'], 'Edge') !== false) {
            $browser = 'Edge';
        } elseif (strpos($_SERVER['HTTP_USER_AGENT'], 'Brave') !== false) {
            $browser = 'Brave';
        } else {
            $browser = 'Otro';
        }

        // Insertar en la base de datos sin geolocalización
        Db::getInstance()->insert('loginsessiontracker', [
            'id_customer' => $id_customer,
            'fecha_inicio' => $fecha_actual,
            'pagina_visitada' => $current_page,
            'dispositivo' => pSQL($device),
            'navegador' => pSQL($browser)
        ]);

        // Registrar un historial de visitas
        $lastVisit = Db::getInstance()->getValue(
            "SELECT pagina_visitada FROM " . _DB_PREFIX_ . "loginsessiontracker
            WHERE id_customer = " . (int) $id_customer . " ORDER BY fecha_inicio DESC LIMIT 1"
        );

        $newVisit = $lastVisit ? $lastVisit . ' -> ' . pSQL($_SERVER['REQUEST_URI']) : pSQL($_SERVER['REQUEST_URI']);

        Db::getInstance()->update(
            'loginsessiontracker',
            ['pagina_visitada' => $newVisit],
            'id_customer = ' . (int) $id_customer . ' ORDER BY fecha_inicio DESC LIMIT 1'
        );

        // Incrementar el número de sesiones
        Db::getInstance()->update(
            'loginsessiontracker',
            ['total_sesiones' => ['type' => 'sql', 'value' => 'total_sesiones + 1']],
            'id_customer = ' . (int) $id_customer
        );

        // Registrar el javascript para el rastreo de sesión
        $this->context->controller->registerJavascript(
            'module-loginsessiontracker-js',
            'modules/' . $this->name . '/views/js/sessionTracker.js',
            ['position' => 'bottom', 'priority' => 150]
        );
    }
}
