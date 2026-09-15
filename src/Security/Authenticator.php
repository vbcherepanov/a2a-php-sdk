<?php

declare(strict_types=1);

namespace A2A\Security;

interface Authenticator
{
    /** @param array<string, string> $headers */
    public function authenticate(array $headers, string $tenant, ?float $deadline): CallContext;
}
