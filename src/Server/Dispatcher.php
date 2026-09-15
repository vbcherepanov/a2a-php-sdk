<?php

declare(strict_types=1);

namespace A2A\Server;

use A2A\Protocol\Operation;
use A2A\Protocol\Validator;
use A2A\Protocol\ProtocolException;
use A2A\Security\CallContext;
use Google\Protobuf\Internal\Message;

final readonly class Dispatcher
{
    public function __construct(private AgentService $service, private Validator $validator)
    {
    }
    /** @return Message|iterable<\Lf\A2a\V1\StreamResponse> */
    public function dispatch(Operation $operation, Message $request, CallContext $context): Message|iterable
    {
        $expected = $operation->requestClass();
        if (!$request instanceof $expected) {
            throw ProtocolException::invalid('Unexpected request type');
        }
        $this->validator->validate($request);
        return match (true) {
            $operation === Operation::SendMessage && $request instanceof \Lf\A2a\V1\SendMessageRequest => $this->service->sendMessage($request, $context),
            $operation === Operation::SendStreamingMessage && $request instanceof \Lf\A2a\V1\SendMessageRequest => $this->service->sendStreamingMessage($request, $context),
            $operation === Operation::GetTask && $request instanceof \Lf\A2a\V1\GetTaskRequest => $this->service->getTask($request, $context),
            $operation === Operation::ListTasks && $request instanceof \Lf\A2a\V1\ListTasksRequest => $this->service->listTasks($request, $context),
            $operation === Operation::CancelTask && $request instanceof \Lf\A2a\V1\CancelTaskRequest => $this->service->cancelTask($request, $context),
            $operation === Operation::SubscribeToTask && $request instanceof \Lf\A2a\V1\SubscribeToTaskRequest => $this->service->subscribeToTask($request, $context),
            $operation === Operation::CreateTaskPushNotificationConfig && $request instanceof \Lf\A2a\V1\TaskPushNotificationConfig => $this->service->createTaskPushNotificationConfig($request, $context),
            $operation === Operation::GetTaskPushNotificationConfig && $request instanceof \Lf\A2a\V1\GetTaskPushNotificationConfigRequest => $this->service->getTaskPushNotificationConfig($request, $context),
            $operation === Operation::ListTaskPushNotificationConfigs && $request instanceof \Lf\A2a\V1\ListTaskPushNotificationConfigsRequest => $this->service->listTaskPushNotificationConfigs($request, $context),
            $operation === Operation::DeleteTaskPushNotificationConfig && $request instanceof \Lf\A2a\V1\DeleteTaskPushNotificationConfigRequest => $this->service->deleteTaskPushNotificationConfig($request, $context),
            $operation === Operation::GetExtendedAgentCard && $request instanceof \Lf\A2a\V1\GetExtendedAgentCardRequest => $this->service->getExtendedAgentCard($request, $context),
            default => throw ProtocolException::invalid('Unsupported request'),
        };
    }
}
