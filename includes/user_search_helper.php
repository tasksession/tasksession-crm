<?php
/*
================================================================================
  User list search helpers — clients, members, trash
  Location: includes/user_search_helper.php
================================================================================
 */

if (!function_exists('comon_db_table_exists')) {
    function comon_db_table_exists(mysqli $connect, string $table): bool
    {
        $table = trim($table);
        if ($table === '') {
            return false;
        }
        $res = @mysqli_query($connect, "SHOW TABLES LIKE '" . mysqli_real_escape_string($connect, $table) . "'");
        return $res && mysqli_num_rows($res) > 0;
    }
}

if (!function_exists('comon_users_searchable_columns')) {
    /**
     * @return string[] Column names that exist on users table.
     */
    function comon_users_searchable_columns(mysqli $connect): array
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        $wanted = [
            'firstName',
            'last_name',
            'lastName',
            'email',
            'phone',
            'company',
            'address',
            'city',
            'state',
            'zip',
            'country',
            'website',
            'title',
            'note',
            'username',
            'teams_id',
            'fb',
        ];

        $existing = [];
        $res = @mysqli_query($connect, 'SHOW COLUMNS FROM users');
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $existing[(string) ($row['Field'] ?? '')] = true;
            }
        }

        $cached = [];
        foreach ($wanted as $col) {
            if (isset($existing[$col])) {
                $cached[] = $col;
            }
        }

        return $cached;
    }
}

if (!function_exists('comon_companies_searchable_columns')) {
    /**
     * @return string[] Column names that exist on client_companies table.
     */
    function comon_companies_searchable_columns(mysqli $connect): array
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        $wanted = [
            'name',
            'vat_number',
            'phone',
            'email',
            'website',
            'currency',
            'address',
            'city',
            'state',
            'zip',
            'country',
            'billing_address',
            'billing_street',
            'billing_city',
            'billing_state',
            'billing_zip',
            'billing_country',
        ];

        $existing = [];
        $res = @mysqli_query($connect, 'SHOW COLUMNS FROM client_companies');
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $existing[(string) ($row['Field'] ?? '')] = true;
            }
        }

        $cached = [];
        foreach ($wanted as $col) {
            if (isset($existing[$col])) {
                $cached[] = $col;
            }
        }

        return $cached;
    }
}

if (!function_exists('comon_build_user_match_sql_parts')) {
    /**
     * OR-match parts for a users row (standard columns + optional custom fields).
     *
     * @return string[]
     */
    function comon_build_user_match_sql_parts(mysqli $connect, string $like, string $userAlias, array $customFieldEntityTypes = []): array
    {
        $prefix = rtrim($userAlias, '.') . '.';
        $idCol = $prefix . 'id';
        $parts = [];

        foreach (comon_users_searchable_columns($connect) as $col) {
            $parts[] = $prefix . $col . ' LIKE ' . $like;
        }

        $columns = comon_users_searchable_columns($connect);
        $nameCols = [];
        if (in_array('firstName', $columns, true)) {
            $nameCols[] = $prefix . 'firstName';
        }
        if (in_array('last_name', $columns, true)) {
            $nameCols[] = "COALESCE({$prefix}last_name, '')";
        } elseif (in_array('lastName', $columns, true)) {
            $nameCols[] = "COALESCE({$prefix}lastName, '')";
        }
        if (count($nameCols) > 1) {
            $parts[] = 'CONCAT(' . implode(", ' ', ", $nameCols) . ') LIKE ' . $like;
        }

        $entityTypes = array_values(array_filter(array_map('strval', $customFieldEntityTypes)));
        if ($entityTypes !== [] && comon_db_table_exists($connect, 'user_custom_field_values') && comon_db_table_exists($connect, 'custom_fields')) {
            $escapedTypes = array_map(static function ($t) use ($connect) {
                return "'" . mysqli_real_escape_string($connect, $t) . "'";
            }, $entityTypes);
            $parts[] = "EXISTS (
                SELECT 1 FROM user_custom_field_values ucfv
                INNER JOIN custom_fields cf ON cf.id = ucfv.custom_field_id
                WHERE ucfv.user_id = {$idCol}
                  AND cf.entity_type IN (" . implode(', ', $escapedTypes) . ")
                  AND COALESCE(cf.is_disabled, 0) = 0
                  AND ucfv.field_value LIKE {$like}
            )";
        }

        return $parts;
    }
}

