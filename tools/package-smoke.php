<?php

declare(strict_types=1);

require $argv[1];

use A2A\Client\Client;
use A2A\Transport\HttpTransport;
use Lf\A2a\V1\{GetTaskRequest, TaskState};
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

if (extension_loaded('grpc') || extension_loaded('openswoole') || class_exists(Grpc\BaseStub::class)) {
    throw new RuntimeException('Package smoke test must run without gRPC dependencies');
}
$http = new MockHttpClient(static function (string $method, string $url, array $options): MockResponse {
    $request = json_decode($options['body'], flags: JSON_THROW_ON_ERROR);
    return new MockResponse(json_encode([
        'jsonrpc' => '2.0',
        'id' => $request->id,
        'result' => ['id' => 'package-check', 'contextId' => 'context', 'status' => ['state' => 'TASK_STATE_COMPLETED']],
    ], JSON_THROW_ON_ERROR));
});
$client = new Client(new HttpTransport($http, 'https://agent.example/a2a/rpc'));
$task = $client->getTask((new GetTaskRequest())->setId('package-check'));
if ($task->getId() !== 'package-check' || $task->getStatus()?->getState() !== TaskState::TASK_STATE_COMPLETED) {
    throw new RuntimeException('Installed SDK returned an invalid task');
}
