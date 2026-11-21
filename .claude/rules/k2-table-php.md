---
paths:
  - "site/public_html/**/*.php"
---

# K2 table PHP edits

This mirrors the Cursor glob rule `.cursor/rules/k2-table-php.mdc`, which is the source of truth.

Before you change table markup or page head assets in this file, read `.cursor/rules/k2-table-php.mdc` and `docs/k2-table-implementation-checklist.md`, then open the closest §1 reference file and copy its pattern. Never add a full-page sortable table with only `k2_table_js_enqueue()`.
