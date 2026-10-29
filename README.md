# KalaSetu – Local Skills & Artisan Preservation

A DBMS mini project: a web platform to discover India's traditional skills, the artisans who practise them, their products, and opportunities to learn them. Built with **PHP 8 (PDO) + MySQL 8 + Bootstrap 5** on XAMPP.

> All artisans, contacts, products and visitors in the sample data are **fictional demo data**. They are not real people.

---

## 1. Setup (XAMPP)

1. **Install XAMPP** (PHP 8.0 or newer) from https://www.apachefriends.org
2. Open the **XAMPP Control Panel** and click **Start** for **Apache** and **MySQL**.
3. Copy the project folder so that this README is at:
   ```
   C:\xampp\htdocs\kalasetu\README.md        (Windows)
   /Applications/XAMPP/htdocs/kalasetu/      (macOS)
   /opt/lampp/htdocs/kalasetu/               (Linux)
   ```
4. Open **phpMyAdmin**: http://localhost/phpmyadmin
5. Click the **Import** tab → **Choose File** → select `database/kalasetu.sql` → click **Import** (or **Go**).
   This creates the database `kalasetu_db`, all 7 tables, the view, the indexes and the sample data.
   *(Re-importing resets everything back to the original sample data.)*

   **Already imported an older version and want to keep your data?** Instead of re-importing, select `kalasetu_db` in phpMyAdmin and import `database/upgrade_artisan_requests.sql` once. It only adds the new `artisan_requests` table (plus two sample pending requests).
6. **Configure** `config/database.php` only if your MySQL login is not the XAMPP default:
   ```php
   define('DB_HOST', '127.0.0.1');
   define('DB_PORT', '3306');
   define('DB_NAME', 'kalasetu_db');
   define('DB_USER', 'root');
   define('DB_PASS', '');        // XAMPP default is an empty password
   ```
7. Open the site: **http://localhost/kalasetu/**

### Demo admin login

| URL | Username | Password |
|---|---|---|
| http://localhost/kalasetu/admin/login.php | `admin` | `Kala@2026` |

The password is stored only as a bcrypt hash (`password_hash()`) in `includes/auth.php` and checked with `password_verify()`. To change it, run
`php -r "echo password_hash('NewPassword', PASSWORD_DEFAULT);"` and paste the output into `ADMIN_PASSWORD_HASH`.

### Artisan accounts (upgrade for an existing database)

If your `kalasetu_db` already exists, import **`database/artisan_auth_upgrade.sql`** (phpMyAdmin → `kalasetu_db` → Import) – **do not re-import `kalasetu.sql`**, which recreates the whole database. The upgrade creates (or completes) `artisan_accounts`, adds `account_id` and `age` to `artisan_requests`, makes all tables InnoDB, and gives every existing request an account (demo password `Artisan@2026`); a request the old code already approved/rejected is copied onto its account. **It deletes nothing**, and running it twice does nothing harmful.

### Demo artisan logins

| URL | Email | Password | Status |
|---|---|---|---|
| http://localhost/kalasetu/artisan/login.php | `kavita.s@kalasetu.example` | `Artisan@2026` | APPROVED → Artisan Dashboard |
| | `mangesh.m@example.com` | `Artisan@2026` | PENDING → "under review" |
| | `ravi.d@example.com` | `Artisan@2026` | REJECTED → reason shown |

### Approve / Reject not working? → Admin → Requests → **Diagnostics**

`admin/diagnostics.php` checks, on your own machine: which folder the browser is really running, that PDO is connected to `kalasetu_db`, every table's engine (must be InnoDB) and charset, all required columns, foreign keys, and whether accounts/requests/artisans agree. Its **Dry-run approve** button runs the real approval statements and then rolls back, showing the exact database error if there is one.

`config/database.php` has `define('APP_DEBUG', true);` – while it is `true`, a failed Approve shows the real database message (for example *Column 'age' cannot be null*). Set it to `false` before putting the site online.

### Troubleshooting

