<?php

declare(strict_types=1);

namespace A2A\Transport\Grpc;

final readonly class GrpcServerOptions
{
    public function __construct(
        public bool $enabled = false,
        public string $host = '127.0.0.1',
        public int $port = 50051,
        public int $workers = 4,
        public int $maxRequestsPerWorker = 1000,
        public ?string $certificateFile = null,
        public ?string $privateKeyFile = null,
        public ?string $clientCaFile = null,
        public bool $requireClientCertificate = false,
        public int $maxConcurrentStreams = 100,
        public int $maxConnections = 1024,
    ) {
        if ($port < 1 || $port > 65535 || min($workers, $maxRequestsPerWorker, $maxConcurrentStreams, $maxConnections) < 1 || $host === '') {
            throw new \InvalidArgumentException('Invalid gRPC server options');
        }
        if (($certificateFile === null) !== ($privateKeyFile === null) || ($requireClientCertificate && ($clientCaFile === null || $certificateFile === null))) {
            throw new \InvalidArgumentException('Incomplete gRPC TLS configuration');
        }
        foreach ([$certificateFile, $privateKeyFile, $clientCaFile] as $path) {
            if ($path !== null && !is_readable($path)) {
                throw new \InvalidArgumentException('TLS file is not readable');
            }
        }
    }
}
