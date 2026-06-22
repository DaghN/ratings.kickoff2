"""Unit tests for L2→L3 boundary manifest lineage checks."""

from __future__ import annotations

import tempfile
import unittest
from pathlib import Path

from scripts.amiga.verify_l2_l3_boundary import verify_manifest_l2_lineage


class TestVerifyL2L3Boundary(unittest.TestCase):
    def test_manifest_lineage_rejects_mdb_layer(self) -> None:
        with tempfile.TemporaryDirectory() as tmp:
            l2 = Path(tmp) / "L2_pruned.sql"
            l2.write_text("-- stub\n", encoding="utf-8")
            manifest = {
                "source": {
                    "path": str(l2.resolve()),
                    "filename": "koatd.mdb",
                }
            }
            errors = verify_manifest_l2_lineage(manifest, l2_sql_path=l2)
            self.assertTrue(any("layer" in e for e in errors))

    def test_manifest_lineage_accepts_l2_path(self) -> None:
        with tempfile.TemporaryDirectory() as tmp:
            l2 = Path(tmp) / "L2_pruned.sql"
            l2.write_text("-- stub\n", encoding="utf-8")
            manifest = {
                "source": {
                    "layer": "L2",
                    "path": str(l2.resolve()),
                    "filename": "L2_pruned.sql",
                }
            }
            errors = verify_manifest_l2_lineage(manifest, l2_sql_path=l2)
            self.assertEqual(errors, [])


if __name__ == "__main__":
    unittest.main()
