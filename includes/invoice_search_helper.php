<?php
/*
================================================================================
  Invoice list search helper
  Location: includes/invoice_search_helper.php
================================================================================
 */

require_once __DIR__ . '/user_search_helper.php';

if (!function_exists('comon_invoice_list_search_or_group')) {
    /**
     * Parenthesized OR group for invoice list search (no leading AND).
     */
    function comon_invoice_list_search_or_group(mysqli $connect, string $searchQuery): string
    {
        $searchQuery = trim($searchQuery);
        if ($searchQuery === '') {
            return '';
        }

        $safeSearch = mysqli_real_escape_string($connect, $searchQuery);
        $like = "'%" . $safeSearch . "%'";

        $parts = [
            'm.title LIKE ' . $like,
            'p.project_title LIKE ' . $like,
            'CAST(m.id AS CHAR) LIKE ' . $like,
            'm.budget LIKE ' . $like,
            'm.memo LIKE ' . $like,
            'm.bill_from LIKE ' . $like,
            'm.bill_from_address LIKE ' . $like,
        ];

        if (comon_db_table_exists($connect, 'invoice_items')) {
            $parts[] = "EXISTS (
                SELECT 1 FROM invoice_items ii
                WHERE ii.milestone_id = m.id
                  AND (
                    ii.description LIKE {$like}
                    OR ii.item_description LIKE {$like}
                    OR CAST(ii.rate AS CHAR) LIKE {$like}
                    OR CAST(ii.quantity AS CHAR) LIKE {$like}
                    OR CAST((ii.rate * ii.quantity) AS CHAR) LIKE {$like}
                  )
            )";
        }

        $clientMatch = comon_build_user_match_sql_parts($connect, $like, 'cu', []);
        if ($clientMatch !== []) {
            $parts[] = "EXISTS (
                SELECT 1 FROM users cu
                WHERE cu.id = COALESCE(NULLIF(m.c_id, 0), p.c_id)
                  AND (" . implode(' OR ', $clientMatch) . ")
            )";
        }

        if (comon_db_table_exists($connect, 'client_companies')) {
            $companyCols = comon_companies_searchable_columns($connect);
            if ($companyCols !== []) {
                $companyParts = [];
                foreach ($companyCols as $col) {
                    $companyParts[] = 'cc.' . $col . ' LIKE ' . $like;
                }
                $parts[] = "EXISTS (
                    SELECT 1 FROM client_companies cc
                    WHERE cc.id = m.company_id
                      AND cc.deleted_at IS NULL
                      AND (" . implode(' OR ', $companyParts) . ")
                )";
            }
        }

        if ($parts === []) {
            return '';
        }

        return '(' . implode(' OR ', $parts) . ')';
    }
}

if (!function_exists('comon_invoice_list_search_sql')) {
    /**
     * SQL AND (...) fragment for invoice list pages.
     */
    function comon_invoice_list_search_sql(mysqli $connect, string $searchQuery): string
    {
        $group = comon_invoice_list_search_or_group($connect, $searchQuery);
        return $group === '' ? '' : ' AND ' . $group;
    }
}
