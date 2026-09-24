<?php
/**
 * Participants used for @mention resolution (must be in this set to receive mention emails).
 */

class ChatEmailContextMaps {

    /**
     * @param string $type project|group|task|direct
     * @param int $id context id (0 for direct)
     * @param array $extra  direct: [peer_id] => int (other user in 1:1)
     * @return int[]
     */
    public static function getParticipantUserIds($type, $id, array $extra = array()) {
        if ($type === 'direct') {
            $a = isset($extra['user_id']) ? (int) $extra['user_id'] : 0;
            $b = isset($extra['peer_id']) ? (int) $extra['peer_id'] : 0;
            if ($a > 0 && $b > 0 && $a !== $b) {
                return array_values(array_unique(array($a, $b)));
            }
            return array();
        }
        if ($type === 'project') {
            return self::projectParticipantUserIds((int) $id);
        }
        if ($type === 'group') {
            return self::groupMemberUserIds((int) $id);
        }
        if ($type === 'task') {
            return self::taskParticipantUserIds((int) $id);
        }
        return array();
    }

    /**
     * @return int[]
     */
    public static function projectParticipantUserIds($projectId) {
        if ($projectId <= 0) {
            return array();
        }
        if (!class_exists('Projects', false)) {
            require_once dirname(__DIR__) . '/projects.php';
        }
        global $db1;
        $user_ids = array();
        $project = Projects::findByProjectId($projectId);
        if (!$project) {
            return array();
        }
        if (!empty($project->s_ids)) {
            foreach (array_map('trim', explode(',', (string) $project->s_ids)) as $sid) {
                if ($sid !== '' && is_numeric($sid)) {
                    $user_ids[] = (int) $sid;
                }
            }
        }
        if (!empty($project->c_ids)) {
            foreach (array_map('trim', explode(',', (string) $project->c_ids)) as $cid) {
                if ($cid !== '' && is_numeric($cid)) {
                    $user_ids[] = (int) $cid;
                }
            }
        }
        if (!empty($project->main_client_id)) {
            $user_ids[] = (int) $project->main_client_id;
        }
        if (!empty($project->c_id) && (int) $project->c_id > 0) {
            $user_ids[] = (int) $project->c_id;
        }
        if (isset($db1) && is_object($db1)) {
            $prior = $db1->query("SELECT DISTINCT user_id FROM messages WHERE Project_id = " . (int) $projectId . " AND receiver = 0");
            if ($prior) {
                while ($row = $db1->fetch_row($prior)) {
                    if (!empty($row['user_id'])) {
                        $user_ids[] = (int) $row['user_id'];
                    }
                }
            }
        } elseif (isset($GLOBALS['database']) && is_object($GLOBALS['database'])) {
            $d = $GLOBALS['database'];
            $prior = $d->query("SELECT DISTINCT user_id FROM messages WHERE Project_id = " . (int) $projectId . " AND receiver = 0");
            if ($prior) {
                while ($row = $d->fetchArray($prior)) {
                    if (!empty($row['user_id'])) {
                        $user_ids[] = (int) $row['user_id'];
                    }
                }
            }
        }
        $user_ids = array_values(array_unique(array_filter($user_ids)));
        return $user_ids;
    }

    /**
     * @return int[]
     */
    public static function groupMemberUserIds($groupId) {
        if ($groupId <= 0) {
            return array();
        }
        global $database;
        $g = (int) $groupId;
        $q = $database->query("SELECT user_id FROM group_chat_members WHERE group_id = {$g} AND left_at IS NULL");
        $ids = array();
        if ($q) {
            while ($r = $database->fetchArray($q)) {
                if (!empty($r['user_id'])) {
                    $ids[] = (int) $r['user_id'];
                }
            }
        }
        return array_values(array_unique($ids));
    }

    /**
     * @return int[]
     */
    public static function taskParticipantUserIds($taskId) {
        if ($taskId <= 0) {
            return array();
        }
        if (!class_exists('Task', false)) {
            require_once dirname(__DIR__) . '/task.php';
        }
        if (!class_exists('Projects', false)) {
            require_once dirname(__DIR__) . '/projects.php';
        }
        $t = Task::findById((int) $taskId);
        if (!$t) {
            return array();
        }
        $user_ids = array();
        if (!empty($t->creator_id)) {
            $user_ids[] = (int) $t->creator_id;
        }
        if (!empty($t->user_id)) {
            $user_ids[] = (int) $t->user_id;
        }
        if (!empty($t->assigned_to)) {
            $a = $t->assigned_to;
            if (is_string($a) && (strpos($a, ',') !== false || preg_match('/^\d+(\s+\d+)*$/', $a))) {
                foreach (preg_split('/[,\s]+/', str_replace(' ', ',', (string) $a)) as $x) {
                    if ($x !== '' && is_numeric($x)) {
                        $user_ids[] = (int) $x;
                    }
                }
            } else {
                foreach (preg_split('/[,\s]+/', (string) $a) as $x) {
                    if (trim($x) !== '' && is_numeric($x)) {
                        $user_ids[] = (int) trim($x);
                    }
                }
            }
        }
        if (!empty($t->project_id) && (int) $t->project_id > 0) {
            $user_ids = array_merge($user_ids, self::projectParticipantUserIds((int) $t->project_id));
        }
        $user_ids = array_values(array_unique(array_filter($user_ids)));
        return $user_ids;
    }
}
