<?php

namespace Stripe;

/**
 * SetupIntent (saved cards / off-session).
 */
class SetupIntent extends ApiResource
{
    const OBJECT_NAME = 'setup_intent';

    use ApiOperations\Create;
    use ApiOperations\Retrieve;
    use ApiOperations\Update;
}
