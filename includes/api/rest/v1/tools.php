<?php
/**
 * REST v1 — tools (Pro workspace AI — not shipped in Free).
 */

function api_v1_tools_list()
{
    api_json_error('Workspace tools are not available in Free edition.', 501, 'not_available');
}

function api_v1_tools_invoke(array $input)
{
    api_json_error('Workspace tools are not available in Free edition.', 501, 'not_available');
}
