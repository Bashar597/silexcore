ALTER TABLE orders ADD COLUMN IF NOT EXISTS payment_session_id VARCHAR(50) NOT NULL DEFAULT '';
ALTER TABLE orders ADD INDEX IF NOT EXISTS idx_session (payment_session_id);
