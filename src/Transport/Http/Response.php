<?php

declare(strict_types=1);

namespace A2A\Transport\Http;

final readonly class Response
{
    /** @param string|iterable<string> $body
     * @param array<string, string> $headers
     */
    public function __construct(public int $status, public string|iterable $body, public array $headers = ['Content-Type' => 'application/json', 'A2A-Version' => '1.0'])
    {
    }
}
