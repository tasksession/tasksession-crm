<?php
/**
 * REST v1 — clients / projects (Pro workspace — not in Free).
 */

function api_v1_clients_search(array $input)
{
    api_json_error('Workspace tools are not available in Free edition.', 501, 'not_available');
}

function api_v1_projects_search(array $input)
{
    api_json_error('Workspace tools are not available in Free edition.', 501, 'not_available');
}
