<?php
/**
 * Single entry for batch crons: apply policy + @mention filter to a message list.
 */
require_once __DIR__ . '/ChatEmailContextMaps.php';
require_once __DIR__ . '/ChatEmailPolicy.php';

class ChatEmailBatchFilter {

    /**
     * @param string $type project|group|task|direct
     * @param int $contextId
     * @param int $userId recipient
     * @param array $messages
     * @param array $extra  direct: [user_id, peer_id]
     * @return array filtered messages
     */
    public static function apply($type, $contextId, $userId, array $messages, array $extra = array()) {
        $pids = ChatEmailContextMaps::getParticipantUserIds($type, (int) $contextId, $extra);
        if ($type === 'direct') {
            if (empty($pids) && !empty($extra['user_id']) && !empty($extra['peer_id'])) {
                $pids = array((int) $extra['user_id'], (int) $extra['peer_id']);
            }
        }
        $toggles = ChatEmailPolicy::getEffectiveToggles($type, (int) $contextId);
        return ChatEmailPolicy::filterMessagesForChatEmail((int) $userId, $messages, $toggles, $pids, $type, (int) $contextId);
    }
}
