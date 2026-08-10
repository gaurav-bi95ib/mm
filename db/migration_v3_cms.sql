-- MeroMaidan homepage CMS upgrade
USE meromaidan;

ALTER TABLE cms_content
  ADD COLUMN IF NOT EXISTS subtitle VARCHAR(500) NULL AFTER title,
  ADD COLUMN IF NOT EXISTS content_type VARCHAR(40) NOT NULL DEFAULT 'general' AFTER section_key,
  ADD COLUMN IF NOT EXISTS image_url VARCHAR(1000) NULL AFTER content_text,
  ADD COLUMN IF NOT EXISTS button_text VARCHAR(100) NULL AFTER image_url,
  ADD COLUMN IF NOT EXISTS button_url VARCHAR(500) NULL AFTER button_text,
  ADD COLUMN IF NOT EXISTS is_published TINYINT(1) NOT NULL DEFAULT 1 AFTER button_url,
  ADD COLUMN IF NOT EXISTS sort_order INT NOT NULL DEFAULT 0 AFTER is_published,
  ADD COLUMN IF NOT EXISTS created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER sort_order;

UPDATE cms_content SET content_type='hero',
  subtitle=COALESCE(NULLIF(subtitle,''),'Find and book sports grounds near you in minutes. Football, futsal, cricket, and cricsal venues across Nepal - all in one place.'),
  content_text='Discover futsal courts, football pitches, and cricket grounds across Nepal with live slot availability.',
  image_url=COALESCE(NULLIF(image_url,''),'https://images.unsplash.com/photo-1546519638-68e109498ffc?auto=format&fit=crop&w=1600&q=85'),
  button_text=COALESCE(NULLIF(button_text,''),'Find a ground'),
  button_url=COALESCE(NULLIF(button_url,''),'#services'), sort_order=1
WHERE page_slug='home' AND section_key='hero_banner';

UPDATE cms_content SET content_type='announcement',is_published=0,sort_order=0
WHERE page_slug='home' AND section_key='announcement_bar';
UPDATE cms_content SET is_published=0
WHERE page_slug='home' AND section_key IN ('story_section','about_section');

INSERT IGNORE INTO cms_content
  (page_slug,section_key,content_type,title,subtitle,content_text,button_text,button_url,is_published,sort_order)
VALUES
  ('home','cta_section','call_to_action','Ready to Play?',NULL,'Discover a suitable sports venue and reserve an available time.','Book a Ground Now','#groundsGrid',1,40);

INSERT IGNORE INTO cms_content
  (page_slug,section_key,content_type,title,subtitle,content_text,image_url,button_text,button_url,is_published,sort_order)
VALUES
  ('home','hero_slide_2','hero','Your next game is one swipe away.','Real venues. Live availability. Direct booking.','Search by sport and location, compare grounds, and reserve the right time.','https://images.unsplash.com/photo-1529900748604-07564a03e7a6?auto=format&fit=crop&w=1600&q=85','Explore venues','#services',1,2),
  ('home','hero_slide_3','hero','More bookings. Better venue operations.','A smarter platform for Nepal sports businesses.','Manage slots, customers, payments, and venue operations from one place.','https://images.unsplash.com/photo-1574629810360-7efbbe195018?auto=format&fit=crop&w=1600&q=85','List your ground','list-ground.php',1,3);
