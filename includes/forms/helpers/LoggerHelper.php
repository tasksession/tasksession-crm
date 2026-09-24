<?php
/**
 * Forms Module – Debug logger
 * Writes to logs/forms_debug.log and optionally to forms_debug_logs table.
 */

class FormsLoggerHelper
{
    /** @var string */
    private static $logFile;
    /** @var mysqli|null */
    private static $connect;
    /** @var bool */
    private static $enabled = true;
    /** @var bool */
    private static $checkedEnabled = false;
    /** @var string */
    private static $requestId;

    public static function init($logFile, $connect = null)
    {
        self::$logFile = $logFile;
        self::$connect = $connect;
        self::$requestId = self::generateRequestId();
        self::$checkedEnabled = false;
    }

    /**
     * Explicitly enable/disable forms debug logging.
     * If not called, the logger will lazy-load the value from the settings table (forms_debug_enabled).
     */
    public static function setEnabled($enabled)
    {
        self::$enabled = (bool)$enabled;
        self::$checkedEnabled = true;
    }

    public static function isEnabled()
    {
        if (!self::$checkedEnabled) {
            self::refreshEnabledFromSettings();
        }
        return self::$enabled;
    }

    public static function getRequestId()
    {
        if (self::$requestId === null) {
            self::$requestId = self::generateRequestId();
        }
        return self::$requestId;
    }

    private static function generateRequestId()
    {
        return bin2hex(random_bytes(8));
    }

    /**
     * @param string $level info|warning|error|debug
     * @param string $message
     * @param string|null $route
     * @param string|null $source
     * @param array|null $context
     */
    public static function log($level, $message, $route = null, $source = null, array $context = null)
    {
        // Check global toggle first; if disabled, skip all logging.
        if (!self::isEnabled()) {
            return;
        }

        $requestId = self::getRequestId();
        $ts = date('Y-m-d H:i:s');
        $line = sprintf("[%s] [%s] [%s] %s %s %s\n",
            $ts,
            strtoupper($level),
            $requestId,
            $route !== null ? "route=$route" : '',
            $source !== null ? "source=$source" : '',
            $message
        );
        if ($context !== null && $context !== []) {
            $line .= '  context: ' . json_encode($context, JSON_UNESCAPED_UNICODE) . "\n";
        }

        if (self::$logFile !== null && self::$logFile !== '') {
            $dir = dirname(self::$logFile);
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            @file_put_contents(self::$logFile, $line, FILE_APPEND | LOCK_EX);
        }

        if (self::$connect !== null && self::tableExists()) {
            $contextJson = ($context !== null && $context !== []) ? json_encode($context, JSON_UNESCAPED_UNICODE) : null;
            $msgTruncated = strlen($message) > 500 ? substr($message, 0, 497) . '...' : $message;
            $stmt = self::$connect->prepare("INSERT INTO forms_debug_logs (level, route, source, request_id, message, context_json) VALUES (?, ?, ?, ?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param('ssssss',
                    $level,
                    $route,
                    $source,
                    $requestId,
                    $msgTruncated,
                    $contextJson
                );
                @$stmt->execute();
                $stmt->close();
            }
        }
    }

    private static function tableExists()
    {
        if (self::$connect === null) return false;
        $r = mysqli_query(self::$connect, "SHOW TABLES LIKE 'forms_debug_logs'");
        return $r && mysqli_fetch_assoc($r);
    }

    /**
     * Lazy-load the global enabled flag from the settings table (forms_debug_enabled).
     * Falls back to enabled=1 if the column/table does not exist.
     */
    private static function refreshEnabledFromSettings()
    {
        self::$checkedEnabled = true;
        // If we don't have a DB connection, default to enabled so file logging still works.
        if (self::$connect === null) {
            self::$enabled = true;
            return;
        }

        // Ensure settings table exists
        $tableCheck = mysqli_query(self::$connect, "SHOW TABLES LIKE 'settings'");
        if (!$tableCheck || !mysqli_fetch_assoc($tableCheck)) {
            self::$enabled = true;
            return;
        }

        // Ensure forms_debug_enabled column exists; if not, add it with default 1
        $colCheck = mysqli_query(self::$connect, "SHOW COLUMNS FROM settings LIKE 'forms_debug_enabled'");
        if (!$colCheck || !mysqli_fetch_assoc($colCheck)) {
            @mysqli_query(self::$connect, "ALTER TABLE settings ADD COLUMN forms_debug_enabled TINYINT(1) NOT NULL DEFAULT 1");
            self::$enabled = true;
            return;
        }

        $res = mysqli_query(self::$connect, "SELECT forms_debug_enabled FROM settings WHERE id = 1 LIMIT 1");
        if ($res && ($row = mysqli_fetch_assoc($res))) {
            self::$enabled = (int)$row['forms_debug_enabled'] === 1;
        } else {
            self::$enabled = true;
        }
    }

    public static function info($message, $route = null, $source = null, array $context = null)
    {
        self::log('info', $message, $route, $source, $context);
    }

    public static function warning($message, $route = null, $source = null, array $context = null)
    {
        self::log('warning', $message, $route, $source, $context);
    }

    public static function error($message, $route = null, $source = null, array $context = null)
    {
        self::log('error', $message, $route, $source, $context);
    }

    public static function debug($message, $route = null, $source = null, array $context = null)
    {
        self::log('debug', $message, $route, $source, $context);
    }
}
