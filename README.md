# A2A PHP SDK

[![CI](https://github.com/vbcherepanov/a2a-php-sdk/actions/workflows/ci.yml/badge.svg)](https://github.com/vbcherepanov/a2a-php-sdk/actions/workflows/ci.yml)

Client and server SDK for **A2A protocol 1.0.0**.
Package: vbcherepanov/a2a-php-sdk. PHP 8.4+, no Laravel/Illuminate.

The generated protobuf classes come from the official A2A v1.0.0 a2a.proto.
All 11 operations are implemented across JSON-RPC, HTTP+JSON/REST and gRPC.
SSE and gRPC server streaming share the same task service and validation.

Use this package to call another A2A agent or expose your own executor through A2A.
For a Symfony application, the [Symfony bundle](https://github.com/vbcherepanov/a2a-symfony-bundle)
provides service registration, routes, console workers, Doctrine storage and Messenger integration.

## Requirements and installation

| Use | Requirements |
|---|---|
| HTTP client/server and SSE | PHP 8.4+, `ext-bcmath`, Composer dependencies |
| Native gRPC client | Also `ext-grpc` and `grpc/grpc` |
| Native gRPC server | Also OpenSwoole 26.2+ built with HTTP/2 and OpenSSL |
| Development tests | Docker Engine and Docker Compose v2 |

Once the package is registered on Packagist, install it inside your application's PHP container:

~~~sh
docker compose run --rm php composer require vbcherepanov/a2a-php-sdk
~~~

For the optional native gRPC client, also install `grpc/grpc` with Composer and
enable `ext-grpc` in the PHP image. HTTP installations do not need either gRPC
extension or OpenSwoole. The included Dockerfile is a development/test runtime,
not a production application image.

Before Packagist registration, add this GitHub repository as a Composer VCS
repository and use `dev-main`. A tag provides the package version; do not add a
hardcoded `version` field to `composer.json`.

## Client

Supply `$endpoint`, `$token`, `$messageId` and `$text` from your application.
`$consumer` below is your handler for incoming stream events.

~~~php
use A2A\Client\{CallOptions, Client};
use A2A\Transport\HttpTransport;
use Lf\A2a\V1\{Message, Part, Role, SendMessageRequest};
use Symfony\Component\HttpClient\HttpClient;

$client = new Client(
    new HttpTransport(HttpClient::create(), $endpoint, jsonRpc: true),
    new CallOptions(timeoutSeconds: 30, headers: ['Authorization' => 'Bearer '.$token]),
);
$request = (new SendMessageRequest())->setMessage(
    (new Message())->setMessageId($messageId)->setRole(Role::ROLE_USER)
        ->setParts([(new Part())->setText($text)]),
);
$response = $client->sendMessage($request);
foreach ($client->sendStreamingMessage($request) as $event) {
    $consumer->accept($event);
}
~~~

For REST, use jsonRpc: false and the REST base endpoint.
For gRPC, create a transport with
(new A2A\Transport\Grpc\GrpcClientOptions())->connect($hostAndPort).
Native gRPC requires ext-grpc and grpc/grpc; they are optional for HTTP installations.
TLS is on by default for the gRPC client. Certificate arguments are PEM contents.

For HTTPS with a private CA, pass `cafile` to `HttpClient::create()`. For mTLS,
also pass `local_cert` and `local_pk`. These HTTP options take file paths;
`GrpcClientOptions` takes the corresponding PEM contents. Keep certificate and
hostname verification enabled. On the gRPC server, mTLS requires
`certificateFile`, `privateKeyFile`, `clientCaFile` and `requireClientCertificate: true`.

CallOptions controls request deadlines, maximum message size, headers, extensions
and the A2A-Version value. Default protocol version is 1.0.

A2A\Client\Discovery fetches and validates the well-known AgentCard with bounded
response sizes and private-network protection. Treat descriptions, messages and
artifacts as untrusted application data; schema validation does not make remote
instructions safe to execute. No endpoint is automatically selected from an
untrusted card, and requests are not automatically retried.

### Operations

| Client method | Result |
|---|---|
| `sendMessage()` | `SendMessageResponse`: Task or direct Message |
| `sendStreamingMessage()` | Iterable of `StreamResponse` events |
| `getTask()` | Task |
| `listTasks()` | Paginated task list |
| `cancelTask()` | Updated Task |
| `subscribeToTask()` | Iterable of task events |
| `createTaskPushNotificationConfig()` | Created push configuration |
| `getTaskPushNotificationConfig()` | Push configuration |
| `listTaskPushNotificationConfigs()` | Paginated push configurations |
| `deleteTaskPushNotificationConfig()` | Empty response |
| `getExtendedAgentCard()` | Authenticated AgentCard |

Requests and responses use generated classes under `Lf\A2a\V1`. Both HTTP bindings
and gRPC call the same typed client methods. REST endpoints use the A2A base path;
JSON-RPC endpoints include the RPC path, for example `/a2a/rpc`.

### Errors and retries

Protocol failures throw `A2A\Protocol\ProtocolException`; its `error` property is
an `ErrorCode` enum. HTTP connection and TLS failures can also throw Symfony's
`TransportExceptionInterface`. A task in the `FAILED` state is a valid protocol
response, so inspect the returned task status as well as handling exceptions.

Set a deadline with `CallOptions::timeoutSeconds` and catch failures at your
application boundary. The SDK does not retry client calls automatically: retrying
`sendMessage()` can repeat work. Choose retry behavior around the operation and
your application's deduplication rules.

## Executor

Implement A2A\Server\Executor with typed request/task/context arguments:

~~~php
public function execute(
    \Lf\A2a\V1\SendMessageRequest $request,
    \Lf\A2a\V1\Task $task,
    \A2A\Security\CallContext $context,
): iterable {
    $answer = $this->service->answer($request, $context);
    yield (new \Lf\A2a\V1\StreamResponse())->setArtifactUpdate(
        (new \Lf\A2a\V1\TaskArtifactUpdateEvent())
            ->setTaskId($task->getId())
            ->setContextId($task->getContextId())
            ->setArtifact(
                (new \Lf\A2a\V1\Artifact())->setArtifactId($answer->id)
                    ->setParts([(new \Lf\A2a\V1\Part())->setText($answer->text)]),
            )->setLastChunk(true),
    );
    yield (new \Lf\A2a\V1\StreamResponse())->setStatusUpdate(
        (new \Lf\A2a\V1\TaskStatusUpdateEvent())
            ->setTaskId($task->getId())
            ->setContextId($task->getContextId())
            ->setStatus(\A2A\Server\Lifecycle::status(
                \Lf\A2a\V1\TaskState::TASK_STATE_COMPLETED,
            )),
    );
}
~~~

The processor creates the task, records the user message and emits WORKING.
Executors emit status/artifact updates and must finish in a terminal or interrupted
state. To return a direct Message without creating a task, also implement
A2A\Server\DirectResponder. Input validation applies to both paths.

## Server composition

Construct these components through constructor injection, or use the companion
vbcherepanov/a2a-symfony-bundle:

1. TaskRepository (FileTaskRepository or your implementation), Executor and AgentCard.
2. PushManager with HTTP client, PushOptions, repository and PSR logger.
3. Processor with repository, executor, push manager, logger and ServerOptions.
4. TaskService with repository, processor, push manager, card, options and optional extended card.
5. Dispatcher with TaskService and Validator.
6. Gateway with dispatcher, Authenticator, Metrics, logger, card and options.
7. HTTP Endpoint and/or GrpcEndpoint with that shared gateway.

File storage uses locks and atomic replacement. Records include principal/tenant,
task history, retained streaming events, pending execution leases and webhook outbox.
All server processes must share persistent storage. Workers call Processor::workOnce()
repeatedly to execute returnImmediately requests and deliver pending webhooks.
Webhook delivery is at least once: receivers must tolerate duplicates.

The HTTP adapter accepts typed Request and returns Response; streaming bodies are
iterables of SSE chunks. The Symfony bundle exposes these with StreamedResponse.
gRPC uses OpenSwooleRuntime and GrpcServerOptions(enabled: true). It needs OpenSwoole
26.2+ with HTTP/2; no gRPC server starts unless explicitly enabled and serve() is called.

## Options

| Type | Controls |
|---|---|
| CallOptions | Client deadline, message size, headers, extensions, version |
| ServerOptions | Deadline, polling, worker lease, message/event/history/config limits |
| HttpOptions | Bindings, paths, card cache and optional Last-Modified timestamp |
| GrpcServerOptions | Enable, bind address, workers, connections, streams, TLS/mTLS files |
| GrpcClientOptions | TLS/mTLS PEM contents and send/receive sizes |
| PushOptions | Host allowlist, HTTPS/private-network policy, retry budget/backoff/outbox limit |

Default gRPC server state is disabled. Default webhooks require HTTPS and an explicitly
allowed public host. Native gRPC client TLS is enabled by default.

BearerAuthenticator maps trusted tokens to principals; custom authenticators implement
the same CallContext contract. Never derive principal identity from unverified input.
Logging is PSR-3; the Symfony integration emits JSON. PrometheusMetrics exposes a
counter and latency histogram; stateFile enables shared persistence across processes.

Execution deadlines are cooperative: executors should check CallContext and bound
their own I/O. In gRPC, sleep hooks allow concurrent HTTP/2 subscriptions; CPU-bound
work and blocking external calls should use background execution. Task handlers must
not keep per-request identities in shared mutable properties.

## Development and verification

All dependency installation and checks run in Docker:

~~~sh
make build
make install
make verify
make package
make tck-check
~~~

The build check regenerates PHP types and the validation schema into a temporary
directory and compares them byte-for-byte. Protocol source SHA-256 is recorded
in proto/SHA256SUMS. Composer lockfiles pin development dependencies.

`make test` covers TLS and mTLS for JSON-RPC, REST and gRPC: ordinary requests,
streaming, an untrusted CA, a wrong server name, and missing or untrusted client
certificates. Certificates are generated inside each test container. These checks
exercise local sockets; they do not validate an external load balancer or certificate
renewal in a deployed environment.

make tck builds a runner from pinned official a2a-tck 1.0.0 sources, starts an isolated
fixture, executes all three transports, writes docs/tck reports and cleans up its
server container. Fixture behavior is only in tests/ and never part of production dispatch.

The unchanged official suite currently reports **244 passed, 3 failed, 18 skipped**.
The three failures are CORE-SEND-003 on each transport: the TCK requirement omits its
expected-error binding. SDK responses match the required error. See
[the diagnosis](docs/TCK_UPSTREAM.md). Fresh reports are attached to
[CI runs](https://github.com/vbcherepanov/a2a-php-sdk/actions/workflows/ci.yml).
The 18 skips concern incompatible capability preconditions and empty SHOULD parameter sets.
This is not a claim of full TCK certification.

### What CI checks

Every pull request, push to `main` and version tag runs the Docker checks:

- Locked dependency installation, Composer validation and vulnerability audit.
- Unit and network tests, including TLS/mTLS and streaming.
- PHPStan level 8, PSR-12 and byte-for-byte regeneration of protocol types/schema.
- A ZIP archive installed into a fresh consumer project, tested without native
  gRPC extensions or the `grpc/grpc` package.
- The unchanged official TCK baseline, followed by the isolated two-line correction.

`make tck-check` requires exactly the reviewed 265 tests, the same 18 capability
skips and only the three documented CORE-SEND-003 failures in the official run.
The corrected diagnostic run must have no failures. Missing reports, new failures,
different skips or an incomplete run fail CI. Both reports remain available as artifacts.

### Package and release files

`make package` writes `dist/a2a-php-sdk.zip` and `dist/SHA256SUMS`. The package
contains runtime code, generated messages, protocol sources, README, changelog
and license. Tests, development tools, local logs and caches are excluded.

CI prepares artifacts but never pushes an image, creates a release or publishes
to Packagist. Releases are a separate manual step after the tagged commit passes CI.
See [the release checklist](docs/RELEASING.md) and [changelog](CHANGELOG.md).

## Support and license

Report reproducible bugs through [GitHub Issues](https://github.com/vbcherepanov/a2a-php-sdk/issues).
Include the PHP version, binding, package version and a minimal request/response;
remove tokens, credentials and private payloads. Licensed under Apache-2.0; see [LICENSE](LICENSE).

No release has been published. Package release numbering is separate from the A2A
protocol version; development Composer fallback output is not a published release.
