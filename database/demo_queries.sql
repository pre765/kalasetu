-- =====================================================================
--  KalaSetu – DBMS concepts cheat-sheet for the viva
--  Run these in phpMyAdmin → kalasetu_db → SQL tab.
--  Each query is also used somewhere in the application (file noted).
-- =====================================================================
USE kalasetu_db;

-- ---------------- SELECT + WHERE + LIKE (search.php, skills.php) ----------------
SELECT skill_id, skill_name, category
FROM skills
WHERE skill_name LIKE '%Pot%';

-- ---------------- INNER JOIN : artisan with skill and district (artisans.php) ----------------
SELECT a.artisan_name, s.skill_name, d.district_name, a.experience
FROM artisans a
INNER JOIN skills s    ON a.skill_id = s.skill_id
INNER JOIN districts d ON s.district_id = d.district_id
ORDER BY a.artisan_name;

-- ---------------- LEFT JOIN + COUNT + GROUP BY : artisans per skill (skills.php) ----------------
-- LEFT JOIN keeps skills with zero artisans (e.g. Clay Idol Making)
SELECT s.skill_name, COUNT(a.artisan_id) AS artisan_count
FROM skills s
LEFT JOIN artisans a ON a.skill_id = s.skill_id
GROUP BY s.skill_id, s.skill_name
ORDER BY artisan_count;

-- ---------------- HAVING : skills at risk (< 10 artisans) (at_risk.php, index.php) ----------------
SELECT s.skill_name, d.district_name, COUNT(a.artisan_id) AS artisan_count
FROM skills s
INNER JOIN districts d ON s.district_id = d.district_id
LEFT JOIN artisans a   ON a.skill_id = s.skill_id
GROUP BY s.skill_id, s.skill_name, d.district_name
HAVING COUNT(a.artisan_id) < 10;

-- ---------------- CASE : risk label computed in SQL (at_risk.php) ----------------
SELECT s.skill_name,
       COUNT(a.artisan_id) AS artisan_count,
       CASE WHEN COUNT(a.artisan_id) < 10  THEN 'HIGH RISK'
            WHEN COUNT(a.artisan_id) <= 20 THEN 'MEDIUM RISK'
            ELSE 'LOW RISK' END AS risk_level
FROM skills s
LEFT JOIN artisans a ON a.skill_id = s.skill_id
GROUP BY s.skill_id, s.skill_name;

-- ---------------- AVG ----------------
SELECT AVG(experience) AS average_experience FROM artisans;          -- artisans.php
SELECT ROUND(AVG(price), 2) AS avg_price FROM products;

-- ---------------- SUBQUERY : experience greater than average (artisans.php checkbox) ----------------
SELECT artisan_name, experience
FROM artisans
WHERE experience > (SELECT AVG(experience) FROM artisans)
ORDER BY experience DESC;

-- ---------------- VIEW : artisan_summary (popular_artisans.php, admin/dashboard.php) ----------------
SELECT * FROM artisan_summary;

-- Most Discovered = profile views + learning interests
SELECT artisan_name, skill_name, profile_views, interest_count,
       (profile_views + interest_count) AS discovery_score
FROM artisan_summary
ORDER BY discovery_score DESC
LIMIT 5;

-- ---------------- Indexes ----------------
SHOW INDEX FROM skills;
SHOW INDEX FROM artisans;

-- ---------------- DML examples (the app runs these through PDO prepared statements) ----------------
-- INSERT  (artisan_details.php – "I'm Interested in Learning")
-- INSERT INTO visitor_interest (artisan_id, visitor_name, visitor_email, message)
-- VALUES (1, 'Demo Visitor', 'demo@example.com', 'I want to learn pottery');

-- UPDATE  (artisan_details.php – every profile visit)
-- UPDATE artisans SET profile_views = profile_views + 1 WHERE artisan_id = 1;

-- DELETE  (admin pages)
-- DELETE FROM products WHERE product_id = 5;

-- ---------------- Referential integrity demo ----------------
-- 1) RESTRICT: this fails because artisans still belong to skill 1
-- DELETE FROM skills WHERE skill_id = 1;
-- 2) CASCADE: deleting an artisan also deletes their products and visitor interests
-- DELETE FROM artisans WHERE artisan_id = 24;

-- ---------------- Artisan registration workflow (register.php, admin/requests.php) ----------------
-- Requests per status (GROUP BY on an ENUM column) – admin tabs
SELECT status, COUNT(*) AS total FROM artisan_requests GROUP BY status;

-- Pending requests with district and skill names (INNER JOINs)
SELECT r.request_id, r.artisan_name, s.skill_name, d.district_name, r.created_at
FROM artisan_requests r
INNER JOIN skills s    ON r.skill_id = s.skill_id
INNER JOIN districts d ON r.district_id = d.district_id
WHERE r.status = 'PENDING'
ORDER BY r.created_at;

-- Which approved requests became which artisans (LEFT JOIN: artisan may have been deleted)
SELECT r.request_id, r.artisan_name AS applicant, a.artisan_id, a.artisan_name AS artisan
FROM artisan_requests r
LEFT JOIN artisans a ON r.artisan_id = a.artisan_id
WHERE r.status = 'APPROVED';

-- Approval as one TRANSACTION (what admin/requests.php does for request 3)
-- START TRANSACTION;
-- SELECT * FROM artisan_requests WHERE request_id = 3 FOR UPDATE;
-- INSERT INTO artisans (skill_id, artisan_name, age, experience, bio, contact, accepts_apprentices, profile_image)
--   SELECT skill_id, artisan_name, NULL, experience, bio, CONCAT('+91 ', phone, ' · ', email), accepts_apprentices, profile_image
--   FROM artisan_requests WHERE request_id = 3;
-- UPDATE artisan_requests SET status = 'APPROVED', artisan_id = LAST_INSERT_ID(), reviewed_at = CURRENT_TIMESTAMP
--   WHERE request_id = 3;
-- COMMIT;

-- ---------------- Artisan accounts & review lifecycle (register.php, admin/requests.php, artisan/*) ----------------
-- Every account with its review result and, if approved, its public artisan row (LEFT JOIN)
SELECT ac.account_id, ac.name, ac.email, ac.status, ac.artisan_id, a.artisan_name, ac.admin_note
FROM artisan_accounts ac
LEFT JOIN artisans a ON ac.artisan_id = a.artisan_id
ORDER BY ac.account_id;

-- Applications still waiting for the admin (the request table is a queue)
SELECT r.request_id, ac.name, ac.email, s.skill_name, r.created_at
FROM artisan_requests r
INNER JOIN artisan_accounts ac ON r.account_id = ac.account_id
INNER JOIN skills s ON r.skill_id = s.skill_id
WHERE r.status = 'PENDING';

-- How many applicants are in each state (GROUP BY)
SELECT status, COUNT(*) AS total FROM artisan_accounts GROUP BY status;

-- TRANSACTION used on approval (example for request 1 / account 3):
-- START TRANSACTION;
--   SELECT * FROM artisan_requests WHERE request_id = 1 FOR UPDATE;
--   INSERT INTO artisans (skill_id, artisan_name, experience, bio, contact, accepts_apprentices)
--        SELECT skill_id, artisan_name, experience, bio, CONCAT(phone, ' · ', email), accepts_apprentices
--        FROM artisan_requests WHERE request_id = 1;
--   UPDATE artisan_accounts SET status = 'APPROVED', artisan_id = LAST_INSERT_ID() WHERE account_id = 3;
--   DELETE FROM artisan_requests WHERE request_id = 1;
-- COMMIT;      -- or ROLLBACK; if any step fails

