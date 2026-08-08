-- MeroMaidan - Migration V2
-- Run this AFTER schema.sql has been applied
-- Adds: players, player_favorites, reviews, promotions, audit_logs, maintenance_blocks

USE meromaidan;

-- ─────────────────────────────────────────────
-- Players (Registered Customers / Marketplace Users)
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS players (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(200) NOT NULL,
  email VARCHAR(200) NOT NULL UNIQUE,
  phone VARCHAR(20),
  password_hash VARCHAR(255) NOT NULL,
  status ENUM('pending','active','suspended') DEFAULT 'active',
  avatar VARCHAR(500) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ─────────────────────────────────────────────
-- Add player_id to bookings (nullable for backward compat)
-- ─────────────────────────────────────────────
ALTER TABLE bookings
  ADD COLUMN IF NOT EXISTS player_id INT NULL AFTER venue_id,
  ADD CONSTRAINT IF NOT EXISTS fk_bookings_player
    FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE SET NULL;

-- ─────────────────────────────────────────────
-- Player Favorites
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS player_favorites (
  id INT AUTO_INCREMENT PRIMARY KEY,
  player_id INT NOT NULL,
  venue_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_fav (player_id, venue_id),
  FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
  FOREIGN KEY (venue_id) REFERENCES venues(id) ON DELETE CASCADE
);

-- ─────────────────────────────────────────────
-- Reviews (only for completed bookings, one per booking)
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS reviews (
  id INT AUTO_INCREMENT PRIMARY KEY,
  venue_id INT NOT NULL,
  player_id INT NOT NULL,
  booking_id INT NOT NULL,
  rating TINYINT NOT NULL DEFAULT 5,
  review_text TEXT,
  status ENUM('approved','pending','hidden') DEFAULT 'approved',
  owner_reply TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_review (booking_id),
  FOREIGN KEY (venue_id) REFERENCES venues(id) ON DELETE CASCADE,
  FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
  FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
);

-- ─────────────────────────────────────────────
-- Promotions / Discount Codes
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS promotions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  owner_id INT NOT NULL,
  venue_id INT NULL COMMENT 'NULL = all venues of owner',
  title VARCHAR(200) NOT NULL,
  code VARCHAR(50) UNIQUE,
  type ENUM('percentage','fixed','first_booking') DEFAULT 'percentage',
  value DECIMAL(10,2) NOT NULL,
  min_amount DECIMAL(10,2) DEFAULT 0,
  max_uses INT DEFAULT NULL,
  uses_count INT DEFAULT 0,
  valid_from DATE NOT NULL,
  valid_to DATE NOT NULL,
  is_active TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (owner_id) REFERENCES venue_owners(id) ON DELETE CASCADE,
  FOREIGN KEY (venue_id) REFERENCES venues(id) ON DELETE SET NULL
);

-- ─────────────────────────────────────────────
-- Audit Logs (BR-016: all privileged actions audited)
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS audit_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  actor_type ENUM('player','owner','superadmin','system') NOT NULL DEFAULT 'system',
  actor_id INT NULL,
  actor_name VARCHAR(200),
  tenant_id INT NULL,
  module VARCHAR(100),
  action VARCHAR(200) NOT NULL,
  resource_type VARCHAR(100),
  resource_id INT NULL,
  description TEXT,
  ip_address VARCHAR(45),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ─────────────────────────────────────────────
-- Maintenance Blocks (FR-AVL-002, FR-AVL-003)
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS maintenance_blocks (
  id INT AUTO_INCREMENT PRIMARY KEY,
  venue_id INT NOT NULL,
  block_date DATE NOT NULL,
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  reason VARCHAR(500),
  created_by_owner INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (venue_id) REFERENCES venues(id) ON DELETE CASCADE
);

-- ─────────────────────────────────────────────
-- Sample Players (password: Player@1234)
-- ─────────────────────────────────────────────
INSERT IGNORE INTO players (name, email, phone, password_hash, status) VALUES
('Anil Maharjan', 'anil@example.com', '9841111111', '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TiBO/ZBbDEv5lOqtlw7wTrBqCqVy', 'active'),
('Sunita Thapa', 'sunita@example.com', '9852222222', '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TiBO/ZBbDEv5lOqtlw7wTrBqCqVy', 'active'),
('Rohan KC', 'rohan@example.com', '9863333333', '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TiBO/ZBbDEv5lOqtlw7wTrBqCqVy', 'active');
