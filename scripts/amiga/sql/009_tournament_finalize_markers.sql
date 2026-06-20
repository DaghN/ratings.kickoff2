-- Tournament rating finalize markers (no amiga_rating_events — retired slice 8).
-- Apply after 008 in fresh apply_schema bundle.
SET time_zone = '+00:00';

ALTER TABLE `tournaments`
  ADD COLUMN `rating_finalized` tinyint(1) NOT NULL DEFAULT 0 AFTER `completed_at`;

ALTER TABLE `tournaments`
  ADD COLUMN `rating_finalized_at` datetime DEFAULT NULL AFTER `rating_finalized`;

ALTER TABLE `tournaments`
  ADD KEY `idx_tournaments_rating_finalized` (`rating_finalized`);
