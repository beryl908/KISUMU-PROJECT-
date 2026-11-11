<?php
// devices.php - add, edit and delete network devices (administrators only)
require __DIR__ . '/auth.php';
require_admin();

$types = ['Router', 'Switch', 'Firewall', 'Access Point', 'Server'];
$msg = ''; $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $id = (int)($_POST['device_id'] ?? 0);
        $pdo->beginTransaction();
        $pdo->prepare('DELETE FROM alerts WHERE device_id = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM traffic_logs WHERE device_id = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM devices WHERE device_id = ?')->execute([$id]);
        $pdo->commit();
        $msg = 'Device deleted together with its traffic logs and alerts.';
    } elseif ($action === 'add' || $action === 'edit') {
        $name = trim($_POST['device_name'] ?? '');
        $ip   = trim($_POST['ip_address'] ?? '');
        $type = in_array($_POST['device_type'] ?? '', $types, true) ? $_POST['device_type'] : $types[0];
        $loc  = trim($_POST['location'] ?? '');
        $id   = (int)($_POST['device_id'] ?? 0);

        if ($name === '' || $ip === '') {
            $err = 'Device name and IP address are required.';
        } elseif (!filter_var($ip, FILTER_VALIDATE_IP)) {
            $err = 'Please enter a valid IP address, for example 192.168.1.13.';
        } else {
            // IP addresses must be unique
            $q = $pdo->prepare('SELECT device_id FROM devices WHERE ip_address = ? AND device_id <> ?');
            $q->execute([$ip, $action === 'edit' ? $id : 0]);
            if ($q->fetch()) {
                $err = 'A device with this IP address already exists.';
            } elseif ($action === 'add') {
                $pdo->prepare("INSERT INTO devices (device_name, ip_address, device_type, location, status) VALUES (?,?,?,?, 'offline')")
                    ->execute([$name, $ip, $type, $loc]);
                $msg = 'Device added.';
            } else {
                $pdo->prepare('UPDATE devices SET device_name = ?, ip_address = ?, device_type = ?, location = ? WHERE device_id = ?')
                    ->execute([$name, $ip, $type, $loc, $id]);
                $msg = 'Device updated.';
            }
        }
    }
}

// device being edited (if any)
$edit = null;
if (isset($_GET['edit'])) {
    $q = $pdo->prepare('SELECT * FROM devices WHERE device_id = ?');
    $q->execute([(int)$_GET['edit']]);
    $edit = $q->fetch() ?: null;
}
$devices = $pdo->query('SELECT * FROM devices ORDER BY device_name')->fetchAll();

$title = 'Devices'; $active = 'devices';
include __DIR__ . '/header.php';
?>
<h1>Devices</h1>
<?php if ($msg): ?><div class="notice ok"><?= h($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="notice bad"><?= h($err) ?></div><?php endif; ?>

<div class="card">
  <h2><?= $edit ? 'Edit device' : 'Add new device' ?></h2>
  <form method="post" action="devices.php" class="row-form">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="<?= $edit ? 'edit' : 'add' ?>">
    <?php if ($edit): ?><input type="hidden" name="device_id" value="<?= (int)$edit['device_id'] ?>"><?php endif; ?>
    <div><label>Device name</label><input name="device_name" required value="<?= h($edit['device_name'] ?? '') ?>"></div>
    <div><label>IP address</label><input name="ip_address" required value="<?= h($edit['ip_address'] ?? '') ?>"></div>
    <div><label>Type</label>
      <select name="device_type">
        <?php foreach ($types as $t): ?>
          <option <?= (($edit['device_type'] ?? '') === $t) ? 'selected' : '' ?>><?= h($t) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div><label>Location</label><input name="location" value="<?= h($edit['location'] ?? '') ?>"></div>
    <div class="actions">
      <button class="btn" type="submit"><?= $edit ? 'Save changes' : 'Add device' ?></button>
      <?php if ($edit): ?><a class="btn grey" href="devices.php">Cancel</a><?php endif; ?>
    </div>
  </form>
</div>

<div class="card nopad">
  <table>
    <thead><tr><th>ID</th><th>Name</th><th>IP address</th><th>Type</th><th>Location</th><th>Status</th><th>Last seen</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach ($devices as $d): ?>
      <tr>
        <td><?= (int)$d['device_id'] ?></td>
        <td><?= h($d['device_name']) ?></td>
        <td><?= h($d['ip_address']) ?></td>
        <td><?= h($d['device_type']) ?></td>
        <td><?= h($d['location']) ?></td>
        <td><span class="pill <?= $d['status'] === 'online' ? 'ok' : 'bad' ?>"><?= $d['status'] === 'online' ? 'Online' : 'Offline' ?></span></td>
        <td><?= $d['last_seen'] ? h(substr($d['last_seen'], 11, 5)) : '-' ?></td>
        <td class="nowrap">
          <a class="btn small" href="devices.php?edit=<?= (int)$d['device_id'] ?>">Edit</a>
          <form method="post" action="devices.php" class="inline" onsubmit="return confirm('Delete this device and all its traffic logs and alerts?');">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="device_id" value="<?= (int)$d['device_id'] ?>">
            <button class="btn small red" type="submit">Delete</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$devices): ?><tr><td colspan="8" class="muted">No devices yet. Add one above.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php include __DIR__ . '/footer.php'; ?>
