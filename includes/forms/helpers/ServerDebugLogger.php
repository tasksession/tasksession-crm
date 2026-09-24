<?php
/**
 * Forms Module – Server-side detailed debug logger.
 * Writes to logs/forms_api_detailed.log with full request data, all steps, and possible failure reasons.
 */

class ServerDebugLogger
{
    /** @var string */
    private static $logPath;
    /** @var string */
    private static $requestId;
    /** @var bool */
    private static $enabled = true;

    /**
     * Initialize. Call once at start of request.
     * @param string $logsDir Full path to logs directory (e.g. project root . /logs)
     */
    public static function init($logsDir)
    {
        $dir = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $logsDir), DIRECTORY_SEPARATOR);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        self::$logPath = $dir . DIRECTORY_SEPARATOR . 'forms_api_detailed.log';
    }

    public static function setRequestId($requestId)
    {
        self::$requestId = $requestId;
    }

    public static function disable()
    {
        self::$enabled = false;
    }

    public static function enable()
    {
        self::$enabled = true;
    }

    /**
     * Write a line to the detailed log file.
     */
    private static function write($line)
    {
        if (!self::$enabled || self::$logPath === null) return;
        $ts = date('Y-m-d H:i:s');
        $rid = self::$requestId !== null ? self::$requestId : '-';
        $entry = "[{$ts}] [{$rid}] {$line}\n";
        @file_put_contents(self::$logPath, $entry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Log start of request with method, integration, and all received fields.
     */
    public static function logRequestStart($method, $integrationKey, $headersSummary, $rawBodyLength, array $parsedPayload)
    {
        self::write("========== REQUEST START ==========");
        self::write("METHOD: " . $method);
        self::write("INTEGRATION_PARAM: " . ($integrationKey === null ? 'null' : $integrationKey));
        self::write("RAW_BODY_LENGTH: " . $rawBodyLength);
        self::write("HEADERS: " . $headersSummary);
        self::write("PARSED_PAYLOAD_FIELDS: " . json_encode(self::safeForLog($parsedPayload), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        self::write("-----------------------------------");
    }

    /**
     * Log a check step (integration, token, etc.).
     */
    public static function logStep($stepName, $status, $message = '', array $data = [])
    {
        $line = "[STEP] {$stepName} | {$status}";
        if ($message !== '') $line .= " | {$message}";
        self::write($line);
        if (!empty($data)) {
            self::write("  DATA: " . json_encode(self::safeForLog($data), JSON_UNESCAPED_UNICODE));
        }
    }

    /**
     * Log possible failure reasons (validation, mapping, DB, etc.).
     */
    public static function logPossibleReasons(array $reasons)
    {
        self::write("POSSIBLE_REASONS:");
        foreach ($reasons as $r) {
            self::write("  - " . $r);
        }
    }

    /**
     * Log capture result (mapped data, validation errors, lead creation result).
     */
    public static function logCaptureResult($success, $leadId, $message, array $errors = null, array $mapped = null)
    {
        self::write("[CAPTURE_RESULT] success=" . ($success ? 'yes' : 'no') . " | lead_id=" . ($leadId ?? 'null') . " | message=" . $message);
        if ($errors !== null && $errors !== []) {
            self::write("  ERRORS: " . json_encode(self::safeForLog($errors), JSON_UNESCAPED_UNICODE));
        }
        if ($mapped !== null && $mapped !== []) {
            self::write("  MAPPED_DATA: " . json_encode(self::safeForLog($mapped), JSON_UNESCAPED_UNICODE));
        }
    }

    /**
     * Log an exception with full trace.
     */
    public static function logException(Throwable $e)
    {
        self::write("========== EXCEPTION ==========");
        self::write("MESSAGE: " . $e->getMessage());
        self::write("FILE: " . $e->getFile() . " (" . $e->getLine() . ")");
        self::write("TRACE: " . str_replace("\n", "\n  ", $e->getTraceAsString()));
        self::write("-----------------------------------");
    }

    /**
     * Log end of request and response summary.
     */
    public static function logRequestEnd($httpCode, $responseSummary = '')
    {
        self::write("RESPONSE: HTTP {$httpCode}" . ($responseSummary !== '' ? " | {$responseSummary}" : ''));
        self::write("========== REQUEST END ==========");
        self::write("");
    }

    private static function safeForLog($data)
    {
        if (!is_array($data)) return $data;
        $out = [];
        foreach ($data as $k => $v) {
            if (in_array((string)$k, ['auth_token', 'token', 'password'], true)) {
                $out[$k] = '[REDACTED]';
            } elseif (is_array($v)) {
                $out[$k] = self::safeForLog($v);
            } else {
                $out[$k] = $v;
            }
        }
        return $out;
    }
}
