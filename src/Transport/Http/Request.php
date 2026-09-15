<?php

declare(strict_types=1);

namespace A2A\Transport\Http;

final readonly class Request
{
    /** @param array<string, string> $headers */
    public function __construct(public string $method, public string $uri, public string $body = '', public array $headers = [])
    {
    }
}
