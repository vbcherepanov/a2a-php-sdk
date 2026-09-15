<?php

declare(strict_types=1);

namespace A2A\Transport;

use A2A\Client\CallOptions;
use A2A\Protocol\Operation;
use Google\Protobuf\Internal\Message;
use Lf\A2a\V1\StreamResponse;

interface Transport
{
    public function call(Operation $operation, Message $request, CallOptions $options): Message;
    /** @return iterable<StreamResponse> */
    public function stream(Operation $operation, Message $request, CallOptions $options): iterable;
}
