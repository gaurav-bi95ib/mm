-- Minimal MeroMaidan seed for the base schema.
-- Password for the test accounts below: Admin@1234
USE meromaidan;

INSERT INTO subscription_plans
  (id,name,slug,price_monthly,price_yearly,duration_months,included_venues,max_venues,max_bookings_per_month,features,is_active)
VALUES
  (1,'Annual Venue Subscription','annual-venue',0,9999,12,1,1,999999,
   '["Manage one venue","Grounds and courts","Photos and facilities","Operating hours and slots","Bookings","Staff operations","Reports"]',1)
ON DUPLICATE KEY UPDATE name=VALUES(name),price_yearly=9999,duration_months=12,included_venues=1,max_venues=1,is_active=1;

INSERT INTO superadmins (id,name,email,password_hash) VALUES
  (1,'MeroMaidan Admin','admin@meromaidan.com','$2y$10$UYoWUXTNRRoAUppeRQeOMeSndVjzeSZTD7v.Bw9ewZpRzxmkwno4m')
ON DUPLICATE KEY UPDATE name=VALUES(name);

INSERT INTO venue_owners (id,name,email,phone,password_hash,business_name,plan_id,status,approved_at) VALUES
  (1,'Ramesh Shrestha','ramesh@royalfutsal.com','9841234567','$2y$10$UYoWUXTNRRoAUppeRQeOMeSndVjzeSZTD7v.Bw9ewZpRzxmkwno4m','Royal Futsal Pvt Ltd',1,'active',NOW()),
  (2,'Sita Karki','sita@greenfield.com','9851234567','$2y$10$UYoWUXTNRRoAUppeRQeOMeSndVjzeSZTD7v.Bw9ewZpRzxmkwno4m','Green Field Sports',1,'active',NOW()),
  (3,'Bikash Tamang','bikash@kathmandufutsal.com','9861234567','$2y$10$UYoWUXTNRRoAUppeRQeOMeSndVjzeSZTD7v.Bw9ewZpRzxmkwno4m','Kathmandu Futsal Center',1,'active',NOW())
ON DUPLICATE KEY UPDATE plan_id=1,status='active';

INSERT INTO venues
  (id,owner_id,name,slug,sport_type,address,city,district,lat,lng,description,amenities,images,cover_image,open_time,close_time,price_per_hour,capacity,status)
VALUES
  (1,1,'Royal Futsal','royal-futsal','Futsal','Thapagaun, Anamnagar','Kathmandu','Kathmandu',27.7036,85.3199,'Indoor futsal ground with quality turf.','["Changing Room","Parking","Drinking Water"]','[]','https://images.unsplash.com/photo-1529900748604-07564a03e7a6?auto=format&fit=crop&w=1200&q=80','06:00:00','23:00:00',1500,'5-a-side','active'),
  (2,2,'Green Field Football Ground','green-field-football','Football','Lagankhel, Lalitpur','Lalitpur','Lalitpur',27.6679,85.3169,'Full-size football ground.','["Parking","Cafeteria","Drinking Water"]','[]','https://images.unsplash.com/photo-1508098682722-e99c43a406b2?auto=format&fit=crop&w=1200&q=80','06:00:00','21:00:00',2500,'11-a-side','active'),
  (3,3,'Kathmandu Futsal Center','kathmandu-futsal-center','Futsal','New Baneshwor, Kathmandu','Kathmandu','Kathmandu',27.6929,85.3385,'Affordable futsal ground.','["Parking"]','[]','https://images.unsplash.com/photo-1574629810360-7efbbe195018?auto=format&fit=crop&w=1200&q=80','10:00:00','22:00:00',800,'5-a-side','active')
ON DUPLICATE KEY UPDATE owner_id=VALUES(owner_id),name=VALUES(name),status='active';

INSERT INTO venue_slots (venue_id,day_of_week,start_time,end_time,price)
SELECT 1,d.day_of_week,t.start_time,t.end_time,t.price
FROM (
  SELECT 0 day_of_week UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6
) d
CROSS JOIN (
  SELECT '06:00:00' start_time,'07:00:00' end_time,1500 price UNION ALL
  SELECT '07:00:00','08:00:00',1500 UNION ALL
  SELECT '08:00:00','09:00:00',1500 UNION ALL
  SELECT '18:00:00','19:00:00',2000 UNION ALL
  SELECT '19:00:00','20:00:00',2500 UNION ALL
  SELECT '20:00:00','21:00:00',2500
) t
WHERE NOT EXISTS (
  SELECT 1 FROM venue_slots s WHERE s.venue_id=1 AND s.day_of_week=d.day_of_week AND s.start_time=t.start_time
);
