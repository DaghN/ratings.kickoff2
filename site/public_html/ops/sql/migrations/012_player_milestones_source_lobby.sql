-- SCH-013: entered_arena — registration = entering the lobby (not a game or league).
-- Register: docs/coordination/schema-register.md

ALTER TABLE `player_milestones`
  MODIFY COLUMN `source_kind` enum('game','league','lobby') DEFAULT NULL
    COMMENT 'game=ratedresults; league=closed league; lobby=playertable.JoinDate at register';
