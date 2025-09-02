# API Reference

Complete REST API documentation for the Professional Affiliate Tracking System admin interface.

## 🌐 API Overview

The admin API provides comprehensive endpoints for managing partners, settings, and retrieving statistics data.

**Base URL**: `https://yourdomain.com/track/admin/api.php`  
**Content-Type**: `application/json`  
**Authentication**: Session-based (admin interface access required)

## 📊 Partner Management

### Save Partner
Create or update partner configuration.

```http
POST /track/admin/api.php
Content-Type: application/json

{
    "action": "save_partner",
    "partner": {
        "id": "partner001",
        "name": "Premium Network",
        "target_domain": "network.example.com/track",
        "notes": "High-value partner with premium rates",
        "clickid_keys": ["clickid", "cid"],
        "sum_keys": ["sum", "payout"],
        "sum_mapping": {"100": "80", "50": "40"},
        "logging_enabled": true,
        "telegram_enabled": true,
        "telegram_whitelist_enabled": false,
        "telegram_whitelist_keywords": ["purchase", "sale"],
        "ip_whitelist_enabled": true,
        "allowed_ips": ["64.227.66.201", "192.168.1.100"],
        "partner_telegram_enabled": false,
        "partner_telegram_bot_token": "",
        "partner_telegram_channel_id": "",
        "google_sheet_name": "Conversions",
        "google_spreadsheet_id": "1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms",
        "google_service_account_json": "{\"type\": \"service_account\", ...}"
    },
    "old_id": "partner001"
}
```

**Response - Success**:
```json
{
    "success": true,
    "message": "Партнер успешно сохранен."
}
```

**Response - Error**:
```json
{
    "success": false,
    "message": "ID, Имя партнера и целевой домен обязательны."
}
```

### Get Partner Data
Retrieve complete partner configuration.

```http
POST /track/admin/api.php
Content-Type: application/json

{
    "action": "get_partner_data",
    "id": "partner001"
}
```

**Response**:
```json
{
    "success": true,
    "partner": {
        "id": "partner001",
        "name": "Premium Network",
        "target_domain": "network.example.com/track",
        "notes": "High-value partner with premium rates",
        "clickid_keys": ["clickid", "cid"],
        "sum_keys": ["sum", "payout"],
        "sum_mapping": {"100": "80", "50": "40"},
        "logging_enabled": true,
        "telegram_enabled": true,
        "ip_whitelist_enabled": true,
        "allowed_ips": ["64.227.66.201"],
        "google_sheet_name": "Conversions",
        "google_spreadsheet_id": "1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms",
        "created_at": "2024-01-15 10:30:00"
    }
}
```

### Delete Partner
Remove partner and all associated data.

```http
POST /track/admin/api.php
Content-Type: application/json

{
    "action": "delete_partner",
    "id": "partner001"
}
```

**Response**:
```json
{
    "success": true,
    "message": "Партнер удален."
}
```

## ⚙️ Settings Management

### Save Global Settings
Update system-wide configuration.

```http
POST /track/admin/api.php
Content-Type: application/json

{
    "action": "save_global_settings",
    "settings": {
        "telegram_globally_enabled": true,
        "telegram_bot_token": "123456789:ABCdef-GHijklMNOpqrsTUVwxyz",
        "telegram_channel_id": "-1001234567890",
        "curl_timeout": 10,
        "curl_connect_timeout": 5,
        "curl_ssl_verify": true,
        "curl_returntransfer": true,
        "curl_followlocation": true
    }
}
```

**Response**:
```json
{
    "success": true,
    "message": "Глобальные настройки сохранены."
}
```

## 📈 Statistics & Analytics

### Get Detailed Statistics
Retrieve conversion data with advanced filtering.

```http
GET /track/admin/api.php?action=get_detailed_stats&partner_id=partner001&search_term=clickid:abc123&status=200&start_date=2024-01-01&end_date=2024-01-31
```

