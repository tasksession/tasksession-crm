<?php
ob_start();
require_once dirname(__DIR__) . '/includes/loader.php';
require_once dirname(__DIR__) . '/includes/initialize.php';
ob_clean();
include dirname(__DIR__) . '/includes/email-notification/api.php';
