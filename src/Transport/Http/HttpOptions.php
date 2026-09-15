<?php

declare(strict_types=1);

namespace A2A\Transport\Http;

final readonly class HttpOptions
{
    public function __construct(
        public bool $jsonRpcEnabled = true,
        public bool $restEnabled = true,
        public string $rpcPath = '/a2a/rpc',
        public string $restPath = '/a2a',
        public string $cardPath = '/.well-known/agent-card.json',
        public int $cardCacheSeconds = 300,
        public ?int $cardLastModified = null,
    ) {
        foreach ([$rpcPath, $restPath, $cardPath] as $path) {
            if (!str_starts_with($path, '/') || str_contains($path, '?') || str_contains($path, '#')) {
                throw new \InvalidArgumentException('Routes must be absolute paths without query or fragment');
            }
        }
        if ($rpcPath === $restPath || $cardCacheSeconds < 0 || ($cardLastModified !== null && $cardLastModified < 0)) {
            throw new \InvalidArgumentException('Invalid HTTP options');
        }
    }
}
