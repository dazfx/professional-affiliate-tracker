# Frequently Asked Questions

Common questions and answers about the Professional Affiliate Tracking System.

## 🚀 Getting Started

### Q: What are the minimum system requirements?
**A:** You need:
- PHP 7.4+ with PDO, cURL, JSON extensions
- MySQL 5.7+ or MariaDB 10.3+
- Web server (Apache/Nginx) with mod_rewrite
- At least 1GB RAM and 10GB storage

### Q: How long does installation take?
**A:** About 15-30 minutes for a complete setup, including:
- Database creation and configuration
- File permissions setup
- First partner configuration
- Testing and verification

### Q: Can I run this on shared hosting?
**A:** Yes, if your shared hosting supports:
- PHP 7.4+ with required extensions
- MySQL database access
- Cron jobs for background processing
- .htaccess file support

## 📊 Configuration & Setup

### Q: How do I get a Telegram bot token?
**A:** Follow these steps:
1. Message @BotFather on Telegram
2. Send `/newbot` command
3. Choose a name and username for your bot
4. Copy the provided token (format: `123456789:ABCdef...`)
5. Add your bot to your channel as an administrator

### Q: What's the difference between ClickID keys and Sum keys?
**A:**
- **ClickID Keys**: Parameter names that contain the unique click identifier (e.g., `clickid`, `cid`, `click_id`)
- **Sum Keys**: Parameter names that contain the conversion value/payout (e.g., `sum`, `payout`, `revenue`)

The system will search for these parameters in the postback URL.

### Q: How does sum mapping work?
**A:** Sum mapping transforms the original conversion value to a different amount:
- Original sum `100` → Mapped sum `80` (20% commission)
- Original sum `50` → Mapped sum `40` (20% commission)
- Used for taking commissions, currency conversion, or different payout rates

### Q: Can I have multiple partners with the same target domain?
**A:** Yes, multiple partners can redirect to the same target domain. Each partner has a unique ID and can have different:
- Parameter configurations
- Sum mapping rules
- Integration settings
- Access restrictions

## 🔧 Technical Questions

### Q: How do I create a postback URL?
**A:** The postback URL format is:
```
https://yourdomain.com/track/postback.php?pid=PARTNER_ID&clickid={click_id}&sum={payout}
```
Replace `PARTNER_ID` with your actual partner ID, and `{click_id}`, `{payout}` with your affiliate network's macros.

### Q: What happens if the target domain is down?
**A:** The system will:
1. Attempt to contact the target domain
2. Log the error if it fails
3. Return an appropriate HTTP status code
4. Store the attempt in statistics with error details
5. Continue processing other requests normally

### Q: How often does the queue processor run?
**A:** By default, every 5 minutes via cron job:
```bash
*/5 * * * * /usr/bin/php /path/to/track/process_queue.php
```
You can adjust this to every minute for near real-time processing.

### Q: What's the maximum number of partners I can have?
**A:** There's no hard limit. The system can handle hundreds of partners. Performance depends on:
- Server resources
- Database optimization
- Traffic volume per partner
- Background processing configuration

## 📈 Statistics & Monitoring

### Q: How long is statistical data kept?
**A:** By default, data is kept indefinitely. You can implement data retention by:
- Setting up automated cleanup scripts
- Partitioning tables by date
- Archiving old data to separate tables
- Using the "Clear Stats" feature for specific partners

### Q: What does "Sum Count" vs "Map Count" mean?
**A:**
- **Sum Count**: Conversions that included a sum/payout value
- **Map Count**: Conversions where sum mapping was applied
- **Difference**: Conversions with sums but no mapping rules

### Q: Can I export statistics data?
**A:** Yes, several options:
- Google Sheets integration for automatic export
- Direct database queries
- API endpoints for custom integrations
- Manual CSV export from the admin interface

### Q: How do I search for specific conversions?
**A:** Use the smart search in partner statistics:
- `clickid:abc123` - Find specific click ID
- `param:utm_source` - Find conversions with specific parameters
- `url:google` - Find URLs containing "google"
- `EMPTY` - Find records with missing data
- `status:200` - Find successful conversions

## 🔒 Security & Access

### Q: How secure is the system?
**A:** Security features include:
- IP whitelisting per partner
- Secure database connections
- Input validation and sanitization
- Protected admin interface
- HTTPS support
- Configurable access controls

### Q: Can I restrict access to specific IP addresses?
**A:** Yes, enable IP whitelisting for each partner:
1. Edit partner configuration
2. Go to "Access" tab
3. Enable "IP filtering"
4. Add allowed IP addresses

### Q: How do I protect the admin interface?
**A:** Several methods:
- Use strong passwords
- Implement HTTP basic authentication
- Restrict access by IP address
- Use HTTPS only
- Regular security updates

## 🔗 Integrations

### Q: Which affiliate networks are supported?
**A:** The system works with any affiliate network that supports postback URLs, including:
- ClickBank
- ShareASale
- Commission Junction
- MaxBounty
- PeerFly
- Custom affiliate programs

