-- Contact messages table
-- Run once against payroll_db

CREATE TABLE IF NOT EXISTS contact_messages (
    message_id   INT          AUTO_INCREMENT PRIMARY KEY,
    full_name    VARCHAR(120) NOT NULL,
    email        VARCHAR(180) NOT NULL,
    subject      VARCHAR(100) NOT NULL DEFAULT 'General Inquiry',
    message      TEXT         NOT NULL,
    ip_address   VARCHAR(45)  NULL,
    is_read      TINYINT(1)   NOT NULL DEFAULT 0,
    replied_at   DATETIME     NULL,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
