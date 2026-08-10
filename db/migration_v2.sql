-- MeroMaidan v2 - player accounts, favourites, audit, and maintenance.
USE meromaidan;

CREATE TABLE IF NOT EXISTS players (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(200) NOT NULL,
  email VARCHAR(200) NOT NULL UNIQUE,
  phone VARCHAR(20),
  password_hash VARCHAR(255) NOT NULL,
  status ENUM('pending','active','suspended') DEFAULT 'active',
  avatar VARCHAR(500) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE bookings ADD COLUMN IF NOT EXISTS player_id INT NULL AFTER venue_id;

CREATE TABLE IF NOT EXISTS player_favorites (
  id INT AUTO_INCREMENT PRIMARY KEY,
  player_id INT NOT NULL,
  venue_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_player_favourite (player_id,venue_id),
  CONSTRAINT fk_favourite_player FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
  CONSTRAINT fk_favourite_venue FOREIGN KEY (venue_id) REFERENCES venues(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_audit_tenant (tenant_id,module,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS maintenance_blocks (
  id INT AUTO_INCREMENT PRIMARY KEY,
  venue_id INT NOT NULL,
  block_date DATE NOT NULL,
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  reason VARCHAR(500),
  created_by_owner INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_maintenance_venue FOREIGN KEY (venue_id) REFERENCES venues(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO players (name,email,phone,password_hash,status) VALUES
('Anil Maharjan','anil@example.com','9841111111','$2y$10$UYoWUXTNRRoAUppeRQeOMeSndVjzeSZTD7v.Bw9ewZpRzxmkwno4m','active'),
('Sunita Thapa','sunita@example.com','9852222222','$2y$10$UYoWUXTNRRoAUppeRQeOMeSndVjzeSZTD7v.Bw9ewZpRzxmkwno4m','active'),
('Rohan KC','rohan@example.com','9863333333','$2y$10$UYoWUXTNRRoAUppeRQeOMeSndVjzeSZTD7v.Bw9ewZpRzxmkwno4m','active');
