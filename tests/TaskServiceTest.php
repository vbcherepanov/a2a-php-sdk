<?php

declare(strict_types=1);

namespace A2A\Tests;

use A2A\Protocol\{ErrorCode, Json, ProtocolException};
use A2A\Security\CallContext;
use A2A\Tests\Fixtures\Application;
use Lf\A2a\V1\{CancelTaskRequest, GetTaskRequest, ListTasksRequest, SendMessageRequest, TaskState};
use PHPUnit\Framework\TestCase;

final class TaskServiceTest extends TestCase
{
    private Application $app;
    protected function setUp(): void
    {
        $this->app = new Application(sys_get_temp_dir().'/a2a-test-'.bin2hex(random_bytes(8)));
    }
    private function request(string $text = 'hello', bool $async = false): SendMessageRequest
    {
        return Json::message(json_encode(['message' => ['messageId' => bin2hex(random_bytes(8)), 'role' => 'ROLE_USER', 'parts' => [['text' => $text]]], 'configuration' => ['returnImmediately' => $async]], JSON_THROW_ON_ERROR), SendMessageRequest::class);
    }
    public function testCompletedTaskAndHistoryProjection(): void
    {
        $ctx = new CallContext('alice');
        $task = $this->app->service->sendMessage($this->request(), $ctx)->getTask();
        self::assertSame(TaskState::TASK_STATE_COMPLETED, $task->getStatus()->getState());
        self::assertSame('hello', $task->getArtifacts()[0]->getParts()[0]->getText());
        self::assertCount(2, $task->getHistory());
        $projected = $this->app->service->getTask((new GetTaskRequest())->setId($task->getId())->setHistoryLength(0), $ctx);
        self::assertCount(0, $projected->getHistory());
        self::assertCount(2, $this->app->service->getTask((new GetTaskRequest())->setId($task->getId()), $ctx)->getHistory());
    }
    public function testAsyncWorkSurvivesServiceReconstruction(): void
    {
        $ctx = new CallContext('alice');
        $task = $this->app->service->sendMessage($this->request(async: true), $ctx)->getTask();
        self::assertSame(TaskState::TASK_STATE_SUBMITTED, $task->getStatus()->getState());
        self::assertGreaterThan(0, $this->app->processor->workOnce());
        self::assertSame(TaskState::TASK_STATE_COMPLETED, $this->app->service->getTask((new GetTaskRequest())->setId($task->getId()), $ctx)->getStatus()->getState());
    }
    public function testOwnerAndTenantIsolation(): void
    {
        $task = $this->app->service->sendMessage($this->request(), new CallContext('alice', 'one'))->getTask();
        foreach ([new CallContext('bob', 'one'), new CallContext('alice', 'two')] as $ctx) {
            try {
                $this->app->service->getTask((new GetTaskRequest())->setId($task->getId()), $ctx);
                self::fail('Unauthorized task access');
            } catch (ProtocolException $error) {
                self::assertSame(ErrorCode::TaskNotFound, $error->error);
            }
            self::assertCount(0, $this->app->service->listTasks(new ListTasksRequest(), $ctx)->getTasks());
        }
    }
    public function testCancelQueuedTaskCannotBeExecuted(): void
    {
        $ctx = new CallContext('alice');
        $task = $this->app->service->sendMessage($this->request(async: true), $ctx)->getTask();
        $cancelled = $this->app->service->cancelTask((new CancelTaskRequest())->setId($task->getId()), $ctx);
        self::assertSame(TaskState::TASK_STATE_CANCELED, $cancelled->getStatus()->getState());
        self::assertSame(0, $this->app->processor->workOnce());
        $this->expectException(ProtocolException::class);
        $this->app->service->cancelTask((new CancelTaskRequest())->setId($task->getId()), $ctx);
    }
    public function testInterruptedTaskContinuesAndTerminalTaskRejectsMessages(): void
    {
        $ctx = new CallContext('alice');
        $task = $this->app->service->sendMessage($this->request('pause'), $ctx)->getTask();
        self::assertSame(TaskState::TASK_STATE_INPUT_REQUIRED, $task->getStatus()->getState());
        $next = $this->request('continue');
        $next->getMessage()->setTaskId($task->getId());
        $done = $this->app->service->sendMessage($next, $ctx)->getTask();
        self::assertSame(TaskState::TASK_STATE_COMPLETED, $done->getStatus()->getState());
        $this->expectException(ProtocolException::class);
        $this->app->service->sendMessage($next, $ctx);
    }
    public function testExceptionBecomesFailedWithoutLeakingDetails(): void
    {
        $task = $this->app->service->sendMessage($this->request('fail'), new CallContext('alice'))->getTask();
        self::assertSame(TaskState::TASK_STATE_FAILED, $task->getStatus()->getState());
        self::assertStringNotContainsString('private execution detail', $task->serializeToJsonString());
    }
    public function testPaginationAndFilterBinding(): void
    {
        $ctx = new CallContext('alice');
        for ($i = 0; $i < 3; ++$i) {
            $this->app->service->sendMessage($this->request(), $ctx);
        }
        $request = (new ListTasksRequest())->setPageSize(2);
        $page = $this->app->service->listTasks($request, $ctx);
        self::assertCount(2, $page->getTasks());
        self::assertSame(3, $page->getTotalSize());
        $request->setPageToken($page->getNextPageToken());
        self::assertCount(1, $this->app->service->listTasks($request, $ctx)->getTasks());
        $request->setContextId('changed');
        $this->expectException(ProtocolException::class);
        $this->app->service->listTasks($request, $ctx);
    }
}
