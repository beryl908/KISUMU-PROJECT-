<?php
require _DIR_ . '/config.php';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'login') {
    try {
        $stmt = db()->prepare("SELECT user_id, username, password_hash, role FROM users WHERE username = ?");
        $stmt->execute([trim($_POST['username'] ?? '')]);
        $user = $stmt->fetch();
        if ($user && password_verify($_POST['password'] ?? '', $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            header('Location: index.php?page=dashboard');
            exit;
        }
        $error = 'Invalid username or password.';
    } catch (Throwable $ex) {
        $error = 'Database not ready. Start MySQL and run setup.php first.';
    }
}
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}
if (empty($_SESSION['user_id'])):
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Network Monitor | Login</title><link rel="stylesheet" href="assets/style.css"></head>
<body class="login-body">
<div class="login-wrap">
  <div class="login-brand"><div class="brand-mark">NM</div><p class="eyebrow">NET MONITOR LTD</p><h1>Network Traffic<br>Monitoring System</h1><p class="muted-light">Monitor network performance. Detect issues early. Improve service delivery.</p></div>
  <form class="login-card" method="post">
    <span class="eyebrow">WELCOME BACK</span><h2>Sign in to your account</h2><p class="muted">Enter your details to access the monitoring dashboard.</p>
    <?php if ($error): ?><div class="alert error"><?=e($error)?></div><?php endif; ?>
    <input type="hidden" name="action" value="login">
    <label>Username</label><input name="username" required autocomplete="username" placeholder="Enter username">
    <label>Password</label><input type="password" name="password" required autocomplete="current-password" placeholder="Enter password">
    <button class="btn primary full" type="submit">Login to dashboard</button>
    <p class="login-hint">Demonstration account: <b>admin</b> / <b>Admin@123</b></p>
  </form>
