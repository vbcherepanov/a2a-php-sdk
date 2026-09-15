<?php

declare(strict_types=1);
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = new A2A\Tests\Fixtures\Application(getenv('A2A_TEST_STORAGE'));
$request = new A2A\Transport\Http\Request($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'], file_get_contents('php://input'), getallheaders());
$response = $app->endpoint->handle($request);
http_response_code($response->status);
foreach ($response->headers as $key => $value) {
    header($key.': '.$value);
}
if (is_string($response->body)) {
    echo $response->body;
} else {
    foreach ($response->body as $chunk) {
        echo $chunk;
        flush();
    }
}
