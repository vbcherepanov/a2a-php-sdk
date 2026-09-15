<?php

declare(strict_types=1);

namespace A2A\Server;

final readonly class PushOptions
{
    /** @param list<string> $allowedHosts */
    public function __construct(
        public array $allowedHosts = [],
        public float $timeoutSeconds = 10.0,
        public int $maxAttempts = 5,
        public float $retryDelaySeconds = 5.0,
        public int $maxPendingPerTask = 1000,
        public bool $allowPrivateNetwork = false,
        public bool $requireHttps = true,
    ) {
        if ($timeoutSeconds <= 0 || $maxAttempts < 1 || $retryDelaySeconds <= 0 || $maxPendingPerTask < 1) {
            throw new \InvalidArgumentException('Push limits must be positive');
        }
    }
}
