-- MeroMaidan v4 - Final subscription and promotion business model
-- Commercial services remain separate:
-- 1) Annual venue subscription: NPR 9,999/year for one venue
-- 2) Recommended Venue: NPR 1,000/month per venue
-- 3) Event Promotion: NPR 2,000 for one seven-day campaign

USE meromaidan;
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

ALTER TABLE subscription_plans
  ADD COLUMN IF NOT EXISTS price_yearly DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER price_monthly,
  ADD COLUMN IF NOT EXISTS duration_months INT NOT NULL DEFAULT 12 AFTER price_yearly,
  ADD COLUMN IF NOT EXISTS included_venues INT NOT NULL DEFAULT 1 AFTER duration_months;

INSERT INTO subscription_plans
  (name, slug, price_monthly, price_yearly, duration_months, included_venues, max_venues, max_bookings_per_month, features, is_active)
VALUES
  ('Annual Venue Subscription', 'annual-venue', 0, 9999, 12, 1, 1, 999999,
   '["List and manage one venue","Manage grounds and courts","Venue information, photos and facilities","Operating hours, pricing and booking slots","Customer bookings","Venue-management dashboard","Operational staff and field activities","Booking, customer and operational reports"]', 1)
ON DUPLICATE KEY UPDATE
  name=VALUES(name), price_monthly=0, price_yearly=9999, duration_months=12,
  included_venues=1, max_venues=1, max_bookings_per_month=999999,
  features=VALUES(features), is_active=1;

UPDATE venue_owners
SET plan_id=(SELECT id FROM subscription_plans WHERE slug='annual-venue' LIMIT 1);
DELETE FROM subscription_plans WHERE slug<>'annual-venue';

