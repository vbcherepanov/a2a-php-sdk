<?php

declare(strict_types=1);
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = new A2A\Tests\Fixtures\Application(getenv('A2A_TEST_STORAGE'));
$runtime = new A2A\Transport\Grpc\OpenSwooleRuntime($app->grpc, new A2A\Transport\Grpc\GrpcServerOptions(enabled: true, port: (int) getenv('A2A_TEST_GRPC_PORT'), workers: 2), $app->options, new Psr\Log\NullLogger());
$runtime->serve();
