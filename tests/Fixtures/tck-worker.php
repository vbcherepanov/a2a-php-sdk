<?php

declare(strict_types=1);
$app = require __DIR__.'/tck-bootstrap.php';
while (true) {
    $app->processor->workOnce();
    usleep(10000);
}