| Problem | Fix |
|---|---|
| "Database connection failed" | MySQL not started in XAMPP, or the SQL file was not imported. |
| MySQL port is not 3306 | Change `DB_PORT` in `config/database.php`. |
| Page looks unstyled | Bootstrap, icons and fonts load from a CDN – an internet connection is needed. |
| Image upload fails | Make sure `assets/images/uploads/` is writable. Max 2 MB, JPG/PNG/WEBP. |

---

## 2. Database design

**Database:** `kalasetu_db` · **8 tables** · all `InnoDB` with foreign keys.

```
DISTRICT 1──N SKILL 1──N ARTISAN 1──N PRODUCT
   │            │  │         │  │
   │            │  1         1  1
   │            │  │         │  │
   │            │  N         N  0..1
   │            │ TRAINING  VISITOR_INTEREST
   │            │                 │
   └──1:N──► ARTISAN_REQUESTS ◄──N:1 (skill)
                  ▲
                  │ account_id (0..1 pending application per account)
            ARTISAN_ACCOUNTS ── artisan_id ──► ARTISAN  (set when APPROVED)
```

| Table | Primary key | Foreign key | On delete |
|---|---|---|---|
| `districts` | `district_id` | – | – |
| `skills` | `skill_id` | `district_id → districts` | RESTRICT |
| `artisans` | `artisan_id` | `skill_id → skills` | RESTRICT |
| `products` | `product_id` | `artisan_id → artisans` | CASCADE |
| `training` | `training_id` | `skill_id → skills` | CASCADE |
| `visitor_interest` | `interest_id` | `artisan_id → artisans` | CASCADE |
| `artisan_accounts` | `account_id` | `artisan_id → artisans` (UNIQUE, NULL until approved) | SET NULL |
| `artisan_requests` | `request_id` | `account_id → artisan_accounts` (UNIQUE)<br>`district_id → districts`<br>`skill_id → skills`<br>`artisan_id → artisans` (legacy, unused) | CASCADE<br>RESTRICT<br>CASCADE<br>SET NULL |

**Why these delete rules?**
- A skill cannot be deleted while artisans belong to it (RESTRICT) – the admin sees a friendly message. This protects real people's records from being removed by accident.
- Deleting an artisan automatically removes their products and visitor interests (CASCADE) – those rows have no meaning without the artisan.
- Deleting a skill removes its training programmes and registration requests (CASCADE).
- Deleting an approved artisan keeps their login account; its `artisan_id` simply becomes NULL (SET NULL) and the artisan sees that their profile was removed.

### Artisan registration & review lifecycle (`artisan_accounts` + `artisan_requests`)

Three roles: **Visitor** (browse, no login) · **Artisan** (login → status / dashboard) · **Admin** (login → approve / reject).

```
register.php ── one transaction ──► artisan_accounts (status = PENDING, password_hash)
                                    artisan_requests (status = PENDING, account_id)
                                              │
                         Admin → Requests → Review
                          │                         │
                       Approve                    Reject
                          │                         │
   START TRANSACTION                       START TRANSACTION
   SELECT … FOR UPDATE (lock request)      SELECT … FOR UPDATE
   INSERT INTO artisans (…)                UPDATE artisan_accounts
   new artisan_id = LAST_INSERT_ID()          SET status = REJECTED, admin_note = reason
   UPDATE artisan_accounts                 DELETE FROM artisan_requests
      SET status = APPROVED,               COMMIT   (no artisan row is created)
          artisan_id = new id
   DELETE FROM artisan_requests
   COMMIT   (any error → ROLLBACK, nothing saved)
```

