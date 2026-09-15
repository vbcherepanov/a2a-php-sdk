<?php

declare(strict_types=1);

namespace A2A\Server;

use A2A\Protocol\{ErrorCode, Json, ProtocolException, Validator};
use A2A\Security\CallContext;
use A2A\Storage\Proto\Record;
use A2A\Storage\TaskRepository;
use Google\Protobuf\GPBEmpty;
use Lf\A2a\V1\{AgentCard, CancelTaskRequest, DeleteTaskPushNotificationConfigRequest, GetExtendedAgentCardRequest, GetTaskPushNotificationConfigRequest, GetTaskRequest, ListTaskPushNotificationConfigsRequest, ListTaskPushNotificationConfigsResponse, ListTasksRequest, ListTasksResponse, Role, SendMessageRequest, SendMessageResponse, StreamResponse, SubscribeToTaskRequest, Task, TaskPushNotificationConfig, TaskState, TaskStatusUpdateEvent};
use Symfony\Component\Uid\Uuid;

final readonly class TaskService implements AgentService
{
    public function __construct(
        private TaskRepository $repository,
        private Processor $processor,
        private PushManager $push,
        private AgentCard $card,
        private ServerOptions $options,
        private ?AgentCard $extendedCard = null,
    ) {
        (new Validator())->validate($card);
    }
    public function sendMessage(SendMessageRequest $request, CallContext $context): SendMessageResponse
    {
        $this->validateInput($request);
        if (($message = $this->processor->directResponse($request, $context)) !== null) {
            return (new SendMessageResponse())->setMessage($message);
        }
        $record = $this->prepare($request, $context);
        $task = $record->getTask() ?? throw new \LogicException('Missing task');
        if (!$request->getConfiguration()?->getReturnImmediately()) {
            foreach ($this->processor->run($task->getId(), $context) as $event) {
                $context->check();
            }
            $task = $this->repository->get($context, $task->getId())->getTask() ?? throw new \LogicException('Missing task');
        }
        $config = $request->getConfiguration();
        return (new SendMessageResponse())->setTask(Lifecycle::project($task, $config?->hasHistoryLength() ? $config->getHistoryLength() : null));
    }
    public function sendStreamingMessage(SendMessageRequest $request, CallContext $context): iterable
    {
        $this->streaming();
        $this->validateInput($request);
        if (($message = $this->processor->directResponse($request, $context)) !== null) {
            return [(new StreamResponse())->setMessage($message)];
        }
        $record = $this->prepare($request, $context);
        $task = $record->getTask() ?? throw new \LogicException('Missing task');
        return (function () use ($request, $context, $task): iterable {
            $config = $request->getConfiguration();
            yield (new StreamResponse())->setTask(Lifecycle::project($task, $config?->hasHistoryLength() ? $config->getHistoryLength() : null));
            yield from $this->processor->run($task->getId(), $context);
        })();
    }
    public function getTask(GetTaskRequest $request, CallContext $context): Task
    {
        $task = $this->repository->get($context, $request->getId())->getTask() ?? throw new \LogicException('Missing task');
        return Lifecycle::project($task, $request->hasHistoryLength() ? $request->getHistoryLength() : null);
    }
    public function listTasks(ListTasksRequest $request, CallContext $context): ListTasksResponse
    {
        $tasks = [];
        foreach ($this->repository->list($context) as $record) {
            $task = $record->getTask() ?? throw new \LogicException('Missing task');
            if ($request->getContextId() !== '' && $task->getContextId() !== $request->getContextId()) {
                continue;
            }
            if ($request->getStatus() !== TaskState::TASK_STATE_UNSPECIFIED && Lifecycle::state($task) !== $request->getStatus()) {
                continue;
            }
            $after = $request->getStatusTimestampAfter();
            $timestamp = $task->getStatus()?->getTimestamp();
            if ($after !== null && ($timestamp === null || $timestamp->toDateTime() < $after->toDateTime())) {
                continue;
            }
            $tasks[] = $task;
        }
        usort($tasks, static fn (Task $a, Task $b): int => strcmp($b->getStatus()?->getTimestamp()?->toDateTime()->format('U.u') ?? '', $a->getStatus()?->getTimestamp()?->toDateTime()->format('U.u') ?? '') ?: strcmp($a->getId(), $b->getId()));
        $copy = clone $request;
        $copy->setPageToken('');
        $fingerprint = hash('sha256', $copy->serializeToString().$context->principal.$context->tenant);
        $offset = $this->offset($request->getPageToken(), $fingerprint);
        $size = $request->hasPageSize() ? $request->getPageSize() : 50;
        $page = array_slice($tasks, $offset, $size);
        $page = array_map(fn (Task $task): Task => Lifecycle::project($task, $request->hasHistoryLength() ? $request->getHistoryLength() : null, $request->getIncludeArtifacts()), $page);
        return (new ListTasksResponse())->setTasks($page)->setPageSize($size)->setTotalSize(count($tasks))->setNextPageToken($offset + $size < count($tasks) ? $this->token($offset + $size, $fingerprint) : '');
    }
    public function cancelTask(CancelTaskRequest $request, CallContext $context): Task
    {
        $record = $this->repository->update($context, $request->getId(), function (Record $record): void {
            $task = $record->getTask() ?? throw new \LogicException('Missing task');
            if (Lifecycle::terminal(Lifecycle::state($task))) {
                throw new ProtocolException(ErrorCode::TaskNotCancelable, 'Task is already terminal');
            }
            $task->setStatus(Lifecycle::status(TaskState::TASK_STATE_CANCELED));
            $record->clearPending();
            $record->setLeaseToken('')->setLeaseUntil(0);
            $this->processor->recordEvent($record, (new StreamResponse())->setStatusUpdate((new TaskStatusUpdateEvent())->setTaskId($task->getId())->setContextId($task->getContextId())->setStatus($task->getStatus() ?? throw new \LogicException('Missing status'))));
            $this->push->enqueue($record, $task);
        });
        return $record->getTask() ?? throw new \LogicException('Missing task');
    }
    public function subscribeToTask(SubscribeToTaskRequest $request, CallContext $context): iterable
    {
        $this->streaming();
        $record = $this->repository->get($context, $request->getId());
        $task = $record->getTask() ?? throw new \LogicException('Missing task');
        if (Lifecycle::terminal(Lifecycle::state($task))) {
            throw new ProtocolException(ErrorCode::UnsupportedOperation, 'Cannot subscribe to a terminal task');
        }
        return (function () use ($record, $request, $context, $task): iterable {
            yield (new StreamResponse())->setTask(Lifecycle::project($task, null));
            $sequence = (int) $record->getEventSequence();
            $deadline = $context->deadline ?? microtime(true) + $this->options->requestTimeoutSeconds;
            while (!Lifecycle::terminal(Lifecycle::state($task))) {
                $context->check();
                if (microtime(true) >= $deadline) {
                    throw new ProtocolException(ErrorCode::DeadlineExceeded, 'Subscription deadline exceeded');
                }
                usleep((int) ($this->options->pollIntervalSeconds * 1000000));
                $current = $this->repository->get($context, $request->getId());
                $newSequence = (int) $current->getEventSequence();
                if ($newSequence !== $sequence) {
                    $task = $current->getTask() ?? throw new \LogicException('Missing task');
                    $events = iterator_to_array($current->getEvents());
                    $delta = $newSequence - $sequence;
                    if ($delta > count($events)) {
                        throw new ProtocolException(ErrorCode::ResourceExhausted, 'Subscriber fell behind retained events; subscribe again for a fresh snapshot');
                    }
                    foreach (array_slice($events, -$delta) as $event) {
                        yield $event;
                    }
                    $sequence = $newSequence;
                }
            }
        })();
    }
    public function createTaskPushNotificationConfig(TaskPushNotificationConfig $request, CallContext $context): TaskPushNotificationConfig
    {
        $this->pushSupported();
        $this->push->validate($request);
        $config = clone $request;
        if ($config->getId() === '') {
            $config->setId(Uuid::v7()->toRfc4122());
        }
        $this->repository->update($context, $request->getTaskId(), function (Record $record) use ($config): void {
            $configs = iterator_to_array($record->getConfigs());
            foreach ($configs as $index => $existing) {
                if ($existing->getId() === $config->getId()) {
                    $configs[$index] = $config;
                    $record->setConfigs($configs);
                    return;
                }
            }
            if (count($configs) >= $this->options->maxPushConfigsPerTask) {
                throw new ProtocolException(ErrorCode::ResourceExhausted, 'Too many push configurations');
            }
            $configs[] = $config;
            $record->setConfigs($configs);
            $this->push->assertCapacity($record);
        });
        return $config;
    }
    public function getTaskPushNotificationConfig(GetTaskPushNotificationConfigRequest $request, CallContext $context): TaskPushNotificationConfig
    {
        $this->pushSupported();
        foreach ($this->repository->get($context, $request->getTaskId())->getConfigs() as $config) {
            if ($config->getId() === $request->getId()) {
                return $config;
            }
        }
        throw new ProtocolException(ErrorCode::TaskNotFound, 'Push configuration not found');
    }
    public function listTaskPushNotificationConfigs(ListTaskPushNotificationConfigsRequest $request, CallContext $context): ListTaskPushNotificationConfigsResponse
    {
        $this->pushSupported();
        $configs = iterator_to_array($this->repository->get($context, $request->getTaskId())->getConfigs());
        $size = $request->getPageSize() ?: 50;
        if ($size < 1 || $size > 100) {
            throw ProtocolException::invalid('pageSize must be between 1 and 100');
        }
        $fingerprint = hash('sha256', $request->getTaskId().$context->principal.$context->tenant);
        $offset = $this->offset($request->getPageToken(), $fingerprint);
        return (new ListTaskPushNotificationConfigsResponse())->setConfigs(array_slice($configs, $offset, $size))->setNextPageToken($offset + $size < count($configs) ? $this->token($offset + $size, $fingerprint) : '');
    }
    public function deleteTaskPushNotificationConfig(DeleteTaskPushNotificationConfigRequest $request, CallContext $context): GPBEmpty
    {
        $this->pushSupported();
        $this->repository->update($context, $request->getTaskId(), static function (Record $record) use ($request): void {
            $configs = iterator_to_array($record->getConfigs());
            $remaining = array_values(array_filter($configs, static fn (TaskPushNotificationConfig $config): bool => $config->getId() !== $request->getId()));
            $record->setConfigs($remaining);
            $record->setOutbox(array_values(array_filter(iterator_to_array($record->getOutbox()), static fn ($delivery): bool => $delivery->getConfig()?->getId() !== $request->getId())));
        });
        return new GPBEmpty();
    }
    public function getExtendedAgentCard(GetExtendedAgentCardRequest $request, CallContext $context): AgentCard
    {
        if ($this->extendedCard === null || !$this->card->getCapabilities()?->getExtendedAgentCard()) {
            throw new ProtocolException(ErrorCode::ExtendedCardNotConfigured, 'Extended agent card is not configured');
        }
        return clone $this->extendedCard;
    }
    private function validateInput(SendMessageRequest $request): void
    {
        (new Validator())->validate($request);
        $message = $request->getMessage() ?? throw ProtocolException::invalid('Message required');
        if ($message->getRole() !== Role::ROLE_USER) {
            throw ProtocolException::invalid('Client message role must be ROLE_USER');
        }
        $config = $request->getConfiguration()?->getTaskPushNotificationConfig();
        if ($config !== null) {
            $this->pushSupported();
            $this->push->validate($config);
        }
        foreach ($message->getParts() as $part) {
            $mediaType = $part->getMediaType() ?: ($part->getContent() === 'text' ? 'text/plain' : 'application/octet-stream');
            $supported = iterator_to_array($this->card->getDefaultInputModes());
            if (!in_array($mediaType, $supported, true) && !in_array('*/*', $supported, true)) {
                throw new ProtocolException(ErrorCode::ContentTypeNotSupported, 'Unsupported input media type');
            }
        }
    }
    private function prepare(SendMessageRequest $request, CallContext $context): Record
    {
        $message = $request->getMessage() ?? throw ProtocolException::invalid('Message required');
        $config = $request->getConfiguration()?->getTaskPushNotificationConfig();
        $id = $message->getTaskId();
        if ($id === '') {
            $id = Uuid::v7()->toRfc4122();
            $task = (new Task())->setId($id)->setContextId($message->getContextId() ?: Uuid::v7()->toRfc4122())->setStatus(Lifecycle::status(TaskState::TASK_STATE_SUBMITTED));
            $record = (new Record())->setTask($task)->setPrincipal($context->principal)->setTenant($context->tenant)->setExtensions($context->extensions);
            $this->attach($record, $request, $config);
            $this->repository->create($record);
            return $record;
        }
        return $this->repository->update($context, $id, function (Record $record) use ($request, $config, $message, $context): void {
            $task = $record->getTask() ?? throw new \LogicException('Missing task');
            if (Lifecycle::terminal(Lifecycle::state($task)) || $record->hasPending()) {
                throw new ProtocolException(ErrorCode::UnsupportedOperation, 'Task cannot accept a message in its current state');
            }
            if ($message->getContextId() !== '' && $message->getContextId() !== $task->getContextId()) {
                throw ProtocolException::invalid('Context ID does not match task');
            }
            $this->attach($record, $request, $config);
            $record->setExtensions($context->extensions);
        });
    }
    private function attach(Record $record, SendMessageRequest $request, ?TaskPushNotificationConfig $config): void
    {
        $task = $record->getTask() ?? throw new \LogicException('Missing task');
        $message = clone ($request->getMessage() ?? throw new \LogicException('Missing message'));
        $message->setTaskId($task->getId())->setContextId($task->getContextId());
        $history = iterator_to_array($task->getHistory());
        $history[] = $message;
        $task->setHistory(array_slice($history, -$this->options->maxHistoryMessages));
        $copy = clone $request;
        $copy->setMessage($message);
        $record->setPending($copy);
        $record->setLeaseUntil($request->getConfiguration()?->getReturnImmediately() ? 0 : microtime(true) + $this->options->workerLeaseSeconds);
        if ($config !== null) {
            if ($config->getTaskId() !== '' && $config->getTaskId() !== $task->getId()) {
                throw ProtocolException::invalid('Push configuration references another task');
            }
            $config = clone $config;
            $config->setTaskId($task->getId())->setTenant($record->getTenant());
            $config->setId($config->getId() ?: Uuid::v7()->toRfc4122());
            $configs = iterator_to_array($record->getConfigs());
            $configs = array_values(array_filter($configs, static fn (TaskPushNotificationConfig $existing): bool => $existing->getId() !== $config->getId()));
            if (count($configs) >= $this->options->maxPushConfigsPerTask) {
                throw new ProtocolException(ErrorCode::ResourceExhausted, 'Too many push configurations');
            }
            $configs[] = $config;
            $record->setConfigs($configs);
            $this->push->assertCapacity($record);
        }
    }
    private function streaming(): void
    {
        if (!$this->card->getCapabilities()?->getStreaming()) {
            throw new ProtocolException(ErrorCode::UnsupportedOperation, 'Streaming is disabled');
        }
    }
    private function pushSupported(): void
    {
        if (!$this->card->getCapabilities()?->getPushNotifications()) {
            throw new ProtocolException(ErrorCode::PushNotSupported, 'Push notifications are disabled');
        }
    }
    private function offset(string $token, string $fingerprint): int
    {
        if ($token === '') {
            return 0;
        }
        $decoded = base64_decode(strtr($token, '-_', '+/'), true);
        if ($decoded === false) {
            throw ProtocolException::invalid('Invalid page token');
        }
        $value = Json::object($decoded);
        if (!is_int($value->offset ?? null) || $value->offset < 0 || ($value->filter ?? null) !== $fingerprint) {
            throw ProtocolException::invalid('Page token does not match request');
        }
        return $value->offset;
    }
    private function token(int $offset, string $fingerprint): string
    {
        return rtrim(strtr(base64_encode(Json::encode((object) ['offset' => $offset, 'filter' => $fingerprint])), '+/', '-_'), '=');
    }
}
