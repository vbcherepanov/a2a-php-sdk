<?php

declare(strict_types=1);

namespace A2A\Transport\Grpc;

use A2A\Protocol\{ErrorCode, ProtocolException};
use A2A\Server\ServerOptions;
use OpenSwoole\Http\{Request, Response, Server};
use Psr\Log\LoggerInterface;

final readonly class OpenSwooleRuntime
{
    public function __construct(private GrpcEndpoint $endpoint, private GrpcServerOptions $grpc, private ServerOptions $limits, private LoggerInterface $logger)
    {
    }
    public function serve(): void
    {
        if (!$this->grpc->enabled) {
            throw new \LogicException('gRPC is disabled; enable the grpc transport first');
        }
        if (!extension_loaded('openswoole')) {
            throw new \LogicException('The gRPC server requires ext-openswoole >= 26.2 with HTTP/2 support');
        }
        $socket = \OpenSwoole\Constant::SOCK_TCP | ($this->grpc->certificateFile !== null ? \OpenSwoole\Constant::SSL : 0);
        $server = new Server($this->grpc->host, $this->grpc->port, \OpenSwoole\Server::POOL_MODE, $socket);
        $settings = [
            'open_http2_protocol' => true,
            'enable_coroutine' => true,
            'hook_flags' => \OpenSwoole\Runtime::HOOK_SLEEP,
            'worker_num' => $this->grpc->workers,
            'max_request' => $this->grpc->maxRequestsPerWorker,
            'max_conn' => $this->grpc->maxConnections,
            'http2_max_concurrent_streams' => $this->grpc->maxConcurrentStreams,
            'package_max_length' => $this->limits->maxMessageBytes + 5,
            'log_level' => \OpenSwoole\Constant::LOG_WARNING,
        ];
        if ($this->grpc->certificateFile !== null) {
            $settings['ssl_cert_file'] = $this->grpc->certificateFile;
            $settings['ssl_key_file'] = $this->grpc->privateKeyFile;
            $settings['ssl_verify_peer'] = $this->grpc->requireClientCertificate;
            $settings['ssl_allow_self_signed'] = false;
            if ($this->grpc->clientCaFile !== null) {
                $settings['ssl_client_cert_file'] = $this->grpc->clientCaFile;
            }
        }
        $server->set($settings);
        $server->on('request', function (Request $request, Response $response): void {
            $this->respond($request, $response);
        });
        if (!$server->start()) {
            throw new \RuntimeException('Cannot start gRPC server');
        }
    }
    private function respond(Request $request, Response $response): void
    {
        $response->header('content-type', 'application/grpc');
        $response->header('a2a-version', '1.0');
        try {
            if (($request->server['request_method'] ?? '') !== 'POST') {
                throw new ProtocolException(ErrorCode::InvalidRequest, 'gRPC requires POST');
            }
            $headers = [];
            foreach ($request->header ?? [] as $name => $value) {
                if (is_string($value)) {
                    $headers[$name] = $value;
                }
            }
            $body = $request->getContent();
            foreach ($this->endpoint->handle($request->server['request_uri'] ?? '', is_string($body) ? $body : '', $headers) as $frame) {
                if (!$response->write($frame)) {
                    throw new \RuntimeException('gRPC client disconnected');
                }
            }
            $response->trailer('grpc-status', '0');
        } catch (\Throwable $error) {
            $this->logger->error('A2A gRPC request failed', ['exception_class' => $error::class]);
            $protocol = $error instanceof ProtocolException ? $error : new ProtocolException(ErrorCode::InternalError, 'Internal server error', $error);
            $response->trailer('grpc-status', (string) $protocol->error->grpcStatus());
            $response->trailer('grpc-message', rawurlencode($protocol->getMessage()));
            $response->trailer('grpc-status-details-bin', base64_encode($protocol->status()->serializeToString()));
        }
        $response->end();
    }
}
