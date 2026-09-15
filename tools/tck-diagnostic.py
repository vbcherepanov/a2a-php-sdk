import difflib
import os
from pathlib import Path
import subprocess
import sys


def regression(report: Path) -> int:
    environment = os.environ.copy()
    environment["PYTHONPATH"] = str(Path.cwd())
    with report.open("w") as output:
        result = subprocess.run(
            [sys.executable, "/diagnostic-tools/test_tck_core_send_003.py"],
            env=environment,
            stdout=output,
            stderr=subprocess.STDOUT,
            check=False,
        )
    return result.returncode


def main() -> int:
    source = Path("tck/requirements/core_operations.py")
    reports = Path("reports")
    original = source.read_text()
    updated = original
    replacements = (
        ("    CANCEL_TASK_BINDING,\n", "    CANCEL_TASK_BINDING,\n    CONTENT_TYPE_NOT_SUPPORTED_ERROR,\n"),
        (
            '        expected_behavior="ContentTypeNotSupportedError returned",\n',
            '        expected_behavior="ContentTypeNotSupportedError returned",\n'
            "        expected_error=CONTENT_TYPE_NOT_SUPPORTED_ERROR,\n",
        ),
    )
    for before, after in replacements:
        if updated.count(before) != 1:
            raise RuntimeError("Pinned TCK source does not match the diagnostic patch")
        updated = updated.replace(before, after, 1)
    if regression(reports / "regression-before.txt") != 1:
        raise RuntimeError("Expected the unmodified TCK regression to fail")
    patch = "".join(difflib.unified_diff(
        original.splitlines(keepends=True), updated.splitlines(keepends=True),
        fromfile=f"a/{source}", tofile=f"b/{source}",
    ))
    (reports / "core-send-003.patch").write_text(patch)
    source.write_text(updated)
    if regression(reports / "regression-after.txt") != 0:
        raise RuntimeError("Patched TCK regression failed; inspect regression-after.txt")
    return subprocess.run(
        [sys.executable, "run_tck.py", "--sut-host", "http://127.0.0.1:9999", *sys.argv[1:]],
        check=False,
    ).returncode


if __name__ == "__main__":
    sys.exit(main())
