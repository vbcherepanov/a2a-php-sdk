<?php

declare(strict_types=1);

namespace A2A\Security;

use A2A\Protocol\ErrorCode;
use A2A\Protocol\ProtocolException;

final readonly class CallContext
{
    /** @param list<string> $extensions */
    public function __construct(
        public string $principal,
        public string $tenant = '',
        public array $extensions = [],
        public string $version = '1.0',
        public ?float $deadline = null,
    ) {
        if ($principal === '') {
            throw new \InvalidArgumentException('A principal is required');
        }
    }
    public function check(): void
    {
        if ($this->version !== '1.0') {
            throw new ProtocolException(ErrorCode::VersionNotSupported, 'Supported A2A version: 1.0');
        }
        if ($this->deadline !== null && microtime(true) >= $this->deadline) {
            throw new ProtocolException(ErrorCode::DeadlineExceeded, 'Request deadline exceeded');
        }
    }
}
