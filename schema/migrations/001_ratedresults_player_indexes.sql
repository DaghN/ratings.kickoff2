-- SCH-001: Profile / player APIs filter ratedresults by idA OR idB.
-- Idempotent: skip if index already exists (manual check) or run via apply_local.ps1.
-- Register: docs/coordination/schema-register.md

-- MySQL 8+ / MariaDB 10.11: CREATE INDEX IF NOT EXISTS (MariaDB 10.11.7+)
CREATE INDEX IF NOT EXISTS idx_ratedresults_idA ON ratedresults (idA);
CREATE INDEX IF NOT EXISTS idx_ratedresults_idB ON ratedresults (idB);
