"""Master disposition register — tournament_id → handler (policy §4)."""

from __future__ import annotations

import json
from dataclasses import dataclass, field
from pathlib import Path
from typing import Any, Final

HANDLER_PURE_RR: Final[str] = "pure_rr"
HANDLER_PURE_KNOCKOUT: Final[str] = "pure_knockout"
HANDLER_STRUCTURE_SPEC: Final[str] = "structure_spec"
HANDLER_PENDING_REVIEW: Final[str] = "pending_review"
HANDLER_WC_DEFERRED: Final[str] = "wc_deferred"
HANDLER_NO_GAMES: Final[str] = "no_games"

VALID_HANDLERS: frozenset[str] = frozenset({
    HANDLER_PURE_RR,
    HANDLER_PURE_KNOCKOUT,
    HANDLER_STRUCTURE_SPEC,
    HANDLER_PENDING_REVIEW,
    HANDLER_WC_DEFERRED,
    HANDLER_NO_GAMES,
})

REGISTER_PATH = Path(__file__).resolve().parent / "disposition_register.json"


@dataclass
class DispositionRow:
    handler: str
    spec_slug: str | None = None
    notes: str | None = None

    def to_dict(self) -> dict[str, Any]:
        out: dict[str, Any] = {"handler": self.handler}
        if self.spec_slug:
            out["spec_slug"] = self.spec_slug
        if self.notes:
            out["notes"] = self.notes
        return out

    @classmethod
    def from_dict(cls, data: dict[str, Any]) -> DispositionRow:
        handler = str(data["handler"])
        if handler not in VALID_HANDLERS:
            raise ValueError(f"unknown handler {handler!r}")
        return cls(
            handler=handler,
            spec_slug=data.get("spec_slug"),
            notes=data.get("notes"),
        )


@dataclass
class DispositionRegister:
    version: int = 1
    rows: dict[int, DispositionRow] = field(default_factory=dict)

    def get(self, tournament_id: int) -> DispositionRow | None:
        return self.rows.get(int(tournament_id))

    def set(self, tournament_id: int, row: DispositionRow) -> None:
        if row.handler not in VALID_HANDLERS:
            raise ValueError(f"unknown handler {row.handler!r}")
        self.rows[int(tournament_id)] = row

    def to_dict(self) -> dict[str, Any]:
        return {
            "version": self.version,
            "tournaments": {
                str(tid): row.to_dict() for tid, row in sorted(self.rows.items())
            },
        }

    @classmethod
    def from_dict(cls, data: dict[str, Any]) -> DispositionRegister:
        reg = cls(version=int(data.get("version", 1)))
        for key, row_data in (data.get("tournaments") or {}).items():
            reg.rows[int(key)] = DispositionRow.from_dict(row_data)
        return reg

    def save(self, path: Path | None = None) -> None:
        path = path or REGISTER_PATH
        path.write_text(json.dumps(self.to_dict(), indent=2) + "\n", encoding="utf-8")

    @classmethod
    def load(cls, path: Path | None = None) -> DispositionRegister:
        path = path or REGISTER_PATH
        if not path.is_file():
            return cls()
        return cls.from_dict(json.loads(path.read_text(encoding="utf-8")))


