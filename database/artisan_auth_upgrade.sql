-- =====================================================================
--  KalaSetu – Artisan Accounts upgrade (artisan login + automatic review)
--
--  Run this on your EXISTING database to keep your current data:
--      phpMyAdmin → click "kalasetu_db" on the left → Import tab →
--      choose this file → Import / Go
--  (or:  /opt/lampp/bin/mysql -u root < database/artisan_auth_upgrade.sql)
--
--  Safe to run more than once: every step checks first and skips itself
--  if it is already done. Works on MySQL 8 and on XAMPP's MariaDB.
--  It only ever uses the database kalasetu_db.
--
--  What it does:
--   1. Creates `artisan_accounts`, or – if you already created one by
--      hand – adds any missing columns, keys and the foreign key.
--   2. Adds `account_id` and `age` to `artisan_requests`.
--   0. First makes sure every KalaSetu table uses InnoDB (needed for
--      transactions and foreign keys).
--   3. Adds the foreign keys that are missing.
--   4. Converts the requests you already have into accounts, so nothing
--      is lost (new accounts get the demo password  Artisan@2026):
--         PENDING  request → PENDING  account (request stays, linked)
--         APPROVED request → APPROVED account linked to its artisan
--         REJECTED request → REJECTED account keeping the admin note
--      An existing PENDING account whose request was already approved or
--      rejected by the old code is brought up to date the same way.
--   5. Deletes NOTHING. Old reviewed request rows are kept as history.
--
--  It does NOT delete any row from any table, and does NOT change any data
--  in districts, skills, artisans, products, training or visitor_interest.
--  Do NOT re-import kalasetu.sql – that file recreates the whole database.
-- =====================================================================
USE kalasetu_db;
SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- 0. InnoDB everywhere FIRST (MyISAM silently ignores transactions and
--    foreign keys, and a foreign key cannot point to a MyISAM table)
-- ---------------------------------------------------------------------
SET @sql := IF((SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'districts') <> 'InnoDB', 'ALTER TABLE districts ENGINE=InnoDB', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql := IF((SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'skills') <> 'InnoDB', 'ALTER TABLE skills ENGINE=InnoDB', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql := IF((SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'artisans') <> 'InnoDB', 'ALTER TABLE artisans ENGINE=InnoDB', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql := IF((SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products') <> 'InnoDB', 'ALTER TABLE products ENGINE=InnoDB', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql := IF((SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'training') <> 'InnoDB', 'ALTER TABLE training ENGINE=InnoDB', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql := IF((SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'visitor_interest') <> 'InnoDB', 'ALTER TABLE visitor_interest ENGINE=InnoDB', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql := IF((SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'artisan_requests') <> 'InnoDB', 'ALTER TABLE artisan_requests ENGINE=InnoDB', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql := IF((SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'artisan_accounts') <> 'InnoDB', 'ALTER TABLE artisan_accounts ENGINE=InnoDB', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ---------------------------------------------------------------------
-- 1. TABLE : artisan_accounts
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS artisan_accounts (
    account_id    INT PRIMARY KEY AUTO_INCREMENT,
    name          VARCHAR(150) NOT NULL,
    email         VARCHAR(80)  NOT NULL,
    phone         VARCHAR(15)  NOT NULL DEFAULT '',
    password_hash VARCHAR(255) NOT NULL,
    status        ENUM('PENDING', 'APPROVED', 'REJECTED') NOT NULL DEFAULT 'PENDING',
    artisan_id    INT NULL,
    admin_note    VARCHAR(255) NULL,
    reviewed_at   TIMESTAMP NULL DEFAULT NULL,
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT uq_account_email   UNIQUE (email),
    CONSTRAINT uq_account_artisan UNIQUE (artisan_id),
    INDEX idx_account_status (status)
    -- the foreign key artisan_id → artisans is added in step 3
) ENGINE=InnoDB;

-- 1b. If artisan_accounts already existed, add whatever is missing.
--     (MySQL has no "ADD COLUMN IF NOT EXISTS", so each step checks
--      information_schema and runs the ALTER only when needed.)
SET @t := 'artisan_accounts';

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @t AND COLUMN_NAME = 'name') = 0,
    'ALTER TABLE artisan_accounts ADD COLUMN name VARCHAR(150) NOT NULL DEFAULT '''' AFTER account_id', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @t AND COLUMN_NAME = 'phone') = 0,
    'ALTER TABLE artisan_accounts ADD COLUMN phone VARCHAR(15) NOT NULL DEFAULT '''' AFTER email', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @t AND COLUMN_NAME = 'artisan_id') = 0,
    'ALTER TABLE artisan_accounts ADD COLUMN artisan_id INT NULL AFTER status', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @t AND COLUMN_NAME = 'admin_note') = 0,
    'ALTER TABLE artisan_accounts ADD COLUMN admin_note VARCHAR(255) NULL AFTER artisan_id', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @t AND COLUMN_NAME = 'reviewed_at') = 0,
    'ALTER TABLE artisan_accounts ADD COLUMN reviewed_at TIMESTAMP NULL DEFAULT NULL AFTER admin_note', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @t AND COLUMN_NAME = 'created_at') = 0,
    'ALTER TABLE artisan_accounts ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @t AND COLUMN_NAME = 'updated_at') = 0,
    'ALTER TABLE artisan_accounts ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- status must be exactly ENUM('PENDING','APPROVED','REJECTED') (upper case)
