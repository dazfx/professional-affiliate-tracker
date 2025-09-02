# Professional Affiliate Tracker

[![PHP](https://img.shields.io/badge/PHP-8.1+-777BB4?style=flat-square&logo=php&logoColor=white)](https://php.net)
[![MySQL](https://img.shields.io/badge/MySQL-8.0+-4479A1?style=flat-square&logo=mysql&logoColor=white)](https://mysql.com)
[![Bootstrap](https://img.shields.io/badge/Bootstrap-5.3-7952B3?style=flat-square&logo=bootstrap&logoColor=white)](https://getbootstrap.com)
[![License](https://img.shields.io/badge/License-MIT-green.svg?style=flat-square)](LICENSE)

A professional-grade affiliate tracking system with real-time postback processing, advanced analytics, Google Sheets integration, and Telegram notifications.

## 🚀 Features

### 📊 **Core Tracking**
- **Real-time Postback Processing**: Instant conversion tracking with high-performance handling
- **Advanced Parameter Mapping**: Flexible clickid and sum parameter detection
- **IP Whitelisting**: Partner-specific IP access control
- **Profit Calculation**: Automatic profit tracking with sum mapping

### 📈 **Analytics & Reporting**
- **Real-time Dashboard**: Live statistics with conversion tracking
- **Partner Performance**: Individual partner analytics and metrics
- **Profit Calculation**: Automated profit tracking (original sum - mapped sum)
- **Historical Data**: Detailed statistics with filtering capabilities
- **Data Export**: Google Sheets integration for external reporting

### 🎨 **Professional UI/UX**
- **Modern Dashboard**: Bootstrap 5 + AdminLTE 3 framework
- **Theme System**: Professional light/dark theme with smooth transitions
- **Responsive Design**: Mobile-first approach with tablet optimization
- **Accessibility**: WCAG 2.1 AA compliant with 4.5:1 contrast ratios
- **Advanced Components**: DataTables integration with column management

### 🔌 **Integrations**
- **Google Sheets API**: Partner-specific configurations with service account authentication
- **Telegram Bot**: Real-time notifications with structured parameter display
- **Queue System**: Asynchronous processing for reliable data handling
- **cURL Configuration**: Flexible HTTP client settings

### 🛡️ **Security**
- **SQL Injection Prevention**: Prepared statements throughout
- **XSS Protection**: Input sanitization and output encoding
- **Partner Isolation**: Individual configurations and access controls
- **Secure Credentials**: Database-stored partner-specific API keys

## 🏗 Architecture

### Directory Structure
```
professional-affiliate-tracker/
├── track/                  # Main application
│   ├── admin/             # Admin dashboard
│   │   ├── assets/        # CSS/JS assets
│   │   ├── api.php        # API endpoints
│   │   ├── index.php      # Dashboard
│   │   ├── db.php.template # Config template
│   │   └── install.php    # Installation
│   ├── logs/              # Logs (gitignored)
│   ├── queue/             # Queue files (gitignored)
│   ├── postback.php       # Main endpoint
│   ├── google_sheets_handler.php # Alt endpoint
│   └── process_queue.php  # Background processor
├── docs/                  # Documentation
├── upgrade_partners_google_sheets.sql # Migration
├── README.md              # Project documentation
├── .gitignore             # Git exclusions
└── DEPLOYMENT_GUIDE.md    # Setup guide
```

## 🚀 Installation

### Prerequisites
- **PHP 7.4+** with extensions: `pdo_mysql`, `curl`, `json`, `mbstring`
- **MySQL 5.7+** or **MariaDB 10.3+**
- **Web server** (Apache/Nginx) with mod_rewrite
- **SSL certificate** (recommended for production)
- **Composer** for Google API dependencies

### Quick Start

1. **Clone the repository**
   ```bash
   git clone https://github.com/dazfx/professional-affiliate-tracker.git
   cd professional-affiliate-tracker
   ```

2. **Install dependencies**
   ```bash
   cd track
   composer install
   ```

3. **Database setup**
   ```bash
   # Create database
   mysql -u root -p -e "CREATE DATABASE affiliate_tracker;"
   
   # Copy database config
   cp track/admin/db.php.template track/admin/db.php
   # Edit db.php with your credentials
   ```

4. **Run installation**
   ```bash
   # Navigate to installation script
   http://yourdomain.com/track/admin/install.php
   ```

5. **Run database migration**
   ```sql
   SOURCE upgrade_partners_google_sheets.sql;
   ```

## 📝 Usage

### Postback URL Format

```
https://yourdomain.com/track/postback.php?pid=PARTNER_ID&clickid=CLICK_ID&sum=AMOUNT&status=SUCCESS
```

### Parameters

| Parameter | Description | Required |
|-----------|-------------|----------|
| `pid` | Partner ID | ✅ Yes |
| `clickid` | Click identifier | ✅ Yes |
| `sum` | Original amount | ❌ Optional |
| `status` | Conversion status | ❌ Optional |

### Example Implementation

```javascript
// Affiliate network integration
const postbackUrl = 'https://yourdomain.com/track/postback.php?' + 
    'pid=partner123&' +
    'clickid=abc123xyz&' +
    'sum=50.00&' +
    'status=success';

fetch(postbackUrl);
```

## ⚙️ New Features

### Partner-Specific Google Sheets
Each partner can now have their own:
- **Google Spreadsheet ID**: Individual spreadsheet per partner
- **Service Account JSON**: Partner-specific credentials
- **Enhanced Security**: No more shared credentials

### Structured Telegram Notifications
**Old format:**
```
PARTNER: Good Ads | URL: /track/postback.php?pid=good&sum=20&type=sale&s=1121313&clickid=31231321313131313 >>> /track/test.php?sum=18&type=sale&s=18&clickid=31231321313131313 | CLICKID: 31231321313131313 | IP: 2a0c:5a82:cd03:f700:59d4:a9cb:779c:5579 | STATUS: 200 | RESPONSE: 111111111.
```

**New format:**
```
PARTNER: Good Ads
sum=20
type=sale
s=1121313
clickid=31231321313131313
CLICKID: 31231321313131313
IP: 2a0c:5a82:cd03:f700:59d4:a9cb:779c:5579
STATUS: 200
RESPONSE: 111111111
```

## 🧪 Testing

### Test Postback
```bash
curl "https://yourdomain.com/track/postback.php?pid=test&clickid=123&sum=50.00"
```

### Expected Response
- **Telegram Notification**: Structured parameter display
- **Google Sheets**: Automatic data logging
- **Database**: Statistics tracking

## 🔧 Troubleshooting

### Common Issues

#### Database Connection Failed
```
Error: SQLSTATE[HY000] [1045] Access denied
```
**Solution**: Check database credentials in `track/admin/db.php`

#### Postback Not Working
```
Error: Partner configuration not found
```
**Solution**: Verify partner ID exists and is correctly configured

#### Google Sheets Integration Failed
```
Error: Service account authentication failed
```
**Solution**: 
- Verify service account JSON is valid
- Check spreadsheet sharing permissions
- Ensure Google Sheets API is enabled

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch: `git checkout -b feature-name`
3. Commit your changes: `git commit -am 'Add feature'`
4. Push to the branch: `git push origin feature-name`
5. Submit a pull request

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🆘 Support

- **Documentation**: Check the `/docs` directory
- **Issues**: Report bugs via GitHub Issues
- **Discussions**: Use GitHub Discussions for questions

---

**Built with ❤️ for affiliate marketers**