- `artisan_requests` is a **queue**: it only holds applications waiting for review. The decision is stored permanently in `artisan_accounts.status`, so the artisan still sees APPROVED / REJECTED after the request row is gone. The admin's *Approved* / *Rejected* tabs read from `artisan_accounts`.
- **Artisan login** (`artisan/login.php`): email + password (`password_hash()` / `password_verify()`), same session style and brute-force lock as the admin login. Only `account_id` is kept in the session; the status is read from the database on every page, so an approval shows up immediately.
  - `PENDING` → `artisan/status.php` "Your artisan registration is currently under review."
  - `REJECTED` → `artisan/status.php` with the admin's reason and an **Apply again** button (same account – no duplicate).
  - `APPROVED` → `artisan/dashboard.php` (Artisan Dashboard: profile, products, learners' interests), `artisan/profile.php`, `artisan/products.php` – all limited to the artisan's own `artisan_id`.
- The request's columns use the **same names as `artisans`** (`artisan_name`, `experience`, `bio`, `accepts_apprentices`, `profile_image`), so approval is a straight copy into the existing table. There is no second artisan table: once approved, the artisan is an ordinary row in `artisans` and appears in listings, skill pages, search, products, visitor interest, *Most Discovered* and *Skills at Risk* automatically.
- `status` is an `ENUM('PENDING','APPROVED','REJECTED')`.
- **District and skill must match.** In this design an artisan's district comes from their skill (`skills.district_id`), so the form only offers skills of the chosen district and the server rejects a mismatch. Approval is also blocked if the admin has since moved that skill to another district.
- **Phone + email → `contact`.** `artisans` has one `contact` column, so on approval it becomes e.g. `+91 98765 43210 · name@example.com`.
- **Age** is asked during registration (stored in `artisan_requests.age`) and copied into `artisans.age` on approval.
- One account per email (`UNIQUE`) and per phone (checked in PHP). A rejected applicant applies again by logging in.

`artisans.profile_views` was added later with `ALTER TABLE` (see the SQL file) and is incremented every time a profile page is opened.

### Sample data
5 districts (Sangli, Kolhapur, Pune, Nashik, Satara) · 12 skills · 24 artisans · 28 products · 9 training programmes · 15 visitor interests.
Because only 24 artisans are spread over 12 skills, every skill currently has fewer than 10 artisans and shows as **HIGH RISK**. Add artisans in the admin panel and the risk level updates automatically (10–20 = MEDIUM, >20 = LOW).

---

## 3. Where each DBMS concept is used

| Concept | Where |
|---|---|
| `CREATE DATABASE`, `CREATE TABLE`, `ALTER TABLE` | `database/kalasetu.sql` |
| `INSERT` | Visitor interest form, admin “Add” forms |
| `UPDATE` | Profile view counter, admin “Edit” forms |
| `DELETE` | Admin delete buttons |
| `WHERE` + `LIKE` | `search.php`, search boxes on skills/artisans/products |
| `ORDER BY` | All listings, Most Discovered ranking |
| `GROUP BY` + `COUNT()` | Artisans per skill, interests per artisan |
| `HAVING` | Skills with fewer than 10 artisans (`at_risk.php`, home page, dashboard) |
| `AVG()` | Average experience (`artisans.php`), avg artisans per skill (`at_risk.php`) |
| `INNER JOIN` | Artisan → Skill → District, Product → Artisan → Skill |
| `LEFT JOIN` | Skills with 0 artisans still appear; artisans with 0 interests in the view |
| Subquery | “Above-average experience” filter on `artisans.php`; dashboard totals |
| `CASE` | Risk label in `at_risk.php` |
| View `artisan_summary` | `popular_artisans.php`, admin dashboard |
| Indexes | `skill_name`, `artisan_name`, `skills.district_id`, `artisan_requests.status` |
| `ENUM` | `artisan_requests.status` |
| Transaction (`BEGIN … COMMIT / ROLLBACK`) + `SELECT … FOR UPDATE` | Approving a registration request (`admin/requests.php`) |
| `ON DELETE SET NULL` | `artisan_requests.artisan_id` |

`database/demo_queries.sql` contains each of these queries ready to run in phpMyAdmin during the viva.

---

## 4. Features

**Visitor (no login)**
- Home page with live statistics from MySQL, featured skills, artisans, and skills needing attention
- Browse/search/filter skills (category, district), artisans (skill, district, above-average experience) and products (skill)
- Skill details: artisans, their products and training for that skill (JOINs)
- Artisan profile: biography, products, teaching status, profile views and learning interests
- **“I'm Interested in Learning”** form → saved to `visitor_interest`
- Training opportunities, **Skills at Risk** analysis, **Most Discovered Artisans** (views + interests – no fake ratings)
- Navbar search across skills, artisans and products
- **Register as an artisan** (`register.php`): name, phone, email, district, skill (filtered by district), experience, description, optional photo → saved as a PENDING request with a reference number

**Admin (login required)**
- Dashboard: totals, recent visitor interests, most discovered artisans, skills at risk
- Add / Edit / Delete for skills, artisans (with optional photo), products (with optional image) and training
- View visitor interests, grouped per artisan, and remove spam entries
- **Registration Requests**: Pending / Approved / Rejected tabs, request details with duplicate warnings, Approve (creates the artisan) or Reject with a reason; pending count shown in the navbar and on the dashboard

Intentionally **not** included (out of scope): cart, payments, orders, chat, maps, AI, social login.

---

## 5. Security

- **PDO prepared statements** for every query that uses user input (prevents SQL injection)
- `htmlspecialchars()` (through the `e()` helper) on every value printed in HTML (prevents XSS)
- Server-side validation on every form, plus Bootstrap client-side validation
- Admin pages protected by PHP sessions (`require_admin()`); `session_regenerate_id()` on login
- Passwords hashed with `password_hash()` / `password_verify()`; 5 failed logins → 60-second lockout
- CSRF token on every admin form; deletes use POST, never GET links
- Image uploads checked for real image type and size, saved with random names
- `.htaccess` blocks browser access to `config/`, `includes/`, `database/` and script execution in the uploads folder
- Database errors are logged, not shown to visitors (a global handler shows a friendly error page)

---

## 6. File structure

```
kalasetu/
├── index.php              Home
├── skills.php             All skills + search/filters
├── skill_details.php      One skill → artisans, products, training
├── artisans.php           All artisans + search/filters
├── artisan_details.php    Profile + interest form
├── products.php           Products + search/filter
├── training.php           Training opportunities
├── at_risk.php            Skills at Risk analysis
├── popular_artisans.php   Most Discovered Artisans
├── search.php             Global search
├── register.php           Artisan registration form (creates the artisan account)
├── artisan/               login, logout, status (pending/rejected), dashboard, profile, products
├── admin/                 login, logout, dashboard, skills, artisans, products, training, interests, requests, diagnostics
├── config/database.php    PDO connection
├── includes/              header, navbar, footer, auth, functions, review (approve/reject transaction)
├── assets/css/style.css   Theme
├── assets/js/script.js    Confirm deletes, form validation, auto-submit filters
├── assets/images/uploads/ Uploaded images
└── database/
    ├── kalasetu.sql       Schema + view + indexes + sample data
    ├── upgrade_artisan_requests.sql   Adds only the registration table to an existing database
    ├── artisan_auth_upgrade.sql       Adds artisan accounts to an existing database (safe to re-run)
    └── demo_queries.sql   Queries for the viva
```

---

## 7. Viva flow to demonstrate

1. Home → **Explore Skills** → *Traditional Pottery* → see artisans, products, training (JOINs).
2. Open *Ramesh Kumbhar* → refresh to show **profile views** increasing (UPDATE).
3. Click **I'm Interested in Learning** → submit → “Thank you! Your interest has been recorded.” (INSERT).
4. **Skills at Risk** → explain `COUNT / GROUP BY / HAVING / LEFT JOIN` (Clay Idol Making shows 0).
5. **Most Discovered** → explain the `artisan_summary` view.
6. Admin login → dashboard shows the new interest → add an artisan to a skill → the artisan count on Skills at Risk changes.
7. Try deleting a skill that has artisans → blocked by the foreign key (RESTRICT). Delete an artisan → products removed too (CASCADE).
8. **Registration:** click *Join as Artisan* → choose *Pune* (only Pune's skills appear) → *Clay Idol Making* → set a password → submit → you land on "Your application is under review" (PENDING).
9. Admin → **Requests** (badge shows the count) → Review → **Approve**. Explain the transaction (INSERT artisans → UPDATE artisan_accounts → DELETE artisan_requests → COMMIT). The artisan logs in again and gets the **Artisan Dashboard**.
10. Back on the site: the new artisan is in *Artisans*, on the *Clay Idol Making* page and in search; *Skills at Risk* now shows Clay Idol Making with 1 more artisan. Open the profile and submit *I'm Interested in Learning* – it works like any other artisan.
