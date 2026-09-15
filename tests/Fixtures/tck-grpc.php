<?php

declare(strict_types=1);
$app = require __DIR__.'/tck-bootstrap.php';
(new A2A\Transport\Grpc\OpenSwooleRuntime($app->grpc, new A2A\Transport\Grpc\GrpcServerOptions(enabled: true, workers: 4), $app->options, new Psr\Log\NullLogger()))->serve();
