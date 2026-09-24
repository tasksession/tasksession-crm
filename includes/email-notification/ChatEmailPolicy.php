<?php
/**
 * Resolves global + per-context email toggles; filters batch messages; single-message gating.
 */

require_once __DIR__ . '/ChatMentionUtil.php';
require_once __DIR__ . '/ChatEmailContextRepository.php';
require_once __DIR__ . '/ChatEmailUserPreferenceRepository.php';

class ChatEmailPolicy {

    /**
     * @return array{admin:bool,staff:bool,client:bool,mention:bool}
     */
    public static function getGlobalTogglesFromSettings($settings) {
        if (!$settings) {
            return self::defaultToggles();
        }
        return array(
            'admin'   => (int) self::g($settings, 'chat_email_notify_admin', 1) === 1,
            'staff'   => (int) self::g($settings, 'chat_email_notify_staff', 1) === 1,
            'client'  => (int) self::g($settings, 'chat_email_notify_client', 1) === 1,
            'mention' => (int) self::g($settings, 'chat_email_notify_mention', 1) === 1,
        );
    }

    private static function g($obj, $k, $def) {
        return (isset($obj->{$k}) && $obj->{$k} !== null && $obj->{$k} !== '') ? $obj->{$k} : $def;
    }

    public static function defaultToggles() {
        return array(
            'admin'   => true,
            'staff'   => true,
            'client'  => true,
            'mention' => true,
        );
    }

    /**
     * @param string $contextType  project|group|task|direct
     * @param int    $contextId
     * @return array{admin:bool,staff:bool,client:bool,mention:bool}
     */
    public static function getEffectiveToggles($contextType, $contextId = 0) {
        $s = self::getSettings();
        if (!$s) {
            return self::defaultToggles();
        }
        $t = self::getGlobalTogglesFromSettings($s);
        if ($contextType === 'direct' || (int) $contextId <= 0) {
            return $t;
        }
        if (!in_array($contextType, array('project', 'group', 'task'), true)) {
            return $t;
        }
        $row = ChatEmailContextRepository::getRow($contextType, (int) $contextId);
        if (!$row) {
            return $t;
        }
        if (array_key_exists('o_admin', $row) && $row['o_admin'] !== null) {
            $t['admin'] = (int) $row['o_admin'] === 1;
        }
        if (array_key_exists('o_staff', $row) && $row['o_staff'] !== null) {
            $t['staff'] = (int) $row['o_staff'] === 1;
        }
        if (array_key_exists('o_client', $row) && $row['o_client'] !== null) {
            $t['client'] = (int) $row['o_client'] === 1;
        }
        if (array_key_exists('o_mention', $row) && $row['o_mention'] !== null) {
            $t['mention'] = (int) $row['o_mention'] === 1;
        }
        return $t;
    }

    public static function getSettings() {
        if (!class_exists('settings', false) && !class_exists('Settings', false)) {
            if (file_exists(dirname(__DIR__) . '/settings.php')) {
                require_once dirname(__DIR__) . '/settings.php';
            }
        }
        if (class_exists('settings')) {
            return settings::findById(1);
        }
        return false;
    }

    /**
     * @return bool
     */
    public static function isNormalEmailAllowedForUser($user, array $toggles) {
        if (!$user || !isset($user->accountStatus)) {
            return false;
        }
        $st = (int) $user->accountStatus;
        if ($st === 1) {
            return !empty($toggles['admin']);
        }
        if ($st === 2) {
            return !empty($toggles['client']);
        }
        if ($st === 3) {
            return !empty($toggles['staff']);
        }
        return false;
    }

    /**
     * Per-user opt-out for this context (applies to normal role email only).
     *
     * @param string|null $contextType
     * @return bool
     */
    public static function isUserContextEmailSuppressed($userId, $contextType, $contextId) {
        if (empty($contextType) || !in_array($contextType, array('project', 'group', 'task'), true) || (int) $contextId <= 0) {
            return false;
        }
        return !ChatEmailUserPreferenceRepository::isUserEmailEnabled((int) $userId, (string) $contextType, (int) $contextId);
    }

    /**
     * @param string|null $contextType project|group|task|direct (optional for direct batch)
     */
    public static function filterMessagesForChatEmail($userId, array $messages, array $toggles, array $participantUserIds, $contextType = null, $contextId = 0) {
        if (empty($messages)) {
            return array();
        }
        $u = User::findById((int) $userId);
        if (!$u) {
            return array();
        }
        if (self::isNormalEmailAllowedForUser($u, $toggles)
            && !self::isUserContextEmailSuppressed($userId, $contextType, (int) $contextId)
        ) {
            return $messages;
        }
        if (empty($toggles['mention'])) {
            return array();
        }
        $filtered = array();
        foreach ($messages as $m) {
            $body = isset($m['message']) ? (string) $m['message'] : '';
            if (ChatMentionUtil::messageMentionsUser($body, (int) $userId, $participantUserIds)) {
                $filtered[] = $m;
            }
        }
        return $filtered;
    }

    /**
     * One new message: send email if role allows, OR (mention on and body @mentions recipient among participants).
     */
    public static function shouldSendEmailForSingleMessage($recipientId, $messageContent, $contextType, $contextId, array $participantUserIds) {
        if ((int) $recipientId <= 0) {
            return false;
        }
        $t = self::getEffectiveToggles($contextType, (int) $contextId);
        $u = User::findById((int) $recipientId);
        if (!$u) {
            return false;
        }
        if (self::isNormalEmailAllowedForUser($u, $t)
            && !self::isUserContextEmailSuppressed($recipientId, $contextType, (int) $contextId)
        ) {
            return true;
        }
        if (empty($t['mention'])) {
            return false;
        }
        if (empty($participantUserIds) || !in_array((int) $recipientId, array_map('intval', $participantUserIds), true)) {
            $participantUserIds[] = (int) $recipientId;
        }
        return ChatMentionUtil::messageMentionsUser($messageContent, (int) $recipientId, $participantUserIds);
    }
}
