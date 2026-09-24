<?php
/**
 * CLI bootstrap for admin "Run all cron jobs" subprocess runs.
 * Defines central-cron flags so child scripts do not exit(1) when idle/disabled.
 */
define('_CRON_CENTRAL_MODE', true);
define('_CRON_AJAX_MODE', true);
putenv('CENTRAL_CRON=1');

$script = isset($argv[1]) ? (string) $argv[1] : '';
if ($script === '' || !is_file($script)) {
    fwrite(STDERR, "Cron bootstrap: script not found\n");
    exit(1);
}

require $script;
