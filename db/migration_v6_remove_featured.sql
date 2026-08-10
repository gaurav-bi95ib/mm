-- Retire the legacy venue Featured flag.
-- Paid visibility is now derived exclusively from a paid, approved and active
-- Recommended Venue promotion.
USE meromaidan;

ALTER TABLE venues DROP COLUMN IF EXISTS featured;
