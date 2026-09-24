<?php
/**
 * Forms Module – Default configuration
 * Do not put secrets here in production; use environment or a protected config file.
 */

// __DIR__ = includes/forms/config -> 4 levels up = project root
defined('SITE_ROOT') || define('SITE_ROOT', dirname(dirname(dirname(dirname(__DIR__)))));

return [
    // Default lead status_id when not provided (0 = use DB default)
    'default_status_id' => 0,
    // Default lead source_id when not provided (0 = use DB default)
    'default_source_id' => 0,
    // User ID to set as created_by for form/API leads (0 = no activity/notify; set to first admin ID to attribute)
    'default_created_by' => 0,
    // Duplicate detection: 'allow' | 'block' | 'mark'
    'duplicate_behavior' => 'allow',
    // Fields to check for duplicate (email, phone)
    'duplicate_check_fields' => ['email', 'phone'],
    // Whitelist: only these lead table columns can be used as mapping destinations
    'lead_columns_whitelist' => [
        'name', 'last_name', 'email', 'phone', 'website', 'company', 'position', 'description',
        'lead_value', 'currency', 'priority', 'assigned_to', 'expected_close_date',
        'country', 'zip', 'city', 'state', 'address', 'notes',
        'status_id', 'source_id',
    ],
    // Custom field destination: validated at runtime against custom_fields where entity_type='lead'
    // No static list here; load from DB in FieldMappingService
    'debug_log_file' => SITE_ROOT . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'forms_debug.log',
    // Rate limit: placeholder (not implemented in v1)
    'rate_limit_enabled' => false,
    'rate_limit_max_requests_per_minute' => 60,
];
