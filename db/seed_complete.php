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
      price_yearly DECIMAL(10,2) NOT NULL DEFAULT 9999,
      duration_months INT NOT NULL DEFAULT 12,
      included_venues INT NOT NULL DEFAULT 1,
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
      type ENUM('booking','payment','system','maintenance') DEFAULT 'booking',
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
      content_type VARCHAR(40) NOT NULL DEFAULT 'general',
      title VARCHAR(255) NOT NULL,
      subtitle VARCHAR(500),
      content_text TEXT,
      image_url VARCHAR(1000),
      button_text VARCHAR(100),
      button_url VARCHAR(500),
      is_published TINYINT(1) NOT NULL DEFAULT 1,
      sort_order INT NOT NULL DEFAULT 0,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    );");

    // 3. Insert Base Data
    $commonPassword = password_hash('Admin@1234', PASSWORD_BCRYPT);

    echo "Seeding the annual venue subscription...\n";
    $pdo->exec("INSERT INTO subscription_plans (id,name,slug,price_monthly,price_yearly,duration_months,included_venues,max_venues,max_bookings_per_month,features) VALUES
    (1,'Annual Venue Subscription','annual-venue',0,9999,12,1,1,9999,'[\"Manage one venue\",\"Grounds and courts\",\"Photos and facilities\",\"Operating hours and slots\",\"Bookings\",\"Staff operations\",\"Reports\"]');");

    echo "Seeding superadmins...\n";
    $pdo->exec("INSERT INTO superadmins (name, email, password_hash) VALUES
    ('MeroMaidan Admin', 'admin@meromaidan.com', '$commonPassword');");

    echo "Seeding venue owners...\n";
    $pdo->exec("INSERT INTO venue_owners (id, name, email, phone, password_hash, business_name, plan_id, status, approved_at) VALUES
    (1, 'Ramesh Shrestha', 'ramesh@royalfutsal.com', '9841234567', '$commonPassword', 'Royal Futsal Pvt Ltd', 1, 'active', NOW()),
    (2, 'Sita Karki', 'sita@greenfield.com', '9851234567', '$commonPassword', 'Green Field Sports', 1, 'active', NOW()),
    (3, 'Bikash Tamang', 'bikash@kathmandufutsal.com', '9861234567', '$commonPassword', 'Kathmandu Futsal Center', 1, 'active', NOW());");

    echo "Seeding venues...\n";
    $pdo->exec("INSERT INTO venues (id, owner_id, name, slug, sport_type, address, city, district, lat, lng, description, amenities, images, cover_image, open_time, close_time, price_per_hour, capacity, status) VALUES
    (1, 1, 'Royal Futsal', 'royal-futsal', 'Futsal', 'Thapagaun, Anamnagar', 'Kathmandu', 'Kathmandu', 27.7036, 85.3199, 'Indoor futsal ground with quality turf.', '[\"Changing Room\",\"Parking\",\"Drinking Water\"]', '[]', 'https://images.unsplash.com/photo-1529900748604-07564a03e7a6?auto=format&fit=crop&w=1200&q=80', '06:00:00', '23:00:00', 1500, '5-a-side', 'active'),
    (2, 2, 'Green Field Football Ground', 'green-field-football', 'Football', 'Lagankhel, Lalitpur', 'Lalitpur', 'Lalitpur', 27.6679, 85.3169, 'Full-size football ground.', '[\"Parking\",\"Cafeteria\",\"Drinking Water\"]', '[]', 'https://images.unsplash.com/photo-1508098682722-e99c43a406b2?auto=format&fit=crop&w=1200&q=80', '06:00:00', '21:00:00', 2500, '11-a-side', 'active'),
    (3, 3, 'Kathmandu Futsal Center', 'kathmandu-futsal-center', 'Futsal', 'New Baneshwor, Kathmandu', 'Kathmandu', 'Kathmandu', 27.6929, 85.3385, 'Affordable futsal ground.', '[\"Parking\"]', '[]', 'https://images.unsplash.com/photo-1574629810360-7efbbe195018?auto=format&fit=crop&w=1200&q=80', '10:00:00', '22:00:00', 800, '5-a-side', 'active');");

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

    echo "Seeding bookings...\n";
    // One past completed booking
    $pdo->exec("INSERT INTO bookings (id, venue_id, player_id, customer_name, customer_phone, customer_email, booking_date, start_time, end_time, total_price, status, payment_method, booking_ref) VALUES
    (1, 1, 1, 'Anil Maharjan', '9841111111', 'anil@example.com', '2026-08-01', '06:00:00', '07:00:00', 1500.00, 'completed', 'cash', 'MM080101');");

    // 1 Upcoming confirmed booking
    $pdo->exec("INSERT INTO bookings (id, venue_id, player_id, customer_name, customer_phone, customer_email, booking_date, start_time, end_time, total_price, status, payment_method, booking_ref) VALUES
    (2, 1, 1, 'Anil Maharjan', '9841111111', 'anil@example.com', '" . date('Y-m-d', strtotime('+2 days')) . "', '19:00:00', '20:00:00', 2500.00, 'confirmed', 'esewa', 'MM080802');");

    // 1 Today's booking for Check-in flow testing
    $pdo->exec("INSERT INTO bookings (id, venue_id, player_id, customer_name, customer_phone, customer_email, booking_date, start_time, end_time, total_price, status, payment_method, booking_ref) VALUES
    (3, 1, 2, 'Sunita Thapa', '9852222222', 'sunita@example.com', '" . date('Y-m-d') . "', '08:00:00', '09:00:00', 1500.00, 'confirmed', 'khalti', 'MM080803');");

    echo "Seeding favorite venues...\n";
    $pdo->exec("INSERT INTO player_favorites (player_id, venue_id) VALUES (1, 1);");

    echo "Seeding pending vendor applications...\n";
    $pdo->exec("INSERT INTO owner_applications (owner_name, business_name, email, phone, sport_type, venue_name, city, plan_selected, status) VALUES
    ('Puskar Lal', 'Balkumari Arena', 'puskar@balkumari.com', '9811223344', 'Futsal', 'Balkumari Futsal Club', 'Lalitpur', 'annual-venue', 'new');");

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
    $pdo->exec("INSERT INTO cms_content (page_slug, section_key, content_type, title, subtitle, content_text, image_url, button_text, button_url, sort_order) VALUES
    ('home', 'hero_banner', 'hero', 'Find and book sports grounds across Nepal', 'Football, futsal, cricket, and cricsal venues in one marketplace.', 'Discover futsal courts, football pitches, and cricket grounds across Nepal with live slot availability.', 'https://images.unsplash.com/photo-1546519638-68e109498ffc?auto=format&fit=crop&w=1600&q=85', 'Find a ground', '#services', 1),
    ('home', 'hero_slide_2', 'hero', 'Your next game is one swipe away.', 'Real venues. Live availability. Instant booking.', 'Search by sport and location, compare verified grounds, and reserve the right time in a few simple taps.', 'https://images.unsplash.com/photo-1529900748604-07564a03e7a6?auto=format&fit=crop&w=1600&q=85', 'Explore venues', '#services', 2),
    ('home', 'hero_slide_3', 'hero', 'More bookings. Better venue operations.', 'A smarter platform for Nepal’s sports businesses.', 'Give your ground the visibility it deserves and manage slots, customers, payments, and performance from one place.', 'https://images.unsplash.com/photo-1574629810360-7efbbe195018?auto=format&fit=crop&w=1600&q=85', 'List your ground', 'list-ground.php', 3),
    ('home', 'about_section', 'content', 'About MeroMaidan', 'Your Ultimate Sports Ground Booking Platform in Nepal', 'We\'re on a mission to make sports accessible to everyone by providing a seamless platform to discover and book the best sports grounds across Nepal.', NULL, NULL, NULL, 20),
    ('home', 'cta_section', 'call_to_action', 'Ready to Play?', NULL, 'Join thousands of sports enthusiasts who trust MeroMaidan for their game bookings', NULL, 'Book a Ground Now', '#groundsGrid', 40); ");

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

    echo "Applying the commercial model and product cleanup migrations...\n";
    $pdo->exec(file_get_contents(__DIR__ . '/migration_v4_business_model.sql'));
    $pdo->exec(file_get_contents(__DIR__ . '/migration_v5_remove_feedback_legacy_promotions.sql'));

    echo "Seeding the live Event Promotion demo...\n";
    $pdo->exec("INSERT INTO event_promotions (tenant_id,owner_id,venue_id,title,short_description,event_date,promotion_starts_at,promotion_expires_at,discount_label,cta_text,amount_npr,status,approved_by,approved_at)
        VALUES (1,1,1,'Kathmandu Futsal Week - Demo','Book Royal Futsal this week and save 15% with the event coupon.',CURDATE(),NOW(),DATE_ADD(NOW(),INTERVAL 7 DAY),'15% off with KTFUTSAL15','Book Now',2000,'active',1,NOW())");
    $demoEventId = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO promotion_hero_banners (event_promotion_id,image_url,alt_text,is_published) VALUES (?,'uploads/promotions/demo-dashain-futsal-1600x600.png','Kathmandu Futsal Week event banner',1)")->execute([$demoEventId]);
    $pdo->prepare("INSERT INTO coupons (tenant_id,owner_id,venue_id,event_promotion_id,code,discount_type,discount_value,minimum_booking_amount,maximum_discount_amount,usage_limit,usage_limit_per_player,valid_from,valid_to,status) VALUES (1,1,1,?,'KTFUTSAL15','percentage',15,1000,300,100,1,NOW(),DATE_ADD(NOW(),INTERVAL 7 DAY),'active')")->execute([$demoEventId]);
    $pdo->prepare("INSERT INTO promotion_payments (tenant_id,owner_id,service_type,service_id,amount_npr,payment_method,provider_reference,status,paid_at) VALUES (1,1,'event_promotion',?,2000,'esewa','ESEWA-PROMO-DEMO','paid',NOW())")->execute([$demoEventId]);
    echo "\nDatabase successfully seeded with comprehensive test data and a live Event Promotion demo.\n";

} catch (PDOException $e) {
    die("❌ Database seeding error: " . $e->getMessage() . "\n");
}