</div></body></html>
<?php
exit;
endif;
$pdo = db();
$page = $_GET['page'] ?? 'dashboard';
$role = $_SESSION['role'] ?? 'Manager';
$success = '';
function setting_value(PDO $pdo, string $key, string $default): string {
    $s = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key=?");
    $s->execute([$key]);
    return (string)($s->fetchColumn() ?: $default);
}
function can_admin(): bool { return ($_SESSION['role'] ?? '') === 'Administrator'; }
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] !== 'login') {
    $action = $_POST['action'];
    try {
        if ($action === 'add_device' || $action === 'update_device') {
            require_admin();
            $name = trim($_POST['device_name'] ?? '');
            $ip = trim($_POST['ip_address'] ?? '');
            $type = trim($_POST['device_type'] ?? '');
            $location = trim($_POST['location'] ?? '');
            if (!$name || !filter_var($ip, FILTER_VALIDATE_IP) || !$type || !$location) {
                throw new RuntimeException('Enter all device details and a valid IP address.');
            }
            if ($action === 'add_device') {
                $s = $pdo->prepare("INSERT INTO devices(device_name,ip_address,device_type,location) VALUES(?,?,?,?)");
                $s->execute([$name,$ip,$type,$location]);
                $success = 'Device added successfully.';
            } else {
                $s = $pdo->prepare("UPDATE devices SET device_name=?,ip_address=?,device_type=?,location=? WHERE device_id=?");
                $s->execute([$name,$ip,$type,$location,(int)$_POST['device_id']]);
                $success = 'Device updated successfully.';
            }
            $page = 'devices';
        } elseif ($action === 'delete_device') {
            require_admin();
            $pdo->prepare("DELETE FROM devices WHERE device_id=?")->execute([(int)$_POST['device_id']]);
            $success = 'Device deleted.';
            $page = 'devices';
        } elseif ($action === 'resolve_alert') {
            $pdo->prepare("UPDATE alerts SET is_resolved=1,resolved_by=? WHERE alert_id=?")
                ->execute([$_SESSION['user_id'], (int)$_POST['alert_id']]);
            $success = 'Alert marked as resolved.';
            $page = 'alerts';
        } elseif ($action === 'save_settings') {
            require_admin();
            $threshold = max(1, min(100, (int)($_POST['bandwidth_threshold'] ?? 80)));
            $timeout = max(1, min(120, (int)($_POST['offline_timeout'] ?? 5)));
            $s = $pdo->prepare("INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)");
            $s->execute(['bandwidth_threshold', (string)$threshold]);
            $s->execute(['offline_timeout', (string)$timeout]);
            $success = 'Monitoring settings saved.';
            $page = 'settings';
        } elseif ($action === 'generate_demo') {
            require_admin();
            $devices = $pdo->query("SELECT device_id FROM devices")->fetchAll();
            foreach ($devices as $d) {
                $pct = random_int(25,96);
                $now = date('Y-m-d H:i:s');
                $bytesIn = random_int(100000,9000000);
                $bytesOut = random_int(100000,6000000);
                $ins = $pdo->prepare("INSERT INTO traffic_logs(device_id,bytes_in,bytes_out,bandwidth_pct,logged_at) VALUES(?,?,?,?,?)");
                $ins->execute([$d['device_id'],$bytesIn,$bytesOut,$pct,$now]);
                $pdo->prepare("UPDATE devices SET status='online',last_seen=? WHERE device_id=?")->execute([$now,$d['device_id']]);
                if ($pct >= (int)setting_value($pdo,'bandwidth_threshold','80')) {
                    $pdo->prepare("INSERT INTO alerts(device_id,log_id,alert_type,severity,created_at) VALUES(?,?,?,?,?)")
                        ->execute([$d['device_id'], $pdo->lastInsertId(), 'Traffic spike', $pct>=90?'High':'Medium', $now]);
                }
            }
            $success = 'Sample traffic readings collected. Review the dashboard and alerts.';
            $page = 'dashboard';
        } elseif ($action === 'generate_report') {
            $from = $_POST['date_from'] ?? date('Y-m-01');
            $to = $_POST['date_to'] ?? date('Y-m-d');
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/',$from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/',$from) || $from > $to) {
                throw new RuntimeException('Choose a valid date range.');
            }
            $s = $pdo->prepare("INSERT INTO reports(user_id,report_type,date_from,date_to,generated_at) VALUES(?,?,?,?,NOW())");
            $s->execute([$_SESSION['user_id'],'Traffic summary',$from,$to]);
            $_SESSION['report_from'] = $from; $_SESSION['report_to'] = $to;
            $success = 'Report generated for the selected date range.';
            $page = 'reports';
        }
    } catch (Throwable $ex) {
        $error = $ex->getMessage();
    }
}
$threshold = (int)setting_value($pdo,'bandwidth_threshold','80');
$online = (int)$pdo->query("SELECT COUNT(*) FROM devices WHERE status='online'")->fetchColumn();
$totalDevices = (int)$pdo->query("SELECT COUNT(*) FROM devices")->fetchColumn();
$openAlerts = (int)$pdo->query("SELECT COUNT(*) FROM alerts WHERE is_resolved=0")->fetchColumn();
$todayBytes = (float)$pdo->query("SELECT COALESCE(SUM(bytes_in+bytes_out),0) FROM traffic_logs WHERE DATE(logged_at)=CURDATE()")->fetchColumn();
$devices = $pdo->query("SELECT * FROM devices ORDER BY device_id DESC")->fetchAll();
$alerts = $pdo->query("SELECT a.*,d.device_name FROM alerts a JOIN devices d ON d.device_id=a.device_id ORDER BY a.created_at DESC LIMIT 100")->fetchAll();
$recent = $pdo->query("SELECT a.*,d.device_name FROM alerts a JOIN devices d ON d.device_id=a.device_id ORDER BY a.created_at DESC LIMIT 5")->fetchAll();
$traffic = $pdo->query("SELECT t.*,d.device_name FROM traffic_logs t JOIN devices d ON d.device_id=t.device_id ORDER BY t.logged_at DESC LIMIT 20")->fetchAll();
$reportFrom = $_SESSION['report_from'] ?? date('Y-m-01');
$reportTo = $_SESSION['report_to'] ?? date('Y-m-d');
$reportStats = null;
$dailyRows = [];
if ($page === 'reports') {
    $s=$pdo->prepare("SELECT COALESCE(SUM(bytes_in+bytes_out),0) total_bytes,COALESCE(MAX(bandwidth_pct),0) peak,COUNT(*) readings FROM traffic_logs WHERE logged_at BETWEEN ? AND ?");
    $s->execute([$reportFrom.' 00:00:00',$reportTo.' 23:59:59']);
    $reportStats=$s->fetch();
    $s=$pdo->prepare("SELECT DATE(logged_at) day,AVG(bandwidth_pct) avg_pct FROM traffic_logs WHERE logged_at BETWEEN ? AND ? GROUP BY DATE(logged_at) ORDER BY day");
    $s->execute([$reportFrom.' 00:00:00',$reportTo.' 23:59:59']);
    $dailyRows=$s->fetchAll();
}
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Network Monitor | <?=e(ucfirst($page))?></title><link rel="stylesheet" href="assets/style.css"><script defer src="assets/app.js"></script></head>
<body>
<div class="app-shell">
<aside class="sidebar">
  <div class="side-brand"><div class="brand-mark small">NM</div><div><b>NetMonitor</b><small>TRAFFIC ANALYSIS</small></div></div>
  <div class="nav-label">WORKSPACE</div>
  <a class="nav-link <?= $page==='dashboard'?'active':'' ?>" href="index.php?page=dashboard">▦ <span>Dashboard</span></a>
  <a class="nav-link <?= $page==='traffic'?'active':'' ?>" href="index.php?page=traffic">⌁ <span>Live Traffic</span></a>
  <a class="nav-link <?= $page==='devices'?'active':'' ?>" href="index.php?page=devices">▣ <span>Devices</span></a>
  <a class="nav-link <?= $page==='alerts'?'active':'' ?>" href="index.php?page=alerts">♢ <span>Alerts</span><?php if($openAlerts): ?><i class="nav-count"><?= $openAlerts ?></i><?php endif; ?></a>
  <a class="nav-link <?= $page==='reports'?'active':'' ?>" href="index.php?page=reports">▤ <span>Reports</span></a>
  <?php if(can_admin()): ?><div class="nav-label">ADMINISTRATION</div><a class="nav-link <?= $page==='settings'?'active':'' ?>" href="index.php?page=settings">⚙️ <span>Settings</span></a><?php endif; ?>
  <div class="side-bottom"><div class="avatar"><?=e(strtoupper(substr($_SESSION['username'],0,1)))?></div><div class="user-mini"><b><?=e($_SESSION['username'])?></b><small><?=e($role)?></small></div><a class="logout" href="index.php?logout=1" title="Logout">↗️</a></div>
