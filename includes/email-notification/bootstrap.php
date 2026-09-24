<?php
$root = dirname(__DIR__);
if (!class_exists('User', false) && file_exists($root . '/user.php')) {
    require_once $root . '/user.php';
}
require_once __DIR__ . '/ChatMentionUtil.php';
require_once __DIR__ . '/ChatEmailContextRepository.php';
require_once __DIR__ . '/ChatEmailUserPreferenceRepository.php';
require_once __DIR__ . '/ChatEmailContextMaps.php';
require_once __DIR__ . '/ChatEmailPolicy.php';
require_once __DIR__ . '/ChatEmailBatchFilter.php';
