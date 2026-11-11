<?php
declare(strict_types=1);
require _DIR_ . '/config.php';

// Simple API authentication (update this key!)
$apiKey = 'your-secret-key-123'; 
$receivedKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
if ($receivedKey !== $apiKey) {
    http_response_code(403);
    exit('Invalid API key');
}

try {
    $deviceId = (int)($_POST['device_id'] ?? 0);
    $bytesIn = (int)($_POST['bytes_in'] ?? 0);
    $bytesOut = (int)($_POST['bytes_out'] ?? 0);
    $bandwidthPct = (float)($_POST['bandwidth_pct'] ?? 0);
    $loggedAt = $_POST['logged_at'] ?? date('Y-m-d H:i:s');

    if ($deviceId <=0 || $bytesIn <0 || $bytesOut <0 || $bandwidthPct <0 || $bandwidthPct >100) {
        throw new RuntimeException('Invalid data values');
    }

    $pdo = db();

    // Insert traffic reading
    $stmt = $pdo->prepare("INSERT INTO traffic_logs (device_id, bytes_in, bytes_out, bandwidth_pct, logged_at) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$deviceId, $bytesIn, $bytesOut, $bandwidthPct, $loggedAt]);
    $logId = $pdo->lastInsertId();

    // Update device status to online
    $pdo->prepare("UPDATE devices SET status='online', last_seen=? WHERE device_id=?", )->execute([$loggedAt, $deviceId]);

    // Check for bandwidth alert
    $threshold = (int)setting_value($pdo, 'bandwidth_threshold', '80');
    if ($bandwidthPct >= $threshold) {
        // Avoid duplicate alerts for same reading
        $check = $pdo->prepare("SELECT alert_id FROM alerts WHERE device_id=? AND log_id=? AND is_resolved=0");
        $check->execute([$deviceId, $logId]);
        if (!$check->fetch()) {
            $severity = $bandwidthPct >=90 ? 'High' : 'Medium';
            $pdo->prepare("INSERT INTO alerts (device_id, log_id, alert_type, severity, created_at) VALUES (?, ?, ?, ?, ?)")
                ->execute([$deviceId, $logId, 'High bandwidth utilization', $severity, $loggedAt]);
        }
    }

    http_response_code(200);
    echo 'OK: Reading saved';
} catch (Throwable $ex) {
    http_response_code(500);
    echo 'Error: ' . $ex->getMessage();
}
?>