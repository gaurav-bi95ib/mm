-- MeroMaidan cleanup: the product has no feedback/review feature.
-- Legacy generic promotions are also removed in favour of the audited
-- Recommended Venue, Event Promotion, and Coupon model introduced in v4.
USE meromaidan;

DROP TABLE IF EXISTS reviews;
DROP TABLE IF EXISTS promotions;

ALTER TABLE venues
  DROP COLUMN IF EXISTS rating,
  DROP COLUMN IF EXISTS total_reviews;

UPDATE notifications SET type='system' WHERE type='review';
ALTER TABLE notifications
  MODIFY type ENUM('booking','payment','system','maintenance') DEFAULT 'booking';
