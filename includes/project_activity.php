<?php
/*
================================================================================
  Project activity log — read/write project_activities
================================================================================
 */

if (!function_exists('project_activities_table_exists')) {
    function project_activities_table_exists(mysqli $conn): bool
    {
        $r = mysqli_query($conn, "SHOW TABLES LIKE 'project_activities'");
        return $r && mysqli_num_rows($r) > 0;
    }
}

if (!function_exists('project_activity_log')) {
    /**
     * @param array<string,mixed> $metadata
     */
    function project_activity_log(
        mysqli $conn,
        int $projectId,
        ?int $actorUserId,
        string $action,
        string $entityType = 'project',
        array $metadata = []
    ): void {
        if ($projectId <= 0 || !project_activities_table_exists($conn)) {
            return;
        }

        $metaJson = $metadata !== [] ? json_encode($metadata, JSON_UNESCAPED_UNICODE) : null;
        if ($metaJson === false) {
            $metaJson = null;
        }

        $stmt = $conn->prepare(
            'INSERT INTO project_activities (project_id, actor_user_id, entity_type, action, metadata)
             VALUES (?, ?, ?, ?, ?)'
        );
        if (!$stmt) {
            return;
        }
        $actor = ($actorUserId !== null && $actorUserId > 0) ? $actorUserId : null;
        $stmt->bind_param('iisss', $projectId, $actor, $entityType, $action, $metaJson);
        $stmt->execute();
        $stmt->close();
    }
}

if (!function_exists('project_activity_fetch_recent')) {
    /**
     * @return array<int, array<string,mixed>>
     */
    function project_activity_fetch_recent(mysqli $conn, int $projectId, int $limit = 10): array
    {
        if ($projectId <= 0 || !project_activities_table_exists($conn)) {
            return [];
        }
        $limit = max(1, min(50, $limit));

        $stmt = $conn->prepare(
            'SELECT pa.*, u.firstName,
                    UNIX_TIMESTAMP(pa.created_at) AS created_ts
             FROM project_activities pa
             LEFT JOIN users u ON u.id = pa.actor_user_id
             WHERE pa.project_id = ?
             ORDER BY pa.created_at DESC, pa.id DESC
             LIMIT ' . (int) $limit
        );
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('i', $projectId);
        if (!$stmt->execute()) {
            $stmt->close();
            return [];
        }
        $res = $stmt->get_result();
        $rows = [];
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $rows[] = $row;
            }
        }
        $stmt->close();

        return $rows;
    }
}

if (!function_exists('project_activity_relative_time')) {
    function project_activity_relative_time(int $ts): string
    {
        if ($ts <= 0) {
            return '';
        }
        $diff = time() - $ts;
        if ($diff < 60) {
            return 'Just now';
        }
        if ($diff < 3600) {
            $m = (int) floor($diff / 60);
            return $m . ' minute' . ($m === 1 ? '' : 's') . ' ago';
        }
        if ($diff < 86400) {
            $h = (int) floor($diff / 3600);
            return $h . ' hour' . ($h === 1 ? '' : 's') . ' ago';
        }
        if ($diff < 604800) {
            $d = (int) floor($diff / 86400);
            return $d . ' day' . ($d === 1 ? '' : 's') . ' ago';
        }

        return date('M j, Y', $ts);
    }
}

if (!function_exists('project_activity_icon_svg')) {
    function project_activity_icon_svg(string $icon): string
    {
        $map = [
            'edit' => 'edit',
            'plus' => 'plus',
            'calendar' => 'calendar',
            'user-plus' => 'user-plus',
            'user-minus' => 'user-minus',
            'tag' => 'tag',
        ];
        $name = $map[$icon] ?? 'edit';
        return function_exists('ts_icon') ? ts_icon($name) : '';
    }
}

