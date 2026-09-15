# Changelog

## Unreleased

Initial client and server implementation of A2A protocol 1.0.0.

- All 11 operations over JSON-RPC, REST and optional native gRPC.
- SSE and gRPC streaming, task cancellation and subscriptions.
- Typed protobuf messages, protocol validation and transport error mappings.
- Principal and tenant isolation, persistent tasks, worker leases and webhook retries.
- TLS/mTLS, PSR-3 logging and Prometheus metrics.
- Docker tests, package installation checks and a pinned TCK baseline.

The official TCK has a known CORE-SEND-003 defect. See
[the TCK notes](docs/TCK_UPSTREAM.md) for the original results and upstream review.
