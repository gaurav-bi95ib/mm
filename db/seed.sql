-- MeroMaidan - Seed Data
USE meromaidan;

-- Subscription Plans
INSERT INTO subscription_plans (name, slug, price_monthly, max_venues, max_bookings_per_month, features) VALUES
('Free', 'free', 0, 1, 30, '["Basic listing","1 venue","Email support"]'),
('Standard', 'standard', 1499, 3, 200, '["3 venues","Priority listing","Analytics","WhatsApp alerts"]'),
('Premium', 'premium', 3999, 999, 9999, '["Unlimited venues","Top placement","Advanced analytics","Dedicated support","Custom branding"]');

-- Super Admin (password: Admin@1234)
INSERT INTO superadmins (name, email, password_hash) VALUES
('MeroMaidan Admin', 'admin@meromaidan.com', '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TiBO/ZBbDEv5lOqtlw7wTrBqCqVy');

-- Sample Owner (password: Owner@1234)
INSERT INTO venue_owners (name, email, phone, password_hash, business_name, plan_id, status, approved_at) VALUES
('Ramesh Shrestha', 'ramesh@royalfutsal.com', '9841234567', '$2y$12$abc123hash456owner789xyz', 'Royal Futsal Pvt Ltd', 3, 'active', NOW()),
('Sita Karki', 'sita@greenfield.com', '9851234567', '$2y$12$def456hash789owner012abc', 'Green Field Sports', 2, 'active', NOW()),
('Bikash Tamang', 'bikash@kathmandufutsal.com', '9861234567', '$2y$12$ghi789hash012owner345def', 'Kathmandu Futsal Center', 1, 'active', NOW());

-- Sample Venues
INSERT INTO venues (owner_id, name, slug, sport_type, address, city, district, lat, lng, description, amenities, images, cover_image, open_time, close_time, price_per_hour, capacity, status, featured, rating, total_reviews) VALUES
(1, 'Royal Futsal', 'royal-futsal', 'Futsal', 'Thapagaun, Anamnagar', 'Kathmandu', 'Kathmandu', 27.7036, 85.3199, 'Premium indoor futsal ground with world-class facilities and professional turf. Perfect for competitive matches and training sessions.', '["Changing Room","Parking","CCTV","Floodlights","Drinking Water","First Aid"]', '["https://images.unsplash.com/photo-1529900748604-07564a03e7a6?auto=format&fit=crop&w=1200&q=80","https://images.unsplash.com/photo-1574629810360-7efbbe195018?auto=format&fit=crop&w=600&q=80","https://images.unsplash.com/photo-1508098682722-e99c43a406b2?auto=format&fit=crop&w=600&q=80"]', 'https://images.unsplash.com/photo-1529900748604-07564a03e7a6?auto=format&fit=crop&w=1200&q=80', '06:00:00', '23:00:00', 1500, '5-a-side', 'active', 1, 4.8, 127),

(1, 'Anamnagar Sports Arena', 'anamnagar-sports-arena', 'Futsal', 'Anamnagar Road, Kathmandu', 'Kathmandu', 'Kathmandu', 27.7058, 85.3208, 'Multi-sport indoor arena with futsal, basketball and badminton courts.', '["Locker Room","Cafeteria","Parking","WiFi","AC Waiting Area"]', '["https://images.unsplash.com/photo-1575361204480-aadea25e6e68?auto=format&fit=crop&w=1200&q=80"]', 'https://images.unsplash.com/photo-1575361204480-aadea25e6e68?auto=format&fit=crop&w=1200&q=80', '05:00:00', '22:00:00', 1200, '5-a-side', 'active', 1, 4.5, 89),

(2, 'Green Field Football Ground', 'green-field-football', 'Football', 'Lagankhel, Lalitpur', 'Lalitpur', 'Lalitpur', 27.6679, 85.3169, 'Full-size professional football ground with natural grass and modern facilities.', '["Changing Room","Parking","Floodlights","Drinking Water","Cafeteria"]', '["https://images.unsplash.com/photo-1508098682722-e99c43a406b2?auto=format&fit=crop&w=1200&q=80"]', 'https://images.unsplash.com/photo-1508098682722-e99c43a406b2?auto=format&fit=crop&w=1200&q=80', '06:00:00', '21:00:00', 2500, '11-a-side', 'active', 1, 4.6, 64),

