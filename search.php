<?php
// search.php - filter traffic logs by device name, IP address or date range (administrators only)
require __DIR__ . '/auth.php';
require_admin();

$q    = trim($_GET['q'] ?? '');
$from = trim($_GET['from'] ?? '');
$to   = trim($_GET['to'] ?? '');

function ok_date($d) { $x = DateTime::createFromFormat('Y-m-d', $d); return $x && $x->format('Y-m-d') === $d; }

$sql  = "SELECT t.logged_at, d.device_name, d.ip_address, t.bytes_in, t.bytes_out, t.bandwidth_pct
         FROM traffic_logs t JOIN devices d ON d.device_id = t.device_id WHERE 1=1";
$args = [];
if ($q !== '') {
    $sql .= ' AND (d.device_name LIKE ? OR d.ip_address LIKE ?)';
    $args[] = '%' . $q . '%'; $args[] = '%' . $q . '%';
}
if ($from !== '' && ok_date($from)) { $sql .= ' AND t.logged_at >= ?'; $args[] = $from . ' 00:00:00'; }
if ($to !== ''   && ok_date($to))   { $sql .= ' AND t.logged_at <= ?'; $args[] = $to . ' 23:59:59'; }
$sql .= ' ORDER BY t.logged_at DESC LIMIT 200';
$st = $pdo->prepare($sql);
$st->execute($args);
$rows = $st->fetchAll();

$title = 'Search'; $active = '';
include __DIR__ . '/header.php';
?>
<h1>Search traffic logs</h1>
<div class="card">
  <form method="get" action="search.php" class="row-form">
    <div class="wide"><label>Device name or IP</label><input name="q" value="<?= h($q) ?>"></div>
    <div><label>From</label><input type="date" name="from" value="<?= h($from) ?>"></div>
    <div><label>To</label><input type="date" name="to" value="<?= h($to) ?>"></div>
    <div class="actions"><button class="btn" type="submit">Search</button></div>
  </form>
</div>
<div class="card nopad">
  <table>
    <thead><tr><th>Time</th><th>Device</th><th>IP address</th><th>Inbound (Mbps)</th><th>Outbound (Mbps)</th><th>Utilisation</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr><td><?= h($r['logged_at']) ?></td><td><?= h($r['device_name']) ?></td><td><?= h($r['ip_address']) ?></td>
          <td><?= round(to_mbps($r['bytes_in'])) ?></td><td><?= round(to_mbps($r['bytes_out'])) ?></td>
          <td><?= round($r['bandwidth_pct']) ?>%</td></tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="6" class="muted">No matching records. Showing at most the latest 200 results.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php include __DIR__ . '/footer.php'; ?>
