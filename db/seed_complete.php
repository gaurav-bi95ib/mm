<?php
// MeroMaidan - Full Database Reseeder and Test Data Generator
// Run this file to reset the database and populate it with rich, realistic mock data.

require_once __DIR__ . '/../api/db.php';

try {
    // 1. Establish connection to MySQL
    $pdo = new PDO('mysql:host=' . DB_HOST, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    echo "Recreating database meromaidan...\n";
    $pdo->exec("DROP DATABASE IF EXISTS meromaidan;");
    $pdo->exec("CREATE DATABASE meromaidan CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
    $pdo->exec("USE meromaidan;");

    // 2. Recreate Tables
    echo "Creating subscription_plans...\n";
    $pdo->exec("CREATE TABLE subscription_plans (
      id INT AUTO_INCREMENT PRIMARY KEY,
      name VARCHAR(100) NOT NULL,
      slug VARCHAR(50) NOT NULL UNIQUE,
      price_monthly DECIMAL(10,2) NOT NULL DEFAULT 0,
      max_venues INT NOT NULL DEFAULT 1,
      max_bookings_per_month INT NOT NULL DEFAULT 50,
      features JSON,
      is_active TINYINT(1) DEFAULT 1,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );");

    echo "Creating venue_owners...\n";
    $pdo->exec("CREATE TABLE venue_owners (
      id INT AUTO_INCREMENT PRIMARY KEY,
      name VARCHAR(200) NOT NULL,
      email VARCHAR(200) NOT NULL UNIQUE,
      phone VARCHAR(20) NOT NULL,
      password_hash VARCHAR(255) NOT NULL,
      business_name VARCHAR(200),
      plan_id INT DEFAULT 1,
      status ENUM('pending','active','suspended') DEFAULT 'pending',
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      approved_at TIMESTAMP NULL
    );");

    echo "Creating venues...\n";
    $pdo->exec("CREATE TABLE venues (
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
      featured TINYINT(1) DEFAULT 0,
      rating DECIMAL(3,2) DEFAULT 0.00,
      total_reviews INT DEFAULT 0,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      FOREIGN KEY (owner_id) REFERENCES venue_owners(id) ON DELETE SET NULL
    );");

    echo "Creating players...\n";
    $pdo->exec("CREATE TABLE players (
      id INT AUTO_INCREMENT PRIMARY KEY,
      name VARCHAR(200) NOT NULL,
      email VARCHAR(200) NOT NULL UNIQUE,
      phone VARCHAR(20),
      password_hash VARCHAR(255) NOT NULL,
      status ENUM('pending','active','suspended') DEFAULT 'active',
      avatar VARCHAR(500) DEFAULT NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );");

    echo "Creating bookings...\n";
    $pdo->exec("CREATE TABLE bookings (
      id INT AUTO_INCREMENT PRIMARY KEY,
      venue_id INT NOT NULL,
      player_id INT NULL,
      customer_name VARCHAR(200) NOT NULL,
      customer_phone VARCHAR(20) NOT NULL,
      customer_email VARCHAR(200),
      booking_date DATE NOT NULL,
      start_time TIME NOT NULL,
      end_time TIME NOT NULL,
      total_price DECIMAL(10,2) NOT NULL,
      status ENUM('pending','confirmed','cancelled','completed','checked_in','in_progress','no_show') DEFAULT 'confirmed',
      payment_method ENUM('cash','esewa','khalti','card') DEFAULT 'cash',
      notes TEXT,
      booking_ref VARCHAR(20) UNIQUE,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      FOREIGN KEY (venue_id) REFERENCES venues(id) ON DELETE CASCADE,
      FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE SET NULL
    );");

    echo "Creating venue_slots...\n";
    $pdo->exec("CREATE TABLE venue_slots (
      id INT AUTO_INCREMENT PRIMARY KEY,
      venue_id INT NOT NULL,
      day_of_week TINYINT(1) NOT NULL COMMENT '0=Sun,1=Mon,...,6=Sat',
      start_time TIME NOT NULL,
      end_time TIME NOT NULL,
      price DECIMAL(10,2) NOT NULL,
      is_available TINYINT(1) DEFAULT 1,
      FOREIGN KEY (venue_id) REFERENCES venues(id) ON DELETE CASCADE
    );");

    echo "Creating superadmins...\n";
    $pdo->exec("CREATE TABLE superadmins (
      id INT AUTO_INCREMENT PRIMARY KEY,
      name VARCHAR(200) NOT NULL,
      email VARCHAR(200) NOT NULL UNIQUE,
      password_hash VARCHAR(255) NOT NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );");

    echo "Creating owner_applications...\n";
    $pdo->exec("CREATE TABLE owner_applications (
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
    );");

    echo "Creating player_favorites...\n";
    $pdo->exec("CREATE TABLE player_favorites (
      id INT AUTO_INCREMENT PRIMARY KEY,
      player_id INT NOT NULL,
      venue_id INT NOT NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      UNIQUE KEY unique_fav (player_id, venue_id),
      FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
      FOREIGN KEY (venue_id) REFERENCES venues(id) ON DELETE CASCADE
    );");

    echo "Creating reviews...\n";
    $pdo->exec("CREATE TABLE reviews (
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
    );");

    echo "Creating promotions...\n";
    $pdo->exec("CREATE TABLE promotions (
      id INT AUTO_INCREMENT PRIMARY KEY,
      owner_id INT NOT NULL,
      venue_id INT NULL,
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
    );");

    echo "Creating audit_logs...\n";
    $pdo->exec("CREATE TABLE audit_logs (
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
    );");

    echo "Creating maintenance_blocks...\n";
    $pdo->exec("CREATE TABLE maintenance_blocks (
      id INT AUTO_INCREMENT PRIMARY KEY,
      venue_id INT NOT NULL,
      block_date DATE NOT NULL,
      start_time TIME NOT NULL,
      end_time TIME NOT NULL,
      reason VARCHAR(500),
      created_by_owner INT,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      FOREIGN KEY (venue_id) REFERENCES venues(id) ON DELETE CASCADE
    );");

    echo "Creating notifications...\n";
    $pdo->exec("CREATE TABLE notifications (
      id INT AUTO_INCREMENT PRIMARY KEY,
      user_id INT NULL,
      role ENUM('player','owner','superadmin') NOT NULL,
      tenant_id INT NULL,
      title VARCHAR(255) NOT NULL,
      message TEXT NOT NULL,
      type ENUM('booking','payment','system','maintenance','review') DEFAULT 'booking',
      is_read TINYINT(1) DEFAULT 0,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );");

    echo "Creating user_notification_prefs...\n";
    $pdo->exec("CREATE TABLE user_notification_prefs (
      id INT AUTO_INCREMENT PRIMARY KEY,
      user_id INT NOT NULL,
      role ENUM('player','owner','superadmin') NOT NULL,
      email_notify TINYINT(1) DEFAULT 1,
      sms_notify TINYINT(1) DEFAULT 1,
      inapp_notify TINYINT(1) DEFAULT 1,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      UNIQUE KEY unique_user_role (user_id, role)
    );");

    echo "Creating mock_esewa_transactions...\n";
    $pdo->exec("CREATE TABLE mock_esewa_transactions (
      id INT AUTO_INCREMENT PRIMARY KEY,
      booking_id INT NOT NULL,
      transaction_code VARCHAR(100) UNIQUE NOT NULL,
      amount DECIMAL(10,2) NOT NULL,
      esewa_phone VARCHAR(20),
      status ENUM('pending','completed','failed') DEFAULT 'completed',
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
    );");

    echo "Creating invoices...\n";
    $pdo->exec("CREATE TABLE invoices (
      id INT AUTO_INCREMENT PRIMARY KEY,
      booking_id INT NOT NULL UNIQUE,
      invoice_no VARCHAR(50) UNIQUE NOT NULL,
      total_amount DECIMAL(10,2) NOT NULL,
      tax_amount DECIMAL(10,2) DEFAULT 0.00,
      discount_amount DECIMAL(10,2) DEFAULT 0.00,
      net_amount DECIMAL(10,2) NOT NULL,
      payment_method VARCHAR(50) DEFAULT 'esewa',
      status ENUM('paid','unpaid','refunded') DEFAULT 'paid',
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
    );");

    echo "Creating tenant_staff...\n";
    $pdo->exec("CREATE TABLE tenant_staff (
      id INT AUTO_INCREMENT PRIMARY KEY,
      owner_id INT NOT NULL,
      name VARCHAR(200) NOT NULL,
      email VARCHAR(200) NOT NULL UNIQUE,
      phone VARCHAR(20),
      password_hash VARCHAR(255) NOT NULL,
      role ENUM('Manager','Receptionist','Field Admin','Ground Supervisor') DEFAULT 'Field Admin',
      assigned_venue_id INT NULL,
      status ENUM('active','suspended') DEFAULT 'active',
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      FOREIGN KEY (owner_id) REFERENCES venue_owners(id) ON DELETE CASCADE,
      FOREIGN KEY (assigned_venue_id) REFERENCES venues(id) ON DELETE SET NULL
    );");

    echo "Creating cms_content...\n";
    $pdo->exec("CREATE TABLE cms_content (
      id INT AUTO_INCREMENT PRIMARY KEY,
      page_slug VARCHAR(100) NOT NULL,
      section_key VARCHAR(100) NOT NULL UNIQUE,
      title VARCHAR(255) NOT NULL,
      content_text TEXT,
      updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    );");

    // 3. Insert Base Data
    $commonPassword = password_hash('Admin@1234', PASSWORD_BCRYPT);

    echo "Seeding subscription plans...\n";
    $pdo->exec("INSERT INTO subscription_plans (name, slug, price_monthly, max_venues, max_bookings_per_month, features) VALUES
    ('Free', 'free', 0, 1, 30, '[\"Basic listing\",\"1 venue\",\"Email support\"]'),
    ('Standard', 'standard', 1499, 3, 200, '[\"3 venues\",\"Priority listing\",\"Analytics\",\"WhatsApp alerts\"]'),
    ('Premium', 'premium', 3999, 999, 9999, '[\"Unlimited venues\",\"Top placement\",\"Advanced analytics\",\"Dedicated support\",\"Custom branding\"]');");

    echo "Seeding superadmins...\n";
    $pdo->exec("INSERT INTO superadmins (name, email, password_hash) VALUES
    ('MeroMaidan Admin', 'admin@meromaidan.com', '$commonPassword');");

    echo "Seeding venue owners...\n";
    $pdo->exec("INSERT INTO venue_owners (id, name, email, phone, password_hash, business_name, plan_id, status, approved_at) VALUES
    (1, 'Ramesh Shrestha', 'ramesh@royalfutsal.com', '9841234567', '$commonPassword', 'Royal Futsal Pvt Ltd', 3, 'active', NOW()),
    (2, 'Sita Karki', 'sita@greenfield.com', '9851234567', '$commonPassword', 'Green Field Sports', 2, 'active', NOW()),
    (3, 'Bikash Tamang', 'bikash@kathmandufutsal.com', '9861234567', '$commonPassword', 'Kathmandu Futsal Center', 1, 'active', NOW());");

    echo "Seeding venues...\n";
    $pdo->exec("INSERT INTO venues (id, owner_id, name, slug, sport_type, address, city, district, lat, lng, description, amenities, images, cover_image, open_time, close_time, price_per_hour, capacity, status, featured, rating, total_reviews) VALUES
    (1, 1, 'Royal Futsal', 'royal-futsal', 'Futsal', 'Thapagaun, Anamnagar', 'Kathmandu', 'Kathmandu', 27.7036, 85.3199, 'Premium indoor futsal ground with world-class turf.', '[\"Changing Room\",\"Parking\",\"Drinking Water\"]', '[]', 'https://images.unsplash.com/photo-1529900748604-07564a03e7a6?auto=format&fit=crop&w=1200&q=80', '06:00:00', '23:00:00', 1500, '5-a-side', 'active', 1, 4.8, 1),
    (2, 2, 'Green Field Football Ground', 'green-field-football', 'Football', 'Lagankhel, Lalitpur', 'Lalitpur', 'Lalitpur', 27.6679, 85.3169, 'Full-size professional football ground.', '[\"Parking\",\"Cafeteria\",\"Drinking Water\"]', '[]', 'https://images.unsplash.com/photo-1508098682722-e99c43a406b2?auto=format&fit=crop&w=1200&q=80', '06:00:00', '21:00:00', 2500, '11-a-side', 'active', 1, 4.5, 1),
    (3, 3, 'Kathmandu Futsal Center', 'kathmandu-futsal-center', 'Futsal', 'New Baneshwor, Kathmandu', 'Kathmandu', 'Kathmandu', 27.6929, 85.3385, 'Budget-friendly futsal ground.', '[\"Parking\"]', '[]', 'https://images.unsplash.com/photo-1574629810360-7efbbe195018?auto=format&fit=crop&w=1200&q=80', '10:00:00', '22:00:00', 800, '5-a-side', 'active', 0, 4.0, 0);");

    echo "Seeding venue slots...\n";
    // Seed slots for Royal Futsal (id=1) for all days of the week
    for ($day = 0; $day <= 6; $day++) {
        $pdo->exec("INSERT INTO venue_slots (venue_id, day_of_week, start_time, end_time, price) VALUES
        (1, $day, '06:00:00', '07:00:00', 1500),
        (1, $day, '07:00:00', '08:00:00', 1500),
        (1, $day, '08:00:00', '09:00:00', 1500),
        (1, $day, '18:00:00', '19:00:00', 2000),
        (1, $day, '19:00:00', '20:00:00', 2500),
        (1, $day, '20:00:00', '21:00:00', 2500);");
    }

    echo "Seeding players...\n";
    $pdo->exec("INSERT INTO players (id, name, email, phone, password_hash, status) VALUES
    (1, 'Anil Maharjan', 'anil@example.com', '9841111111', '$commonPassword', 'active'),
    (2, 'Sunita Thapa', 'sunita@example.com', '9852222222', '$commonPassword', 'active'),
    (3, 'Rohan KC', 'rohan@example.com', '9863333333', '$commonPassword', 'active');");

    echo "Seeding promotions...\n";
    $pdo->exec("INSERT INTO promotions (owner_id, venue_id, title, code, type, value, valid_from, valid_to, is_active) VALUES
    (1, 1, 'Kickoff Discount', 'KICKOFF', 'percentage', 10.00, '2026-01-01', '2027-12-31', 1),
    (1, 1, 'Flat 500 Off', 'FLAT500', 'fixed', 500.00, '2026-01-01', '2027-12-31', 1);");

    echo "Seeding bookings...\n";
    // 1 Past completed booking for review
    $pdo->exec("INSERT INTO bookings (id, venue_id, player_id, customer_name, customer_phone, customer_email, booking_date, start_time, end_time, total_price, status, payment_method, booking_ref) VALUES
    (1, 1, 1, 'Anil Maharjan', '9841111111', 'anil@example.com', '2026-08-01', '06:00:00', '07:00:00', 1500.00, 'completed', 'cash', 'MM080101');");

    // 1 Upcoming confirmed booking
    $pdo->exec("INSERT INTO bookings (id, venue_id, player_id, customer_name, customer_phone, customer_email, booking_date, start_time, end_time, total_price, status, payment_method, booking_ref) VALUES
    (2, 1, 1, 'Anil Maharjan', '9841111111', 'anil@example.com', '" . date('Y-m-d', strtotime('+2 days')) . "', '19:00:00', '20:00:00', 2500.00, 'confirmed', 'esewa', 'MM080802');");

    // 1 Today's booking for Check-in flow testing
    $pdo->exec("INSERT INTO bookings (id, venue_id, player_id, customer_name, customer_phone, customer_email, booking_date, start_time, end_time, total_price, status, payment_method, booking_ref) VALUES
    (3, 1, 2, 'Sunita Thapa', '9852222222', 'sunita@example.com', '" . date('Y-m-d') . "', '08:00:00', '09:00:00', 1500.00, 'confirmed', 'khalti', 'MM080803');");

    echo "Seeding reviews...\n";
    $pdo->exec("INSERT INTO reviews (venue_id, player_id, booking_id, rating, review_text, status) VALUES
    (1, 1, 1, 5, 'World class facilities and smooth experience booking online!', 'approved');");

    echo "Seeding favorite venues...\n";
    $pdo->exec("INSERT INTO player_favorites (player_id, venue_id) VALUES (1, 1);");

    echo "Seeding pending vendor applications...\n";
    $pdo->exec("INSERT INTO owner_applications (owner_name, business_name, email, phone, sport_type, venue_name, city, plan_selected, status) VALUES
    ('Puskar Lal', 'Balkumari Arena', 'puskar@balkumari.com', '9811223344', 'Futsal', 'Balkumari Futsal Club', 'Lalitpur', 'standard', 'new');");

    echo "Seeding maintenance blocks...\n";
    $pdo->exec("INSERT INTO maintenance_blocks (venue_id, block_date, start_time, end_time, reason, created_by_owner) VALUES
    (1, '" . date('Y-m-d', strtotime('+1 day')) . "', '06:00:00', '08:00:00', 'Weekly pitch cleaning and turf brushing', 1);");

    echo "Seeding audit logs...\n";
    $pdo->exec("INSERT INTO audit_logs (actor_type, actor_id, actor_name, action, description) VALUES
    ('system', NULL, 'System', 'database_reset', 'Fully reset and populated test databases for MeroMaidan.');");

    echo "Seeding tenant staff...\n";
    $pdo->exec("INSERT INTO tenant_staff (owner_id, name, email, phone, password_hash, role, assigned_venue_id) VALUES
    (1, 'Hari Karki', 'hari@royalfutsal.com', '9841999888', '$commonPassword', 'Field Admin', 1),
    (1, 'Gopal Subedi', 'gopal@royalfutsal.com', '9841777666', '$commonPassword', 'Receptionist', 1);");

    echo "Seeding CMS content...\n";
    $pdo->exec("INSERT INTO cms_content (page_slug, section_key, title, content_text) VALUES
    ('home', 'hero_banner', 'Book Nepal\'s Top Sports Grounds Instantly', 'Discover 200+ top-rated futsal courts, football pitches, and cricket grounds in Nepal with real-time slot availability.'),
    ('home', 'announcement_bar', 'Grand Opening Offer!', 'Get 20% OFF on your first booking using coupon code KICKOFF at checkout.');");

    echo "Seeding mock eSewa transactions and invoices...\n";
    $pdo->exec("INSERT INTO mock_esewa_transactions (booking_id, transaction_code, amount, esewa_phone, status) VALUES
    (2, 'ESEWA-MM-89213', 2500.00, '9841111111', 'completed');");

    $pdo->exec("INSERT INTO invoices (booking_id, invoice_no, total_amount, tax_amount, discount_amount, net_amount, payment_method, status) VALUES
    (1, 'INV-2026-001', 1500.00, 0.00, 0.00, 1500.00, 'cash', 'paid'),
    (2, 'INV-2026-002', 2500.00, 0.00, 0.00, 2500.00, 'esewa', 'paid');");

    echo "Seeding initial notifications...\n";
    $pdo->exec("INSERT INTO notifications (user_id, role, tenant_id, title, message, type) VALUES
    (1, 'player', NULL, 'Booking Confirmed!', 'Your booking for Royal Futsal on " . date('Y-m-d', strtotime('+2 days')) . " (19:00 - 20:00) is confirmed via eSewa.', 'booking'),
    (1, 'owner', 1, 'New eSewa Booking', 'Anil Maharjan booked Royal Futsal for Rs. 2,500 via eSewa.', 'payment'),
    (NULL, 'superadmin', NULL, 'Platform Alert', 'New owner application submitted by Balkumari Arena.', 'system');");

    echo "\n🎉 DATABASE SUCCESSFULLY SEEDED WITH COMPREHENSIVE TEST DATA!\n";

} catch (PDOException $e) {
    die("❌ Database seeding error: " . $e->getMessage() . "\n");
}
