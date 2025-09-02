# Google Sheets Integration

Automatically export tracking data to Google Sheets with partner-specific configurations and real-time updates.

## 📊 Overview

The Google Sheets integration provides:

- **Partner-specific Configurations**: Individual spreadsheets and credentials per partner
- **Real-time Data Export**: Automatic posting of conversion data
- **Queue-based Processing**: Reliable background processing with error handling
- **Dynamic Column Management**: Automatic header creation for new parameters
- **Secure Authentication**: Service Account based authentication per partner

## 🔧 Setup Process

### Step 1: Google Cloud Console Setup

#### 1.1 Create Google Cloud Project
1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Click **"New Project"**
3. Enter project name: `affiliate-tracker-integration`
4. Click **"Create"**

#### 1.2 Enable Google Sheets API
1. Navigate to **"APIs & Services"** > **"Library"**
2. Search for **"Google Sheets API"**
3. Click on it and press **"Enable"**

#### 1.3 Create Service Account
1. Go to **"APIs & Services"** > **"Credentials"**
2. Click **"Create Credentials"** > **"Service Account"**
3. Fill in details:
   ```
   Service account name: affiliate-sheets-access
   Service account ID: affiliate-sheets-access  
   Description: Service account for affiliate tracking sheets access
   ```
4. Click **"Create and Continue"**
5. Skip role assignment (click **"Continue"**)
6. Click **"Done"**

#### 1.4 Generate JSON Key
1. Click on the created service account
2. Go to **"Keys"** tab
3. Click **"Add Key"** > **"Create new key"**
4. Select **"JSON"** format
5. Click **"Create"** - the JSON file will download

### Step 2: Google Sheets Preparation

