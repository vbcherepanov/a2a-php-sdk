<?php

declare(strict_types=1);

namespace A2A\Tests;

use A2A\Observability\PrometheusMetrics;
use A2A\Protocol\{ErrorCode, Json, ProtocolException};
use A2A\Security\CallContext;
use A2A\Tests\Fixtures\{Application, TckExecutor};
use Lf\A2a\V1\{SendMessageRequest, Role};
use PHPUnit\Framework\TestCase;

final class ConformanceRegressionTest extends TestCase
{
    public function testDirectMessageAndStreamAreValidated(): void
    {
        $app = new Application(sys_get_temp_dir().'/a2a-direct-'.bin2hex(random_bytes(8)), new TckExecutor());
        $request = Json::message('{"message":{"messageId":"tck-message-response","role":"ROLE_USER","parts":[{"text":"hello"}]}}', SendMessageRequest::class);
        $context = new CallContext('alice');
        self::assertSame('Direct message response', $app->service->sendMessage($request, $context)->getMessage()->getParts()[0]->getText());
        self::assertCount(1, [...$app->service->sendStreamingMessage($request, $context)]);
        $request->getMessage()->setRole(Role::ROLE_AGENT);
        $this->expectException(ProtocolException::class);
        $app->service->sendMessage($request, $context);
    }
    public function testDirectResponsesCannotBypassInputMediaValidation(): void
    {
        $app = new Application(sys_get_temp_dir().'/a2a-direct-media-'.bin2hex(random_bytes(8)), new TckExecutor());
        $request = Json::message('{"message":{"messageId":"tck-message-response","role":"ROLE_USER","parts":[{"raw":"dGNr","mediaType":"application/x-unsupported-tck-type"}]}}', SendMessageRequest::class);
        try {
            $app->service->sendMessage($request, new CallContext('alice'));
            self::fail('Unsupported media was accepted');
        } catch (ProtocolException $error) {
            self::assertSame(ErrorCode::ContentTypeNotSupported, $error->error);
            self::assertSame(415, $error->error->httpStatus());
            self::assertSame(3, $error->error->grpcStatus());
        }
    }
    public function testMetricsPersistAcrossIndependentInstances(): void
    {
        $file = sys_get_temp_dir().'/a2a-metrics-'.bin2hex(random_bytes(8)).'/state.json';
        (new PrometheusMetrics(stateFile: $file))->observe('GetTask', 'success', 0.01);
        (new PrometheusMetrics(stateFile: $file))->observe('GetTask', 'success', 0.02);
        $result = (new PrometheusMetrics(stateFile: $file))->render();
        self::assertStringContainsString('a2a_requests_total{operation="GetTask",outcome="success"} 2', $result);
        self::assertStringContainsString('le="0.05"} 2', $result);
        self::assertStringContainsString('a2a_request_duration_seconds_sum{operation="GetTask",outcome="success"} 0.03', $result);
    }
    public function testOutboxBackpressurePersistsFailureAndReleasesPendingWork(): void
    {
        $app = new Application(sys_get_temp_dir().'/a2a-capacity-'.bin2hex(random_bytes(8)), pushOptions: new \A2A\Server\PushOptions(['example.com'], maxPendingPerTask: 2));
        $request = Json::message('{"message":{"messageId":"capacity","role":"ROLE_USER","parts":[{"text":"hello"}]},"configuration":{"taskPushNotificationConfig":{"url":"https://example.com/hook"}}}', SendMessageRequest::class);
        $task = $app->service->sendMessage($request, new CallContext('alice'))->getTask();
        self::assertSame(\Lf\A2a\V1\TaskState::TASK_STATE_FAILED, $task->getStatus()->getState());
        $record = $app->repository->get(new CallContext('alice'), $task->getId());
        self::assertFalse($record->hasPending());
        self::assertCount(2, $record->getOutbox());
        self::assertSame(\Lf\A2a\V1\TaskState::TASK_STATE_FAILED, $record->getOutbox()[1]->getEvent()->getStatusUpdate()->getStatus()->getState());
    }
}
