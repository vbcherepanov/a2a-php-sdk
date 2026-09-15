<?php

declare(strict_types=1);
$app = require __DIR__.'/tck-bootstrap.php';
$response = $app->endpoint->handle(new A2A\Transport\Http\Request($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'], file_get_contents('php://input'), getallheaders()));
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
