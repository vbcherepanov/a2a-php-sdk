<?php

declare(strict_types=1);

namespace A2A\Transport\Grpc;

use A2A\Client\CallOptions;
use A2A\Protocol\{ErrorCode, Operation, ProtocolException, Validator};
use A2A\Transport\Transport;
use Google\Protobuf\Internal\Message;
use Google\Rpc\Status;
use Lf\A2a\V1\StreamResponse;

final readonly class GrpcTransport implements Transport
{
    public function __construct(private NativeClient $client)
    {
    }
    public function call(Operation $operation, Message $request, CallOptions $options): Message
    {
        if ($operation->streaming()) {
            throw new \InvalidArgumentException('Use stream for this operation');
        }
        $this->validate($request, $options);
        $call = $this->client->unary($operation, $request, $this->metadata($options), ['timeout' => (int) ($options->timeoutSeconds * 1000000)]);
        try {
            [$message, $status] = $call->wait();
            $this->status($status, $call->getTrailingMetadata());
            if (!$message instanceof Message || strlen($message->serializeToString()) > $options->maxMessageBytes) {
                throw new ProtocolException(ErrorCode::InvalidAgentResponse, 'Invalid gRPC response');
            }
            (new Validator())->validate($message);
            return $message;
        } finally {
            $call->cancel();
        }
    }
    public function stream(Operation $operation, Message $request, CallOptions $options): iterable
    {
        if (!$operation->streaming()) {
            throw new \InvalidArgumentException('Operation does not stream');
        }
        $this->validate($request, $options);
        $call = $this->client->streaming($operation, $request, $this->metadata($options), ['timeout' => (int) ($options->timeoutSeconds * 1000000)]);
        try {
            foreach ($call->responses() as $message) {
                if (!$message instanceof StreamResponse || strlen($message->serializeToString()) > $options->maxMessageBytes) {
                    throw new ProtocolException(ErrorCode::InvalidAgentResponse, 'Invalid gRPC stream response');
                }
                (new Validator())->validate($message);
                yield $message;
            }
            $this->status($call->getStatus(), $call->getTrailingMetadata());
        } finally {
            $call->cancel();
        }
    }
    private function validate(Message $request, CallOptions $options): void
    {
        (new Validator())->validate($request);
        if (strlen($request->serializeToString()) > $options->maxMessageBytes) {
            throw new ProtocolException(ErrorCode::ResourceExhausted, 'Request exceeds limit');
        }
    }
    /** @return array<string, list<string>> */
    private function metadata(CallOptions $options): array
    {
        $metadata = [];
        foreach ($options->httpHeaders() as $name => $value) {
            $metadata[strtolower($name)] = [$value];
        }
        return $metadata;
    }
    /** @param array<string, list<string>> $metadata */
    private function status(\stdClass $status, array $metadata): void
    {
        if (($status->code ?? null) === 0) {
            return;
        }
        $details = $metadata['grpc-status-details-bin'][0] ?? null;
        if (is_string($details)) {
            $decoded = new Status();
            $decoded->mergeFromString($details);
            $reason = null;
            foreach ($decoded->getDetails() as $detail) {
                if ($detail->getTypeUrl() === 'type.googleapis.com/google.rpc.ErrorInfo') {
                    $info = new \Google\Rpc\ErrorInfo();
                    $info->mergeFromString($detail->getValue());
                    $reason = $info->getReason();
                }
            }
            foreach (ErrorCode::cases() as $candidate) {
                if ($candidate->reason() === $reason) {
                    throw new ProtocolException($candidate, $decoded->getMessage());
                }
            }
        }
        $error = match ($status->code ?? null) {
            3 => ErrorCode::InvalidParams,
            4 => ErrorCode::DeadlineExceeded,
            5 => ErrorCode::TaskNotFound,
            7 => ErrorCode::PermissionDenied,
            8 => ErrorCode::ResourceExhausted,
            9 => ErrorCode::UnsupportedOperation,
            12 => ErrorCode::MethodNotFound,
            16 => ErrorCode::Unauthenticated,
            default => ErrorCode::InternalError,
        };
        throw new ProtocolException($error, is_string($status->details ?? null) ? $status->details : 'gRPC call failed');
    }
}
