<?php
/**
 * Mention text parsing (aligned with discussion/group/task mention: first word of firstName).
 */

if (!function_exists('chatMention_getTokenForUser')) {
    function chatMention_getTokenForUser($user) {
        if (!$user || !isset($user->firstName)) {
            return 'User';
        }
        $firstName = trim((string) $user->firstName);
        if ($firstName === '') {
            return 'User';
        }
        $parts = preg_split('/\s+/', $firstName, 2);
        $displayName = !empty($parts[0]) ? trim($parts[0]) : 'User';
        return $displayName;
    }
}

class ChatMentionUtil {

    /**
     * @param int[] $userIds
     * @return array{lowerToken: string, ids: int[]}
     */
    public static function buildTokenToUserIdsMap(array $userIds) {
        $map = array();
        foreach (array_unique(array_map('intval', $userIds)) as $uid) {
            if ($uid <= 0) {
                continue;
            }
            $u = User::findById($uid);
            if (!$u) {
                continue;
            }
            $t = chatMention_getTokenForUser($u);
            $lt = function_exists('mb_strtolower') ? mb_strtolower($t, 'UTF-8') : strtolower($t);
            if (!isset($map[$lt])) {
                $map[$lt] = array();
            }
            if (!in_array($uid, $map[$lt], true)) {
                $map[$lt][] = $uid;
            }
        }
        return $map;
    }

    /**
     * @param string $rawMessage
     * @param array $tokenToUserIdsMap  lowercasedToken => int[]
     * @return int[]
     */
    public static function extractMentionedUserIds($rawMessage, array $tokenToUserIdsMap) {
        if ($rawMessage === '' || $rawMessage === null) {
            return array();
        }
        $out = array();

        if (preg_match_all('/data-mention="([^"]+)"/u', $rawMessage, $dm)) {
            foreach ($dm[1] as $tok) {
                $lt = function_exists('mb_strtolower') ? mb_strtolower(trim($tok), 'UTF-8') : strtolower(trim($tok));
                if (isset($tokenToUserIdsMap[$lt])) {
                    foreach ($tokenToUserIdsMap[$lt] as $id) {
                        $out[] = (int) $id;
                    }
                }
            }
        }

        $text = $rawMessage;
        if (strpos($rawMessage, '<') !== false) {
            $text = preg_replace('/<[^>]+>/', ' ', $rawMessage);
        }

        if (preg_match_all('/@([a-zA-Z0-9\-\.]+)(?=\s|$|[\.\,\!\?\:\;]|<|@)/u', $text, $m)) {
            foreach ($m[1] as $chunk) {
                $lt = function_exists('mb_strtolower') ? mb_strtolower($chunk, 'UTF-8') : strtolower($chunk);
                if (isset($tokenToUserIdsMap[$lt])) {
                    foreach ($tokenToUserIdsMap[$lt] as $id) {
                        $out[] = (int) $id;
                    }
                }
            }
        }

        return array_values(array_unique($out));
    }

    public static function messageMentionsUser($rawMessage, $targetUserId, array $allParticipantUserIds) {
        $map = self::buildTokenToUserIdsMap($allParticipantUserIds);
        $ids = self::extractMentionedUserIds($rawMessage, $map);
        return in_array((int) $targetUserId, $ids, true);
    }
}