#### 2.1 Create Spreadsheet
1. Go to [Google Sheets](https://sheets.google.com/)
2. Create a new spreadsheet
3. Name it: `Partner Data - [Partner Name]`
4. Note the **Spreadsheet ID** from URL:
   ```
   https://docs.google.com/spreadsheets/d/1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms/edit
                                      ^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^
                                      This is your Spreadsheet ID
   ```

#### 2.2 Share with Service Account
1. Click **"Share"** button in your spreadsheet
2. Add the service account email (from JSON file):
   ```
   affiliate-sheets-access@your-project.iam.gserviceaccount.com
   ```
3. Set permission to **"Editor"**
4. Uncheck **"Notify people"**
5. Click **"Share"**

### Step 3: Partner Configuration

#### 3.1 Access Partner Settings
1. Go to admin dashboard
2. Click **"Edit"** for your partner
3. Navigate to **"Integrations"** tab

#### 3.2 Configure Google Sheets
```
Spreadsheet ID: 1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms
Sheet Name: Лист1 (or Sheet1)
```

#### 3.3 Add Service Account JSON
Paste the complete JSON content from the downloaded file:
```json
{
  "type": "service_account",
  "project_id": "your-project-id",
  "private_key_id": "key-id-here",
  "private_key": "-----BEGIN PRIVATE KEY-----\nYour-Private-Key-Here\n-----END PRIVATE KEY-----\n",
  "client_email": "affiliate-sheets-access@your-project.iam.gserviceaccount.com",
  "client_id": "123456789012345678901",
  "auth_uri": "https://accounts.google.com/o/oauth2/auth",
  "token_uri": "https://oauth2.googleapis.com/token",
  "auth_provider_x509_cert_url": "https://www.googleapis.com/oauth2/v1/certs",
  "client_x509_cert_url": "https://www.googleapis.com/robot/v1/metadata/x509/affiliate-sheets-access%40your-project.iam.gserviceaccount.com"
}
```

#### 3.4 Save Configuration
Click **"Save"** to store the partner configuration.

## ⚙️ Queue Processing Setup

### Background Processor
The system uses `process_queue.php` for reliable data export:

```bash
# Add to crontab for automatic processing
crontab -e

# Process queue every 5 minutes
*/5 * * * * /usr/bin/php /path/to/track/process_queue.php

# Or every minute for real-time updates
* * * * * /usr/bin/php /path/to/track/process_queue.php
```

### Manual Processing
```bash
# Run queue processor manually
php track/process_queue.php

# Check queue status
ls -la track/queue/

# View processing logs
tail -f track/logs/queue_process.log
```

## 📋 Data Structure

### Exported Columns
The system automatically exports these fields:

| Column | Description | Example |
|--------|-------------|---------||
| `date` | Conversion timestamp | 2024-01-15 14:30:22 |
| `partner_name` | Partner display name | Test Partner |
| `clickid` | Unique click identifier | abc123xyz |
| `sum` | Original sum value | 50.00 |
| `sum_mapping` | Mapped sum value | 40.00 |
| `status` | HTTP response status | 200 |
| `ip` | Client IP address | 192.168.1.100 |
| `response` | Target response (truncated) | Success message... |

### Dynamic Parameters
Additional URL parameters are automatically added as columns:
- `utm_source`
- `utm_campaign` 
- `affiliate_id`
- `sub_id`
- Any custom parameters

### Header Management
- **Automatic Creation**: New parameters create new columns
- **Dynamic Updates**: Headers updated when new fields appear
- **Preserved Order**: Existing columns maintain their position

## 🔍 Monitoring & Troubleshooting

### Queue Status Check
```bash
# Check queue files
ls -la track/queue/
# Should show .json files waiting to be processed

# Check error files
ls -la track/queue/error/
# Should be empty or contain failed processing files
```

### Log Analysis
```bash
# View queue processing logs
tail -f track/logs/queue_process.log

# Sample successful log entry:
[2024-01-15 14:30:25] Google Client Initialized with partner-specific credentials.
[2024-01-15 14:30:25] Found 8 existing headers.
[2024-01-15 14:30:26] Row appended successfully.
[2024-01-15 14:30:26] --- Successfully processed and deleted file: gs_abc123.json ---
```

### Common Issues & Solutions

#### Service Account Authentication Failed
```
Error: Service account authentication failed
```
**Solutions**:
1. Verify JSON format is valid
2. Check spreadsheet sharing with service account email
3. Ensure Google Sheets API is enabled
4. Confirm service account has Editor permissions

#### Spreadsheet Not Found
```
Error: The caller does not have permission
```
**Solutions**:
1. Verify Spreadsheet ID is correct
2. Check service account email has access
3. Ensure spreadsheet is not deleted
4. Confirm sheet name matches configuration

#### Headers Not Updating
```
Error: Unable to update spreadsheet headers
```
**Solutions**:
1. Check service account has Editor (not Viewer) permissions
2. Verify sheet name exists in spreadsheet
3. Ensure no protected ranges block header row

#### Queue Processing Not Working
```
No files being processed from queue
```
**Solutions**:
1. Check crontab configuration:
   ```bash
   crontab -l
   ```
2. Verify file permissions:
   ```bash
   chmod 755 track/process_queue.php
   chmod -R 777 track/queue/
   ```
3. Test manual execution:
   ```bash
   php track/process_queue.php
   ```

## 🚀 Advanced Configuration

### Multiple Spreadsheets per Partner
Configure different sheets for different data types:

```php
// In partner configuration, use different sheet names
Sheet Name: Conversions    // For conversion data
Sheet Name: Clicks        // For click tracking  
Sheet Name: Revenue       // For revenue analysis
```

### Custom Data Processing
Modify `google_sheets_handler.php` to customize data export:

```php
// Add custom calculated fields
$google_sheet_data = [
    'date' => date('Y-m-d H:i:s'),
    'partner_name' => $config['name'],
    'clickid' => $clickId,
    'sum' => $originalSum,
    'sum_mapping' => $sumMappingValue,
    'profit' => floatval($originalSum) - floatval($sumMappingValue), // Custom profit calculation
    'conversion_rate' => $this->calculateConversionRate($partner_id), // Custom calculation
    // ... existing fields
];
```

### Batch Processing Optimization
For high-volume partners, optimize queue processing:

```bash
# Process more files per run
# Edit process_queue.php line 103:
$files_to_process = array_slice($files, 0, 50); // Increase from 20 to 50
```

## 📈 Analytics & Reporting

### Google Sheets Formulas
Add these formulas to your spreadsheet for automatic calculations:

#### Profit Summary
```
=SUM(E:E)-SUM(F:F)  // Total Profit (Sum - Sum Mapping columns)
```

#### Conversion Rate
```
=COUNTIF(G:G,200)/COUNTA(A:A)  // Success rate based on status 200
```

#### Daily Revenue
```
=SUMIFS(E:E,A:A,">="&TODAY(),A:A,"<"&TODAY()+1)  // Today's revenue
```

### Data Visualization
Create charts in Google Sheets:
1. Select your data range
2. Insert > Chart
3. Choose appropriate chart type:
   - **Line Chart**: Daily revenue trends
   - **Pie Chart**: Conversion status distribution  
   - **Bar Chart**: Partner performance comparison

## 🔐 Security Best Practices

### Service Account Security
1. **Limit Permissions**: Only grant Editor access to specific spreadsheets
2. **Regular Rotation**: Regenerate service account keys quarterly
3. **Monitor Usage**: Check Google Cloud Console for API usage

### Data Protection
1. **Encryption**: All data transmitted via HTTPS
2. **Access Control**: Limit spreadsheet sharing
3. **Audit Trail**: Monitor who accesses your spreadsheets

### JSON Credentials Safety
1. **Never Commit**: Keep JSON files out of version control
2. **Environment Variables**: Consider storing credentials as env vars
3. **File Permissions**: Restrict JSON file access (600 permissions)

---

🎉 **Success!** Your Google Sheets integration is now automatically exporting conversion data in real-time.

> 💡 **Pro Tip**: Set up Google Sheets notifications to get alerts when new data is added, or create automated reports using Google Apps Script!

**Related Guides**:
- [Queue Processing](Queue-Processing) - Background job management
- [Partner Management](Partner-Management) - Partner configuration
- [Troubleshooting](Troubleshooting) - Common issues and solutions