<?php

declare(strict_types=1);

namespace A2A\Client;

use A2A\Protocol\Operation;
use A2A\Transport\Transport;

final readonly class Client
{
    public function __construct(private Transport $transport, private CallOptions $options = new CallOptions())
    {
    }
    public function sendMessage(\Lf\A2a\V1\SendMessageRequest $request): \Lf\A2a\V1\SendMessageResponse
    {
        $response = $this->transport->call(Operation::SendMessage, $request, $this->options);
        if (!$response instanceof \Lf\A2a\V1\SendMessageResponse) {
            throw new \UnexpectedValueException('Invalid response type for SendMessage');
        }
        return $response;
    }
    /** @return iterable<\Lf\A2a\V1\StreamResponse> */
    public function sendStreamingMessage(\Lf\A2a\V1\SendMessageRequest $request): iterable
    {
        return $this->transport->stream(Operation::SendStreamingMessage, $request, $this->options);
    }
    public function getTask(\Lf\A2a\V1\GetTaskRequest $request): \Lf\A2a\V1\Task
    {
        $response = $this->transport->call(Operation::GetTask, $request, $this->options);
        if (!$response instanceof \Lf\A2a\V1\Task) {
            throw new \UnexpectedValueException('Invalid response type for GetTask');
        }
        return $response;
    }
    public function listTasks(\Lf\A2a\V1\ListTasksRequest $request): \Lf\A2a\V1\ListTasksResponse
    {
        $response = $this->transport->call(Operation::ListTasks, $request, $this->options);
        if (!$response instanceof \Lf\A2a\V1\ListTasksResponse) {
            throw new \UnexpectedValueException('Invalid response type for ListTasks');
        }
        return $response;
    }
    public function cancelTask(\Lf\A2a\V1\CancelTaskRequest $request): \Lf\A2a\V1\Task
    {
        $response = $this->transport->call(Operation::CancelTask, $request, $this->options);
        if (!$response instanceof \Lf\A2a\V1\Task) {
            throw new \UnexpectedValueException('Invalid response type for CancelTask');
        }
        return $response;
    }
    /** @return iterable<\Lf\A2a\V1\StreamResponse> */
    public function subscribeToTask(\Lf\A2a\V1\SubscribeToTaskRequest $request): iterable
    {
        return $this->transport->stream(Operation::SubscribeToTask, $request, $this->options);
    }
    public function createTaskPushNotificationConfig(\Lf\A2a\V1\TaskPushNotificationConfig $request): \Lf\A2a\V1\TaskPushNotificationConfig
    {
        $response = $this->transport->call(Operation::CreateTaskPushNotificationConfig, $request, $this->options);
        if (!$response instanceof \Lf\A2a\V1\TaskPushNotificationConfig) {
            throw new \UnexpectedValueException('Invalid response type for CreateTaskPushNotificationConfig');
        }
        return $response;
    }
    public function getTaskPushNotificationConfig(\Lf\A2a\V1\GetTaskPushNotificationConfigRequest $request): \Lf\A2a\V1\TaskPushNotificationConfig
    {
        $response = $this->transport->call(Operation::GetTaskPushNotificationConfig, $request, $this->options);
        if (!$response instanceof \Lf\A2a\V1\TaskPushNotificationConfig) {
            throw new \UnexpectedValueException('Invalid response type for GetTaskPushNotificationConfig');
        }
        return $response;
    }
    public function listTaskPushNotificationConfigs(\Lf\A2a\V1\ListTaskPushNotificationConfigsRequest $request): \Lf\A2a\V1\ListTaskPushNotificationConfigsResponse
    {
        $response = $this->transport->call(Operation::ListTaskPushNotificationConfigs, $request, $this->options);
        if (!$response instanceof \Lf\A2a\V1\ListTaskPushNotificationConfigsResponse) {
            throw new \UnexpectedValueException('Invalid response type for ListTaskPushNotificationConfigs');
        }
        return $response;
    }
    public function deleteTaskPushNotificationConfig(\Lf\A2a\V1\DeleteTaskPushNotificationConfigRequest $request): \Google\Protobuf\GPBEmpty
    {
        $response = $this->transport->call(Operation::DeleteTaskPushNotificationConfig, $request, $this->options);
        if (!$response instanceof \Google\Protobuf\GPBEmpty) {
            throw new \UnexpectedValueException('Invalid response type for DeleteTaskPushNotificationConfig');
        }
        return $response;
    }
    public function getExtendedAgentCard(\Lf\A2a\V1\GetExtendedAgentCardRequest $request): \Lf\A2a\V1\AgentCard
    {
        $response = $this->transport->call(Operation::GetExtendedAgentCard, $request, $this->options);
        if (!$response instanceof \Lf\A2a\V1\AgentCard) {
            throw new \UnexpectedValueException('Invalid response type for GetExtendedAgentCard');
        }
        return $response;
    }
}