### Q: How do I set up Google Sheets integration?
**A:** Complete process:
1. Create Google Cloud Project
2. Enable Google Sheets API
3. Create Service Account
4. Download JSON credentials
5. Share your spreadsheet with the service account email
6. Add credentials to partner configuration

### Q: Can I integrate with multiple Telegram channels?
**A:** Yes, two levels:
- **Global**: One bot/channel for all partners
- **Partner-specific**: Individual bot/channel per partner
Partner-specific settings override global settings.

### Q: What about webhook integrations?
**A:** You can extend the system to support webhooks by:
- Modifying the postback processor
- Adding webhook endpoints
- Implementing custom notification handlers
- Using the queue system for reliable delivery

## 🐛 Troubleshooting

### Q: Postback URLs return 404 errors
**A:** Check these items:
1. Verify .htaccess file exists and contains rewrite rules
2. Ensure mod_rewrite is enabled on your web server
3. Check file permissions (755 for directories, 644 for files)
4. Verify the postback.php file exists in the track directory

### Q: No Telegram notifications are sent
**A:** Troubleshooting steps:
1. Verify bot token format: `123456789:ABCdef...`
2. Check channel ID includes minus sign: `-1001234567890`
3. Ensure bot is administrator in the channel
4. Test with a simple message first
5. Check PHP cURL extension is installed

### Q: Google Sheets integration not working
**A:** Common solutions:
1. Verify JSON credentials format
2. Check spreadsheet sharing permissions
3. Ensure Google Sheets API is enabled
4. Confirm service account has Editor access
5. Test with a simple spreadsheet first

### Q: High memory usage or slow performance
**A:** Optimization tips:
1. Add database indexes for large datasets
2. Implement data archiving for old records
3. Optimize queue processing batch size
4. Use database connection pooling
5. Enable PHP OPcache
6. Monitor and tune MySQL settings

### Q: Queue processor not running
**A:** Check these items:
1. Verify cron job is configured correctly
2. Check file permissions for process_queue.php
3. Ensure PHP CLI is available
4. Review error logs for PHP errors
5. Test manual execution: `php process_queue.php`

## 💰 Business & Usage

### Q: Can I use this for multiple businesses?
**A:** Absolutely! You can:
- Create separate partners for different businesses
- Use different target domains
- Configure unique Telegram channels
- Set up separate Google Sheets
- Apply different sum mapping rules

### Q: How do I calculate profit margins?
**A:** The system calculates profit as:
```
Profit = Original Sum - Mapped Sum
```
For example:
- Partner sends $100 conversion
- Your mapping rule: $100 → $80
- Your profit: $100 - $80 = $20

### Q: Can I track multiple conversion types?
**A:** Yes, use different approaches:
- **Multiple Partners**: Different partner IDs for different conversion types
- **URL Parameters**: Use custom parameters to distinguish types
- **Sum Mapping**: Different mapping rules for different values
- **Target Domains**: Route different types to different endpoints

### Q: Is there a limit on conversion volume?
**A:** No built-in limits. System performance depends on:
- Server specifications
- Database optimization
- Network connectivity
- Background processing configuration

Production systems handle thousands of conversions daily.

## 🔄 Updates & Maintenance

### Q: How do I update the system?
**A:** Update process:
1. Backup your database and files
2. Download the latest version
3. Replace system files (preserve config files)
4. Run any database migration scripts
5. Test functionality
6. Clear any caches

### Q: What files should I backup?
**A:** Critical files to backup:
- `admin/db.php` - Database configuration
- Database dump - All data
- `logs/` directory - Historical logs
- Custom modifications to system files

### Q: How do I migrate to a new server?
**A:** Migration steps:
1. Export database from old server
2. Copy all system files
3. Install dependencies on new server
4. Import database to new server
5. Update database connection settings
6. Configure web server
7. Set up cron jobs
8. Test all functionality

## 📞 Support & Resources

### Q: Where can I get help?
**A:** Support resources:
- Check the [Troubleshooting Guide](Troubleshooting)
- Review [Installation Guide](Installation-Guide) for setup issues
- Consult [API Reference](API-Reference) for integration questions
- Check system logs for error details

### Q: How do I report bugs or request features?
**A:** You can:
- Create GitHub issues for bugs
- Submit feature requests
- Contribute to the project
- Share your use cases and requirements

### Q: Can I customize the admin interface?
**A:** Yes, the admin interface can be customized:
- Modify CSS styles
- Add custom dashboard widgets
- Extend API endpoints
- Create custom reports
- Integration additional tools

---

❓ **Still have questions?** Check our comprehensive documentation or reach out for support!

**Related Guides**:
- [Installation Guide](Installation-Guide) - Complete setup instructions
- [Troubleshooting](Troubleshooting) - Common issues and solutions
- [API Reference](API-Reference) - Technical integration details
- [Google Sheets Integration](Google-Sheets-Integration) - Automated data export