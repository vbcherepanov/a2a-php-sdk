<?php

declare(strict_types=1);

namespace A2A\Storage;

use A2A\Protocol\{ErrorCode, ProtocolException};
use A2A\Security\CallContext;
use A2A\Storage\Proto\Record;

final readonly class FileTaskRepository implements TaskRepository
{
    public function __construct(private string $directory)
    {
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new \RuntimeException('Cannot create task storage directory');
        }
    }
    public function create(Record $record): void
    {
        $id = $record->getTask()?->getId() ?? '';
        if ($id === '') {
            throw new \InvalidArgumentException('Task ID required');
        }
        $this->locked(function () use ($record, $id): void {
            if (is_file($this->path($id))) {
                throw new \RuntimeException('Duplicate task ID');
            }
            $this->write($record);
        });
    }
    public function get(CallContext $context, string $id): Record
    {
        return $this->locked(fn (): Record => $this->authorized($context, $id));
    }
    public function update(CallContext $context, string $id, \Closure $change): Record
    {
        return $this->locked(function () use ($context, $id, $change): Record {
            $record = $this->authorized($context, $id);
            $principal = $record->getPrincipal();
            $tenant = $record->getTenant();
            $change($record);
            if ($record->getPrincipal() !== $principal || $record->getTenant() !== $tenant || $record->getTask()?->getId() !== $id) {
                throw new \LogicException('Task identity cannot change');
            }
            $record->setRevision((int) $record->getRevision() + 1);
            $this->write($record);
            return $record;
        });
    }
    public function list(CallContext $context): iterable
    {
        foreach ($this->records() as $record) {
            if ($record->getPrincipal() === $context->principal && $record->getTenant() === $context->tenant) {
                yield $record;
            }
        }
    }
    public function pending(): iterable
    {
        foreach ($this->records() as $record) {
            if ($record->hasPending() && $record->getLeaseUntil() < microtime(true)) {
                yield $record;
            }
        }
    }
    public function deliveries(): iterable
    {
        foreach ($this->records() as $record) {
            if (count($record->getOutbox()) > 0) {
                yield $record;
            }
        }
    }
    /** @return iterable<Record> */
    private function records(): iterable
    {
        $files = glob($this->directory.'/*.pb');
        if ($files === false) {
            throw new \RuntimeException('Cannot enumerate tasks');
        }
        foreach ($files as $file) {
            yield $this->locked(fn (): Record => $this->read($file));
        }
    }
    private function authorized(CallContext $context, string $id): Record
    {
        $file = $this->path($id);
        if (!is_file($file)) {
            throw new ProtocolException(ErrorCode::TaskNotFound, 'Task not found');
        }
        $record = $this->read($file);
        if ($record->getPrincipal() !== $context->principal || $record->getTenant() !== $context->tenant) {
            throw new ProtocolException(ErrorCode::TaskNotFound, 'Task not found');
        }
        return $record;
    }
    private function read(string $path): Record
    {
        $bytes = file_get_contents($path);
        if ($bytes === false) {
            throw new \RuntimeException('Cannot read task storage');
        }
        $record = new Record();
        $record->mergeFromString($bytes);
        return $record;
    }
    private function write(Record $record): void
    {
        $path = $this->path($record->getTask()?->getId() ?? '');
        $temporary = tempnam($this->directory, '.write-');
        if ($temporary === false) {
            throw new \RuntimeException('Cannot create task storage temporary file');
        }
        try {
            $data = $record->serializeToString();
            if (file_put_contents($temporary, $data) !== strlen($data) || !rename($temporary, $path)) {
                throw new \RuntimeException('Cannot persist task');
            }
        } finally {
            if (is_file($temporary) && !unlink($temporary)) {
                throw new \RuntimeException('Cannot remove task storage temporary file');
            }
        }
    }
    private function path(string $id): string
    {
        return $this->directory.'/'.hash('sha256', $id).'.pb';
    }
    /** @template T
     * @param \Closure(): T $operation
     * @return T
     */
    private function locked(\Closure $operation)
    {
        $lock = fopen($this->directory.'/.lock', 'c');
        if ($lock === false) {
            throw new \RuntimeException('Cannot open task lock');
        }
        try {
            if (!flock($lock, LOCK_EX)) {
                throw new \RuntimeException('Cannot acquire task lock');
            }
            return $operation();
        } finally {
            if (!fclose($lock)) {
                throw new \RuntimeException('Cannot release task lock');
            }
        }
    }
}
