<?php
// live.php - live traffic monitor (refreshes every 5 seconds with JavaScript)
require __DIR__ . '/auth.php';
require_admin();
$title = 'Live Traffic'; $active = 'live';
include __DIR__ . '/header.php';
?>
<h1>Live Traffic Monitor</h1>
<div class="card">
  <h2>Average network utilisation (auto-refresh every 5 s)</h2>
  <canvas id="chart" class="chart"></canvas>
</div>
<div class="card nopad">
  <table>
    <thead><tr><th>Time</th><th>Device</th><th>Inbound (Mbps)</th><th>Outbound (Mbps)</th><th>Utilisation</th><th>Status</th></tr></thead>
    <tbody id="rows"><tr><td colspan="6" class="muted">Loading...</td></tr></tbody>
  </table>
</div>
<script>
var labels = [], values = [], threshold = 80, MAXPTS = 15;
function esc(s){ var d=document.createElement('div'); d.textContent=s; return d.innerHTML; }
function pillClass(s){ return s==='Normal' ? 'ok' : (s==='High' ? 'warn' : 'bad'); }
function draw(){ lineChart(document.getElementById('chart'), labels, values, threshold); }
function refresh(){
  fetch('api.php?what=live', {credentials:'same-origin'}).then(function(r){return r.json();}).then(function(d){
    threshold = d.threshold;
    labels.push(d.time); values.push(d.avg);
    if (labels.length > MAXPTS) { labels.shift(); values.shift(); }
    document.getElementById('rows').innerHTML = d.rows.map(function(r){
      return '<tr><td>'+esc(r.time)+'</td><td>'+esc(r.device)+'</td><td>'+r.in+'</td><td>'+r.out+'</td><td>'+r.pct+'%</td>' +
             '<td><span class="pill '+pillClass(r.status)+'">'+esc(r.status)+'</span></td></tr>';
    }).join('');
    draw();
  }).catch(function(){});
}
window.addEventListener('load', function(){ refresh(); setInterval(refresh, 5000); });
window.addEventListener('resize', draw);
</script>
<?php include __DIR__ . '/footer.php'; ?>
