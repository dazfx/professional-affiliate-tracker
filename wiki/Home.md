# Professional Affiliate Tracking System Wiki

Welcome to the comprehensive documentation for the Professional Affiliate Tracking System - a robust, enterprise-grade solution for affiliate marketing tracking and analytics.

## 🚀 Quick Navigation

### Getting Started
- [Installation Guide](Installation-Guide) - Step-by-step setup instructions
- [Quick Start](Quick-Start) - Get up and running in 5 minutes
- [Configuration](Configuration) - System configuration and settings

### Core Features
- [Tracking System](Tracking-System) - Postback processing and URL handling
- [Partner Management](Partner-Management) - Managing affiliate partners
- [Google Sheets Integration](Google-Sheets-Integration) - Automated data export
- [Telegram Notifications](Telegram-Notifications) - Real-time alerts

### Administration
- [Admin Dashboard](Admin-Dashboard) - Interface overview and usage
- [Database Schema](Database-Schema) - Complete database structure
- [API Reference](API-Reference) - REST API endpoints
- [Security Guide](Security-Guide) - Security best practices

### Advanced Topics
- [Queue Processing](Queue-Processing) - Background job management
- [Profit Tracking](Profit-Tracking) - Revenue and profit calculations
- [Statistics & Analytics](Statistics-Analytics) - Data analysis features
- [Troubleshooting](Troubleshooting) - Common issues and solutions

### Development
- [Developer Guide](Developer-Guide) - Contributing and customization
- [Deployment Guide](Deployment-Guide) - Production deployment guide
- [Security Guide](Security-Guide) - Security best practices
- [Code Architecture](Code-Architecture) - System design and structure

## 📊 System Overview

The Professional Affiliate Tracking System provides:

✅ **Real-time Postback Processing** - Instant conversion tracking with high-performance handling  
✅ **Partner-specific Google Sheets Integration** - Individual configurations with service account authentication  
✅ **Advanced Analytics Dashboard** - Professional UI with real-time statistics  
✅ **Structured Telegram Notifications** - Clean parameter display instead of URLs  
✅ **IP Whitelisting & Security** - Partner-specific access controls  
✅ **Queue-based Background Processing** - Reliable data handling  
✅ **Profit Calculation** - Automatic profit tracking with sum mapping  

## 🏗️ Architecture

```
┌─────────────────┐    ┌──────────────────┐    ┌─────────────────┐
│   Postback URL  │───▶│  Processing Core │───▶│  Data Storage   │
│                 │    │                  │    │                 │
│ • postback.php  │    │ • Parameter      │    │ • MySQL DB      │
│ • URL params    │    │   validation     │    │ • Statistics    │
│ • Partner ID    │    │ • Sum mapping    │    │ • Detailed logs │
└─────────────────┘    │ • IP whitelist   │    └─────────────────┘
                       └──────────────────┘            │
                                │                       │
                                ▼                       ▼
                       ┌──────────────────┐    ┌─────────────────┐
                       │   Integrations   │    │ Admin Dashboard │
                       │                  │    │                 │
                       │ • Google Sheets  │    │ • Partner mgmt  │
                       │ • Telegram Bot   │    │ • Statistics    │
                       │ • Queue System   │    │ • Configuration │
                       └──────────────────┘    └─────────────────┘
```

## 🔧 Technology Stack

- **Backend**: PHP 7.4+ with PDO
- **Database**: MySQL 5.7+ / MariaDB 10.3+
- **Frontend**: Bootstrap 5 + AdminLTE 3
- **APIs**: Google Sheets API, Telegram Bot API
- **Queue**: File-based background processing
- **Security**: Prepared statements, IP whitelisting, input validation

## 📈 Key Metrics

The system tracks and calculates:
- **Conversion Tracking**: Real-time postback processing
- **Profit Calculation**: `Original Sum - Mapped Sum = Profit`
- **Partner Performance**: Individual statistics and analytics
- **Success Rates**: Request/redirect/error ratios

## 🎯 Use Cases

Perfect for:
- **Affiliate Networks**: Managing multiple partners and campaigns
- **Performance Marketing**: Tracking conversions and ROI
- **Lead Generation**: Monitoring lead quality and volume
- **E-commerce**: Tracking affiliate sales and commissions

## 🆘 Support

- **Documentation**: This wiki contains comprehensive guides
- **Issues**: Report bugs via GitHub Issues
- **Discussions**: Use GitHub Discussions for questions
- **Updates**: Check the repository for latest releases

---

**Built with ❤️ for affiliate marketers worldwide**

> 💡 **Tip**: Start with the [Quick Start Guide](Quick-Start) to get your system running in minutes!