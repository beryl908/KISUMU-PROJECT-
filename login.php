<?php
// 3.3.1 Login backend
require __DIR__ . '/db.php';
require __DIR__ . '/functions.php';   // starts the session
if (isset($_SESSION['user_id'])) { header('Location: dashboard.php'); exit; }
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $pdo->prepare('SELECT user_id, username, password_hash, role FROM users WHERE username = ?');
    $stmt->execute([trim($_POST['username'] ?? '')]);
    $user = $stmt->fetch();
    if ($user && password_verify($_POST['password'] ?? '', $user['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user_id']  = $user['user_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role']     = $user['role'];
        header('Location: dashboard.php');
        exit;
    }
    $error = 'Invalid username or password.';
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Log in - NetMonitor TMS</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body class="login-body">
  <form class="login-box" method="post" action="login.php" autocomplete="on">
    <h1>NetMonitor TMS</h1>
    <p class="sub">Network Traffic Monitoring System<br>Net Monitor Ltd, Kisumu</p>
    <?php if ($error): ?><div class="error"><?= h($error) ?></div><?php endif; ?>
    <label for="username">Username</label>
    <input id="username" name="username" type="text" required autofocus value="<?= h($_POST['username'] ?? '') ?>">
    <label for="password">Password</label>
    <input id="password" name="password" type="password" required>
    <button type="submit" class="btn full">Log in</button>
  </form>
</body>
</html>
