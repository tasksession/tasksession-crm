<?php
/**
 * REST v1 — tasks (Pro workspace — not in Free).
 */

function api_v1_tasks_search(array $input)
{
    api_json_error('Workspace tools are not available in Free edition.', 501, 'not_available');
}

function api_v1_tasks_overdue(array $input)
{
    api_json_error('Workspace tools are not available in Free edition.', 501, 'not_available');
}