</aside>
<main class="main-area">
<header class="topbar"><div><span class="eyebrow">NET MONITOR LTD / KISUMU</span><h1><?=e(ucfirst($page==='traffic'?'Live Traffic':$page))?></h1></div><div class="top-user"><span class="status-dot"></span> System active <span class="top-divider"></span><?=e(date('D, d M Y'))?></div></header>
<div class="content">
<?php if (!empty($success)): ?><div class="alert success"><?=e($success)?></div><?php endif; ?>
<?php if (!empty($error)): ?><div class="alert error"><?=e($error)?></div><?php endif; ?>
<?php if ($page==='dashboard'): ?>
  <div class="welcome-row"><div><h2>Network overview</h2><p class="muted">Here is the current status of your network infrastructure.</p></div>
  <?php if(can_admin()): ?><form method="post"><input type="hidden" name="action" value="generate_demo"><button class="btn primary" type="submit">＋ Collect sample traffic</button></form><?php endif; ?></div>
  <section class="stat-grid">
    <div class="stat-card"><div class="stat-top"><span>Devices online</span><span class="stat-icon green">▣</span></div><strong><?=$online?> <small>/ <?=$totalDevices?></small></strong><p class="stat-foot">Registered network devices</p></div>
    <div class="stat-card"><div class="stat-top"><span>Open alerts</span><span class="stat-icon orange">♢</span></div><strong><?=$openAlerts?></strong><p class="stat-foot">Require administrator attention</p></div>
    <div class="stat-card"><div class="stat-top"><span>Data transferred today</span><span class="stat-icon blue">⇅</span></div><strong><?=number_format($todayBytes/1048576,2)?> <small>MB</small></strong><p class="stat-foot">Inbound and outbound traffic</p></div>
    <div class="stat-card"><div class="stat-top"><span>Alert threshold</span><span class="stat-icon purple">⌁</span></div><strong><?=$threshold?> <small>%</small></strong><p class="stat-foot">Bandwidth utilization limit</p></div>
  </section>
  <div class="two-col">
    <section class="panel"><div class="panel-head"><div><h3>Device status</h3><p class="muted">Registered devices and latest status</p></div><a href="index.php?page=devices" class="text-link">View all →</a></div>
      <div class="table-wrap"><table><thead><tr><th>DEVICE</th><th>IP ADDRESS</th><th>LOCATION</th><th>STATUS</th></tr></thead><tbody>
      <?php foreach(array_slice($devices,0,6) as $d): ?><tr><td><b><?=e($d['device_name'])?></b><small class="cell-sub"><?=e($d['device_type'])?></small></td><td><?=e($d['ip_address'])?></td><td><?=e($d['location'])?></td><td><span class="pill <?=$d['status']==='online'?'pill-green':'pill-gray'?>"><?=e(ucfirst($d['status']))?></span></td></tr><?php endforeach; ?>
      <?php if(!$devices): ?><tr><td colspan="4" class="empty">No devices registered yet.</td></tr><?php endif; ?></tbody></table></div>
    </section>
    <section class="panel"><div class="panel-head"><div><h3>Recent alerts</h3><p class="muted">Latest detected network events</p></div><a href="index.php?page=alerts" class="text-link">View all →</a></div>
      <?php foreach($recent as $a): ?><div class="alert-row"><span class="alert-symbol">!</span><div class="alert-info"><b><?=e($a['alert_type'])?></b><small><?=e($a['device_name'])?> · <?=e($a['created_at'])?></small></div><span class="pill <?=$a['is_resolved']?'pill-green':'pill-orange'?>"><?=$a['is_resolved']?'Resolved':e($a['severity'])?></span></div><?php endforeach; ?>
      <?php if(!$recent): ?><p class="empty">No alerts recorded. Your network looks quiet.</p><?php endif; ?>
    </section>
  </div>
  <section class="panel"><div class="panel-head"><div><h3>Latest traffic readings</h3><p class="muted">Most recent collected device readings</p></div><a href="index.php?page=traffic" class="text-link">All readings →</a></div>
    <div class="table-wrap"><table><thead><tr><th>DEVICE</th><th>INBOUND</th><th>OUTBOUND</th><th>UTILIZATION</th><th>TIME</th></tr></thead><tbody>
    <?php foreach(array_slice($traffic,0,6) as $t): ?><tr><td><b><?=e($t['device_name'])?></b></td><td><?=number_format($t['bytes_in']/1048576,2)?> MB</td><td><?=number_format($t['bytes_out']/1048576,2)?> MB</td><td><div class="progress"><span style="width:<?=min(100,(float)$t['bandwidth_pct'])?>%"></span></div><small><?=e($t['bandwidth_pct'])?>%</small></td><td><?=e($t['logged_at'])?></td></tr><?php endforeach; ?>
    <?php if(!$traffic): ?><tr><td colspan="5" class="empty">No traffic readings yet. Use “Collect sample traffic” to demonstrate the system.</td></tr><?php endif; ?></tbody></table></div>
  </section>