CREATE TABLE IF NOT EXISTS platform_commercial_config (
  id INT AUTO_INCREMENT PRIMARY KEY,
  config_key VARCHAR(100) NOT NULL UNIQUE,
  config_value VARCHAR(255) NULL,
  value_type ENUM('money','integer','text','boolean') NOT NULL DEFAULT 'text',
  description VARCHAR(255) NULL,
  updated_by INT NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO platform_commercial_config (config_key,config_value,value_type,description) VALUES
('annual_subscription_price_npr','9999','money','Annual subscription for one venue'),
('annual_subscription_duration_months','12','integer','Annual venue subscription duration'),
('annual_subscription_venue_limit','1','integer','Included venue limit'),
('recommended_venue_price_npr','1000','money','Recommended Venue placement for one month'),
('recommended_venue_duration_months','1','integer','Recommended Venue duration'),
('event_promotion_price_npr','2000','money','Event Promotion price for one week'),
('event_promotion_duration_days','7','integer','Event Promotion duration in days')
ON DUPLICATE KEY UPDATE config_value=VALUES(config_value),description=VALUES(description);

-- Operational settings are created once and remain editable from Super Admin.
INSERT INTO platform_commercial_config (config_key,config_value,value_type,description) VALUES
('platform_name','MeroMaidan','text','Public platform name'),
('support_email','support@meromaidan.com','text','Support contact email'),
('mock_esewa_enabled','1','boolean','Enable mock eSewa gateway'),
('mock_khalti_enabled','0','boolean','Enable mock Khalti gateway'),
('cancellation_window_hours','6','integer','Default booking cancellation window')
ON DUPLICATE KEY UPDATE config_key=VALUES(config_key);

CREATE TABLE IF NOT EXISTS venue_subscriptions (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT NOT NULL,
  owner_id INT NOT NULL,
  venue_id INT NULL,
  plan_id INT NOT NULL,
  amount_npr DECIMAL(10,2) NOT NULL DEFAULT 9999,
  starts_at DATE NOT NULL,
  expires_at DATE NOT NULL,
  status ENUM('pending_payment','active','expired','suspended','cancelled') NOT NULL DEFAULT 'pending_payment',
  payment_reference VARCHAR(100) NULL,
  auto_renew TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_subscription_tenant (tenant_id,status),
  INDEX idx_subscription_expiry (expires_at,status),
  CONSTRAINT fk_vs_owner FOREIGN KEY (owner_id) REFERENCES venue_owners(id) ON DELETE CASCADE,
  CONSTRAINT fk_vs_venue FOREIGN KEY (venue_id) REFERENCES venues(id) ON DELETE SET NULL,
  CONSTRAINT fk_vs_plan FOREIGN KEY (plan_id) REFERENCES subscription_plans(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO venue_subscriptions
  (tenant_id,owner_id,venue_id,plan_id,amount_npr,starts_at,expires_at,status,payment_reference)
SELECT vo.id,vo.id,MIN(v.id),vo.plan_id,9999,
       COALESCE(DATE(vo.approved_at),CURDATE()),
       DATE_ADD(COALESCE(DATE(vo.approved_at),CURDATE()),INTERVAL 1 YEAR),
       IF(vo.status='active','active','suspended'),'MIGRATED-V4'
FROM venue_owners vo LEFT JOIN venues v ON v.owner_id=vo.id
WHERE NOT EXISTS (SELECT 1 FROM venue_subscriptions vs WHERE vs.owner_id=vo.id)
GROUP BY vo.id,vo.plan_id,vo.approved_at,vo.status;

CREATE TABLE IF NOT EXISTS recommended_venue_promotions (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT NOT NULL,
  owner_id INT NOT NULL,
  venue_id INT NOT NULL,
  amount_npr DECIMAL(10,2) NOT NULL DEFAULT 1000,
  starts_at DATE NULL,
  expires_at DATE NULL,
  status ENUM('draft','pending_payment','pending_review','scheduled','active','expired','rejected','suspended','cancelled') NOT NULL DEFAULT 'pending_payment',
  payment_reference VARCHAR(100) NULL,
  approved_by INT NULL,
  approved_at DATETIME NULL,
  rejection_reason VARCHAR(500) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_recommended_location (venue_id,status,starts_at,expires_at),
  INDEX idx_recommended_tenant (tenant_id,status),
  CONSTRAINT fk_rvp_owner FOREIGN KEY (owner_id) REFERENCES venue_owners(id) ON DELETE CASCADE,
  CONSTRAINT fk_rvp_venue FOREIGN KEY (venue_id) REFERENCES venues(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS event_promotions (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT NOT NULL,
  owner_id INT NOT NULL,
  venue_id INT NOT NULL,
  title VARCHAR(180) NOT NULL,
  short_description VARCHAR(500) NOT NULL,
  event_date DATE NOT NULL,
  promotion_starts_at DATETIME NOT NULL,
  promotion_expires_at DATETIME NOT NULL,
  discount_label VARCHAR(120) NULL,
  cta_text VARCHAR(50) NOT NULL DEFAULT 'View Venue',
  amount_npr DECIMAL(10,2) NULL,
  status ENUM('draft','pending_payment','pending_review','scheduled','active','expired','rejected','suspended','cancelled') NOT NULL DEFAULT 'draft',
  approved_by INT NULL,
  approved_at DATETIME NULL,
  rejection_reason VARCHAR(500) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_event_marketplace (status,promotion_starts_at,promotion_expires_at),
  INDEX idx_event_tenant (tenant_id,status),
  CONSTRAINT fk_event_owner FOREIGN KEY (owner_id) REFERENCES venue_owners(id) ON DELETE CASCADE,
  CONSTRAINT fk_event_venue FOREIGN KEY (venue_id) REFERENCES venues(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS promotion_hero_banners (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  event_promotion_id BIGINT NOT NULL UNIQUE,
  image_url VARCHAR(1000) NOT NULL,
  alt_text VARCHAR(255) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_published TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_banner_event FOREIGN KEY (event_promotion_id) REFERENCES event_promotions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS coupons (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT NOT NULL,
  owner_id INT NOT NULL,
  venue_id INT NOT NULL,
  event_promotion_id BIGINT NULL,
  code VARCHAR(50) NOT NULL UNIQUE,
  discount_type ENUM('percentage','fixed') NOT NULL,
  discount_value DECIMAL(10,2) NOT NULL,
  minimum_booking_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
  maximum_discount_amount DECIMAL(10,2) NULL,
  usage_limit INT NULL,
  usage_limit_per_player INT NOT NULL DEFAULT 1,
  uses_count INT NOT NULL DEFAULT 0,
  eligibility_json LONGTEXT NULL,
  valid_from DATETIME NOT NULL,
  valid_to DATETIME NOT NULL,
  status ENUM('draft','active','expired','suspended','cancelled') NOT NULL DEFAULT 'draft',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_coupon_validation (code,status,venue_id,valid_from,valid_to),
  CONSTRAINT fk_coupon_owner FOREIGN KEY (owner_id) REFERENCES venue_owners(id) ON DELETE CASCADE,
  CONSTRAINT fk_coupon_venue FOREIGN KEY (venue_id) REFERENCES venues(id) ON DELETE CASCADE,
  CONSTRAINT fk_coupon_event FOREIGN KEY (event_promotion_id) REFERENCES event_promotions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS coupon_usages (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  coupon_id BIGINT NOT NULL,
  booking_id INT NOT NULL,
  player_id INT NULL,
  tenant_id INT NOT NULL,
  original_amount DECIMAL(10,2) NOT NULL,
  discount_amount DECIMAL(10,2) NOT NULL,
  final_amount DECIMAL(10,2) NOT NULL,
  used_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_coupon_booking (coupon_id,booking_id),
  INDEX idx_coupon_player (coupon_id,player_id),
  CONSTRAINT fk_usage_coupon FOREIGN KEY (coupon_id) REFERENCES coupons(id),
  CONSTRAINT fk_usage_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
  CONSTRAINT fk_usage_player FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS promotion_payments (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT NOT NULL,
  owner_id INT NOT NULL,
  service_type ENUM('annual_subscription','recommended_venue','event_promotion') NOT NULL,
  service_id BIGINT NOT NULL,
  amount_npr DECIMAL(10,2) NOT NULL,
  payment_method VARCHAR(50) NOT NULL DEFAULT 'esewa',
  provider_reference VARCHAR(120) NULL,
  status ENUM('initiated','pending','paid','failed','refunded','cancelled') NOT NULL DEFAULT 'initiated',
  paid_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_promotion_payment (tenant_id,service_type,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS promotion_analytics (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT NOT NULL,
  promotion_type ENUM('recommended_venue','event_promotion') NOT NULL,
  promotion_id BIGINT NOT NULL,
  event_type ENUM('impression','click','booking','coupon_use') NOT NULL,
  player_id INT NULL,
  booking_id INT NULL,
  event_date DATE NOT NULL,
  metadata_json LONGTEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_promo_analytics (promotion_type,promotion_id,event_date,event_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE bookings
  ADD COLUMN IF NOT EXISTS base_price DECIMAL(10,2) NULL AFTER end_time,
  ADD COLUMN IF NOT EXISTS coupon_id BIGINT NULL AFTER base_price,
  ADD COLUMN IF NOT EXISTS coupon_code VARCHAR(50) NULL AFTER coupon_id,
  ADD COLUMN IF NOT EXISTS discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER coupon_code,
  ADD COLUMN IF NOT EXISTS fees_amount DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER discount_amount,
  ADD COLUMN IF NOT EXISTS tax_amount DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER fees_amount;

UPDATE bookings SET base_price=total_price WHERE base_price IS NULL;

SET FOREIGN_KEY_CHECKS = 1;
