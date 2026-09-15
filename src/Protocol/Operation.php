<?php

declare(strict_types=1);

namespace A2A\Protocol;

use Google\Protobuf\Internal\Message;

enum Operation: string
{
    case SendMessage = 'SendMessage';
    case SendStreamingMessage = 'SendStreamingMessage';
    case GetTask = 'GetTask';
    case ListTasks = 'ListTasks';
    case CancelTask = 'CancelTask';
    case SubscribeToTask = 'SubscribeToTask';
    case CreateTaskPushNotificationConfig = 'CreateTaskPushNotificationConfig';
    case GetTaskPushNotificationConfig = 'GetTaskPushNotificationConfig';
    case ListTaskPushNotificationConfigs = 'ListTaskPushNotificationConfigs';
    case DeleteTaskPushNotificationConfig = 'DeleteTaskPushNotificationConfig';
    case GetExtendedAgentCard = 'GetExtendedAgentCard';
    /** @return class-string<Message> */
    public function requestClass(): string
    {
        return match ($this) {
            self::SendMessage => \Lf\A2a\V1\SendMessageRequest::class,
            self::SendStreamingMessage => \Lf\A2a\V1\SendMessageRequest::class,
            self::GetTask => \Lf\A2a\V1\GetTaskRequest::class,
            self::ListTasks => \Lf\A2a\V1\ListTasksRequest::class,
            self::CancelTask => \Lf\A2a\V1\CancelTaskRequest::class,
            self::SubscribeToTask => \Lf\A2a\V1\SubscribeToTaskRequest::class,
            self::CreateTaskPushNotificationConfig => \Lf\A2a\V1\TaskPushNotificationConfig::class,
            self::GetTaskPushNotificationConfig => \Lf\A2a\V1\GetTaskPushNotificationConfigRequest::class,
            self::ListTaskPushNotificationConfigs => \Lf\A2a\V1\ListTaskPushNotificationConfigsRequest::class,
            self::DeleteTaskPushNotificationConfig => \Lf\A2a\V1\DeleteTaskPushNotificationConfigRequest::class,
            self::GetExtendedAgentCard => \Lf\A2a\V1\GetExtendedAgentCardRequest::class,
        };
    }
    /** @return class-string<Message> */
    public function responseClass(): string
    {
        return match ($this) {
            self::SendMessage => \Lf\A2a\V1\SendMessageResponse::class,
            self::SendStreamingMessage => \Lf\A2a\V1\StreamResponse::class,
            self::GetTask => \Lf\A2a\V1\Task::class,
            self::ListTasks => \Lf\A2a\V1\ListTasksResponse::class,
            self::CancelTask => \Lf\A2a\V1\Task::class,
            self::SubscribeToTask => \Lf\A2a\V1\StreamResponse::class,
            self::CreateTaskPushNotificationConfig => \Lf\A2a\V1\TaskPushNotificationConfig::class,
            self::GetTaskPushNotificationConfig => \Lf\A2a\V1\TaskPushNotificationConfig::class,
            self::ListTaskPushNotificationConfigs => \Lf\A2a\V1\ListTaskPushNotificationConfigsResponse::class,
            self::DeleteTaskPushNotificationConfig => \Google\Protobuf\GPBEmpty::class,
            self::GetExtendedAgentCard => \Lf\A2a\V1\AgentCard::class,
        };
    }
    public function streaming(): bool
    {
        return $this === self::SendStreamingMessage || $this === self::SubscribeToTask;
    }
    public function httpMethod(): string
    {
        return match ($this) {
            self::SendMessage => 'POST',
            self::SendStreamingMessage => 'POST',
            self::GetTask => 'GET',
            self::ListTasks => 'GET',
            self::CancelTask => 'POST',
            self::SubscribeToTask => 'GET',
            self::CreateTaskPushNotificationConfig => 'POST',
            self::GetTaskPushNotificationConfig => 'GET',
            self::ListTaskPushNotificationConfigs => 'GET',
            self::DeleteTaskPushNotificationConfig => 'DELETE',
            self::GetExtendedAgentCard => 'GET',
        };
    }
    public function path(): string
    {
        return match ($this) {
            self::SendMessage => '/message:send',
            self::SendStreamingMessage => '/message:stream',
            self::GetTask => '/tasks/{id}',
            self::ListTasks => '/tasks',
            self::CancelTask => '/tasks/{id}:cancel',
            self::SubscribeToTask => '/tasks/{id}:subscribe',
            self::CreateTaskPushNotificationConfig => '/tasks/{taskId}/pushNotificationConfigs',
            self::GetTaskPushNotificationConfig => '/tasks/{taskId}/pushNotificationConfigs/{id}',
            self::ListTaskPushNotificationConfigs => '/tasks/{taskId}/pushNotificationConfigs',
            self::DeleteTaskPushNotificationConfig => '/tasks/{taskId}/pushNotificationConfigs/{id}',
            self::GetExtendedAgentCard => '/extendedAgentCard',
        };
    }
}