if (!function_exists('comon_user_list_search_sql')) {
    /**
     * Build SQL AND (...) fragment for user list search.
     *
     * @param mysqli $connect
     * @param string $searchQuery
     * @param array $options {
     *   @var string $alias users table alias without dot (default '' → users.)
     *   @var string[] $custom_field_entity_types e.g. ['client'] or ['staff','admin']
     *   @var bool $include_company_membership match linked client_companies.name
     * }
     */
    function comon_user_list_search_sql(mysqli $connect, string $searchQuery, array $options = []): string
    {
        $searchQuery = trim($searchQuery);
        if ($searchQuery === '') {
            return '';
        }

        $alias = isset($options['alias']) ? trim((string) $options['alias']) : '';
        $prefix = $alias !== '' ? $alias . '.' : 'users.';
        $idCol = $prefix . 'id';

        $safeSearch = mysqli_real_escape_string($connect, $searchQuery);
        $like = "'%" . $safeSearch . "%'";

        $entityTypes = isset($options['custom_field_entity_types']) && is_array($options['custom_field_entity_types'])
            ? array_values(array_filter(array_map('strval', $options['custom_field_entity_types'])))
            : [];

        $parts = comon_build_user_match_sql_parts($connect, $like, $alias !== '' ? $alias : 'users', $entityTypes);

        $includeCompany = !empty($options['include_company_membership']);
        if ($includeCompany
            && comon_db_table_exists($connect, 'client_companies')
            && comon_db_table_exists($connect, 'client_company_members')
        ) {
            $parts[] = "EXISTS (
                SELECT 1 FROM client_company_members m
                INNER JOIN client_companies c ON c.id = m.company_id AND c.deleted_at IS NULL
                WHERE m.user_id = {$idCol}
                  AND c.name LIKE {$like}
            )";
        }

        return ' AND (' . implode(' OR ', $parts) . ')';
    }
}

if (!function_exists('comon_company_list_search_sql')) {
    /**
     * Build SQL fragment appended to company list WHERE (alias c).
     *
     * @param array $options {
     *   @var bool $include_member_clients match linked client members (name, email, custom fields, etc.)
     * }
     */
    function comon_company_list_search_sql(mysqli $connect, string $searchQuery, string $companyAlias = 'c', array $options = []): string
    {
        $searchQuery = trim($searchQuery);
        if ($searchQuery === '') {
            return '';
        }

        $alias = trim($companyAlias) !== '' ? trim($companyAlias) : 'c';
        $safeSearch = mysqli_real_escape_string($connect, $searchQuery);
        $like = "'%" . $safeSearch . "%'";

        $parts = [];
        foreach (comon_companies_searchable_columns($connect) as $col) {
            $parts[] = $alias . '.' . $col . ' LIKE ' . $like;
        }

        if (comon_db_table_exists($connect, 'company_custom_field_values') && comon_db_table_exists($connect, 'custom_fields')) {
            $parts[] = "EXISTS (
                SELECT 1 FROM company_custom_field_values ccv
                INNER JOIN custom_fields cf ON cf.id = ccv.custom_field_id
                WHERE ccv.company_id = {$alias}.id
                  AND cf.entity_type = 'company'
                  AND COALESCE(cf.is_disabled, 0) = 0
                  AND ccv.field_value LIKE {$like}
            )";
        }

        $includeMembers = !empty($options['include_member_clients']);
        if ($includeMembers && comon_db_table_exists($connect, 'client_company_members')) {
            $memberParts = comon_build_user_match_sql_parts($connect, $like, 'u', ['client']);
            if ($memberParts !== []) {
                $parts[] = 'EXISTS (
                    SELECT 1 FROM client_company_members m
                    INNER JOIN users u ON u.id = m.user_id
                    WHERE m.company_id = ' . $alias . '.id
                      AND (' . implode(' OR ', $memberParts) . ')
                )';
            }
        }

        if ($parts === []) {
            return '';
        }

        return ' AND (' . implode(' OR ', $parts) . ')';
    }
}

