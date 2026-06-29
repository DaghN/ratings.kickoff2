"""Unit tests for generalstats / realm snapshot column manifests."""

from __future__ import annotations

import unittest

from scripts.amiga.generalstats_columns import (
    GENERALSTATS_AGGREGATE_COLUMNS,
    GENERALSTATS_PAYLOAD_COLUMNS,
    RATIO_LEADER_COLUMNS,
    RECORD_HOLDER_COLUMNS,
    REALM_SNAPSHOT_COLUMNS,
    REALM_SNAPSHOT_PAYLOAD_COLUMNS,
)


class GeneralstatsColumnManifestTests(unittest.TestCase):
    def test_payload_column_counts(self) -> None:
        self.assertEqual(len(GENERALSTATS_AGGREGATE_COLUMNS), 14)
        self.assertEqual(len(RATIO_LEADER_COLUMNS), 18)
        self.assertEqual(len(RECORD_HOLDER_COLUMNS), 96)
        self.assertEqual(len(GENERALSTATS_PAYLOAD_COLUMNS), 114)

    def test_realm_snapshot_payload_matches_generalstats(self) -> None:
        self.assertEqual(
            REALM_SNAPSHOT_PAYLOAD_COLUMNS,
            GENERALSTATS_PAYLOAD_COLUMNS,
        )
        self.assertEqual(len(REALM_SNAPSHOT_COLUMNS), 119)

    def test_no_duplicate_column_names(self) -> None:
        for label, columns in (
            ("generalstats payload", GENERALSTATS_PAYLOAD_COLUMNS),
            ("realm snapshot", REALM_SNAPSHOT_COLUMNS),
        ):
            self.assertEqual(
                len(columns),
                len(set(columns)),
                f"duplicate names in {label}",
            )


if __name__ == "__main__":
    unittest.main()
