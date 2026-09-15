<?php

declare(strict_types=1);

namespace A2A\Observability;

final class PrometheusMetrics implements Metrics
{
    /** @var array<string, array{count:int, sum:float, buckets:array<int,int>}> */
    private array $series = [];
    /** @param list<float> $buckets */
    public function __construct(private readonly array $buckets = [0.005, 0.01, 0.05, 0.1, 0.5, 1.0, 5.0, 30.0, 60.0], private readonly ?string $stateFile = null)
    {
        $previous = 0.0;
        foreach ($buckets as $bound) {
            if (!is_finite($bound) || $bound <= $previous) {
                throw new \InvalidArgumentException('Histogram boundaries must be finite, positive and increasing');
            }
            $previous = $bound;
        }
    }
    public function observe(string $operation, string $outcome, float $seconds): void
    {
        $this->synchronized(function () use ($operation, $outcome, $seconds): string {
            $this->record($operation, $outcome, $seconds);
            return '';
        }, true);
    }
    private function record(string $operation, string $outcome, float $seconds): void
    {
        $key = 'operation="'.$this->escape($operation).'",outcome="'.$this->escape($outcome).'"';
        $this->series[$key] ??= ['count' => 0, 'sum' => 0.0, 'buckets' => array_fill(0, count($this->buckets), 0)];
        ++$this->series[$key]['count'];
        $this->series[$key]['sum'] += $seconds;
        foreach ($this->buckets as $index => $bound) {
            if ($seconds <= $bound) {
                ++$this->series[$key]['buckets'][$index];
            }
        }
    }
    public function render(): string
    {
        return $this->synchronized(fn (): string => $this->format(), false);
    }
    private function format(): string
    {
        $out = "# TYPE a2a_requests_total counter\n# TYPE a2a_request_duration_seconds histogram\n";
        foreach ($this->series as $key => $series) {
            $out .= "a2a_requests_total{{$key}} {$series['count']}\n";
            foreach ($this->buckets as $index => $bound) {
                $out .= "a2a_request_duration_seconds_bucket{{$key},le=\"{$bound}\"} {$series['buckets'][$index]}\n";
            }
            $out .= "a2a_request_duration_seconds_bucket{{$key},le=\"+Inf\"} {$series['count']}\n";
            $out .= "a2a_request_duration_seconds_count{{$key}} {$series['count']}\n";
            $out .= "a2a_request_duration_seconds_sum{{$key}} {$series['sum']}\n";
        }
        return $out;
    }
    private function synchronized(\Closure $action, bool $write): string
    {
        if ($this->stateFile === null) {
            return $action();
        }
        $directory = dirname($this->stateFile);
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new \RuntimeException('Cannot create metrics directory');
        }
        $lock = fopen($this->stateFile.'.lock', 'c');
        if ($lock === false) {
            throw new \RuntimeException('Cannot open metrics lock');
        }
        try {
            if (!flock($lock, $write ? LOCK_EX : LOCK_SH)) {
                throw new \RuntimeException('Cannot lock metrics storage');
            }
            $this->series = [];
            if (is_file($this->stateFile)) {
                $json = file_get_contents($this->stateFile);
                if ($json === false) {
                    throw new \RuntimeException('Cannot read metrics storage');
                }
                $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
                if (!is_array($data) || ($data['bounds'] ?? null) != $this->buckets || !is_array($data['series'] ?? null)) {
                    throw new \RuntimeException('Incompatible metrics storage; use a new state file for changed buckets');
                }
                foreach ($data['series'] as $key => $series) {
                    if (!is_string($key) || !is_array($series) || !is_int($series['count'] ?? null) || !is_numeric($series['sum'] ?? null) || !is_array($series['buckets'] ?? null) || count($series['buckets']) !== count($this->buckets)) {
                        throw new \RuntimeException('Corrupt metrics series');
                    }
                    $counts = [];
                    foreach ($series['buckets'] as $count) {
                        if (!is_int($count)) {
                            throw new \RuntimeException('Corrupt histogram');
                        }
                        $counts[] = $count;
                    }
                    $this->series[$key] = ['count' => $series['count'], 'sum' => (float) $series['sum'], 'buckets' => $counts];
                }
            }
            $result = $action();
            if ($write) {
                $json = json_encode(['bounds' => $this->buckets, 'series' => $this->series], JSON_THROW_ON_ERROR);
                $temporary = $this->stateFile.'.tmp';
                if (file_put_contents($temporary, $json) !== strlen($json) || !rename($temporary, $this->stateFile)) {
                    throw new \RuntimeException('Cannot persist metrics');
                }
            }
            return $result;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
    private function escape(string $label): string
    {
        return str_replace(["\\", "\"", "\n"], ["\\\\", "\\\"", "\\n"], $label);
    }
}
