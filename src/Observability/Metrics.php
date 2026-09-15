<?php

declare(strict_types=1);

namespace A2A\Observability;

interface Metrics
{
    public function observe(string $operation, string $outcome, float $seconds): void;
}