**Query Parameters**:
- `partner_id` (required): Partner identifier
- `search_term` (optional): Smart search query
- `status` (optional): HTTP status filter (200, 403, 500, or 'all')
- `start_date` (optional): Start date filter (YYYY-MM-DD)
- `end_date` (optional): End date filter (YYYY-MM-DD)

**Search Term Examples**:
- `clickid:abc123` - Find specific click ID
- `param:sale` - Find conversions with 'sale' parameter
- `url:google` - Find URLs containing 'google'
- `EMPTY` - Find records with missing data
- `general_term` - Search across all fields

**Response**:
```json
{
    "data": [
        {
            "date": "2024-01-15 14:30:25",
            "click_id": "abc123xyz",
            "url": "https://domain.com/track/postback.php?pid=partner001&clickid=abc123xyz&sum=50.00",
            "status": 200,
            "response": "Success: Conversion recorded",
            "sum": "50.00",
            "sum_mapping": "40.00",
            "extra_params": "{\"utm_source\":\"google\",\"utm_campaign\":\"winter_sale\"}"
        },
        {
            "date": "2024-01-15 14:25:10",
            "click_id": "def456uvw",
            "url": "https://domain.com/track/postback.php?pid=partner001&clickid=def456uvw&sum=25.00",
            "status": 200,
            "response": "Success: Lead processed",
            "sum": "25.00",
            "sum_mapping": "20.00",
            "extra_params": "{\"source\":\"facebook\"}"
        }
    ]
}
```

### Clear Partner Statistics
Remove all statistical data for a partner.

```http
POST /track/admin/api.php
Content-Type: application/json

{
    "action": "clear_partner_stats",
    "id": "partner001"
}
```

**Response**:
```json
{
    "success": true,
    "message": "Статистика для партнера partner001 очищена."
}
```

## 🔒 Authentication & Security

### Session Requirements
All API endpoints require valid admin session:

```http
Cookie: PHPSESSID=abc123def456ghi789
```

### Error Handling
All endpoints return consistent error format:

```json
{
    "success": false,
    "message": "Error description in Russian"
}
```

### Common Error Codes

| HTTP Status | Description |
|-------------|-------------|
| 200 | Success |
| 400 | Bad Request - Invalid parameters |
| 401 | Unauthorized - No valid session |
| 403 | Forbidden - Access denied |
| 500 | Internal Server Error |

## 📊 Data Types

### Partner Object Schema
```typescript
interface Partner {
    id: string;                              // Unique identifier
    name: string;                           // Display name
    target_domain: string;                  // Redirect target
    notes?: string;                         // Optional description
    clickid_keys: string[];                 // ClickID parameter names
    sum_keys: string[];                     // Sum parameter names
    sum_mapping: Record<string, string>;    // Sum transformation map
    logging_enabled: boolean;               // File logging toggle
    telegram_enabled: boolean;              // Global Telegram toggle
    telegram_whitelist_enabled: boolean;    // Keyword filtering toggle
    telegram_whitelist_keywords: string[]; // Filter keywords
    ip_whitelist_enabled: boolean;          // IP filtering toggle
    allowed_ips: string[];                  // Allowed IP addresses
    partner_telegram_enabled: boolean;      // Individual bot toggle
    partner_telegram_bot_token?: string;    // Individual bot token
    partner_telegram_channel_id?: string;   // Individual channel ID
    google_sheet_name?: string;             // Sheets worksheet name
    google_spreadsheet_id?: string;         // Sheets document ID
    google_service_account_json?: string;   // Service account credentials
    created_at: string;                     // Creation timestamp
}
```

### Statistics Record Schema
```typescript
interface StatisticsRecord {
    date: string;           // Conversion timestamp
    click_id: string;       // Click identifier
    url: string;            // Original postback URL
    status: number;         // HTTP response status
    response: string;       // Target server response
    sum: string;            // Original sum value
    sum_mapping: string;    // Mapped sum value
    extra_params: string;   // JSON string of additional parameters
}
```

## 🚀 Usage Examples

