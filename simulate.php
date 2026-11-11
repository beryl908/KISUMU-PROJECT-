<?php
// simulate.php - pretends to be your network devices and sends a reading every 5 seconds.
// Run it from the command line (not in the browser):
//
//   XAMPP on Windows:   C:\xampp\php\php.exe C:\xampp\htdocs\netmonitor\simulate.php
//   Another URL:        php simulate.php http://localhost/netmonitor
//   Send one round:     php simulate.php http://localhost/netmonitor --once
//
// Keep the window open while you demonstrate the system. Press Ctrl+C to stop.
if (PHP_SAPI !== 'cli') { exit('Run simulate.php from the command line.'); }

require __DIR__ . '/db.php';          // for COLLECT_KEY and the device list

$base = rtrim($argv[1] ?? 'http://localhost/netmonitor', '/');
$once = in_array('--once', $argv, true);
$url  = $base . '/collect.php';

// average load of each device, so the graphs look realistic (device_id => typical %)
$typical = [1 => 82, 2 => 70, 3 => 56, 4 => 42, 5 => 62, 6 => 58];
$capacityMbps = 1000;                 // pretend every link is 1 Gbps

function post($url, array $data) {
    $ctx = stream_context_create(['http' => [
        'method'  => 'POST',
        'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
        'content' => http_build_query($data),
        'timeout' => 4,
        'ignore_errors' => true,
    ]]);
    $res = @file_get_contents($url, false, $ctx);
    return $res === false ? 'no connection' : trim($res);
}

echo "Sending readings to $url (Ctrl+C to stop)\n";
do {
    $devices = $pdo->query('SELECT device_id, device_name FROM devices ORDER BY device_id')->fetchAll();
    foreach ($devices as $d) {
        $base_pct = $typical[$d['device_id']] ?? 50;
        $pct = max(3, min(99, $base_pct + mt_rand(-18, 18)));
        $mbps = $capacityMbps * $pct / 100;                       // total traffic this second
        $inShare = mt_rand(55, 65) / 100;
        $bytesIn  = (int)($mbps * $inShare * 1000000 / 8 * 5);    // 5-second reading
        $bytesOut = (int)($mbps * (1 - $inShare) * 1000000 / 8 * 5);
        $res = post($url, ['key' => COLLECT_KEY, 'device_id' => $d['device_id'],
                           'bytes_in' => $bytesIn, 'bytes_out' => $bytesOut, 'bandwidth_pct' => $pct]);
        echo date('H:i:s'), '  ', str_pad($d['device_name'], 16), str_pad($pct . '%', 6), $res, "\n";
    }
    if (!$once) { sleep(5); }
} while (!$once);
