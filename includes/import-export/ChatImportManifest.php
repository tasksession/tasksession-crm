<?php
/**
 * Chat import ZIP package — manifest version and expected layout (v1).
 *
 * ZIP root:
 *   manifest.json          (required)
 *   messages_one_to_one.jsonl   (optional, UTF-8 JSON lines)
 *   messages_discussion.jsonl   (optional)
 *   groups.json              (optional — array of group objects)
 *   group_messages.jsonl     (optional)
 *   task_messages.jsonl      (optional)
 *   attachments/             (optional — files named like tokens in bodies)
 *
 * JSON line schemas (each line is one JSON object):
 *
 * messages_one_to_one.jsonl:
 *   external_id (string, required), sender_user_id, receiver_user_id,
 *   time (int unix or ISO8601 string), plaintext_body (string),
 *   reply_to_external_id (string|null), edited (0|1), edit_time (int|null)
 *
 * messages_discussion.jsonl:
 *   external_id, project_id, sender_user_id, time, plaintext_body,
 *   reply_to_external_id, edited, edit_time
 *
 * groups.json: JSON array of:
 *   external_id (string), name (string), created_by (int), created_at (int unix, optional),
 *   avatar (string|null), members: [ { user_id, role: "admin"|"member", joined_at (int, optional) } ]
 *
 * group_messages.jsonl:
 *   external_id, external_group_id (maps to groups.json external_id), sender_user_id,
 *   time, plaintext_body, reply_to_external_id, edited, edit_time
 *
 * task_messages.jsonl:
 *   external_id, task_id, sender_user_id, time, plaintext_body,
 *   reply_to_external_id, edited, edit_time
 */
class Comon_IE_ChatImportManifest
{
    public const VERSION = 1;

    public const FILE_MANIFEST = 'manifest.json';

    public const FILE_ONE_TO_ONE = 'messages_one_to_one.jsonl';

    public const FILE_DISCUSSION = 'messages_discussion.jsonl';

    public const FILE_GROUPS = 'groups.json';

    public const FILE_GROUP_MESSAGES = 'group_messages.jsonl';

    public const FILE_TASK_MESSAGES = 'task_messages.jsonl';

    public const DIR_ATTACHMENTS = 'attachments';

    /**
     * @param array<string,mixed> $manifest
     * @return array{ok:bool,error?:string}
     */
    public static function validate(array $manifest): array
    {
        $v = isset($manifest['version']) ? (int)$manifest['version'] : 0;
        if ($v !== self::VERSION) {
            return ['ok' => false, 'error' => 'manifest.version must be ' . self::VERSION];
        }
        return ['ok' => true];
    }

    /**
     * @return list<string> optional jsonl / json file names (relative to package root)
     */
    public static function optionalDataFiles(): array
    {
        return [
            self::FILE_ONE_TO_ONE,
            self::FILE_DISCUSSION,
            self::FILE_GROUPS,
            self::FILE_GROUP_MESSAGES,
            self::FILE_TASK_MESSAGES,
        ];
    }
}
