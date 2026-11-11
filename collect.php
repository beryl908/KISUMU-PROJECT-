<?php
// 3.3.2 Traffic collection and alert detection backend
// Network devices POST: key, device_id, bytes_in, bytes_out, bandwidth_pct   (every 5 seconds)
require __DIR__ . '/db.php';
require __DIR__ . '/functions.php';
header('Content-Type: text/plain');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('POST only'); }
if (!hash_equals(COLLECT_KEY, (string)($_POST['key'] ?? ''))) { http_response_code(403); exit('bad key'); }
$dev = (int)($_POST['device_id'] ?? 0);
$in  = max(0, (int)($_POST['bytes_in'] ?? 0));
$out = max(0, (int)($_POST['bytes_out'] ?? 0));
$pct = (float)($_POST['bandwidth_pct'] ?? 0);
$pct = min(100, max(0, $pct));
$now = date('Y-m-d H:i:s');
// the device must be registered
$chk = $pdo->prepare('SELECT device_id FROM devices WHERE device_id = ?');
$chk->execute([$dev]);
if (!$chk->fetch()) { http_response_code(404); exit('unknown device'); }
// reject duplicate reading for same device and time
$chk = $pdo->prepare('SELECT log_id FROM traffic_logs WHERE device_id = ? AND logged_at = ?');
$chk->execute([$dev, $now]);
if ($chk->fetch()) { exit('duplicate'); }
try {
    $ins = $pdo->prepare('INSERT INTO traffic_logs (device_id, bytes_in, bytes_out, bandwidth_pct, logged_at) VALUES (?,?,?,?,?)');
    $ins->execute([$dev, $in, $out, $pct, $now]);
} catch (PDOException $e) {
    exit('duplicate');   // the UNIQUE key (device_id, logged_at) caught a race between two requests
}
$logId = $pdo->lastInsertId();
// the device is clearly alive
$pdo->prepare("UPDATE devices SET status = 'online', last_seen = ? WHERE device_id = ?")->execute([$now, $dev]);
// compare with the threshold
$threshold = (float)setting($pdo, 'bandwidth_threshold', 80);
if ($pct >= $threshold) {
    $sev = ($pct >= 90) ? 'High' : 'Medium';
    $a = $pdo->prepare("INSERT INTO alerts (device_id, log_id, alert_type, severity, created_at) VALUES (?, ?, 'Traffic spike', ?, ?)");
    $a->execute([$dev, $logId, $sev, $now]);
}
// separate check: flag any device that has stopped reporting
check_offline($pdo);
echo 'ok';