SET @bad_status := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @t
                    AND COLUMN_NAME = 'status' AND COLUMN_TYPE <> 'enum(''PENDING'',''APPROVED'',''REJECTED'')');
SET @sql := IF(@bad_status > 0, 'UPDATE artisan_accounts SET status = UPPER(TRIM(status))', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql := IF(@bad_status > 0,
    'ALTER TABLE artisan_accounts MODIFY status ENUM(''PENDING'',''APPROVED'',''REJECTED'') NOT NULL DEFAULT ''PENDING''', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- artisan_id values that point to a deleted artisan → NULL (so the foreign key can be added)
UPDATE artisan_accounts ac LEFT JOIN artisans a ON a.artisan_id = ac.artisan_id
SET ac.artisan_id = NULL
WHERE ac.artisan_id IS NOT NULL AND a.artisan_id IS NULL;

-- UNIQUE(email) and UNIQUE(artisan_id) – only if not there yet and no duplicates exist
SET @sql := IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @t
                AND COLUMN_NAME = 'email' AND NON_UNIQUE = 0) = 0
               AND (SELECT COUNT(*) FROM (SELECT email FROM artisan_accounts GROUP BY email HAVING COUNT(*) > 1) d) = 0,
    'ALTER TABLE artisan_accounts ADD CONSTRAINT uq_account_email UNIQUE (email)', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @t
                AND COLUMN_NAME = 'artisan_id' AND NON_UNIQUE = 0) = 0
               AND (SELECT COUNT(*) FROM (SELECT artisan_id FROM artisan_accounts WHERE artisan_id IS NOT NULL GROUP BY artisan_id HAVING COUNT(*) > 1) d) = 0,
    'ALTER TABLE artisan_accounts ADD CONSTRAINT uq_account_artisan UNIQUE (artisan_id)', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ---------------------------------------------------------------------
-- 2. artisan_requests : account_id (+ UNIQUE) and age
-- ---------------------------------------------------------------------
SET @t := 'artisan_requests';

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @t AND COLUMN_NAME = 'account_id') = 0,
    'ALTER TABLE artisan_requests ADD COLUMN account_id INT NULL AFTER request_id', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @t AND COLUMN_NAME = 'age') = 0,
    'ALTER TABLE artisan_requests ADD COLUMN age INT NULL AFTER artisan_name', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @t
                AND COLUMN_NAME = 'account_id' AND NON_UNIQUE = 0) = 0,
    'ALTER TABLE artisan_requests ADD CONSTRAINT uq_request_account UNIQUE (account_id)', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ---------------------------------------------------------------------
-- 3. Foreign keys (only if missing)
-- ---------------------------------------------------------------------
SET @sql := IF((SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'artisan_accounts'
                AND COLUMN_NAME = 'artisan_id' AND REFERENCED_TABLE_NAME = 'artisans') = 0,
    'ALTER TABLE artisan_accounts ADD CONSTRAINT fk_account_artisan FOREIGN KEY (artisan_id) REFERENCES artisans(artisan_id) ON UPDATE CASCADE ON DELETE SET NULL', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

UPDATE artisan_requests r LEFT JOIN artisan_accounts ac ON ac.account_id = r.account_id
SET r.account_id = NULL
WHERE r.account_id IS NOT NULL AND ac.account_id IS NULL;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'artisan_requests'
                AND COLUMN_NAME = 'account_id' AND REFERENCED_TABLE_NAME = 'artisan_accounts') = 0,
    'ALTER TABLE artisan_requests ADD CONSTRAINT fk_request_account FOREIGN KEY (account_id) REFERENCES artisan_accounts(account_id) ON UPDATE CASCADE ON DELETE CASCADE', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ---------------------------------------------------------------------
