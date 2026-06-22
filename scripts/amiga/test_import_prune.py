"""Tests for L2 import-prune (slice 9 — witness_player_identity)."""

from __future__ import annotations

import unittest

from scripts.amiga.import_prune import (
    WITNESS_PLAYER_IDENTITY,
    build_witness_player_identity_sql,
    extract_witness_player_identity,
    verify_prune_sql,
)


class TestImportPrune(unittest.TestCase):
    def test_extract_witness_player_identity_from_minimal_section(self) -> None:
        section = """
-- table: Rankings
DROP TABLE IF EXISTS `Rankings`;
CREATE TABLE IF NOT EXISTS `Rankings` (
  `Player` varchar(255) NULL,
  `Country` varchar(255) NULL,
  `R0102` varchar(5) NULL
);
INSERT INTO `Rankings` (`Player`, `Country`, `R0102`) VALUES
  ('Mark B', 'England', 1000),
  ('Oliver St', 'Germany', NULL);
"""
        rows = extract_witness_player_identity(section)
        self.assertEqual(rows, [("Mark B", "England"), ("Oliver St", "Germany")])

    def test_build_witness_sql_has_no_rating_columns(self) -> None:
        sql = build_witness_player_identity_sql([("A", "Italy")])
        self.assertIn(f"`{WITNESS_PLAYER_IDENTITY}`", sql)
        self.assertNotIn("R0102", sql)
        self.assertIn("'A'", sql)

    def test_verify_prune_sql_rejects_rankings_grid(self) -> None:
        from pathlib import Path
        import tempfile

        bad = "CREATE TABLE IF NOT EXISTS `Rankings` (`R0102` int);"
        with tempfile.NamedTemporaryFile("w", suffix=".sql", delete=False, encoding="utf-8") as f:
            f.write(bad)
            path = Path(f.name)
        try:
            errors = verify_prune_sql(path)
            self.assertTrue(any("forbidden" in e for e in errors))
        finally:
            path.unlink(missing_ok=True)


if __name__ == "__main__":
    unittest.main()
