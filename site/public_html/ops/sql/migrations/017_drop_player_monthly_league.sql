-- SCH-017: Drop legacy player_monthly_league (superseded by player_period_league month rows).
-- Status and league UI read player_period_league; table no longer rebuilt or truncated in prepare.
-- Idempotent: no-op when table already absent.
-- Apply on work via prepare migrate-work; coordinate Steve for staging/prod before deploy.

DROP TABLE IF EXISTS `player_monthly_league`;