if (!function_exists('project_activity_format_message')) {
    /**
     * @param array<string,mixed> $row
     * @param array<string,string> $lang
     * @return array{title:string, body:string, icon:string, relative_time:string, actor_name:string}
     */
    function project_activity_format_message(array $row, array $lang = []): array
    {
        $action = (string) ($row['action'] ?? '');
        $meta = [];
        if (!empty($row['metadata'])) {
            $dec = json_decode((string) $row['metadata'], true);
            if (is_array($dec)) {
                $meta = $dec;
            }
        }

        $first = trim((string) ($row['firstName'] ?? ''));
        $last = trim((string) ($row['lastName'] ?? ''));
        $actorName = trim($first . ($last !== '' ? ' ' . $last : ''));
        if ($actorName === '') {
            $actorName = $lang['Someone'] ?? 'Someone';
        }

        $title = $lang['Project updated'] ?? 'Project updated';
        $body = '';
        $icon = 'edit';

        switch ($action) {
            case 'project_created':
                $title = $lang['Project created'] ?? 'Project created';
                $body = sprintf(
                    $lang['Project activity created body'] ?? '%s created this project.',
                    htmlspecialchars($actorName, ENT_QUOTES, 'UTF-8')
                );
                $icon = 'plus';
                break;
            case 'description_updated':
                $title = $lang['Project description updated'] ?? 'Project description updated';
                $body = sprintf(
                    $lang['Project activity description body'] ?? '%s modified the project description.',
                    htmlspecialchars($actorName, ENT_QUOTES, 'UTF-8')
                );
                $icon = 'edit';
                break;
            case 'dates_updated':
                $title = $lang['Project dates updated'] ?? 'Project dates updated';
                $body = sprintf(
                    $lang['Project activity dates body'] ?? '%s updated project start or due dates.',
                    $actorName
                );
                $icon = 'calendar';
                break;
            case 'team_added':
                $title = $lang['Team member added'] ?? 'Team member added';
                $name = (string) ($meta['member_name'] ?? ($meta['member_id'] ?? ''));
                $body = $name !== ''
                    ? sprintf($lang['Project activity team added body'] ?? '%s was assigned to the project.', htmlspecialchars($name, ENT_QUOTES, 'UTF-8'))
                    : sprintf($lang['Project activity team added generic'] ?? '%s updated the project team.', $actorName);
                $icon = 'user-plus';
                break;
            case 'team_removed':
                $title = $lang['Team member removed'] ?? 'Team member removed';
                $body = sprintf($lang['Project activity team removed body'] ?? '%s updated the project team.', $actorName);
                $icon = 'user-minus';
                break;
            case 'custom_field_updated':
                $title = $lang['Custom field updated'] ?? 'Custom field updated';
                $label = (string) ($meta['field_label'] ?? 'Custom field');
                $value = (string) ($meta['new_value'] ?? '');
                $body = $value !== ''
                    ? sprintf(
                        $lang['Project activity cf body'] ?? '%s field was updated with new value \'%s\'.',
                        htmlspecialchars($label, ENT_QUOTES, 'UTF-8'),
                        htmlspecialchars($value, ENT_QUOTES, 'UTF-8')
                    )
                    : sprintf(
                        $lang['Project activity cf cleared'] ?? '%s field was updated.',
                        htmlspecialchars($label, ENT_QUOTES, 'UTF-8')
                    );
                $icon = 'tag';
                break;
            case 'project_updated':
                $title = $lang['Project updated'] ?? 'Project updated';
                $field = (string) ($meta['field'] ?? '');
                if ($field === 'project_title') {
                    $title = $lang['Project title updated'] ?? 'Project title updated';
                } elseif ($field === 'budget') {
                    $title = $lang['Project budget updated'] ?? 'Project budget updated';
                } elseif ($field === 'status') {
                    $title = $lang['Project status updated'] ?? 'Project status updated';
                }
                $body = sprintf($lang['Project activity generic body'] ?? '%s updated the project.', $actorName);
                $icon = 'edit';
                break;
            default:
                $body = sprintf($lang['Project activity generic body'] ?? '%s updated the project.', $actorName);
                break;
        }

        $ts = (int) ($row['created_ts'] ?? 0);

        return [
            'title' => $title,
            'body' => $body,
            'icon' => $icon,
            'relative_time' => project_activity_relative_time($ts),
            'actor_name' => $actorName,
        ];
    }
}

if (!function_exists('project_activity_normalize_cf_value')) {
    function project_activity_normalize_cf_value(string $fieldType, $raw): string
    {
        if (!function_exists('company_cf_format_value_for_view')) {
            require_once __DIR__ . '/custom-fields/company_custom_field_values.php';
        }
        return company_cf_format_value_for_view($fieldType, $raw, null);
    }
}

