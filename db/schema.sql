-- MeroMaidan SaaS Platform - MySQL Schema
-- Run this in phpMyAdmin or via: mysql -u root < schema.sql

CREATE DATABASE IF NOT EXISTS meromaidan CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE meromaidan;

-- ─────────────────────────────────────────────
-- Single annual venue subscription
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS subscription_plans (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  slug VARCHAR(50) NOT NULL UNIQUE,
  price_monthly DECIMAL(10,2) NOT NULL DEFAULT 0,
  price_yearly DECIMAL(10,2) NOT NULL DEFAULT 9999,
  duration_months INT NOT NULL DEFAULT 12,
  included_venues INT NOT NULL DEFAULT 1,
  max_venues INT NOT NULL DEFAULT 1,
  max_bookings_per_month INT NOT NULL DEFAULT 50,
  features JSON,
  is_active TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ─────────────────────────────────────────────
-- Venue Owners
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS venue_owners (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(200) NOT NULL,
  email VARCHAR(200) NOT NULL UNIQUE,
  phone VARCHAR(20) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  business_name VARCHAR(200),
  plan_id INT DEFAULT 1,
  status ENUM('pending','active','suspended') DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  approved_at TIMESTAMP NULL,
  FOREIGN KEY (plan_id) REFERENCES subscription_plans(id)
);

-- ─────────────────────────────────────────────
-- Venues
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS venues (
  id INT AUTO_INCREMENT PRIMARY KEY,
  owner_id INT,
  name VARCHAR(200) NOT NULL,
  slug VARCHAR(200) NOT NULL UNIQUE,
  sport_type ENUM('Football','Futsal','Cricket','Cricsal','Multi-Sport') NOT NULL DEFAULT 'Futsal',
  address TEXT NOT NULL,
  city VARCHAR(100) NOT NULL DEFAULT 'Kathmandu',
  district VARCHAR(100) NOT NULL DEFAULT 'Kathmandu',
  lat DECIMAL(10,8) DEFAULT NULL,
  lng DECIMAL(11,8) DEFAULT NULL,
  description TEXT,
  amenities JSON,
  images JSON,
  cover_image VARCHAR(500),
  open_time TIME DEFAULT '06:00:00',
  close_time TIME DEFAULT '22:00:00',
  price_per_hour DECIMAL(10,2) NOT NULL DEFAULT 1000,
  capacity VARCHAR(50) DEFAULT '5-a-side',
  status ENUM('pending','active','suspended') DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (owner_id) REFERENCES venue_owners(id) ON DELETE SET NULL
);

-- ─────────────────────────────────────────────
-- Venue Time Slots
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS venue_slots (
  id INT AUTO_INCREMENT PRIMARY KEY,
  venue_id INT NOT NULL,
  day_of_week TINYINT(1) NOT NULL COMMENT '0=Sun,1=Mon,...,6=Sat',
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  base_price DECIMAL(10,2) NULL,
  coupon_id BIGINT NULL,
  coupon_code VARCHAR(50) NULL,
  discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
  fees_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
  tax_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
  price DECIMAL(10,2) NOT NULL,
  is_available TINYINT(1) DEFAULT 1,
  FOREIGN KEY (venue_id) REFERENCES venues(id) ON DELETE CASCADE
);

-- ─────────────────────────────────────────────
-- Bookings
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS bookings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  venue_id INT NOT NULL,
  customer_name VARCHAR(200) NOT NULL,
  customer_phone VARCHAR(20) NOT NULL,
  customer_email VARCHAR(200),
  booking_date DATE NOT NULL,
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  total_price DECIMAL(10,2) NOT NULL,
  status ENUM('pending','confirmed','cancelled','completed') DEFAULT 'confirmed',
  payment_method ENUM('cash','esewa','khalti','card') DEFAULT 'cash',
  notes TEXT,
  booking_ref VARCHAR(20) UNIQUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (venue_id) REFERENCES venues(id) ON DELETE CASCADE
);

-- ─────────────────────────────────────────────
-- Super Admins
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS superadmins (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(200) NOT NULL,
  email VARCHAR(200) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ─────────────────────────────────────────────
-- Owner Applications (List My Ground form)
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS owner_applications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  owner_name VARCHAR(200) NOT NULL,
  business_name VARCHAR(200) NOT NULL,
  email VARCHAR(200) NOT NULL,
  phone VARCHAR(20) NOT NULL,
  sport_type VARCHAR(100),
  venue_name VARCHAR(200),
  venue_address TEXT,
  city VARCHAR(100),
  district VARCHAR(100),
  lat DECIMAL(10,8) DEFAULT NULL,
  lng DECIMAL(11,8) DEFAULT NULL,
  price_per_hour DECIMAL(10,2),
  open_time VARCHAR(10),
  close_time VARCHAR(10),
  amenities JSON,
  plan_selected VARCHAR(50),
  status ENUM('new','reviewed','approved','rejected') DEFAULT 'new',
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ─────────────────────────────────────────────
-- Sessions (PHP handles this, but log admin sessions)
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS admin_sessions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  user_type ENUM('superadmin','owner') NOT NULL,
  session_token VARCHAR(255),
  ip_address VARCHAR(45),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  expires_at TIMESTAMP NULL
);
