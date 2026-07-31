CREATE TABLE IF NOT EXISTS reviews (
    id          INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    product_id  VARCHAR(60)     NOT NULL,
    user_id     INT UNSIGNED    NOT NULL,
    user_name   VARCHAR(200)    NOT NULL DEFAULT '',
    rating      TINYINT         NOT NULL DEFAULT 5,
    title       VARCHAR(200)    NOT NULL DEFAULT '',
    body        TEXT            NOT NULL,
    status      ENUM('approved','pending','rejected') NOT NULL DEFAULT 'approved',
    created_at  TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_product (product_id),
    INDEX idx_user    (user_id),
    INDEX idx_status  (status),
    CONSTRAINT fk_rev_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
