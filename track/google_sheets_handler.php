<?php
declare(strict_types=1);

// ------------------ РЕЖИМ ОТЛАДКИ ------------------
// Установите true, чтобы видеть ошибки PHP и детальную информацию об исключениях.
// Установите false для рабочего режима (будут выводиться только ответы от целевого URL).
const DEBUG_MODE = true;
// ----------------------------------------------------

error_reporting(E_ALL);
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/logs/php_errors.log');

if (DEBUG_MODE === true) {
    ini_set('display_errors', '1');
} else {
    ini_set('display_errors', '0');
}

// Подключаем автозагрузчик Composer для работы с Google API
require_once '../../vendor/autoload.php';
// Подключаем базу данных
require_once __DIR__ . '/admin/db.php';

const LOG_DIR = __DIR__ . '/logs';

function get_config(PDO $pdo, string $partner_id): ?array
{
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
    $global_settings_raw = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $global_settings = array_map(function ($value) {
        $decoded = json_decode($value, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }, $global_settings_raw);

    $stmt = $pdo->prepare("SELECT * FROM partners WHERE id = ?");
    $stmt->execute([$partner_id]);
    $partner_config = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$partner_config) {
        return null;
    }

    foreach ($partner_config as $key => &$value) {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $value = $decoded;
            }
        }
    }
    return array_merge($global_settings, $partner_config);
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

/**
 * Запись в Google Sheets с использованием партнер-специфичных JSON credentials
 */
