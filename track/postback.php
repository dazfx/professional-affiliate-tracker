<?php
/**
 * Professional Affiliate Tracking System - Postback Handler
 * Version: 2.0.0
 * Author: TeamLead Optimized
 * Last Updated: 2025-09-01
 */

declare(strict_types=1);

// Configuration
const DEBUG_MODE = false;
const MAX_EXECUTION_TIME = 30;
const MEMORY_LIMIT = '64M';
const RATE_LIMIT_REQUESTS = 100;
const RATE_LIMIT_WINDOW = 60;

// Set execution limits
ini_set('max_execution_time', (string)MAX_EXECUTION_TIME);
ini_set('memory_limit', MEMORY_LIMIT);

// Enhanced error reporting
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/logs/php_errors.log');

if (DEBUG_MODE) {
    ini_set('display_errors', '1');
} else {
    ini_set('display_errors', '0');
}

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Database connection
try {
    require_once __DIR__ . '/admin/db.php';
} catch (Exception $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    http_response_code(500);
    exit('Database connection error');
}

// Constants
const LOG_DIR = __DIR__ . '/logs';
const QUEUE_DIR = __DIR__ . '/queue';
const CACHE_DIR = __DIR__ . '/cache';

// Ensure directories exist
foreach ([LOG_DIR, QUEUE_DIR, CACHE_DIR] as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// Performance monitor
class PerformanceMonitor
{
    private static array $timers = [];
    private static array $metrics = [];
    
    public static function start(string $label): void
    {
        self::$timers[$label] = microtime(true);
    }
    
    public static function end(string $label): float
    {
        if (!isset(self::$timers[$label])) {
            return 0.0;
        }
        
        $duration = microtime(true) - self::$timers[$label];
        self::$metrics[$label] = $duration;
        unset(self::$timers[$label]);
        
        return $duration;
    }
    
    public static function log(): void
    {
        if (DEBUG_MODE && !empty(self::$metrics)) {
            error_log('Performance metrics: ' . json_encode(self::$metrics));
        }
    }
}

// Rate limiter
class RateLimiter
{
    private static string $cacheFile = CACHE_DIR . '/rate_limits.json';
    
    public static function isAllowed(string $identifier, int $limit = RATE_LIMIT_REQUESTS): bool
    {
        $now = time();
        $windowStart = $now - RATE_LIMIT_WINDOW;
        
        $data = [];
        if (file_exists(self::$cacheFile)) {
            $content = file_get_contents(self::$cacheFile);
            if ($content !== false) {
                $data = json_decode($content, true) ?: [];
            }
        }
        
        // Clean old entries
        foreach ($data as $id => $timestamps) {
            $data[$id] = array_filter($timestamps, fn($ts) => $ts > $windowStart);
            if (empty($data[$id])) {
                unset($data[$id]);
            }
        }
        
        if (!isset($data[$identifier])) {
            $data[$identifier] = [];
        }
        
        if (count($data[$identifier]) >= $limit) {
            return false;
        }
        
        $data[$identifier][] = $now;
        file_put_contents(self::$cacheFile, json_encode($data), LOCK_EX);
        
        return true;
    }
}

// Input validator
class InputValidator
{
    public static function sanitizeString(string $input, int $maxLength = 255): string
    {
        $sanitized = filter_var($input, FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
        return substr($sanitized ?: '', 0, $maxLength);
    }
    
    public static function validatePartnerId(string $partnerId): bool
    {
        return preg_match('/^[a-zA-Z0-9_-]{1,50}$/', $partnerId) === 1;
    }
    
    public static function sanitizeArrayValues(array $input): array
    {
        return array_map(function($value) {
            if (is_string($value)) {
                return self::sanitizeString($value);
            }
            return $value;
        }, $input);
    }
}

// Queue management
function add_to_google_sheet_queue(array $config, array $data): bool
{
    try {
        PerformanceMonitor::start('queue_add');
        
        if (!is_dir(QUEUE_DIR)) {
            if (!mkdir(QUEUE_DIR, 0755, true) && !is_dir(QUEUE_DIR)) {
                throw new RuntimeException('Failed to create queue directory');
            }
        }
        
        if (empty($config['google_spreadsheet_id']) || empty($data)) {
            return false;
        }
        
        $sanitizedData = InputValidator::sanitizeArrayValues($data);
        
        $payload = [
            'config' => $config,
            'data' => $sanitizedData,
            'timestamp' => time(),
            'retry_count' => 0
        ];
        
        $filename = QUEUE_DIR . '/' . uniqid('gs_', true) . '.json';
        $result = file_put_contents($filename, json_encode($payload, JSON_PRETTY_PRINT), LOCK_EX);
        
        if ($result === false) {
            throw new RuntimeException('Failed to write queue file');
        }
        
        PerformanceMonitor::end('queue_add');
        return true;
        
    } catch (Exception $e) {
        error_log('Queue add failed: ' . $e->getMessage());
        return false;
    }
}

// Configuration retrieval
function get_config(PDO $pdo, string $partner_id): ?array
{
    try {
        PerformanceMonitor::start('config_fetch');
        
        if (!InputValidator::validatePartnerId($partner_id)) {
            return null;
        }
        
        $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM settings");
        $stmt->execute();
        $global_settings_raw = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        if ($global_settings_raw === false) {
            throw new RuntimeException('Failed to fetch global settings');
        }
        
        $config = [];
        foreach ($global_settings_raw as $key => $value) {
            if ($value !== null) {
                $decoded = json_decode($value, true);
                $config[$key] = (json_last_error() === JSON_ERROR_NONE) ? $decoded : $value;
            }
        }
        
        $stmt = $pdo->prepare("SELECT * FROM partners WHERE id = ? LIMIT 1");
        $stmt->execute([$partner_id]);
        $partner_config = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$partner_config) {
            return null;
        }
        
        foreach ($partner_config as $key => $value) {
            if ($value !== null) {
                if (is_string($value)) {
                    $decoded = json_decode($value, true);
                    $config[$key] = (json_last_error() === JSON_ERROR_NONE) ? $decoded : $value;
                } else {
                    $config[$key] = $value;
                }
            }
        }
        
        PerformanceMonitor::end('config_fetch');
        return $config;
        
    } catch (Exception $e) {
        error_log('Config fetch failed: ' . $e->getMessage());
        return null;
    }
}

// Statistics update
function update_stats(PDO $pdo, string $partner_id, bool $success = true): void
{
    $success_col = $success ? 'successful_redirects' : 'errors';
    $sql = "INSERT INTO summary_stats (partner_id, total_requests, {$success_col}) VALUES (?, 1, 1) 
            ON DUPLICATE KEY UPDATE total_requests = total_requests + 1, {$success_col} = {$success_col} + 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$partner_id]);
}