if (!function_exists('comon_prefetch_client_picker_search_extras')) {
    /**
     * Batch-load custom field values and linked company names for client picker search strings.
     *
     * @param int[] $userIds
     * @return array{custom: array<int, string[]>, companies: array<int, string[]>}
     */
    function comon_prefetch_client_picker_search_extras(mysqli $connect, array $userIds): array
    {
        $custom = [];
        $companies = [];
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds), static function ($id) {
            return $id > 0;
        })));

        if ($userIds === []) {
            return ['custom' => $custom, 'companies' => $companies];
        }

        $idList = implode(',', $userIds);

        if (comon_db_table_exists($connect, 'user_custom_field_values') && comon_db_table_exists($connect, 'custom_fields')) {
            $q = "SELECT ucfv.user_id, ucfv.field_value
                FROM user_custom_field_values ucfv
                INNER JOIN custom_fields cf ON cf.id = ucfv.custom_field_id
                WHERE ucfv.user_id IN ({$idList})
                  AND cf.entity_type = 'client'
                  AND COALESCE(cf.is_disabled, 0) = 0
                  AND ucfv.field_value IS NOT NULL
                  AND ucfv.field_value <> ''";
            $res = @mysqli_query($connect, $q);
            if ($res) {
                while ($row = mysqli_fetch_assoc($res)) {
                    $uid = (int) ($row['user_id'] ?? 0);
                    $val = trim((string) ($row['field_value'] ?? ''));
                    if ($uid <= 0 || $val === '') {
                        continue;
                    }
                    if (!isset($custom[$uid])) {
                        $custom[$uid] = [];
                    }
                    $custom[$uid][] = $val;
                }
            }
        }

        if (comon_db_table_exists($connect, 'client_companies') && comon_db_table_exists($connect, 'client_company_members')) {
            $q = "SELECT m.user_id, c.name
                FROM client_company_members m
                INNER JOIN client_companies c ON c.id = m.company_id AND c.deleted_at IS NULL
                WHERE m.user_id IN ({$idList})";
            $res = @mysqli_query($connect, $q);
            if ($res) {
                while ($row = mysqli_fetch_assoc($res)) {
                    $uid = (int) ($row['user_id'] ?? 0);
                    $name = trim((string) ($row['name'] ?? ''));
                    if ($uid <= 0 || $name === '') {
                        continue;
                    }
                    if (!isset($companies[$uid])) {
                        $companies[$uid] = [];
                    }
                    $companies[$uid][] = $name;
                }
            }
        }

        return ['custom' => $custom, 'companies' => $companies];
    }
}

if (!function_exists('comon_build_client_picker_search_text')) {
    /**
     * Lowercase search blob for client-side picker filtering.
     */
    function comon_build_client_picker_search_text(object $user, array $extras = []): string
    {
        $lastName = '';
        if (isset($user->lastName) && (string) $user->lastName !== '') {
            $lastName = (string) $user->lastName;
        } elseif (isset($user->last_name) && (string) $user->last_name !== '') {
            $lastName = (string) $user->last_name;
        }

        $parts = [
            trim((string) ($user->firstName ?? '')),
            $lastName,
            trim((string) ($user->email ?? '')),
            trim((string) ($user->phone ?? '')),
            trim((string) ($user->company ?? '')),
            trim((string) ($user->address ?? '')),
            trim((string) ($user->city ?? '')),
            trim((string) ($user->state ?? '')),
            trim((string) ($user->zip ?? '')),
            trim((string) ($user->country ?? '')),
            trim((string) ($user->website ?? '')),
            trim((string) ($user->title ?? '')),
            trim((string) ($user->teams_id ?? '')),
            trim((string) ($user->fb ?? '')),
        ];

        $uid = (int) ($user->id ?? 0);
        if ($uid > 0) {
            if (!empty($extras['custom'][$uid]) && is_array($extras['custom'][$uid])) {
                $parts = array_merge($parts, $extras['custom'][$uid]);
            }
            if (!empty($extras['companies'][$uid]) && is_array($extras['companies'][$uid])) {
                $parts = array_merge($parts, $extras['companies'][$uid]);
            }
        }

        $parts = array_filter(array_map('trim', $parts), static function ($p) {
            return $p !== '';
        });

        return strtolower(implode(' ', $parts));
    }
}

