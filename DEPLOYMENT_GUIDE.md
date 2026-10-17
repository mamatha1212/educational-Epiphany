# EEMT 2027 - Complete Deployment Guide
## For Shared Hosting (cPanel / Hostinger) and Linux VPS (Ubuntu / Debian / AlmaLinux)

This document provides step-by-step instructions for deploying the **EEMT 2027 Application** on both **Shared Hosting** and **Dedicated Linux VPS**.

---

## 📋 System Requirements
- **PHP**: 8.0, 8.1, 8.2, or 8.3
- **PHP Extensions**: `pdo`, `pdo_mysql` (or `pdo_sqlite`), `json`, `mbstring`, `fileinfo`
- **Web Server**: Apache, LiteSpeed, or Nginx
- **Database**: MySQL 5.7+ / MariaDB 10.3+ (or SQLite 3.x)

---

## 🚀 Option 1: Deploying on Shared Hosting (cPanel / Hostinger / GoDaddy)

Shared hosting platforms run Apache or LiteSpeed with cPanel and phpMyAdmin.

### Step 1: Upload the Project Files
1. Log in to your hosting **cPanel**.
2. Open **File Manager** and navigate to your web root (`public_html` or your subdomain folder, e.g. `public_html/eemt2027`).
3. Upload all contents from the `eemt2027_app/` folder.
4. Ensure the `.htaccess` file is present in the root folder. *(If hidden in cPanel, click "Settings" in top-right of File Manager and check "Show Hidden Files")*.

### Step 2: Create MySQL Database in cPanel
1. In cPanel, navigate to **MySQL Databases** (or **MySQL Database Wizard**).
2. Create a new database, e.g., `cpaneluser_eemt2027`.
3. Create a new MySQL user, e.g., `cpaneluser_eemtadmin`, and generate a secure password.
4. Add the user to the database and grant **ALL PRIVILEGES**.

### Step 3: Import the Pre-Seeded MySQL Dump
1. In cPanel, open **phpMyAdmin**.
2. Select your newly created database from the left sidebar.
3. Click the **Import** tab at the top.
4. Choose the file: `database/eemt2027_production_mysql.sql`.
5. Click **Go / Import**.
> **Result**: All tables (`schools_master`, `school_registrations_2027`, `users`, `question_papers`) will be created and populated with **12,936 AP Government Schools across all 27 Districts (including Markapuram)** and default admin credentials.

### Step 4: Configure Database Credentials
You can configure credentials using either **Method A** (`.env`) or **Method B** (`config.local.php`):

#### Method A: Using `.env` (Recommended)
Edit `.env` in the root folder:
```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

DB_DRIVER=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=cpaneluser_eemt2027
DB_USERNAME=cpaneluser_eemtadmin
DB_PASSWORD=your_mysql_password
```

#### Method B: Using `config/config.local.php` (Alternative if `.env` is hidden)
1. Copy `config/config.local.php.example` to `config/config.local.php`.
2. Update the credentials in `config/config.local.php`.

### Step 5: Set Directory Permissions
Ensure the following folders are writable by the web server (Permission `755` or `775`):
- `uploads/`
- `uploads/question_papers/`

---

## 🖥️ Option 2: Deploying on Linux VPS (Ubuntu / Debian / AlmaLinux)

### Step 1: Install Required Software
#### Ubuntu / Debian:
```bash
sudo apt update
sudo apt install -y nginx mariadb-server php8.2 php8.2-fpm php8.2-mysql php8.2-sqlite3 php8.2-mbstring php8.2-xml php8.2-curl git unzip
```

### Step 2: Create MySQL / MariaDB Database
Log into MySQL:
```bash
sudo mysql -u root
```
Run the following SQL commands:
```sql
CREATE DATABASE eemt2027 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'eemt_user'@'localhost' IDENTIFIED BY 'StrongPasswordHere123!';
GRANT ALL PRIVILEGES ON eemt2027.* TO 'eemt_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### Step 3: Deploy Files to `/var/www/eemt2027_app`
```bash
sudo mkdir -p /var/www/eemt2027_app
sudo cp -r /path/to/eemt2027_app/* /var/www/eemt2027_app/
```

### Step 4: Import Database Dump
```bash
sudo mysql -u eemt_user -p eemt2027 < /var/www/eemt2027_app/database/eemt2027_production_mysql.sql
```

### Step 5: Configure `.env` on VPS
```bash
cd /var/www/eemt2027_app
sudo cp .env.example .env
sudo nano .env
```
Set:
```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://eemt2027.educationalepiphany.org

DB_DRIVER=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=eemt2027
DB_USERNAME=eemt_user
DB_PASSWORD=StrongPasswordHere123!
```

### Step 6: Configure Nginx
1. Copy the provided Nginx configuration:
```bash
sudo cp /var/www/eemt2027_app/nginx.conf.example /etc/nginx/sites-available/eemt2027
```
2. Edit `/etc/nginx/sites-available/eemt2027` with your domain name.
3. Enable the site and restart Nginx:
```bash
sudo ln -s /etc/nginx/sites-available/eemt2027 /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

### Step 7: Set File Permissions
```bash
sudo chown -R www-data:www-data /var/www/eemt2027_app
sudo chmod -R 755 /var/www/eemt2027_app
sudo chmod -R 775 /var/www/eemt2027_app/uploads
```

### Step 8: Enable SSL (HTTPS) with Certbot
```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d eemt2027.educationalepiphany.org
```

---

## 🔒 Security Features Implemented
1. **Direct Access Denied**:
   - Web visitors cannot directly download `.sqlite`, `.sql`, `.env`, `.bak`, `.log` files via browser.
2. **Directory Listing Blocked**:
   - `Options -Indexes` in Apache and `autoindex off` in Nginx prevent directory browsing.
3. **Prepared Statements**:
   - All database queries use PDO prepared statements with strict parameterized inputs preventing SQL injection.
4. **Environment Isolation**:
   - Passwords and database credentials reside in `.env` or `config.local.php` outside web-accessible scopes.

---

## 🔑 Default Administrative Access
| Role | Username | Default Password | Dashboard URL |
| :--- | :--- | :--- | :--- |
| **State Admin** | `admin` | `admin123` | `/admin/dashboard.php` |
| **District Coordinator** | `coordinator` | `coord123` | `/admin/dashboard.php` |

*(Be sure to update default passwords via database or profile settings upon first production login).*
