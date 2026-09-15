<?php

declare(strict_types=1);

namespace A2A\Server;

use A2A\Protocol\{ErrorCode, ProtocolException, Validator};
use A2A\Security\CallContext;
use A2A\Storage\Proto\Record;
use A2A\Storage\TaskRepository;
use Lf\A2a\V1\{StreamResponse, Task, TaskState, TaskStatusUpdateEvent};
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class Processor
{
    public function directResponse(\Lf\A2a\V1\SendMessageRequest $request, CallContext $context): ?\Lf\A2a\V1\Message
    {
        if (!$this->executor instanceof DirectResponder || $request->getMessage()?->getTaskId() !== '') {
            return null;
        }
        $response = $this->executor->respond($request, $context);
        if ($response !== null) {
            (new Validator())->validate($response);
            if ($response->getRole() !== \Lf\A2a\V1\Role::ROLE_AGENT || $response->getContextId() === '' || $response->getTaskId() !== '') {
                throw new ProtocolException(ErrorCode::InvalidAgentResponse, 'Invalid direct agent response');
            }
        }
        return $response;
    }
    public function __construct(private TaskRepository $repository, private Executor $executor, private PushManager $push, private LoggerInterface $logger, private ServerOptions $options)
    {
    }
    /** @return iterable<StreamResponse> */
    public function run(string $id, CallContext $context): iterable
    {
        $token = Uuid::v7()->toRfc4122();
        $record = $this->repository->update($context, $id, function (Record $record) use ($token): void {
            if (!$record->hasPending() || ($record->getLeaseToken() !== '' && $record->getLeaseUntil() > microtime(true))) {
                throw new ProtocolException(ErrorCode::UnsupportedOperation, 'Task is already processing or has no pending work');
            }
            $task = $record->getTask() ?? throw new \LogicException('Missing task');
            if (Lifecycle::terminal(Lifecycle::state($task))) {
                throw new ProtocolException(ErrorCode::UnsupportedOperation, 'Task is terminal');
            }
            $record->setLeaseToken($token)->setLeaseUntil(microtime(true) + $this->options->workerLeaseSeconds);
        });
        $task = $record->getTask() ?? throw new \LogicException('Missing task');
        $request = $record->getPending() ?? throw new \LogicException('Missing request');
        try {
            $working = (new StreamResponse())->setStatusUpdate((new TaskStatusUpdateEvent())->setTaskId($id)->setContextId($task->getContextId())->setStatus(Lifecycle::status(TaskState::TASK_STATE_WORKING)));
            $this->apply($id, $context, $working, $token);
            yield $working;
            foreach ($this->executor->execute($request, Lifecycle::project($task, null), $context) as $event) {
                $context->check();
                (new Validator())->validate($event);
                $this->apply($id, $context, $event, $token);
                yield $event;
            }
            $current = $this->repository->get($context, $id)->getTask() ?? throw new \LogicException('Missing task');
            if (!Lifecycle::stopped(Lifecycle::state($current))) {
                throw new ProtocolException(ErrorCode::InvalidAgentResponse, 'Executor must finish in a terminal or interrupted state');
            }
        } catch (\Throwable $error) {
            $this->logger->error('A2A execution failed', ['task_id' => $id, 'exception_class' => $error::class]);
            $failed = null;
            $this->repository->update($context, $id, function (Record $record) use ($token, &$failed): void {
                $task = $record->getTask() ?? throw new \LogicException('Missing task');
                if ($record->getLeaseToken() !== $token || Lifecycle::terminal(Lifecycle::state($task))) {
                    return;
                }
                $task->setStatus(Lifecycle::status(TaskState::TASK_STATE_FAILED));
                $failed = (new StreamResponse())->setStatusUpdate((new TaskStatusUpdateEvent())->setTaskId($task->getId())->setContextId($task->getContextId())->setStatus($task->getStatus() ?? throw new \LogicException('Missing status')));
                $this->recordEvent($record, $failed);
                $this->push->enqueue($record, $task);
            });
            if ($failed !== null) {
                yield $failed;
            } elseif (!($error instanceof ProtocolException && $error->error === ErrorCode::UnsupportedOperation)) {
                throw $error;
            }
        } finally {
            $this->repository->update($context, $id, static function (Record $record) use ($token): void {
                if ($record->getLeaseToken() === $token) {
                    if (Lifecycle::stopped(Lifecycle::state($record->getTask() ?? throw new \LogicException('Missing task')))) {
                        $record->clearPending();
                    }
                    $record->setLeaseUntil(0)->setLeaseToken('');
                }
            });
        }
    }
    private function apply(string $id, CallContext $context, StreamResponse $event, string $token): void
    {
        $this->repository->update($context, $id, function (Record $record) use ($event, $token): void {
            $task = $record->getTask() ?? throw new \LogicException('Missing task');
            if ($record->getLeaseToken() !== $token || Lifecycle::terminal(Lifecycle::state($task))) {
                throw new ProtocolException(ErrorCode::UnsupportedOperation, 'Execution no longer owns the task');
            }
            if (($status = $event->getStatusUpdate()) !== null) {
                if ($status->getTaskId() !== $task->getId() || $status->getContextId() !== $task->getContextId()) {
                    throw new ProtocolException(ErrorCode::InvalidAgentResponse, 'Status event references another task');
                }
                $newStatus = $status->getStatus() ?? throw new \LogicException('Missing status');
                Lifecycle::transition($task, $newStatus);
                if (($message = $newStatus->getMessage()) !== null) {
                    $task->getHistory()[] = $message;
                }
            } elseif (($update = $event->getArtifactUpdate()) !== null) {
                if ($update->getTaskId() !== $task->getId() || $update->getContextId() !== $task->getContextId()) {
                    throw new ProtocolException(ErrorCode::InvalidAgentResponse, 'Artifact event references another task');
                }
                $artifact = $update->getArtifact() ?? throw new \LogicException('Missing artifact');
                $artifacts = iterator_to_array($task->getArtifacts());
                $found = false;
                foreach ($artifacts as $index => $existing) {
                    if ($existing->getArtifactId() === $artifact->getArtifactId()) {
                        if ($update->getAppend()) {
                            $existing->setParts([...iterator_to_array($existing->getParts()), ...iterator_to_array($artifact->getParts())]);
                        } else {
                            $artifacts[$index] = $artifact;
                        }
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    if ($update->getAppend()) {
                        throw new ProtocolException(ErrorCode::InvalidAgentResponse, 'Cannot append to unknown artifact');
                    }
                    $artifacts[] = $artifact;
                }
                $task->setArtifacts($artifacts);
            } else {
                throw new ProtocolException(ErrorCode::InvalidAgentResponse, 'Task executors must emit status or artifact updates');
            }
            $task->setHistory(array_slice(iterator_to_array($task->getHistory()), -$this->options->maxHistoryMessages));
            $record->setLeaseUntil(microtime(true) + $this->options->workerLeaseSeconds);
            $this->recordEvent($record, $event);
            $this->push->enqueue($record, $task);
        });
    }
    public function recordEvent(Record $record, StreamResponse $event): void
    {
        $events = iterator_to_array($record->getEvents());
        $events[] = $event;
        $record->setEvents(array_slice($events, -$this->options->maxEventsPerTask));
        $record->setEventSequence((int) $record->getEventSequence() + 1);
    }
    public function workOnce(): int
    {
        $count = 0;
        foreach ($this->repository->pending() as $record) {
            $context = new CallContext($record->getPrincipal(), $record->getTenant(), array_values(iterator_to_array($record->getExtensions())), deadline: microtime(true) + $this->options->requestTimeoutSeconds);
            try {
                foreach ($this->run($record->getTask()?->getId() ?? '', $context) as $event) {
                    ++$count;
                }
            } catch (ProtocolException $error) {
                if ($error->error !== ErrorCode::UnsupportedOperation) {
                    throw $error;
                }
            }
        }
        foreach ($this->repository->deliveries() as $record) {
            $this->push->deliver($record);
        }
        return $count;
    }
}
