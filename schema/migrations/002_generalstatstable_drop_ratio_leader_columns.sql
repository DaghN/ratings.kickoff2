-- PG-004: ratio/average player record leaders live on playertable only (Records page queries).
-- Server-wide DoubleDigitsRatio / CleanSheetsRatio (totals) are unchanged.
-- Steve: apply same file on staging/prod kooldb when deploying PG-004.

ALTER TABLE `generalstatstable`
  DROP COLUMN `BiggestWinRatio`,
  DROP COLUMN `BiggestGoalsForAverage`,
  DROP COLUMN `SmallestGoalsAgainstAverage`,
  DROP COLUMN `BiggestGoalRatio`,
  DROP COLUMN `BiggestDoubleDigitsRatio`,
  DROP COLUMN `BiggestCleanSheetsRatio`,
  DROP COLUMN `BiggestAverageOpponentRating`,
  DROP COLUMN `BiggestWinRatioID`,
  DROP COLUMN `BiggestGoalsForAverageID`,
  DROP COLUMN `SmallestGoalsAgainstAverageID`,
  DROP COLUMN `BiggestGoalRatioID`,
  DROP COLUMN `BiggestDoubleDigitsRatioID`,
  DROP COLUMN `BiggestCleanSheetsRatioID`,
  DROP COLUMN `BiggestAverageOpponentRatingID`,
  DROP COLUMN `BiggestWinRatioName`,
  DROP COLUMN `BiggestGoalsForAverageName`,
  DROP COLUMN `SmallestGoalsAgainstAverageName`,
  DROP COLUMN `BiggestGoalRatioName`,
  DROP COLUMN `BiggestDoubleDigitsRatioName`,
  DROP COLUMN `BiggestCleanSheetsRatioName`,
  DROP COLUMN `BiggestAverageOpponentRatingName`,
  DROP COLUMN `BiggestWinRatioDate`,
  DROP COLUMN `BiggestGoalsForAverageDate`,
  DROP COLUMN `SmallestGoalsAgainstAverageDate`,
  DROP COLUMN `BiggestGoalRatioDate`,
  DROP COLUMN `BiggestDoubleDigitsRatioDate`,
  DROP COLUMN `BiggestCleanSheetsRatioDate`,
  DROP COLUMN `BiggestAverageOpponentRatingDate`;
