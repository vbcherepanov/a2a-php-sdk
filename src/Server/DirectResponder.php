<?php

declare(strict_types=1);

namespace A2A\Server;

use A2A\Security\CallContext;
use Lf\A2a\V1\{Message, SendMessageRequest};

interface DirectResponder
{
    public function respond(SendMessageRequest $request, CallContext $context): ?Message;
}
