<?php

declare(strict_types=1);

namespace A2A\Transport;

use A2A\Protocol\ErrorCode;
use A2A\Protocol\ProtocolException;

final class SseDecoder
{
    private string $buffer = '';
    private string $data = '';
    private bool $initial = true;
    public function __construct(private readonly int $maxEventBytes)
    {
    }
    /** @return list<string> */
    public function feed(string $chunk): array
    {
        $this->buffer .= $chunk;
        if ($this->initial) {
            if (strlen($this->buffer) < 3) {
                return [];
            }
            if (str_starts_with($this->buffer, "\xEF\xBB\xBF")) {
                $this->buffer = substr($this->buffer, 3);
            }
            $this->initial = false;
        }
        $events = [];
        while (preg_match('/\r\n|\r(?!$)|\n/', $this->buffer, $match, PREG_OFFSET_CAPTURE)) {
            $line = substr($this->buffer, 0, $match[0][1]);
            $this->buffer = substr($this->buffer, $match[0][1] + strlen($match[0][0]));
            if ($line === '') {
                if ($this->data !== '') {
                    $events[] = substr($this->data, 0, -1);
                    $this->data = '';
                }
            } elseif (str_starts_with($line, 'data:')) {
                $value = substr($line, 5);
                $this->data .= (str_starts_with($value, ' ') ? substr($value, 1) : $value)."\n";
            } elseif ($line === 'data') {
                $this->data .= "\n";
            }
            $this->checkLimit();
        }
        $this->checkLimit();
        return $events;
    }
    public function finish(): void
    {
        if (trim($this->buffer) !== '' || $this->data !== '') {
            throw new ProtocolException(ErrorCode::InvalidAgentResponse, 'Truncated SSE event');
        }
    }
    private function checkLimit(): void
    {
        if (strlen($this->buffer) + strlen($this->data) > $this->maxEventBytes) {
            throw new ProtocolException(ErrorCode::ResourceExhausted, 'SSE event exceeds limit');
        }
    }
}
