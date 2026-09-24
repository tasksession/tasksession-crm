<?php
/**
 * Minimal bootstrap for company CSV import (avoids loading unrelated import runners).
 */
$__comon_ie_dir = __DIR__;
require_once $__comon_ie_dir . '/CsvUtilities.php';
require_once $__comon_ie_dir . '/XlsxSimpleReader.php';
require_once $__comon_ie_dir . '/ImportJobService.php';
require_once $__comon_ie_dir . '/CompanyImportRunner.php';
