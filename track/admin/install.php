<?php
// Файл: admin/install.php

echo '<!DOCTYPE html><html lang="ru"><head><meta charset="UTF-8"><title>Установка базы данных</title>';
echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head>';
echo '<body class="bg-light"><div class="container mt-5"><div class="card"><div class="card-body">';
echo '<h1>Установка таблиц для Redirect Panel</h1>';

try {
    require_once 'db.php';

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `settings` (
            `setting_key` VARCHAR(50) NOT NULL PRIMARY KEY,
            `setting_value` TEXT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo '<p class="text-success">Таблица `settings` успешно создана или уже существует.</p>';

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `partners` (
            `id` VARCHAR(100) NOT NULL PRIMARY KEY,
            `name` VARCHAR(255) NOT NULL,
            `notes` TEXT,
            `target_domain` VARCHAR(255) NOT NULL,
            `clickid_keys` JSON,
            `sum_keys` JSON,
            `sum_mapping` JSON,
            `logging_enabled` BOOLEAN DEFAULT TRUE,
            `telegram_enabled` BOOLEAN DEFAULT TRUE,
            `telegram_whitelist_enabled` BOOLEAN DEFAULT FALSE,
            `telegram_whitelist_keywords` JSON,
            `ip_whitelist_enabled` BOOLEAN DEFAULT FALSE,
            `allowed_ips` JSON,
            `partner_telegram_enabled` BOOLEAN DEFAULT FALSE,
            `partner_telegram_bot_token` VARCHAR(255) DEFAULT NULL,
            `partner_telegram_channel_id` VARCHAR(255) DEFAULT NULL,
            `google_sheet_name` VARCHAR(255) DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo '<p class="text-success">Таблица `partners` успешно создана или уже существует.</p>';

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `summary_stats` (
            `partner_id` VARCHAR(100) NOT NULL PRIMARY KEY,
            `total_requests` INT UNSIGNED NOT NULL DEFAULT 0,
            `successful_redirects` INT UNSIGNED NOT NULL DEFAULT 0,
            `errors` INT UNSIGNED NOT NULL DEFAULT 0,
            CONSTRAINT `fk_partner_id_summary` FOREIGN KEY (`partner_id`) REFERENCES `partners` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo '<p class="text-success">Таблица `summary_stats` успешно создана или уже существует.</p>';

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `detailed_stats` (
            `stat_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `partner_id` VARCHAR(100) NOT NULL,
            `timestamp` DATETIME NOT NULL,
            `url` TEXT,
            `status` SMALLINT,
            `click_id` VARCHAR(255),
            `response` TEXT,
            `sum` VARCHAR(50),
            `sum_mapping` VARCHAR(50),
            `extra_params` JSON,
            INDEX `idx_partner_id` (`partner_id`),
            INDEX `idx_timestamp` (`timestamp`),
            CONSTRAINT `fk_partner_id_detailed` FOREIGN KEY (`partner_id`) REFERENCES `partners` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo '<p class="text-success">Таблица `detailed_stats` успешно создана или уже существует.</p>';
    
    $initial_settings = [
        'telegram_globally_enabled' => 'true',
        'curl_timeout' => '10',
        'curl_connect_timeout' => '5',
        'curl_ssl_verify' => 'true',
        'curl_returntransfer' => 'true',
        'curl_followlocation' => 'true'
    ];
    $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    foreach($initial_settings as $key => $val) {
        $stmt->execute([$key, $val]);
    }
    echo '<p class="text-info">Начальные настройки по умолчанию добавлены/обновлены.</p>';

    echo '<div class="alert alert-warning mt-4"><strong>ВАЖНО:</strong> Теперь удалите этот файл (install.php) с вашего сервера!</div>';

} catch (PDOException $e) {
    echo '<p class="text-danger">Ошибка установки: ' . $e->getMessage() . '</p>';
}

echo '</div></div></div></body></html>';