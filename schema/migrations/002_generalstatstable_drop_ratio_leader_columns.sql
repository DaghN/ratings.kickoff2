-- PG-004: ratio/average player record leaders live on playertable only (Records page queries).
-- Server-wide DoubleDigitsRatio / CleanSheetsRatio (totals) are unchanged.
-- Steve: apply same file on staging/prod kooldb when deploying PG-004.

SET SESSION group_concat_max_len = 8192;

SET @drop_columns := (
  SELECT GROUP_CONCAT(CONCAT('DROP COLUMN `', COLUMN_NAME, '`') SEPARATOR ', ')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'generalstatstable'
    AND COLUMN_NAME IN (
      'BiggestWinRatio',
      'BiggestGoalsForAverage',
      'SmallestGoalsAgainstAverage',
      'BiggestGoalRatio',
      'BiggestDoubleDigitsRatio',
      'BiggestCleanSheetsRatio',
      'BiggestAverageOpponentRating',
      'BiggestWinRatioID',
      'BiggestGoalsForAverageID',
      'SmallestGoalsAgainstAverageID',
      'BiggestGoalRatioID',
      'BiggestDoubleDigitsRatioID',
      'BiggestCleanSheetsRatioID',
      'BiggestAverageOpponentRatingID',
      'BiggestWinRatioName',
      'BiggestGoalsForAverageName',
      'SmallestGoalsAgainstAverageName',
      'BiggestGoalRatioName',
      'BiggestDoubleDigitsRatioName',
      'BiggestCleanSheetsRatioName',
      'BiggestAverageOpponentRatingName',
      'BiggestWinRatioDate',
      'BiggestGoalsForAverageDate',
      'SmallestGoalsAgainstAverageDate',
      'BiggestGoalRatioDate',
      'BiggestDoubleDigitsRatioDate',
      'BiggestCleanSheetsRatioDate',
      'BiggestAverageOpponentRatingDate'
    )
);

SET @sql := IF(
  @drop_columns IS NULL,
  'SELECT 1',
  CONCAT('ALTER TABLE `generalstatstable` ', @drop_columns)
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
