"""Unit tests for PHP finalize parity compare helpers."""

from __future__ import annotations

import unittest
from datetime import date
from decimal import Decimal

from scripts.amiga.verify_php_finalize_parity import _values_equal


class PhpFinalizeParityTests(unittest.TestCase):
    def test_values_equal_dates(self) -> None:
        self.assertTrue(_values_equal(date(2025, 9, 20), "2025-09-20"))

    def test_values_equal_decimals(self) -> None:
        self.assertTrue(_values_equal(Decimal("1500.5"), 1500.5))

    def test_values_equal_float_tolerance(self) -> None:
        self.assertTrue(_values_equal(1.0000001, 1.0))


if __name__ == "__main__":
    unittest.main()
