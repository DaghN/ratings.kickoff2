"""Unit tests for format template registry."""

from __future__ import annotations

import unittest

from scripts.amiga.tournament_format import FORMAT_TEMPLATES, PLANNED_TEMPLATE_SLUGS


class FormatTemplateRegistryTests(unittest.TestCase):
    def test_all_templates_implemented(self) -> None:
        self.assertEqual(len(FORMAT_TEMPLATES), 6)
        self.assertEqual(PLANNED_TEMPLATE_SLUGS, frozenset())
        for row in FORMAT_TEMPLATES:
            spec = row["spec"]
            self.assertNotEqual(spec.get("status"), "planned", row["slug"])

    def test_double_elim_and_swiss_implemented(self) -> None:
        by_slug = {str(t["slug"]): t for t in FORMAT_TEMPLATES}
        self.assertEqual(by_slug["swiss"]["spec"].get("status"), "implemented")
        self.assertEqual(by_slug["double_elimination"]["spec"].get("status"), "implemented")
        self.assertIn("stage_factory", by_slug["double_elimination"]["spec"])


if __name__ == "__main__":
    unittest.main()