<?php elseif ($page==='devices'): ?>
  <div class="welcome-row"><div><h2>Network devices</h2><p class="muted">Register and manage routers, switches and access points.</p></div></div>
  <?php if(can_admin()): ?><section class="panel"><h3>Add a network device</h3><form method="post" class="form-grid"><input type="hidden" name="action" value="add_device"><div><label>Device name</label><input name="device_name" required placeholder="e.g. Main Router"></div><div><label>IP address</label><input name="ip_address" required placeholder="192.168.1.1"></div><div><label>Device type</label><select name="device_type"><option>Router</option><option>Switch</option><option>Access Point</option><option>Server</option><option>Other</option></select></div><div><label>Location</label><input name="location" required placeholder="e.g. Server Room"></div><div class="form-action"><button class="btn primary">Add device</button></div></form></section><?php endif; ?>
  <section class="panel"><div class="panel-head"><h3>Registered devices (<?=$totalDevices?>)</h3></div><div class="table-wrap"><table><thead><tr><th>DEVICE</th><th>IP ADDRESS</th><th>TYPE</th><th>LOCATION</th><th>STATUS</th><?php if(can_admin()): ?><th>ACTION</th><?php endif; ?></tr></thead><tbody>
  <?php foreach($devices as $d): ?><tr><td><b><?=e($d['device_name'])?></b></td><td><?=e($d['ip_address'])?></td><td><?=e($d['device_type'])?></td><td><?=e($d['location'])?></td><td><span class="pill <?=$d['status']==='online'?'pill-green':'pill-gray'?>"><?=e(ucfirst($d['status']))?></span></td><?php if(can_admin()): ?><td><form method="post" onsubmit="return confirm('Delete this device and its readings?')"><input type="hidden" name="action" value="delete_device"><input type="hidden" name="device_id" value="<?=$d['device_id']?>"><button class="btn danger small-btn">Delete</button></form></td><?php endif; ?></tr><?php endforeach; ?>
  <?php if(!$devices): ?><tr><td colspan="6" class="empty">No devices registered.</td></tr><?php endif; ?></tbody></table></div></section>
