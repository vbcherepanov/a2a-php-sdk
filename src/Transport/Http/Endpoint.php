<?php

declare(strict_types=1);

namespace A2A\Transport\Http;

use A2A\Protocol\{ErrorCode, ErrorMapper, Json, Operation, ProtocolException};
use A2A\Server\{Gateway, ServerOptions};
use Google\Protobuf\Internal\Message;
use Lf\A2a\V1\AgentCard;

final readonly class Endpoint
{
    public function __construct(private Gateway $gateway, private AgentCard $card, private HttpOptions $httpOptions, private ServerOptions $serverOptions)
    {
    }
    public function handle(Request $request): Response
    {
        $path = parse_url($request->uri, PHP_URL_PATH);
        $rpc = is_string($path) && rtrim($path, '/') === rtrim($this->httpOptions->rpcPath, '/') && $this->httpOptions->jsonRpcEnabled;
        $id = null;
        try {
            if ($path === $this->httpOptions->cardPath && $request->method === 'GET') {
                $body = $this->card->serializeToJsonString();
                $etag = '"'.hash('sha256', $body).'"';
                $headers = array_change_key_case($request->headers);
                $responseHeaders = ['Content-Type' => 'application/json', 'ETag' => $etag, 'Cache-Control' => 'public, max-age='.$this->httpOptions->cardCacheSeconds, 'A2A-Version' => '1.0'];
                $modified = $this->httpOptions->cardLastModified;
                if ($modified !== null) {
                    $responseHeaders['Last-Modified'] = gmdate('D, d M Y H:i:s', $modified).' GMT';
                }
                $notModified = isset($headers['if-none-match']) ? $headers['if-none-match'] === $etag : ($modified !== null && ($since = strtotime($headers['if-modified-since'] ?? '')) !== false && $modified <= $since);
                return new Response($notModified ? 304 : 200, $notModified ? '' : $body, $responseHeaders);
            }
            if (strlen($request->body) > $this->serverOptions->maxMessageBytes) {
                throw new ProtocolException(ErrorCode::ResourceExhausted, 'Request exceeds message limit');
            }
            if ($request->body !== '') {
                $contentType = strtolower(explode(';', array_change_key_case($request->headers)['content-type'] ?? '')[0]);
                if (!in_array($contentType, ['application/json', 'application/a2a+json'], true)) {
                    throw new ProtocolException(ErrorCode::ContentTypeNotSupported, 'Expected JSON content type');
                }
            }
            if ($rpc) {
                if ($request->method !== 'POST') {
                    throw new ProtocolException(ErrorCode::InvalidRequest, 'JSON-RPC requires POST');
                }
                $envelope = Json::object($request->body);
                $id = is_string($envelope->id ?? null) || is_int($envelope->id ?? null) ? $envelope->id : null;
                if (($envelope->jsonrpc ?? null) !== '2.0' || $id === null || !is_string($envelope->method ?? null)) {
                    throw new ProtocolException(ErrorCode::InvalidRequest, 'Invalid JSON-RPC request; notifications and batches are not supported');
                }
                $operation = Operation::tryFrom($envelope->method) ?? throw new ProtocolException(ErrorCode::MethodNotFound, 'Unknown method');
                $data = $envelope->params ?? new \stdClass();
                if (!$data instanceof \stdClass) {
                    throw ProtocolException::invalid('Named parameters required');
                }
                $tenant = is_string($data->tenant ?? null) ? $data->tenant : '';
            } else {
                [$operation, $data, $tenant] = $this->rest($request, is_string($path) ? $path : '');
            }
            $message = Json::message(Json::encode($data), $operation->requestClass());
            $response = $this->gateway->invoke($operation, $message, $request->headers, $tenant);
            if ($response instanceof Message) {
                $body = $rpc ? Json::encode((object) ['jsonrpc' => '2.0', 'id' => $id, 'result' => Json::data($response)]) : $response->serializeToJsonString();
                return new Response(200, $body);
            }
            $stream = (function () use ($response, $rpc, $id): iterable {
                try {
                    foreach ($response as $event) {
                        $body = $rpc ? Json::encode((object) ['jsonrpc' => '2.0', 'id' => $id, 'result' => Json::data($event)]) : $event->serializeToJsonString();
                        yield "data: ".$body."\n\n";
                    }
                } catch (ProtocolException $error) {
                    yield "data: ".$this->errorBody($error, $rpc, $id)."\n\n";
                }
            })();
            return new Response(200, $stream, ['Content-Type' => 'text/event-stream', 'Cache-Control' => 'no-cache', 'X-Accel-Buffering' => 'no', 'A2A-Version' => '1.0']);
        } catch (ProtocolException $error) {
            return new Response($rpc && !in_array($error->error, [ErrorCode::Unauthenticated, ErrorCode::PermissionDenied, ErrorCode::ResourceExhausted], true) ? 200 : $error->error->httpStatus(), $this->errorBody($error, $rpc, $id));
        }
    }
    /** @return array{Operation, \stdClass, string} */
    private function rest(Request $request, string $path): array
    {
        $prefix = rtrim($this->httpOptions->restPath, '/');
        if (!$this->httpOptions->restEnabled || !str_starts_with($path, $prefix.'/')) {
            throw new ProtocolException(ErrorCode::MethodNotFound, 'Endpoint not found');
        }
        $relative = substr($path, strlen($prefix));
        foreach (Operation::cases() as $operation) {
            $methodMatches = $operation->httpMethod() === $request->method || ($operation === Operation::SubscribeToTask && $request->method === 'POST');
            if (!$methodMatches) {
                continue;
            }
            $pattern = preg_quote($operation->path(), '~');
            $pattern = str_replace(['\{id\}', '\{taskId\}'], ['(?P<id>[^/:]+)', '(?P<taskId>[^/:]+)'], $pattern);
            if (!preg_match('~^(?:/(?P<tenant>[^/]+))?'.$pattern.'$~D', $relative, $matches)) {
                continue;
            }
            $data = $request->body === '' ? new \stdClass() : Json::object($request->body);
            $queryString = parse_url($request->uri, PHP_URL_QUERY);
            parse_str(is_string($queryString) ? $queryString : '', $query);
            foreach ($query as $name => $value) {
                if (!is_string($value)) {
                    throw ProtocolException::invalid('Scalar query parameters required');
                }
                if (in_array($name, ['historyLength', 'pageSize'], true)) {
                    if (!preg_match('/^-?\d+$/D', $value) || filter_var($value, FILTER_VALIDATE_INT) === false) {
                        throw ProtocolException::invalid('Invalid integer query parameter');
                    }
                    $data->{$name} = (int) $value;
                } elseif ($name === 'includeArtifacts') {
                    if (!in_array($value, ['true', 'false'], true)) {
                        throw ProtocolException::invalid('Invalid boolean query parameter');
                    }
                    $data->{$name} = $value === 'true';
                } else {
                    $data->{$name} = $value;
                }
            }
            foreach (['id', 'taskId', 'tenant'] as $name) {
                if (isset($matches[$name]) && $matches[$name] !== '') {
                    $value = rawurldecode($matches[$name]);
                    if (isset($data->{$name}) && $data->{$name} !== $value) {
                        throw ProtocolException::invalid('Body/query parameter conflicts with path');
                    }
                    $data->{$name} = $value;
                }
            }
            return [$operation, $data, is_string($data->tenant ?? null) ? $data->tenant : ''];
        }
        throw new ProtocolException(ErrorCode::MethodNotFound, 'Endpoint not found');
    }
    private function errorBody(ProtocolException $error, bool $rpc, int|string|null $id): string
    {
        return Json::encode($rpc ? (object) ['jsonrpc' => '2.0', 'id' => $id, 'error' => ErrorMapper::rpc($error)] : ErrorMapper::rest($error));
    }
}
