<?php

declare(strict_types=1);

namespace A2A\Storage;

use A2A\Security\CallContext;
use A2A\Storage\Proto\Record;

interface TaskRepository
{
    public function create(Record $record): void;
    public function get(CallContext $context, string $id): Record;
    /** @param \Closure(Record): void $change */
    public function update(CallContext $context, string $id, \Closure $change): Record;
    /** @return iterable<Record> */
    public function list(CallContext $context): iterable;
    /** @return iterable<Record> */
    public function pending(): iterable;
    /** @return iterable<Record> */
    public function deliveries(): iterable;
}
