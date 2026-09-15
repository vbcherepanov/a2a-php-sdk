<?php

declare(strict_types=1);

namespace A2A\Server;

use A2A\Security\CallContext;
use Lf\A2a\V1\{SendMessageRequest, StreamResponse, Task};

interface Executor
{
    /** @return iterable<StreamResponse> */
    public function execute(SendMessageRequest $request, Task $task, CallContext $context): iterable;
}
