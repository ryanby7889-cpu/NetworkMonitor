-- ======================================================
-- DATABASE : NETWORK MONITOR
-- Author   : Ryan & ChatGPT
-- Version  : 1.0
-- ======================================================

DROP DATABASE IF EXISTS network_monitor;
CREATE DATABASE network_monitor
CHARACTER SET utf8mb4
COLLATE utf8mb4_general_ci;

USE network_monitor;

-- ======================================================
-- TABLE : USERS
-- ======================================================

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fullname VARCHAR(100) NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('Administrator','Operator') DEFAULT 'Administrator',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO users(fullname,username,password)
VALUES
('Administrator','admin','admin123');


-- ======================================================
-- TABLE : ROUTER
-- ======================================================

CREATE TABLE router (
    id INT AUTO_INCREMENT PRIMARY KEY,
    router_name VARCHAR(100) NOT NULL,
    ip_address VARCHAR(50) NOT NULL,
    username VARCHAR(50) NOT NULL,
    password VARCHAR(100) NOT NULL,
    api_port INT DEFAULT 8728,
    status ENUM('ONLINE','OFFLINE') DEFAULT 'OFFLINE',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO router
(router_name,ip_address,username,password)
VALUES
(
'MikroTik Utama',
'192.168.88.1',
'monitor',
'monitor123'
);

-- ======================================================
-- TABLE : INTERFACE
-- ======================================================

CREATE TABLE interfaces (

    id INT AUTO_INCREMENT PRIMARY KEY,

    router_id INT NOT NULL,

    interface_name VARCHAR(50),

    interface_type VARCHAR(30),

    enabled BOOLEAN DEFAULT TRUE,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY(router_id)
    REFERENCES router(id)
    ON DELETE CASCADE

);

INSERT INTO interfaces
(router_id,interface_name,interface_type)

VALUES

(1,'ether1','WAN');

-- ======================================================
-- TABLE : TRAFFIC LOG
-- ======================================================

CREATE TABLE traffic_log (

    id BIGINT AUTO_INCREMENT PRIMARY KEY,

    router_id INT,

    interface_id INT,

    rx_bps BIGINT,

    tx_bps BIGINT,

    rx_mbps DECIMAL(10,2),

    tx_mbps DECIMAL(10,2),

    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    INDEX(created_at),

    FOREIGN KEY(router_id)
    REFERENCES router(id),

    FOREIGN KEY(interface_id)
    REFERENCES interfaces(id)

);

-- ======================================================
-- TABLE : SETTINGS
-- ======================================================

CREATE TABLE settings (

    id INT AUTO_INCREMENT PRIMARY KEY,

    setting_name VARCHAR(100),

    setting_value VARCHAR(255)

);

INSERT INTO settings(setting_name,setting_value)
VALUES

('company_name','Network Monitor'),

('refresh_interval','1000'),

('timezone','Asia/Jakarta');

-- ======================================================
-- TABLE : SYSTEM LOG
-- ======================================================

CREATE TABLE system_log (

    id BIGINT AUTO_INCREMENT PRIMARY KEY,

    activity VARCHAR(255),

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP

);

INSERT INTO system_log(activity)
VALUES

('Database berhasil dibuat.');
