<?php

declare(strict_types=1);

namespace A2A\Tests\Fixtures;

use A2A\Security\CallContext;
use A2A\Server\{DirectResponder, Executor, Lifecycle};
use Google\Protobuf\Value;
use Lf\A2a\V1\{Artifact, Message, Part, Role, SendMessageRequest, StreamResponse, Task, TaskArtifactUpdateEvent, TaskState, TaskStatusUpdateEvent};

final class TckExecutor implements Executor, DirectResponder
{
    public function respond(SendMessageRequest $request, CallContext $context): ?Message
    {
        if (!str_starts_with($request->getMessage()?->getMessageId() ?? '', 'tck-message-response')) {
            return null;
        }
        return (new Message())->setMessageId('response-'.bin2hex(random_bytes(8)))->setContextId($request->getMessage()?->getContextId() ?: bin2hex(random_bytes(16)))->setRole(Role::ROLE_AGENT)->setParts([(new Part())->setText('Direct message response')]);
    }
    public function execute(SendMessageRequest $request, Task $task, CallContext $context): iterable
    {
        $id = $request->getMessage()?->getMessageId() ?? '';
        if (str_contains($id, 'artifact') || str_starts_with($id, 'tck-stream-')) {
            $part = (new Part())->setText('Generated text content');
            if (str_contains($id, 'file-url')) {
                $part = (new Part())->setUrl('https://example.com/output.txt')->setFilename('output.txt')->setMediaType('text/plain');
            } elseif (str_contains($id, 'file')) {
                $part = (new Part())->setRaw('Generated file content')->setFilename('output.txt')->setMediaType('text/plain');
            } elseif (str_contains($id, 'data')) {
                $data = new Value();
                $data->mergeFromJsonString('{"key":"value","count":42}');
                $part = (new Part())->setData($data);
            }
            $chunked = str_contains($id, 'chunked');
            if ($chunked) {
                $part->setText('chunk-1 ');
            }
            $artifact = (new Artifact())->setArtifactId('output')->setParts([$part]);
            yield (new StreamResponse())->setArtifactUpdate((new TaskArtifactUpdateEvent())->setTaskId($task->getId())->setContextId($task->getContextId())->setArtifact($artifact)->setLastChunk(!$chunked));
            if ($chunked) {
                yield (new StreamResponse())->setArtifactUpdate((new TaskArtifactUpdateEvent())->setTaskId($task->getId())->setContextId($task->getContextId())->setArtifact((new Artifact())->setArtifactId('output')->setParts([(new Part())->setText('chunk-2')]))->setAppend(true)->setLastChunk(true));
            }
        }
        $state = match (true) {
            str_starts_with($id, 'tck-input-required') => TaskState::TASK_STATE_INPUT_REQUIRED,
            str_starts_with($id, 'tck-reject-task') => TaskState::TASK_STATE_REJECTED,
            default => TaskState::TASK_STATE_COMPLETED,
        };
        $status = Lifecycle::status($state);
        $status->setMessage((new Message())->setMessageId('response-'.$id)->setTaskId($task->getId())->setContextId($task->getContextId())->setRole(Role::ROLE_AGENT)->setParts([(new Part())->setText('Hello from TCK')]));
        yield (new StreamResponse())->setStatusUpdate((new TaskStatusUpdateEvent())->setTaskId($task->getId())->setContextId($task->getContextId())->setStatus($status));
    }
}
