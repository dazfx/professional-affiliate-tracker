# Quick Start Guide

Get your affiliate tracking system up and running in just 5 minutes!

## ⚡ Prerequisites Check

Before starting, ensure you have:
- ✅ PHP 7.4+ installed
- ✅ MySQL/MariaDB running
- ✅ Web server configured
- ✅ System already installed (see [Installation Guide](Installation-Guide))

## 🚀 5-Minute Setup

### Step 1: Access Admin Dashboard (30 seconds)

Open your browser and navigate to:
```
http://yourdomain.com/track/admin/
```

You should see the professional dashboard interface.

### Step 2: Configure Global Settings (1 minute)

Click on the **Global Settings** section and configure:

#### Telegram Notifications
```
Bot Token: 123456789:ABCdefGHijklMNOpqrsTUVwxyz
Channel ID: -1001234567890
✅ Enable Telegram globally
```

#### cURL Settings
```
General timeout: 10 seconds
Connection timeout: 5 seconds
```

Click **Save** to apply settings.

### Step 3: Create Your First Partner (2 minutes)

1. Click the **"Добавить" (Add)** button in the Partners section

2. Fill in the **Basic Information** tab:
   ```
   Partner ID: partner001
   Partner Name: Test Partner
   Target Domain: your-affiliate-network.com/track
   ```

3. Switch to **URL Parameters** tab:
   ```
   ClickID Keys: clickid, cid
   Sum Keys: sum, payout
   ```

4. Go to **Integrations** tab and enable:
   ```
   ✅ Enable Telegram for this partner
   ✅ Enable file logging
   ```

5. Click **Save** to create the partner

### Step 4: Get Your Postback URL (30 seconds)

After creating the partner, you'll see the postback URL in the partners table:
```
https://yourdomain.com/track/postback.php?pid=partner001&clickid=...
```

Copy this URL - you'll use it in your affiliate network.

### Step 5: Test the System (1 minute)

Test your setup with a simple request:
```bash
curl "https://yourdomain.com/track/postback.php?pid=partner001&clickid=test123&sum=50.00"
```

**Expected Results**:
- ✅ Response from target domain
- ✅ Statistics appear in admin dashboard
- ✅ Telegram notification sent (if configured)

## 🎯 Your First Conversion

### Add Postback URL to Affiliate Network

In your affiliate network's postback configuration, use:
```
https://yourdomain.com/track/postback.php?pid=partner001&clickid={click_id}&sum={payout}
```

Replace `{click_id}` and `{payout}` with your network's macros.

### Monitor Results

1. **Real-time Dashboard**: Watch conversions appear instantly
2. **Partner Statistics**: Click on your partner name in the sidebar
3. **Telegram Notifications**: Receive structured alerts

## 📊 Understanding Your Data

### Dashboard Metrics
- **Total Requests**: All incoming postbacks
- **Successful Redirects**: Successfully processed conversions  
- **Today's Profit**: `Original Sum - Mapped Sum`
- **Monthly Profit**: Cumulative profit calculation

### Partner Statistics
- **Sum Count**: Conversions with revenue data
- **Map Count**: Conversions with mapped values
- **Detailed View**: Complete conversion history

## 🔧 Common Configurations

### Setting Up Sum Mapping

If you need to transform sum values:

1. Edit your partner
2. Go to **URL Parameters** tab
3. Add sum mapping:
   ```
   Original Sum → Mapped Sum
   100 → 80
   50 → 40
   25 → 20
   ```

This is useful for:
- Taking commission percentages
- Converting currencies
- Applying different payout rates

### IP Whitelisting

For security, restrict access to specific IPs:

1. Edit partner → **Access** tab
2. Enable **IP filtering**
3. Add allowed IPs:
   ```
   64.227.66.201
   192.168.1.100
   ```

### Google Sheets Integration

Export data automatically to Google Sheets:

1. Create Google Cloud Project
2. Enable Sheets API
3. Create Service Account
4. Download JSON credentials
5. Add to partner → **Integrations** tab

## 📱 Mobile Testing

Test from mobile devices:
```bash
# iOS Safari
curl -H "User-Agent: Mozilla/5.0 (iPhone; CPU iPhone OS 14_0 like Mac OS X)" \
  "https://yourdomain.com/track/postback.php?pid=partner001&clickid=mobile123&sum=25.00"

# Android Chrome  
curl -H "User-Agent: Mozilla/5.0 (Linux; Android 10; SM-G973F)" \
  "https://yourdomain.com/track/postback.php?pid=partner001&clickid=android123&sum=30.00"
```

## 🔍 Monitoring & Debugging

### Check Logs
```bash
# PHP errors
tail -f track/logs/php_errors.log

# Queue processing
tail -f track/logs/queue_process.log

# Redirect logs (if enabled)
tail -f track/logs/redirect.log
```

### Debug Mode
Enable debug mode in `postback.php`:
```php
const DEBUG_MODE = true;
```

### Statistics Filtering
Use smart search in partner statistics:
- `clickid:123` - Find specific click ID
- `param:sale` - Find conversions with 'sale' parameter
- `EMPTY` - Find incomplete data

## 🚨 Troubleshooting Quick Fixes

### Postback Returns 404
```bash
# Check URL rewriting
echo "RewriteEngine On" > track/.htaccess
echo "RewriteCond %{REQUEST_FILENAME} !-d" >> track/.htaccess  
echo "RewriteCond %{REQUEST_FILENAME}\.php -f" >> track/.htaccess
echo "RewriteRule ^(.*)$ $1.php" >> track/.htaccess
```

### No Telegram Notifications
1. Verify bot token format: `123456789:ABCdef...`
2. Check channel ID includes minus sign: `-1001234567890`
3. Ensure bot is admin in the channel

### Partner Not Found Error
- Verify partner ID matches exactly (case-sensitive)
- Check partner exists in admin dashboard
- Confirm database connection

### Google Sheets Not Working
- Verify JSON credentials format
- Check spreadsheet sharing permissions
- Enable Google Sheets API in Cloud Console

## 🎉 Success Checklist

After completing this guide, you should have:

- ✅ Working admin dashboard
- ✅ First partner configured  
- ✅ Postback URL ready for use
- ✅ Test conversion processed
- ✅ Real-time statistics visible
- ✅ Telegram notifications working
- ✅ Monitoring and debugging tools ready

## 🚀 Next Steps

Now that your system is running:

1. **Scale Up**: Add more partners and campaigns
2. **Integrate**: Connect with your affiliate networks
3. **Optimize**: Set up sum mapping and profit tracking
4. **Secure**: Review the [Security Guide](Security-Guide)
5. **Automate**: Configure [Google Sheets Integration](Google-Sheets-Integration)

---

🎯 **You're Ready!** Your affiliate tracking system is now processing conversions. 

> 💡 **Pro Tip**: Bookmark the admin dashboard and check it regularly to monitor your affiliate performance!

**Need Help?** Check out our comprehensive guides:
- [Partner Management](Partner-Management)
- [Tracking System](Tracking-System)  
- [Admin Dashboard](Admin-Dashboard)
- [Troubleshooting](Troubleshooting)