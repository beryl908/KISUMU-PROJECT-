<?php
// auth.php - include at the top of every page that needs a logged-in user
require __DIR__ . '/db.php';
require __DIR__ . '/functions.php';

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// pages that only administrators may use call require_admin()
function require_admin() {
    if (($_SESSION['role'] ?? '') !== 'admin') {
        http_response_code(403);
        exit('Only administrators can open this page.');
    }
}
