<?php
/**
 * Shared helpers for company profile sales / invoice statistics.
 */

if (!function_exists('company_profile_milestone_scope_sql')) {
    function company_profile_milestone_scope_sql(mysqli $connect, int $companyId, array $companyMembers): string
    {
        static $hasCompanyCol = null;
        if ($hasCompanyCol === null) {
            $colChk = @mysqli_query($connect, "SHOW COLUMNS FROM `milestones` LIKE 'company_id'");
            $hasCompanyCol = $colChk && mysqli_num_rows($colChk) > 0;
            if ($colChk) {
                mysqli_free_result($colChk);
            }
        }

        if ($hasCompanyCol) {
            return ' AND m.company_id = ' . (int) $companyId;
        }

        $memberIds = array_values(array_filter(array_map(static function ($m) {
            return (int) ($m['id'] ?? 0);
        }, $companyMembers)));

        if ($memberIds === []) {
            return ' AND 1=0';
        }

        $parts = [];
        foreach ($memberIds as $uid) {
            $parts[] = '(m.c_id = ' . $uid . ' OR p.c_id = ' . $uid . ' OR p.main_client_id = ' . $uid . ')';
        }

        return ' AND (' . implode(' OR ', $parts) . ')';
    }
}

if (!function_exists('company_profile_calculate_invoice_total')) {
    function company_profile_calculate_invoice_total(array $milestone): float
    {
        require_once __DIR__ . '/invoice_item.php';
        $subtotal = 0.0;
        $invoiceItems = InvoiceItem::findByMilestoneId((int) ($milestone['id'] ?? 0));
        if ($invoiceItems && count($invoiceItems) > 0) {
            foreach ($invoiceItems as $item) {
                $subtotal += (float) $item->rate * (float) $item->quantity;
            }
        } else {
            $subtotal = (float) ($milestone['budget'] ?? 0);
        }

        $discount = isset($milestone['discount']) ? (float) $milestone['discount'] : 0.0;
        $discountType = $milestone['discount_type'] ?? 'percentage';
        $discountAmount = 0.0;
        if ($discount > 0 && $subtotal > 0) {
            $discountAmount = $discountType === 'percentage' ? ($subtotal * $discount) / 100 : $discount;
        }

        $amountAfterDiscount = $subtotal - $discountAmount;
        $salesTax = isset($milestone['sales_tax']) ? (float) $milestone['sales_tax'] : 0.0;
        $salesTaxType = $milestone['sales_tax_type'] ?? 'percentage';
        $taxAmount = 0.0;
        if ($salesTax > 0 && $amountAfterDiscount > 0) {
            $taxAmount = $salesTaxType === 'percentage' ? ($amountAfterDiscount * $salesTax) / 100 : $salesTax;
        }

        return $amountAfterDiscount + $taxAmount;
    }
}

if (!function_exists('company_profile_milestone_belongs_to_company')) {
    /**
     * Verify a milestone is in scope for the given company (prevents IDOR on invoice actions).
     */
    function company_profile_milestone_belongs_to_company(mysqli $connect, int $companyId, int $milestoneId, array $companyMembers): bool
    {
        if ($companyId <= 0 || $milestoneId <= 0) {
            return false;
        }
        $scopeSql = company_profile_milestone_scope_sql($connect, $companyId, $companyMembers);
        $sql = 'SELECT m.id FROM milestones m LEFT JOIN projects p ON m.p_id = p.p_id WHERE m.id = '
            . (int) $milestoneId . ' ' . $scopeSql . ' LIMIT 1';
        $res = mysqli_query($connect, $sql);
        if (!$res) {
            return false;
        }
        $row = mysqli_fetch_assoc($res);
        mysqli_free_result($res);

        return (bool) $row;
    }
}

if (!function_exists('company_profile_currency_symbol')) {
    function company_profile_currency_symbol(string $currencyString): string
    {
        if ($currencyString === '') {
            return '$';
        }
        if (strlen($currencyString) <= 3) {
            return $currencyString;
        }
        $parts = explode(',', $currencyString);

        return isset($parts[1]) && $parts[1] !== '' ? (string) $parts[1] : (string) $parts[0];
    }
}

if (!function_exists('company_profile_milestone_total')) {
    function company_profile_milestone_total($milestone): float
    {
        if (is_array($milestone)) {
            return company_profile_calculate_invoice_total($milestone);
        }

        return company_profile_calculate_invoice_total([
            'id' => $milestone->id ?? 0,
            'budget' => $milestone->budget ?? 0,
            'sales_tax' => $milestone->sales_tax ?? 0,
            'sales_tax_type' => $milestone->sales_tax_type ?? 'percentage',
            'discount' => $milestone->discount ?? 0,
            'discount_type' => $milestone->discount_type ?? 'percentage',
        ]);
    }
}

if (!function_exists('profile_sales_currency_country_name')) {
    function profile_sales_currency_country_name(string $currencyCode): string
    {
        switch ($currencyCode) {
            case 'USD': return 'United States';
            case 'PKR': return 'Pakistan';
            case 'Rs': return 'Pakistan';
            case 'CAD': return 'Canada';
            case 'EUR': return 'European Union';
            case 'GBP': return 'United Kingdom';
            case 'INR': return 'India';
            case 'JPY': return 'Japan';
            case 'AUD': return 'Australia';
            case 'CHF': return 'Switzerland';
            case 'CNY': return 'China';
            default: return $currencyCode;
        }
    }
}

