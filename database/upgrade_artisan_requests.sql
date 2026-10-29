-- =====================================================================
--  KalaSetu – Artisan Registration upgrade
--
--  Use this ONLY if you already imported an older kalasetu.sql and want
--  to keep your current data. It adds the new `artisan_requests` table
--  without touching any existing table or row.
--
--  phpMyAdmin → select kalasetu_db → Import → this file → Import.
--  Run it once. (A fresh install should just import kalasetu.sql.)
-- =====================================================================
USE kalasetu_db;

CREATE TABLE IF NOT EXISTS artisan_requests (
    request_id          INT PRIMARY KEY AUTO_INCREMENT,
    district_id         INT NOT NULL,
    skill_id            INT NOT NULL,
    artisan_name        VARCHAR(150) NOT NULL,
    phone               VARCHAR(15)  NOT NULL,
    email               VARCHAR(80)  NOT NULL,
    experience          INT NOT NULL,
    bio                 TEXT NOT NULL,
    accepts_apprentices BOOLEAN DEFAULT FALSE,
    profile_image       VARCHAR(255),
    status              ENUM('PENDING', 'APPROVED', 'REJECTED') NOT NULL DEFAULT 'PENDING',
    admin_note          VARCHAR(255),
    artisan_id          INT NULL,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_at         TIMESTAMP NULL DEFAULT NULL,
    INDEX idx_request_status (status),
    CONSTRAINT fk_request_district FOREIGN KEY (district_id)
        REFERENCES districts(district_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_request_skill FOREIGN KEY (skill_id)
        REFERENCES skills(skill_id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_request_artisan FOREIGN KEY (artisan_id)
        REFERENCES artisans(artisan_id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

-- Two sample pending requests so the admin page has something to review.
-- Uses the skill → district pairs from the original sample data
-- (Clay Idol Making = Pune, Bamboo Craft = Sangli).
INSERT INTO artisan_requests (district_id, skill_id, artisan_name, phone, email, experience, bio, accepts_apprentices, status)
SELECT s.district_id, s.skill_id, 'Mangesh Murkute', '9000000307', 'mangesh.m@example.com', 22,
       'Shapes and paints eco-friendly shadu clay Ganesh idols every festival season with natural colours. (Fictional demo request)', TRUE, 'PENDING'
FROM skills s WHERE s.skill_name = 'Clay Idol Making' LIMIT 1;

INSERT INTO artisan_requests (district_id, skill_id, artisan_name, phone, email, experience, bio, accepts_apprentices, status)
SELECT s.district_id, s.skill_id, 'Suman Burud', '9000000412', 'suman.b@example.com', 12,
       'Weaves bamboo baskets and decorative trays with family members; would like more visibility for the craft. (Fictional demo request)', FALSE, 'PENDING'
FROM skills s WHERE s.skill_name = 'Bamboo Craft' LIMIT 1;
