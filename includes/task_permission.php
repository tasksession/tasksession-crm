<?php
// includes/task_permission.php

// if your lib‑initializer already loads database.php then you may not need this
require_once __DIR__ . '/lib-initialize.php';  

require_once('database-object.php');

/**
 * TaskPermission Class
 * 
 * Handles staff permissions for task operations
 */
class TaskPermission extends DatabaseObject {
    protected static $tblName = "task_permissions";
    protected static $tblFields = ['id', 'user_id', 'can_create_task', 'can_delete_task', 
                                 'can_change_status', 'can_update_task', 'can_assign_members', 'can_view_milestones'];
    
    public $id;
    public $user_id;
    public $can_create_task = 0;
    public $can_delete_task = 0;
    public $can_change_status = 0;
    public $can_update_task = 0;
    public $can_assign_members = 0;
    public $can_view_milestones = 0;
    
    /**
     * Find permission record by user ID
     */
    public static function findByUserId($user_id) {
        $result_array = self::findBySql("SELECT * FROM " . static::$tblName . 
                                       " WHERE user_id = " . (int)$user_id . 
                                       " LIMIT 1");
        return !empty($result_array) ? array_shift($result_array) : false;
    }
    
    /**
     * Get or create permission record for a user
     */
    public static function getOrCreate($user_id) {
        $permission = self::findByUserId($user_id);
        
        if (!$permission) {
            $permission = new self();
            $permission->user_id = $user_id;
            $permission->can_create_task = 0;
            $permission->can_delete_task = 0;
            $permission->can_change_status = 0;
            $permission->can_update_task = 0;
            $permission->can_assign_members = 0;
            $permission->can_view_milestones = 0;
        }
        
        return $permission;
    }
    
    /**
     * Check if a user has a specific permission
     */
    public static function hasPermission($user_id, $permission_name) {
        // Admin has all permissions
        global $session;
        if ($session->isAdmin()) {
            return true;
        }

        $permission = self::findByUserId($user_id);
        if (!$permission) {
            return false;
        }
        
        return $permission->$permission_name == 1;
    }

    /** Staff/client task-create flag from task_permissions.can_create_task */
    public static function canCreateTask($user_id) {
        global $session;
        if ($session && $session->isAdmin()) {
            return true;
        }
        $permission = self::findByUserId((int) $user_id);
        if (!$permission) {
            return false;
        }

        return isset($permission->can_create_task) && (int) $permission->can_create_task === 1;
    }
}
?> 