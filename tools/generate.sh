#!/bin/sh
set -eu
protoc -I proto --php_out=generated proto/a2a.proto proto/storage.proto
php tools/schema.php
sha256sum proto/a2a.proto > proto/SHA256SUMS