(2, 'Patan Cricket Ground', 'patan-cricket-ground', 'Cricket', 'Mangalbazar, Patan', 'Lalitpur', 'Lalitpur', 27.6726, 85.3256, 'Official cricket pitch with natural turf and proper boundary marking. Ideal for club matches.', '["Pavilion","Changing Room","Parking","Floodlights"]', '["https://images.unsplash.com/photo-1531415074968-036ba1b575da?auto=format&fit=crop&w=1200&q=80"]', 'https://images.unsplash.com/photo-1531415074968-036ba1b575da?auto=format&fit=crop&w=1200&q=80', '07:00:00', '20:00:00', 3000, '11-a-side', 'active', 0, 4.3, 45),

(3, 'Kathmandu Futsal Center', 'kathmandu-futsal-center', 'Futsal', 'New Baneshwor, Kathmandu', 'Kathmandu', 'Kathmandu', 27.6929, 85.3385, 'Budget-friendly futsal ground with good turf quality and evening availability.', '["Parking","Changing Room","Drinking Water"]', '["https://images.unsplash.com/photo-1593025211833-288285511b0e?auto=format&fit=crop&w=1200&q=80"]', 'https://images.unsplash.com/photo-1593025211833-288285511b0e?auto=format&fit=crop&w=1200&q=80', '10:00:00', '22:00:00', 800, '5-a-side', 'active', 0, 4.1, 38),

(3, 'Cricsal Indoor - Baneshwor', 'cricsal-indoor-baneshwor', 'Cricsal', 'Baneshwor, Kathmandu', 'Kathmandu', 'Kathmandu', 27.6915, 85.3401, 'Nepal first dedicated Cricsal indoor facility. Perfect for cricket training in any weather.', '["Indoor AC","Nets","Coaching Available","Parking","Changing Room"]', '["https://images.unsplash.com/photo-1593025211833-288285511b0e?auto=format&fit=crop&w=1200&q=80"]', 'https://images.unsplash.com/photo-1593025211833-288285511b0e?auto=format&fit=crop&w=1200&q=80', '08:00:00', '21:00:00', 1000, 'Indoor Net', 'active', 1, 4.7, 52),

(1, 'Bhaktapur Futsal Zone', 'bhaktapur-futsal-zone', 'Futsal', 'Suryabinayak, Bhaktapur', 'Bhaktapur', 'Bhaktapur', 27.6721, 85.4298, 'Spacious futsal ground near Suryabinayak temple. Great for evening games.', '["Parking","Changing Room","Canteen","Floodlights"]', '["https://images.unsplash.com/photo-1574629810360-7efbbe195018?auto=format&fit=crop&w=1200&q=80"]', 'https://images.unsplash.com/photo-1574629810360-7efbbe195018?auto=format&fit=crop&w=1200&q=80', '07:00:00', '22:00:00', 1200, '5-a-side', 'active', 0, 4.2, 29),

(2, 'Pokhara Lakeside Ground', 'pokhara-lakeside-ground', 'Football', 'Lakeside Road, Pokhara', 'Pokhara', 'Kaski', 28.2096, 83.9856, 'Scenic football ground with views of Phewa Lake. A unique sports experience in Nepal.', '["Changing Room","Parking","Cafeteria","Beautiful View"]', '["https://images.unsplash.com/photo-1551958219-acbc608c6377?auto=format&fit=crop&w=1200&q=80"]', 'https://images.unsplash.com/photo-1551958219-acbc608c6377?auto=format&fit=crop&w=1200&q=80', '06:00:00', '20:00:00', 2000, '7-a-side', 'active', 1, 4.9, 91);

