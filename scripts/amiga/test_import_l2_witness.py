"""Tests for L3 witness load from L2 SQL (slice 10)."""

from __future__ import annotations

import tempfile
import unittest
from pathlib import Path

from scripts.amiga.import_l2_witness import load_l2_witness_inputs
from scripts.amiga.import_prune import prune_l1_mirror


_MIN_L1_SCORES = """
-- table: Scores
DROP TABLE IF EXISTS `Scores`;
CREATE TABLE IF NOT EXISTS `Scores` (
  `ID` int NOT NULL,
  `Team A` varchar(50) NULL,
  `Team B` varchar(50) NULL,
  `A` varchar(5) NULL,
  `B` varchar(5) NULL,
  `Tournament` varchar(50) NULL,
  `Phase` varchar(50) NULL,
  `Extra` varchar(50) NULL
) ENGINE=InnoDB;
INSERT INTO `Scores` (`ID`, `Team A`, `Team B`, `A`, `B`, `Tournament`, `Phase`, `Extra`) VALUES
  (1, 'Mark B', 'Oliver St', 3, 2, 'Test Cup', NULL, NULL);
"""

_MIN_L1_TOURNAMENTS = """
-- table: Tournament players
DROP TABLE IF EXISTS `Tournament players`;
CREATE TABLE IF NOT EXISTS `Tournament players` (
  `ID` int NOT NULL,
  `Tournament` varchar(50) NULL,
  `Players` varchar(5) NULL,
  `Chrono` double NULL,
  `Date` datetime NULL,
  `Cup?` tinyint(1) NOT NULL,
  `Country` varchar(50) NULL,
  `EqualTeams` tinyint(1) NOT NULL
) ENGINE=InnoDB;
INSERT INTO `Tournament players` (`ID`, `Tournament`, `Players`, `Chrono`, `Date`, `Cup?`, `Country`, `EqualTeams`) VALUES
  (1, 'Test Cup', 2, 1.0, '2020-01-01 00:00:00', 0, 'England', 1);
"""

_MIN_L1_RANKINGS = """
-- table: Rankings
DROP TABLE IF EXISTS `Rankings`;
CREATE TABLE IF NOT EXISTS `Rankings` (
  `Player` varchar(255) NULL,
  `Country` varchar(255) NULL,
  `R0102` varchar(5) NULL
) ENGINE=InnoDB;
INSERT INTO `Rankings` (`Player`, `Country`, `R0102`) VALUES
  ('Mark B', 'England', 1000),
  ('Oliver St', 'Germany', 1000);
"""


class TestImportL2Witness(unittest.TestCase):
    def test_prepare_witness_from_l2_minimal(self) -> None:
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp)
            l1_dir = root / "pristine"
            l2_dir = root / "pruned"
            l1_dir.mkdir()
            manifest = {
                "tables": {
                    "Scores": {"rows": 1, "columns": 8},
                    "Tournament players": {"rows": 1, "columns": 8},
                    "Rankings": {"rows": 2, "columns": 3},
                }
            }
            (l1_dir / "pristine_manifest.json").write_text(
                __import__("json").dumps(manifest),
                encoding="utf-8",
            )
            (l1_dir / "L1_mirror.sql").write_text(
                _MIN_L1_SCORES + _MIN_L1_TOURNAMENTS + _MIN_L1_RANKINGS,
                encoding="utf-8",
            )
            prune_l1_mirror(
                l1_manifest_path=l1_dir / "pristine_manifest.json",
                l1_sql_path=l1_dir / "L1_mirror.sql",
                out_dir=l2_dir,
            )
            source, tournaments, scores, countries = load_l2_witness_inputs(l2_dir)
            self.assertEqual(source.get("layer"), "L2")
            self.assertEqual(len(scores), 1)
            self.assertEqual(countries.get("Mark B"), "England")
            self.assertEqual(tournaments[0]["name"], "Test Cup")


if __name__ == "__main__":
    unittest.main()