if (!function_exists('comon_prefetch_company_picker_search_extras')) {
    /**
     * Batch-load company custom field values and linked member names for picker search.
     *
     * @param int[] $companyIds
     * @return array{custom: array<int, string[]>, members: array<int, string[]>}
     */
    function comon_prefetch_company_picker_search_extras(mysqli $connect, array $companyIds): array
    {
        $custom = [];
        $members = [];
        $companyIds = array_values(array_unique(array_filter(array_map('intval', $companyIds), static function ($id) {
            return $id > 0;
        })));

        if ($companyIds === []) {
            return ['custom' => $custom, 'members' => $members];
        }

        $idList = implode(',', $companyIds);

        if (comon_db_table_exists($connect, 'company_custom_field_values') && comon_db_table_exists($connect, 'custom_fields')) {
            $q = "SELECT ccv.company_id, ccv.field_value
                FROM company_custom_field_values ccv
                INNER JOIN custom_fields cf ON cf.id = ccv.custom_field_id
                WHERE ccv.company_id IN ({$idList})
                  AND cf.entity_type = 'company'
                  AND COALESCE(cf.is_disabled, 0) = 0
                  AND ccv.field_value IS NOT NULL
                  AND ccv.field_value <> ''";
            $res = @mysqli_query($connect, $q);
            if ($res) {
                while ($row = mysqli_fetch_assoc($res)) {
                    $cid = (int) ($row['company_id'] ?? 0);
                    $val = trim((string) ($row['field_value'] ?? ''));
                    if ($cid <= 0 || $val === '') {
                        continue;
                    }
                    if (!isset($custom[$cid])) {
                        $custom[$cid] = [];
                    }
                    $custom[$cid][] = $val;
                }
            }
        }

        if (comon_db_table_exists($connect, 'client_company_members')) {
            $q = "SELECT m.company_id, u.firstName, u.email, u.phone, u.teams_id
                FROM client_company_members m
                INNER JOIN users u ON u.id = m.user_id
                WHERE m.company_id IN ({$idList})";
            $res = @mysqli_query($connect, $q);
            if ($res) {
                while ($row = mysqli_fetch_assoc($res)) {
                    $cid = (int) ($row['company_id'] ?? 0);
                    if ($cid <= 0) {
                        continue;
                    }
                    if (!isset($members[$cid])) {
                        $members[$cid] = [];
                    }
                    foreach (['firstName', 'email', 'phone', 'teams_id'] as $field) {
                        $val = trim((string) ($row[$field] ?? ''));
                        if ($val !== '') {
                            $members[$cid][] = $val;
                        }
                    }
                }
            }
        }

        return ['custom' => $custom, 'members' => $members];
    }
}

if (!function_exists('comon_build_company_picker_search_text')) {
    /**
     * Lowercase search blob for company picker client-side filtering.
     */
    function comon_build_company_picker_search_text(mysqli $connect, array $row, array $extras = []): string
    {
        $parts = [];
        foreach (comon_companies_searchable_columns($connect) as $col) {
            if (array_key_exists($col, $row)) {
                $parts[] = trim((string) $row[$col]);
            }
        }

        $cid = (int) ($row['id'] ?? 0);
        if ($cid > 0) {
            if (!empty($extras['custom'][$cid]) && is_array($extras['custom'][$cid])) {
                $parts = array_merge($parts, $extras['custom'][$cid]);
            }
            if (!empty($extras['members'][$cid]) && is_array($extras['members'][$cid])) {
                $parts = array_merge($parts, $extras['members'][$cid]);
            }
        }

        $parts = array_filter(array_map('trim', $parts), static function ($p) {
            return $p !== '';
        });

        return strtolower(implode(' ', $parts));
    }
}