-- Sample Venue Slots for venue 1 (Royal Futsal) - all days
INSERT INTO venue_slots (venue_id, day_of_week, start_time, end_time, price) VALUES
(1, 0, '06:00:00', '07:00:00', 1500),(1, 0, '07:00:00', '08:00:00', 1500),(1, 0, '08:00:00', '09:00:00', 1500),
(1, 0, '09:00:00', '10:00:00', 1500),(1, 0, '10:00:00', '11:00:00', 1500),(1, 0, '11:00:00', '12:00:00', 1500),
(1, 0, '12:00:00', '13:00:00', 1500),(1, 0, '13:00:00', '14:00:00', 1500),(1, 0, '14:00:00', '15:00:00', 1500),
(1, 0, '15:00:00', '16:00:00', 2000),(1, 0, '16:00:00', '17:00:00', 2000),(1, 0, '17:00:00', '18:00:00', 2000),
(1, 0, '18:00:00', '19:00:00', 2500),(1, 0, '19:00:00', '20:00:00', 2500),(1, 0, '20:00:00', '21:00:00', 2500),
(1, 0, '21:00:00', '22:00:00', 2500),(1, 0, '22:00:00', '23:00:00', 2000),
(1, 1, '06:00:00', '07:00:00', 1500),(1, 1, '07:00:00', '08:00:00', 1500),(1, 1, '08:00:00', '09:00:00', 1500),
(1, 1, '09:00:00', '10:00:00', 1500),(1, 1, '10:00:00', '11:00:00', 1500),(1, 1, '11:00:00', '12:00:00', 1500),
(1, 1, '12:00:00', '13:00:00', 1500),(1, 1, '13:00:00', '14:00:00', 1500),(1, 1, '14:00:00', '15:00:00', 1500),
(1, 1, '15:00:00', '16:00:00', 2000),(1, 1, '16:00:00', '17:00:00', 2000),(1, 1, '17:00:00', '18:00:00', 2000),
(1, 1, '18:00:00', '19:00:00', 2500),(1, 1, '19:00:00', '20:00:00', 2500),(1, 1, '20:00:00', '21:00:00', 2500),
(1, 2, '06:00:00', '07:00:00', 1500),(1, 2, '07:00:00', '08:00:00', 1500),(1, 2, '08:00:00', '09:00:00', 1500),
(1, 2, '12:00:00', '13:00:00', 1500),(1, 2, '13:00:00', '14:00:00', 1500),(1, 2, '14:00:00', '15:00:00', 1500),
(1, 2, '18:00:00', '19:00:00', 2500),(1, 2, '19:00:00', '20:00:00', 2500),(1, 2, '20:00:00', '21:00:00', 2500),
(1, 3, '06:00:00', '07:00:00', 1500),(1, 3, '07:00:00', '08:00:00', 1500),(1, 3, '08:00:00', '09:00:00', 1500),
(1, 3, '18:00:00', '19:00:00', 2500),(1, 3, '19:00:00', '20:00:00', 2500),(1, 3, '20:00:00', '21:00:00', 2500),
(1, 4, '06:00:00', '07:00:00', 1500),(1, 4, '07:00:00', '08:00:00', 1500),(1, 4, '08:00:00', '09:00:00', 1500),
(1, 4, '18:00:00', '19:00:00', 2500),(1, 4, '19:00:00', '20:00:00', 2500),(1, 4, '20:00:00', '21:00:00', 2500),
(1, 5, '06:00:00', '07:00:00', 1500),(1, 5, '07:00:00', '08:00:00', 1500),(1, 5, '08:00:00', '09:00:00', 1500),
(1, 5, '18:00:00', '19:00:00', 2500),(1, 5, '19:00:00', '20:00:00', 2500),(1, 5, '20:00:00', '21:00:00', 2500),
(1, 6, '06:00:00', '07:00:00', 1500),(1, 6, '07:00:00', '08:00:00', 1500),(1, 6, '08:00:00', '09:00:00', 1500),
(1, 6, '18:00:00', '19:00:00', 2500),(1, 6, '19:00:00', '20:00:00', 2500),(1, 6, '20:00:00', '21:00:00', 2500);

-- Sample Bookings
INSERT INTO bookings (venue_id, customer_name, customer_phone, booking_date, start_time, end_time, total_price, status, payment_method, booking_ref) VALUES
(1, 'Anil Maharjan', '9841111111', CURDATE(), '18:00:00', '19:00:00', 2500, 'confirmed', 'esewa', 'MM20240001'),
(1, 'Sujan Rai', '9852222222', CURDATE(), '19:00:00', '20:00:00', 2500, 'confirmed', 'cash', 'MM20240002'),
(1, 'Pratik Shrestha', '9863333333', DATE_ADD(CURDATE(), INTERVAL 1 DAY), '17:00:00', '18:00:00', 2000, 'confirmed', 'khalti', 'MM20240003'),
(2, 'Riya Sharma', '9874444444', CURDATE(), '10:00:00', '11:00:00', 1200, 'confirmed', 'cash', 'MM20240004'),
(3, 'FC Kathmandu Boys', '9885555555', DATE_ADD(CURDATE(), INTERVAL 2 DAY), '08:00:00', '09:00:00', 2500, 'pending', 'cash', 'MM20240005');
