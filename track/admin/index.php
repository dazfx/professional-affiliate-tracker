<?php
require_once 'db.php';

// Global settings
$global_settings_raw = $pdo->query("SELECT setting_key, setting_value FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);
$global_settings = array_map(function($v) {
    if ($v === 'true') return true;
    if ($v === 'false') return false;
    return json_decode($v, true) ?? $v;
}, $global_settings_raw);

// Partners
$partners = $pdo->query("SELECT id, name FROM partners ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Summary statistics
$summary_stats = $pdo->query("SELECT * FROM summary_stats")->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_UNIQUE);
$global_stats = ['total_requests' => 0, 'successful_redirects' => 0, 'errors' => 0];
foreach ($summary_stats as $p_stat) {
    $global_stats['total_requests'] += $p_stat['total_requests'];
    $global_stats['successful_redirects'] += $p_stat['successful_redirects'];
    $global_stats['errors'] += $p_stat['errors'];
}

$today_start = date('Y-m-d 00:00:00');
$month_start = date('Y-m-01 00:00:00');

// Profit calculations
$stmt_profit = $pdo->prepare("
    SELECT
        (SUM(CASE WHEN `timestamp` >= :today_start1 THEN CAST(COALESCE(sum, 0) AS DECIMAL(10,2)) ELSE 0 END) - SUM(CASE WHEN `timestamp` >= :today_start2 THEN CAST(COALESCE(sum_mapping, 0) AS DECIMAL(10,2)) ELSE 0 END)) as today_profit,
        (SUM(CASE WHEN `timestamp` >= :month_start1 THEN CAST(COALESCE(sum, 0) AS DECIMAL(10,2)) ELSE 0 END) - SUM(CASE WHEN `timestamp` >= :month_start2 THEN CAST(COALESCE(sum_mapping, 0) AS DECIMAL(10,2)) ELSE 0 END)) as month_profit
    FROM detailed_stats
");
$stmt_profit->execute([
    'today_start1' => $today_start, 
    'today_start2' => $today_start,
    'month_start1' => $month_start,
    'month_start2' => $month_start
]);
$profit_stats = $stmt_profit->fetch(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Панель управления</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link href="https://cdn.datatables.net/2.0.8/css/dataTables.bootstrap5.min.css" rel="stylesheet" />
    <link href="https://cdn.datatables.net/colreorder/2.0.3/css/colReorder.bootstrap5.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
    <nav class="main-header navbar navbar-expand navbar-dark">
        <ul class="navbar-nav"><li class="nav-item"><a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a></li></ul>
        <ul class="navbar-nav ml-auto">
            <li class="nav-item">
                <a class="nav-link" href="#" id="theme-toggle" role="button" 
                   data-bs-toggle="tooltip" 
                   data-bs-placement="bottom" 
                   title="Переключить тему">
                    <i class="fas fa-moon"></i>
                </a>
            </li>
        </ul>
    </nav>
    <aside class="main-sidebar sidebar-dark-primary elevation-4">
        <a href="#" class="brand-link main-nav-link" data-bs-target="#dashboard-pane" data-bs-toggle="tab"><i class="fa-solid fa-shuffle brand-image" style="opacity: .8"></i><span class="brand-text font-weight-light">Панель</span></a>
        <div class="sidebar">
            <nav class="mt-2">
                <ul class="nav nav-pills nav-sidebar flex-column" data-bs-toggle="tab" role="tablist">
                    <li class="nav-item"><a href="#" class="nav-link active main-nav-link" data-bs-target="#dashboard-pane" data-bs-toggle="tab" role="tab"><i class="nav-icon fas fa-tachometer-alt"></i><p>Дашборд</p></a></li>
                    <li class="nav-header">ПАРТНЕРЫ</li>
                    <?php foreach ($partners as $partner): ?>
                        <li class="nav-item"><a href="#" class="nav-link main-nav-link" data-bs-target="#partner-pane-<?= htmlspecialchars($partner['id']) ?>" data-bs-toggle="tab" role="tab"><i class="nav-icon fas fa-user-tie"></i><p><?= htmlspecialchars($partner['name']) ?></p></a></li>
                    <?php endforeach; ?>
                </ul>
            </nav>
        </div>
    </aside>

    <div class="content-wrapper">
        <section class="content pt-3">
            <div class="container-fluid">
            <div class="tab-content">
                <div class="tab-pane fade show active" id="dashboard-pane" role="tabpanel">
                    <div class="row">
                        <div class="col-lg-3 col-6"><div class="small-box bg-primary"><div class="inner"><h3><?= $global_stats['total_requests'] ?? 0 ?></h3><p>Всего запросов</p></div><div class="icon"><i class="fas fa-chart-bar"></i></div></div></div>
                        <div class="col-lg-3 col-6"><div class="small-box bg-success"><div class="inner"><h3><?= $global_stats['successful_redirects'] ?? 0 ?></h3><p>Успешных редиректов</p></div><div class="icon"><i class="fas fa-check"></i></div></div></div>
                        <div class="col-lg-3 col-6"><div class="small-box bg-info"><div class="inner"><h3><?= number_format((float)($profit_stats['today_profit'] ?? 0), 2) ?></h3><p>Профит за сегодня</p></div><div class="icon"><i class="fas fa-wallet"></i></div></div></div>
                        <div class="col-lg-3 col-6"><div class="small-box bg-secondary"><div class="inner"><h3><?= number_format((float)($profit_stats['month_profit'] ?? 0), 2) ?></h3><p>Профит за месяц</p></div><div class="icon"><i class="fas fa-coins"></i></div></div></div>
                    </div>
                    <div class="row">
                        <div class="col-lg-6">
                            <div class="card card-primary card-outline h-100"><div class="card-header"><h3 class="card-title"><i class="fas fa-globe"></i> Глобальные настройки</h3></div><div class="card-body">
                                <form id="globalSettingsForm">
                                    <h5 class="mb-3">Telegram</h5>
                                    <div class="mb-3"><label for="global_telegram_bot_token" class="form-label">Bot Token</label><input type="text" class="form-control" name="telegram_bot_token" id="global_telegram_bot_token" value="<?= htmlspecialchars($global_settings['telegram_bot_token'] ?? '') ?>"></div>
                                    <div class="mb-3"><label for="global_telegram_channel_id" class="form-label">Channel ID</label><input type="text" class="form-control" name="telegram_channel_id" id="global_telegram_channel_id" value="<?= htmlspecialchars($global_settings['telegram_channel_id'] ?? '') ?>"></div>
                                    <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" role="switch" name="telegram_globally_enabled" id="telegramGloballyEnabled" <?= ($global_settings['telegram_globally_enabled'] ?? false) ? 'checked' : '' ?>><label class="form-check-label" for="telegramGloballyEnabled"><b>Включить отправку в Telegram глобально</b></label></div>
                                    <hr>
                                    <h5 class="mb-3">Настройки cURL</h5>
                                    <div class="row">
                                        <div class="col-md-6 mb-3"><label for="curlTimeout" class="form-label">Общий таймаут (сек)</label><input type="number" class="form-control" name="curl_timeout" id="curlTimeout" value="<?= htmlspecialchars($global_settings['curl_timeout'] ?? 10) ?>" min="1"></div>
                                        <div class="col-md-6 mb-3"><label for="curlConnectTimeout" class="form-label">Таймаут соединения (сек)</label><input type="number" class="form-control" name="curl_connect_timeout" id="curlConnectTimeout" value="<?= htmlspecialchars($global_settings['curl_connect_timeout'] ?? 5) ?>" min="1"></div>
                                    </div>
                                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>Сохранить</button>
                                </form>
                            </div></div>
                        </div>
                        <div class="col-lg-6">
                            <div class="card card-primary card-outline h-100"><div class="card-header"><h3 class="card-title"><i class="fas fa-users"></i> Партнеры</h3><div class="card-tools"><button class="btn btn-success btn-sm" id="addPartnerBtn"><i class="fas fa-plus"></i> Добавить</button></div></div><div class="card-body table-responsive p-0">
                                <table class="table table-hover text-nowrap">
                                    <thead><tr><th>Партнер</th><th>Ссылка для постбека</th><th class="text-center">Действия</th></tr></thead>
                                    <tbody id="partnersTableBody">
                                    <?php foreach ($partners as $partner): ?>
                                        <tr data-id="<?= htmlspecialchars($partner['id']) ?>">
                                            <td class="align-middle">
                                                <strong><?= htmlspecialchars($partner['name']) ?></strong>
                                                <br>
                                                <small class="text-muted d-block">ID: <code><?= htmlspecialchars($partner['id']) ?></code></small>
                                            </td>
                                            <td><?php $postbackUrl = "https://{$_SERVER['HTTP_HOST']}/track/postback.php?pid=" . urlencode($partner['id']); ?><div class="input-group input-group-sm"><input type="text" class="form-control" value="<?= $postbackUrl ?>&clickid=..." readonly><button class="btn btn-outline-secondary copy-btn" type="button" data-bs-toggle="tooltip" title="Копировать"><i class="fas fa-clipboard"></i></button></div></td>
                                            <td class="text-center align-middle"><div class="btn-group"><button class="btn btn-primary btn-sm edit-btn" data-bs-toggle="tooltip" title="Редактировать"><i class="fas fa-pencil-alt"></i></button><button class="btn btn-warning btn-sm clear-stats-btn" data-bs-toggle="tooltip" title="Очистить статистику"><i class="fas fa-eraser"></i></button><button class="btn btn-danger btn-sm delete-btn" data-bs-toggle="tooltip" title="Удалить"><i class="fas fa-trash"></i></button></div></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div></div>
                        </div>
                    </div>
                </div>
            </div>
            </div>
        </section>
    </div>
</div>

<!-- Partner Modal placeholder -->
<div class="modal fade" id="partnerModal" tabindex="-1" aria-hidden="true"></div>

<!-- Toast notifications placeholder -->
<div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1080"></div>

<!-- SCRIPTS -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
<script src="https://cdn.datatables.net/2.0.8/js/dataTables.min.js"></script>
<script src="https://cdn.datatables.net/2.0.8/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/colreorder/2.0.3/js/dataTables.colReorder.min.js"></script>
<script src="https://cdn.datatables.net/colreorder/2.0.3/js/colReorder.bootstrap5.min.js"></script>
<script src="assets/js/app.js"></script>
</body>
</html>