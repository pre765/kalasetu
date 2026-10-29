-- =====================================================================
--  KalaSetu – Local Skills & Artisan Preservation
--  DBMS Mini Project : Database script (MySQL 8+)
--  8 tables: districts, skills, artisans, products, training,
--            visitor_interest, artisan_accounts, artisan_requests
--
--  Import this file in phpMyAdmin (Import tab) or run:
--      mysql -u root < database/kalasetu.sql
--
--  All artisans, contacts, products and visitors below are FICTIONAL
--  demo data created for this college project. They are not real people.
-- =====================================================================

-- ---------------------------------------------------------------------
-- DDL : CREATE DATABASE
-- ---------------------------------------------------------------------
SET NAMES utf8mb4;
DROP DATABASE IF EXISTS kalasetu_db;
CREATE DATABASE kalasetu_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE kalasetu_db;

-- ---------------------------------------------------------------------
-- DDL : CREATE TABLE (8 tables)
-- ---------------------------------------------------------------------

-- TABLE 1 : districts
CREATE TABLE districts (
    district_id   INT PRIMARY KEY AUTO_INCREMENT,
    district_name VARCHAR(100) NOT NULL,
    state         VARCHAR(100) NOT NULL
) ENGINE=InnoDB;

-- TABLE 2 : skills   (District 1 : N Skill)
CREATE TABLE skills (
    skill_id    INT PRIMARY KEY AUTO_INCREMENT,
    district_id INT NOT NULL,
    skill_name  VARCHAR(150) NOT NULL,
    category    VARCHAR(100) NOT NULL,
    description TEXT,
    CONSTRAINT fk_skill_district FOREIGN KEY (district_id)
        REFERENCES districts(district_id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- TABLE 3 : artisans   (Skill 1 : N Artisan)
CREATE TABLE artisans (
    artisan_id          INT PRIMARY KEY AUTO_INCREMENT,
    skill_id            INT NOT NULL,
    artisan_name        VARCHAR(150) NOT NULL,
    age                 INT,
    experience          INT,
    bio                 TEXT,
    contact             VARCHAR(100),
    accepts_apprentices BOOLEAN DEFAULT FALSE,
    profile_image       VARCHAR(255),
    CONSTRAINT fk_artisan_skill FOREIGN KEY (skill_id)
        REFERENCES skills(skill_id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- TABLE 4 : products   (Artisan 1 : N Product)
CREATE TABLE products (
    product_id   INT PRIMARY KEY AUTO_INCREMENT,
    artisan_id   INT NOT NULL,
    product_name VARCHAR(150) NOT NULL,
    description  TEXT,
    price        DECIMAL(10,2),
    availability VARCHAR(50),
    image        VARCHAR(255),
    CONSTRAINT fk_product_artisan FOREIGN KEY (artisan_id)
        REFERENCES artisans(artisan_id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

-- TABLE 5 : training   (Skill 1 : N Training)
CREATE TABLE training (
    training_id     INT PRIMARY KEY AUTO_INCREMENT,
    skill_id        INT NOT NULL,
    training_name   VARCHAR(150) NOT NULL,
    duration        VARCHAR(100),
    available_seats INT,
    description     TEXT,
    CONSTRAINT fk_training_skill FOREIGN KEY (skill_id)
        REFERENCES skills(skill_id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

-- TABLE 6 : visitor_interest   (Artisan 1 : N Visitor Interest)
CREATE TABLE visitor_interest (
    interest_id   INT PRIMARY KEY AUTO_INCREMENT,
    artisan_id    INT NOT NULL,
    visitor_name  VARCHAR(100) NOT NULL,
    visitor_email VARCHAR(150) NOT NULL,
    message       TEXT,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_interest_artisan FOREIGN KEY (artisan_id)
        REFERENCES artisans(artisan_id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

-- TABLE 7 : artisan_accounts   (Artisan login + review result)
-- Persistent identity of a person who registered on register.php.
-- password_hash is created with PHP password_hash() – never plain text.
-- status keeps the admin's decision (PENDING / APPROVED / REJECTED) even
-- after the request row has been deleted. artisan_id points to the public
-- `artisans` row created on approval (NULL while pending or if rejected).
--   Account 1 : 0..1 Artisan,  Account 1 : 0..1 pending Request
CREATE TABLE artisan_accounts (
    account_id    INT PRIMARY KEY AUTO_INCREMENT,
    name          VARCHAR(150) NOT NULL,
    email         VARCHAR(80)  NOT NULL,
    phone         VARCHAR(15)  NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    status        ENUM('PENDING', 'APPROVED', 'REJECTED') NOT NULL DEFAULT 'PENDING',
    artisan_id    INT NULL,
    admin_note    VARCHAR(255) NULL,
    reviewed_at   TIMESTAMP NULL DEFAULT NULL,
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT uq_account_email   UNIQUE (email),
    CONSTRAINT uq_account_artisan UNIQUE (artisan_id),
    INDEX idx_account_status (status),
    CONSTRAINT fk_account_artisan FOREIGN KEY (artisan_id)
        REFERENCES artisans(artisan_id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

-- TABLE 8 : artisan_requests   (applications waiting for admin review)
-- An artisan registers on register.php → an account + a PENDING row here.
-- The admin APPROVES (one transaction: INSERT into `artisans`, UPDATE the
-- account to APPROVED + artisan_id, DELETE this row) or REJECTS (UPDATE the
-- account to REJECTED + note, DELETE this row). So this table only ever
-- holds pending applications; the result lives in artisan_accounts.
--   Account 1 : 0..1 Request, District 1 : N Request, Skill 1 : N Request
-- Column names match the `artisans` table (artisan_name, experience, bio,
-- accepts_apprentices, profile_image) so approval is a direct copy.
-- The chosen skill must belong to the chosen district (checked in PHP),
-- because an artisan's district comes from skills.district_id.
CREATE TABLE artisan_requests (
    request_id          INT PRIMARY KEY AUTO_INCREMENT,
    account_id          INT NULL,
    district_id         INT NOT NULL,
    skill_id            INT NOT NULL,
    artisan_name        VARCHAR(150) NOT NULL,
    age                 INT NULL,
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
    CONSTRAINT uq_request_account UNIQUE (account_id),
    CONSTRAINT fk_request_account FOREIGN KEY (account_id)
        REFERENCES artisan_accounts(account_id)
        ON UPDATE CASCADE ON DELETE CASCADE,
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

-- ---------------------------------------------------------------------
-- DDL : ALTER TABLE
-- Profile views were added after the first version of the design.
-- ---------------------------------------------------------------------
ALTER TABLE artisans ADD COLUMN profile_views INT DEFAULT 0;

-- ---------------------------------------------------------------------
-- DDL : INDEXES on frequently searched / joined fields
-- ---------------------------------------------------------------------
CREATE INDEX idx_skill_name    ON skills(skill_name);
CREATE INDEX idx_artisan_name  ON artisans(artisan_name);
CREATE INDEX idx_skill_district ON skills(district_id);
CREATE INDEX idx_request_status ON artisan_requests(status);

-- ---------------------------------------------------------------------
-- DML : INSERT sample data (Maharashtra prototype)
-- ---------------------------------------------------------------------

INSERT INTO districts (district_name, state) VALUES
('Sangli',   'Maharashtra'),   -- 1
('Kolhapur', 'Maharashtra'),   -- 2
('Pune',     'Maharashtra'),   -- 3
('Nashik',   'Maharashtra'),   -- 4
('Satara',   'Maharashtra');   -- 5

INSERT INTO skills (district_id, skill_name, category, description) VALUES
(1, 'Traditional Pottery', 'Pottery',
 'Wheel-thrown earthen pots, water matkas and diyas shaped from local river clay and fired in small community kilns. A craft passed down through potter families for generations.'),
(1, 'Bamboo Craft', 'Craft',
 'Baskets, winnowing trays (soop), mats and household items woven from split bamboo. Requires careful splitting, soaking and tight weaving by hand.'),
(2, 'Kolhapuri Leather Craft', 'Leather',
 'Hand-stitched Kolhapuri chappals made from vegetable-tanned leather, with braided straps and fine punched patterns. Each pair is cut, stitched and finished by hand.'),
(2, 'Kolhapuri Saaj Jewellery', 'Jewellery',
 'Traditional Maharashtrian necklace with small gold or gold-plated motifs strung together, each motif carrying its own meaning. Made with delicate filigree and soldering work.'),
(4, 'Warli Painting', 'Folk Art',
 'Tribal wall art drawn in white on mud or red-ochre backgrounds using circles, triangles and lines to show village life, harvests and celebrations.'),
(4, 'Paithani Weaving', 'Textile',
 'Handloom silk sarees with zari borders and peacock and lotus motifs woven on traditional looms. A single saree can take several weeks to months to complete.'),
(5, 'Wood Carving', 'Woodwork',
 'Hand-carved wooden doors, pillars, temple panels and toys using chisels and mallets. Designs often feature floral patterns and figures from folk tales.'),
(3, 'Brass & Copper Metalwork', 'Metal Craft',
 'Hand-beaten brass and copper vessels such as kalash, lamps and pots, shaped by hammering sheet metal and finished with engraving.'),
(3, 'Hand Block Printing', 'Textile',
 'Printing patterns on cotton fabric using hand-carved wooden blocks dipped in natural dyes. Each colour is printed separately by hand.'),
(1, 'Miraj Sitar Making', 'Musical Instruments',
 'Crafting sitars and tanpuras from seasoned wood and dried gourds. Miraj in Sangli district is known for this instrument-making tradition.'),
(5, 'Ghongadi Weaving', 'Textile',
 'Coarse woollen blankets (ghongadi) woven from sheep wool by shepherd communities on simple pit looms. Valued for warmth and durability.'),
(3, 'Clay Idol Making', 'Pottery',
 'Shaping and painting clay idols for festivals using eco-friendly shadu clay and natural colours.');

-- Fictional / demo artisans (contacts use the reserved example domain)
INSERT INTO artisans (skill_id, artisan_name, age, experience, bio, contact, accepts_apprentices, profile_image, profile_views) VALUES
(1, 'Ramesh Kumbhar',   58, 35, 'Learned pottery from his father at the age of twelve. Known for large water matkas and festival diyas. (Fictional demo profile)', 'ramesh.k@kalasetu.example', TRUE,  NULL, 342),
(1, 'Sunanda Patil',    46, 22, 'Specialises in decorative planters and painted terracotta. Runs weekend sessions for school children. (Fictional demo profile)', 'sunanda.p@kalasetu.example', TRUE,  NULL, 188),
(1, 'Ganpat More',      67, 45, 'One of the senior potters of his village. Still uses a hand-turned wheel and a wood-fired kiln. (Fictional demo profile)', 'ganpat.m@kalasetu.example', FALSE, NULL, 95),
(1, 'Kavita Shinde',    34, 10, 'Combines traditional pottery with modern glazed tableware. (Fictional demo profile)', 'kavita.s@kalasetu.example', TRUE,  NULL, 141),
(2, 'Dattatray Burud',  52, 30, 'Weaves baskets, soops and fishing traps from locally grown bamboo. (Fictional demo profile)', 'dattatray.b@kalasetu.example', TRUE,  NULL, 76),
(2, 'Lata Burud',       48, 25, 'Makes fine bamboo lamp shades and decorative trays. (Fictional demo profile)', 'lata.b@kalasetu.example', FALSE, NULL, 54),
(3, 'Shankar Chavan',   61, 40, 'Third-generation Kolhapuri chappal maker who still uses vegetable-tanned leather. (Fictional demo profile)', 'shankar.c@kalasetu.example', TRUE,  NULL, 263),
(3, 'Vaishali Kamble',  39, 15, 'Known for women''s Kolhapuri designs with fine braided straps. (Fictional demo profile)', 'vaishali.k@kalasetu.example', TRUE,  NULL, 172),
(3, 'Anil Sathe',       44, 18, 'Focuses on durable, hand-stitched soles and repairs. (Fictional demo profile)', 'anil.s@kalasetu.example', FALSE, NULL, 66),
(4, 'Prakash Sonar',    55, 32, 'Makes traditional Saaj necklaces with 21 hand-made motifs. (Fictional demo profile)', 'prakash.s@kalasetu.example', FALSE, NULL, 119),
(4, 'Meera Potdar',     41, 16, 'Creates lightweight Saaj and Thushi designs for everyday wear. (Fictional demo profile)', 'meera.p@kalasetu.example', TRUE,  NULL, 87),
(5, 'Jaywant Bhoir',      63, 40, 'Paints Warli scenes of harvests and festivals on mud walls and canvas. (Fictional demo profile)', 'jaywant.b@kalasetu.example', TRUE,  NULL, 298),
(5, 'Sarita Dhangda',   36, 14, 'Teaches Warli art at community workshops and paints on fabric. (Fictional demo profile)', 'sarita.d@kalasetu.example', TRUE,  NULL, 156),
(5, 'Mohan Wagh',       29,  8, 'Young artist bringing Warli motifs to stationery and home decor. (Fictional demo profile)', 'mohan.w@kalasetu.example', FALSE, NULL, 73),
(6, 'Rajendra Kshirsagar', 57, 34, 'Master weaver of Paithani sarees with traditional peacock borders. (Fictional demo profile)', 'rajendra.k@kalasetu.example', TRUE,  NULL, 221),
(6, 'Sushila Bhandare', 50, 28, 'Weaves Paithani dupattas and works on restoring old saree designs. (Fictional demo profile)', 'sushila.b@kalasetu.example', TRUE,  NULL, 133),
(6, 'Nilesh Pardeshi',  32,  9, 'Weaves smaller Paithani items such as stoles and pouches. (Fictional demo profile)', 'nilesh.p@kalasetu.example', FALSE, NULL, 58),
(7, 'Vishnu Sutar',     60, 38, 'Carves temple doors, pillars and wooden toys. (Fictional demo profile)', 'vishnu.s@kalasetu.example', TRUE,  NULL, 104),
(8, 'Ashok Tambat',     54, 31, 'Hand-beats brass and copper vessels in the traditional Tambat style. (Fictional demo profile)', 'ashok.t@kalasetu.example', TRUE,  NULL, 147),
(9, 'Farida Shaikh',    43, 20, 'Prints cotton sarees and dupattas using natural indigo and madder dyes. (Fictional demo profile)', 'farida.s@kalasetu.example', TRUE,  NULL, 112),
(9, 'Rohan Joshi',      27,  6, 'Carves his own printing blocks and makes printed home textiles. (Fictional demo profile)', 'rohan.j@kalasetu.example', FALSE, NULL, 49),
(10, 'Yusuf Mulla', 65, 45, 'Makes concert sitars and tanpuras, and repairs old instruments. (Fictional demo profile)', 'yusuf.m@kalasetu.example', TRUE,  NULL, 207),
(10, 'Imran Mirajkar',  38, 17, 'Specialises in tanpura making and gourd preparation. (Fictional demo profile)', 'imran.m@kalasetu.example', TRUE,  NULL, 98),
(11, 'Bhimrao Gavade',  66, 44, 'Weaves woollen ghongadi blankets on a traditional pit loom. (Fictional demo profile)', 'bhimrao.g@kalasetu.example', TRUE,  NULL, 61);

INSERT INTO products (artisan_id, product_name, description, price, availability, image) VALUES
(1,  'Earthen Water Matka',      'Hand-thrown clay pot that keeps water naturally cool.', 450.00,  'In Stock',   NULL),
(1,  'Festival Diya Set (12)',   'Set of twelve hand-made clay diyas.',                   180.00,  'In Stock',   NULL),
(2,  'Painted Terracotta Planter','Medium planter with hand-painted folk motifs.',        650.00,  'In Stock',   NULL),
(3,  'Clay Cooking Handi',       'Traditional clay pot for slow cooking.',                520.00,  'Made to Order', NULL),
(4,  'Glazed Clay Mug Pair',     'Two glazed mugs made on the wheel.',                    700.00,  'Limited',    NULL),
(5,  'Bamboo Storage Basket',    'Strong woven basket for grains or vegetables.',         350.00,  'In Stock',   NULL),
(5,  'Bamboo Soop (Winnowing Tray)','Traditional tray used for cleaning grain.',          220.00,  'In Stock',   NULL),
(6,  'Bamboo Lamp Shade',        'Hand-woven lamp shade with soft light pattern.',        890.00,  'Limited',    NULL),
(7,  'Classic Kolhapuri Chappal (Men)','Vegetable-tanned leather, hand-stitched.',        1450.00, 'In Stock',   NULL),
(7,  'Kapshi Kolhapuri Chappal', 'Lightweight design with braided toe strap.',           1250.00, 'Made to Order', NULL),
(8,  'Women''s Braided Kolhapuri','Fine braided straps with punched pattern.',           1350.00, 'In Stock',   NULL),
(9,  'Kolhapuri Sandal',         'Durable sandal with back strap.',                       1100.00, 'In Stock',   NULL),
(10, 'Traditional Saaj Necklace','Gold-plated Saaj with 21 hand-made motifs.',           6500.00, 'Made to Order', NULL),
(11, 'Thushi Choker',            'Lightweight gold-plated Thushi choker.',                3200.00, 'Limited',    NULL),
(12, 'Warli Harvest Painting',   'Warli scene on canvas showing a harvest dance.',        2800.00, 'In Stock',   NULL),
(13, 'Warli Printed Tote Bag',   'Cotton tote bag with hand-painted Warli figures.',      450.00,  'In Stock',   NULL),
(14, 'Warli Bookmark Set',       'Set of five hand-painted bookmarks.',                   150.00,  'In Stock',   NULL),
(15, 'Paithani Silk Saree',      'Pure silk saree with peacock border and zari pallu.',   28000.00,'Made to Order', NULL),
(16, 'Paithani Dupatta',         'Silk dupatta with lotus motifs.',                       7500.00, 'Limited',    NULL),
(17, 'Paithani Pouch',           'Small pouch made from Paithani fabric.',                 600.00, 'In Stock',   NULL),
(18, 'Carved Wooden Panel',      'Wall panel with floral carving in teak wood.',          4800.00, 'Made to Order', NULL),
(18, 'Wooden Toy Bullock Cart',  'Hand-carved toy cart painted with natural colours.',     900.00, 'In Stock',   NULL),
(19, 'Copper Water Kalash',      'Hand-beaten copper kalash with engraved rim.',          1800.00, 'In Stock',   NULL),
(19, 'Brass Samai (Oil Lamp)',   'Traditional standing oil lamp in brass.',               2600.00, 'Limited',    NULL),
(20, 'Block Printed Cotton Saree','Indigo block-printed cotton saree.',                   2200.00, 'In Stock',   NULL),
(21, 'Block Printed Cushion Covers (2)','Pair of cushion covers with geometric blocks.',   800.00, 'In Stock',   NULL),
(22, 'Concert Sitar',            'Full-size sitar made from seasoned tun wood and gourd.', 32000.00,'Made to Order', NULL),
(24, 'Woollen Ghongadi Blanket', 'Hand-woven sheep wool blanket.',                         1600.00, 'In Stock',   NULL);

INSERT INTO training (skill_id, training_name, duration, available_seats, description) VALUES
(1,  'Traditional Pottery Workshop', '3 Months', 5,  'Learn clay preparation, wheel throwing and kiln firing with a master potter.'),
(1,  'Weekend Diya Making',          '2 Weekends', 12, 'Short beginner course on hand-shaping and painting diyas.'),
(2,  'Bamboo Basket Weaving Basics', '1 Month', 8,   'Splitting, soaking and weaving bamboo into baskets and trays.'),
(3,  'Kolhapuri Chappal Apprenticeship', '6 Months', 4, 'Cutting, stitching and finishing Kolhapuri chappals by hand.'),
(5,  'Warli Painting for Beginners', '4 Weeks', 15,  'Basic Warli shapes, composition and storytelling through painting.'),
(6,  'Paithani Handloom Introduction', '3 Months', 3, 'Introduction to the Paithani loom, warp setting and simple motifs.'),
(7,  'Wood Carving Fundamentals',    '2 Months', 6,   'Tools, wood selection and carving simple floral patterns.'),
(9,  'Hand Block Printing Workshop', '10 Days', 10,   'Printing with wooden blocks and natural dyes on cotton.'),
(10, 'Sitar Making Apprenticeship',  '1 Year', 2,     'Long-term apprenticeship covering gourd preparation, woodwork and assembly.');

INSERT INTO visitor_interest (artisan_id, visitor_name, visitor_email, message, created_at) VALUES
(1,  'Aditi Kulkarni', 'aditi.k@example.com',  'I would love to learn wheel pottery on weekends.',          '2026-09-02 10:15:00'),
(1,  'Rahul Desai',    'rahul.d@example.com',  'Is the pottery workshop open for college students?',       '2026-09-05 16:40:00'),
(1,  'Sneha Jadhav',   'sneha.j@example.com',  'Interested in learning diya making for Diwali.',           '2026-10-01 11:05:00'),
(7,  'Omkar Pawar',    'omkar.p@example.com',  'I want to understand how Kolhapuri chappals are stitched.', '2026-09-10 09:30:00'),
(7,  'Pooja Mane',     'pooja.m@example.com',  'Would like to do a short apprenticeship.',                 '2026-09-21 14:20:00'),
(12, 'Kunal Shah',     'kunal.s@example.com',  'Love Warli art, want to learn the basics.',                '2026-09-12 18:00:00'),
(12, 'Isha Naik',      'isha.n@example.com',   'Can you teach Warli on fabric?',                           '2026-09-28 12:45:00'),
(13, 'Tanvi Gokhale',  'tanvi.g@example.com',  'Interested in your community workshop.',                   '2026-10-03 17:10:00'),
(15, 'Neha Bhosale',   'neha.b@example.com',   'I want to learn about Paithani weaving.',                  '2026-09-15 10:00:00'),
(19, 'Siddharth Rao',  'sid.r@example.com',    'Interested in copper metalwork.',                          '2026-09-18 15:35:00'),
(20, 'Mansi Thakur',   'mansi.t@example.com',  'Want to join the block printing workshop.',                '2026-10-06 13:25:00'),
(22, 'Arjun Mehta',    'arjun.m@example.com',  'I play sitar and want to learn how it is made.',           '2026-09-25 19:05:00'),
(22, 'Varun Iyer',     'varun.i@example.com',  'Interested in the sitar apprenticeship.',                  '2026-10-08 08:50:00'),
(2,  'Riya Sawant',    'riya.s@example.com',   'Would like to learn terracotta painting.',                 '2026-10-10 16:15:00'),
(24, 'Prathamesh Gaikwad', 'prathamesh.g@example.com', 'Interested in learning ghongadi weaving.',          '2026-10-12 11:30:00');

-- Sample artisan accounts (fictional). Demo password for all: Artisan@2026
-- (stored only as a bcrypt hash).
--   1 Kavita Shinde   – APPROVED earlier, linked to artisan 4
--   2 Ravi Dhodi      – REJECTED, with the admin's reason
--   3 Mangesh Murkute – PENDING (request 1)
--   4 Suman Burud     – PENDING (request 2)
INSERT INTO artisan_accounts (name, email, phone, password_hash, status, artisan_id, admin_note, reviewed_at, created_at) VALUES
('Kavita Shinde',   'kavita.s@kalasetu.example', '9000000104', '$2y$10$c7X1yDl/oBRtuXNJ.ZUPJ.hczjaUJfVAnpzzhwahxxHzLwjVEUgeu', 'APPROVED', 4,    NULL, '2026-08-22 12:30:00', '2026-08-20 10:00:00'),
('Ravi Dhodi',      'ravi.d@example.com',        '9000000211', '$2y$10$c7X1yDl/oBRtuXNJ.ZUPJ.hczjaUJfVAnpzzhwahxxHzLwjVEUgeu', 'REJECTED', NULL, 'Duplicate submission – the applicant already sent request details by email.', '2026-10-03 11:00:00', '2026-10-02 09:15:00'),
('Mangesh Murkute', 'mangesh.m@example.com',     '9000000307', '$2y$10$c7X1yDl/oBRtuXNJ.ZUPJ.hczjaUJfVAnpzzhwahxxHzLwjVEUgeu', 'PENDING',  NULL, NULL, NULL, '2026-10-15 17:40:00'),
('Suman Burud',     'suman.b@example.com',       '9000000412', '$2y$10$c7X1yDl/oBRtuXNJ.ZUPJ.hczjaUJfVAnpzzhwahxxHzLwjVEUgeu', 'PENDING',  NULL, NULL, NULL, '2026-10-18 08:25:00');

-- Applications waiting for review (reviewed ones have already been deleted)
INSERT INTO artisan_requests (account_id, district_id, skill_id, artisan_name, age, phone, email, experience, bio, accepts_apprentices, profile_image, status, created_at) VALUES
(3, 3, 12, 'Mangesh Murkute', 45, '9000000307', 'mangesh.m@example.com', 22,
 'Shapes and paints eco-friendly shadu clay Ganesh idols every festival season with natural colours. (Fictional demo request)', TRUE, NULL,
 'PENDING', '2026-10-15 17:40:00'),
(4, 1, 2,  'Suman Burud',     38, '9000000412', 'suman.b@example.com', 12,
 'Weaves bamboo baskets and decorative trays with family members; would like more visibility for the craft. (Fictional demo request)', FALSE, NULL,
 'PENDING', '2026-10-18 08:25:00');

-- ---------------------------------------------------------------------
-- VIEW : artisan_summary
-- One row per artisan with skill, district, profile views and
-- the number of learning interests received (LEFT JOIN + COUNT).
-- ---------------------------------------------------------------------
CREATE VIEW artisan_summary AS
SELECT  a.artisan_id,
        a.artisan_name,
        s.skill_name,
        d.district_name,
        a.profile_views,
        COUNT(vi.interest_id) AS interest_count
FROM artisans a
INNER JOIN skills s     ON a.skill_id = s.skill_id
INNER JOIN districts d  ON s.district_id = d.district_id
LEFT JOIN visitor_interest vi ON vi.artisan_id = a.artisan_id
GROUP BY a.artisan_id, a.artisan_name, s.skill_name, d.district_name, a.profile_views;