// Detailed statistics
function write_detailed_stat(PDO $pdo, array $log_data): void
{
    $sql = "INSERT INTO detailed_stats (partner_id, `timestamp`, url, status, click_id, response, `sum`, sum_mapping, extra_params) 
            VALUES (:partner_id, :timestamp, :url, :status, :click_id, :response, :sum, :sum_mapping, :extra_params)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':partner_id' => $log_data['partner_id'],
        ':timestamp' => $log_data['date'],
        ':url' => $log_data['original_url'],
        ':status' => $log_data['status'],
        ':click_id' => $log_data['click_id'],
        ':response' => $log_data['response'],
        ':sum' => $log_data['sum'],
        ':sum_mapping' => $log_data['sum_mapping'],
        ':extra_params' => json_encode($log_data['extra_params'])
    ]);
}

// Telegram messaging
function send_telegram_message(string $message, string $bot_token, string $channel_id, ?array $whitelist_keywords = null): void
{
    if (empty($bot_token) || empty($channel_id)) { 
        return; 
    }
    
    $sendMessage = true;
    if ($whitelist_keywords !== null && !empty($whitelist_keywords)) {
        $sendMessage = false;
        foreach ($whitelist_keywords as $keyword) {
            if (stripos($message, $keyword) !== false) { 
                $sendMessage = true; 
                break; 
            }
        }
    }
    
    if ($sendMessage) {
        $ch = curl_init();
        $url = "https://api.telegram.org/bot" . $bot_token . "/sendMessage";
        $post_fields = http_build_query([
            'chat_id' => $channel_id, 
            'text' => $message, 
            'parse_mode' => 'HTML'
        ]);
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url, 
            CURLOPT_RETURNTRANSFER => true, 
            CURLOPT_POST => true, 
            CURLOPT_POSTFIELDS => $post_fields, 
            CURLOPT_SSL_VERIFYPEER => false, 
            CURLOPT_TIMEOUT => 10
        ]);
        
        curl_exec($ch);
        curl_close($ch);
    }
}

// Main processing
$partner_id = $_GET['pid'] ?? null;
$clickId = null;
$originalUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . 
              '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . ($_SERVER['REQUEST_URI'] ?? '');

