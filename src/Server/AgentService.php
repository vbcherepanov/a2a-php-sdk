<?php

declare(strict_types=1);

namespace A2A\Server;

use A2A\Security\CallContext;

interface AgentService
{
    public function sendMessage(\Lf\A2a\V1\SendMessageRequest $request, CallContext $context): \Lf\A2a\V1\SendMessageResponse;
    /** @return iterable<\Lf\A2a\V1\StreamResponse> */
    public function sendStreamingMessage(\Lf\A2a\V1\SendMessageRequest $request, CallContext $context): iterable;
    public function getTask(\Lf\A2a\V1\GetTaskRequest $request, CallContext $context): \Lf\A2a\V1\Task;
    public function listTasks(\Lf\A2a\V1\ListTasksRequest $request, CallContext $context): \Lf\A2a\V1\ListTasksResponse;
    public function cancelTask(\Lf\A2a\V1\CancelTaskRequest $request, CallContext $context): \Lf\A2a\V1\Task;
    /** @return iterable<\Lf\A2a\V1\StreamResponse> */
    public function subscribeToTask(\Lf\A2a\V1\SubscribeToTaskRequest $request, CallContext $context): iterable;
    public function createTaskPushNotificationConfig(\Lf\A2a\V1\TaskPushNotificationConfig $request, CallContext $context): \Lf\A2a\V1\TaskPushNotificationConfig;
    public function getTaskPushNotificationConfig(\Lf\A2a\V1\GetTaskPushNotificationConfigRequest $request, CallContext $context): \Lf\A2a\V1\TaskPushNotificationConfig;
    public function listTaskPushNotificationConfigs(\Lf\A2a\V1\ListTaskPushNotificationConfigsRequest $request, CallContext $context): \Lf\A2a\V1\ListTaskPushNotificationConfigsResponse;
    public function deleteTaskPushNotificationConfig(\Lf\A2a\V1\DeleteTaskPushNotificationConfigRequest $request, CallContext $context): \Google\Protobuf\GPBEmpty;
    public function getExtendedAgentCard(\Lf\A2a\V1\GetExtendedAgentCardRequest $request, CallContext $context): \Lf\A2a\V1\AgentCard;
}
