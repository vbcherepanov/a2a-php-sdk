<?php

declare(strict_types=1);

namespace A2A\Server;

use A2A\Protocol\{ErrorCode, ProtocolException};
use Google\Protobuf\Timestamp;
use Lf\A2a\V1\{Task, TaskState, TaskStatus};

final class Lifecycle
{
    public static function terminal(int $state): bool
    {
        return in_array($state, [TaskState::TASK_STATE_COMPLETED, TaskState::TASK_STATE_FAILED, TaskState::TASK_STATE_CANCELED, TaskState::TASK_STATE_REJECTED], true);
    }
    public static function stopped(int $state): bool
    {
        return self::terminal($state) || in_array($state, [TaskState::TASK_STATE_INPUT_REQUIRED, TaskState::TASK_STATE_AUTH_REQUIRED], true);
    }
    public static function state(Task $task): int
    {
        return $task->getStatus()?->getState() ?? TaskState::TASK_STATE_UNSPECIFIED;
    }
    public static function status(int $state): TaskStatus
    {
        $time = new Timestamp();
        $time->fromDateTime(new \DateTime());
        return (new TaskStatus())->setState($state)->setTimestamp($time);
    }
    public static function transition(Task $task, TaskStatus $status): void
    {
        if (self::terminal(self::state($task))) {
            throw new ProtocolException(ErrorCode::UnsupportedOperation, 'Task is already terminal');
        }
        if ($status->getState() === TaskState::TASK_STATE_UNSPECIFIED || $status->getState() === TaskState::TASK_STATE_SUBMITTED) {
            throw new ProtocolException(ErrorCode::InvalidAgentResponse, 'Invalid task transition');
        }
        if (!$status->hasTimestamp()) {
            $status->setTimestamp(self::status($status->getState())->getTimestamp() ?? throw new \LogicException('Missing timestamp'));
        }
        $task->setStatus($status);
    }
    public static function project(Task $task, ?int $historyLength, bool $artifacts = true): Task
    {
        $copy = new Task();
        $copy->mergeFromString($task->serializeToString());
        if ($historyLength !== null) {
            $copy->setHistory($historyLength === 0 ? [] : array_slice(iterator_to_array($copy->getHistory()), -$historyLength));
        }
        if (!$artifacts) {
            $copy->setArtifacts([]);
        }
        return $copy;
    }
}
