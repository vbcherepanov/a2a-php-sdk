<?php

declare(strict_types=1);

namespace A2A\Transport\Grpc;

use A2A\Protocol\Operation;
use Google\Protobuf\Internal\Message;
use Grpc\{BaseStub, ServerStreamingCall, UnaryCall};

final class NativeClient extends BaseStub
{
    /** @param array<string, list<string>> $metadata
     * @param array<string, int> $options
     * @return UnaryCall<Message>
     */
    public function unary(Operation $operation, Message $request, array $metadata, array $options): UnaryCall
    {
        return $this->_simpleRequest('/lf.a2a.v1.A2AService/'.$operation->value, $request, [$operation->responseClass(), 'decode'], $metadata, $options); // @phpstan-ignore argument.type (Upstream decodes a class tuple, despite its callable annotation.)
    }
    /** @param array<string, list<string>> $metadata
     * @param array<string, int> $options
     */
    public function streaming(Operation $operation, Message $request, array $metadata, array $options): ServerStreamingCall
    {
        return $this->_serverStreamRequest('/lf.a2a.v1.A2AService/'.$operation->value, $request, [$operation->responseClass(), 'decode'], $metadata, $options); // @phpstan-ignore argument.type (Upstream decodes a class tuple, despite its callable annotation.)
    }
}
