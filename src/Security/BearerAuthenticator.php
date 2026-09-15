<?php

declare(strict_types=1);

namespace A2A\Security;

use A2A\Protocol\ErrorCode;
use A2A\Protocol\ProtocolException;

final readonly class BearerAuthenticator implements Authenticator
{
    /** @param array<string, string> $tokens Map of principal to secret token. */
    public function __construct(private array $tokens)
    {
        if ($tokens === [] || in_array('', $tokens, true)) {
            throw new \InvalidArgumentException('Nonempty credentials required');
        }
    }
    public function authenticate(array $headers, string $tenant, ?float $deadline): CallContext
    {
        $headers = array_change_key_case($headers);
        $authorization = $headers['authorization'] ?? '';
        if (!preg_match('/^Bearer (.+)$/iD', $authorization, $matches)) {
            throw new ProtocolException(ErrorCode::Unauthenticated, 'Bearer authentication required');
        }
        foreach ($this->tokens as $principal => $token) {
            if (hash_equals($token, $matches[1])) {
                return new CallContext($principal, $tenant, array_values(array_filter(array_map('trim', explode(',', $headers['a2a-extensions'] ?? '')))), $headers['a2a-version'] ?? '1.0', $deadline);
            }
        }
        throw new ProtocolException(ErrorCode::Unauthenticated, 'Invalid credentials');
    }
}
