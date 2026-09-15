import json
from pathlib import Path
import sys
import xml.etree.ElementTree as ET


def validate_report(root: ET.Element, expectations: dict, mode: str) -> None:
    if mode not in ("official", "diagnostic"):
        raise ValueError("Unknown TCK report mode")
    cases = list(root.iter("testcase"))
    identities = [(case.get("classname"), case.get("name")) for case in cases]
    if len(cases) != expectations["tests"] or len(set(identities)) != len(cases):
        raise ValueError("TCK report is incomplete or contains duplicate tests")
    if any(case.find("error") is not None for case in cases):
        raise ValueError("TCK reported an execution error")
    skipped = {
        (case.get("classname"), case.get("name"), case.find("skipped").get("message"))
        for case in cases if case.find("skipped") is not None
    }
    expected_skips = {(item["classname"], item["name"], item["reason"]) for item in expectations["skipped"]}
    if skipped != expected_skips:
        raise ValueError("TCK skips differ from the reviewed capability preconditions")
    failures = {(case.get("classname"), case.get("name")) for case in cases if case.find("failure") is not None}
    expected_failures = {(item["classname"], item["name"]) for item in expectations["failed"]} if mode == "official" else set()
    if failures != expected_failures:
        raise ValueError("TCK failures differ from the documented upstream defect")
    for case in cases:
        failure = case.find("failure")
        if failure is not None and "Unsupported input media type" not in (failure.text or ""):
            raise ValueError("CORE-SEND-003 failed for a different reason")


if __name__ == "__main__":
    report, mode, host_root, container_root = sys.argv[1:]
    path = Path(container_root) / Path(report).relative_to(host_root)
    expectations = json.loads(Path(__file__).with_name("tck-expectations.json").read_text())
    validate_report(ET.parse(path).getroot(), expectations, mode)
    print(f"{mode}: expected test count, skips and failures verified")
