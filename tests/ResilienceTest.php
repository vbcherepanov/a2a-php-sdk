<?php

declare(strict_types=1);

namespace A2A\Tests;

use A2A\Protocol\{ErrorCode, Json, ProtocolException};
use A2A\Security\CallContext;
use A2A\Server\{PushManager, PushOptions};
use A2A\Storage\Proto\Record;
use A2A\Tests\Fixtures\Application;
use A2A\Transport\Http\Request;
use Lf\A2a\V1\{CancelTaskRequest, GetTaskRequest, SendMessageRequest, TaskPushNotificationConfig, TaskState};
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\{MockHttpClient, Response\MockResponse};

final class ResilienceTest extends TestCase
{
    private function request(): SendMessageRequest
    {
        return Json::message('{"message":{"messageId":"job","role":"ROLE_USER","parts":[{"text":"hello"}]},"configuration":{"returnImmediately":true}}', SendMessageRequest::class);
    }
    public function testDisconnectedExecutionRemainsRecoverable(): void
    {
        $directory = sys_get_temp_dir().'/a2a-resilience-'.bin2hex(random_bytes(8));
        $app = new Application($directory);
        $ctx = new CallContext('alice');
        $task = $app->service->sendMessage($this->request(), $ctx)->getTask();
        $run = $app->processor->run($task->getId(), $ctx);
        self::assertTrue($run->current()->hasStatusUpdate());
        unset($run);
        $fresh = new Application($directory);
        self::assertTrue($fresh->repository->get($ctx, $task->getId())->hasPending());
        $fresh->processor->workOnce();
        self::assertSame(TaskState::TASK_STATE_COMPLETED, $fresh->service->getTask((new GetTaskRequest())->setId($task->getId()), $ctx)->getStatus()->getState());
    }
    public function testCancelWinsAgainstActiveExecutor(): void
    {
        $app = new Application(sys_get_temp_dir().'/a2a-cancel-'.bin2hex(random_bytes(8)));
        $ctx = new CallContext('alice');
        $task = $app->service->sendMessage($this->request(), $ctx)->getTask();
        $run = $app->processor->run($task->getId(), $ctx);
        self::assertTrue($run->current()->hasStatusUpdate());
        $app->service->cancelTask((new CancelTaskRequest())->setId($task->getId()), $ctx);
        $run->next();
        self::assertFalse($run->valid());
        self::assertSame(TaskState::TASK_STATE_CANCELED, $app->repository->get($ctx, $task->getId())->getTask()->getStatus()->getState());
    }
    public function testWebhookRetryAndSuccessfulAcknowledgement(): void
    {
        $app = new Application(sys_get_temp_dir().'/a2a-push-'.bin2hex(random_bytes(8)));
        $ctx = new CallContext('alice');
        $task = $app->service->sendMessage($this->request(), $ctx)->getTask();
        $config = (new TaskPushNotificationConfig())->setTaskId($task->getId())->setUrl('https://example.com/hook')->setToken('verification-token');
        $app->service->createTaskPushNotificationConfig($config, $ctx);
        $requests = [];
        $http = new MockHttpClient(function ($method, $url, $options) use (&$requests) {
            $requests[] = [$method, $url, $options];
            return new MockResponse('', ['http_code' => count($requests) === 1 ? 503 : 204]);
        });
        $push = new PushManager($http, new PushOptions(['example.com'], retryDelaySeconds: 0.001), $app->repository, new NullLogger());
        $record = $app->repository->update($ctx, $task->getId(), fn (Record $record) => $push->enqueue($record, $task));
        $push->deliver($record);
        $current = $app->repository->get($ctx, $task->getId());
        self::assertCount(1, $current->getOutbox());
        self::assertSame(1, $current->getOutbox()[0]->getAttempts());
        usleep(10000);
        $push->deliver($current);
        self::assertCount(0, $app->repository->get($ctx, $task->getId())->getOutbox());
        self::assertCount(2, $requests);
        $payload = json_decode($requests[0][2]['body']);
        self::assertSame($task->getId(), $payload->statusUpdate->taskId);
        self::assertSame('TASK_STATE_SUBMITTED', $payload->statusUpdate->status->state);
        self::assertSame(0, $requests[0][2]['max_redirects']);
    }
    public function testWebhookAllowlistBlocksPrivateAndUnlistedTargets(): void
    {
        $app = new Application(sys_get_temp_dir().'/a2a-ssrf-'.bin2hex(random_bytes(8)));
        foreach (['http://example.com/hook', 'https://localhost/hook', 'https://127.0.0.1/hook', 'https://example.com@evil.test/hook', 'https://example.com/hook#fragment'] as $url) {
            try {
                $app->push->validate((new TaskPushNotificationConfig())->setUrl($url));
                self::fail('Unsafe webhook accepted');
            } catch (ProtocolException $error) {
                self::assertSame(ErrorCode::InvalidParams, $error->error);
            }
        }
    }
    public function testProtocolVersionAndMalformedRequestsAndMetrics(): void
    {
        $app = new Application(sys_get_temp_dir().'/a2a-errors-'.bin2hex(random_bytes(8)));
        $headers = ['Content-Type' => 'application/json', 'Authorization' => 'Bearer test-alice-token', 'A2A-Version' => '0.3'];
        $response = $app->endpoint->handle(new Request('POST', '/a2a/rpc', '{"jsonrpc":"2.0","id":1,"method":"ListTasks","params":{}}', $headers));
        self::assertSame(-32009, json_decode($response->body)->error->code);
        $headers['A2A-Version'] = '1.0';
        $malformed = $app->endpoint->handle(new Request('POST', '/a2a/rpc', '{', $headers));
        self::assertSame(-32700, json_decode($malformed->body)->error->code);
        $batch = $app->endpoint->handle(new Request('POST', '/a2a/rpc', '[]', $headers));
        self::assertSame(-32600, json_decode($batch->body)->error->code);
        self::assertStringContainsString('a2a_requests_total{operation="ListTasks",outcome="error"} 1', $app->metrics->render());
        self::assertStringContainsString('a2a_request_duration_seconds_bucket', $app->metrics->render());
    }
}
