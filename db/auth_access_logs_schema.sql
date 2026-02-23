CREATE TABLE IF NOT EXISTS auth_access_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  username VARCHAR(100) NOT NULL,
  event_type VARCHAR(50) NOT NULL,
  success TINYINT(1) NOT NULL DEFAULT 0,
  ip_address VARCHAR(45) NOT NULL,
  user_agent VARCHAR(255) NOT NULL,
  details VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_auth_logs_created_at (created_at),
  INDEX idx_auth_logs_username (username),
  INDEX idx_auth_logs_user_id (user_id),
  INDEX idx_auth_logs_event_type (event_type),
  CONSTRAINT fk_auth_logs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
