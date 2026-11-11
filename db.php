<?php
// db.php - database connection details. Change these to match your server.
const DB_HOST = 'localhost';
const DB_NAME = 'netmonitor';
const DB_USER = 'root';      // XAMPP default
const DB_PASS = '';          // XAMPP default is empty

// Secret key the network devices must send with every reading (see collect.php).
// Change it, and use the same value in simulate.php or on the devices.
const COLLECT_KEY = 'change-this-secret-key';

date_default_timezone_set('Africa/Nairobi');

$dsn = getenv('NM_DSN') ?: 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    exit('Could not connect to the database. Check the details in db.php.');
}
