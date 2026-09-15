# Releasing the SDK

The maintainer runs all Git and publication commands. CI only verifies code and
creates downloadable artifacts.

## Before the first release

1. Push the reviewed source to `main` and wait for **CI / SDK checks** to pass.
2. Set the repository description and topics. Suggested description:
   “A2A 1.0 client and server SDK for PHP with JSON-RPC, REST, streaming and optional gRPC.”
   Topics: `a2a`, `php`, `sdk`, `agent-to-agent`, `json-rpc`, `grpc`, `sse`.
3. Protect `main`: require pull requests and the SDK checks status for later changes.
4. Decide the first release version and move the changelog entry out of Unreleased.
5. Create and push the version tag, then wait for the tag's CI run to pass.
6. Create the GitHub release using the package ZIP and SHA256SUMS from that exact
   tag's successful CI run. Include the TCK limitation in the release notes.
7. Submit the repository on Packagist and enable GitHub updates there.
8. Check installation by package name/version from a fresh project before releasing
   the companion Symfony bundle.

## TCK limitation

The unchanged pinned TCK has three known CORE-SEND-003 failures. CI verifies this
exact baseline and separately requires the diagnostic correction to pass. Do not
describe the release as certified by the unchanged official TCK.

When upstream PR #203 is merged, review the new TCK revision, update the pin and
expected results, and rerun the full suite before removing the exception.

## Artifacts

The `composer-package` CI artifact contains a clean source ZIP and its checksum.
It does not contain dependencies or native binaries. Composer installs runtime
dependencies for the consumer. GitHub-generated source archives use `.gitattributes`
to omit development files as well.
