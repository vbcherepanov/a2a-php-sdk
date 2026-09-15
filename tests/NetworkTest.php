<?php

declare(strict_types=1);

namespace A2A\Tests;

use A2A\Client\{CallOptions, Client, Discovery};
use A2A\Protocol\{ErrorCode, Json, ProtocolException};
use A2A\Transport\HttpTransport;
use A2A\Transport\Grpc\GrpcClientOptions;
use Lf\A2a\V1\{CancelTaskRequest, DeleteTaskPushNotificationConfigRequest, GetExtendedAgentCardRequest, GetTaskPushNotificationConfigRequest, GetTaskRequest, ListTaskPushNotificationConfigsRequest, ListTasksRequest, SendMessageRequest, SubscribeToTaskRequest, TaskPushNotificationConfig, TaskState};
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Process\Process;

final class NetworkTest extends TestCase
{
    private static Process $http;
    private static Process $grpc;
    private static int $httpPort;
    private static int $grpcPort;
    public static function setUpBeforeClass(): void
    {
        self::$httpPort = self::freePort();
        self::$grpcPort = self::freePort();
        $storage = sys_get_temp_dir().'/a2a-network-'.bin2hex(random_bytes(8));
        $env = ['A2A_TEST_STORAGE' => $storage, 'A2A_TEST_GRPC_PORT' => (string) self::$grpcPort];
        self::$http = new Process(['php', '-S', '127.0.0.1:'.self::$httpPort, __DIR__.'/Fixtures/http-server.php'], env: $env);
        self::$grpc = new Process(['php', __DIR__.'/Fixtures/grpc-server.php'], env: $env);
        self::$http->start();
        self::$grpc->start();
        foreach ([self::$httpPort => self::$http, self::$grpcPort => self::$grpc] as $port => $process) {
            $ready = false;
            for ($attempt = 0; $attempt < 100; ++$attempt) {
                if (!$process->isRunning()) {
                    throw new \RuntimeException($process->getErrorOutput().$process->getOutput());
                }
                $socket = @fsockopen('127.0.0.1', $port, $errno, $error, 0.05);
                if (is_resource($socket)) {
                    fclose($socket);
                    $ready = true;
                    break;
                }
                usleep(20000);
            }
            if (!$ready) {
                throw new \RuntimeException('Test server did not start');
            }
        }
    }
    public static function tearDownAfterClass(): void
    {
        if (isset(self::$http)) {
            self::$http->stop();
        }
        if (isset(self::$grpc)) {
            self::$grpc->stop();
        }
    }
    private static function freePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $name = stream_socket_get_name($socket, false);
        fclose($socket);
        return (int) substr($name, strrpos($name, ':') + 1);
    }
    public function testGrpcSubscriptionAllowsConcurrentFollowupOnSameChannel(): void
    {
        $client = $this->client('grpc');
        $request = $this->request();
        $request->getMessage()->getParts()[0]->setText('pause');
        $task = $client->sendMessage($request)->getTask();
        $stream = $client->subscribeToTask((new SubscribeToTaskRequest())->setId($task->getId()));
        $stream->rewind();
        self::assertSame($task->getId(), $stream->current()->getTask()->getId());
        $followup = $this->request();
        $followup->getMessage()->setTaskId($task->getId());
        self::assertSame(TaskState::TASK_STATE_COMPLETED, $client->sendMessage($followup)->getTask()->getStatus()->getState());
        $last = null;
        foreach ($stream as $event) {
            $last = $event;
        }
        self::assertSame(TaskState::TASK_STATE_COMPLETED, $last->getStatusUpdate()->getStatus()->getState());
    }
    public static function bindings(): array
    {
        return [['rpc'], ['rest'], ['grpc']];
    }
    #[DataProvider('bindings')]
    public function testUnsupportedMediaReturnsTheA2aError(string $binding): void
    {
        $request = $this->request();
        $request->getMessage()->getParts()[0]->setMediaType('application/x-unsupported-tck-type');
        try {
            $this->client($binding)->sendMessage($request);
            self::fail('Unsupported media must produce an A2A error');
        } catch (ProtocolException $error) {
            self::assertSame(ErrorCode::ContentTypeNotSupported, $error->error);
        }
    }
    private function client(string $binding, string $token = 'test-alice-token'): Client
    {
        $transport = $binding === 'grpc'
            ? (new GrpcClientOptions(tls: false))->connect('127.0.0.1:'.self::$grpcPort)
            : new HttpTransport(HttpClient::create(), 'http://127.0.0.1:'.self::$httpPort.($binding === 'rpc' ? '/a2a/rpc' : '/a2a'), $binding === 'rpc');
        return new Client($transport, new CallOptions(timeoutSeconds: 5, headers: ['Authorization' => 'Bearer '.$token]));
    }
    private function request(bool $async = false): SendMessageRequest
    {
        return Json::message('{"message":{"messageId":"'.bin2hex(random_bytes(8)).'","role":"ROLE_USER","parts":[{"text":"hello"}]},"configuration":{"returnImmediately":'.($async ? 'true' : 'false').'}}', SendMessageRequest::class);
    }
    #[DataProvider('bindings')]
    public function testAllUnaryOperationsAndIsolation(string $binding): void
    {
        $client = $this->client($binding);
        $task = $client->sendMessage($this->request())->getTask();
        self::assertSame(TaskState::TASK_STATE_COMPLETED, $task->getStatus()->getState());
        $id = $task->getId();
        self::assertCount(0, $client->getTask((new GetTaskRequest())->setId($id)->setHistoryLength(0))->getHistory());
        self::assertGreaterThanOrEqual(1, $client->listTasks(new ListTasksRequest())->getTotalSize());
        self::assertSame('Conformance fixture', $client->getExtendedAgentCard(new GetExtendedAgentCardRequest())->getName());
        $config = $client->createTaskPushNotificationConfig((new TaskPushNotificationConfig())->setTaskId($id)->setUrl('https://example.com/hook'));
        self::assertSame($config->getId(), $client->getTaskPushNotificationConfig((new GetTaskPushNotificationConfigRequest())->setTaskId($id)->setId($config->getId()))->getId());
        self::assertCount(1, $client->listTaskPushNotificationConfigs((new ListTaskPushNotificationConfigsRequest())->setTaskId($id))->getConfigs());
        $client->deleteTaskPushNotificationConfig((new DeleteTaskPushNotificationConfigRequest())->setTaskId($id)->setId($config->getId()));
        $client->deleteTaskPushNotificationConfig((new DeleteTaskPushNotificationConfigRequest())->setTaskId($id)->setId($config->getId()));
        self::assertCount(0, $client->listTaskPushNotificationConfigs((new ListTaskPushNotificationConfigsRequest())->setTaskId($id))->getConfigs());
        $queued = $client->sendMessage($this->request(true))->getTask();
        self::assertSame(TaskState::TASK_STATE_CANCELED, $client->cancelTask((new CancelTaskRequest())->setId($queued->getId()))->getStatus()->getState());
        try {
            $this->client($binding, 'test-bob-token')->getTask((new GetTaskRequest())->setId($id));
            self::fail('Cross-principal disclosure');
        } catch (ProtocolException $error) {
            self::assertSame(ErrorCode::TaskNotFound, $error->error);
        }
    }
    #[DataProvider('bindings')]
    public function testStreamingAndTerminalSubscriptionRejection(string $binding): void
    {
        $client = $this->client($binding);
        $events = iterator_to_array($client->sendStreamingMessage($this->request()));
        self::assertCount(4, $events);
        self::assertTrue($events[0]->hasTask());
        self::assertTrue($events[2]->hasArtifactUpdate());
        self::assertSame(TaskState::TASK_STATE_COMPLETED, $events[3]->getStatusUpdate()->getStatus()->getState());
        try {
            iterator_to_array($client->subscribeToTask((new SubscribeToTaskRequest())->setId($events[0]->getTask()->getId())));
            self::fail('Terminal subscription accepted');
        } catch (ProtocolException $error) {
            self::assertSame(ErrorCode::UnsupportedOperation, $error->error);
        }
    }
    #[DataProvider('bindings')]
    public function testBadCredentialsRejected(string $binding): void
    {
        try {
            $this->client($binding, 'bad')->listTasks(new ListTasksRequest());
            self::fail('Invalid credentials accepted');
        } catch (ProtocolException $error) {
            self::assertSame(ErrorCode::Unauthenticated, $error->error);
        }
    }
    public function testDiscoveryOverHttp(): void
    {
        $card = (new Discovery(HttpClient::create(), allowPrivateNetwork: true))->discover('http://127.0.0.1:'.self::$httpPort);
        self::assertSame('Conformance fixture', $card->getName());
    }
}