### JavaScript/jQuery Example
```javascript
// Save partner configuration
function savePartner(partnerData) {
    $.ajax({
        url: '/track/admin/api.php',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({
            action: 'save_partner',
            partner: partnerData,
            old_id: partnerData.id
        }),
        success: function(response) {
            if (response.success) {
                showToast('Success', response.message, 'success');
                reloadPartnersList();
            } else {
                showToast('Error', response.message, 'error');
            }
        },
        error: function() {
            showToast('Error', 'Network error occurred', 'error');
        }
    });
}

// Get partner statistics with filtering
function loadPartnerStats(partnerId, filters = {}) {
    const params = new URLSearchParams({
        action: 'get_detailed_stats',
        partner_id: partnerId,
        ...filters
    });
    
    $.get(`/track/admin/api.php?${params}`)
        .done(function(response) {
            populateStatsTable(response.data);
        })
        .fail(function() {
            console.error('Failed to load statistics');
        });
}
```

### PHP Client Example
```php
// API client class
class AffiliateAPI {
    private $baseUrl;
    private $sessionCookie;
    
    public function __construct($baseUrl, $sessionCookie) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->sessionCookie = $sessionCookie;
    }
    
    public function savePartner($partnerData, $oldId = null) {
        return $this->makeRequest([
            'action' => 'save_partner',
            'partner' => $partnerData,
            'old_id' => $oldId
        ]);
    }
    
    public function getPartnerStats($partnerId, $filters = []) {
        $params = array_merge(['action' => 'get_detailed_stats', 'partner_id' => $partnerId], $filters);
        return $this->makeGetRequest($params);
    }
    
    private function makeRequest($data) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->baseUrl . '/admin/api.php',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Cookie: ' . $this->sessionCookie
            ]
        ]);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        return json_decode($response, true);
    }
}
```

### cURL Examples
```bash
# Create new partner
curl -X POST https://yourdomain.com/track/admin/api.php \
  -H "Content-Type: application/json" \
  -H "Cookie: PHPSESSID=your_session_id" \
  -d '{
    "action": "save_partner",
    "partner": {
      "id": "new_partner",
      "name": "New Affiliate",
      "target_domain": "affiliate.example.com/track",
      "clickid_keys": ["clickid"],
      "sum_keys": ["sum"],
      "sum_mapping": {},
      "logging_enabled": true,
      "telegram_enabled": true
    }
  }'

# Get statistics with filters
curl "https://yourdomain.com/track/admin/api.php?action=get_detailed_stats&partner_id=partner001&status=200&start_date=2024-01-01" \
  -H "Cookie: PHPSESSID=your_session_id"

# Update global settings
curl -X POST https://yourdomain.com/track/admin/api.php \
  -H "Content-Type: application/json" \
  -H "Cookie: PHPSESSID=your_session_id" \
  -d '{
    "action": "save_global_settings",
    "settings": {
      "telegram_globally_enabled": true,
      "curl_timeout": 15
    }
  }'
```

## 🔍 Advanced Filtering

### Smart Search Syntax
The `search_term` parameter supports advanced queries:

| Syntax | Description | Example |
|--------|-------------|---------|
| `field:value` | Search specific field | `clickid:abc123` |
| `EMPTY` | Find missing data | `EMPTY` |
| `general_term` | Search all fields | `google` |

### Supported Search Fields
- `clickid` - Click identifier
- `url` - Original postback URL  
- `param` - Extra parameters JSON
- `status` - HTTP status code
- `response` - Target response text

### Date Filtering
- Format: `YYYY-MM-DD`
- `start_date` - Include records from this date
- `end_date` - Include records up to this date
- Time is automatically set (00:00:00 for start, 23:59:59 for end)

---

📚 **API Reference Complete!** This covers all available endpoints for managing your affiliate tracking system programmatically.

> 💡 **Pro Tip**: Use the browser's developer tools to inspect actual API calls made by the admin interface for real-world examples!

**Related Guides**:
- [Admin Dashboard](Admin-Dashboard) - Interface usage
- [Partner Management](Partner-Management) - Configuration details
- [Developer Guide](Developer-Guide) - Integration examples