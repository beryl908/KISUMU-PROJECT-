<?php
$host = 'localhost';
$user = 'root';
$pass = '';
try {
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS network_traffic_monitor CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE network_traffic_monitor");
    $sql = [
        "CREATE TABLE IF NOT EXISTS users (
            user_id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(60) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            role ENUM('Administrator','Manager') NOT NULL DEFAULT 'Administrator',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",
        "CREATE TABLE IF NOT EXISTS devices (
            device_id INT AUTO_INCREMENT PRIMARY KEY,
            device_name VARCHAR(100) NOT NULL,
            ip_address VARCHAR(45) NOT NULL UNIQUE,
            device_type VARCHAR(60) NOT NULL,
            location VARCHAR(120) NOT NULL,
            status ENUM('online','offline') NOT NULL DEFAULT 'offline',
            last_seen DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",
        "CREATE TABLE IF NOT EXISTS traffic_logs (
            log_id BIGINT AUTO_INCREMENT PRIMARY KEY,
            device_id INT NOT NULL,
            bytes_in BIGINT NOT NULL DEFAULT 0,
            bytes_out BIGINT NOT NULL DEFAULT 0,
            bandwidth_pct DECIMAL(5,2) NOT NULL DEFAULT 0,
            logged_at DATETIME NOT NULL,
            UNIQUE KEY unique_device_reading (device_id, logged_at),
            FOREIGN KEY (device_id) REFERENCES devices(device_id) ON DELETE CASCADE
        )",
        "CREATE TABLE IF NOT EXISTS alerts (
            alert_id INT AUTO_INCREMENT PRIMARY KEY,
            device_id INT NOT NULL,
            log_id BIGINT NULL,
            alert_type VARCHAR(80) NOT NULL,
            severity ENUM('Low','Medium','High') NOT NULL DEFAULT 'Medium',
            is_resolved TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            resolved_by INT NULL,
            FOREIGN KEY (device_id) REFERENCES devices(device_id) ON DELETE CASCADE,
            FOREIGN KEY (resolved_by) REFERENCES users(user_id) ON DELETE SET NULL
        )",
        "CREATE TABLE IF NOT EXISTS settings (
            setting_key VARCHAR(80) PRIMARY KEY,
            setting_value VARCHAR(120) NOT NULL
        )",
        "CREATE TABLE IF NOT EXISTS reports (
            report_id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            report_type VARCHAR(80) NOT NULL,
            date_from DATE NOT NULL,
            date_to DATE NOT NULL,
            generated_at DATETIME NOT NULL,
            FOREIGN KEY (user_id) REFERENCES users(user_id)
        )"
    ];
    foreach ($sql as $statement) $pdo->exec($statement);
    
    $check = $pdo->prepare("SELECT user_id FROM users WHERE username = ?");
    $check->execute(['admin']);
    if (!$check->fetch()) {
        $insert = $pdo->prepare("INSERT INTO users (username,password_hash,role) VALUES (?,?,?)");
        $insert->execute(['admin', password_hash('Admin@123', PASSWORD_DEFAULT), 'Administrator']);
    }
    
    $pdo->exec("INSERT IGNORE INTO settings (setting_key,setting_value) VALUES ('bandwidth_threshold','80'),('offline_timeout','5')");
    $count = (int)$pdo->query("SELECT COUNT(*) FROM devices")->fetchColumn();
    if ($count === 0) {
        $pdo->exec("INSERT INTO devices (device_name,ip_address,device_type,location,status,last_seen) VALUES
            ('Main Router','192.168.1.1','Router','Server Room','online',NOW()),
            ('Office Switch','192.168.1.2','Switch','Office','online',NOW()),
            ('Access Point A','192.168.1.10','Access Point','Reception','offline',NULL)");
    }
    
    echo '<!doctype html><html><head><meta charset="utf-8"><title>Setup complete</title><link rel="stylesheet" href="assets/style.css"></head><body><main class="setup-card"><h1>System setup complete</h1><p>The Network Traffic Monitoring System database and sample devices are ready.</p><p><b>Username:</b> admin<br><b>Password:</b> Admin@123</p><p>Open <a href="index.php">the login page</a> to start. For security, delete or rename setup.php after setup and change the demonstration password.</p></main></body></html>';
} catch (Throwable $ex) {
    http_response_code(500);
    echo '<h2>Setup failed</h2><p>Check that MySQL is running and the username/password in setup.php are correct.</p><pre>' . htmlspecialchars($ex->getMessage()) . '</pre>';
}
?>