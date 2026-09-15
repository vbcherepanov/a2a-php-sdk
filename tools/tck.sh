#!/bin/sh
set -eu
root_dir=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
sut_image=${SDK_IMAGE:-a2a-php-sdk-dev:local}
runner_image=${TCK_IMAGE:-a2a-php-sdk-tck:local}
report_dir="$root_dir/docs/tck"
diagnostic=false
revision=''
case "${1:-}" in
    --diagnostic-core-send-003)
        diagnostic=true
        report_dir="$root_dir/docs/tck-diagnostic"
        shift
        ;;
    --revision)
        revision=${2:-}
        case "$revision" in
            ''|*[!0-9a-f]*) printf '%s\n' 'Revision must be a full lowercase commit SHA' >&2; exit 2 ;;
        esac
        if [ "${#revision}" -ne 40 ]; then
            printf '%s\n' 'Revision must contain 40 hexadecimal characters' >&2
            exit 2
        fi
        report_dir="$root_dir/docs/tck-revisions/$revision"
        runner_image=${TCK_IMAGE:-a2a-php-sdk-tck:$revision}
        shift 2
        ;;
esac
report_dir=${TCK_REPORT_DIR:-$report_dir}
sut_name="a2a-tck-sut-$$"
if [ -n "$revision" ]; then
    docker build --build-arg "TCK_REVISION=$revision" -t "$runner_image" -f "$root_dir/tools/tck.Dockerfile" "$root_dir"
else
    docker build -t "$runner_image" -f "$root_dir/tools/tck.Dockerfile" "$root_dir"
fi
mkdir -p "$report_dir"
docker run --rm -d --name "$sut_name" -v "$root_dir:/app" -w /app "$sut_image" sh tests/Fixtures/tck-start.sh
trap 'docker stop "$sut_name" >/dev/null' EXIT INT TERM
attempt=0
until docker exec "$sut_name" php -r 'exit(@file_get_contents("http://127.0.0.1:9999/.well-known/agent-card.json") === false ? 1 : 0);'; do
    attempt=$((attempt + 1))
    if [ "$attempt" -ge 30 ]; then
        docker logs "$sut_name"
        exit 1
    fi
    sleep 1
done
if [ "$diagnostic" = true ]; then
    docker run --rm --network "container:$sut_name" -v "$report_dir:/tck/reports" \
        -v "$root_dir/tools:/diagnostic-tools:ro" --entrypoint uv "$runner_image" \
        run --frozen --no-sync python /diagnostic-tools/tck-diagnostic.py "$@"
else
    docker run --rm --network "container:$sut_name" -v "$report_dir:/tck/reports" "$runner_image" "$@"
fi