<?php elseif ($page==='traffic'): ?>
  <div class="welcome-row"><div><h2>Traffic monitor</h2><p class="muted">Review inbound and outbound traffic readings received from devices.</p></div><?php if(can_admin()): ?><form method="post"><input type="hidden" name="action" value="generate_demo"><button class="btn primary">＋ Collect sample traffic</button></form><?php endif; ?></div>
  <section class="panel"><div class="panel-head"><h3>Latest readings</h3><span class="pill pill-blue">Alert threshold: <?=$threshold?>%</span></div><div class="table-wrap"><table><thead><tr><th>DEVICE</th><th>INBOUND</th><th>OUTBOUND</th><th>UTILIZATION</th><th>RECORDED AT</th></tr></thead><tbody>
  <?php foreach($traffic as $t): ?><tr><td><b><?=e($t['device_name'])?></b></td><td><?=number_format($t['bytes_in']/1048576,2)?> MB</td><td><?=number_format($t['bytes_out']/1048576,2)?> MB</td><td><div class="progress"><span style="width:<?=min(100,(float)$t['bandwidth_pct'])?>%"></span></div><?=e($t['bandwidth_pct'])?>%</td><td><?=e($t['logged_at'])?></td></tr><?php endforeach; ?>
  <?php if(!$traffic): ?><tr><td colspan="5" class="empty">No readings available. Collect sample traffic to test the monitor.</td></tr><?php endif; ?></tbody></table></div></section>
  <p class="note">For a live network connection, configure your network devices or a trusted monitoring agent to send readings to <code>collect.php</code>. The sample button is provided for project demonstration and testing.</p>
