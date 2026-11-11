<?php
// functions.php - shared helpers (also starts the session)
if (session_status() === PHP_SESSION_NONE) { session_start(); }

function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// ---- form protection ------------------------------------------------------
function csrf_token() {
    if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
    return $_SESSION['csrf'];
}
function csrf_field() { return '<input type="hidden" name="csrf" value="' . h(csrf_token()) . '">'; }
function csrf_check() {
    if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        exit('Invalid form token. Go back, refresh the page and try again.');
    }
}

// ---- settings table ---------------------------------------------------------
function setting(PDO $pdo, $key, $default = null) {
    $st = $pdo->prepare('SELECT setting_value FROM settings WHERE setting_key = ?');
    $st->execute([$key]);
    $v = $st->fetchColumn();
    return $v === false ? $default : $v;
}
function save_setting(PDO $pdo, $key, $value) {
    $st = $pdo->prepare('SELECT COUNT(*) FROM settings WHERE setting_key = ?');
    $st->execute([$key]);
    if ($st->fetchColumn()) {
        $pdo->prepare('UPDATE settings SET setting_value = ? WHERE setting_key = ?')->execute([$value, $key]);
    } else {
        $pdo->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)')->execute([$key, $value]);
    }
}

// ---- number formatting -------------------------------------------------------
// A reading covers 5 seconds, so bytes -> megabits per second
function to_mbps($bytes) { return ($bytes * 8) / 1000000 / 5; }

function format_bytes($b) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
    $b = (float)$b; $i = 0;
    while ($b >= 1024 && $i < count($units) - 1) { $b /= 1024; $i++; }
    return round($b, $i ? 1 : 0) . ' ' . $units[$i];
}

// ---- readings ------------------------------------------------------------------
// newest reading of every device (devices with no reading still appear)
function latest_readings(PDO $pdo) {
    return $pdo->query(
        "SELECT d.device_id, d.device_name, d.status, t.bytes_in, t.bytes_out, t.bandwidth_pct, t.logged_at
         FROM devices d
         LEFT JOIN traffic_logs t ON t.log_id =
            (SELECT t2.log_id FROM traffic_logs t2 WHERE t2.device_id = d.device_id
             ORDER BY t2.logged_at DESC, t2.log_id DESC LIMIT 1)
         ORDER BY d.device_name"
    )->fetchAll();
}

// label shown in the live traffic table
function status_label($pct, $threshold, $status) {
    if ($status === 'offline') { return 'Offline'; }
    if ($pct >= $threshold)    { return 'Congested'; }
    if ($pct >= $threshold - 10) { return 'High'; }
    return 'Normal';
}

// functions.php - the separate offline check called above
// Marks devices offline (and raises one alert each) when no reading arrived within the offline timeout.
function check_offline(PDO $pdo) {
    $timeout = max(1, (int)setting($pdo, 'offline_timeout', 5));
    $limit   = date('Y-m-d H:i:s', time() - $timeout * 60);
    $st = $pdo->prepare("SELECT device_id FROM devices WHERE status = 'online' AND last_seen < ?");
    $st->execute([$limit]);
    foreach ($st->fetchAll() as $d) {
        $pdo->prepare("UPDATE devices SET status = 'offline' WHERE device_id = ?")->execute([$d['device_id']]);
        $pdo->prepare("INSERT INTO alerts (device_id, log_id, alert_type, severity, created_at)
                       VALUES (?, NULL, 'Device offline', 'Medium', ?)")
            ->execute([$d['device_id'], date('Y-m-d H:i:s')]);
    }
}

// functions.php
function dashboard_data(PDO $pdo) {
    $threshold = (float)setting($pdo, 'bandwidth_threshold', 80);
    $online = (int)$pdo->query("SELECT COUNT(*) FROM devices WHERE status = 'online'")->fetchColumn();
    $total  = (int)$pdo->query("SELECT COUNT(*) FROM devices")->fetchColumn();
    $alerts = (int)$pdo->query("SELECT COUNT(*) FROM alerts WHERE is_resolved = 0")->fetchColumn();
    $st = $pdo->prepare("SELECT SUM(bytes_in + bytes_out) FROM traffic_logs WHERE logged_at >= ?");
    $st->execute([date('Y-m-d') . ' 00:00:00']);
    $today = (float)$st->fetchColumn();
    // bandwidth "now" = sum of each device's newest reading from the last minute
    $mbps = 0;
    $cut  = date('Y-m-d H:i:s', time() - 60);
    foreach (latest_readings($pdo) as $r) {
        if ($r['logged_at'] && $r['logged_at'] >= $cut) { $mbps += to_mbps($r['bytes_in'] + $r['bytes_out']); }
    }
    $recent = $pdo->query("SELECT a.alert_type, a.severity, a.is_resolved, d.device_name
                           FROM alerts a JOIN devices d ON d.device_id = a.device_id
                           ORDER BY a.created_at DESC, a.alert_id DESC LIMIT 5")->fetchAll();
    $st = $pdo->prepare("SELECT SUBSTR(logged_at, 1, 13) h, AVG(bandwidth_pct) p
                         FROM traffic_logs WHERE logged_at >= ? GROUP BY SUBSTR(logged_at, 1, 13) ORDER BY h");
    $st->execute([date('Y-m-d H:i:s', time() - 86400)]);
    $hourly = [];
    foreach ($st->fetchAll() as $r) {
        $hourly[] = ['label' => substr($r['h'], 11, 2) . ':00', 'value' => round((float)$r['p'], 1)];
    }
    return [
        'bandwidth'  => round($mbps, 1) . ' Mbps',
        'online'     => $online,
        'total'      => $total,
        'alerts'     => $alerts,
        'today'      => format_bytes($today),
        'recent'     => $recent,
        'hourly'     => $hourly,
        'threshold'  => $threshold,
    ];
}
