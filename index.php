<?php
// index.php - the first page. Sends visitors to the dashboard if logged in, otherwise to the login page.
require __DIR__ . '/functions.php';   // starts the session
header('Location: ' . (isset($_SESSION['user_id']) ? 'dashboard.php' : 'login.php'));
exit;
