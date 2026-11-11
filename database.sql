-- NetMonitor TMS - database schema and starter data
-- Import this file in phpMyAdmin (Import tab) or run it in the MySQL console.

CREATE DATABASE IF NOT EXISTS netmonitor CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE netmonitor;

DROP TABLE IF EXISTS reports;
DROP TABLE IF EXISTS alerts;
DROP TABLE IF EXISTS traffic_logs;
DROP TABLE IF EXISTS devices;
DROP TABLE IF EXISTS settings;
DROP TABLE IF EXISTS users;

CREATE TABLE users (
  user_id       INT AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(50)  NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  full_name     VARCHAR(100) NULL,
  role          ENUM('admin','manager') NOT NULL DEFAULT 'admin',
  created_at    DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE devices (
  device_id   INT AUTO_INCREMENT PRIMARY KEY,
  device_name VARCHAR(100) NOT NULL,
  ip_address  VARCHAR(45)  NOT NULL UNIQUE,
  device_type VARCHAR(30)  NOT NULL,
  location    VARCHAR(100) NULL,
  status      ENUM('online','offline') NOT NULL DEFAULT 'offline',
  last_seen   DATETIME NULL
) ENGINE=InnoDB;

CREATE TABLE traffic_logs (
  log_id        BIGINT AUTO_INCREMENT PRIMARY KEY,
  device_id     INT NOT NULL,
  bytes_in      BIGINT NOT NULL DEFAULT 0,
  bytes_out     BIGINT NOT NULL DEFAULT 0,
  bandwidth_pct DECIMAL(5,2) NOT NULL DEFAULT 0,
  logged_at     DATETIME NOT NULL,
  UNIQUE KEY uq_device_time (device_id, logged_at),   -- stops the same reading being saved twice
  KEY idx_logged_at (logged_at),
  CONSTRAINT fk_log_device FOREIGN KEY (device_id) REFERENCES devices(device_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE alerts (
  alert_id    INT AUTO_INCREMENT PRIMARY KEY,
  device_id   INT NOT NULL,
  log_id      BIGINT NULL,
  alert_type  VARCHAR(40) NOT NULL,
  severity    ENUM('Low','Medium','High') NOT NULL DEFAULT 'Medium',
  is_resolved TINYINT(1) NOT NULL DEFAULT 0,
  resolved_by INT NULL,
  resolved_at DATETIME NULL,
  created_at  DATETIME NOT NULL,
  KEY idx_created (created_at),
  CONSTRAINT fk_alert_device FOREIGN KEY (device_id) REFERENCES devices(device_id) ON DELETE CASCADE,
  CONSTRAINT fk_alert_log    FOREIGN KEY (log_id) REFERENCES traffic_logs(log_id) ON DELETE SET NULL,
  CONSTRAINT fk_alert_user   FOREIGN KEY (resolved_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE reports (
  report_id    INT AUTO_INCREMENT PRIMARY KEY,
  user_id      INT NOT NULL,
  report_type  VARCHAR(40) NOT NULL,
  date_from    DATE NOT NULL,
  date_to      DATE NOT NULL,
  generated_at DATETIME NOT NULL,
  CONSTRAINT fk_report_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE settings (
  setting_key   VARCHAR(50) PRIMARY KEY,
  setting_value VARCHAR(100) NOT NULL
) ENGINE=InnoDB;

-- Starter data ------------------------------------------------------------
-- Logins:  admin / admin123   and   manager / manager123   (change these after first login)
INSERT INTO users (username, password_hash, full_name, role) VALUES
 ('admin',   '$2y$10$Q.s7qkQpj9Xb1aZxRIDw6ucoYleaFpBChk3wj6AJ4gx2ykHoKJSGK', 'Network Administrator', 'admin'),
 ('manager', '$2y$10$9BOUeAT3cFMyS9qt6w4zVumkA290uyW4tJivXyM9pDZvDfc8.3rEG', 'Operations Manager',   'manager');

INSERT INTO devices (device_id, device_name, ip_address, device_type, location, status) VALUES
 (1, 'Core Switch 1', '192.168.1.1',  'Switch',       'Server room',  'offline'),
 (2, 'Router-Main',   '192.168.1.2',  'Router',       'Server room',  'offline'),
 (3, 'Firewall-01',   '192.168.1.3',  'Firewall',     'Server room',  'offline'),
 (4, 'AP-Floor1',     '192.168.1.11', 'Access Point', 'First floor',  'offline'),
 (5, 'AP-Floor2',     '192.168.1.12', 'Access Point', 'Second floor', 'offline'),
 (6, 'Server-Rack',   '192.168.1.20', 'Server',       'Server room',  'offline');

INSERT INTO settings (setting_key, setting_value) VALUES
 ('bandwidth_threshold', '80'),
 ('offline_timeout',     '5');