<?php elseif ($page==='alerts'): ?>
  <div class="welcome-row"><div><h2>Alerts log</h2><p class="muted">Monitor detected traffic spikes and device events.</p></div><span class="pill pill-orange"><?=$openAlerts?> unresolved</span></div>
  <section class="panel"><div class="table-wrap"><table><thead><tr><th>EVENT</th><th>DEVICE</th><th>SEVERITY</th><th>DATE / TIME</th><th>STATUS</th><th>ACTION</th></tr></thead><tbody>
  <?php foreach($alerts as $a): ?><tr><td><b><?=e($a['alert_type'])?></b></td><td><?=e($a['device_name'])?></td><td><span class="pill <?=$a['severity']==='High'?'pill-red':'pill-orange'?>"><?=e($a['severity'])?></span></td><td><?=e($a['created_at'])?></td><td><?=$a['is_resolved']?'<span class="pill pill-green">Resolved</span>':'<span class="pill pill-orange">Open</span>'?></td><td><?php if(!$a['is_resolved']): ?><form method="post"><input type="hidden" name="action" value="resolve_alert"><input type="hidden" name="alert_id" value="<?=$a['alert_id']?>"><button class="btn secondary small-btn">Resolve</button></form><?php else: ?>—<?php endif; ?></td></tr><?php endforeach; ?>
  <?php if(!$alerts): ?><tr><td colspan="6" class="empty">No alerts have been raised.</td></tr><?php endif; ?></tbody></table></div></section>
<?php elseif ($page==='reports'): ?>
  <div class="welcome-row"><div><h2>Reports centre</h2><p class="muted">Generate a summary of network traffic for a selected period.</p></div></div>
  <section class="panel"><form method="post" class="report-form"><input type="hidden" name="action" value="generate_report"><div><label>From date</label><input type="date" name="date_from" value="<?=e($reportFrom)?>" required></div><div><label>To date</label><input type="date" name="date_to" value="<?=e($reportTo)?>" required></div><button class="btn primary">Generate report</button></form></section>
  <?php if($reportStats): ?><section class="stat-grid three"><div class="stat-card"><span class="muted">Total data transferred</span><strong><?=number_format((float)$reportStats['total_bytes']/1048576,2)?> <small>MB</small></strong></div><div class="stat-card"><span class="muted">Peak utilization</span><strong><?=e($reportStats['peak'])?> <small>%</small></strong></div><div class="stat-card"><span class="muted">Traffic readings</span><strong><?=e($reportStats['readings'])?></strong></div></section>
  <section class="panel"><div class="panel-head"><h3>Daily utilization summary</h3><button class="btn secondary" onclick="window.print()">Print / Save PDF</button></div><p class="muted">Period: <?=e($reportFrom)?> to <?=e($reportTo)?></p><div class="table-wrap"><table><thead><tr><th>DATE</th><th>AVERAGE BANDWIDTH UTILIZATION</th></tr></thead><tbody><?php foreach($dailyRows as $r): ?><tr><td><?=e($r['day'])?></td><td><?=e(round((float)$r['avg_pct'],1))?>%</td></tr><?php endforeach; ?><?php if(!$dailyRows): ?><tr><td colspan="2" class="empty">No traffic data found in this date range.</td></tr><?php endif; ?></tbody></table></div></section><?php endif; ?>
<?php elseif ($page==='settings' && can_admin()): ?>
  <section class="panel"><h2>Monitoring settings</h2><p class="muted">Set the bandwidth level that raises an alert and the offline timeout.</p><form method="post" class="form-grid"><input type="hidden" name="action" value="save_settings"><div><label>Bandwidth alert threshold (%)</label><input type="number" name="bandwidth_threshold" min="10" max="100" value="<?= $threshold ?>" required></div>
  <div><label>Offline device timeout (minutes)</label><input type="number" name="offline_timeout" min="1" max="120" value="<?= setting_value($pdo,'offline_timeout','5') ?>" required></div>
  <div class="form-action"><button class="btn primary">Save settings</button></div></form></section>
<?php endif; ?>
</div><!-- /content -->
</main><!-- /main-area -->
</div><!-- /app-shell -->
</body></html>
?>