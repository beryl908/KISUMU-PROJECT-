<?php
// alerts.php - thresholds and the alert log (administrators only)
require __DIR__ . '/auth.php';
require_admin();
check_offline($pdo);
$msg = ''; $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    if ($action === 'thresholds') {
        $bw = $_POST['bandwidth_threshold'] ?? '';
        $to = $_POST['offline_timeout'] ?? '';
        if (!is_numeric($bw) || $bw < 1 || $bw > 100) {
            $err = 'The bandwidth threshold must be a number from 1 to 100.';
        } elseif (!ctype_digit((string)$to) || $to < 1 || $to > 1440) {
            $err = 'The offline timeout must be a whole number of minutes from 1 to 1440.';
        } else {
            save_setting($pdo, 'bandwidth_threshold', (string)(float)$bw);
            save_setting($pdo, 'offline_timeout', (string)(int)$to);
            $msg = 'Thresholds saved.';
        }
    } elseif ($action === 'resolve') {
        $pdo->prepare('UPDATE alerts SET is_resolved = 1, resolved_by = ?, resolved_at = ? WHERE alert_id = ?')
            ->execute([$_SESSION['user_id'], date('Y-m-d H:i:s'), (int)($_POST['alert_id'] ?? 0)]);
        $msg = 'Alert marked as resolved.';
    }
}

$bwSetting = setting($pdo, 'bandwidth_threshold', 80);
$toSetting = setting($pdo, 'offline_timeout', 5);

$sev = $_GET['severity'] ?? '';
$sql = "SELECT a.alert_id, a.created_at, a.alert_type, a.severity, d.device_name, t.bandwidth_pct
        FROM alerts a
        JOIN devices d ON d.device_id = a.device_id
        LEFT JOIN traffic_logs t ON t.log_id = a.log_id
        WHERE a.is_resolved = 0";
$args = [];
if (in_array($sev, ['Low', 'Medium', 'High'], true)) { $sql .= ' AND a.severity = ?'; $args[] = $sev; }
$sql .= ' ORDER BY a.created_at DESC, a.alert_id DESC LIMIT 200';
$q = $pdo->prepare($sql);
$q->execute($args);
$rows = $q->fetchAll();

$title = 'Alerts'; $active = 'alerts';
include __DIR__ . '/header.php';
?>
<h1>Alerts &amp; Thresholds</h1>
<?php if ($msg): ?><div class="notice ok"><?= h($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="notice bad"><?= h($err) ?></div><?php endif; ?>

<div class="card">
  <form method="post" action="alerts.php" class="row-form">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="thresholds">
    <div class="wide"><label>Bandwidth utilisation threshold (%)</label>
      <input name="bandwidth_threshold" required value="<?= h($bwSetting) ?>"></div>
    <div class="wide"><label>Offline timeout (minutes)</label>
      <input name="offline_timeout" required value="<?= h($toSetting) ?>"></div>
    <div class="actions"><button class="btn" type="submit">Save thresholds</button></div>
  </form>
</div>

<form method="get" action="alerts.php" class="filter">
  <label>Show severity:</label>
  <select name="severity" onchange="this.form.submit()">
    <option value="">All</option>
    <?php foreach (['High', 'Medium', 'Low'] as $s): ?>
      <option <?= $sev === $s ? 'selected' : '' ?>><?= $s ?></option>
    <?php endforeach; ?>
  </select>
</form>

<div class="card nopad">
  <table>
    <thead><tr><th>Time</th><th>Device</th><th>Type</th><th>Severity</th><th>Action</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= h(substr($r['created_at'], 11, 5)) ?> <span class="muted"><?= h(substr($r['created_at'], 5, 5)) ?></span></td>
        <td><?= h($r['device_name']) ?></td>
        <td><?= h($r['alert_type']) ?><?= $r['bandwidth_pct'] !== null ? ' (' . round($r['bandwidth_pct']) . '%)' : '' ?></td>
        <td><span class="pill sev-<?= h(strtolower($r['severity'])) ?>"><?= h($r['severity']) ?></span></td>
        <td>
          <form method="post" action="alerts.php" class="inline">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="resolve">
            <input type="hidden" name="alert_id" value="<?= (int)$r['alert_id'] ?>">
            <button class="btn small" type="submit">Resolve</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="5" class="muted">No open alerts. Everything is running normally.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php include __DIR__ . '/footer.php'; ?>
