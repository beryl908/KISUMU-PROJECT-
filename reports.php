<?php
// 3.3.4 Reports backend (administrators and managers)
require __DIR__ . '/auth.php';
$types = ['Traffic summary', 'Alert report'];
$today = date('Y-m-d');
$fromIn = $_GET['from'] ?? date('Y-m-d', strtotime('-29 days'));
$toIn   = $_GET['to']   ?? $today;
$type   = in_array($_GET['type'] ?? '', $types, true) ? $_GET['type'] : $types[0];
$run    = isset($_GET['generate']);
$err    = '';
function valid_date($d) {
    $x = DateTime::createFromFormat('Y-m-d', $d);
    return $x && $x->format('Y-m-d') === $d;
}
$sum = $al = $busy = $peakAt = null; $daily = $top = $alertRows = []; $uptime = null;
if ($run) {
    if (!valid_date($fromIn) || !valid_date($toIn)) {
        $err = 'Please choose a valid From and To date.';
    } elseif ($fromIn > $toIn) {
        $err = 'The From date must not be after the To date.';
    } else {
        // the whole end date is included (00:00:00 up to 23:59:59)
        $from = $fromIn . ' 00:00:00';
        $to   = $toIn . ' 23:59:59';
        $q = $pdo->prepare('SELECT SUM(bytes_in + bytes_out) total, MAX(bandwidth_pct) peak FROM traffic_logs WHERE logged_at BETWEEN ? AND ?');
        $q->execute([$from, $to]);
        $sum = $q->fetch();
        $q = $pdo->prepare('SELECT logged_at FROM traffic_logs WHERE logged_at BETWEEN ? AND ? ORDER BY bandwidth_pct DESC, logged_at LIMIT 1');
        $q->execute([$from, $to]);
        $peakAt = $q->fetchColumn();
        $a = $pdo->prepare('SELECT COUNT(*) raised, SUM(is_resolved) resolved FROM alerts WHERE created_at BETWEEN ? AND ?');
        $a->execute([$from, $to]);
        $al = $a->fetch();
        $q = $pdo->prepare('SELECT SUBSTR(logged_at, 12, 2) hr, SUM(bytes_in + bytes_out) b FROM traffic_logs
                            WHERE logged_at BETWEEN ? AND ? GROUP BY SUBSTR(logged_at, 12, 2) ORDER BY b DESC LIMIT 1');
        $q->execute([$from, $to]);
        $busy = $q->fetch();
        $q = $pdo->prepare('SELECT SUBSTR(logged_at, 1, 10) day, AVG(bandwidth_pct) p FROM traffic_logs
                            WHERE logged_at BETWEEN ? AND ? GROUP BY SUBSTR(logged_at, 1, 10) ORDER BY day');
        $q->execute([$from, $to]);
        foreach ($q->fetchAll() as $r) { $daily[] = ['label' => substr($r['day'], 5), 'value' => round((float)$r['p'], 1)]; }
        $q = $pdo->prepare('SELECT d.device_name, SUM(t.bytes_in + t.bytes_out) b FROM traffic_logs t
                            JOIN devices d ON d.device_id = t.device_id WHERE t.logged_at BETWEEN ? AND ?
                            GROUP BY d.device_id, d.device_name ORDER BY b DESC LIMIT 5');
        $q->execute([$from, $to]);
        $top = $q->fetchAll();
        // Average uptime (estimate): each "Device offline" alert is counted as one offline-timeout of downtime.
        $devCount = (int)$pdo->query('SELECT COUNT(*) FROM devices')->fetchColumn();
        $q = $pdo->prepare("SELECT COUNT(*) FROM alerts WHERE alert_type = 'Device offline' AND created_at BETWEEN ? AND ?");
        $q->execute([$from, $to]);
        $offAlerts = (int)$q->fetchColumn();
        $minutes   = max(1, (strtotime($to) - strtotime($from)) / 60);
        $down      = $offAlerts * (int)setting($pdo, 'offline_timeout', 5);
        $uptime    = $devCount ? max(0, 100 - ($down / ($devCount * $minutes)) * 100) : null;
        if ($type === 'Alert report') {
            $q = $pdo->prepare('SELECT a.created_at, d.device_name, a.alert_type, a.severity, a.is_resolved
                                FROM alerts a JOIN devices d ON d.device_id = a.device_id
                                WHERE a.created_at BETWEEN ? AND ? ORDER BY a.created_at DESC LIMIT 500');
            $q->execute([$from, $to]);
            $alertRows = $q->fetchAll();
        }
        $pdo->prepare('INSERT INTO reports (user_id, report_type, date_from, date_to, generated_at) VALUES (?,?,?,?,?)')
            ->execute([$_SESSION['user_id'], $type, $fromIn, $toIn, date('Y-m-d H:i:s')]);
    }
}

// ---- page -------------------------------------------------------------------------
$threshold = (float)setting($pdo, 'bandwidth_threshold', 80);
$title = 'Reports'; $active = 'reports';
include __DIR__ . '/header.php';
?>
<h1>Reports Centre</h1>
<div class="card noprint">
  <form method="get" action="reports.php" class="row-form">
    <input type="hidden" name="generate" value="1">
    <div class="wide"><label>Report type</label>
      <select name="type"><?php foreach ($types as $t): ?><option <?= $type === $t ? 'selected' : '' ?>><?= h($t) ?></option><?php endforeach; ?></select></div>
    <div class="wide"><label>From</label><input type="date" name="from" value="<?= h($fromIn) ?>"></div>
    <div class="wide"><label>To</label><input type="date" name="to" value="<?= h($toIn) ?>"></div>
    <div class="actions">
      <button class="btn" type="submit">Generate</button>
      <?php if ($run && !$err): ?><button class="btn green" type="button" onclick="window.print()">Export PDF</button><?php endif; ?>
    </div>
  </form>
</div>

<?php if ($err): ?><div class="notice bad"><?= h($err) ?></div><?php endif; ?>

<?php if ($run && !$err): ?>
  <h2 class="report-title"><strong><?= h($type) ?></strong> &mdash; <?= h($fromIn) ?> to <?= h($toIn) ?></h2>
  <div class="two">
    <div class="card">
      <h2>Average daily utilisation</h2>
      <canvas id="chart" class="chart"></canvas>
    </div>
    <div class="card nopad">
      <table>
        <thead><tr><th colspan="2">Summary</th></tr></thead>
        <tbody>
          <tr><td>Total data transferred</td><td><?= h(format_bytes($sum['total'] ?? 0)) ?></td></tr>
          <tr><td>Peak utilisation</td><td><?= $sum && $sum['peak'] !== null ? round($sum['peak']) . '%' . ($peakAt ? ' (' . h(date('M j', strtotime($peakAt))) . ')' : '') : '-' ?></td></tr>
          <tr><td>Busiest hour</td><td><?= $busy ? h($busy['hr']) . ':00 (' . h(format_bytes($busy['b'])) . ')' : '-' ?></td></tr>
          <tr><td>Alerts raised</td><td><?= (int)($al['raised'] ?? 0) ?></td></tr>
          <tr><td>Alerts resolved</td><td><?= (int)($al['resolved'] ?? 0) ?></td></tr>
          <tr><td>Average uptime</td><td><?= $uptime !== null ? number_format($uptime, 1) . '%' : '-' ?></td></tr>
        </tbody>
      </table>
    </div>
  </div>

  <div class="card nopad">
    <table>
      <thead><tr><th>Top devices by data transferred</th><th>Data</th></tr></thead>
      <tbody>
        <?php foreach ($top as $t): ?><tr><td><?= h($t['device_name']) ?></td><td><?= h(format_bytes($t['b'])) ?></td></tr><?php endforeach; ?>
        <?php if (!$top): ?><tr><td colspan="2" class="muted">No traffic recorded for these dates.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>

  <?php if ($type === 'Alert report'): ?>
  <div class="card nopad">
    <table>
      <thead><tr><th>Time</th><th>Device</th><th>Type</th><th>Severity</th><th>Status</th></tr></thead>
      <tbody>
        <?php foreach ($alertRows as $r): ?>
          <tr><td><?= h($r['created_at']) ?></td><td><?= h($r['device_name']) ?></td><td><?= h($r['alert_type']) ?></td>
              <td><span class="pill sev-<?= h(strtolower($r['severity'])) ?>"><?= h($r['severity']) ?></span></td>
              <td><?= $r['is_resolved'] ? 'Resolved' : 'Open' ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$alertRows): ?><tr><td colspan="5" class="muted">No alerts in this period.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

  <script>
    var labels = <?= json_encode(array_column($daily, 'label')) ?>;
    var values = <?= json_encode(array_column($daily, 'value')) ?>;
    function draw(){ lineChart(document.getElementById('chart'), labels, values, <?= json_encode($threshold) ?>); }
    window.addEventListener('load', draw); window.addEventListener('resize', draw);
  </script>
<?php elseif (!$run): ?>
  <p class="muted">Choose a report type and a date range, then press Generate.</p>
<?php endif; ?>
<?php include __DIR__ . '/footer.php'; ?>
