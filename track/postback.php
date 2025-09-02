<?php
declare(strict_types=1);

const DEBUG_MODE = true;

error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/logs/php_errors.log');
if (DEBUG_MODE === true) {
    ini_set('display_errors', '1');
} else {
    ini_set('display_errors', '0');
}

require_once __DIR__ . '/admin/db.php';

const LOG_DIR = __DIR__ . '/logs';
const QUEUE_DIR = __DIR__ . '/queue';

function add_to_google_sheet_queue(array $config, array $data): void
{
    if (!is_dir(QUEUE_DIR)) {
        mkdir(QUEUE_DIR, 0775, true);
    }
    
    $payload = json_encode(['config' => $config, 'data' => $data]);
    $filename = QUEUE_DIR . '/' . uniqid('gs_', true) . '.json';
    file_put_contents($filename, $payload);
}

function get_config(PDO $pdo, string $partner_id): ?array
{
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
    $global_settings_raw = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $config = array_map(function ($value) {
        $decoded = json_decode($value, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }, $global_settings_raw);

    $stmt = $pdo->prepare("SELECT * FROM partners WHERE id = ?");
    $stmt->execute([$partner_id]);
    $partner_config = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$partner_config) {
        return null;
    }

    foreach ($partner_config as $key => $value) {
        if ($value !== null) {
            if (is_string($value)) {
                $decoded = json_decode($value, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $config[$key] = $decoded;
                } else {
                    $config[$key] = $value;
                }
            } else {
                $config[$key] = $value;
            }
        }
    }
    return $config;
}

function update_stats(PDO $pdo, string $partner_id, bool $success = true): void
{
    $success_col = $success ? 'successful_redirects' : 'errors';
    $sql = "INSERT INTO summary_stats (partner_id, total_requests, {$success_col}) VALUES (?, 1, 1) 
            ON DUPLICATE KEY UPDATE total_requests = total_requests + 1, {$success_col} = {$success_col} + 1;";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$partner_id]);
}

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

    $stmt = $pdo->prepare("SELECT stat_id FROM detailed_stats WHERE partner_id = ? ORDER BY `timestamp` DESC LIMIT 1000, 1");
    $stmt->execute([$log_data['partner_id']]);
    $oldest_id = $stmt->fetchColumn();
    if ($oldest_id) {
        $stmt = $pdo->prepare("DELETE FROM detailed_stats WHERE partner_id = ? AND stat_id <= ?");
        $stmt->execute([$log_data['partner_id'], $oldest_id]);
    }
}

function send_telegram_message(string $message, string $bot_token, string $channel_id, ?array $whitelist_keywords = null): void
{
    if (empty($bot_token) || empty($channel_id)) { return; }
    
    $sendMessage = true;
    if ($whitelist_keywords !== null) {
        $sendMessage = false;
        if (!empty($whitelist_keywords)) {
            foreach ($whitelist_keywords as $keyword) {
                if (stripos($message, $keyword) !== false) { $sendMessage = true; break; }
            }
        }
    }
    
    if ($sendMessage) {
        $ch = curl_init();
        $url = "https://api.telegram.org/bot" . $bot_token . "/sendMessage";
        $post_fields = http_build_query(['chat_id' => $channel_id, 'text' => $message, 'parse_mode' => 'HTML']);
        curl_setopt_array($ch, [CURLOPT_URL => $url, CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => $post_fields, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_TIMEOUT => 10]);
        curl_exec($ch);
        curl_close($ch);
    }
}

function get_url_path_with_query(?string $url): string
{
    if (empty($url)) {
        return '';
    }
    $parsed_url = parse_url($url);
    $path = $parsed_url['path'] ?? '/';
    $query = $parsed_url['query'] ?? '';
    return $query ? $path . '?' . $query : $path;
}

$partner_id = $_GET['pid'] ?? null;
$clickId = null;
$originalUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . ($_SERVER['REQUEST_URI'] ?? '');

