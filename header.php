<?php
// header.php - top of every page. Set $title and $active before including it.
$title  = $title  ?? 'NetMonitor TMS';
$active = $active ?? '';
$isAdmin = ($_SESSION['role'] ?? '') === 'admin';
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($title) ?> - NetMonitor TMS</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<nav class="topbar">
  <a class="brand" href="dashboard.php">NetMonitor TMS</a>
  <div class="menu">
    <a href="dashboard.php" class="<?= $active === 'dashboard' ? 'on' : '' ?>">Dashboard</a>
    <?php if ($isAdmin): ?>
      <a href="live.php"    class="<?= $active === 'live' ? 'on' : '' ?>">Live Traffic</a>
      <a href="devices.php" class="<?= $active === 'devices' ? 'on' : '' ?>">Devices</a>
      <a href="alerts.php"  class="<?= $active === 'alerts' ? 'on' : '' ?>">Alerts</a>
    <?php endif; ?>
    <a href="reports.php" class="<?= $active === 'reports' ? 'on' : '' ?>">Reports</a>
  </div>
  <?php if ($isAdmin): ?>
  <form class="search" action="search.php" method="get">
    <input type="text" name="q" placeholder="Search device or IP" value="<?= h($_GET['q'] ?? '') ?>">
    <button type="submit" title="Search">&#128269;</button>
  </form>
  <?php endif; ?>
  <div class="user"><?= h($_SESSION['username'] ?? '') ?> <span class="bar">|</span> <a href="logout.php">Log out</a></div>
</nav>
<main class="page">
