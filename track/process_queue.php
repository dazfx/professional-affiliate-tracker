<?php
// Этот скрипт должен запускаться через CRON каждую минуту.

// Устанавливаем максимальное время выполнения, чтобы успеть обработать несколько файлов
set_time_limit(55); 

// Убедимся, что мы находимся в правильной директории
chdir(__DIR__);

require_once '../../../vendor/autoload.php';

const QUEUE_DIR = __DIR__ . '/queue';
const LOG_FILE = __DIR__ . '/logs/queue_process.log'; // Выделенный лог для обработчика

// Устанавливаем уровень ошибок, чтобы избежать E_DEPRECATED
error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('log_errors', '1');
ini_set('error_log', LOG_FILE);

function log_message(string $message): void
{
    // Записываем в лог с меткой времени
    file_put_contents(LOG_FILE, date('[Y-m-d H:i:s] ') . $message . PHP_EOL, FILE_APPEND);
}

function write_to_google_sheet(array $config, array $data): void
{
    $spreadsheetId = $config['google_spreadsheet_id'] ?? null;
    $sheetName = $config['google_sheet_name'] ?? null;
    $serviceAccountJson = $config['google_service_account_json'] ?? null;

    log_message("Attempting to write to Sheet ID: " . ($spreadsheetId ?: 'NONE') . ", Sheet Name: " . ($sheetName ?: 'NONE'));

    if (empty($spreadsheetId) || empty($sheetName) || empty($serviceAccountJson)) {
        log_message("Skipped: Missing Spreadsheet ID, Sheet Name, or Service Account JSON.");
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
        log_message("Google Client Initialized with partner-specific credentials.");

    $headerRange = $sheetName . '!1:1';
    $headerResponse = $service->spreadsheets_values->get($spreadsheetId, $headerRange);
    $existingHeaders = $headerResponse->getValues()[0] ?? [];
    log_message("Found " . count($existingHeaders) . " existing headers.");

    $headerMap = array_flip($existingHeaders);
    
    $newHeaders = [];
    foreach ($data as $key => $value) {
        if (!isset($headerMap[$key])) {
            $newHeaders[] = $key;
        }
    }

    if (!empty($newHeaders)) {
        log_message("Found new headers to add: " . implode(', ', $newHeaders));
        $allHeaders = array_merge($existingHeaders, $newHeaders);
        $valueRange = new Google_Service_Sheets_ValueRange(['values' => [$allHeaders]]);
        $service->spreadsheets_values->update($spreadsheetId, $headerRange, $valueRange, ['valueInputOption' => 'RAW']);
        $headerMap = array_flip($allHeaders);
        log_message("Headers updated successfully.");
    }

    $rowData = array_fill(0, count($headerMap), '');
    foreach ($data as $key => $value) {
        if (isset($headerMap[$key])) {
            $colIndex = $headerMap[$key];
            $rowData[$colIndex] = is_array($value) ? json_encode($value) : $value;
        }
    }
    
    $appendRange = $sheetName;
    $valueRange = new Google_Service_Sheets_ValueRange(['values' => [$rowData]]);
    $service->spreadsheets_values->append($spreadsheetId, $appendRange, $valueRange, ['valueInputOption' => 'USER_ENTERED']);
    log_message("Row appended successfully.");
    
    } finally {
        // Clean up temporary credentials file
        if (file_exists($temp_key_file)) {
            unlink($temp_key_file);
        }
    }
}

// --- НАЧАЛО ОБРАБОТКИ ОЧЕРЕДИ ---
if (!is_writable(dirname(LOG_FILE))) {
    die("Error: Log directory is not writable.");
}

log_message("=========================================");
log_message("Queue processor started by " . (php_sapi_name() === 'cli' ? 'CLI' : 'Web Server'));

if (!is_dir(QUEUE_DIR)) {
    log_message("Queue directory not found. Exiting.");
    exit;
}

$files = array_slice(scandir(QUEUE_DIR), 2); 
if (empty($files)) {
    log_message("Queue is empty. Exiting.");
    exit;
}

log_message("Found " . count($files) . " files in queue. Processing up to 20.");
$files_to_process = array_slice($files, 0, 20);

foreach ($files_to_process as $file) {
    $filePath = QUEUE_DIR . '/' . $file;
    if (is_file($filePath)) {
        log_message("--- Processing file: {$file} ---");
        try {
            $payload = json_decode(file_get_contents($filePath), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception("Invalid JSON in queue file. Error: " . json_last_error_msg());
            }
            if (isset($payload['config']) && isset($payload['data'])) {
                write_to_google_sheet($payload['config'], $payload['data']);
            } else {
                log_message("Skipping file {$file}: invalid payload structure.");
            }
            unlink($filePath);
            log_message("--- Successfully processed and deleted file: {$file} ---");
        } catch (Throwable $e) {
            log_message("!!! ERROR processing file {$file}: " . $e->getMessage());
            $errorDir = QUEUE_DIR . '/error';
            if (!is_dir($errorDir)) {
                mkdir($errorDir, 0775, true);
            }
            rename($filePath, $errorDir . '/' . basename($file));
        }
    }
}

log_message("Queue processor finished.");
log_message("=========================================\n");