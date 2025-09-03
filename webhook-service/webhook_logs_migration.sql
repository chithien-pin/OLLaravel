-- Create webhook_logs table for audit trail
CREATE TABLE IF NOT EXISTS webhook_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    stripe_event_id VARCHAR(255) NOT NULL UNIQUE,
    event_type VARCHAR(100) NOT NULL,
    payment_intent_id VARCHAR(255),
    status ENUM('success', 'error', 'warning', 'skipped') NOT NULL,
    error_message TEXT,
    processed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_stripe_event_id (stripe_event_id),
    INDEX idx_payment_intent_id (payment_intent_id),
    INDEX idx_status (status),
    INDEX idx_event_type (event_type),
    INDEX idx_processed_at (processed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;