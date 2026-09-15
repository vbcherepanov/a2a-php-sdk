<?php

declare(strict_types=1);

namespace A2A\Transport;

use A2A\Client\CallOptions;
use A2A\Protocol\{ErrorCode, ErrorMapper, Json, Operation, ProtocolException, Validator};
use Google\Protobuf\Internal\Message;
use Lf\A2a\V1\StreamResponse;
use Symfony\Contracts\HttpClient\{HttpClientInterface, ResponseInterface};

final readonly class HttpTransport implements Transport
{
    public function __construct(private HttpClientInterface $http, private string $endpoint, private bool $jsonRpc = true)
    {
        if (!filter_var($endpoint, FILTER_VALIDATE_URL) || !in_array(parse_url($endpoint, PHP_URL_SCHEME), ['https', 'http'], true)) {
            throw new \InvalidArgumentException('An absolute HTTP(S) endpoint is required');
        }
    }
    public function call(Operation $operation, Message $request, CallOptions $options): Message
    {
        if ($operation->streaming()) {
            throw new \InvalidArgumentException('Use stream() for streaming operations');
        }
        [$response, $id] = $this->request($operation, $request, $options);
        try {
            $response->getStatusCode();
            $body = '';
            foreach ($this->http->stream($response) as $chunk) {
                if ($chunk->isTimeout()) {
                    throw new ProtocolException(ErrorCode::DeadlineExceeded, 'HTTP response timed out');
                }
                $body .= $chunk->getContent();
                if (strlen($body) > $options->maxMessageBytes) {
                    throw new ProtocolException(ErrorCode::ResourceExhausted, 'Response exceeds message limit');
                }
            }
            $payload = $this->unwrap(Json::object($body), $id);
            if ($response->getStatusCode() >= 400) {
                throw new ProtocolException(ErrorCode::InternalError, 'HTTP request failed: '.$response->getStatusCode());
            }
            $message = Json::message(Json::encode($payload), $operation->responseClass());
            (new Validator())->validate($message);
            return $message;
        } finally {
            $response->cancel();
        }
    }
    public function stream(Operation $operation, Message $request, CallOptions $options): iterable
    {
        if (!$operation->streaming()) {
            throw new \InvalidArgumentException('Operation does not stream');
        }
        [$response, $id] = $this->request($operation, $request, $options);
        try {
            $headers = $response->getHeaders(false);
            if ($response->getStatusCode() !== 200 || !str_starts_with($headers['content-type'][0] ?? '', 'text/event-stream')) {
                $body = '';
                foreach ($this->http->stream($response) as $chunk) {
                    if ($chunk->isTimeout()) {
                        throw new ProtocolException(ErrorCode::DeadlineExceeded, 'HTTP response timed out');
                    }
                    $body .= $chunk->getContent();
                    if (strlen($body) > $options->maxMessageBytes) {
                        throw new ProtocolException(ErrorCode::ResourceExhausted, 'Response exceeds message limit');
                    }
                }
                $this->unwrap(Json::object($body), $id);
                throw new ProtocolException(ErrorCode::InvalidAgentResponse, 'Expected an SSE response');
            }
            $decoder = new SseDecoder($options->maxMessageBytes);
            foreach ($this->http->stream($response) as $chunk) {
                if ($chunk->isTimeout()) {
                    throw new ProtocolException(ErrorCode::DeadlineExceeded, 'SSE response timed out');
                }
                foreach ($decoder->feed($chunk->getContent()) as $data) {
                    $payload = $this->unwrap(Json::object($data), $id);
                    $message = Json::message(Json::encode($payload), StreamResponse::class);
                    (new Validator())->validate($message);
                    yield $message;
                }
            }
            $decoder->finish();
        } finally {
            $response->cancel();
        }
    }
    /** @return array{ResponseInterface, string} */
    private function request(Operation $operation, Message $request, CallOptions $options): array
    {
        (new Validator())->validate($request);
        $id = bin2hex(random_bytes(16));
        $data = Json::data($request);
        $path = '';
        $query = '';
        $method = 'POST';
        $body = Json::encode((object) ['jsonrpc' => '2.0', 'id' => $id, 'method' => $operation->value, 'params' => $data]);
        if (!$this->jsonRpc) {
            $method = $operation->httpMethod();
            $path = $operation->path();
            foreach (['id', 'taskId'] as $key) {
                if (str_contains($path, '{'.$key.'}')) {
                    if (!is_string($data->{$key} ?? null) || $data->{$key} === '') {
                        throw ProtocolException::invalid('Missing path parameter '.$key);
                    }
                    $path = str_replace('{'.$key.'}', rawurlencode($data->{$key}), $path);
                    unset($data->{$key});
                }
            }
            if (isset($data->tenant) && is_string($data->tenant) && $data->tenant !== '') {
                $path = '/'.rawurlencode($data->tenant).$path;
            }
            unset($data->tenant);
            if (in_array($method, ['GET', 'DELETE'], true)) {
                $params = [];
                foreach (get_object_vars($data) as $key => $value) {
                    if (!is_scalar($value)) {
                        throw ProtocolException::invalid('Unsupported query parameter');
                    }
                    $params[$key] = is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
                }
                $encoded = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
                $query = $encoded === '' ? '' : '?'.$encoded;
                $body = '';
            } else {
                $body = Json::encode($data);
            }
        }
        if (strlen($body) > $options->maxMessageBytes) {
            throw new ProtocolException(ErrorCode::ResourceExhausted, 'Request exceeds message limit');
        }
        return [$this->http->request($method, rtrim($this->endpoint, '/').$path.$query, [
            'headers' => array_merge($options->httpHeaders(), ['Content-Type' => 'application/json', 'Accept' => $operation->streaming() ? 'text/event-stream' : 'application/json']),
            'body' => $body,
            'max_duration' => $options->timeoutSeconds,
            'timeout' => $options->timeoutSeconds,
            'max_redirects' => 0,
        ]), $id];
    }
    private function unwrap(\stdClass $payload, string $id): \stdClass
    {
        if ($this->jsonRpc && (($payload->jsonrpc ?? null) !== '2.0' || ($payload->id ?? null) !== $id)) {
            throw new ProtocolException(ErrorCode::InvalidAgentResponse, 'JSON-RPC response ID or version mismatch');
        }
        if (isset($payload->error) && $payload->error instanceof \stdClass) {
            throw ErrorMapper::fromWire($payload->error);
        }
        if (!$this->jsonRpc) {
            return $payload;
        }
        if (!isset($payload->result) || !$payload->result instanceof \stdClass) {
            throw new ProtocolException(ErrorCode::InvalidAgentResponse, 'Missing JSON-RPC result');
        }
        return $payload->result;
    }
}
