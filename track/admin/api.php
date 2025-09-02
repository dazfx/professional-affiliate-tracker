<?php
header('Content-Type: application/json');
require_once 'db.php';

ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

set_exception_handler(function ($exception) {
    error_log($exception->getMessage() . "\n" . $exception->getTraceAsString());
    if (!headers_sent()) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Внутренняя ошибка сервера. Подробности записаны в лог.']);
    }
    exit;
});

$response = ['success' => false, 'message' => 'Неизвестное действие.'];
$data = json_decode(file_get_contents('php://input'), true);
if (json_last_error() !== JSON_ERROR_NONE && !empty(file_get_contents('php://input'))) {
    throw new Exception("Некорректный JSON в теле запроса: " . json_last_error_msg());
}
$action = $data['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'save_partner':
            $partner_data = $data['partner'];
            $old_id = $data['old_id'] ?? null;

            if (empty($partner_data['id']) || empty($partner_data['name']) || empty($partner_data['target_domain'])) {
                $response['message'] = 'ID, Имя партнера и целевой домен обязательны.';
                break;
            }

            $is_new_partner = empty($old_id);
            $id_changed = !$is_new_partner && $old_id !== $partner_data['id'];

            if ($is_new_partner || $id_changed) {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM partners WHERE id = ?");
                $stmt->execute([$partner_data['id']]);
                if ($stmt->fetchColumn() > 0) {
                    $response['message'] = "Партнер с ID '{$partner_data['id']}' уже существует.";
                    echo json_encode($response);
                    exit;
                }
            }
            
            $json_fields = ['clickid_keys', 'sum_keys', 'sum_mapping', 'telegram_whitelist_keywords', 'allowed_ips'];
            foreach ($json_fields as $field) {
                $partner_data[$field] = $partner_data[$field] ?? [];
            }

            $bool_fields = ['logging_enabled', 'telegram_enabled', 'telegram_whitelist_enabled', 'ip_whitelist_enabled', 'partner_telegram_enabled'];
            foreach ($bool_fields as $field) {
                $partner_data[$field] = filter_var($partner_data[$field] ?? false, FILTER_VALIDATE_BOOLEAN);
            }

            if ($is_new_partner) {
                foreach ($json_fields as $field) {
                    $partner_data[$field] = json_encode($partner_data[$field]);
                }
                $sql = "INSERT INTO partners (id, name, target_domain, notes, clickid_keys, sum_keys, sum_mapping, logging_enabled, telegram_enabled, telegram_whitelist_enabled, telegram_whitelist_keywords, ip_whitelist_enabled, allowed_ips, partner_telegram_enabled, partner_telegram_bot_token, partner_telegram_channel_id, google_sheet_name, google_spreadsheet_id, google_service_account_json) 
                        VALUES (:id, :name, :target_domain, :notes, :clickid_keys, :sum_keys, :sum_mapping, :logging_enabled, :telegram_enabled, :telegram_whitelist_enabled, :telegram_whitelist_keywords, :ip_whitelist_enabled, :allowed_ips, :partner_telegram_enabled, :partner_telegram_bot_token, :partner_telegram_channel_id, :google_sheet_name, :google_spreadsheet_id, :google_service_account_json)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($partner_data);
            } else {
                $allowed_fields = [
                    'id', 'name', 'target_domain', 'notes', 'clickid_keys', 'sum_keys', 'sum_mapping', 
                    'logging_enabled', 'telegram_enabled', 'telegram_whitelist_enabled', 
                    'telegram_whitelist_keywords', 'ip_whitelist_enabled', 'allowed_ips', 
                    'partner_telegram_enabled', 'partner_telegram_bot_token', 'partner_telegram_channel_id',
                    'google_sheet_name', 'google_spreadsheet_id', 'google_service_account_json'
                ];
                
                $sql_set_parts = [];
                $params_for_execute = [];

                foreach ($allowed_fields as $field) {
                    if ($field === 'id' && !$id_changed) continue;
                    if (array_key_exists($field, $partner_data)) {
                        $sql_set_parts[] = "`{$field}` = :{$field}";
                        $params_for_execute[$field] = in_array($field, $json_fields) ? json_encode($partner_data[$field]) : $partner_data[$field];
                    }
                }

                if (empty($sql_set_parts)) {
                    $response = ['success' => true, 'message' => 'Нет данных для обновления.'];
                    break;
                }

                $params_for_execute['where_id'] = $old_id;
                $sql_set_clause = implode(', ', $sql_set_parts);
                $sql = "UPDATE partners SET {$sql_set_clause} WHERE id = :where_id";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params_for_execute);
            }
            
            $response = ['success' => true, 'message' => 'Партнер успешно сохранен.'];
            break;

        case 'delete_partner':
            $partner_id = $data['id'] ?? null;
            if (!$partner_id) {
                $response['message'] = 'Не указан ID партнера.';
                break;
            }
            $stmt = $pdo->prepare("DELETE FROM partners WHERE id = ?");
            $stmt->execute([$partner_id]);
            $response = ['success' => true, 'message' => 'Партнер удален.'];
            break;

        case 'save_global_settings':
            $settings = $data['settings'];
            $sql = "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)";
            $stmt = $pdo->prepare($sql);
            foreach ($settings as $key => $value) {
                $stmt->execute([$key, is_array($value) ? json_encode($value) : ($value === true ? 'true' : ($value === false ? 'false' : $value)) ]);
            }
            $response = ['success' => true, 'message' => 'Глобальные настройки сохранены.'];
            break;

        case 'clear_partner_stats':
            $partner_id = $data['id'] ?? null;
            if (!$partner_id) {
                $response['message'] = 'Не указан ID партнера.';
                break;
            }
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("DELETE FROM detailed_stats WHERE partner_id = ?");
            $stmt->execute([$partner_id]);
            $stmt = $pdo->prepare("DELETE FROM summary_stats WHERE partner_id = ?");
            $stmt->execute([$partner_id]);
            $pdo->commit();
            $response = ['success' => true, 'message' => "Статистика для партнера {$partner_id} очищена."];
            break;

        case 'get_partner_data':
            $partner_id = $data['id'] ?? null;
            if (!$partner_id) {
                $response['message'] = 'Не указан ID партнера.';
                break;
            }
            $stmt = $pdo->prepare("SELECT * FROM partners WHERE id = ?");
            $stmt->execute([$partner_id]);
            $partner = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($partner) {
                $json_fields_get = ['clickid_keys', 'sum_keys', 'sum_mapping', 'telegram_whitelist_keywords', 'allowed_ips'];
                foreach ($json_fields_get as $field) {
                    if (!empty($partner[$field])) {
                        $partner[$field] = json_decode($partner[$field], true);
                    } else {
                        $partner[$field] = [];
                    }
                }
                $response = ['success' => true, 'partner' => $partner];
            } else {
                $response['message'] = 'Партнер не найден.';
            }
            break;

        case 'get_detailed_stats':
            $partner_id = $_GET['partner_id'] ?? null;
            if (!$partner_id) {
                echo json_encode(['data' => []]);
                exit;
            }
            $sql = "SELECT `timestamp` as date, click_id, url, status, response, `sum`, sum_mapping, extra_params FROM detailed_stats WHERE partner_id = ?";
            $params = [$partner_id];

            if (!empty($_GET['search_term'])) {
                $search_term = trim($_GET['search_term']);
                if (strtoupper($search_term) === 'EMPTY') {
                    $sql .= " AND (click_id IS NULL OR click_id = '' OR url IS NULL OR url = '' OR extra_params IS NULL OR extra_params = '' OR extra_params = '[]')";
                } elseif (strpos($search_term, ':') !== false) {
                    list($key, $value) = array_map('trim', explode(':', $search_term, 2));
                    $value_param = '%' . $value . '%';

                    switch (strtolower($key)) {
                        case 'clickid':
                            $sql .= " AND click_id LIKE ?";
                            $params[] = $value_param;
                            break;
                        case 'url':
                            $sql .= " AND url LIKE ?";
                            $params[] = $value_param;
                            break;
                        case 'param':
                            $sql .= " AND extra_params LIKE ?";
                            $params[] = $value_param;
                            break;
                        default:
                            $sql .= " AND (click_id LIKE ? OR url LIKE ? OR extra_params LIKE ?)";
                            $searchTermParam = '%' . $search_term . '%';
                            $params[] = $searchTermParam; $params[] = $searchTermParam; $params[] = $searchTermParam;
                            break;
                    }
                } else {
                    $sql .= " AND (click_id LIKE ? OR url LIKE ? OR extra_params LIKE ?)";
                    $searchTermParam = '%' . $search_term . '%';
                    $params[] = $searchTermParam;
                    $params[] = $searchTermParam;
                    $params[] = $searchTermParam;
                }
            }
            
            if (!empty($_GET['status']) && $_GET['status'] !== 'all') {
                $sql .= " AND status = ?";
                $params[] = $_GET['status'];
            }
            if (!empty($_GET['start_date'])) {
                $sql .= " AND `timestamp` >= ?";
                $params[] = $_GET['start_date'] . ' 00:00:00';
            }
            if (!empty($_GET['end_date'])) {
                $sql .= " AND `timestamp` <= ?";
                $params[] = $_GET['end_date'] . ' 23:59:59';
            }
            $sql .= " ORDER BY `timestamp` DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['data' => $data]);
            exit;
    }
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}

echo json_encode($response);
?>