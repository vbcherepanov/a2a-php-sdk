#!/bin/sh
set -eu
php tests/Fixtures/tck-grpc.php >/tmp/tck-grpc.log 2>&1 &
grpc_pid=$!
php tests/Fixtures/tck-worker.php >/tmp/tck-worker.log 2>&1 &
worker_pid=$!
trap 'kill "$grpc_pid" "$worker_pid"' EXIT INT TERM
PHP_CLI_SERVER_WORKERS=4 php -S 0.0.0.0:9999 tests/Fixtures/tck-http.php
