#!/bin/sh
set -eu
root_dir=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
mkdir -p "$root_dir/var"
run_dir=$(mktemp -d "$root_dir/var/tck-ci.XXXXXX")
runner_image=${TCK_IMAGE:-a2a-php-sdk-tck:local}
status=0
TCK_REPORT_DIR="$run_dir/official" sh "$root_dir/tools/tck.sh" || status=$?
if [ "$status" -ne 1 ]; then
    printf 'Official TCK exited %s; expected the documented CORE-SEND-003 failure\n' "$status" >&2
    exit 1
fi
docker run --rm --entrypoint python -v "$root_dir:/app:ro" "$runner_image" \
    /app/tools/check-tck-report.py "$run_dir/official/junitreport.xml" official "$root_dir" /app
TCK_REPORT_DIR="$run_dir/diagnostic" sh "$root_dir/tools/tck.sh" --diagnostic-core-send-003
docker run --rm --entrypoint python -v "$root_dir:/app:ro" "$runner_image" \
    /app/tools/check-tck-report.py "$run_dir/diagnostic/junitreport.xml" diagnostic "$root_dir" /app
docker run --rm --entrypoint python -v "$root_dir:/app:ro" "$runner_image" /app/tools/test_tck_report.py