try {
    PerformanceMonitor::start('total_processing');
    
    // Rate limiting
    $clientIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    if (!RateLimiter::isAllowed($clientIP)) {
        throw new RuntimeException("Rate limit exceeded for IP: {$clientIP}", 429);
    }
    
    if (!$partner_id) {
        throw new RuntimeException("Missing partner ID (pid)", 400);
    }

    $config = get_config($pdo, $partner_id);
    if (!$config) {
        throw new RuntimeException("Configuration for partner '{$partner_id}' not found", 404);
    }

    // IP whitelist check
    if (!empty($config['ip_whitelist_enabled']) && !empty($config['allowed_ips']) && is_array($config['allowed_ips'])) {
        if (!in_array($_SERVER['REMOTE_ADDR'], $config['allowed_ips'])) {
            throw new RuntimeException("IP address {$_SERVER['REMOTE_ADDR']} is not allowed for this partner.", 403);
        }
    }

    $params = $_GET;
    unset($params['pid']);
    
    // Extract click ID
    $clickid_keys = $config['clickid_keys'] ?? [];
    foreach ($clickid_keys as $key) { 
        if (!empty($params[$key])) { 
            $clickId = $params[$key]; 
            break; 
        } 
    }
    
    if (empty($clickId)) {
        throw new RuntimeException("Missing clickid parameter", 400);
    }

    // Extract and map sum
    $originalSum = '';
    $sum_keys = $config['sum_keys'] ?? [];
    foreach ($sum_keys as $key) { 
        if (isset($params[$key])) { 
            $originalSum = $params[$key]; 
            break; 
        } 
    }
    
    $sum_mapping = $config['sum_mapping'] ?? [];
    $sumMappingValue = $sum_mapping[$originalSum] ?? '';
    $targetUrlParams = $params;
    
    if ($originalSum && $sumMappingValue !== '') {
        foreach ($sum_keys as $key) { 
            if (isset($targetUrlParams[$key])) { 
                $targetUrlParams[$key] = $sumMappingValue; 
            } 
        }
    }
    
    // Extract extra parameters
    $extra_params = [];
    $known_keys = array_merge(['pid'], $clickid_keys, $sum_keys);
    foreach ($_GET as $key => $value) { 
        if (!in_array($key, $known_keys)) { 
            $extra_params[$key] = $value; 
        } 
    }

    // Make target request
    $targetUrl = 'https://' . $config['target_domain'] . '?' . http_build_query($targetUrlParams);
    
    PerformanceMonitor::start('curl_request');
    $ch = curl_init($targetUrl);
    curl_setopt_array($ch, [
        CURLOPT_TIMEOUT => (int)($config['curl_timeout'] ?? 10),
        CURLOPT_CONNECTTIMEOUT => (int)($config['curl_connect_timeout'] ?? 5),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT => $_SERVER['HTTP_USER_AGENT'] ?? 'AffiliateTracker/2.0'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    PerformanceMonitor::end('curl_request');

    if ($response === false) {
        throw new RuntimeException("Request failed: {$curlError}", 500);
    }
    
    // Update statistics
    update_stats($pdo, $partner_id, true);
    
    // Write detailed statistics
    write_detailed_stat($pdo, [
        'partner_id' => $partner_id,
        'date' => date('Y-m-d H:i:s'),
        'original_url' => $originalUrl,
        'status' => $httpCode,
        'click_id' => $clickId,
        'response' => mb_strimwidth(strip_tags((string)$response), 0, 150, "..."),
        'sum' => $originalSum,
        'sum_mapping' => $sumMappingValue,
        'extra_params' => $extra_params
    ]);

    // Send Telegram notification
    if (!empty($config['telegram_enabled'])) {
        $bot_token = !empty($config['partner_telegram_enabled']) ? 
            $config['partner_telegram_bot_token'] : $config['telegram_bot_token'];
        $channel_id = !empty($config['partner_telegram_enabled']) ? 
            $config['partner_telegram_channel_id'] : $config['telegram_channel_id'];
        $whitelist = !empty($config['telegram_whitelist_enabled']) ? 
            $config['telegram_whitelist_keywords'] : null;
        
        $telegramMessage = "🎯 Conversion Tracked\n" .
                          "Partner: {$config['name']}\n" .
                          "Click ID: {$clickId}\n" .
                          "Sum: {$originalSum}" . ($sumMappingValue ? " → {$sumMappingValue}" : "") . "\n" .
                          "Status: {$httpCode}";
        
        send_telegram_message($telegramMessage, $bot_token, $channel_id, $whitelist);
    }

    // Queue Google Sheets export
    if (!empty($config['google_spreadsheet_id']) && !empty($config['google_service_account_json'])) {
        $google_sheet_data = [
            'date' => date('Y-m-d H:i:s'),
            'partner_name' => $config['name'],
            'clickid' => $clickId,
            'sum' => $originalSum,
            'sum_mapping' => $sumMappingValue,
            'status' => $httpCode,
            'ip' => $_SERVER['REMOTE_ADDR'],
            'response' => mb_strimwidth(strip_tags((string)$response), 0, 50, "...")
        ];
        
        foreach ($extra_params as $key => $value) {
            $google_sheet_data[$key] = $value;
        }
        
        add_to_google_sheet_queue($config, $google_sheet_data);
    }

    PerformanceMonitor::end('total_processing');
    PerformanceMonitor::log();

    // Return response
    http_response_code($httpCode);
    echo $response;

} catch (Exception $e) {
    $errorCode = $e->getCode() ?: 500;
    error_log("Postback error [{$errorCode}]: {$e->getMessage()}");
    
    if (isset($partner_id) && isset($pdo)) {
        update_stats($pdo, $partner_id, false);
    }
    
    http_response_code($errorCode);
    
    if (DEBUG_MODE) {
        echo "Error {$errorCode}: {$e->getMessage()}";
    } else {
        echo "Error {$errorCode}";
    }
} finally {
    PerformanceMonitor::log();
}