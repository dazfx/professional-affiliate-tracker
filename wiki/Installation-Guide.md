# Installation Guide

This guide will walk you through installing the Professional Affiliate Tracking System step by step.

## 📋 Prerequisites

### System Requirements
- **PHP 7.4+** with extensions:
  - `pdo_mysql`
  - `curl`
  - `json`
  - `mbstring`
- **MySQL 5.7+** or **MariaDB 10.3+**
- **Web Server** (Apache/Nginx) with mod_rewrite
- **SSL Certificate** (recommended for production)
- **Composer** for dependency management

### Hardware Requirements
- **Minimum**: 1 CPU, 1GB RAM, 10GB storage
- **Recommended**: 2+ CPUs, 4GB RAM, 50GB SSD

## 🚀 Quick Installation

### Step 1: Download and Extract

```bash
# Clone from GitHub
git clone https://github.com/dazfx/professional-affiliate-tracker.git
cd professional-affiliate-tracker

# Or download and extract ZIP
wget https://github.com/dazfx/professional-affiliate-tracker/archive/main.zip
unzip main.zip
cd professional-affiliate-tracker-main
```

### Step 2: Install Dependencies

```bash
cd track
composer install
```

If you don't have Composer installed:
```bash
# Install Composer (Linux/Mac)
curl -sS https://getcomposer.org/installer | php
php composer.phar install

# Or download composer.phar manually
wget https://getcomposer.org/composer.phar
php composer.phar install
```

### Step 3: Database Setup

#### Create Database
```sql
CREATE DATABASE affiliate_tracker CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'affiliate_user'@'localhost' IDENTIFIED BY 'secure_password_here';
GRANT ALL PRIVILEGES ON affiliate_tracker.* TO 'affiliate_user'@'localhost';
FLUSH PRIVILEGES;
```

#### Configure Database Connection
```bash
# Copy template and edit
cp track/admin/db.php.template track/admin/db.php
```

Edit `track/admin/db.php`:
```php
<?php
const DB_HOST = 'localhost';
const DB_NAME = 'affiliate_tracker';
const DB_USER = 'affiliate_user';
const DB_PASS = 'secure_password_here';

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>
```

### Step 4: Run Installation Script

```bash
# Navigate to admin installation
http://yourdomain.com/track/admin/install.php
```

The installation script will:
- Create required database tables
- Set up initial configuration
- Configure default settings

### Step 5: Web Server Configuration

#### Apache Configuration
Create or edit `.htaccess` in the track directory:
```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-d
RewriteCond %{REQUEST_FILENAME}\.php -f
RewriteRule ^(.*)$ $1.php

# Security headers
Header always set X-Content-Type-Options nosniff
Header always set X-Frame-Options DENY
Header always set X-XSS-Protection "1; mode=block"
```

#### Nginx Configuration
```nginx
server {
    listen 80;
    server_name yourdomain.com;
    root /path/to/track;
    index index.php;

    location / {
        try_files $uri $uri/ $uri.php?$args;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    # Security
    location ~ /\.(ht|git) {
        deny all;
    }
}
```

### Step 6: Set Directory Permissions

```bash
# Set proper permissions
chmod 755 track/
chmod 644 track/*.php
chmod -R 777 track/logs/
chmod -R 777 track/queue/
chmod 600 track/admin/db.php
```

### Step 7: Configure Background Processing

Add to crontab for queue processing:
```bash
crontab -e
```

Add this line:
```bash
*/5 * * * * /usr/bin/php /path/to/track/process_queue.php
```

## 🔒 Security Setup

### 1. Create Read-Only Database User (Optional)
```sql
CREATE USER 'affiliate_readonly'@'localhost' IDENTIFIED BY 'readonly_password';
GRANT SELECT ON affiliate_tracker.* TO 'affiliate_readonly'@'localhost';
FLUSH PRIVILEGES;
```

### 2. SSL Configuration
```apache
# Force HTTPS
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

### 3. Firewall Rules
```bash
# Allow only necessary ports
ufw allow 22/tcp
ufw allow 80/tcp
ufw allow 443/tcp
ufw enable
```

## 🧪 Testing Installation

### 1. Test Database Connection
```bash
curl http://yourdomain.com/track/admin/
```

### 2. Test Postback Processing
```bash
curl "http://yourdomain.com/track/postback.php?pid=test&clickid=123&sum=50.00"
```

### 3. Verify Logs
```bash
tail -f track/logs/php_errors.log
tail -f track/logs/queue_process.log
```

## 📊 Post-Installation Steps

### 1. Access Admin Dashboard
```
http://yourdomain.com/track/admin/
```

### 2. Configure Global Settings
- Set up Telegram bot token and channel ID
- Configure cURL timeouts
- Enable global Telegram notifications

### 3. Add Your First Partner
- Click "Добавить" (Add) button
- Fill in partner details:
  - Unique Partner ID
  - Partner name
  - Target domain
  - ClickID and Sum parameter keys

### 4. Set Up Google Sheets Integration
- Create Google Cloud Project
- Enable Google Sheets API
- Create Service Account
- Download JSON credentials
- Add credentials to partner configuration

## 🔧 Troubleshooting

### Common Issues

#### Database Connection Failed
```
Error: SQLSTATE[HY000] [1045] Access denied
```
**Solution**: Check database credentials in `track/admin/db.php`

#### Composer Dependencies Missing
```
Error: Class 'Google_Client' not found
```
**Solution**: Run `composer install` in the track directory

#### Permission Denied
```
Error: Permission denied writing to logs directory
```
**Solution**: 
```bash
chmod -R 777 track/logs/
chmod -R 777 track/queue/
```

#### URL Rewriting Not Working
```
Error: 404 Not Found for postback URLs
```
**Solution**: Enable mod_rewrite and check .htaccess configuration

### Log Files
- **PHP Errors**: `track/logs/php_errors.log`
- **Queue Processing**: `track/logs/queue_process.log`
- **Access Logs**: Check web server logs

## 🚀 Next Steps

After successful installation:

1. **Read the [Configuration Guide](Configuration)** for detailed settings
2. **Set up [Google Sheets Integration](Google-Sheets-Integration)**
3. **Configure [Telegram Notifications](Telegram-Notifications)**
4. **Review [Security Guide](Security-Guide)** for production hardening
5. **Explore [Admin Dashboard](Admin-Dashboard)** features

---

🎉 **Congratulations!** Your affiliate tracking system is now ready to process postbacks and track conversions!

> 💡 **Next**: Check out the [Quick Start Guide](Quick-Start) to create your first partner and start tracking!