if (!function_exists('profile_sales_encode_currency_option')) {
    /**
     * @return array{value:string,display:string}
     */
    function profile_sales_encode_currency_option(string $currency, $settings = null): array
    {
        $fallback = ['value' => 'USD,$', 'display' => 'United States ($)'];
        $currency = trim($currency);
        if ($currency === '') {
            return $fallback;
        }

        $currencyCode = $currency;
        $currencySymbol = '';
        if (strpos($currency, ',') !== false) {
            $currencyParts = explode(',', $currency);
            $currencyCode = trim($currencyParts[0]);
            $currencySymbol = isset($currencyParts[1]) ? trim($currencyParts[1]) : $currencyCode;
        } else {
            if (!$settings) {
                $settings = settings::findById(1);
            }
            $symbolText = $settings->currency_symbols[$currencyCode] ?? $currencyCode;
            if (preg_match('/\((.*?)\)/', (string) $symbolText, $m)) {
                $currencySymbol = $m[1];
            } else {
                $currencySymbol = $currencyCode;
            }
        }

        return [
            'value' => (strpos($currency, ',') !== false) ? $currency : ($currencyCode . ',' . $currencySymbol),
            'display' => profile_sales_currency_country_name($currencyCode) . ' (' . $currencySymbol . ')',
        ];
    }
}

if (!function_exists('profile_sales_order_currencies')) {
    /**
     * Put invoice system default currency first in Sales Statistics dropdown.
     */
    function profile_sales_order_currencies(array $currencies, $settings = null, string $preferredCurrency = ''): array
    {
        $currencies = array_values(array_filter(array_map(static function ($currency) {
            return trim((string) $currency);
        }, $currencies)));

        if ($currencies === []) {
            return [];
        }

        if (!$settings) {
            $settings = settings::findById(1);
        }

        $priority = trim($preferredCurrency);
        if ($priority === '') {
            $priority = trim((string) ($settings->system_currency ?? ''));
        }
        if ($priority === '') {
            return $currencies;
        }

        $priorityCode = trim(explode(',', $priority)[0]);
        $first = null;
        $rest = [];
        foreach ($currencies as $currency) {
            $code = trim(explode(',', $currency)[0]);
            if ($first === null && ($currency === $priority || $code === $priorityCode)) {
                $first = $currency;
                continue;
            }
            $rest[] = $currency;
        }

        if ($first === null) {
            return $currencies;
        }

        return array_merge([$first], $rest);
    }
}

if (!function_exists('profile_sales_default_currency_option')) {
    /**
     * Default Sales Statistics currency from invoice settings (system default), then fallback.
     *
     * @return array{value:string,display:string}
     */
    function profile_sales_default_currency_option(array $orderedCurrencies, $settings = null, string $preferredCurrency = ''): array
    {
        $fallback = ['value' => 'USD,$', 'display' => 'United States ($)'];
        if ($orderedCurrencies === []) {
            return $fallback;
        }

        if (!$settings) {
            $settings = settings::findById(1);
        }

        $candidates = [];
        if (trim($preferredCurrency) !== '') {
            $candidates[] = trim($preferredCurrency);
        }
        $system = trim((string) ($settings->system_currency ?? ''));
        if ($system !== '') {
            $candidates[] = $system;
        }

        foreach ($candidates as $candidate) {
            $candidateCode = trim(explode(',', $candidate)[0]);
            foreach ($orderedCurrencies as $currency) {
                $currency = trim((string) $currency);
                if ($currency === '') {
                    continue;
                }
                $currencyCode = trim(explode(',', $currency)[0]);
                if ($currency === $candidate || $currencyCode === $candidateCode) {
                    return profile_sales_encode_currency_option($currency, $settings);
                }
            }
        }

        return profile_sales_encode_currency_option((string) $orderedCurrencies[0], $settings);
    }
}

if (!function_exists('profile_sales_first_currency_option')) {
    /** @deprecated Use profile_sales_default_currency_option() */
    function profile_sales_first_currency_option(array $orderedCurrencies, $settings = null): array
    {
        return profile_sales_default_currency_option($orderedCurrencies, $settings);
    }
}

if (!function_exists('company_sales_currency_display_name')) {
    function company_sales_currency_display_name(string $currencyCode): string
    {
        if ($currencyCode === '') {
            return 'United States ($)';
        }

        $cleanCurrencyCode = explode(',', $currencyCode)[0];
        $currencyCountries = [
            'PKR' => 'Pakistan (Rs)',
            'Rs' => 'Pakistan (Rs)',
            'USD' => 'United States ($)',
            'CAD' => 'Canada (C$)',
            'EUR' => 'European Union (€)',
            'GBP' => 'United Kingdom (£)',
            'INR' => 'India (₹)',
            'JPY' => 'Japan (¥)',
            'AUD' => 'Australia (A$)',
            'CHF' => 'Switzerland (CHF)',
            'CNY' => 'China (¥)',
        ];

        return $currencyCountries[$cleanCurrencyCode] ?? $cleanCurrencyCode;
    }
}

if (!function_exists('company_sales_currency_sql_condition')) {
    function company_sales_currency_sql_condition(string $currencyFilter): string
    {
        if ($currencyFilter === '' || $currencyFilter === 'all') {
            return '';
        }

        global $database;
        $currencyParts = explode(',', $currencyFilter);
        $currencyCode = trim($currencyParts[0]);
        if ($currencyCode === '') {
            return '';
        }

        $esc = $database->escapeValue($currencyCode);

        return " AND (m.currency LIKE '{$esc},%' OR m.currency = '{$esc}' OR m.currency LIKE '%,{$esc}')";
    }
}