try {
    if (!$partner_id) throw new RuntimeException("Отсутствует ID партнера (pid)", 400);

    $config = get_config($pdo, $partner_id);
    if (!$config) throw new RuntimeException("Конфигурация для партнера '$partner_id' не найдена", 404);

    if (!empty($config['ip_whitelist_enabled']) && !empty($config['allowed_ips']) && is_array($config['allowed_ips'])) {
        if (!in_array($_SERVER['REMOTE_ADDR'], $config['allowed_ips'])) {
            throw new RuntimeException("IP address {$_SERVER['REMOTE_ADDR']} is not allowed for this partner.", 403);
        }
    }

    $params = $_GET;
    unset($params['pid']);
    
    $clickid_keys = $config['clickid_keys'] ?? [];
    foreach ($clickid_keys as $key) { if (!empty($params[$key])) { $clickId = $params[$key]; break; } }
    if (empty($clickId)) throw new RuntimeException("Отсутствует параметр clickid", 400);

    $originalSum = '';
    $sum_keys = $config['sum_keys'] ?? [];
    foreach ($sum_keys as $key) { if (isset($params[$key])) { $originalSum = $params[$key]; break; } }
    $sum_mapping = $config['sum_mapping'] ?? [];
    $sumMappingValue = $sum_mapping[$originalSum] ?? '';
    $targetUrlParams = $params;
    if ($originalSum && $sumMappingValue !== '') {
        foreach ($sum_keys as $key) { if (isset($targetUrlParams[$key])) { $targetUrlParams[$key] = $sumMappingValue; } }
    }
    
    $extra_params = [];
    $known_keys = array_merge(['pid'], $clickid_keys, $sum_keys);
    foreach ($_GET as $key => $value) { if (!in_array($key, $known_keys)) { $extra_params[$key] = $value; } }

    $targetUrl = 'https://' . $config['target_domain'] . '?' . http_build_query($targetUrlParams);
    
    $ch = curl_init($targetUrl);
    curl_setopt_array($ch, [
        CURLOPT_TIMEOUT => (int)($config['curl_timeout'] ?? 10),
        CURLOPT_CONNECTTIMEOUT => (int)($config['curl_connect_timeout'] ?? 5),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false) {
        throw new RuntimeException("Запрос не удался: " . curl_error($ch), 500);
    }
    
    update_stats($pdo, $partner_id, true);
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

    $telegram_enabled = (bool)($config['telegram_globally_enabled'] ?? false);
    $partner_telegram_enabled = (bool)($config['partner_telegram_enabled'] ?? false);
    
    if ($telegram_enabled && $partner_telegram_enabled && !empty($config['telegram_bot_token']) && !empty($config['telegram_channel_id'])) {
        // New structured parameter format
        $message_parts = [];
        $message_parts[] = "PARTNER: " . ($config['name'] ?? $partner_id);
        
        // Display all parameters except pid
        foreach ($params as $key => $value) {
            $message_parts[] = "{$key}={$value}";
        }
        
        $message_parts[] = "CLICKID: {$clickId}";
        $message_parts[] = "IP: {$_SERVER['REMOTE_ADDR']}";
        $message_parts[] = "STATUS: {$httpCode}";
        $message_parts[] = "RESPONSE: " . mb_strimwidth(strip_tags((string)$response), 0, 50, "...");
        
        $telegramMessage = implode("\n", $message_parts);
        send_telegram_message($telegramMessage, $config['telegram_bot_token'], $config['telegram_channel_id'], $config['telegram_keywords'] ?? null);
    }

    if (!empty($config['google_sheet_name']) && !empty($config['google_spreadsheet_id']) && !empty($config['google_service_account_json'])) {
        $spreadsheet_data = [
            'date' => date('Y-m-d H:i:s'),
            'partner_id' => $partner_id,
            'clickid' => $clickId,
            'sum' => $originalSum,
            'sum_mapping' => $sumMappingValue,
            'status' => $httpCode,
            'ip' => $_SERVER['REMOTE_ADDR'],
            'response' => mb_strimwidth(strip_tags((string)$response), 0, 100, "...")
        ];
        
        foreach ($extra_params as $key => $value) {
            $spreadsheet_data[$key] = $value;
        }
        
        add_to_google_sheet_queue($config, $spreadsheet_data);
    }

    echo $response;

} catch (RuntimeException $e) {
    $httpStatus = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
    http_response_code($httpStatus);
    update_stats($pdo, $partner_id ?? 'unknown', false);
    
    if (isset($partner_id)) {
        write_detailed_stat($pdo, [
            'partner_id' => $partner_id,
            'date' => date('Y-m-d H:i:s'),
            'original_url' => $originalUrl,
            'status' => $httpStatus,
            'click_id' => $clickId ?? '',
            'response' => $e->getMessage(),
            'sum' => '',
            'sum_mapping' => '',
            'extra_params' => []
        ]);
    }
    
    if (DEBUG_MODE) {
        echo "ERROR: " . $e->getMessage();
    } else {
        echo "Error occurred";
    }
}
?>