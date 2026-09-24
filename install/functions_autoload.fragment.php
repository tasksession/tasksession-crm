// ===============================================================================
// Autoload Classes using spl_autoload_register()
// Resolve from includes/ using absolute paths (VPS PHP-FPM CWD differs from script dir).
// ===============================================================================
spl_autoload_register(function ($class_name) {
    $lower = strtolower($class_name);
    $candidates = array();

    if (defined('LIB_ROOT')) {
        $candidates[] = LIB_ROOT . DS . $lower . '.php';
        $snake = strtolower((string)preg_replace('/([a-z])([A-Z])/', '$1_$2', $class_name));
        if ($snake !== $lower) {
            $candidates[] = LIB_ROOT . DS . $snake . '.php';
        }
    }

    if (defined('SITE_ROOT')) {
        $candidates[] = SITE_ROOT . DS . $lower . '.php';
        if (defined('LIB_ROOT')) {
            $candidates[] = SITE_ROOT . DS . 'includes' . DS . $lower . '.php';
        }
    }

    $candidates[] = $lower . '.php';

    foreach ($candidates as $path) {
        if ($path !== '' && is_file($path)) {
            require_once $path;
            return;
        }
    }

    if (strpos($lower, 'tasksession') === 0) {
        $map = array(
            'tasksessionecommercemanager' => 'class-tasksession-ecommerce-manager.php',
            'tasksessionwoapiservice' => 'class-tasksession-woo-api.php',
            'tasksessionwoosettings' => 'class-tasksession-woo-settings.php',
            'tasksessionsenderapiservice' => 'class-tasksession-sender-api.php',
        );
        if (isset($map[$lower])) {
            $file = dirname(__DIR__) . '/vendor/woocommerce/includes/' . $map[$lower];
            if (is_file($file)) {
                require_once $file;
            }
        }
    }
});