def propose_handler(
    *,
    tournament_id: int,
    tournament_name: str,
    tier: str,
    tier_detail: str,
    game_count: int,
    materialized_spec: bool = False,
) -> DispositionRow:
    """Bootstrap proposal from audit tier — human review may override."""
    from scripts.amiga.tournament_structure.registry import registry_entry_for_catalog
    from scripts.amiga.tournament_structure.tier_b_non_wc_register import (
        DEFERRED_WORLD_CUP_TOURNAMENT_IDS,
        is_world_cup_catalog_name,
    )

    if game_count == 0:
        return DispositionRow(handler=HANDLER_NO_GAMES, notes="no games")

    entry = registry_entry_for_catalog(tournament_name)
    if entry is not None and entry.status == "active":
        slug = entry.spec.template_slug
        return DispositionRow(
            handler=HANDLER_STRUCTURE_SPEC,
            spec_slug=slug,
            notes=entry.notes,
        )

    if tournament_id in DEFERRED_WORLD_CUP_TOURNAMENT_IDS or is_world_cup_catalog_name(tournament_name):
        return DispositionRow(handler=HANDLER_WC_DEFERRED, notes="WC track")

    if tier == "A":
        return DispositionRow(handler=HANDLER_PURE_RR, notes=tier_detail)

    # Known safe pure KO cups (Jun 2026 audit)
    from scripts.amiga.tournament_structure.tier_b_non_wc_register import (
        NON_WC_TIER_B_AUTO_MATERIALIZE_IDS,
    )

    if tournament_id in NON_WC_TIER_B_AUTO_MATERIALIZE_IDS:
        return DispositionRow(handler=HANDLER_PURE_KNOCKOUT, notes="audit: obvious 2^n cup")

    if tier == "D" or materialized_spec:
        return DispositionRow(handler=HANDLER_STRUCTURE_SPEC, notes=tier_detail)

    return DispositionRow(handler=HANDLER_PENDING_REVIEW, notes=f"{tier}: {tier_detail}")


def generate_register(conn: Any) -> DispositionRegister:
    """Build full register from ko2amiga_db audit."""
    from scripts.amiga.tournament_structure.verify_legacy import (
        TIER_D,
        audit_legacy_tier_inventory,
        classify_legacy_tier,
        _load_games,
        _parse_overrides,
    )

    report = audit_legacy_tier_inventory(conn, imported_only=True, min_games=0)
    reg = DispositionRegister()
    seen: set[int] = set()

    for tier_key, rows in report["tiers"].items():
        for row in rows:
            tid = int(row["tournament_id"])
            seen.add(tid)
            games_n = int(row.get("game_count", 0))
            reg.set(
                tid,
                propose_handler(
                    tournament_id=tid,
                    tournament_name=str(row["name"]),
                    tier=tier_key,
                    tier_detail=str(row.get("tier_detail", "")),
                    game_count=games_n,
                    materialized_spec=tier_key == TIER_D,
                ),
            )

    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT t.id, t.name, t.format_overrides
            FROM tournaments t
            WHERE t.source_id IS NOT NULL
            ORDER BY t.id
            """
        )
        for row in cur.fetchall():
            tid = int(row["id"])
            if tid in seen:
                continue
            games = _load_games(conn, tid)
            overrides = _parse_overrides(row.get("format_overrides"))
            tier, detail = classify_legacy_tier(
                games,
                tournament_name=str(row["name"]),
                format_overrides=overrides,
                tournament_id=tid,
            )
            reg.set(
                tid,
                propose_handler(
                    tournament_id=tid,
                    tournament_name=str(row["name"]),
                    tier=tier,
                    tier_detail=detail,
                    game_count=len(games),
                    materialized_spec=tier == TIER_D,
                ),
            )

    return reg


def verify_register(conn: Any, reg: DispositionRegister | None = None) -> dict[str, Any]:
    """Coverage check — every imported tournament with games has a row."""
    reg = reg or DispositionRegister.load()
    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT t.id, t.name,
                   (SELECT COUNT(*) FROM amiga_games g WHERE g.tournament_id = t.id) AS game_count
            FROM tournaments t
            WHERE t.source_id IS NOT NULL
            ORDER BY t.id
            """
        )
        catalog = list(cur.fetchall())

    missing: list[int] = []
    by_handler: dict[str, int] = {}
    for row in catalog:
        tid = int(row["id"])
        if tid not in reg.rows:
            missing.append(tid)
        else:
            h = reg.rows[tid].handler
            by_handler[h] = by_handler.get(h, 0) + 1

    return {
        "catalog_count": len(catalog),
        "register_count": len(reg.rows),
        "missing_ids": missing,
        "by_handler": by_handler,
        "ok": len(missing) == 0 and len(reg.rows) >= len(catalog),
    }
