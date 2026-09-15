<?php

declare(strict_types=1);

namespace A2A\Transport\Grpc;

use A2A\Protocol\{ErrorCode, Json, Operation, ProtocolException};
use A2A\Server\{Gateway, ServerOptions};
use Google\Protobuf\Internal\Message;

final readonly class GrpcEndpoint
{
    public function __construct(private Gateway $gateway, private ServerOptions $options)
    {
    }
    /** @param array<string, string> $headers
     * @return iterable<string>
     */
    public function handle(string $path, string $body, array $headers): iterable
    {
        $prefix = '/lf.a2a.v1.A2AService/';
        if (!str_starts_with($path, $prefix)) {
            throw new ProtocolException(ErrorCode::MethodNotFound, 'Unknown gRPC service');
        }
        $headers = array_change_key_case($headers);
        if (!in_array(explode(';', $headers['content-type'] ?? '')[0], ['application/grpc', 'application/grpc+proto'], true)) {
            throw new ProtocolException(ErrorCode::ContentTypeNotSupported, 'Expected protobuf gRPC content type');
        }
        $operation = Operation::tryFrom(substr($path, strlen($prefix))) ?? throw new ProtocolException(ErrorCode::MethodNotFound, 'Unknown gRPC method');
        $class = $operation->requestClass();
        $request = new $class();
        try {
            $request->mergeFromString(Frame::decode($body, $this->options->maxMessageBytes));
        } catch (ProtocolException $error) {
            throw $error;
        } catch (\Throwable $error) {
            throw new ProtocolException(ErrorCode::InvalidParams, 'Invalid protobuf request', $error);
        }
        $data = Json::data($request);
        $tenant = is_string($data->tenant ?? null) ? $data->tenant : '';
        $timeout = $this->options->requestTimeoutSeconds;
        if (isset($headers['grpc-timeout'])) {
            if (!preg_match('/^(\d{1,8})([HMSmun])$/D', $headers['grpc-timeout'], $match)) {
                throw ProtocolException::invalid('Invalid gRPC timeout');
            }
            $multiplier = match ($match[2]) {
                'H' => 3600, 'M' => 60, 'S' => 1, 'm' => 0.001, 'u' => 0.000001,
                default => 0.000000001,
            };
            $timeout = min($timeout, (int) $match[1] * $multiplier);
        }
        $result = $this->gateway->invoke($operation, $request, $headers, $tenant, microtime(true) + $timeout);
        if ($result instanceof Message) {
            yield Frame::encode($result->serializeToString(), $this->options->maxMessageBytes);
        } else {
            foreach ($result as $event) {
                yield Frame::encode($event->serializeToString(), $this->options->maxMessageBytes);
            }
        }
    }
}
