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
        $this->displayName = $this->l('Login Session Tracker');
        $this->description = $this->l('Registra el inicio de sesión y la navegación de los usuarios.');
        parent::__construct();
    }

    public function install()
    {
        return parent::install()
            && $this->registerHook('displayHeader')
            && $this->createDatabaseTable()
            && $this->registerHook('actionAuthentication');
    }

    public function uninstall()
    {
        return parent::uninstall() && $this->removeDatabaseTable();
    }

    private function createDatabaseTable()
    {
        $sql1 = "CREATE TABLE IF NOT EXISTS " . _DB_PREFIX_ . "loginsessiontracker (
        id_log INT AUTO_INCREMENT PRIMARY KEY,
        id_customer INT NOT NULL,
        fecha_inicio DATETIME NOT NULL,
        fecha_fin DATETIME NULL,
        last_ping DATETIME NULL,
        dispositivo VARCHAR(50) NULL,
        navegador VARCHAR(50) NULL,
        UNIQUE KEY unique_active_session (id_customer, fecha_fin),
        INDEX (id_customer)
    ) ENGINE=" . _MYSQL_ENGINE_ . " DEFAULT CHARSET=utf8;";

        $sql2 = "CREATE TABLE IF NOT EXISTS " . _DB_PREFIX_ . "loginsession_pages (
        id_page_log INT AUTO_INCREMENT PRIMARY KEY,
        id_log INT NOT NULL,
        pagina_visitada TEXT,
        fecha DATETIME NOT NULL,
        INDEX (id_log),
        FOREIGN KEY (id_log) REFERENCES " . _DB_PREFIX_ . "loginsessiontracker(id_log) ON DELETE CASCADE
    ) ENGINE=" . _MYSQL_ENGINE_ . " DEFAULT CHARSET=utf8;";

        return Db::getInstance()->execute($sql1) && Db::getInstance()->execute($sql2);
    }


    private function removeDatabaseTable()
    {
        try {
            $connection = Db::getInstance();
            $connection->execute('SET FOREIGN_KEY_CHECKS = 0');

            // Verificar si las tablas existen antes de intentar eliminarlas
            $pagesTableExists = $connection->getValue('
                SELECT COUNT(*) 
                FROM information_schema.tables 
                WHERE table_schema = DATABASE() 
                AND table_name = "' . _DB_PREFIX_ . 'loginsession_pages"
            ');

            $mainTableExists = $connection->getValue('
                SELECT COUNT(*) 
                FROM information_schema.tables 
                WHERE table_schema = DATABASE() 
                AND table_name = "' . _DB_PREFIX_ . 'loginsessiontracker"
            ');

            $success = true;

            if ($pagesTableExists) {
                $success = $success && $connection->execute('DROP TABLE ' . _DB_PREFIX_ . 'loginsession_pages');
            }

            if ($mainTableExists) {
                $success = $success && $connection->execute('DROP TABLE ' . _DB_PREFIX_ . 'loginsessiontracker');
            }

            $connection->execute('SET FOREIGN_KEY_CHECKS = 1');

            return $success;
        } catch (Exception $e) {
            // Registrar el error y continuar
            PrestaShopLogger::addLog('Error al eliminar tablas: ' . $e->getMessage(), 3);
            return false;
        }
    }

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

            Db::getInstance()->update(
                'loginsessiontracker',
                [
                    'fecha_fin' => $fecha_fin,
                ],
                'id_customer = ' . (int) $id_customer . ' AND fecha_inicio = "' . pSQL($fecha_inicio) . '"'
            );
        }
    }

    public function hookDisplayHeader()
    {
        try {
            if (!$this->context->customer->isLogged()) {
                return;
            }

            $id_customer = (int)$this->context->customer->id;
            $current_url = pSQL("https://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);

            if ($this->context->controller->php_self == 'pagenotfound') {
                return;
            }

            // Obtener sesión activa
            $active_session = Db::getInstance()->getValue('
                SELECT id_log FROM ' . _DB_PREFIX_ . 'loginsessiontracker
                WHERE id_customer = ' . $id_customer . '
                AND fecha_fin IS NULL
                ORDER BY fecha_inicio DESC
            ');

            if (!$active_session) {
                Db::getInstance()->insert('loginsessiontracker', [
                    'id_customer' => $id_customer,
                    'fecha_inicio' => date('Y-m-d H:i:s'),
                    'dispositivo' => pSQL($this->getDeviceType()),
                    'navegador' => pSQL($this->getBrowser())
                ]);
                $active_session = Db::getInstance()->Insert_ID();
            }

            // Registrar página visitada
            Db::getInstance()->insert('loginsession_pages', [
                'id_log' => (int)$active_session,
                'pagina_visitada' => $current_url,
                'fecha' => date('Y-m-d H:i:s')
            ]);

            $this->context->controller->registerJavascript(
                'module-loginsessiontracker-js',
                'modules/' . $this->name . '/views/js/sessionTracker.js',
                ['position' => 'bottom', 'priority' => 150]
            );
        } catch (Exception $e) {
            PrestaShopLogger::addLog('Error en hookDisplayHeader: ' . $e->getMessage(), 3);
        }
    }


    public function hookActionAuthentication($params)
    {
        try {
            PrestaShopLogger::addLog('hookActionAuthentication ejecutado', 1);

            if (!isset($params['customer']) || !Validate::isLoadedObject($params['customer'])) {
                PrestaShopLogger::addLog('Cliente no válido en hookActionAuthentication', 2);
                return;
            }

            $customer = $params['customer'];
            $id_customer = (int)$customer->id;
            $activeSessions = (int)Db::getInstance()->getValue(
                'SELECT COUNT(*) FROM ' . _DB_PREFIX_ . 'loginsessiontracker 
                WHERE id_customer = ' . (int)$id_customer . ' 
                AND fecha_fin IS NULL'
            );

            PrestaShopLogger::addLog("Sesiones activas actuales: $activeSessions", 1);

            if ($activeSessions === 0) {
                Db::getInstance()->insert('loginsessiontracker', [
                    'id_customer' => $id_customer,
                    'fecha_inicio' => date('Y-m-d H:i:s'),
                    'dispositivo' => pSQL($this->getDeviceType()),
                    'navegador' => pSQL($this->getBrowser())
                ]);
                PrestaShopLogger::addLog('Sesión iniciada en hookActionAuthentication', 1);
            }
        } catch (Exception $e) {
            PrestaShopLogger::addLog('[ERROR] hookActionAuthentication: ' . $e->getMessage(), 3);
        }
    }


    // Nuevos métodos auxiliares
    private function getDeviceType()
    {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        if (preg_match('/mobile/i', $userAgent)) {
            return 'Móvil';
        } elseif (preg_match('/tablet/i', $userAgent)) {
            return 'Tablet';
        }
        return 'PC';
    }

    private function getBrowser()
    {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $browsers = [
            'Chrome' => 'Chrome',
            'Firefox' => 'Firefox',
            'Safari' => 'Safari',
            'Opera' => 'Opera',
            'MSIE' => 'Internet Explorer',
            'Edge' => 'Edge',
            'Brave' => 'Brave'
        ];

        foreach ($browsers as $key => $value) {
            if (strpos($userAgent, $key) !== false) {
                return $value;
            }
        }
        return 'Otro';
    }
}
