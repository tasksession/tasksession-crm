<?php
/**
 * Mega search bulk delete — permissions + handlers.
 */

if (!function_exists('comon_mega_search_bulk_tab_permissions')) {
    /**
     * @return array<string,bool>
     */
    function comon_mega_search_bulk_tab_permissions(array $ctx): array
    {
        $role = (string) ($ctx['role'] ?? 'admin');
        if ($role === 'client') {
            return [];
        }

        $can = static function (string $perm): bool {
            return !function_exists('has_permission') || has_permission($perm);
        };

        $tabs = [];
        if (!empty($ctx['tabs']['people']['enabled'])) {
            $tabs['people'] = $can('client_delete') || $can('staff_profile_delete');
        }
        if (!empty($ctx['tabs']['companies']['enabled'])) {
            $tabs['companies'] = $can('client_delete');
        }
        if (!empty($ctx['tabs']['projects']['enabled'])) {
            $tabs['projects'] = $role === 'admin' || $can('project_delete');
        }
        if (!empty($ctx['tabs']['tasks']['enabled'])) {
            $tabs['tasks'] = $role === 'admin' || $can('task_delete');
        }
        if (!empty($ctx['tabs']['invoices']['enabled'])) {
            $tabs['invoices'] = $role === 'admin' || $can('milestone_delete');
        }

        return array_filter($tabs);
    }
}

if (!function_exists('comon_mega_search_bulk_delete')) {
    /**
     * @param list<int|string> $ids
     * @return array{deleted:int,skipped:int}
     */
    function comon_mega_search_bulk_delete(string $tab, array $ids, array $ctx): array
    {
        global $connect;

        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static function ($id) {
            return $id > 0;
        })));
        if ($ids === []) {
            return ['deleted' => 0, 'skipped' => 0];
        }

        $perms = comon_mega_search_bulk_tab_permissions($ctx);
        if (empty($perms[$tab])) {
            throw new RuntimeException('Forbidden');
        }

        $deleted = 0;
        $skipped = 0;

        switch ($tab) {
            case 'people':
                foreach ($ids as $uid) {
                    $user = user::findById($uid);
                    if (!$user || (int) ($user->status ?? 0) === 1) {
                        $skipped++;
                        continue;
                    }
                    $acct = (int) ($user->accountStatus ?? 0);
                    if ($acct === 2) {
                        if (function_exists('has_permission') && !has_permission('client_delete')) {
                            $skipped++;
                            continue;
                        }
                    } elseif ($acct === 3) {
                        if (function_exists('has_permission') && !has_permission('staff_profile_delete')) {
                            $skipped++;
                            continue;
                        }
                    } else {
                        $skipped++;
                        continue;
                    }
                    $user->status = 1;
                    if ($user->save()) {
                        if (class_exists('User') && method_exists('User', 'revokeRememberMeTokens')) {
                            User::revokeRememberMeTokens($uid);
                        }
                        $deleted++;
                    } else {
                        $skipped++;
                    }
                }
                break;

            case 'companies':
                if (!($connect instanceof mysqli) || !comon_db_table_exists($connect, 'client_companies')) {
                    throw new RuntimeException('Companies unavailable');
                }
                if (function_exists('has_permission') && !has_permission('client_delete')) {
                    throw new RuntimeException('Forbidden');
                }
                foreach ($ids as $cid) {
                    $stmt = mysqli_prepare($connect, 'UPDATE client_companies SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL LIMIT 1');
                    if (!$stmt) {
                        $skipped++;
                        continue;
                    }
                    mysqli_stmt_bind_param($stmt, 'i', $cid);
                    mysqli_stmt_execute($stmt);
                    $aff = mysqli_stmt_affected_rows($stmt);
                    mysqli_stmt_close($stmt);
                    if ($aff > 0) {
                        $deleted++;
                    } else {
                        $skipped++;
                    }
                }
                break;

            case 'projects':
                if (!($connect instanceof mysqli)) {
                    throw new RuntimeException('Database unavailable');
                }
                $role = (string) ($ctx['role'] ?? 'admin');
                if ($role !== 'admin' && function_exists('has_permission') && !has_permission('project_delete')) {
                    throw new RuntimeException('Forbidden');
                }
                foreach ($ids as $pid) {
                    $res = mysqli_query($connect, 'UPDATE projects SET trash = 1 WHERE p_id = ' . (int) $pid . ' AND trash != 1 LIMIT 1');
                    if ($res && mysqli_affected_rows($connect) > 0) {
                        $deleted++;
                    } else {
                        $skipped++;
                    }
                }
                break;

            case 'tasks':
                require_once __DIR__ . '/task.php';
                $role = (string) ($ctx['role'] ?? 'admin');
                if ($role !== 'admin' && function_exists('has_permission') && !has_permission('task_delete')) {
                    throw new RuntimeException('Forbidden');
                }
                foreach ($ids as $taskId) {
                    $task = Task::findById($taskId);
                    if ($task && $task->delete()) {
                        $deleted++;
                    } else {
                        $skipped++;
                    }
                }
                break;

            case 'invoices':
                $role = (string) ($ctx['role'] ?? 'admin');
                if ($role !== 'admin' && function_exists('has_permission') && !has_permission('milestone_delete')) {
                    throw new RuntimeException('Forbidden');
                }
                foreach ($ids as $invoiceId) {
                    $milestone = milestone::findById($invoiceId);
                    if ($milestone && (int) ($milestone->status ?? 0) !== 1 && $milestone->delete()) {
                        $deleted++;
                    } else {
                        $skipped++;
                    }
                }
                break;

            default:
                throw new RuntimeException('Invalid tab');
        }

        return ['deleted' => $deleted, 'skipped' => $skipped];
    }
}
