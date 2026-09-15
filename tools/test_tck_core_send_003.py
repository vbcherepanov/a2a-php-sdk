import sys
import unittest
from types import SimpleNamespace

from tck.requirements.base import CONTENT_TYPE_NOT_SUPPORTED_ERROR
from tck.requirements.core_operations import CORE_OPERATIONS_REQUIREMENTS
from tests.compatibility.core_operations.test_requirements import _validate_response


class CoreSend003Test(unittest.TestCase):
    def test_response_validation(self):
        requirement = next(r for r in CORE_OPERATIONS_REQUIREMENTS if r.id == "CORE-SEND-003")
        for transport in ("jsonrpc", "http_json", "grpc"):
            code = CONTENT_TYPE_NOT_SUPPORTED_ERROR.expected_code(transport)
            self.assertIsNotNone(code)
            for success, actual_code, valid in (
                (False, code, True),
                (False, "incorrect-code", False),
                (True, None, False),
            ):
                with self.subTest(transport=transport, success=success, code=actual_code):
                    response = SimpleNamespace(
                        success=success,
                        error_code=actual_code,
                        error="Unsupported media",
                        raw_response={},
                    )
                    errors = _validate_response(response, transport, requirement, {})
                    self.assertEqual(not errors, valid, errors)


if __name__ == "__main__":
    unittest.main(argv=[sys.argv[0]], verbosity=2)
