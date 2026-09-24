<?php
/**
 * Project-scoped permission helpers (staff on team, client on project).
 */
require_once __DIR__ . '/projects.php';
require_once __DIR__ . '/permissions.php';

class ProjectPermission
{
    /**
     * Staff may manage project-scoped actions if on project team or has project_view_all.
     */
    public static function canManageProject($userId, $projectId): bool
    {
        global $connect;
        $projectId = (int) $projectId;
        $userId = (int) $userId;
        if ($projectId <= 0 || $userId <= 0) {
            return false;
        }
        $project = projects::findByProjectId($projectId);
        if (!$project) {
            return false;
        }
        if (function_exists('ensure_user_permissions') && isset($connect)) {
            ensure_user_permissions($connect);
        }
        if (function_exists('has_permission') && has_permission('project_view_all')) {
            return true;
        }
        $projectStaffIds = array_filter(array_map('trim', explode(',', (string) $project->s_ids)));

        return in_array((string) $userId, $projectStaffIds, true);
    }

    /**
     * Client is allowed for project if listed on project clients.
     */
    public static function isProjectClient($userId, $projectId): bool
    {
        $projectId = (int) $projectId;
        $userId = (int) $userId;
        if ($projectId <= 0 || $userId <= 0) {
            return false;
        }
        $project = projects::findByProjectId($projectId);
        if (!$project) {
            return false;
        }
        if (method_exists($project, 'isClient') && $project->isClient($userId)) {
            return true;
        }
        if (isset($project->c_id) && (int) $project->c_id === $userId) {
            return true;
        }

        return false;
    }
}
