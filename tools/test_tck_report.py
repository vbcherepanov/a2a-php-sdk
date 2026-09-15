import copy
import importlib.util
import json
from pathlib import Path
import unittest
import xml.etree.ElementTree as ET


spec = importlib.util.spec_from_file_location("tck_report", Path(__file__).with_name("check-tck-report.py"))
module = importlib.util.module_from_spec(spec)
spec.loader.exec_module(module)


class TckReportTest(unittest.TestCase):
    def setUp(self):
        self.expectations = json.loads(Path(__file__).with_name("tck-expectations.json").read_text())
        self.root = ET.Element("testsuite")
        for item in self.expectations["skipped"]:
            case = ET.SubElement(self.root, "testcase", classname=item["classname"], name=item["name"])
            ET.SubElement(case, "skipped", message=item["reason"])
        for item in self.expectations["failed"]:
            case = ET.SubElement(self.root, "testcase", **item)
            ET.SubElement(case, "failure").text = "Operation failed: Unsupported input media type"
        while len(self.root) < self.expectations["tests"]:
            ET.SubElement(self.root, "testcase", classname="passing", name=str(len(self.root)))

    def test_documented_baseline_is_accepted(self):
        module.validate_report(self.root, self.expectations, "official")

    def test_empty_report_is_rejected(self):
        with self.assertRaises(ValueError):
            module.validate_report(ET.Element("testsuite"), self.expectations, "official")

    def test_unexpected_failure_is_rejected(self):
        ET.SubElement(self.root[-1], "failure").text = "New regression"
        with self.assertRaises(ValueError):
            module.validate_report(self.root, self.expectations, "official")

    def test_changed_skip_is_rejected(self):
        self.root[0].find("skipped").set("message", "Connection unavailable")
        with self.assertRaises(ValueError):
            module.validate_report(self.root, self.expectations, "official")

    def test_duplicate_test_is_rejected(self):
        self.root[-1] = copy.deepcopy(self.root[-2])
        with self.assertRaises(ValueError):
            module.validate_report(self.root, self.expectations, "official")

    def test_diagnostic_report_must_have_no_failures(self):
        with self.assertRaises(ValueError):
            module.validate_report(self.root, self.expectations, "diagnostic")
        for case in self.root:
            failure = case.find("failure")
            if failure is not None:
                case.remove(failure)
        module.validate_report(self.root, self.expectations, "diagnostic")


if __name__ == "__main__":
    unittest.main()