if (!function_exists('project_activity_log_save_changes')) {
    /**
     * Compare old project + custom fields vs new POST data and write activity rows.
     *
     * @param object $oldProject projects row object
     * @param array<string,mixed> $newData
     * @param array<int, array{id:int, field_type:string, label:string, field_value:?string}> $oldCfMap
     * @param array<int|string,mixed>|null $postedCf
     */
    function project_activity_log_save_changes(
        mysqli $conn,
        int $projectId,
        int $actorUserId,
        $oldProject,
        array $newData,
        array $oldCfMap,
        ?array $postedCf
    ): void {
        if ($projectId <= 0 || !$oldProject) {
            return;
        }

        require_once __DIR__ . '/custom-fields/project_custom_field_values.php';

        $oldDesc = (string) ($oldProject->project_desc ?? '');
        $newDesc = (string) ($newData['project_desc'] ?? '');
        if (trim(strip_tags($oldDesc)) !== trim(strip_tags($newDesc))) {
            project_activity_log($conn, $projectId, $actorUserId, 'description_updated', 'project');
        }

        $oldStart = substr((string) ($oldProject->start_time ?? ''), 0, 10);
        $oldEnd = substr((string) ($oldProject->end_time ?? ''), 0, 10);
        $newStart = substr((string) ($newData['start_time'] ?? ''), 0, 10);
        $newEnd = substr((string) ($newData['end_time'] ?? ''), 0, 10);
        if ($oldStart !== $newStart || $oldEnd !== $newEnd) {
            project_activity_log($conn, $projectId, $actorUserId, 'dates_updated', 'project', [
                'start' => $newStart,
                'end' => $newEnd,
            ]);
        }

        if ((string) ($oldProject->project_title ?? '') !== (string) ($newData['project_title'] ?? '')) {
            project_activity_log($conn, $projectId, $actorUserId, 'project_updated', 'project', [
                'field' => 'project_title',
            ]);
        }

        if ((string) ($oldProject->budget ?? '') !== (string) ($newData['budget'] ?? '')) {
            project_activity_log($conn, $projectId, $actorUserId, 'project_updated', 'project', [
                'field' => 'budget',
            ]);
        }

        if ((int) ($oldProject->status ?? 0) !== (int) ($newData['status'] ?? 0)) {
            project_activity_log($conn, $projectId, $actorUserId, 'project_updated', 'project', [
                'field' => 'status',
            ]);
        }

        $oldStaff = array_filter(array_map('intval', explode(',', (string) ($oldProject->s_ids ?? ''))));
        $newStaff = array_filter(array_map('intval', explode(',', (string) ($newData['s_ids'] ?? ''))));
        sort($oldStaff);
        sort($newStaff);
        $added = array_values(array_diff($newStaff, $oldStaff));
        $removed = array_values(array_diff($oldStaff, $newStaff));
        foreach ($added as $uid) {
            $memberName = '';
            if ($uid > 0 && class_exists('User')) {
                $u = User::findById($uid);
                if ($u) {
                    $memberName = trim((string) $u->firstName . ' ' . (string) ($u->lastName ?? ''));
                }
            }
            project_activity_log($conn, $projectId, $actorUserId, 'team_added', 'project', [
                'member_id' => $uid,
                'member_name' => $memberName,
            ]);
        }
        if ($removed !== [] && $added === []) {
            project_activity_log($conn, $projectId, $actorUserId, 'team_removed', 'project', [
                'removed_ids' => $removed,
            ]);
        }

        if ($postedCf === null || !is_array($postedCf)) {
            return;
        }

        $defsById = [];
        $dq = mysqli_query(
            $conn,
            "SELECT id, field_type, label FROM custom_fields
             WHERE entity_type = 'project' AND COALESCE(is_disabled, 0) = 0"
        );
        if ($dq) {
            while ($row = mysqli_fetch_assoc($dq)) {
                $defsById[(int) $row['id']] = $row;
            }
            mysqli_free_result($dq);
        }

        foreach ($defsById as $fid => $def) {
            $ft = (string) ($def['field_type'] ?? 'text');
            $label = trim((string) ($def['label'] ?? ''));
            if ($label === '') {
                $label = 'Field #' . $fid;
            }

            $oldRaw = isset($oldCfMap[$fid]) ? $oldCfMap[$fid]['field_value'] : null;
            $oldNorm = project_activity_normalize_cf_value($ft, $oldRaw);

            $newRaw = array_key_exists($fid, $postedCf) ? $postedCf[$fid] : (array_key_exists((string) $fid, $postedCf) ? $postedCf[(string) $fid] : null);
            if ($ft === 'checkbox' && $newRaw === null) {
                $newRaw = '0';
            }
            if ($ft === 'multiple_select' && is_array($newRaw)) {
                $filtered = array_values(array_filter(array_map('strval', $newRaw), static function ($v) {
                    return $v !== '';
                }));
                $newRaw = $filtered === [] ? null : json_encode($filtered);
            }
            $newNorm = project_activity_normalize_cf_value($ft, $newRaw);

            if ($oldNorm !== $newNorm) {
                project_activity_log($conn, $projectId, $actorUserId, 'custom_field_updated', 'custom_field', [
                    'field_id' => $fid,
                    'field_label' => $label,
                    'new_value' => $newNorm,
                ]);
            }
        }
    }
}