-- 4. Bring existing data into the new model
-- ---------------------------------------------------------------------
-- 4a. An account that is still PENDING although the old code already
--     APPROVED its request → APPROVED + linked to that artisan
UPDATE artisan_accounts ac
INNER JOIN artisan_requests r ON r.email = ac.email AND r.status = 'APPROVED' AND r.artisan_id IS NOT NULL
INNER JOIN artisans a        ON a.artisan_id = r.artisan_id
LEFT JOIN artisan_accounts other ON other.artisan_id = r.artisan_id
SET ac.status = 'APPROVED', ac.artisan_id = r.artisan_id,
    ac.reviewed_at = COALESCE(r.reviewed_at, CURRENT_TIMESTAMP)
WHERE ac.status = 'PENDING' AND ac.artisan_id IS NULL AND other.account_id IS NULL
  AND NOT EXISTS (SELECT 1 FROM artisan_requests p WHERE p.email = r.email AND p.status = 'PENDING');

-- 4b. … whose request was already REJECTED by the old code → REJECTED + note
UPDATE artisan_accounts ac
INNER JOIN artisan_requests r ON r.email = ac.email AND r.status = 'REJECTED'
SET ac.status = 'REJECTED', ac.admin_note = r.admin_note,
    ac.reviewed_at = COALESCE(r.reviewed_at, CURRENT_TIMESTAMP)
WHERE ac.status = 'PENDING'
  AND NOT EXISTS (SELECT 1 FROM artisan_requests p WHERE p.email = r.email AND p.status IN ('PENDING', 'APPROVED'));

-- 4c. Requests that have no account yet → new account (demo password: Artisan@2026).
--     INSERT IGNORE + UNIQUE(email): one account per email; APPROVED wins over
--     PENDING, PENDING wins over REJECTED.
INSERT IGNORE INTO artisan_accounts
    (name, email, phone, password_hash, status, artisan_id, admin_note, reviewed_at, created_at)
SELECT r.artisan_name, r.email, r.phone,
       '$2y$10$c7X1yDl/oBRtuXNJ.ZUPJ.hczjaUJfVAnpzzhwahxxHzLwjVEUgeu',
       r.status,
       IF(r.status = 'APPROVED', r.artisan_id, NULL),
       r.admin_note, r.reviewed_at, r.created_at
FROM artisan_requests r
LEFT JOIN artisan_accounts ex ON ex.email = r.email
WHERE r.account_id IS NULL AND ex.account_id IS NULL
ORDER BY FIELD(r.status, 'APPROVED', 'PENDING', 'REJECTED'), r.request_id DESC;

-- 4d. Accounts with an empty phone → take it from their request
UPDATE artisan_accounts ac INNER JOIN artisan_requests r ON r.email = ac.email
SET ac.phone = r.phone
WHERE ac.phone = '';

-- 4e. Link every pending request to its pending account
UPDATE artisan_requests r
INNER JOIN artisan_accounts ac ON ac.email = r.email AND ac.status = 'PENDING'
SET r.account_id = ac.account_id
WHERE r.status = 'PENDING' AND r.account_id IS NULL;

-- ---------------------------------------------------------------------
-- 5. No data is deleted.
--    Rows that the OLD code marked APPROVED / REJECTED stay in
--    artisan_requests as history; their result has been copied to
--    artisan_accounts above. The admin request queue only lists PENDING
--    rows, and from now on Approve / Reject delete the request row
--    automatically. (Optional clean-up, only if you want it later:
--        DELETE FROM artisan_requests WHERE status <> 'PENDING';  )
-- ---------------------------------------------------------------------

-- Check the result
SELECT DATABASE() AS database_in_use;
SELECT account_id, name, email, status, artisan_id, admin_note FROM artisan_accounts ORDER BY account_id;
SELECT request_id, account_id, artisan_name, status FROM artisan_requests ORDER BY request_id;