function write_to_google_sheet(array $config, array $data): void
{
    $spreadsheetId = $config['google_spreadsheet_id'] ?? null;
    $sheetName = $config['google_sheet_name'] ?? null;
    $serviceAccountJson = $config['google_service_account_json'] ?? null;

    if (empty($spreadsheetId) || empty($sheetName) || empty($serviceAccountJson)) {
        error_log("Google Sheets: Missing Spreadsheet ID, Sheet Name, or Service Account JSON.");
        return;
    }
    
    // Create temporary file for partner-specific JSON credentials
    $temp_key_file = tempnam(sys_get_temp_dir(), 'google_credentials_');
    file_put_contents($temp_key_file, $serviceAccountJson);
    
    try {
        $client = new Google_Client();
        $client->setAuthConfig($temp_key_file);
        $client->setScopes([Google_Service_Sheets::SPREADSHEETS]);
        $service = new Google_Service_Sheets($client);

    $headerRange = $sheetName . '!1:1';
    $headerResponse = $service->spreadsheets_values->get($spreadsheetId, $headerRange);
    $existingHeaders = $headerResponse->getValues()[0] ?? [];

    $headerMap = [];
    foreach ($existingHeaders as $index => $headerName) {
        $headerMap[$headerName] = $index;
    }

    $newHeadersFound = false;
    foreach ($data as $key => $value) {
        if (!array_key_exists($key, $headerMap)) {
            $existingHeaders[] = $key;
            $headerMap[$key] = count($existingHeaders) - 1;
            $newHeadersFound = true;
        }
    }

    if ($newHeadersFound) {
        $valueRange = new Google_Service_Sheets_ValueRange(['values' => [$existingHeaders]]);
        $service->spreadsheets_values->update(
            $spreadsheetId,
            $headerRange,
            $valueRange,
            ['valueInputOption' => 'RAW']
        );
    }

    $rowData = array_fill(0, count($existingHeaders), '');
    foreach ($data as $key => $value) {
        $colIndex = $headerMap[$key];
        $rowData[$colIndex] = is_array($value) ? json_encode($value) : $value;
    }

    $appendRange = $sheetName . '!A1';
    $valueRange = new Google_Service_Sheets_ValueRange(['values' => [$rowData]]);
    $service->spreadsheets_values->append(
        $spreadsheetId,
        $appendRange,
        $valueRange,
        ['valueInputOption' => 'USER_ENTERED']
    );
    
    } finally {
        // Clean up temporary credentials file
        if (file_exists($temp_key_file)) {
            unlink($temp_key_file);
        }
    }
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
    
    $curl_opts = [CURLOPT_TIMEOUT => (int)($config['curl_timeout'] ?? 10), CURLOPT_CONNECTTIMEOUT => (int)($config['curl_connect_timeout'] ?? 5), CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_SSL_VERIFYPEER => false,];
    $ch = curl_init($targetUrl);
    curl_setopt_array($ch, $curl_opts);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        throw new RuntimeException("Запрос не удался: $error", 500);
    }
    
    update_stats($pdo, $partner_id, true);

    $log_data_for_db = [
        'partner_id' => $partner_id,
        'date' => date('Y-m-d H:i:s'),
        'original_url' => $originalUrl,
        'status' => $httpCode,
        'click_id' => $clickId,
        'response' => mb_strimwidth(strip_tags((string)$response), 0, 150, "..."),
        'sum' => $originalSum,
        'sum_mapping' => $sumMappingValue,
        'extra_params' => $extra_params
    ];
    write_detailed_stat($pdo, $log_data_for_db);

    $google_sheet_data = [
        'date'         => date('Y-m-d H:i:s'),
        'partner_name' => $config['name'],
        'original_url' => $originalUrl,
        'target_url'   => $targetUrl,
        'response'     => $response,
        'ip'           => $_SERVER['REMOTE_ADDR'],
        'status'       => $httpCode,
        'click_id'     => $clickId,
        'sum'          => $originalSum,
        'sum_mapping'  => $sumMappingValue,
    ];
    $google_sheet_data = array_merge($google_sheet_data, $params);
    
    // БЛОК ЗАПИСИ В GOOGLE SHEETS С ОТЛОВКОЙ ОШИБОК
    try {
        write_to_google_sheet($config, $google_sheet_data);
    } catch (Throwable $gs_e) {
        // Записываем ошибку в лог в любом режиме
        error_log("Google Sheets API Error for partner '{$partner_id}': " . $gs_e->getMessage());
        // Если включена отладка, выводим ошибку в браузер
        if (DEBUG_MODE === true) {
            echo "\n<!--\n";
            echo "DEBUG MODE: GOOGLE SHEETS API ERROR\n\n";
            echo "Сообщение: " . htmlspecialchars($gs_e->getMessage()) . "\n";
            echo "Файл: " . htmlspecialchars($gs_e->getFile()) . "\n";
            echo "Строка: " . htmlspecialchars($gs_e->getLine()) . "\n";
            echo "-->\n";
        }
    }

    // Create new telegram message format with structured parameters
    $telegramMessage = "PARTNER: {$config['name']}\n";
    
    // Add original URL parameters  
    $originalParams = $_GET;
    unset($originalParams['pid']); // Remove pid since it's shown in partner name
    
    foreach ($originalParams as $key => $value) {
        $telegramMessage .= "{$key}={$value}\n";
    }
    
    $telegramMessage .= "CLICKID: {$clickId}\n";
    $telegramMessage .= "IP: {$_SERVER['REMOTE_ADDR']}\n";
    $telegramMessage .= "STATUS: {$httpCode}\n";
    $telegramMessage .= "RESPONSE: {$response}";

    if (!empty($config['logging_enabled'])) {
        $shortOriginalUrl = get_url_path_with_query($originalUrl);
        $shortTargetUrl = get_url_path_with_query($targetUrl);
        $logMessage = sprintf("PARTNER: %s | URL: %s >>> %s | CLICKID: %s | IP: %s | STATUS: %d | RESPONSE: %s", 
            $config['name'], $shortOriginalUrl, $shortTargetUrl, $clickId, $_SERVER['REMOTE_ADDR'], $httpCode, $response);
        if (!is_dir(LOG_DIR)) mkdir(LOG_DIR, 0777, true);
        file_put_contents(LOG_DIR . '/redirect.log', date('Y-m-d H:i:s') . ' ' . $logMessage . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
    
    if (!empty($config['telegram_globally_enabled']) && !empty($config['telegram_enabled'])) {
        $whitelist = !empty($config['telegram_whitelist_enabled']) ? ($config['telegram_whitelist_keywords'] ?? []) : null;
        send_telegram_message($telegramMessage, (string)($config['telegram_bot_token'] ?? ''), (string)($config['telegram_channel_id'] ?? ''), $whitelist);
    }

    if (!empty($config['partner_telegram_enabled'])) {
        send_telegram_message($telegramMessage, (string)($config['partner_telegram_bot_token'] ?? ''), (string)($config['partner_telegram_channel_id'] ?? ''));
    }

    http_response_code($httpCode);
    echo $response;

} catch (Throwable $e) {
    $error_message = $e->getMessage();
    $error_code = $e->getCode();
    $http_status = ($error_code >= 400 && $error_code < 600) ? $error_code : 500;
    
    if (isset($partner_id) && isset($pdo)) {
        update_stats($pdo, $partner_id, false);
        write_detailed_stat($pdo, [
            'partner_id' => $partner_id,
            'date' => date('Y-m-d H:i:s'),
            'original_url' => $originalUrl,
            'status' => $http_status,
            'click_id' => ($clickId ?? 'N/A'),
            'response' => "Error: " . $error_message,
            'sum' => ($_GET['sum'] ?? ''),
            'sum_mapping' => '',
            'extra_params' => []
        ]);
    }
    
    error_log("Redirect Error (Partner: {$partner_id}): {$error_message}");

    http_response_code($http_status);
    if (DEBUG_MODE === true) {
        header('Content-Type: text/plain; charset=utf-8');
        echo "DEBUG MODE: ИСКЛЮЧЕНИЕ ПЕРЕХВАЧЕНО\n\n";
        echo "Сообщение: " . $e->getMessage() . "\n";
        echo "Код: " . $e->getCode() . "\n";
        echo "Файл: " . $e->getFile() . "\n";
        echo "Строка: " . $e->getLine() . "\n\n";
        echo "Трассировка:\n" . $e->getTraceAsString();
    } else {
        echo ($http_status === 403) ? 'Access Denied' : 'Произошла ошибка';
    }
}