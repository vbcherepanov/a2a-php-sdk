<?php

declare(strict_types=1);

namespace A2A\Server;

final readonly class ServerOptions
{
    public function __construct(
        public float $requestTimeoutSeconds = 60.0,
        public float $pollIntervalSeconds = 0.1,
        public float $workerLeaseSeconds = 300.0,
        public int $maxMessageBytes = 4194304,
        public int $maxEventsPerTask = 1000,
        public int $maxHistoryMessages = 1000,
        public int $maxPushConfigsPerTask = 10,
    ) {
        if ($requestTimeoutSeconds <= 0 || $pollIntervalSeconds <= 0 || $workerLeaseSeconds <= 0 || min($maxMessageBytes, $maxEventsPerTask, $maxHistoryMessages, $maxPushConfigsPerTask) < 1) {
            throw new \InvalidArgumentException('Server limits must be positive');
        }
    }
}
