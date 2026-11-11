<?php
// dashboard.php
require __DIR__ . '/auth.php';          // checks the session, loads db.php and functions.php
check_offline($pdo);
$data = dashboard_data($pdo);           // device counts, alerts, today's bytes, recent alerts, hourly chart
$title = 'Dashboard'; $active = 'dashboard';
include __DIR__ . '/header.php';
?>
<h1>Dashboard</h1>

<div class="cards">
  <div class="card stat"><span>Total bandwidth now</span><strong id="bw"><?= h($data['bandwidth']) ?></strong></div>
  <div class="card stat"><span>Devices online</span><strong class="green" id="online"><?= (int)$data['online'] ?> / <?= (int)$data['total'] ?></strong></div>
  <div class="card stat"><span>Active alerts</span><strong class="red" id="alerts"><?= (int)$data['alerts'] ?></strong></div>
  <div class="card stat"><span>Data transferred today</span><strong id="today"><?= h($data['today']) ?></strong></div>
</div>

<div class="two">
  <div class="card">
    <h2>Bandwidth usage &ndash; last 24 hours</h2>
    <canvas id="chart" class="chart"></canvas>
  </div>
  <div class="card nopad">
    <table>
      <thead><tr><th>Recent alerts</th><th>Severity</th></tr></thead>
      <tbody id="recent">
      <?php foreach ($data['recent'] as $a): ?>
        <tr><td><?= h($a['alert_type']) ?> &ndash; <?= h($a['device_name']) ?></td>
            <td><span class="pill sev-<?= h(strtolower($a['severity'])) ?>"><?= h($a['severity']) ?></span></td></tr>
      <?php endforeach; ?>
      <?php if (!$data['recent']): ?><tr><td colspan="2" class="muted">No alerts yet.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
var initial = <?= json_encode($data) ?>;
function esc(s){ var d=document.createElement('div'); d.textContent=s; return d.innerHTML; }
function render(d){
  document.getElementById('bw').textContent = d.bandwidth;
  document.getElementById('online').textContent = d.online + ' / ' + d.total;
  document.getElementById('alerts').textContent = d.alerts;
  document.getElementById('today').textContent = d.today;
  var rows = d.recent.map(function(a){
    return '<tr><td>' + esc(a.alert_type) + ' – ' + esc(a.device_name) + '</td><td><span class="pill sev-' +
           a.severity.toLowerCase() + '">' + esc(a.severity) + '</span></td></tr>';
  }).join('');
  document.getElementById('recent').innerHTML = rows || '<tr><td colspan="2" class="muted">No alerts yet.</td></tr>';
  lineChart(document.getElementById('chart'), d.hourly.map(function(p){return p.label;}),
            d.hourly.map(function(p){return p.value;}), d.threshold);
}
function refresh(){
  fetch('api.php?what=dashboard', {credentials:'same-origin'}).then(function(r){return r.json();}).then(render).catch(function(){});
}
window.addEventListener('load', function(){ render(initial); setInterval(refresh, 5000); });
window.addEventListener('resize', function(){ render(initial); });
</script>
<?php include __DIR__ . '/footer.php'; ?>
