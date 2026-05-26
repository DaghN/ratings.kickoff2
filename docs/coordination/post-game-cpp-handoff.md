# Post-game C++ handoff — retired snippet workflow

**May 2026:** We no longer maintain per-table C++ snippet packs (`PG-005` … `PG-013`) in this repo.

## For agents

- Spec: [`website-data-contract.md`](../website-data-contract.md)
- Staging/local data: schema migrations + `scripts/ladder/sql/*_rebuild.sql` ([`replay-register.md`](replay-register.md))
- Do **not** create new `cpp-snippets/` files or cite `PG-00x` as blocking work

## For Steve (prod cutover)

1. Apply pending [`schema/migrations/`](../schema/migrations/) on prod.
2. Run matching `*_rebuild.sql` scripts (same as staging).
3. Update post-game C++ from **contract** post-game rules + [`ratings_cpp.txt`](../ratings_cpp.txt).
4. **Records only:** [`records-post-game-exception.md`](records-post-game-exception.md) + [`staging-post-game-record-defects.md`](../staging-post-game-record-defects.md).

Cutover email: [`cutover-packet-template.md`](cutover-packet-template.md).
