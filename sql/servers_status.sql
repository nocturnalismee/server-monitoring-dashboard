-- SQL script to create a server status metrics table in InnoDB

CREATE TABLE server_status (
    id INT AUTO_INCREMENT PRIMARY KEY,
    server_name VARCHAR(255) NOT NULL,
    total_memory BIGINT NOT NULL,
    used_memory BIGINT NOT NULL,
    total_disk BIGINT NOT NULL,
    used_disk BIGINT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    memory_usage_percentage DECIMAL(5, 2) GENERATED ALWAYS AS (used_memory / total_memory * 100) STORED,
    disk_usage_percentage DECIMAL(5, 2) GENERATED ALWAYS AS (used_disk / total_disk * 100) STORED
) ENGINE=InnoDB;