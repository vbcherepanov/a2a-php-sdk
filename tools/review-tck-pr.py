import difflib
import os
from pathlib import Path
import subprocess
import sys


def run_check(arguments: list[str], report: Path) -> int:
    with report.open("w") as output:
        result = subprocess.run(
            [sys.executable, *arguments], stdout=output, stderr=subprocess.STDOUT,
            env={**os.environ, "PYTHONPATH": str(Path.cwd())}, check=False,
        )
    return result.returncode


def main() -> None:
    reports = Path("/reports")
    reports.mkdir(exist_ok=True)
    regression = Path("/review/test_tck_context_id.py")
    if run_check([str(regression)], reports / "regression-before.txt") != 1:
        raise RuntimeError("Expected context-ID regressions on the reviewed PR head")

    replacements = {
        "tck/validators/grpc/payload.py": (
            '    inner = getattr(msg, "task", None) or getattr(msg, "message", None) or msg\n'
            '    actual = getattr(inner, field, None)\n',
            '    payload = msg.WhichOneof("payload")\n'
            '    inner = getattr(msg, payload) if payload is not None else msg\n'
            '    actual = getattr(inner, field, None)\n',
        ),
        "tests/compatibility/core_operations/test_requirements.py": (
            '    if requirement.allows_error and not response.success:\n        return []\n',
            '    if requirement.allows_error and not response.success:\n'
            '        if response.error_code is None:\n'
            '            return ["Operation failed without an A2A error response"]\n'
            '        return []\n',
        ),
    }
    patch = []
    for filename, (before, after) in replacements.items():
        path = Path(filename)
        original = path.read_text()
        if original.count(before) != 1:
            raise RuntimeError(f"Reviewed source no longer matches {filename}")
        updated = original.replace(before, after, 1)
        patch.extend(difflib.unified_diff(
            original.splitlines(keepends=True), updated.splitlines(keepends=True),
            fromfile=f"a/{filename}", tofile=f"b/{filename}",
        ))
        path.write_text(updated)

    test_path = Path("tests/unit/requirements/test_context_id_outcomes.py")
    test_source = regression.read_text()
    if test_path.exists():
        raise RuntimeError("Regression test path already exists")
    patch.extend(difflib.unified_diff(
        [], test_source.splitlines(keepends=True), fromfile="/dev/null", tofile=f"b/{test_path}",
    ))
    test_path.write_text(test_source)
    (reports / "pr-203-followup.patch").write_text("".join(patch))
    if run_check([str(regression)], reports / "regression-after.txt") != 0:
        raise RuntimeError("Context-ID regressions still fail after the patch")
    if run_check(["-m", "pytest", "tests/unit", "-q"], reports / "unit-tests.txt") != 0:
        raise RuntimeError("Upstream unit tests failed; inspect unit-tests.txt")
    if len(sys.argv) > 1:
        if run_check(["run_tck.py", "--sut-host", sys.argv[1]], reports / "compatibility-run.txt") != 0:
            raise RuntimeError("Compatibility tests failed; inspect compatibility-run.txt")


if __name__ == "__main__":
    main()
