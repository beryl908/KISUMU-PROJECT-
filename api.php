<?php
// api.php - JSON endpoint used by the dashboard and live monitor to refresh every 5 seconds
require __DIR__ . '/auth.php';
header('Content-Type: application/json');
header('Cache-Control: no-store');

check_offline($pdo);
$what = $_GET['what'] ?? 'dashboard';

if ($what === 'live') {
    $threshold = (float)setting($pdo, 'bandwidth_threshold', 80);
    $cut  = date('Y-m-d H:i:s', time() - 60);
    $rows = []; $sum = 0; $n = 0;
    foreach (latest_readings($pdo) as $r) {
        $fresh = $r['logged_at'] && $r['logged_at'] >= $cut;
        $pct   = $fresh ? (float)$r['bandwidth_pct'] : 0;
        if ($fresh) { $sum += $pct; $n++; }
        $rows[] = [
            'time'   => $r['logged_at'] ? substr($r['logged_at'], 11, 8) : '-',
            'device' => $r['device_name'],
            'in'     => $fresh ? round(to_mbps($r['bytes_in'])) : 0,
            'out'    => $fresh ? round(to_mbps($r['bytes_out'])) : 0,
            'pct'    => round($pct),
            'status' => status_label($pct, $threshold, $fresh ? $r['status'] : 'offline'),
        ];
    }
    echo json_encode(['time' => date('H:i:s'), 'avg' => $n ? round($sum / $n, 1) : 0,
                      'threshold' => $threshold, 'rows' => $rows]);
    exit;
}

echo json_encode(dashboard_data($pdo));
