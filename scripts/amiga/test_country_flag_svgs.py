"""Tests for Amiga country flag SVG sync."""

from __future__ import annotations

import unittest
from pathlib import Path

from scripts.amiga.country_registry import choosable_flag_codes
from scripts.amiga.sync_country_flag_svgs import REPO_ROOT, SITE_FLAGS, VENDOR_4X3, sync_flags

SITE_REL = Path("site") / "public_html" / "img" / "flags" / "amiga"


class CountryFlagSvgTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls) -> None:
        sync_flags()

    def test_vendor_tree_present(self) -> None:
        self.assertTrue(VENDOR_4X3.is_dir(), f"Missing {VENDOR_4X3}")
        self.assertTrue((VENDOR_4X3 / "dk.svg").is_file())

    def test_sync_dry_run_covers_choosable_codes(self) -> None:
        copied = sync_flags(dry_run=True)
        self.assertEqual(len(copied), len(choosable_flag_codes()))

    def test_site_flags_after_sync(self) -> None:
        for code in choosable_flag_codes():
            path = SITE_FLAGS / f"{code}.svg"
            self.assertTrue(path.is_file(), f"Missing synced SVG: {SITE_REL / (code + '.svg')}")
            text = path.read_text(encoding="utf-8")
            self.assertIn("flag-icons", text)
            self.assertIn('viewBox="0 0 640 480"', text)

    def test_corpus_codes_present(self) -> None:
        for code in ("dk", "gb-nir", "ae", "hk", "gb-eng", "tw"):
            self.assertTrue((SITE_FLAGS / f"{code}.svg").is_file(), code)


if __name__ == "__main__":
    unittest.main()