<?php

declare(strict_types=1);

namespace A2A\Client;

final readonly class CallOptions
{
    /** @param array<string, string> $headers
     * @param list<string> $extensions
     */
    public function __construct(
        public float $timeoutSeconds = 60.0,
        public int $maxMessageBytes = 4194304,
        public array $headers = [],
        public array $extensions = [],
        public string $version = '1.0',
    ) {
        if ($timeoutSeconds <= 0 || $maxMessageBytes < 1) {
            throw new \InvalidArgumentException('Timeout and message limit must be positive');
        }
        foreach ($headers as $name => $value) {
            if (preg_match('/[\r\n]/', $name.$value) || in_array(strtolower($name), ['host', 'content-length', 'transfer-encoding', 'content-type', 'a2a-version', 'a2a-extensions'], true)) {
                throw new \InvalidArgumentException('Unsafe or reserved header');
            }
        }
    }
    /** @return array<string, string> */
    public function httpHeaders(): array
    {
        return array_merge($this->headers, ['A2A-Version' => $this->version, 'A2A-Extensions' => implode(',', $this->extensions)]);
    }
}
