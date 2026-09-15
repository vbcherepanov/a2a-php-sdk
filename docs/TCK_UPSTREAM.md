# TCK 1.0 status and upstream defect

Pinned source: a2aproject/a2a-tck commit 263b9cfaf16a554bdfb166a7ba5b67716e946349.
The source reports package version 1.0.0. Official tests are not edited or deselected.

CORE-SEND-003 declares that unsupported media MUST return ContentTypeNotSupportedError.
Its requirement definition in tck/requirements/core_operations.py omits expected_error.
The generic validator in tests/compatibility/core_operations/test_requirements.py consequently
treats the required error as an unexpected operation failure, before checking its code.

The SDK returns -32005 for JSON-RPC, HTTP 415 for REST and INVALID_ARGUMENT with
CONTENT_TYPE_NOT_SUPPORTED ErrorInfo for gRPC. Dedicated transport tests and local
regression tests verify these mappings. Accepting unsupported data to turn this test green
would violate the requirement.

Proposed upstream correction: import CONTENT_TYPE_NOT_SUPPORTED_ERROR from requirements.base
and set expected_error=CONTENT_TYPE_NOT_SUPPORTED_ERROR on CORE-SEND-003.
The user posted the findings to the existing PR on 2026-09-15:
[review comment](https://github.com/a2aproject/a2a-tck/pull/203#issuecomment-5675202054).

On 2026-09-15, upstream main still points to the pinned commit. The defect is
already tracked in [issue #202](https://github.com/a2aproject/a2a-tck/issues/202),
with [PR #203](https://github.com/a2aproject/a2a-tck/pull/203) open and approved.
No duplicate issue is needed.

PR head `0ba80047c2946fdac28f39eea0cc10103dd11b09` passes the PHP SDK compatibility
run: **247 passed / 18 skipped**. Reports are in `docs/tck-revisions/<commit>/`.
Reproduce with:

```sh
sh tools/tck.sh --revision 0ba80047c2946fdac28f39eea0cc10103dd11b09
```

The revision option uses a separate image and report directory. Plain `make tck`
continues to use the pinned official source.

Additional review found two context-ID edge cases in that PR: gRPC Message
responses are read as empty Tasks, and failures without an error code pass as
valid rejections. A follow-up patch and regressions are in `docs/tck-pr-203/`.
The proposed comment is in [TCK_PR_203_COMMENT.md](TCK_PR_203_COMMENT.md).
The comment was posted by the user; the assistant made no external writes.

TCK's gRPC client expects host:port without a URL scheme. Its JSON-RPC HTTP client appends
a trailing slash to the advertised endpoint. The fixture supplies the native gRPC target;
the SDK HTTP endpoint accepts both forms of the configured JSON-RPC path.

TCK summary percentages include requirements that do not have executing tests; use
pytest totals and per-requirement errors rather than interpreting the percentage
as an SDK coverage figure. Skips must be reviewed in the JUnit report.

Run the unchanged suite with make tck. Reports are written to docs/tck/.
A nonzero result due to CORE-SEND-003 is retained, never hidden or converted to success.

## Isolated diagnostic reproduction (2026-09-15)

Run `make tck-diagnostic` to apply the two-line correction only inside a disposable
runner container. The base image and the official `make tck` path stay unmodified.
The launcher fails if the expected source anchors or regression results differ.

Artifacts are written to `docs/tck-diagnostic/`, separately from `docs/tck/`:

- `core-send-003.patch`: unified patch for the pinned upstream revision.
- `regression-before.txt`: the original validator rejects the required error and
  incorrectly accepts success on each of the three transports (six failed subcases).
- `regression-after.txt`: all nine subcases pass, including wrong-code rejection.
- Standard TCK HTML, JSON and JUnit reports for the diagnostically patched suite.

The standalone regression is `tools/test_tck_core_send_003.py`. To reproduce in an
upstream checkout with its dependencies installed, run it with the checkout root
on `PYTHONPATH`, before and after applying `core-send-003.patch` using `patch -p1`.
All project-side execution uses Docker. The PR remains open; no upstream merge is claimed.

Passing the diagnostic suite does not mean that the unchanged official TCK passes.

Verified on 2026-09-15: unchanged suite **244 passed / 3 failed / 18 skipped**;
diagnostically patched suite **247 passed / 0 failed / 18 skipped**. The three
failures are exclusively CORE-SEND-003 (gRPC, JSON-RPC, HTTP+JSON). Unit regression
passes all nine subcases after the patch. No SDK production changes were needed.
