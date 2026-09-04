-- =========================================================
-- NetMonitor - Billing PPPoE
-- Jalankan file ini pada database NetworkMonitor yang
-- digunakan oleh config/database.php
-- =========================================================

CREATE TABLE IF NOT EXISTS billing_customers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(30) DEFAULT '',
    address TEXT DEFAULT NULL,
    pppoe_username VARCHAR(64) NOT NULL UNIQUE,
    package_name VARCHAR(100) NOT NULL DEFAULT '10 Mbps',
    monthly_price DECIMAL(12,2) NOT NULL DEFAULT 0,
    billing_day TINYINT UNSIGNED NOT NULL DEFAULT 1,
    status ENUM('active','suspended') NOT NULL DEFAULT 'active',
    notes TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_customer_status (status),
    INDEX idx_billing_day (billing_day)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS billing_invoices (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id INT UNSIGNED NOT NULL,
    invoice_no VARCHAR(40) NOT NULL UNIQUE,
    period DATE NOT NULL,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    due_date DATE NOT NULL,
    status ENUM('unpaid','paid','cancelled') NOT NULL DEFAULT 'unpaid',
    paid_at DATETIME DEFAULT NULL,
    payment_method VARCHAR(50) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_billing_invoice_customer
        FOREIGN KEY (customer_id) REFERENCES billing_customers(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    UNIQUE KEY uq_customer_period (customer_id, period),
    INDEX idx_invoice_status (status),
    INDEX idx_invoice_due_date (due_date),
    INDEX idx_invoice_period (period)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
