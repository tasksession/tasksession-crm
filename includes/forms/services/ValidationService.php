<?php
/**
 * Forms Module – Validate mapped lead data before insert.
 */

class ValidationService
{
    /** @var array */
    private $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    /**
     * Validate mapped data has required lead fields and valid formats.
     * @return array ['valid' => bool, 'errors' => array]
     */
    public function validateMappedLead(array $mapped)
    {
        $errors = [];
        $name = trim((string)($mapped['name'] ?? ''));
        $name = self::normalizeNameFromPayload($name);
        if ($name === '') {
            $errors['name'] = 'Name is required';
        }
        $email = trim((string)($mapped['email'] ?? ''));
        if ($email !== '') {
            $email = self::normalizeEmailFromPayload($email);
            if (!FormsSecurityHelper::isValidEmail($email)) {
                $errors['email'] = 'Invalid email format';
            }
        }
        $expectedCloseDate = trim((string)($mapped['expected_close_date'] ?? ''));
        if ($expectedCloseDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $expectedCloseDate)) {
            $errors['expected_close_date'] = 'Invalid date format';
        }
        $assignedTo = trim((string)($mapped['assigned_to'] ?? ''));
        if ($assignedTo !== '' && !preg_match('/^\d+(,\d+)*$/', preg_replace('/\s+/', '', $assignedTo))) {
            $errors['assigned_to'] = 'Invalid assigned_to format';
        }
        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Build lead data array for ExistingLeadIntegrationService (only whitelisted columns + custom_fields).
     */
    public function buildLeadDataFromMapped(array $mapped)
    {
        $whitelist = $this->config['lead_columns_whitelist'] ?? [];
        $leadData = [];
        foreach ($whitelist as $col) {
            if (array_key_exists($col, $mapped)) {
                $value = $mapped[$col];
                if ($col === 'email' && is_string($value) && $value !== '') {
                    $value = self::normalizeEmailFromPayload($value);
                }
                if ($col === 'name' && is_string($value) && $value !== '') {
                    $value = self::normalizeNameFromPayload($value);
                }
                $leadData[$col] = $value;
            }
        }
        if (isset($mapped['custom_fields']) && is_array($mapped['custom_fields'])) {
            $leadData['custom_fields'] = $mapped['custom_fields'];
        }
        if (isset($mapped['tag_ids']) && is_array($mapped['tag_ids'])) {
            $leadData['tag_ids'] = $mapped['tag_ids'];
        }
        return $leadData;
    }

    /**
     * Normalize email string from form payloads (e.g. CF7) that may replace dots with underscores.
     * Only the domain part (after @) is adjusted so values like user@gmail_com become user@gmail.com.
     */
    public static function normalizeEmailFromPayload(string $email): string
    {
        $at = strpos($email, '@');
        if ($at === false || $at === strlen($email) - 1) {
            return $email;
        }
        $local = substr($email, 0, $at);
        $domain = str_replace('_', '.', substr($email, $at + 1));
        return $local . '@' . $domain;
    }

    /**
     * Normalize name from form payloads (e.g. CF7) that may replace spaces with underscores.
     * Converts "omer_nadeem" back to "omer nadeem".
     */
    public static function normalizeNameFromPayload(string $name): string
    {
        $name = str_replace('_', ' ', $name);
        return trim(preg_replace('/\s+/', ' ', $name));
    }
}
