<?php
declare(strict_types=1);
session_start();
const DB_HOST = 'localhost';
const DB_NAME = 'network_traffic_monitor';
const DB_USER = 'root';
const DB_PASS = ''; // Leave blank for XAMPP default root MySQL
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
    }
    return $pdo;
}
function e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
function require_login(): void {
    if (empty($_SESSION['user_id'])) {
        header('Location: index.php');
        exit;
    }
}
function require_admin(): void {
    require_login();
    if (($_SESSION['role'] ?? '') !== 'Administrator') {
        http_response_code(403);
        exit('Access denied. Administrator permission is required.');
    }
}
?>